<?php

namespace YRWP;

if (!defined('ABSPATH')) exit;

class Rest
{
    // Period value saved by older versions for what is now the 9-day forecast.
    private const LEGACY_NINE_DAY_KEY = '14';

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
        $id = intval($req->get_param('id'));
        $context = self::resolve_widget_context($id);
        if ($context instanceof \WP_REST_Response) {
            return $context;
        }

        $meta = $context['meta'];
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

    /**
     * Coordinates for a widget's forecast. Coordinates sent with the request
     * count only for a widget that shows the visitor's location, and are
     * rounded to 2 decimals (about 1 km): every distinct pair costs a request
     * to MET and a cached forecast, so the endpoint must not take arbitrary ones.
     * Other widgets always use their own saved location.
     */
    private static function resolve_coordinates(array $meta, \WP_REST_Request $req)
    {
        $mode = $meta['location_mode'] ?? 'manual_coords';

        if ($mode === 'visitor_geo') {
            $lat = $req->get_param('lat');
            $lon = $req->get_param('lon');

            if (!is_numeric($lat) || !is_numeric($lon) || abs((float) $lat) > 90 || abs((float) $lon) > 180) {
                return new \WP_REST_Response([
                    'ok'    => false,
                    'error' => 'Invalid parameters'
                ], 400);
            }

            return [
                'lat' => round((float) $lat, 2),
                'lon' => round((float) $lon, 2),
            ];
        }

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
