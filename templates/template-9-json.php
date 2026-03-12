<?php
if (!defined('ABSPATH')) exit;

if (!empty($meta['title_show'])) {
    echo '<h3 class="yrwp-title">' . esc_html($meta['_widget_title'] ?? '') . '</h3>';
}

echo '<pre class="yrwp-json-output">' . esc_html(\YRWP\Shortcode::render_period_json('9', [
    'meta' => $meta,
    'period' => $period,
    'timeseries' => $timeseries,
    'days' => $days,
    'updated_at' => $updated_at,
])) . '</pre>';
