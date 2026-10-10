<?php
if (!defined('ABSPATH')) exit;

if (!empty($meta['title_show'])) {
    echo '<h3 class="yrwp-title">' . esc_html($meta['_widget_title'] ?? '') . '</h3>';
}

$row = $timeseries[0] ?? null;
if (!$row || !is_array($row)) {
    echo '<pre class="yrwp-json-output">' . esc_html(wp_json_encode([
        'ok' => false,
        'error' => 'No data',
        'period' => 'current',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '</pre>';
    return;
}

echo '<pre class="yrwp-json-output">' . esc_html(\YRWP\Shortcode::render_period_json('current', [
    'meta' => $meta,
    'period' => $period,
    'timeseries' => $timeseries,
    'days' => $days,
    'updated_at' => $updated_at,
])) . '</pre>';
