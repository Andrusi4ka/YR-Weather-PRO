<?php

namespace YRWP;

if (!defined('ABSPATH')) exit;

class Shortcode
{
    private const WEATHER_ICON_SIZE = 150;

    public static function init()
    {
        add_shortcode('yr_weather', [__CLASS__, 'render']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
    }

    /**
     * Registered on every page, enqueued only where a widget is: up front for
     * a post whose content has the shortcode, otherwise by render() (page
     * builders, widgets), in which case WordPress prints them in the footer.
     */
    public static function assets()
    {
        self::register_assets();

        $post = get_post();
        if (is_singular() && $post && has_shortcode((string) $post->post_content, 'yr_weather')) {
            self::enqueue_assets();
        }
    }

    private static function register_assets()
    {
        if (wp_script_is('yrwp-frontend', 'registered')) {
            return;
        }

        wp_register_style('yrwp-frontend', YRWP_URL . 'assets/style/frontend.css', [], YRWP_VERSION);
        wp_register_style('yrwp-template-current-default', YRWP_URL . 'assets/style/templates/template-current-default.css', ['yrwp-frontend'], YRWP_VERSION);

        wp_register_script('yrwp-frontend', YRWP_URL . 'assets/frontend.js', [], YRWP_VERSION, true);
        wp_localize_script('yrwp-frontend', 'YRWP_FRONT', [
            'rest'  => esc_url_raw(rest_url('yrwp/v1/weather')),
            'nonce' => wp_create_nonce('wp_rest'),
            'locale' => str_replace('_', '-', determine_locale()),
            'strings' => [
                'unsupported' => I18N::t('geo_unsupported'),
                'unavailable' => I18N::t('weather_unavailable'),
                'denied' => I18N::t('geo_denied'),
            ],
        ]);
    }

    private static function enqueue_assets()
    {
        self::register_assets();
        wp_enqueue_style('yrwp-frontend');
        wp_enqueue_style('yrwp-template-current-default');
        wp_enqueue_script('yrwp-frontend');
    }

    /**
     * Custom CSS as it may go inside <style>: no tags. wp_kses_post() was used
     * before and turned ">" into "&gt;", breaking child selectors; CSS saved
     * that way is repaired here.
     */
    public static function sanitize_css(string $css): string
    {
        return trim(wp_strip_all_tags(str_replace('&gt;', '>', $css)));
    }

    public static function get_weather_icon_svg(?string $symbol_code): string
    {
        if (empty($symbol_code)) return '';

        $symbol_code = sanitize_file_name($symbol_code);

        $upload_dir  = wp_upload_dir();
        $custom_dir  = trailingslashit($upload_dir['basedir']) . 'yrwp-icons/';
        $custom_file = $custom_dir . $symbol_code . '.svg';

        if (file_exists($custom_file)) {
            $svg = file_get_contents($custom_file);
            if ($svg !== false) return $svg;
        }

        $builtin_file = YRWP_PATH . 'assets/icons/weather/svg/' . $symbol_code . '.svg';
        if (file_exists($builtin_file)) {
            $svg = file_get_contents($builtin_file);
            if ($svg !== false) return $svg;
        }

        $fallback = YRWP_PATH . 'assets/icons/weather/svg/cloudy.svg';
        if (file_exists($fallback)) {
            $svg = file_get_contents($fallback);
            if ($svg !== false) return $svg;
        }

        return '';
    }

    public static function render($atts)
    {
        $atts = shortcode_atts(['id' => 0], $atts);
        $id = intval($atts['id']);
        if (!$id) return '';

        $post = get_post($id);
        if (!$post || $post->post_type !== CPT::POST_TYPE) return '';

        $meta = CPT::get_meta($id);
        $meta['_widget_title'] = get_the_title($id);
        if (empty($meta['enabled'])) return '';

        self::enqueue_assets();

        $period = $meta['period'] ?? 'current';
        $template_map = $meta['template_map'] ?? [];
        $active_template = $template_map[$period] ?? ($period . '-default');
        $is_manual_template = self::is_manual_template($active_template);
        $is_json_template = self::is_json_template($active_template);
        $is_raw_template = self::is_raw_template($active_template);

        $class = 'yrwp-widget';
        if (!$is_raw_template && !empty($meta['custom_class'])) {
            $class .= ' ' . sanitize_html_class($meta['custom_class']);
        }

        $out = '';
        if (!$is_raw_template) {
            $out = '<div class="' . esc_attr($class) . '"
                data-yrwp-id="' . intval($id) . '"
                data-yrwp-mode="' . esc_attr($meta['location_mode'] ?? 'manual_coords') . '">';
        }

        if (!$is_raw_template && !empty($meta['title_show'])) {
            $out .= '<h3 class="yrwp-title">' . esc_html(get_the_title($id)) . '</h3>';
        }

        if (!$is_json_template && !empty($meta['custom_css'])) {
            $out .= '<style>' . self::sanitize_css((string) $meta['custom_css']) . '</style>';
        }

        $mode = $meta['location_mode'] ?? 'manual_coords';

        if ($mode === 'visitor_geo') {
            $out .= '<div class="yrwp-body yrwp-loading">' . esc_html(I18N::t('loading_weather')) . '</div>';
            if (!$is_raw_template) {
                $out .= self::met_credit($meta) . '</div>';
            }
            return $out;
        }

        if ($mode === 'city') {
            $lat = floatval($meta['city_lat'] ?? 0);
            $lon = floatval($meta['city_lon'] ?? 0);
        } else {
            $lat = floatval($meta['lat'] ?? 0);
            $lon = floatval($meta['lon'] ?? 0);
        }

        if (!$lat && !$lon) {
            if ($is_json_template) {
                return wp_json_encode([
                    'ok' => false,
                    'period' => $period,
                    'template' => $active_template,
                    'error' => 'Coordinates are not set.',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $out .= '<div class="yrwp-body yrwp-error">' . esc_html(I18N::t('coordinates_not_set')) . '</div>';
            if (!$is_raw_template) {
                $out .= '</div>';
            }
            return $out;
        }

        $ttl = intval($meta['refresh'] ?? 1800);
        $res = MET::get_cached_for_widget($id, $lat, $lon, $ttl);

        if (empty($res['ok'])) {
            if ($is_json_template) {
                return wp_json_encode([
                    'ok' => false,
                    'period' => $period,
                    'template' => $active_template,
                    'error' => 'Weather unavailable.',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $out .= '<div class="yrwp-body yrwp-error">' . esc_html(I18N::t('weather_unavailable')) . '</div>';
            if (!$is_raw_template) {
                $out .= '</div>';
            }
            return $out;
        }

        $out .= self::render_from_api($meta, $res['data'], $res['updated_at'] ?? null);
        if (!$is_raw_template) {
            $out .= self::met_credit($meta) . '</div>';
        }

        return $out;
    }

    /**
     * Credit line MET Norway's licence (CC BY 4.0) asks for, unless the widget
     * switches it off (e.g. the site credits MET elsewhere). Manual and JSON
     * templates leave it to whoever writes them.
     */
    private static function met_credit(array $meta): string
    {
        if (isset($meta['show_credit']) && empty($meta['show_credit'])) {
            return '';
        }

        return '<p class="yrwp-credit"><a href="https://www.met.no/" target="_blank" rel="noopener noreferrer">' . esc_html(I18N::t('met_credit')) . '</a></p>';
    }

    public static function render_from_api(array $meta, array $api, ?int $updated_at): string
    {
        $timeseries = $api['properties']['timeseries'] ?? [];
        if (empty($timeseries)) {
            return '<div class="yrwp-body yrwp-error">Invalid API response.</div>';
        }

        $meta = self::enrich_meta_with_location_timezone($meta, $api);
        $days = self::group_by_day($timeseries);
        $period = $meta['period'] ?? 'current';
        $template_map = $meta['template_map'] ?? [];
        $template = $template_map[$period] ?? ($period . '-default');

        $data = [
            'meta'        => $meta,
            'period'      => $period,
            'timeseries'  => $timeseries,
            'days'        => $days,
            'updated_at'  => $updated_at,
            'template'    => $template, // convenient in templates
        ];

        return self::load_template($template, $data);
    }

    public static function get_site_time_format(): string
    {
        return (string) get_option('time_format', 'H:i');
    }

    private static function is_manual_template(string $template): bool
    {
        return substr($template, -7) === '-manual';
    }

    private static function is_json_template(string $template): bool
    {
        return substr($template, -5) === '-json';
    }

    private static function is_raw_template(string $template): bool
    {
        return self::is_manual_template($template) || self::is_json_template($template);
    }

    /**
     * Locate template file, allowing theme overrides.
     *
     * Theme override path:
     *   /wp-content/themes/your-theme/yr-weather-pro/template-{$slug}.php
     *
     * Plugin fallback:
     *   /wp-content/plugins/yr-weather-pro/templates/template-{$slug}.php
     */
    private static function locate_template_file(string $slug): string
    {
        $slug = sanitize_key($slug);

        // Theme override (child theme first)
        $theme_rel = 'yr-weather-pro/template-' . $slug . '.php';
        $theme_file = locate_template([$theme_rel], false, false);

        if (!empty($theme_file) && file_exists($theme_file)) {
            return $theme_file;
        }

        // Plugin template
        $plugin_file = YRWP_PATH . 'templates/template-' . $slug . '.php';
        if (file_exists($plugin_file)) {
            return $plugin_file;
        }

        // Default fallback
        return YRWP_PATH . 'templates/template-default.php';
    }

    private static function load_template(string $slug, array $data): string
    {
        $file = self::locate_template_file($slug);

        // Make variables available inside template:
        // $meta, $period, $timeseries, $days, $updated_at, $template, ...
        extract($data, EXTR_SKIP);

        ob_start();
        include $file;
        return ob_get_clean();
    }

    public static function render_period_json(string $period, array $data): string
    {
        $payload = self::build_period_json_payload($period, $data);

        return wp_json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    public static function get_period_json_payload(string $period, array $data): array
    {
        return self::build_period_json_payload($period, $data);
    }

    /* ====== RENDER HELPERS (доступні в шаблонах) ====== */

    public static function get_current_detail_icon_map(): array
    {
        return [
            'air_temperature' => 'temperature.svg',
            'wind_speed' => 'wind_speed.svg',
            'wind_from_direction' => 'wind_direction.svg',
            'relative_humidity' => 'humidity.svg',
            'air_pressure_at_sea_level' => 'pressure.svg',
            'cloud_area_fraction' => 'precipitation_amount.svg',
            'precipitation_amount' => 'precipitation.svg',
        ];
    }

    public static function get_detail_icon_html(string $key): string
    {
        $map = self::get_current_detail_icon_map();
        $icon_file = $map[$key] ?? '';
        if ($icon_file === '') return '';

        $label = I18N::field_label($key);
        $icon_url = Icons::get_detail_icon_url($icon_file);
        if ($icon_url === '') return '';

        return '<img src="'
            . esc_url($icon_url)
            . '" alt="'
            . esc_attr($label)
            . '">';
    }

    public static function render_manual_template(string $template, array $tokens): string
    {
        return (string) preg_replace_callback(
            '/{{\s*([a-z0-9_-]+)\s*}}/i',
            static function (array $matches) use ($tokens): string {
                $token = strtolower($matches[1]);
                if ($token === 'now-time' || $token === 'location-now-time') {
                    return self::render_live_time_token($token, $tokens);
                }

                return $tokens[$token] ?? '';
            },
            $template
        );
    }

    private static function filter_today_manual_rows(array $rows, int $step): array
    {
        $step = max(1, min(3, $step));
        if ($step === 1) {
            return array_values($rows);
        }

        $filtered = [];
        foreach (array_values($rows) as $index => $row) {
            if ($index % $step === 0) {
                $filtered[] = $row;
            }
        }

        return $filtered;
    }

    private static function render_today_manual_series(array $rows, callable $resolver, string $item_class): string
    {
        $html = '';

        foreach ($rows as $row) {
            $value = $resolver($row);
            if ($value === null || $value === '') {
                continue;
            }

            $html .= $value;
        }

        return $html;
    }

    private static function get_row_field_value(array $row, string $key)
    {
        if ($key === 'precipitation_amount') {
            if (isset($row['data']['next_1_hours']['details']['precipitation_amount'])) {
                return $row['data']['next_1_hours']['details']['precipitation_amount'];
            }

            if (isset($row['data']['next_6_hours']['details']['precipitation_amount'])) {
                return $row['data']['next_6_hours']['details']['precipitation_amount'];
            }
        }

        return $row['data']['instant']['details'][$key] ?? null;
    }

    private static function render_today_manual_blocks(array $rows, array $meta, ?int $updated_at): string
    {
        $template = $meta['manual_templates']['today_item'] ?? CPT::default_manual_item_template('today');
        $html = '';

        foreach ($rows as $row) {
            $tokens = self::get_manual_template_tokens('current', $meta, $row, $updated_at);
            $html .= self::render_manual_template($template, $tokens);
        }

        return $html;
    }

    private static function render_multi_day_manual_series(array $days, callable $resolver): string
    {
        $html = '';

        foreach ($days as $date => $rows) {
            $value = $resolver((string) $date, is_array($rows) ? $rows : []);
            if ($value === null || $value === '') {
                continue;
            }

            $html .= $value;
        }

        return $html;
    }

    private static function filter_multi_day_manual_days(string $period, array $days, array $meta): array
    {
        if ($period !== '7' && $period !== '9') {
            return $days;
        }

        $show_today_key = $period === '7' ? 'manual_show_today_7' : 'manual_show_today_9';
        $show_today = !isset($meta[$show_today_key]) || (int) $meta[$show_today_key] === 1;
        if ($show_today) {
            return $days;
        }

        $today = wp_date('Y-m-d', current_time('timestamp', true));
        unset($days[$today]);

        return $days;
    }

    private static function aggregate_manual_day(array $rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            $instant = $row['data']['instant']['details'] ?? [];
            foreach ($instant as $key => $value) {
                if (!is_numeric($value)) {
                    continue;
                }

                $result[$key][] = $value;
            }

            $precipitation_amount = $row['data']['next_1_hours']['details']['precipitation_amount']
                ?? $row['data']['next_6_hours']['details']['precipitation_amount']
                ?? null;

            if ($precipitation_amount !== null && is_numeric($precipitation_amount)) {
                $result['precipitation_amount'][] = $precipitation_amount;
            }
        }

        $aggregated = [];
        foreach ($result as $key => $values) {
            if ($key === 'air_temperature') {
                $aggregated[$key] = [
                    'min' => min($values),
                    'max' => max($values),
                ];
                continue;
            }

            $aggregated[$key] = round(array_sum($values) / count($values), 1);
        }

        return $aggregated;
    }

    private static function get_day_symbol_code(array $rows): string
    {
        foreach ($rows as $row) {
            $symbol_code = self::get_row_symbol_code($row);
            if ($symbol_code !== '') {
                return $symbol_code;
            }
        }

        return '';
    }

    public static function get_row_symbol_code(array $row): string
    {
        $candidates = [
            (string) ($row['data']['next_1_hours']['summary']['symbol_code'] ?? ''),
            (string) ($row['data']['next_6_hours']['summary']['symbol_code'] ?? ''),
            (string) ($row['data']['next_12_hours']['summary']['symbol_code'] ?? ''),
        ];

        foreach ($candidates as $symbol_code) {
            if ($symbol_code !== '') {
                return $symbol_code;
            }
        }

        return '';
    }

    private static function get_multi_day_block_tokens(string $date, array $rows, array $meta = []): array
    {
        if (!$rows) {
            return [];
        }

        $date_ts = strtotime($date . ' 00:00:00');
        $aggregate = self::aggregate_manual_day($rows);
        $symbol_code = self::get_day_symbol_code($rows);
        $weather_icon = $symbol_code ? self::get_weather_icon_svg($symbol_code) : '';

        $min_temperature = $aggregate['air_temperature']['min'] ?? null;
        $max_temperature = $aggregate['air_temperature']['max'] ?? null;
        $now_time_tokens = self::get_manual_now_time_tokens($meta);

        return [
            'date' => $date_ts ? esc_html(I18N::format_date($date_ts)) : esc_html($date),
            'weekday' => $date_ts ? esc_html(I18N::weekday_name($date_ts)) : '',
            'weekday_short' => $date_ts ? esc_html(I18N::weekday_short_name($date_ts)) : '',
            'weather_icon' => $weather_icon,
            'weather_icons' => $weather_icon,
            'weather_code' => esc_html(I18N::symbol_description($symbol_code)),
            'weather_text' => esc_html(I18N::symbol_description($symbol_code)),
            'min_temperature' => $min_temperature !== null ? esc_html(self::format_manual_value('air_temperature', $min_temperature, $meta)) : '',
            'min_temperature_value' => $min_temperature !== null ? esc_html((string) $min_temperature) : '',
            'max_temperature' => $max_temperature !== null ? esc_html(self::format_manual_value('air_temperature', $max_temperature, $meta)) : '',
            'max_temperature_value' => $max_temperature !== null ? esc_html((string) $max_temperature) : '',
            'icon-temperature' => self::get_detail_icon_html('air_temperature'),
            'wind_speed' => isset($aggregate['wind_speed']) ? esc_html(self::format_manual_value('wind_speed', $aggregate['wind_speed'], $meta)) : '',
            'wind_speed_value' => isset($aggregate['wind_speed']) ? esc_html((string) $aggregate['wind_speed']) : '',
            'icon-wind-speed' => self::get_detail_icon_html('wind_speed'),
            'wind_direction' => isset($aggregate['wind_from_direction']) ? esc_html(self::format_manual_value('wind_from_direction', $aggregate['wind_from_direction'], $meta)) : '',
            'wind_direction_value' => isset($aggregate['wind_from_direction']) ? esc_html((string) $aggregate['wind_from_direction']) : '',
            'icon-wind-direction' => self::get_detail_icon_html('wind_from_direction'),
            'humidity' => isset($aggregate['relative_humidity']) ? esc_html(self::format_manual_value('relative_humidity', $aggregate['relative_humidity'], $meta)) : '',
            'humidity_value' => isset($aggregate['relative_humidity']) ? esc_html((string) $aggregate['relative_humidity']) : '',
            'icon-humidity' => self::get_detail_icon_html('relative_humidity'),
            'pressure' => isset($aggregate['air_pressure_at_sea_level']) ? esc_html(self::format_manual_value('air_pressure_at_sea_level', $aggregate['air_pressure_at_sea_level'], $meta)) : '',
            'pressure_value' => isset($aggregate['air_pressure_at_sea_level']) ? esc_html((string) $aggregate['air_pressure_at_sea_level']) : '',
            'icon-pressure' => self::get_detail_icon_html('air_pressure_at_sea_level'),
            'clouds' => isset($aggregate['cloud_area_fraction']) ? esc_html(self::format_manual_value('cloud_area_fraction', $aggregate['cloud_area_fraction'], $meta)) : '',
            'clouds_value' => isset($aggregate['cloud_area_fraction']) ? esc_html((string) $aggregate['cloud_area_fraction']) : '',
            'icon-cloud-area-fraction' => self::get_detail_icon_html('cloud_area_fraction'),
            'precipitation' => isset($aggregate['precipitation_amount']) ? esc_html(self::format_manual_value('precipitation_amount', $aggregate['precipitation_amount'], $meta)) : '',
            'precipitation_value' => isset($aggregate['precipitation_amount']) ? esc_html((string) $aggregate['precipitation_amount']) : '',
            'icon-precipitation' => self::get_detail_icon_html('precipitation_amount'),
        ] + $now_time_tokens;
    }

    private static function manual_show_units(array $meta): bool
    {
        return !isset($meta['manual_show_units']) || (int) $meta['manual_show_units'] === 1;
    }

    private static function format_manual_value(string $key, $value, array $meta): string
    {
        if (!self::manual_show_units($meta)) {
            return (string) $value;
        }

        return I18N::format_value($key, $value);
    }

    private static function render_multi_day_manual_blocks(string $period, array $days, array $meta): string
    {
        $template_key = $period . '_item';
        $template = $meta['manual_templates'][$template_key] ?? CPT::default_manual_item_template($period);
        $html = '';

        foreach ($days as $date => $rows) {
            $tokens = self::get_multi_day_block_tokens((string) $date, is_array($rows) ? $rows : [], $meta);
            if (!$tokens) {
                continue;
            }

            $html .= self::render_manual_template($template, $tokens);
        }

        return $html;
    }

    private static function build_period_json_payload(string $period, array $data): array
    {
        $timeseries = $data['timeseries'] ?? [];
        $days = $data['days'] ?? [];

        if ($period === 'current') {
            $row = $timeseries[0] ?? null;
            if (!is_array($row)) {
                return [];
            }

            return self::normalize_timeseries_row($row);
        }

        if ($period === 'today') {
            $today_rows = array_values($days)[0] ?? [];
            return array_map([__CLASS__, 'normalize_timeseries_row'], is_array($today_rows) ? $today_rows : []);
        }

        $limit = $period === '9' ? 9 : 7;
        return self::slice_daily_rows($days, $limit);
    }

    private static function slice_daily_rows(array $days, int $limit): array
    {
        $result = [];
        $count = 0;

        foreach ($days as $date => $rows) {
            if ($count >= $limit) {
                break;
            }

            if (!is_array($rows) || !$rows) {
                continue;
            }

            $result[(string) $date] = array_values($rows);
            $count++;
        }

        return $result;
    }

    private static function normalize_timeseries_row(array $row): array
    {
        return $row;
    }

    public static function get_manual_template_tokens(string $period, array $meta, array $source, ?int $updated_at): array
    {
        $clock_format = self::get_site_time_format();
        $now_time_tokens = self::get_manual_now_time_tokens($meta);

        if ($period === 'today') {
            if (!$source) {
                return [];
            }

            $first_row = array_values($source)[0] ?? null;
            if (!is_array($first_row)) {
                return [];
            }

            $date_iso = substr((string) ($first_row['time'] ?? ''), 0, 10);
            $date_ts = $date_iso ? strtotime($date_iso . ' 00:00:00') : false;
            $short_text = (string) ($meta['custom_short_text'] ?? '');
            $long_text = wp_kses_post((string) ($meta['custom_long_text'] ?? ''));
            $title = (string) ($meta['_widget_title'] ?? '');
            $today_manual_step = max(1, min(3, intval($meta['today_manual_step'] ?? 1)));
            $rows = self::filter_today_manual_rows($source, $today_manual_step);
            $updated = !empty($updated_at)
                ? wp_date($clock_format, (int) $updated_at)
                : '';

            $tokens = [
                'title' => esc_html($title),
                'date' => $date_ts ? esc_html(I18N::format_date($date_ts)) : '',
                'weekday' => $date_ts ? esc_html(I18N::weekday_name($date_ts)) : '',
                'weekday_short' => $date_ts ? esc_html(I18N::weekday_short_name($date_ts)) : '',
                'hourly_count' => esc_html((string) count($rows)),
                'hourly_blocks' => self::render_today_manual_blocks($rows, $meta, $updated_at),
                'hourly_times' => self::render_today_manual_series(
                    $rows,
                    static function (array $row) use ($clock_format): string {
                        $time_iso = (string) ($row['time'] ?? '');
                        $timestamp = $time_iso ? strtotime($time_iso) : false;
                        return $timestamp ? esc_html(wp_date($clock_format, $timestamp)) : '';
                    },
                    'yrwp-manual-series-time'
                ),
                'hourly_weather_icons' => self::render_today_manual_series(
                    $rows,
                    static function (array $row): string {
                        $symbol_code = (string) ($row['data']['next_1_hours']['summary']['symbol_code'] ?? '');
                        if ($symbol_code === '') {
                            return '';
                        }

                        $svg = self::get_weather_icon_svg($symbol_code);
                        if ($svg === '') {
                            return '';
                        }

                        return $svg;
                    },
                    'yrwp-manual-series-weather-icon'
                ),
                'hourly_weather_texts' => self::render_today_manual_series(
                    $rows,
                    static function (array $row): string {
                        $symbol_code = (string) ($row['data']['next_1_hours']['summary']['symbol_code'] ?? '');
                        return esc_html(I18N::symbol_description($symbol_code));
                    },
                    'yrwp-manual-series-weather-text'
                ),
                'hourly_weather_codes' => self::render_today_manual_series(
                    $rows,
                    static function (array $row): string {
                        $symbol_code = (string) ($row['data']['next_1_hours']['summary']['symbol_code'] ?? '');
                        return esc_html(I18N::symbol_description($symbol_code));
                    },
                    'yrwp-manual-series-weather-code'
                ),
                'hourly_temperatures' => self::render_today_manual_series(
                    $rows,
                    static function (array $row) use ($meta): string {
                        $value = self::get_row_field_value($row, 'air_temperature');
                        return $value !== null ? esc_html(self::format_manual_value('air_temperature', $value, $meta)) : '';
                    },
                    'yrwp-manual-series-temperature'
                ),
                'hourly_temperature_icons' => self::render_today_manual_series(
                    $rows,
                    static function (): string {
                        return self::get_detail_icon_html('air_temperature');
                    },
                    'yrwp-manual-series-temperature-icon'
                ),
                'hourly_temperature_values' => self::render_today_manual_series(
                    $rows,
                    static function (array $row): string {
                        $value = self::get_row_field_value($row, 'air_temperature');
                        return $value !== null ? esc_html((string) $value) : '';
                    },
                    'yrwp-manual-series-temperature-value'
                ),
                'hourly_wind_speeds' => self::render_today_manual_series(
                    $rows,
                    static function (array $row) use ($meta): string {
                        $value = self::get_row_field_value($row, 'wind_speed');
                        return $value !== null ? esc_html(self::format_manual_value('wind_speed', $value, $meta)) : '';
                    },
                    'yrwp-manual-series-wind-speed'
                ),
                'hourly_wind_speed_icons' => self::render_today_manual_series(
                    $rows,
                    static function (): string {
                        return self::get_detail_icon_html('wind_speed');
                    },
                    'yrwp-manual-series-wind-speed-icon'
                ),
                'hourly_wind_speed_values' => self::render_today_manual_series(
                    $rows,
                    static function (array $row): string {
                        $value = self::get_row_field_value($row, 'wind_speed');
                        return $value !== null ? esc_html((string) $value) : '';
                    },
                    'yrwp-manual-series-wind-speed-value'
                ),
                'hourly_wind_directions' => self::render_today_manual_series(
                    $rows,
                    static function (array $row) use ($meta): string {
                        $value = self::get_row_field_value($row, 'wind_from_direction');
                        return $value !== null ? esc_html(self::format_manual_value('wind_from_direction', $value, $meta)) : '';
                    },
                    'yrwp-manual-series-wind-direction'
                ),
                'hourly_wind_direction_icons' => self::render_today_manual_series(
                    $rows,
                    static function (): string {
                        return self::get_detail_icon_html('wind_from_direction');
                    },
                    'yrwp-manual-series-wind-direction-icon'
                ),
                'hourly_wind_direction_values' => self::render_today_manual_series(
                    $rows,
                    static function (array $row): string {
                        $value = self::get_row_field_value($row, 'wind_from_direction');
                        return $value !== null ? esc_html((string) $value) : '';
                    },
                    'yrwp-manual-series-wind-direction-value'
                ),
                'hourly_humidities' => self::render_today_manual_series(
                    $rows,
                    static function (array $row) use ($meta): string {
                        $value = self::get_row_field_value($row, 'relative_humidity');
                        return $value !== null ? esc_html(self::format_manual_value('relative_humidity', $value, $meta)) : '';
                    },
                    'yrwp-manual-series-humidity'
                ),
                'hourly_humidity_icons' => self::render_today_manual_series(
                    $rows,
                    static function (): string {
                        return self::get_detail_icon_html('relative_humidity');
                    },
                    'yrwp-manual-series-humidity-icon'
                ),
                'hourly_humidity_values' => self::render_today_manual_series(
                    $rows,
                    static function (array $row): string {
                        $value = self::get_row_field_value($row, 'relative_humidity');
                        return $value !== null ? esc_html((string) $value) : '';
                    },
                    'yrwp-manual-series-humidity-value'
                ),
                'hourly_pressures' => self::render_today_manual_series(
                    $rows,
                    static function (array $row) use ($meta): string {
                        $value = self::get_row_field_value($row, 'air_pressure_at_sea_level');
                        return $value !== null ? esc_html(self::format_manual_value('air_pressure_at_sea_level', $value, $meta)) : '';
                    },
                    'yrwp-manual-series-pressure'
                ),
                'hourly_pressure_icons' => self::render_today_manual_series(
                    $rows,
                    static function (): string {
                        return self::get_detail_icon_html('air_pressure_at_sea_level');
                    },
                    'yrwp-manual-series-pressure-icon'
                ),
                'hourly_pressure_values' => self::render_today_manual_series(
                    $rows,
                    static function (array $row): string {
                        $value = self::get_row_field_value($row, 'air_pressure_at_sea_level');
                        return $value !== null ? esc_html((string) $value) : '';
                    },
                    'yrwp-manual-series-pressure-value'
                ),
                'hourly_clouds' => self::render_today_manual_series(
                    $rows,
                    static function (array $row) use ($meta): string {
                        $value = self::get_row_field_value($row, 'cloud_area_fraction');
                        return $value !== null ? esc_html(self::format_manual_value('cloud_area_fraction', $value, $meta)) : '';
                    },
                    'yrwp-manual-series-clouds'
                ),
                'hourly_cloud_icons' => self::render_today_manual_series(
                    $rows,
                    static function (): string {
                        return self::get_detail_icon_html('cloud_area_fraction');
                    },
                    'yrwp-manual-series-cloud-icon'
                ),
                'hourly_cloud_values' => self::render_today_manual_series(
                    $rows,
                    static function (array $row): string {
                        $value = self::get_row_field_value($row, 'cloud_area_fraction');
                        return $value !== null ? esc_html((string) $value) : '';
                    },
                    'yrwp-manual-series-cloud-value'
                ),
                'hourly_precipitation' => self::render_today_manual_series(
                    $rows,
                    static function (array $row) use ($meta): string {
                        $value = self::get_row_field_value($row, 'precipitation_amount');
                        return $value !== null ? esc_html(self::format_manual_value('precipitation_amount', $value, $meta)) : '';
                    },
                    'yrwp-manual-series-precipitation'
                ),
                'hourly_precipitation_icons' => self::render_today_manual_series(
                    $rows,
                    static function (): string {
                        return self::get_detail_icon_html('precipitation_amount');
                    },
                    'yrwp-manual-series-precipitation-icon'
                ),
                'hourly_precipitation_values' => self::render_today_manual_series(
                    $rows,
                    static function (array $row): string {
                        $value = self::get_row_field_value($row, 'precipitation_amount');
                        return $value !== null ? esc_html((string) $value) : '';
                    },
                    'yrwp-manual-series-precipitation-value'
                ),
                'updated_at' => esc_html($updated),
                'short_text' => esc_html($short_text),
                'long_text' => $long_text,
            ] + $now_time_tokens;

            return $tokens;
        }

        if ($period === '7' || $period === '9') {
            if (!$source) {
                return [];
            }

            $source = self::filter_multi_day_manual_days($period, $source, $meta);
            if (!$source) {
                return [];
            }

            $first_date = array_key_first($source);
            $short_text = (string) ($meta['custom_short_text'] ?? '');
            $long_text = wp_kses_post((string) ($meta['custom_long_text'] ?? ''));
            $title = (string) ($meta['_widget_title'] ?? '');
            $updated = !empty($updated_at)
                ? wp_date($clock_format, (int) $updated_at)
                : '';

            return [
                'title' => esc_html($title),
                'days_count' => esc_html((string) count($source)),
                'daily_blocks' => self::render_multi_day_manual_blocks($period, $source, $meta),
                'daily_dates' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date): string {
                        $date_ts = strtotime($date . ' 00:00:00');
                        return $date_ts ? esc_html(I18N::format_date($date_ts)) : esc_html($date);
                    }
                ),
                'daily_weekdays' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date): string {
                        $date_ts = strtotime($date . ' 00:00:00');
                        return $date_ts ? esc_html(I18N::weekday_name($date_ts)) : '';
                    }
                ),
                'daily_weekdays_short' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date): string {
                        $date_ts = strtotime($date . ' 00:00:00');
                        return $date_ts ? esc_html(I18N::weekday_short_name($date_ts)) : '';
                    }
                ),
                'daily_weather_icons' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows);
                        return $tokens['weather_icon'] ?? '';
                    }
                ),
                'daily_weather_texts' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows);
                        return $tokens['weather_text'] ?? '';
                    }
                ),
                'daily_weather_codes' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows);
                        return $tokens['weather_code'] ?? '';
                    }
                ),
                'daily_min_temperatures' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows) use ($meta): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows, $meta);
                        return $tokens['min_temperature'] ?? '';
                    }
                ),
                'daily_temperature_icons' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows);
                        return $tokens['icon-temperature'] ?? '';
                    }
                ),
                'daily_min_temperature_values' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows);
                        return $tokens['min_temperature_value'] ?? '';
                    }
                ),
                'daily_max_temperatures' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows) use ($meta): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows, $meta);
                        return $tokens['max_temperature'] ?? '';
                    }
                ),
                'daily_max_temperature_values' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows);
                        return $tokens['max_temperature_value'] ?? '';
                    }
                ),
                'daily_wind_speeds' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows) use ($meta): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows, $meta);
                        return $tokens['wind_speed'] ?? '';
                    }
                ),
                'daily_wind_speed_icons' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows);
                        return $tokens['icon-wind-speed'] ?? '';
                    }
                ),
                'daily_wind_speed_values' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows);
                        return $tokens['wind_speed_value'] ?? '';
                    }
                ),
                'daily_wind_directions' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows) use ($meta): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows, $meta);
                        return $tokens['wind_direction'] ?? '';
                    }
                ),
                'daily_wind_direction_icons' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows);
                        return $tokens['icon-wind-direction'] ?? '';
                    }
                ),
                'daily_wind_direction_values' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows);
                        return $tokens['wind_direction_value'] ?? '';
                    }
                ),
                'daily_humidities' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows) use ($meta): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows, $meta);
                        return $tokens['humidity'] ?? '';
                    }
                ),
                'daily_humidity_icons' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows);
                        return $tokens['icon-humidity'] ?? '';
                    }
                ),
                'daily_humidity_values' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows);
                        return $tokens['humidity_value'] ?? '';
                    }
                ),
                'daily_pressures' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows) use ($meta): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows, $meta);
                        return $tokens['pressure'] ?? '';
                    }
                ),
                'daily_pressure_icons' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows);
                        return $tokens['icon-pressure'] ?? '';
                    }
                ),
                'daily_pressure_values' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows);
                        return $tokens['pressure_value'] ?? '';
                    }
                ),
                'daily_clouds' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows) use ($meta): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows, $meta);
                        return $tokens['clouds'] ?? '';
                    }
                ),
                'daily_cloud_icons' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows);
                        return $tokens['icon-cloud-area-fraction'] ?? '';
                    }
                ),
                'daily_cloud_values' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows);
                        return $tokens['clouds_value'] ?? '';
                    }
                ),
                'daily_precipitation' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows) use ($meta): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows, $meta);
                        return $tokens['precipitation'] ?? '';
                    }
                ),
                'daily_precipitation_icons' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows);
                        return $tokens['icon-precipitation'] ?? '';
                    }
                ),
                'daily_precipitation_values' => self::render_multi_day_manual_series(
                    $source,
                    static function (string $date, array $rows): string {
                        $tokens = Shortcode::get_multi_day_block_tokens($date, $rows);
                        return $tokens['precipitation_value'] ?? '';
                    }
                ),
                'updated_at' => esc_html($updated),
                'short_text' => esc_html($short_text),
                'long_text' => $long_text,
            ] + $now_time_tokens;
        }

        if ($period !== 'current') {
            return [];
        }

        $row = $source;
        $instant = $row['data']['instant']['details'] ?? [];
        $next_1 = $row['data']['next_1_hours'] ?? [];
        $symbol_code = $next_1['summary']['symbol_code'] ?? '';
        $time_iso = $row['time'] ?? '';
        $icon_size = self::WEATHER_ICON_SIZE;

        $weather_icon = '';
        if ($symbol_code) {
            $svg = self::get_weather_icon_svg($symbol_code);
            if ($svg !== '') {
                $weather_icon = $svg;
            }
        }

        $timestamp = $time_iso ? strtotime($time_iso) : false;
        $time = $timestamp
            ? wp_date($clock_format, $timestamp)
            : '';
        $date = $timestamp
            ? I18N::format_date($timestamp)
            : '';
        $weekday = $timestamp
            ? I18N::weekday_name($timestamp)
            : '';
        $updated = !empty($updated_at)
            ? wp_date($clock_format, (int) $updated_at)
            : '';

        $field_value = static function (string $key) use ($row, $instant): string {
            $value = Shortcode::get_row_field_value($row, $key);
            return $value !== null ? (string) $value : '';
        };

        $field_format = static function (string $key) use ($row, $meta): string {
            $value = Shortcode::get_row_field_value($row, $key);
            return $value !== null ? self::format_manual_value($key, $value, $meta) : '';
        };

        $short_text = (string) ($meta['custom_short_text'] ?? '');
        $long_text = wp_kses_post((string) ($meta['custom_long_text'] ?? ''));
        $title = (string) ($meta['_widget_title'] ?? '');

        return [
            'title' => esc_html($title),
            'date' => esc_html($date),
            'weekday' => esc_html($weekday),
            'weekday_short' => $timestamp ? esc_html(I18N::weekday_short_name($timestamp)) : '',
            'weather_icon' => $weather_icon,
            'weather_icons' => $weather_icon,
            'weather_code' => esc_html(I18N::symbol_description($symbol_code)),
            'weather_text' => esc_html(I18N::symbol_description($symbol_code)),
            'temperature' => esc_html($field_format('air_temperature')),
            'temperature_value' => esc_html($field_value('air_temperature')),
            'icon-temperature' => self::get_detail_icon_html('air_temperature'),
            'wind' => esc_html($field_format('wind_speed')),
            'wind_speed' => esc_html($field_format('wind_speed')),
            'wind_speed_value' => esc_html($field_value('wind_speed')),
            'icon-wind-speed' => self::get_detail_icon_html('wind_speed'),
            'wind_direction' => esc_html($field_format('wind_from_direction')),
            'wind_direction_value' => esc_html($field_value('wind_from_direction')),
            'icon-wind-direction' => self::get_detail_icon_html('wind_from_direction'),
            'humidity' => esc_html($field_format('relative_humidity')),
            'humidity_value' => esc_html($field_value('relative_humidity')),
            'icon-humidity' => self::get_detail_icon_html('relative_humidity'),
            'pressure' => esc_html($field_format('air_pressure_at_sea_level')),
            'pressure_value' => esc_html($field_value('air_pressure_at_sea_level')),
            'icon-pressure' => self::get_detail_icon_html('air_pressure_at_sea_level'),
            'clouds' => esc_html($field_format('cloud_area_fraction')),
            'clouds_value' => esc_html($field_value('cloud_area_fraction')),
            'icon-cloud-area-fraction' => self::get_detail_icon_html('cloud_area_fraction'),
            'precipitation' => esc_html($field_format('precipitation_amount')),
            'precipitation_value' => esc_html($field_value('precipitation_amount')),
            'icon-precipitation' => self::get_detail_icon_html('precipitation_amount'),
            'forecast-time' => esc_html($time),
            'updated_at' => esc_html($updated),
            'short_text' => esc_html($short_text),
            'long_text' => $long_text,
        ] + $now_time_tokens;
    }

    private static function get_manual_now_time_tokens(array $meta): array
    {
        $clock_format = self::get_site_time_format();
        $location_timezone = MET::normalize_timezone($meta['location_timezone'] ?? '');
        $site_timezone = wp_timezone()->getName();

        return [
            'now-time' => esc_html(wp_date($clock_format, current_time('timestamp', true))),
            'location-now-time' => $location_timezone !== ''
                ? esc_html(wp_date($clock_format, time(), new \DateTimeZone($location_timezone)))
                : '',
            '_clock_format' => $clock_format,
            '_site_timezone' => $site_timezone,
            '_location_timezone' => $location_timezone,
        ];
    }

    private static function render_live_time_token(string $token, array $tokens): string
    {
        $value = (string) ($tokens[$token] ?? '');
        $timezone = $token === 'location-now-time'
            ? (string) ($tokens['_location_timezone'] ?? '')
            : (string) ($tokens['_site_timezone'] ?? '');
        $format = (string) ($tokens['_clock_format'] ?? 'H:i');

        if ($timezone === '' || $value === '') {
            return $value;
        }

        return '<span class="yrwp-live-time" data-yrwp-time-token="'
            . esc_attr($token)
            . '" data-yrwp-timezone="'
            . esc_attr($timezone)
            . '" data-yrwp-format="'
            . esc_attr($format)
            . '">'
            . $value
            . '</span>';
    }

    private static function enrich_meta_with_location_timezone(array $meta, array $api): array
    {
        $location_timezone = MET::normalize_timezone($meta['location_timezone'] ?? '');
        if ($location_timezone !== '') {
            $meta['location_timezone'] = $location_timezone;
            return $meta;
        }

        $mode = $meta['location_mode'] ?? 'manual_coords';
        if ($mode === 'city') {
            $city_timezone = MET::normalize_timezone($meta['city_timezone'] ?? '');
            if ($city_timezone !== '') {
                $meta['location_timezone'] = $city_timezone;
                return $meta;
            }
        }

        [$lat, $lon] = self::resolve_location_coordinates($meta, $api);
        if ($lat === null || $lon === null) {
            return $meta;
        }

        $resolved_timezone = MET::get_cached_timezone_for_coordinates($lat, $lon);
        if ($resolved_timezone !== '') {
            $meta['location_timezone'] = $resolved_timezone;
            if ($mode === 'city') {
                $meta['city_timezone'] = $resolved_timezone;
            }
        }

        return $meta;
    }

    private static function resolve_location_coordinates(array $meta, array $api): array
    {
        $mode = $meta['location_mode'] ?? 'manual_coords';
        if ($mode === 'city') {
            $lat = array_key_exists('city_lat', $meta) ? floatval($meta['city_lat']) : null;
            $lon = array_key_exists('city_lon', $meta) ? floatval($meta['city_lon']) : null;
        } else {
            $lat = array_key_exists('lat', $meta) ? floatval($meta['lat']) : null;
            $lon = array_key_exists('lon', $meta) ? floatval($meta['lon']) : null;
        }

        if (($lat || $lon) && $lat !== null && $lon !== null) {
            return [$lat, $lon];
        }

        $coordinates = $api['geometry']['coordinates'] ?? null;
        if (!is_array($coordinates) || !isset($coordinates[0], $coordinates[1])) {
            return [null, null];
        }

        return [floatval($coordinates[1]), floatval($coordinates[0])];
    }

    private static function group_by_day(array $timeseries): array
    {
        $days = [];

        foreach ($timeseries as $row) {
            $time = $row['time'] ?? null;
            if (!$time) continue;

            $date = substr($time, 0, 10);
            $days[$date][] = $row;
        }

        return $days;
    }
}
