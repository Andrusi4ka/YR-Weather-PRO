<?php
/*
Plugin Name: YR Weather Pro
Description: Weather widgets powered by api.met.no (yr.no). Multiple instances, shortcode, geolocation, admin tabs, custom CSS.
Version: 8.4
Author: Relevant AS (Andrii Boiko)
Author URI: https://relevant.no/
Requires at least: 6.0
Requires PHP: 7.4
*/

if (!defined('ABSPATH')) exit;

define('YRWP_VERSION', '8.4.0');
define('YRWP_PATH', plugin_dir_path(__FILE__));
define('YRWP_URL', plugin_dir_url(__FILE__));
define( 'YRWP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'YRWP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'YRWP_BASENAME', plugin_basename( __FILE__ ) );

require_once YRWP_PATH . 'includes/class-yrwp-plugin.php';

function yrwp_boot() {
    \YRWP\Plugin::instance();
}
add_action('plugins_loaded', 'yrwp_boot');
