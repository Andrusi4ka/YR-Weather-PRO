<?php
if (!defined('ABSPATH')) exit;
/**
 * Template: 9 days manual
 *
 * Available variables (from Shortcode::load_template):
 *  - $meta (array)
 *  - $period (string)
 *  - $timeseries (array)
 *  - $days (array)
 *  - $updated_at (?int)
 *  - $template (?string)
 */

$forecast_days = array_slice($days, 0, 9, true);
if (!$forecast_days) {
    echo '<div class="yrwp-body yrwp-error">No data.</div>';
    return;
}

$manual_template = $meta['manual_templates']['9'] ?? \YRWP\CPT::default_manual_template('9');
$tokens = \YRWP\Shortcode::get_manual_template_tokens('9', $meta, $forecast_days, $updated_at);
$html = \YRWP\Shortcode::render_manual_template($manual_template, $tokens);
echo $html;
