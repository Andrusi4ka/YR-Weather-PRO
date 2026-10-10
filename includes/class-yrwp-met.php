<?php
namespace YRWP;

if (!defined('ABSPATH')) exit;

class MET {
    public static function user_agent() : string {
        $opts = get_option(Admin::OPT_KEY, []);
        $email = $opts['user_agent_email'] ?? '';
        $site = home_url();
        $ua = 'YRWP/' . YRWP_VERSION . ' ' . $site;
        if ($email) $ua .= ' ' . $email;
        return $ua;
    }

    public static function fetch_compact(float $lat, float $lon) : array {
        if (!Admin::has_valid_access_key()) {
            return ['ok' => false, 'error' => I18N::t('access_key_required')];
        }

        // MET asks for at most 4 decimals (it ignores the rest).
        $url = add_query_arg([
            'lat' => round($lat, 4),
            'lon' => round($lon, 4),
        ], 'https://api.met.no/weatherapi/locationforecast/2.0/compact');

        $resp = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => self::user_agent(), // MET requires an identifying User-Agent.
            ],
        ]);

        if (is_wp_error($resp)) {
            return ['ok' => false, 'error' => $resp->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);

        if ($code !== 200 || !is_array($data)) {
            return ['ok' => false, 'error' => 'API error', 'status' => $code];
        }

        return ['ok' => true, 'data' => $data];
    }

    public static function normalize_timezone($timezone): string {
        $timezone = trim((string) $timezone);
        if ($timezone === '') {
            return '';
        }

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : '';
    }

    public static function get_cached_timezone_for_coordinates(float $lat, float $lon): string {
        if (!$lat && !$lon) {
            return '';
        }

        $key = 'yrwp_timezone_' . md5(sprintf('%.4F:%.4F', $lat, $lon));
        $cached = get_transient($key);
        if (is_string($cached)) {
            return self::normalize_timezone($cached);
        }

        $timezone = self::fetch_timezone_for_coordinates($lat, $lon);
        if ($timezone !== '') {
            set_transient($key, $timezone, WEEK_IN_SECONDS * 4);
        }

        return $timezone;
    }

    private static function fetch_timezone_for_coordinates(float $lat, float $lon): string {
        if (!Admin::has_valid_access_key()) {
            return '';
        }

        $url = add_query_arg([
            'latitude' => $lat,
            'longitude' => $lon,
            'language' => 'en',
            'format' => 'json',
        ], 'https://geocoding-api.open-meteo.com/v1/reverse');

        $resp = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => self::user_agent(),
            ],
        ]);

        if (is_wp_error($resp)) {
            return '';
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);
        if ($code !== 200 || !is_array($data)) {
            return '';
        }

        $results = $data['results'] ?? [];
        if (!is_array($results) || !$results) {
            return '';
        }

        return self::normalize_timezone($results[0]['timezone'] ?? '');
    }

    public static function get_cached_for_widget(int $widget_id, float $lat, float $lon, int $ttl) : array {
        $key = 'yrwp_cache_' . $widget_id . '_' . md5($lat . ':' . $lon);
        $cached = get_transient($key);
        if (is_array($cached)) return $cached;

        $res = self::fetch_compact($lat, $lon);
        if (!$res['ok']) return $res;

        $payload = [
            'ok' => true,
            'data' => $res['data'],
            'updated_at' => time(),
            'lat' => $lat,
            'lon' => $lon,
        ];
        set_transient($key, $payload, max(60, $ttl));
        return $payload;
    }
}
