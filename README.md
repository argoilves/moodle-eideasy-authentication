# Moodle eID Easy Authentication Plugin

This Moodle plugin integrates the eID Easy authentication service into Moodle.

## Installation

1. Clone this repository into your Moodle installation's `auth` directory or download as .zip and upload via `Site administration -> Plugins -> Install plugins` and follow the instructions.
2. Navigate to `Site administration -> Notifications` to install the plugin.
3. Activate the plugin in `Site administration -> Plugins -> Authentication`.

## Configuration

1. Configure the plugin in `Site administration -> Plugins -> Authentication -> Manage authentication`.
   - `client_id`: Your eID Easy client ID or TEST data
   - `client_secret`: Your eID Easy client secret or TEST data.
   - `not_allowed_roles`: Comma-separated list of role IDs that are not allowed to use this authentication method.
   - `allow_user_creation`: Allow new user creation during authentication.

## Testing

1. When testing the plugin, use test credentials according to the documentation [eidEasy](https://docs.eideasy.com/guide/test-environment.html).
2. Create test users for mid and smartID testing according to the documentation (ID numbers and phone number for mID).
3. Settings for testing must be added to the configuration file in the Moodle installation root directory.

    ```php
    $CFG->eideasy_auth_url = 'https://test.eideasy.com/oauth/authorize'; // TEST servers
    $CFG->eideasy_token_url = 'https://test.eideasy.com/oauth/access_token';
    $CFG->eideasy_user_data_url = 'https://test.eideasy.com/api/v2/user_data';
    $CFG->eideasy_client_id = ''; // test data from documentation
    $CFG->eideasy_client_secret = ''; // test data from documentation
    $CFG->eideasy_not_allowed_roles = ''; // Comma-separated role IDs
    $CFG->eideasy_allow_user_creation = false;
    ```

## Usage

1. After testing, get live credentials (client ID and secret) from eideasy.com, follow the configuration instructions, and update settings for live usage.
2. LIVE settings must be added both to the plugin settings pages and to the configuration conf.php file in the Moodle installation root directory.

    ```php
    $CFG->eideasy_auth_url = 'https://id.eideasy.com/oauth/authorize'; // LIVE servers
    $CFG->eideasy_token_url = 'https://id.eideasy.com/oauth/access_token'; 
    $CFG->eideasy_user_data_url = 'https://id.eideasy.com/api/v2/user_data';
    $CFG->eideasy_client_id = ''; // LIVE data from eideasy.com
    $CFG->eideasy_client_secret = ''; // LIVE data from eideasy.com
    $CFG->eideasy_not_allowed_roles = ''; // Comma-separated role IDs
    $CFG->eideasy_allow_user_creation = false; //set to true if users can be created during authentication
    ```

3. Users will be redirected to eID Easy for authentication when they attempt to log in.
4. Upon successful authentication via ID-card, Mobile-ID or SmartID, they will be redirectbac and logged in to the Moodle.

## License
This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
