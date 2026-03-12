<?php

namespace YRWP;

if (!defined('ABSPATH')) exit;

class CPT
{
    const POST_TYPE = 'yrwp_widget';
    private const LEGACY_NINE_DAY_KEY = '1' . '4';
    private const LEGACY_NINE_DAY_ITEM_KEY = '1' . '4_item';
    private const LEGACY_NINE_DAY_PREFIX = '1' . '4-';

    public static function init()
    {
        add_action('init', [__CLASS__, 'register']);
        add_action('add_meta_boxes', [__CLASS__, 'metaboxes']);
        add_action('save_post_' . self::POST_TYPE, [__CLASS__, 'save'], 10, 2);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [__CLASS__, 'add_admin_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [__CLASS__, 'render_admin_column'], 10, 2);
    }

    public static function register()
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => I18N::t('yr_weather'),
                'singular_name' => I18N::t('weather_widget'),
                'add_new_item' => I18N::t('add_weather_widget'),
                'edit_item' => I18N::t('edit_weather_widget'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'yrwp_main',
            'supports' => ['title'],
        ]);
    }

    public static function metaboxes()
    {
        add_meta_box('yrwp_settings', I18N::t('widget_settings'), [__CLASS__, 'render_metabox'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('yrwp_shortcode', I18N::t('shortcode'), [__CLASS__, 'render_shortcode_box'], self::POST_TYPE, 'side', 'default');
    }

    public static function render_shortcode_box($post)
    {
        echo self::get_shortcode_markup((int) $post->ID, true);
        echo '<p style="margin-top:8px;">' . esc_html(I18N::t('copy_paste')) . '</p>';
    }

    public static function add_admin_columns(array $columns): array
    {
        $updated = [];

        foreach ($columns as $key => $label) {
            $updated[$key] = $label;

            if ($key === 'title') {
                $updated['yrwp_shortcode'] = I18N::t('shortcode');
            }
        }

        if (!isset($updated['yrwp_shortcode'])) {
            $updated['yrwp_shortcode'] = I18N::t('shortcode');
        }

        return $updated;
    }

    public static function render_admin_column(string $column, int $post_id): void
    {
        if ($column !== 'yrwp_shortcode') {
            return;
        }

        echo self::get_shortcode_markup($post_id);
    }

    private static function get_shortcode_markup(int $post_id, bool $is_sidebar = false): string
    {
        $shortcode = '[yr_weather id="' . $post_id . '"]';
        $wrapper_class = $is_sidebar ? 'yrwp-shortcode-box is-sidebar' : 'yrwp-shortcode-box';
        $button_class = $is_sidebar ? 'button button-small' : 'button button-small';
        $code_style = $is_sidebar ? ' style="user-select:all;display:block;padding:8px;"' : '';
        $copy_label = I18N::t('copy');

        return sprintf(
            '<div class="%1$s"><code class="yrwp-shortcode-value"%2$s>%3$s</code><button type="button" class="%4$s yrwp-copy-shortcode" data-shortcode="%5$s">%6$s</button></div>',
            esc_attr($wrapper_class),
            $code_style,
            esc_html($shortcode),
            esc_attr($button_class),
            esc_attr($shortcode),
            esc_html($copy_label)
        );
    }

    private static function get_template_preview_map(): array
    {
        return [
            'current-default' => 'screen-2.png',
            'current-compact' => 'screen-3.png',
            'current-manual' => 'screen-4.gif',
            'current-json' => 'screen-5.gif',
            'today-default' => 'screen-1.png',
            'today-compact' => 'screen-6.png',
            'today-manual' => 'screen-7.gif',
            'today-json' => 'screen-5.gif',
            '7-default' => 'screen-8.png',
            '7-compact' => 'screen-10.png',
            '7-manual' => 'screen-9.png',
            '7-json' => 'screen-5.gif',
            '9-default' => 'screen-11.png',
            '9-compact' => 'screen-12.png',
            '9-manual' => 'screen-9.png',
            '9-json' => 'screen-5.gif',
        ];
    }

    private static function get_template_preview_url(string $template_slug): string
    {
        $map = self::get_template_preview_map();
        $file = $map[$template_slug] ?? 'screen_1.png';

        return YRWP_URL . 'assets/images/' . ltrim($file, '/');
    }

    private static function render_template_option(string $period, string $variant, string $label, string $current_value, string $period_label, string $label_style = ''): string
    {
        $template_slug = $period . '-' . $variant;
        $preview_title = $period_label . ' / ' . $label;
        $style_attr = $label_style !== '' ? ' style="' . esc_attr($label_style) . '"' : '';

        return sprintf(
            '<label class="yrwp-template-option"%1$s><input type="radio" name="yrwp[template_map][%2$s]" value="%3$s" %4$s> <span class="yrwp-template-option__label">%5$s</span><span class="yrwp-template-preview-trigger" data-preview-url="%6$s" data-preview-title="%7$s" aria-hidden="true">?</span></label>',
            $style_attr,
            esc_attr($period),
            esc_attr($template_slug),
            checked($current_value, $template_slug, false),
            esc_html($label),
            esc_url(self::get_template_preview_url($template_slug)),
            esc_attr($preview_title)
        );
    }

    private static function render_template_preview_modal(): string
    {
        return '<div id="yrwp-template-preview-modal" class="yrwp-template-preview-modal" aria-hidden="true">
            <div class="yrwp-template-preview-modal__inner">
                <img class="yrwp-template-preview-modal__image" src="" alt="">
            </div>
        </div>';
    }

    public static function render_metabox($post)
    {
        wp_nonce_field('yrwp_save_widget', 'yrwp_nonce');

        $meta = self::get_meta($post->ID);

        $enabled = !empty($meta['enabled']) ? 1 : 0;
        $title_show = !empty($meta['title_show']) ? 1 : 0;

        $location_mode = $meta['location_mode'] ?? 'manual_coords';
        $lat = $meta['lat'] ?? '';
        $lon = $meta['lon'] ?? '';
        $city_name = $meta['city_name'] ?? '';
        $city_lat = $meta['city_lat'] ?? '';
        $city_lon = $meta['city_lon'] ?? '';
        $city_timezone = $meta['city_timezone'] ?? '';

        $refresh = isset($meta['refresh']) ? intval($meta['refresh']) : 1800;
        $custom_class = $meta['custom_class'] ?? '';
        $custom_css = $meta['custom_css'] ?? '';

        $period = $meta['period'] ?? 'current';
        $template_map = $meta['template_map'] ?? [
            'current' => 'current-default',
            'today' => 'today-default',
            '7' => '7-default',
            '9' => '9-default',
        ];

        $custom_short_text = $meta['custom_short_text'] ?? '';
        $custom_long_text = $meta['custom_long_text'] ?? '';
        $manual_templates = isset($meta['manual_templates']) && is_array($meta['manual_templates'])
            ? $meta['manual_templates']
            : [];
        $manual_template_current = $manual_templates['current'] ?? self::default_manual_template('current');
        $manual_template_today = $manual_templates['today'] ?? self::default_manual_template('today');
        $manual_template_today_item = $manual_templates['today_item'] ?? self::default_manual_item_template('today');
        $manual_template_7 = $manual_templates['7'] ?? self::default_manual_template('7');
        $manual_template_7_item = $manual_templates['7_item'] ?? self::default_manual_item_template('7');
        $manual_template_9 = $manual_templates['9'] ?? self::default_manual_template('9');
        $manual_template_9_item = $manual_templates['9_item'] ?? self::default_manual_item_template('9');
        $today_manual_step = isset($meta['today_manual_step']) ? max(1, min(3, intval($meta['today_manual_step']))) : 1;
        $manual_show_units = !isset($meta['manual_show_units']) || (int) $meta['manual_show_units'] === 1 ? 1 : 0;
        $manual_show_today_7 = !isset($meta['manual_show_today_7']) || (int) $meta['manual_show_today_7'] === 1 ? 1 : 0;
        $manual_show_today_9 = !isset($meta['manual_show_today_9']) || (int) $meta['manual_show_today_9'] === 1 ? 1 : 0;
        $periods = [
            'current' => I18N::t('current_weather'),
            'today' => I18N::t('today'),
            '7' => I18N::t('days_7'),
            '9' => I18N::t('days_9'),
        ];

        echo '<table class="form-table"><tbody>';

        echo '<tr><th scope="row">' . esc_html(I18N::t('enabled')) . '</th><td>
            <label><input type="checkbox" name="yrwp[enabled]" value="1" ' . checked(1, $enabled, false) . '> ' . esc_html(I18N::t('show_on_site')) . '</label>
        </td></tr>';

        echo '<tr><th scope="row">' . esc_html(I18N::t('forecast_period')) . '</th><td>
            <label><input type="radio" name="yrwp[period]" value="current" ' . checked('current', $period, false) . '> ' . esc_html(I18N::t('current_weather')) . '</label><br>
            <label><input type="radio" name="yrwp[period]" value="today" ' . checked('today', $period, false) . '> ' . esc_html(I18N::t('today')) . '</label><br>
            <label><input type="radio" name="yrwp[period]" value="7" ' . checked('7', $period, false) . '> ' . esc_html(I18N::t('days_7')) . '</label><br>
            <label><input type="radio" name="yrwp[period]" value="9" ' . checked('9', $period, false) . '> ' . esc_html(I18N::t('days_9')) . '</label>
        </td></tr>';

        echo '<tr><th scope="row">' . esc_html(I18N::t('template')) . '</th><td>';
        foreach ($periods as $pkey => $plabel) {
            $val = $template_map[$pkey] ?? ($pkey . '-default');
            echo '<div class="yrwp-template-group" data-period="' . esc_attr($pkey) . '" style="display:none;margin-bottom:8px">';
            echo '<strong>' . esc_html($plabel) . ':</strong> ';
            echo self::render_template_option($pkey, 'default', I18N::t('default'), $val, $plabel, 'margin-right:12px;');
            echo self::render_template_option($pkey, 'compact', I18N::t('compact'), $val, $plabel, 'margin-right:12px;');
            echo self::render_template_option($pkey, 'manual', I18N::t('manual'), $val, $plabel, 'margin-right:12px;');
            echo self::render_template_option($pkey, 'json', 'JSON', $val, $plabel);
            echo '</div>';
        }
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html(I18N::t('location_source')) . '</th><td>
            <label><input type="radio" name="yrwp[location_mode]" value="manual_coords" ' . checked('manual_coords', $location_mode, false) . '> ' . esc_html(I18N::t('manual_coords')) . '</label><br>
            <label><input type="radio" name="yrwp[location_mode]" value="city" ' . checked('city', $location_mode, false) . '> ' . esc_html(I18N::t('city_search')) . '</label><br>
            <label><input type="radio" name="yrwp[location_mode]" value="visitor_geo" ' . checked('visitor_geo', $location_mode, false) . '> ' . esc_html(I18N::t('visitor_geolocation')) . '</label>
            <p class="description">Visitor geolocation uses JS + REST call. Cache is per coordinate pair.</p>
        </td></tr>';
         
        echo '<tr class="yrwp-row-city"><th scope="row">' . esc_html(I18N::t('city')) . '</th><td>
            <input type="text" class="regular-text yrwp-city" name="yrwp[city_name]" value="' . esc_attr($city_name) . '" placeholder="Start typing city...">
            <input type="hidden" name="yrwp[city_lat]" value="' . esc_attr($city_lat) . '">
            <input type="hidden" name="yrwp[city_lon]" value="' . esc_attr($city_lon) . '">
            <input type="hidden" name="yrwp[city_timezone]" value="' . esc_attr($city_timezone) . '">
            <p class="description">Autocomplete uses a geocoding API (can be replaced).</p>
        </td></tr>';
         
        echo '<tr class="yrwp-row-manual"><th scope="row">' . esc_html(I18N::t('manual_coordinates')) . '</th><td>
            <input type="text" name="yrwp[lat]" value="' . esc_attr($lat) . '" placeholder="lat" style="width:120px;">
            <input type="text" name="yrwp[lon]" value="' . esc_attr($lon) . '" placeholder="lon" style="width:120px;">
            <button type="button" class="button yrwp-use-browser-geo">Use my current location</button>
            <p class="description">Uses navigator.geolocation to fill lat/lon.</p>
        </td></tr>';
         
        echo '<tr><th scope="row">' . esc_html(I18N::t('update_frequency')) . '</th><td>
            <select name="yrwp[refresh]">
                <option value="900" ' . selected(900, $refresh, false) . '>Every 15 min</option>
                <option value="1800" ' . selected(1800, $refresh, false) . '>Every 30 min</option>
                <option value="3600" ' . selected(3600, $refresh, false) . '>Every 1 hour</option>
                <option value="10800" ' . selected(10800, $refresh, false) . '>Every 3 hours</option>
            </select>
        </td></tr>';

        echo '<tr class="yrwp-only-non-manual"><th scope="row">' . esc_html(I18N::t('show_title')) . '</th><td>
            <label><input type="checkbox" name="yrwp[title_show]" value="1" ' . checked(1, $title_show, false) . '> ' . esc_html(I18N::t('show_title')) . '</label>
        </td></tr>';

        echo '<tr class="yrwp-only-non-manual"><th scope="row">' . esc_html(I18N::t('custom_css_class')) . '</th><td>
            <input type="text" class="regular-text" name="yrwp[custom_class]" value="' . esc_attr($custom_class) . '" placeholder="e.g. my-weather-block">
        </td></tr>';

        echo '<tr class="yrwp-only-non-json yrwp-editor-row yrwp-editor-row-css"><th scope="row">' . esc_html(I18N::t('custom_css')) . '</th><td>
            <textarea id="yrwp_custom_css" name="yrwp[custom_css]" rows="10" class="large-text code" placeholder=".my-weather-block { }">' . esc_textarea($custom_css) . '</textarea>
            <p class="description">Syntax highlighting via WordPress Code Editor.</p>
        </td></tr>';

        echo '<tr class="yrwp-only-manual yrwp-only-current-manual yrwp-editor-row yrwp-editor-row-manual"><th scope="row">Manual template</th><td>
            <textarea id="yrwp_manual_template_current" name="yrwp[manual_templates][current]" rows="16" class="large-text code" placeholder="' . esc_attr(self::default_manual_template('current')) . '">' . esc_textarea($manual_template_current) . '</textarea>
            <p class="description">HTML template with tokens like <code>{{ temperature }}</code> and <code>{{ weather_icon }}</code>. PHP is not executed here.</p>
            ' . self::render_manual_template_help('current') . '
        </td></tr>';

        echo '<tr class="yrwp-only-manual"><th scope="row">Measurement units</th><td>
            <label><input type="checkbox" name="yrwp[manual_show_units]" value="1" ' . checked(1, $manual_show_units, false) . '> Show measurement units in Manual tokens</label>
            <p class="description">Affects formatted Manual placeholders like <code>{{ temperature }}</code>, <code>{{ wind }}</code>, <code>{{ min_temperature }}</code>. Raw <code>*_value</code> placeholders are always without units.</p>
        </td></tr>';

        echo '<tr class="yrwp-only-manual yrwp-only-today-manual yrwp-editor-row yrwp-editor-row-manual"><th scope="row">Manual template</th><td>
            <textarea id="yrwp_manual_template_today" name="yrwp[manual_templates][today]" rows="16" class="large-text code" placeholder="' . esc_attr(self::default_manual_template('today')) . '">' . esc_textarea($manual_template_today) . '</textarea>
            <p class="description">HTML template with general tokens like <code>{{ date }}</code>, <code>{{ updated_at }}</code> and block token <code>{{ hourly_blocks }}</code>. PHP is not executed here.</p>
            ' . self::render_manual_template_help('today') . '
        </td></tr>';

        echo '<tr class="yrwp-only-manual yrwp-only-today-manual yrwp-editor-row yrwp-editor-row-manual"><th scope="row">Hourly block template</th><td>
            <textarea id="yrwp_manual_template_today_item" name="yrwp[manual_templates][today_item]" rows="14" class="large-text code" placeholder="' . esc_attr(self::default_manual_item_template('today')) . '">' . esc_textarea($manual_template_today_item) . '</textarea>
            <p class="description">Template for one hourly block. This template is repeated inside <code>{{ hourly_blocks }}</code>.</p>
            ' . self::render_manual_template_help('today_item') . '
        </td></tr>';

        echo '<tr class="yrwp-only-manual yrwp-only-today-manual"><th scope="row">Hour step</th><td>
            <select name="yrwp[today_manual_step]">
                <option value="1" ' . selected(1, $today_manual_step, false) . '>Show every hour</option>
                <option value="2" ' . selected(2, $today_manual_step, false) . '>Show every 2 hours</option>
                <option value="3" ' . selected(3, $today_manual_step, false) . '>Show every 3 hours</option>
            </select>
            <p class="description">Controls which hourly rows are used in the today manual placeholders.</p>
        </td></tr>';

        echo '<tr class="yrwp-only-manual yrwp-only-7-manual yrwp-editor-row yrwp-editor-row-manual"><th scope="row">Manual template</th><td>
            <textarea id="yrwp_manual_template_7" name="yrwp[manual_templates][7]" rows="16" class="large-text code" placeholder="' . esc_attr(self::default_manual_template('7')) . '">' . esc_textarea($manual_template_7) . '</textarea>
            <p class="description">HTML template with general tokens like <code>{{ days_count }}</code>, <code>{{ updated_at }}</code> and block token <code>{{ daily_blocks }}</code>. PHP is not executed here.</p>
            ' . self::render_manual_template_help('7') . '
        </td></tr>';

        echo '<tr class="yrwp-only-manual yrwp-only-7-manual yrwp-editor-row yrwp-editor-row-manual"><th scope="row">Day block template</th><td>
            <textarea id="yrwp_manual_template_7_item" name="yrwp[manual_templates][7_item]" rows="14" class="large-text code" placeholder="' . esc_attr(self::default_manual_item_template('7')) . '">' . esc_textarea($manual_template_7_item) . '</textarea>
            <p class="description">Template for one day block. This template is repeated inside <code>{{ daily_blocks }}</code>.</p>
            ' . self::render_manual_template_help('7_item') . '
        </td></tr>';

        echo '<tr class="yrwp-only-manual yrwp-only-7-manual"><th scope="row">' . esc_html(I18N::t('show_today_day')) . '</th><td>
            <label><input type="checkbox" name="yrwp[manual_show_today_7]" value="1" ' . checked(1, $manual_show_today_7, false) . '> ' . esc_html(I18N::t('show_today_day')) . '</label>
            <p class="description">If disabled, today is excluded from Manual 7-day placeholders and day blocks.</p>
        </td></tr>';

        echo '<tr class="yrwp-only-manual yrwp-only-9-manual yrwp-editor-row yrwp-editor-row-manual"><th scope="row">Manual template</th><td>
            <textarea id="yrwp_manual_template_9" name="yrwp[manual_templates][9]" rows="16" class="large-text code" placeholder="' . esc_attr(self::default_manual_template('9')) . '">' . esc_textarea($manual_template_9) . '</textarea>
            <p class="description">HTML template with general tokens like <code>{{ days_count }}</code>, <code>{{ updated_at }}</code> and block token <code>{{ daily_blocks }}</code>. PHP is not executed here.</p>
            ' . self::render_manual_template_help('9') . '
        </td></tr>';

        echo '<tr class="yrwp-only-manual yrwp-only-9-manual yrwp-editor-row yrwp-editor-row-manual"><th scope="row">Day block template</th><td>
            <textarea id="yrwp_manual_template_9_item" name="yrwp[manual_templates][9_item]" rows="14" class="large-text code" placeholder="' . esc_attr(self::default_manual_item_template('9')) . '">' . esc_textarea($manual_template_9_item) . '</textarea>
            <p class="description">Template for one day block. This template is repeated inside <code>{{ daily_blocks }}</code>.</p>
            ' . self::render_manual_template_help('9_item') . '
        </td></tr>';

        echo '<tr class="yrwp-only-manual yrwp-only-9-manual"><th scope="row">' . esc_html(I18N::t('show_today_day')) . '</th><td>
            <label><input type="checkbox" name="yrwp[manual_show_today_9]" value="1" ' . checked(1, $manual_show_today_9, false) . '> ' . esc_html(I18N::t('show_today_day')) . '</label>
            <p class="description">If disabled, today is excluded from Manual 9-day placeholders and day blocks.</p>
        </td></tr>';

        echo '<tr class="yrwp-only-manual yrwp-row-custom-short-text">
            <th scope="row"><label for="yrwp_custom_short_text">Short text</label></th>
            <td>
                <input type="text" id="yrwp_custom_short_text" name="yrwp[custom_short_text]" value="' . esc_attr($custom_short_text) . '" class="regular-text yrwp-field yrwp-field-short-text" placeholder="e.g. Last update from our station">
            </td>
        </tr>';

        echo '<tr class="yrwp-only-manual yrwp-row-custom-long-text">';
        echo '<th scope="row"><label for="yrwp_custom_long_text">Long text</label></th>';
        echo '<td>';
        wp_editor(
            $custom_long_text,
            'yrwp_custom_long_text',
            [
                'textarea_name' => 'yrwp[custom_long_text]',
                'textarea_rows' => 6,
                'media_buttons' => false,
                'teeny' => true,
                'quicktags' => true,
                'editor_class' => 'yrwp-field yrwp-field-long-text',
            ]
        );
        echo '</td>';
        echo '</tr>';

        echo '</tbody></table>';
        echo self::render_template_preview_modal();

        echo '<script>
        (function(){
            function getCurrentPeriod() {
                var period = document.querySelector("input[name=\'yrwp[period]\']:checked");
                return period ? period.value : "current";
            }

            function updateTemplateVisibility() {
                var current = getCurrentPeriod();

                document.querySelectorAll(".yrwp-template-group").forEach(function(el){
                    el.style.display = (el.getAttribute("data-period") === current) ? "block" : "none";
                });

                updateTemplateDependentFields();
            }

            function updateTemplateDependentFields() {
                var period = getCurrentPeriod();
                var active = document.querySelector("input[name=\'yrwp[template_map][" + period + "]\']:checked");
                var value = active ? active.value : "";
                var isManual = value.endsWith("-manual");
                var isJson = value.endsWith("-json");

                document.querySelectorAll(".yrwp-only-manual").forEach(function(el){
                    el.style.display = isManual ? "" : "none";
                });

                document.querySelectorAll(".yrwp-only-non-manual").forEach(function(el){
                    el.style.display = isManual ? "none" : "";
                });

                document.querySelectorAll(".yrwp-only-non-json").forEach(function(el){
                    el.style.display = isJson ? "none" : "";
                });

                document.querySelectorAll(".yrwp-only-current-manual").forEach(function(el){
                    el.style.display = (period === "current" && value === "current-manual") ? "" : "none";
                });

                document.querySelectorAll(".yrwp-only-today-manual").forEach(function(el){
                    el.style.display = (period === "today" && value === "today-manual") ? "" : "none";
                });

                document.querySelectorAll(".yrwp-only-7-manual").forEach(function(el){
                    el.style.display = (period === "7" && value === "7-manual") ? "" : "none";
                });

                document.querySelectorAll(".yrwp-only-9-manual").forEach(function(el){
                    el.style.display = (period === "9" && value === "9-manual") ? "" : "none";
                });
            }

            function bindEvents() {
                document.querySelectorAll("input[name=\'yrwp[period]\']").forEach(function(el){
                    el.addEventListener("change", updateTemplateVisibility);
                });

                document.querySelectorAll("input[name^=\'yrwp[template_map]\']").forEach(function(el){
                    el.addEventListener("change", updateTemplateDependentFields);
                });
            }

            document.addEventListener("DOMContentLoaded", function(){
                bindEvents();
                updateTemplateVisibility();
            });
        })();
        </script>';
    }

    public static function save($post_id, $post)
    {
        if (!isset($_POST['yrwp_nonce']) || !wp_verify_nonce($_POST['yrwp_nonce'], 'yrwp_save_widget')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $in = $_POST['yrwp'] ?? [];
        $meta = [];

        $meta['enabled'] = !empty($in['enabled']) ? 1 : 0;
        $meta['title_show'] = !empty($in['title_show']) ? 1 : 0;
        $meta['location_mode'] = in_array(($in['location_mode'] ?? ''), ['manual_coords', 'city', 'visitor_geo'], true)
            ? $in['location_mode']
            : 'manual_coords';

        $meta['lat'] = sanitize_text_field($in['lat'] ?? '');
        $meta['lon'] = sanitize_text_field($in['lon'] ?? '');
        $meta['city_name'] = sanitize_text_field($in['city_name'] ?? '');
        $meta['city_lat'] = sanitize_text_field($in['city_lat'] ?? '');
        $meta['city_lon'] = sanitize_text_field($in['city_lon'] ?? '');
        $meta['city_timezone'] = MET::normalize_timezone($in['city_timezone'] ?? '');

        $meta['refresh'] = max(300, intval($in['refresh'] ?? 1800));

        $meta['custom_class'] = sanitize_html_class($in['custom_class'] ?? '');
        $meta['custom_css'] = wp_kses_post($in['custom_css'] ?? '');
        $meta['today_manual_step'] = max(1, min(3, intval($in['today_manual_step'] ?? 1)));
        $meta['manual_show_units'] = !empty($in['manual_show_units']) ? 1 : 0;
        $meta['manual_show_today_7'] = !empty($in['manual_show_today_7']) ? 1 : 0;
        $meta['manual_show_today_9'] = !empty($in['manual_show_today_9']) ? 1 : 0;

        $allowed_periods = ['current', 'today', '7', '9'];
        $meta['period'] = in_array($in['period'] ?? 'current', $allowed_periods, true)
            ? $in['period']
            : 'current';

        $periods = ['current', 'today', '7', '9'];
        $template_map = [];
        if (isset($in['template_map']) && is_array($in['template_map'])) {
            foreach ($periods as $pkey) {
                $val = $in['template_map'][$pkey] ?? ($pkey . '-default');
                if (in_array($val, ["{$pkey}-default", "{$pkey}-compact", "{$pkey}-manual", "{$pkey}-json"], true)) {
                    $template_map[$pkey] = $val;
                } else {
                    $template_map[$pkey] = $pkey . '-default';
                }
            }
        } else {
            foreach ($periods as $pkey) {
                $template_map[$pkey] = $pkey . '-default';
            }
        }
        $meta['template_map'] = $template_map;

        $meta['custom_short_text'] = isset($in['custom_short_text'])
            ? sanitize_text_field($in['custom_short_text'])
            : '';

        $meta['custom_long_text'] = isset($in['custom_long_text'])
            ? wp_kses_post($in['custom_long_text'])
            : '';

        $current_manual_template = isset($in['manual_templates']['current'])
            ? wp_kses_post($in['manual_templates']['current'])
            : '';
        $today_manual_template = isset($in['manual_templates']['today'])
            ? wp_kses_post($in['manual_templates']['today'])
            : '';
        $today_item_manual_template = isset($in['manual_templates']['today_item'])
            ? wp_kses_post($in['manual_templates']['today_item'])
            : '';
        $manual_template_7 = isset($in['manual_templates']['7'])
            ? wp_kses_post($in['manual_templates']['7'])
            : '';
        $manual_template_7_item = isset($in['manual_templates']['7_item'])
            ? wp_kses_post($in['manual_templates']['7_item'])
            : '';
        $manual_template_9 = isset($in['manual_templates']['9'])
            ? wp_kses_post($in['manual_templates']['9'])
            : '';
        $manual_template_9_item = isset($in['manual_templates']['9_item'])
            ? wp_kses_post($in['manual_templates']['9_item'])
            : '';
        $meta['manual_templates'] = [
            'current' => trim($current_manual_template) !== ''
                ? $current_manual_template
                : self::default_manual_template('current'),
            'today' => trim($today_manual_template) !== ''
                ? $today_manual_template
                : self::default_manual_template('today'),
            'today_item' => trim($today_item_manual_template) !== ''
                ? $today_item_manual_template
                : self::default_manual_item_template('today'),
            '7' => trim($manual_template_7) !== ''
                ? $manual_template_7
                : self::default_manual_template('7'),
            '7_item' => trim($manual_template_7_item) !== ''
                ? $manual_template_7_item
                : self::default_manual_item_template('7'),
            '9' => trim($manual_template_9) !== ''
                ? $manual_template_9
                : self::default_manual_template('9'),
            '9_item' => trim($manual_template_9_item) !== ''
                ? $manual_template_9_item
                : self::default_manual_item_template('9'),
        ];

        update_post_meta($post_id, '_yrwp', $meta);

        delete_transient('yrwp_cache_' . $post_id);
    }

    public static function get_meta($post_id): array
    {
        $meta = get_post_meta($post_id, '_yrwp', true);
        if (!is_array($meta)) {
            return [];
        }

        if (($meta['period'] ?? '') === self::LEGACY_NINE_DAY_KEY) {
            $meta['period'] = '9';
        }

        if (isset($meta['template_map'][self::LEGACY_NINE_DAY_KEY]) && !isset($meta['template_map']['9'])) {
            $meta['template_map']['9'] = str_replace(self::LEGACY_NINE_DAY_PREFIX, '9-', (string) $meta['template_map'][self::LEGACY_NINE_DAY_KEY]);
        }
        unset($meta['template_map'][self::LEGACY_NINE_DAY_KEY]);

        if (isset($meta['manual_templates'][self::LEGACY_NINE_DAY_KEY]) && !isset($meta['manual_templates']['9'])) {
            $meta['manual_templates']['9'] = $meta['manual_templates'][self::LEGACY_NINE_DAY_KEY];
        }
        if (isset($meta['manual_templates'][self::LEGACY_NINE_DAY_ITEM_KEY]) && !isset($meta['manual_templates']['9_item'])) {
            $meta['manual_templates']['9_item'] = $meta['manual_templates'][self::LEGACY_NINE_DAY_ITEM_KEY];
        }
        unset($meta['manual_templates'][self::LEGACY_NINE_DAY_KEY], $meta['manual_templates'][self::LEGACY_NINE_DAY_ITEM_KEY]);

        return $meta;
    }

    public static function default_manual_template(string $period): string
    {
        if ($period === 'today') {
            return <<<'HTML'
<div class="yrwp-manual-layout yrwp-manual-layout-today">
  <h3>{{ title }}</h3>
  <p>{{ date }}</p>
  <div class="yrwp-manual-hours">{{ hourly_blocks }}</div>
  <p>{{ updated_at }}</p>
</div>
HTML;
        }

        if ($period === '7' || $period === '9') {
            return <<<'HTML'
<div class="yrwp-manual-layout yrwp-manual-layout-multi-day">
  <h3>{{ title }}</h3>
  <p>{{ days_count }} days</p>
  <div class="yrwp-manual-days">{{ daily_blocks }}</div>
  <p>{{ updated_at }}</p>
</div>
HTML;
        }

        if ($period !== 'current') {
            return '';
        }

        return <<<'HTML'
<div class="yrwp-manual-layout">
  <h3>{{ title }}</h3>
  <div class="yrwp-manual-hero">
    {{ weather_icon }}
    <p>{{ temperature }}</p>
  </div>
  <div class="yrwp-manual-details">
    <p>{{ icon-wind-speed }} {{ wind }}</p>
    <p>{{ icon-humidity }} {{ humidity }}</p>
    <p>{{ icon-pressure }} {{ pressure }}</p>
    <p>{{ forecast-time }}</p>
    <p>{{ updated_at }}</p>
  </div>
</div>
HTML;
    }

    public static function default_manual_item_template(string $period): string
    {
        if ($period === '7' || $period === '9') {
            return <<<'HTML'
<div class="yrwp-manual-day-block">
  <div>{{ weekday }}</div>
  <div>{{ weather_icon }}</div>
  <div>{{ weather_text }}</div>
  <div>{{ max_temperature }}</div>
  <div>{{ min_temperature }}</div>
</div>
HTML;
        }

        if ($period !== 'today') {
            return '';
        }

        return <<<'HTML'
<div class="yrwp-manual-hour-block">
  <div>{{ forecast-time }}</div>
  <div>{{ weather_icon }}</div>
  <div>{{ weather_text }}</div>
  <div>{{ icon-temperature }} {{ temperature }}</div>
  <div>{{ icon-wind-speed }} {{ wind }}</div>
  <div>{{ icon-humidity }} {{ humidity }}</div>
</div>
HTML;
    }

    private static function manual_token_help_text(string $key): string
    {
        $translations = [
            'en' => [
                'available_tokens' => 'Available tokens',
                'token_help_forecast_time' => 'Forecast time. (Example: 10:30)',
                'token_help_now_time' => 'Current time in the WordPress site timezone at the moment the widget is rendered. (Example: 14:25)',
                'token_help_location_now_time' => 'Current time in the configured weather location timezone at the moment the widget is rendered. (Example: 14:25)',
                'token_help_hourly_blocks' => 'Rendered HTML blocks for each selected hour. (Example: repeated cards with time, icon and weather data)',
                'token_help_daily_blocks' => 'Rendered HTML blocks for each selected day. (Example: repeated day cards with date, icon and temperatures)',
            ],
            'nb' => [
                'available_tokens' => 'Tilgjengelige tokens',
                'token_help_forecast_time' => 'Prognosetid. (Eksempel: 10:30)',
                'token_help_now_time' => 'Gjeldende tid nar widgeten rendres. (Eksempel: 14:25)',
                'token_help_hourly_blocks' => 'Renderte HTML-blokker for hver valgt time. (Eksempel: gjentatte kort med tid, ikon og vaerdata)',
                'token_help_daily_blocks' => 'Renderte HTML-blokker for hver valgt dag. (Eksempel: gjentatte dagkort med dato, ikon og temperaturer)',
            ],
            'uk' => [
                'available_tokens' => 'Доступні токени',
                'token_help_forecast_time' => 'Час прогнозу. (Приклад: 10:30)',
                'token_help_now_time' => 'Поточний час на момент рендеру віджета. (Приклад: 14:25)',
                'token_help_hourly_blocks' => 'Згенеровані HTML-блоки для кожної вибраної години. (Приклад: картки, що повторюються, з часом, іконкою та даними погоди)',
                'token_help_daily_blocks' => 'Згенеровані HTML-блоки для кожного вибраного дня. (Приклад: картки днів, що повторюються, з датою, іконкою та температурами)',
            ],
        ];

        $language = I18N::get_language();

        return $translations[$language][$key] ?? $translations['en'][$key] ?? $key;
    }

    private static function manual_token_help_catalog_from_file(): array
    {
        static $catalog = null;

        if ($catalog === null) {
            $catalog = require YRWP_PATH . 'includes/manual-token-help-map.php';
        }

        return $catalog;
    }

    private static function manual_token_period_map_from_file(string $period): array
    {
        $catalog = self::manual_token_help_catalog_from_file();
        $language = I18N::get_language();

        return $catalog[$language]['periods'][$period]
            ?? $catalog['en']['periods'][$period]
            ?? [];
    }

    private static function render_manual_template_help(string $period): string
    {
        $tokens = self::manual_template_tokens_help_v2($period);
        if (!$tokens) {
            return '';
        }

        $html = '<div class="yrwp-manual-token-help" style="margin-top:12px;">';
        $html .= '<strong>' . esc_html(self::manual_token_help_text('available_tokens')) . '</strong>';
        $html .= '<ul style="margin:8px 0 0;padding:0;list-style:none;">';

        foreach ($tokens as $token => $description) {
            $html .= '<li><code>{{ ' . esc_html($token) . ' }}</code> - ' . esc_html($description) . '</li>';
        }

        $html .= '</ul></div>';

        return $html;
    }

    private static function manual_template_tokens_help(string $period): array
    {
        if ($period === 'today') {
            $tokens = [
                'title' => 'Widget title. (Example: Bergen Harbor)',
                'date' => 'Date for the selected today block. (Example: March 10, 2026)',
                'weekday' => 'Weekday for the selected today block. (Example: Tuesday)',
                'hourly_count' => 'Number of hourly forecast rows for today. (Example: 24)',
                'hourly_times' => 'All selected forecast times in order. (Example: 12:00 13:00 14:00)',
                'hourly_weather_icons' => 'All selected weather icons in order. (Example: icon list for each shown hour)',
                'hourly_weather_texts' => 'All selected localized weather labels in order. (Example: Ясно Хмарно Дощ)',
                'hourly_temperatures' => 'All selected temperatures in order. (Example: 8 °C 9 °C 10 °C)',
                'hourly_wind_speeds' => 'All selected wind speeds in order. (Example: 4.1 m/s 5.0 m/s 5.3 m/s)',
                'hourly_wind_directions' => 'All selected wind directions in order. (Example: 180 ° 190 ° 200 °)',
                'hourly_humidities' => 'All selected humidity values in order. (Example: 73 % 71 % 69 %)',
                'hourly_pressures' => 'All selected air pressure values in order. (Example: 1013 hPa 1012 hPa 1011 hPa)',
                'hourly_clouds' => 'All selected cloud cover values in order. (Example: 65 % 70 % 75 %)',
                'hourly_precipitation' => 'All selected precipitation values in order. (Example: 0.1 mm 0.2 mm 0.3 mm)',
                'now-time' => self::manual_token_help_text('token_help_now_time'),
                'location-now-time' => self::manual_token_help_text('token_help_location_now_time'),
                'updated_at' => 'Time of the last API refresh. (Example: 10:15)',
                'short_text' => 'Custom short text if filled in. (Example: Live from the station)',
                'long_text' => 'Custom long text if filled in. (Example: Local conditions remain stable.)',
            ];

            return $tokens;
        }

        if ($period === '7' || $period === '9') {
            return [
                'title' => 'Widget title. (Example: Bergen Harbor)',
                'days_count' => 'Number of day blocks in the forecast. (Example: 7 or 9)',
                'daily_dates' => 'All selected dates in order. (Example: March 11 March 12 March 13)',
                'daily_weekdays' => 'All selected weekdays in order. (Example: Wednesday Thursday Friday)',
                'daily_weather_icons' => 'All selected weather icons in order. (Example: icon list for each day)',
                'daily_weather_texts' => 'All selected localized weather labels in order. (Example: Ясно Хмарно Дощ)',
                'daily_min_temperatures' => 'All selected minimum temperatures in order. (Example: 1 °C 2 °C 4 °C)',
                'daily_max_temperatures' => 'All selected maximum temperatures in order. (Example: 8 °C 9 °C 10 °C)',
                'daily_wind_speeds' => 'All selected average wind speeds in order. (Example: 4.1 m/s 5.0 m/s 5.3 m/s)',
                'daily_humidities' => 'All selected average humidity values in order. (Example: 73 % 71 % 69 %)',
                'daily_pressures' => 'All selected average air pressure values in order. (Example: 1013 hPa 1012 hPa 1011 hPa)',
                'daily_clouds' => 'All selected average cloud cover values in order. (Example: 65 % 70 % 75 %)',
                'daily_precipitation' => 'All selected precipitation values in order. (Example: 0.1 mm 0.2 mm 0.3 mm)',
                'now-time' => self::manual_token_help_text('token_help_now_time'),
                'location-now-time' => self::manual_token_help_text('token_help_location_now_time'),
                'updated_at' => 'Time of the last API refresh. (Example: 10:15)',
                'short_text' => 'Custom short text if filled in. (Example: Live from the station)',
                'long_text' => 'Custom long text if filled in. (Example: Local conditions remain stable.)',
            ];
        }

        if ($period === 'day_item') {
            return [
                'date' => 'Date for the selected day block. (Example: March 11, 2026)',
                'weekday' => 'Weekday for the selected day block. (Example: Wednesday)',
                'weather_icon' => 'Day weather SVG icon from MET. (Example: cloud/sun icon)',
                'weather_text' => 'Localized weather label. (Example: Ясно)',
                'min_temperature' => 'Minimum temperature with unit. (Example: 1 °C)',
                'max_temperature' => 'Maximum temperature with unit. (Example: 8 °C)',
                'wind_speed' => 'Average wind speed with unit. (Example: 5.2 m/s)',
                'humidity' => 'Average humidity with unit. (Example: 73 %)',
                'pressure' => 'Average air pressure with unit. (Example: 1013 hPa)',
                'clouds' => 'Average cloud cover with unit. (Example: 65 %)',
                'precipitation' => 'Precipitation with unit. (Example: 1.4 mm)',
            ];
        }

        if ($period !== 'current') {
            return [];
        }

        return [
            'title' => 'Widget title if "Show title" is enabled. (Example: Bergen Harbor)',
            'date' => 'Date for the current forecast row. (Example: March 11, 2026)',
            'weekday' => 'Weekday for the current forecast row. (Example: Wednesday)',
            'weather_icon' => 'Current weather SVG icon from MET. (Example: cloud/sun icon)',
            'weather_icons' => 'Alias of weather_icon. (Example: cloud/sun icon)',
            'weather_text' => 'Localized weather label. (Example: Ясно)',
            'temperature' => 'Formatted temperature with unit. (Example: 8 °C)',
            'icon-temperature' => 'Detail icon for temperature. (Example: thermometer icon)',
            'wind' => 'Formatted wind speed with unit. (Example: 5.2 m/s)',
            'wind_speed' => 'Alias of wind. (Example: 5.2 m/s)',
            'icon-wind-speed' => 'Detail icon for wind speed. (Example: wind icon)',
            'wind_direction' => 'Formatted wind direction with unit. (Example: 180 °)',
            'icon-wind-direction' => 'Detail icon for wind direction. (Example: windy icon)',
            'humidity' => 'Formatted humidity with unit. (Example: 73 %)',
            'icon-humidity' => 'Detail icon for humidity. (Example: drop icon)',
            'pressure' => 'Formatted air pressure with unit. (Example: 1013 hPa)',
            'icon-pressure' => 'Detail icon for pressure. (Example: cloud icon)',
            'clouds' => 'Formatted cloud cover with unit. (Example: 65 %)',
            'icon-cloud-area-fraction' => 'Detail icon for cloud cover. (Example: clouds icon)',
            'precipitation' => 'Formatted precipitation with unit. (Example: 1.4 mm)',
            'icon-precipitation' => 'Detail icon for precipitation. (Example: umbrella icon)',
            'forecast-time' => self::manual_token_help_text('token_help_forecast_time'),
            'now-time' => self::manual_token_help_text('token_help_now_time'),
            'location-now-time' => self::manual_token_help_text('token_help_location_now_time'),
            'updated_at' => 'Time of the last API refresh. (Example: 10:15)',
            'short_text' => 'Custom short text if filled in. (Example: Live from the station)',
            'long_text' => 'Custom long text if filled in. (Example: Local conditions remain stable.)',
        ];
    }

    private static function manual_token_help_catalog_v2(): array
    {
        static $catalog = null;

        if ($catalog === null) {
            $catalog = require YRWP_PATH . 'includes/manual-token-help-map.php';
        }

        return $catalog;
    }

    private static function manual_template_tokens_help_v2(string $period): array
    {
        $catalog = self::manual_token_help_catalog_v2();
        $language = I18N::get_language();
        $periods = $catalog[$language]['periods'] ?? $catalog['en']['periods'] ?? [];

        if ($period === 'today_item') {
            $tokens = $periods['current'] ?? ($catalog['en']['periods']['current'] ?? []);
            unset($tokens['title'], $tokens['short_text'], $tokens['long_text']);
            return $tokens;
        }

        if ($period === '7_item' || $period === '9_item') {
            return $periods['day_item'] ?? ($catalog['en']['periods']['day_item'] ?? []);
        }

        return $periods[$period] ?? ($catalog['en']['periods'][$period] ?? []);
    }
}
