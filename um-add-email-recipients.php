<?php
/**
 * Plugin Name:     Ultimate Member - Additional Email Recipients
 * Description:     Extension to Ultimate Member for additional CC: and BCC: to UM Notification Emails and replacement address for User email. Additional CC: email addresses depending on meta field values.
 * Version:         3.1.0
 * Requires PHP:    7.4
 * Author:          Miss Veronica
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:      https://github.com/MissVeronica
 * Text Domain:     ultimate-member
 * Domain Path:     /languages
 * UM version:      2.8.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; 
if ( ! class_exists( 'UM' ) ) return;


class UM_Additional_Email_Recipients {

    public $template = '';
    public $registration_user_id = '';
    public $email_options = array( '_custom_cc'  => 'cc: ',
                                   '_custom_bcc' => 'Bcc: ' );

    function __construct() {

        add_filter( 'wp_mail',                                array( $this, 'um_add_email_recipients_wp_mail' ), 10, 1 );
        add_action( 'um_before_email_notification_sending',   array( $this, 'um_add_email_recipients_setup' ), 10, 3 );
        add_filter( 'um_admin_settings_email_section_fields', array( $this, 'um_admin_settings_email_section_email_recipients' ), 10, 2 );
        add_action( 'um_registration_set_extra_data',         array( $this, 'um_registration_set_extra_data_email_recipients' ), 10, 2 );
        add_action( 'um_user_pre_updating_profile',           array( $this, 'um_user_pre_updating_profile_email_recipients' ), 10, 2 );
        add_action( 'um_when_status_is_set',                  array( $this, 'um_when_status_is_set_email_recipients' ), 10, 1 );
        add_action( 'um_account_pre_update_profile',          array( $this, 'um_user_pre_updating_profile_email_recipients' ), 10, 2 );

    }

    public function um_add_email_recipients_wp_mail( $args ) {

        if ( ! empty( $this->template ) && ! empty( $this->registration_user_id )) {

            um_fetch_user( $this->registration_user_id );

            $custom_meta_key = trim( sanitize_text_field( UM()->options()->get( $this->template . '_custom_meta_key' )));
            $custom_email = '';

            if ( ! empty( $custom_meta_key )) {

                $form_field_value = um_user( $custom_meta_key );

                if ( ! empty( $form_field_value )) {

                    $custom_meta_key_emails = array_map( 'trim', explode( "\n", UM()->options()->get( $this->template . '_custom_meta_key_emails' )));

                    if ( is_array( $custom_meta_key_emails )) {

                        foreach( $custom_meta_key_emails as $custom_meta_key_email ) {
                            $field_email_pair = array_map( 'trim', explode( ':', $custom_meta_key_email ));

                            if( $field_email_pair[0] == $form_field_value ) {

                                if ( ! empty( $field_email_pair[1] )) {
                                    $custom_email = $field_email_pair[1];
                                    // possible to have a comma separated email list
                                }
                                break;
                            }
                        }
                    }
                }
            }

            foreach( $this->email_options as $option => $carbon_copy ) {

                $emails = trim( UM()->options()->get( $this->template . $option ));

                if ( $option == '_custom_cc' && ! empty( $custom_email )) {
                    if ( ! empty( $emails )) {
                        $emails .= ',';
                    }
                    $emails .= $custom_email;
                }

                if ( ! empty( $emails )) {

                    $emails = array_map( 'trim', explode( ',', $emails ));
                    foreach( $emails as $email ) {

                        $email = filter_var( sanitize_email( $email ), FILTER_VALIDATE_EMAIL );
                        if ( ! empty( $email )) {

                            if ( is_array( $args['headers'] )) {
                                $args['headers'][] = $carbon_copy . $email;

                            } else {

                                $args['headers'] .= $carbon_copy . $email . "\r\n";
                            }
                        }
                    }
                }
            }

            $custom_role_emails = UM()->options()->get( $this->template . '_custom_role_emails' );

            if ( ! empty( $custom_role_emails )) {

                $role_emails = array_map( 'sanitize_text_field', $custom_role_emails );
                $email_list = get_users( array(
                                    'fields'   => array( 'ID', 'user_email' ),
                                    'role__in' => $role_emails,
                            ));

                $carbon_copy = $this->email_options['_custom_cc'];
                if ( UM()->options()->get( $this->template . '_custom_role_bcc' ) == 1 ) {
                    $carbon_copy = $this->email_options['_custom_bcc'];
                }

                foreach( $email_list as $email ) {

                    if ( ! empty( $email->user_email )) {

                        if ( is_array( $args['headers'] )) {
                            $args['headers'][] = $carbon_copy . $email->user_email;

                        } else {

                            $args['headers'] .= $carbon_copy . $email->user_email . "\r\n";
                        }
                    }
                }
            }

            $replace_email = trim( UM()->options()->get( $this->template . '_custom_replace_email' ));

            if ( ! empty( $replace_email ) && ! empty( um_user( $replace_email ) )) {

                $replace_email = filter_var( sanitize_email( um_user( $replace_email ) ), FILTER_VALIDATE_EMAIL );
                if ( ! empty( $replace_email )) {

                    if ( UM()->options()->get( $this->template . '_custom_replace_email_both' ) == 1 ) {
                        $args['to'] = implode( ', ', array( $args['to'], $replace_email ));

                    } else {

                        $args['to'] = $replace_email;
                    }
                }
            }
        }

        return $args;
    }

    public function um_admin_settings_email_section_email_recipients( $section_fields, $email_key ) {

        global $wp_roles;

        foreach ( $wp_roles->roles as $key => $value ) {
            $role_options[$key] = $value['name'];
        }

        $section_fields[] = array(
                    'id'            => $email_key . '_custom_cc',
                    'type'          => 'text',
                    'label'         => __( 'Additional Email Recipients - cc:', 'ultimate-member' ),
                    'conditional'   => array( $email_key . '_on', '=', 1 ),
                    'tooltip'       => __( 'Comma separated e-mail adresses', 'ultimate-member' )
                );

        $section_fields[] = array(
                    'id'            => $email_key . '_custom_bcc',
                    'type'          => 'text',
                    'label'         => __( 'Additional Email Recipients - Bcc:', 'ultimate-member' ),
                    'conditional'   => array( $email_key . '_on', '=', 1 ),
                    'tooltip'       => __( 'Comma separated e-mail adresses', 'ultimate-member' )
                );

        $section_fields[] = array(
                    'id'            => $email_key . '_custom_replace_email',
                    'type'          => 'text',
                    'size'          => 'small',
                    'label'         => __( 'Additional Email Recipients - Extra UM User email address', 'ultimate-member' ),
                    'tooltip'       => __( 'Extra e-mail address meta_key to be used instead of UM User email', 'ultimate-member' ),
                    'conditional'   => array( $email_key . '_on', '=', 1 ),
                );

        $section_fields[] = array(
                    'id'            => $email_key . '_custom_replace_email_both',
                    'type'          => 'checkbox',
                    'label'         => __( 'Additional Email Recipients - Send to both Extra and UM User email address', 'ultimate-member' ),
                    'tooltip'       => __( 'Click to send to both Extra e-mail and UM User email address', 'ultimate-member' ),
                    'conditional'   => array( $email_key . '_on', '=', 1 ),
                );

        $section_fields[] = array(
                    'id'            => $email_key . '_custom_meta_key',
                    'type'          => 'text',
                    'size'          => 'small',
                    'label'         => __( 'Additional Email Recipients - Meta Key for Field additional cc: email', 'ultimate-member' ),
                    'tooltip'       => __( 'Enter the meta_key name for Form field value dependent for an additional cc: email', 'ultimate-member' ),
                    'conditional'   => array( $email_key . '_on', '=', 1 ),
                );

        $section_fields[] = array(
                    'id'            => $email_key . '_custom_meta_key_emails',
                    'type'          => 'textarea',
                    'size'          => 'medium',
                    'label'         => __( 'Additional Email Recipients - Form Field value : Email address', 'ultimate-member' ),
                    'tooltip'       => __( 'Enter the relation for Form field values for an additional cc: email address colon separated and one pair per line', 'ultimate-member' ),
                    'conditional'   => array( $email_key . '_on', '=', 1 ),
                );

        $section_fields[] = array(
                    'id'            => $email_key . '_custom_role_emails',
                    'type'          => 'select',
                    'multi'         => true,
                    'size'          => 'medium',
                    'options'       => $role_options,
                    'label'         => __( 'Additional Email Recipients - Users with Roles', 'ultimate-member' ),
                    'tooltip'       => __( 'Select the Role names for additional cc: or Bcc: emails.', 'ultimate-member' ),
                    'conditional'   => array( $email_key . '_on', '=', 1 ),
                );

        $section_fields[] = array(
                    'id'            => $email_key . '_custom_role_bcc',
                    'type'          => 'checkbox',
                    'label'         => __( 'Additional Email Recipients - Users with Roles Bcc:', 'ultimate-member' ),
                    'tooltip'       => __( 'Click to send to Users with selected Roles as Bcc: email, unclick for cc: email', 'ultimate-member' ),
                    'conditional'   => array( $email_key . '_on', '=', 1 ),
                );

        return $section_fields;
    }

    public function um_add_email_recipients_setup( $email, $template, $args ) {

        if ( ! empty( $email ) && ! empty( $template ) ) {

            $this->template = $template;
            if ( empty( $this->registration_user_id ) && ! empty( um_user( 'ID' ) )) {
                $this->registration_user_id = um_user( 'ID' );
            }
        }
    }

    public function um_registration_set_extra_data_email_recipients( $user_id, $args ) {

        $this->registration_user_id = $user_id;
    }

    public function um_user_pre_updating_profile_email_recipients( $to_update, $user_id ) {

        $this->registration_user_id = $user_id;
    }

    public function um_when_status_is_set_email_recipients( $user_id ) {

        $this->registration_user_id = $user_id;
    }

}

new UM_Additional_Email_Recipients();
