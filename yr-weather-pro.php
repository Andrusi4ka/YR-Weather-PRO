<?php
/*
Plugin Name:       YR Weather Pro
Plugin URI:        https://github.com/Andrusi4ka/YR-Weather-PRO
Description:       Weather widgets powered by api.met.no (yr.no). Multiple instances, shortcode, geolocation, admin tabs, custom CSS.
Version:           9.0.0
Requires at least: 6.0
Requires PHP:      7.4
Author:            Relevant AS (Andrii Boiko)
Author URI:        https://relevant.no/
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Update URI:        https://github.com/Andrusi4ka/YR-Weather-PRO
*/

// "Update URI": updates come from this plugin's GitHub releases
// (yrwp_updates()), never from a wordpress.org plugin of the same folder name.

if (!defined('ABSPATH')) exit;

define('YRWP_VERSION', '9.0.0');
define('YRWP_REPOSITORY', 'https://github.com/Andrusi4ka/YR-Weather-PRO/');
define('YRWP_FILE', __FILE__);
define('YRWP_PATH', plugin_dir_path(__FILE__));
define('YRWP_URL', plugin_dir_url(__FILE__));

require_once YRWP_PATH . 'includes/class-yrwp-plugin.php';

function yrwp_boot() {
    \YRWP\Plugin::instance();
}
add_action('plugins_loaded', 'yrwp_boot');

/**
 * Updates from the GitHub releases, shown under Plugins like any other update.
 */
function yrwp_updates() {
    $library = YRWP_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';
    // Only where WordPress looks for updates: the admin and its cron.
    if ((!is_admin() && !wp_doing_cron()) || !file_exists($library)) {
        return;
    }
    require_once $library;

    $checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(YRWP_REPOSITORY, YRWP_FILE, 'yr-weather-pro');
    // The release's zip, which holds just the plugin (not the repository's dev files).
    $checker->getVcsApi()->enableReleaseAssets('/yr-weather-pro\.zip($|[?&#])/i');
}
add_action('plugins_loaded', 'yrwp_updates');
