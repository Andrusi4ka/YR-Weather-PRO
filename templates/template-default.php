<?php
if (!defined('ABSPATH')) exit;
/**
 * Template: current (default) — FULL FIELDS HTML
 *
 * Available variables (from Shortcode::load_template):
 *  - $meta (array)
 *  - $period (string)
 *  - $timeseries (array)
 *  - $days (array)
 *  - $updated_at (?int)
 *  - $template (?string)
 */

wp_enqueue_style('yrwp-template-default', YRWP_URL . 'assets/style/templates/template-default.css', ['yrwp-frontend'], YRWP_VERSION);

$row = $timeseries[0] ?? null;
if (!$row) {
    echo '<div class="yrwp-body yrwp-error">No data.</div>';
    return;
}

$instant_details = $row['data']['instant']['details'] ?? [];

$next_1  = $row['data']['next_1_hours']  ?? [];
$next_6  = $row['data']['next_6_hours']  ?? [];
$next_12 = $row['data']['next_12_hours'] ?? [];

$symbol_1  = $next_1['summary']['symbol_code']  ?? null;
$symbol_6  = $next_6['summary']['symbol_code']  ?? null;
$symbol_12 = $next_12['summary']['symbol_code'] ?? null;

$details_1  = $next_1['details']  ?? [];
$details_6  = $next_6['details']  ?? [];
$details_12 = $next_12['details'] ?? [];

$time_iso = $row['time'] ?? '';
?>

<div class="yrwp-body yrwp-template-current-default yrwp-template-current-full">

    <div class="yrwp-current-full" data-yrwp-period="<?php echo esc_attr($period ?? 'current'); ?>">

        <!-- Custom cort text -->
        <?php if (!empty($meta['custom_short_text'])) : ?>
            <div class="yrwp-custom-text-short blabla">
                <?php echo esc_html($meta['custom_short_text']); ?>
            </div>
        <?php endif; ?>

        <!-- Custom long text -->
        <?php if (!empty($meta['custom_long_text'])) : ?>
            <div class="yrwp-custom-text-long">
                <?php echo wp_kses_post($meta['custom_long_text']); ?>
            </div>
        <?php endif; ?>

        <!-- Header / Meta -->
        <header class="yrwp-current-full__header">

            <?php $clock_format = \YRWP\Shortcode::get_site_time_format(); ?>

            <?php if (!empty($time_iso)) : ?>
                <div class="yrwp-current-full__time" data-field="time">
                    <span class="yrwp-label">time</span>
                    <?php $timestamp = strtotime($time_iso); ?>
                    <span class="yrwp-value">
                        <?php echo esc_html(wp_date($clock_format, $timestamp)); ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if (!empty($updated_at)) : ?>
                <div class="yrwp-current-full__updated" data-field="updated_at">
                    <span class="yrwp-label">updated_at</span>
                    <span class="yrwp-value">
                        <?php echo esc_html(wp_date($clock_format, (int) $updated_at)); ?>
                    </span>
                </div>
            <?php endif; ?>

        </header>

        <!-- Symbols -->
        <section class="yrwp-current-full__symbols">
            <!-- Symbols Now -->
            <div class="yrwp-symbol" data-field="symbol_code" data-range="next_1_hours">
                <?php
                if (!empty($symbol_1)) {

                    $size = 150;

                    $svg = \YRWP\Shortcode::get_weather_icon_svg($symbol_1);

                    if ($svg) {
                        echo '<div class="yrwp-icon" style="width:' . esc_attr($size) . 'px;height:' . esc_attr($size) . 'px;">'
                            . $svg .
                            '</div>';
                    }
                }
                ?>
                <span class="yrwp-value"><?php echo esc_html((string)$symbol_1); ?></span>
            </div>

            <!-- Symbols 6 hours-->
            <div class="yrwp-symbol" data-field="symbol_code" data-range="next_1_hours">
                <?php
                if (!empty($symbol_6)) {

                    $size = 150;

                    $svg = \YRWP\Shortcode::get_weather_icon_svg($symbol_6);

                    if ($svg) {
                        echo '<div class="yrwp-icon" style="width:' . esc_attr($size) . 'px;height:' . esc_attr($size) . 'px;">'
                            . $svg .
                            '</div>';
                    }
                }
                ?>
                <span class="yrwp-value"><?php echo esc_html((string)$symbol_6); ?></span>
            </div>

            <!-- Symbols 12 hours-->
            <div class="yrwp-symbol" data-field="symbol_code" data-range="next_1_hours">
                <?php
                if (!empty($symbol_12)) {

                    $size = 150;

                    $svg = \YRWP\Shortcode::get_weather_icon_svg($symbol_12);

                    if ($svg) {
                        echo '<div class="yrwp-icon" style="width:' . esc_attr($size) . 'px;height:' . esc_attr($size) . 'px;">'
                            . $svg .
                            '</div>';
                    }
                }
                ?>
                <span class="yrwp-value"><?php echo esc_html((string)$symbol_12); ?></span>
            </div>
        </section>

        <!-- Instant details (ALL KEYS) -->
        <section class="yrwp-current-full__instant">
            <?php if (!empty($instant_details) && is_array($instant_details)) : ?>

                <?php
                $render_field = static function ($key, $instant_details) {
                    if (!isset($instant_details[$key])) return;

                    echo '<div class="yrwp-field yrwp-field-' . esc_attr(sanitize_key($key)) . '" data-field="' . esc_attr($key) . '">';
                    echo '<dt class="yrwp-field__label">' . esc_html(\YRWP\I18N::field_label($key)) . '</dt>';
                    echo '<dd class="yrwp-field__value">' . esc_html(\YRWP\I18N::format_value($key, $instant_details[$key])) . '</dd>';
                    echo '</div>';
                };
                ?>

                <dl class="yrwp-fields yrwp-fields--instant">

                    <?php $render_field('air_temperature', $instant_details); ?>

                    <?php $render_field('wind_speed', $instant_details); ?>

                    <?php $render_field('wind_from_direction', $instant_details); ?>

                    <?php $render_field('relative_humidity', $instant_details); ?>

                    <?php $render_field('air_pressure_at_sea_level', $instant_details); ?>

                    <?php $render_field('cloud_area_fraction', $instant_details); ?>

                    <?php $render_field('precipitation_amount', $instant_details); ?>

                </dl>

            <?php else : ?>
                <div class="yrwp-empty">No instant.details.</div>
            <?php endif; ?>
        </section>

        <!-- Debug dump (optional) -->
        <?php if (!empty($meta['debug'])) : ?>
            <details class="yrwp-debug">
                <summary>Debug</summary>
                <pre><?php echo esc_html(wp_json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
            </details>
        <?php endif; ?>

    </div>

</div>
