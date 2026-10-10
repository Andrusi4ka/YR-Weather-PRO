<?php
namespace YRWP;

if (!defined('ABSPATH')) exit;

class Admin {
    const OPT_KEY = 'yrwp_options';
    private const ACCESS_KEY_LENGTH = 32;

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('wp_ajax_yrwp_city_search', [__CLASS__, 'ajax_city_search']);
    }

    public static function menu() {
        add_menu_page(
            I18N::t('yr_weather'),
            I18N::t('yr_weather'),
            'manage_options',
            'yrwp_main',
            [__CLASS__, 'render'],
            'dashicons-cloud',
            56
        );

        add_submenu_page(
            'yrwp_main',
            I18N::t('settings'),
            I18N::t('settings'),
            'manage_options',
            'yrwp_main',
            [__CLASS__, 'render']
        );

        add_submenu_page(
            'yrwp_main',
            I18N::t('yr_weather_info'),
            I18N::t('info'),
            'manage_options',
            'yrwp_info',
            [__CLASS__, 'render_info']
        );
    }

    public static function register_settings() {
        register_setting('yrwp_general', self::OPT_KEY, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_options']
        ]);

        add_settings_section('yrwp_sec_general', I18N::t('settings'), '__return_false', 'yrwp_general');
        add_settings_field('language', I18N::t('language'), [__CLASS__, 'field_language'], 'yrwp_general', 'yrwp_sec_general');
        add_settings_field('user_agent_email', I18N::t('contact_email'), [__CLASS__, 'field_email'], 'yrwp_general', 'yrwp_sec_general');
        add_settings_field('access_key', I18N::t('access_key'), [__CLASS__, 'field_access_key'], 'yrwp_general', 'yrwp_sec_general');
    }

    public static function sanitize_options($opts) {
        $out = [];
        $out['language'] = I18N::sanitize_language($opts['language'] ?? 'en');
        $out['user_agent_email'] = sanitize_email($opts['user_agent_email'] ?? '');
        $out['access_key'] = self::sanitize_access_key($opts['access_key'] ?? '');
        return $out;
    }

    public static function sanitize_access_key($value): string {
        $value = preg_replace('/\s+/', '', sanitize_text_field((string) $value));
        return strlen($value) === self::ACCESS_KEY_LENGTH ? $value : '';
    }

    public static function get_access_key(): string {
        $opts = get_option(self::OPT_KEY, []);
        return self::sanitize_access_key($opts['access_key'] ?? '');
    }

    public static function has_valid_access_key(): bool {
        return self::get_access_key() !== '';
    }

    public static function field_language() {
        $opts = get_option(self::OPT_KEY, []);
        $selected = I18N::sanitize_language($opts['language'] ?? 'en');
        $languages = I18N::get_supported_languages();

        echo '<select name="' . esc_attr(self::OPT_KEY) . '[language]">';
        foreach ($languages as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($selected, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public static function field_email() {
        $opts = get_option(self::OPT_KEY, []);
        $email = $opts['user_agent_email'] ?? '';
        echo '<input type="email" class="regular-text" name="' . esc_attr(self::OPT_KEY) . '[user_agent_email]" value="' . esc_attr($email) . '" placeholder="name@example.com">';
        if ($email) {
            echo '<p class="description">' . wp_kses_post(str_replace('{email}', '<strong>' . esc_html($email) . '</strong>', I18N::t('saved_email'))) . '</p>';
        } else {
            echo '<p class="description">' . esc_html(I18N::t('user_agent_help')) . '</p>';
        }
    }

    public static function field_access_key() {
        $key = self::get_access_key();
        $is_locked = $key !== '';
        if ($is_locked) {
            echo '<input type="hidden" name="' . esc_attr(self::OPT_KEY) . '[access_key]" value="' . esc_attr($key) . '">';
        }
        echo '<input type="text" class="regular-text" value="' . esc_attr($key) . '" maxlength="' . esc_attr((string) self::ACCESS_KEY_LENGTH) . '" placeholder="1234567890abcdef1234567890abcdef"' . ($is_locked ? ' disabled="disabled" readonly="readonly"' : ' name="' . esc_attr(self::OPT_KEY) . '[access_key]"') . '>';
        if ($key !== '') {
            echo '<p class="description">' . esc_html(I18N::t('access_key_saved')) . '</p>';
        } else {
            echo '<p class="description">' . esc_html(I18N::t('access_key_help')) . '</p>';
        }
    }

    public static function render() {
        if (!current_user_can('manage_options')) return;

        echo '<div class="wrap"><h1>' . esc_html(I18N::t('settings')) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('yrwp_general');
        do_settings_sections('yrwp_general');
        submit_button(I18N::t('save'));
        echo '</form>';

        echo '</div>';
    }

    public static function render_info() {
        if (!current_user_can('manage_options')) return;

        echo '<div class="wrap"><h1>' . esc_html(I18N::t('info')) . '</h1>';
        self::render_info_content();
        echo '</div>';
    }

    private static function render_info_content() {
        $forecast_api = 'https://api.met.no/weatherapi/locationforecast/2.0/compact';
        $geocoding_api = 'https://geocoding-api.open-meteo.com/v1/search';
        $icons_path = YRWP_URL . 'assets/icons/';
        $content = I18N::info_content();
        $replacements = [
            '{forecast_api}' => esc_html($forecast_api),
            '{geocoding_api}' => esc_html($geocoding_api),
            '{icons_path}' => esc_html($icons_path),
        ];

        echo '<div class="yrwp-info-page">';
        echo '<article class="yrwp-info-article">';
        echo '<header class="yrwp-info-header">';
        echo '<h2>' . esc_html($content['header_title']) . '</h2>';
        echo '<p>' . esc_html($content['header_intro']) . '</p>';
        echo '</header>';

        foreach (($content['sections'] ?? []) as $section) {
            echo '<section class="yrwp-info-section">';
            echo '<h3>' . esc_html((string) ($section['title'] ?? '')) . '</h3>';
            echo '<ul>';

            foreach (($section['items'] ?? []) as $item) {
                $item_html = strtr((string) $item, $replacements);
                echo '<li>' . wp_kses_post($item_html) . '</li>';
            }

            echo '</ul>';

            if (!empty($section['description'])) {
                $description_html = strtr((string) $section['description'], $replacements);
                echo '<p class="description">' . wp_kses_post($description_html) . '</p>';
            }

            echo '</section>';
        }

        $footer_text = I18N::t('version_footer', [
            'label' => I18N::t('version_label'),
            'version' => YRWP_VERSION,
            'year' => gmdate('Y'),
        ]);

        echo '<footer class="yrwp-info-footer">';
        echo '<div class="yrwp-info-footer__meta">' . esc_html($footer_text) . '</div>';
        echo '<a class="yrwp-info-footer__brand" href="https://relevant.no/" target="_blank" rel="noopener noreferrer">';
        echo '<img src="' . esc_url(YRWP_URL . 'assets/images/Relevant-logo.png') . '" alt="Relevant logo">';
        echo '</a>';
        echo '</footer>';

        echo '</article>';
        echo '</div>';
    }

    public static function assets($hook) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $page = sanitize_key($_GET['page'] ?? '');
        $is_our = $screen && (
            $screen->id === 'toplevel_page_yrwp_main' ||
            $screen->post_type === CPT::POST_TYPE ||
            $page === 'yrwp_info'
        );

        if (!$is_our) return;

        wp_enqueue_style('yrwp-admin', YRWP_URL . 'assets/style/admin.css', [], YRWP_VERSION);
        wp_enqueue_script('yrwp-admin', YRWP_URL . 'assets/admin.js', ['jquery'], YRWP_VERSION, true);

        $settings = wp_enqueue_code_editor(['type' => 'text/css']);
        $template_settings = wp_enqueue_code_editor(['type' => 'text/html']);
        wp_localize_script('yrwp-admin', 'YRWP_ADMIN', [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('yrwp_admin'),
            'codeEditor' => $settings ? $settings : null,
            'templateEditor' => $template_settings ? $template_settings : null,
            'tokenCopied' => I18N::get_language() === 'uk' ? 'Токен скопійовано.' : (I18N::get_language() === 'nb' ? 'Token kopiert.' : 'Token copied to clipboard.'),
            'shortcodeCopied' => I18N::get_language() === 'uk' ? 'Шорткод скопійовано.' : (I18N::get_language() === 'nb' ? 'Shortcode kopiert.' : 'Shortcode copied to clipboard.'),
        ]);
        wp_enqueue_script('code-editor');
        wp_enqueue_style('code-editor');
    }

    public static function ajax_city_search() {
        check_ajax_referer('yrwp_admin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }
        if (!self::has_valid_access_key()) {
            wp_send_json_error(['message' => I18N::t('access_key_required')], 403);
        }

        $q = sanitize_text_field($_GET['q'] ?? '');
        if (mb_strlen($q) < 2) wp_send_json_success(['results' => []]);

        $url = add_query_arg([
            'name' => $q,
            'count' => 8,
            'language' => 'en',
            'format' => 'json'
        ], 'https://geocoding-api.open-meteo.com/v1/search');

        $resp = wp_remote_get($url, ['timeout' => 10]);
        if (is_wp_error($resp)) wp_send_json_error(['message' => 'Geocoding unavailable']);

        $data = json_decode(wp_remote_retrieve_body($resp), true);
        $results = [];

        foreach (($data['results'] ?? []) as $row) {
            $results[] = [
                'label' => trim(($row['name'] ?? '') . ', ' . ($row['admin1'] ?? '') . ', ' . ($row['country'] ?? '')),
                'lat' => $row['latitude'] ?? null,
                'lon' => $row['longitude'] ?? null,
                'timezone' => MET::normalize_timezone($row['timezone'] ?? ''),
            ];
        }

        wp_send_json_success(['results' => $results]);
    }
}
