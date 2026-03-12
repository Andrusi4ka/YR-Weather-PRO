<?php

namespace YRWP;

if (!defined('ABSPATH')) exit;

final class Plugin
{
    private static $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct()
    {
        require_once YRWP_PATH . 'includes/class-yrwp-cpt.php';
        require_once YRWP_PATH . 'includes/class-yrwp-i18n.php';
        require_once YRWP_PATH . 'includes/class-yrwp-admin.php';
        require_once YRWP_PATH . 'includes/class-yrwp-met.php';
        require_once YRWP_PATH . 'includes/class-yrwp-shortcode.php';
        require_once YRWP_PATH . 'includes/class-yrwp-rest.php';
        require_once YRWP_PATH . 'includes/class-yrwp-icons.php';

        CPT::init();
        Admin::init();
        Icons::init();
        Shortcode::init();
        Rest::init();
    }

    public static function locate_template($template_name)
    {

        $template_name = ltrim($template_name, '/');

        $theme_path = get_stylesheet_directory() . '/yr-weather-pro/' . $template_name;
        $plugin_path = YRWP_PLUGIN_PATH . 'templates/' . $template_name;

        if (file_exists($theme_path)) {
            return $theme_path;
        }

        return $plugin_path;
    }

    public static function render_template($template_name, $data = [])
    {

        $template = self::locate_template($template_name);

        if (! file_exists($template)) {
            return '';
        }

        ob_start();

        if (! empty($data)) {
            extract($data);
        }

        include $template;

        return ob_get_clean();
    }
}
