<?php
if (!defined('ABSPATH')) exit;
/**
 * Template: current manual
 *
 * Available variables (from Shortcode::load_template):
 *  - $meta (array)
 *  - $period (string)
 *  - $timeseries (array)
 *  - $days (array)
 *  - $updated_at (?int)
 *  - $template (?string)
 */

$row = $timeseries[0] ?? null;
if (!$row) {
    echo '<div class="yrwp-body yrwp-error">No data.</div>';
    return;
}

$manual_template = $meta['manual_templates']['current'] ?? \YRWP\CPT::default_manual_template('current');
$tokens = \YRWP\Shortcode::get_manual_template_tokens('current', $meta, $row, $updated_at);
$html = \YRWP\Shortcode::render_manual_template($manual_template, $tokens);
echo $html;
