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

}
