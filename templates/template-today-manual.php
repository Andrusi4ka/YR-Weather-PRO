<?php
if (!defined('ABSPATH')) exit;
/**
 * Template: today manual
 *
 * Available variables (from Shortcode::load_template):
 *  - $meta (array)
 *  - $period (string)
 *  - $timeseries (array)
 *  - $days (array)
 *  - $updated_at (?int)
 *  - $template (?string)
 */

$today_rows = array_values($days)[0] ?? [];
if (!$today_rows) {
    echo '<div class="yrwp-body yrwp-error">No data.</div>';
    return;
}

$manual_template = $meta['manual_templates']['today'] ?? \YRWP\CPT::default_manual_template('today');
$tokens = \YRWP\Shortcode::get_manual_template_tokens('today', $meta, $today_rows, $updated_at);
$html = \YRWP\Shortcode::render_manual_template($manual_template, $tokens);
echo $html;
