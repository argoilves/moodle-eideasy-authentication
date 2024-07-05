<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   auth_eideasy
 * @author    Argo Ilves <argoilves@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


if (!defined('MOODLE_INTERNAL'))
    die('Direct access to this script is forbidden.');

require_once($CFG->dirroot . '/lib/authlib.php');
require_once($CFG->dirroot . '/lib/adminlib.php');
require_once($CFG->dirroot . '/user/lib.php');

class auth_plugin_eideasy extends auth_plugin_base {

    function __construct() {
        $this->authtype = 'eideasy';
        $this->pluginconfig = 'auth_'. $this->authtype;
        $this->config = get_config($this->pluginconfig);
    }

    function user_login($username, $password) {
        return false;
    }

    function authenticate_with_eideasy() {
        global $CFG, $SESSION;

        if (isloggedin()) {
            $goto = $SESSION->wantsurl ?? $CFG->wwwroot;
            redirect($goto);
        }

        $stateId = $this->get_random_state(10);

        $urlProperties = [
            "client_id=".$this->config->client_id,
            "redirect_uri=".$CFG->wwwroot.'/auth/eideasy/authenticate.php',
            "response_type=code"
        ];

        $SESSION->eideasyState = $stateId;
        $url = $CFG->eideasy_auth_url."?".implode("&", $urlProperties);
        redirect($url);
    }

    private function get_random_state($len = 20) {
        $bytes = random_bytes($len);
        return bin2hex($bytes);
    }

    function auth() {
        global $DB, $CFG, $SESSION;

        $loginUrl = $CFG->wwwroot.'/login/';
        $errorUrl = "$loginUrl?auth_failed=1";

        $code = optional_param('code', '', PARAM_TEXT);
        $scope = optional_param('scope', '', PARAM_TEXT);

        if (isset($SESSION->eideasyAuthError)) {
            unset($SESSION->eideasyAuthError);
        }

        unset($SESSION->eideasyState);
    
        $url = $CFG->eideasy_token_url;
        $data="code=$code&grant_type=authorization_code&client_id=$CFG->eideasy_client_id&client_secret=$CFG->eideasy_client_secret&redirect_uri=$CFG->wwwroot/auth/eideasy/authenticate.php";

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array("Content-Type: application/x-www-form-urlencoded"),
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => 1
        ));
       
        $result2 = curl_exec($curl);

        if (curl_error($curl)) {
            $SESSION->eideasyAuthError = curl_error($curl);
            curl_close($curl);            
            redirect($errorUrl);
        }
        curl_close($curl);

    try {
        $jsonDecoded = json_decode($result2);
           
            if (isset($jsonDecoded->error)) {
                redirect($errorUrl . urlencode($jsonDecoded->error));
            } else {
                $accessToken = $jsonDecoded->access_token;
                $userData = $this->get_user_data($accessToken);

                if ($userData) {
                    $ik = $userData->idcode;
                    $firstname = $userData->firstname;
                    $lastname = $userData->lastname;
                    $conditions = [
                        'idnumber' => $ik,
                        'firstname' => $firstname,
                        'lastname' => $lastname
                        ];                    
                    $usertologin = $DB->get_record('user', $conditions);

                    if ($usertologin === false) {
                        if ($this->config->allow_user_creation) {
                            $user = new stdClass();
                            $user->auth = $this->authtype;
                            $user->confirmed = 1;
                            $user->username = $ik;
                            $user->firstname = $userData->firstname;
                            $user->lastname = $userData->lastname;
                            $user->idnumber = $ik;
                            user_create_user($user, false, false);
                            $usertologin = $DB->get_record('user', $conditions);
                        } else {
                            $errorUrl = "$loginUrl?no_such_idnumber=1";
                            redirect($errorUrl);
                        }
                    }

                    if ($usertologin !== false) {
                        if (!$usertologin->country) {
                            $DB->update_record('user', $usertologin);
                        }
                        $this->check_for_not_allowed_roles($usertologin);
                        complete_user_login($usertologin);
                        $goto = $SESSION->wantsurl ?? $CFG->wwwroot;
                        redirect($goto);
                    }
                }
            }
        } catch (Exception $e) {
            redirect($errorUrl);
        }
        redirect($errorUrl);
    }

    function get_user_data($accessToken) {
        global $CFG, $SESSION;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $CFG->eideasy_user_data_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $accessToken"
        ]);

        $result = curl_exec($ch);

        if (curl_error($ch)) {
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        return json_decode($result);
    }

    private function check_for_not_allowed_roles($usertologin) {
        global $DB, $CFG;
        $roles = explode(',', ($CFG->eideasy_not_allowed_role ?? ''));
        foreach ($roles as $roleid) {
            if ('0' === $roleid) { 
                foreach (explode(',', $CFG->siteadmins) as $admin) {
                    if ((int) $admin == $usertologin->id) {
                        $goto = $CFG->wwwroot . '/login/?no_site_admin_login=1';
                        break 2;
                    }
                }
            } else {
                if ($DB->count_records('role_assignments', ['roleid' => $roleid, 'userid' => $usertologin->id])) {
                    $goto = $CFG->wwwroot . '/login/?not_allowed_to_login=1';
                    break;
                }
            }
        }

        if (isset($goto)) {
            redirect($goto);
        }
    }

    function loginpage_hook() {
        global $errormsg, $SESSION;
        if (optional_param('no_such_idnumber', false, PARAM_BOOL)) {
            $errormsg = get_string('nosuchidnumber', 'auth_eideasy');
        } else if (optional_param('auth_failed', false, PARAM_BOOL)) {
            $errormsg = get_string('auth_failed', 'auth_eideasy');
            $eideasyError = ($SESSION->eideasyAuthError ?? false);
            if ($eideasyError) {
                $errormsg .= "\r\n Eideasy: " . $eideasyError;
            }
        }
    }

    /** 
     * Logout user 
     */
    function logoutpage_hook() {
        require_logout();
    }

    /** 
     * Provide eideasy login button on login page 
     */
    function loginpage_idp_list($wantsurl) {
        global $CFG;
        return [
            [
                'url' => new moodle_url($CFG->wwwroot . '/auth/eideasy/login.php'),
                'iconurl' => $CFG->wwwroot . '/auth/eeid/img/auth.png',
                'name' => get_string('login_button', 'auth_eideasy')
            ],
        ];
    }
}
