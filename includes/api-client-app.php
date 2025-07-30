<?php
/**
 * Handles all API communication with the main aiohm.app website.
 *
 * *** UPDATED: Removed conflicting AJAX handler functions. This file is now only for the AIOHM_App_API_Client class. ***
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('AIOHM_App_API_Client')) :
    class AIOHM_App_API_Client {

        private $base_url = 'https://www.aiohm.app/wp-json/aiohm/v1/';

        public function __construct() {
            // Constructor can be used for future setup if needed.
        }
        
        /**
         * Check if this is demo version and handle demo license
         */
        private function is_demo_version() {
            return defined('AIOHM_KB_VERSION') && AIOHM_KB_VERSION === 'DEMO';
        }
        
        /**
         * Get demo license validation
         */
        private function get_demo_license_response($email) {
            if ($email === 'contact@ohm.events') {
                return [
                    'success' => true,
                    'user_details' => [
                        'email' => 'contact@ohm.events',
                        'display_name' => 'Demo User',
                        'membership_level' => 'demo',
                        'level_name' => 'Demo Access',
                        'start_date' => date('Y-m-d'),
                        'end_date' => date('Y-m-d', strtotime('+30 days')),
                        'has_club_access' => false,
                        'has_private_access' => false,
                        'demo_mode' => true
                    ]
                ];
            }
            
            return new WP_Error('demo_invalid_email', 'Demo version only accepts contact@ohm.events as license email.');
        }

        private function make_request($endpoint, $args = []) {
            $request_url = $this->base_url . $endpoint;
            $request_url = add_query_arg($args, $request_url);

            $response = wp_remote_get($request_url, ['timeout' => 20]);

            if (is_wp_error($response)) {
                return $response;
            }

            $body_content = wp_remote_retrieve_body($response);
            $data = json_decode($body_content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error('api_json_decode_error', 'API response could not be decoded.');
            }

            if (isset($data['error'])) {
                return new WP_Error('api_error', $data['error']);
            }

            return $data;
        }

        /**
         * Sends a verification code to the user's email address.
         *
         * @param string $email The user's email address.
         * @return array|WP_Error
         */
        public function send_verification_code($email) {
            if (empty($email) || !is_email($email)) {
                return new WP_Error('invalid_email', 'A valid email is required.');
            }
            
            // Handle demo version
            if ($this->is_demo_version()) {
                if ($email === 'contact@ohm.events') {
                    return [
                        'success' => true,
                        'message' => 'Demo verification code sent! Use code: DEMO123'
                    ];
                } else {
                    return new WP_Error('demo_email_required', 'Demo version requires contact@ohm.events email address.');
                }
            }

            return $this->make_request('send-verification-code', ['email' => $email]);
        }

        /**
         * Verifies the code and gets membership details if valid.
         *
         * @param string $email The user's email address.
         * @param string $code The verification code.
         * @return array|WP_Error
         */
        public function verify_code_and_get_details($email, $code) {
            if (empty($email) || !is_email($email)) {
                return new WP_Error('invalid_email', 'A valid email is required.');
            }
            
            if (empty($code)) {
                return new WP_Error('invalid_code', 'Verification code is required.');
            }
            
            // Handle demo version
            if ($this->is_demo_version()) {
                if ($email === 'contact@ohm.events' && $code === 'DEMO123') {
                    return $this->get_demo_license_response($email);
                } else {
                    return new WP_Error('demo_invalid_credentials', 'Demo version requires contact@ohm.events email and DEMO123 code.');
                }
            }

            return $this->make_request('verify-code-and-get-details', [
                'email' => $email,
                'code' => $code
            ]);
        }

        /**
         * Gets all available membership details for a user by email.
         * Note: This method is now deprecated in favor of the verification flow.
         *
         * @param string $email The user's email address.
         * @return array|WP_Error
         */
        public function get_member_details_by_email($email) {
            if (empty($email) || !is_email($email)) {
                return new WP_Error('invalid_email', 'A valid email is required.');
            }

            return $this->make_request('get-member-details', ['email' => $email]);
        }
    }
endif; // End class_exists check