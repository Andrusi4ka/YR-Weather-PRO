<?php

namespace YRWP;

if (!defined('ABSPATH')) exit;

class Rest
{
    private const LEGACY_NINE_DAY_KEY = '1' . '4';

    public static function init()
    {
        add_action('rest_api_init', [__CLASS__, 'routes']);
    }

    public static function routes()
    {
        register_rest_route('yrwp/v1', '/weather', [
            'methods'  => 'GET',
            'permission_callback' => '__return_true',
            'args' => [
                'id'  => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && intval($param) > 0;
                    }
                ],
                'lat' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    }
                ],
                'lon' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    }
                ],
            ],
            'callback' => [__CLASS__, 'get_weather'],
        ]);

        register_rest_route('yrwp/v1', '/data', [
            'methods'  => 'GET',
            'permission_callback' => '__return_true',
            'args' => [
                'id'  => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && intval($param) > 0;
                    }
                ],
                'period' => [
                    'required' => false,
                    'validate_callback' => function ($param) {
                        return in_array((string) $param, ['current', 'today', '7', '9'], true);
                    }
                ],
                'lat' => [
                    'required' => false,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    }
                ],
                'lon' => [
                    'required' => false,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    }
                ],
            ],
            'callback' => [__CLASS__, 'get_weather_data'],
        ]);
    }

    public static function get_weather(\WP_REST_Request $req)
    {
        if (!Admin::has_valid_access_key()) {
            return new \WP_REST_Response([
                'ok'    => false,
                'error' => I18N::t('access_key_required')
            ], 403);
        }

        $id  = intval($req->get_param('id'));
        $lat = floatval($req->get_param('lat'));
        $lon = floatval($req->get_param('lon'));

        if (!$id || !$lat && !$lon) {
            return new \WP_REST_Response([
                'ok'    => false,
                'error' => 'Invalid parameters'
            ], 400);
        }

        $post = get_post($id);
        if (!$post || $post->post_type !== CPT::POST_TYPE) {
            return new \WP_REST_Response([
                'ok'    => false,
                'error' => 'Not found'
            ], 404);
        }

        $meta = CPT::get_meta($id);
        if (empty($meta['enabled'])) {
            return new \WP_REST_Response([
                'ok'    => false,
                'error' => 'Disabled'
            ], 200);
        }

        $ttl = intval($meta['refresh'] ?? 1800);
        $res = MET::get_cached_for_widget($id, $lat, $lon, $ttl);

        if (empty($res['ok'])) {
            return new \WP_REST_Response([
                'ok'    => false,
                'error' => 'Weather unavailable'
            ], 200);
        }

        // Використовує нову систему template override через Shortcode
        $html = Shortcode::render_from_api(
            $meta,
            $res['data'],
            $res['updated_at'] ?? null
        );

        return new \WP_REST_Response([
            'ok'   => true,
            'html' => $html,
        ], 200);
    }

    public static function get_weather_data(\WP_REST_Request $req)
    {
        if (!Admin::has_valid_access_key()) {
            return new \WP_REST_Response([
                'ok'    => false,
                'error' => I18N::t('access_key_required')
            ], 403);
        }

        $id = intval($req->get_param('id'));
        $context = self::resolve_widget_context($id);

        if ($context instanceof \WP_REST_Response) {
            return $context;
        }

        $meta = $context['meta'];
        $period = (string) ($req->get_param('period') ?: ($meta['period'] ?? 'current'));
        if ($period === self::LEGACY_NINE_DAY_KEY) {
            $period = '9';
        }
        if (!in_array($period, ['current', 'today', '7', '9'], true)) {
            $period = 'current';
        }

        $coordinates = self::resolve_coordinates($meta, $req);
        if ($coordinates instanceof \WP_REST_Response) {
            return $coordinates;
        }

        $ttl = intval($meta['refresh'] ?? 1800);
        $res = MET::get_cached_for_widget($id, $coordinates['lat'], $coordinates['lon'], $ttl);

        if (empty($res['ok'])) {
            return new \WP_REST_Response([
                'ok'    => false,
                'error' => 'Weather unavailable'
            ], 200);
        }

        $payload = Shortcode::get_period_json_payload($period, [
            'meta' => $meta,
            'period' => $period,
            'timeseries' => $res['data']['properties']['timeseries'] ?? [],
            'days' => self::group_timeseries_by_day($res['data']['properties']['timeseries'] ?? []),
            'updated_at' => $res['updated_at'] ?? null,
            'template' => $period . '-json',
        ]);

        return new \WP_REST_Response($payload, 200);
    }

    private static function resolve_widget_context(int $id)
    {
        if (!$id) {
            return new \WP_REST_Response([
                'ok'    => false,
                'error' => 'Invalid parameters'
            ], 400);
        }

        $post = get_post($id);
        if (!$post || $post->post_type !== CPT::POST_TYPE) {
            return new \WP_REST_Response([
                'ok'    => false,
                'error' => 'Not found'
            ], 404);
        }

        $meta = CPT::get_meta($id);
        if (empty($meta['enabled'])) {
            return new \WP_REST_Response([
                'ok'    => false,
                'error' => 'Disabled'
            ], 200);
        }

        return [
            'post' => $post,
            'meta' => $meta,
        ];
    }

    private static function resolve_coordinates(array $meta, \WP_REST_Request $req)
    {
        $lat_param = $req->get_param('lat');
        $lon_param = $req->get_param('lon');

        if ($lat_param !== null && $lon_param !== null) {
            $lat = floatval($lat_param);
            $lon = floatval($lon_param);

            if (!$lat && !$lon) {
                return new \WP_REST_Response([
                    'ok'    => false,
                    'error' => 'Invalid parameters'
                ], 400);
            }

            return [
                'lat' => $lat,
                'lon' => $lon,
            ];
        }

        $mode = $meta['location_mode'] ?? 'manual_coords';
        if ($mode === 'city') {
            $lat = floatval($meta['city_lat'] ?? 0);
            $lon = floatval($meta['city_lon'] ?? 0);
        } else {
            $lat = floatval($meta['lat'] ?? 0);
            $lon = floatval($meta['lon'] ?? 0);
        }

        if (!$lat && !$lon) {
            return new \WP_REST_Response([
                'ok'    => false,
                'error' => 'Coordinates are not set'
            ], 200);
        }

        return [
            'lat' => $lat,
            'lon' => $lon,
        ];
    }

    private static function group_timeseries_by_day(array $timeseries): array
    {
        $days = [];

        foreach ($timeseries as $row) {
            $time = $row['time'] ?? '';
            if ($time === '') {
                continue;
            }

            $date = substr((string) $time, 0, 10);
            $days[$date][] = $row;
        }

        return $days;
    }
}
