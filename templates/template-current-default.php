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

wp_enqueue_style('yrwp-template-current-default', YRWP_URL . 'assets/style/templates/template-current-default.css', ['yrwp-frontend'], YRWP_VERSION);

$row = $timeseries[0] ?? null;
if (!$row) {
    echo '<div class="yrwp-body yrwp-error">' . esc_html(\YRWP\I18N::t('no_data')) . '</div>';
    return;
}

$instant_details = $row['data']['instant']['details'] ?? [];

$next_1  = $row['data']['next_1_hours']  ?? [];
$symbol_1  = $next_1['summary']['symbol_code']  ?? null;
$details_1  = $next_1['details']  ?? [];

if (!isset($instant_details['precipitation_amount']) && isset($details_1['precipitation_amount'])) {
    $instant_details['precipitation_amount'] = $details_1['precipitation_amount'];
}

$time_iso = $row['time'] ?? '';

$detail_field_config = [
    // 'icon' => false hides the icon completely for the field.
    // 'icon' => '' falls back to the text label.
    'air_temperature' => ['icon' => false],
    'wind_speed' => ['icon' => 'wind_speed.svg'],
    'wind_from_direction' => ['icon' => 'wind_direction.svg'],
    'relative_humidity' => ['icon' => 'humidity.svg'],
    'air_pressure_at_sea_level' => ['icon' => 'pressure.svg'],
    'cloud_area_fraction' => ['icon' => 'precipitation_amount.svg'],
    'precipitation_amount' => ['icon' => 'precipitation.svg'],
];
?>

<div class="yrwp-body yrwp-template-current-default yrwp-template-current-full">

    <div class="yrwp-current-full" data-yrwp-period="<?php echo esc_attr($period ?? 'current'); ?>">

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
            </div>

            <?php if (isset($instant_details['air_temperature'])) : ?>
                <div class="yrwp-current-full__temperature" data-field="air_temperature">
                    <span class="yrwp-current-full__temperature-value">
                        <?php echo esc_html(\YRWP\I18N::format_value('air_temperature', $instant_details['air_temperature'])); ?>
                    </span>
                </div>
            <?php endif; ?>
        </section>

        <!-- Instant details (ALL KEYS) -->
        <section class="yrwp-current-full__instant">
            <?php if (!empty($instant_details) && is_array($instant_details)) : ?>

                <?php
                $render_field = static function ($key, $instant_details, $detail_field_config) {
                    if (!isset($instant_details[$key])) return;

                    $label = \YRWP\I18N::field_label($key);
                    $field_config = $detail_field_config[$key] ?? [];
                    $icon_file = $field_config['icon'] ?? '';
                    $hide_icon = $icon_file === false;
                    $show_text_label = !$hide_icon && $icon_file === '';
                    $icon_url = is_string($icon_file) && $icon_file !== ''
                        ? \YRWP\Icons::get_detail_icon_url($icon_file)
                        : '';

                    echo '<div class="yrwp-field yrwp-field-' . esc_attr(sanitize_key($key)) . '" data-field="' . esc_attr($key) . '">';
                    if ($icon_url !== '') {
                        echo '<dt class="yrwp-field__label yrwp-field__label--icon" title="' . esc_attr($label) . '" aria-label="' . esc_attr($label) . '">';
                        echo '<img class="yrwp-field__icon" src="' . esc_url($icon_url) . '" alt="' . esc_attr($label) . '">';
                        echo '</dt>';
                    } elseif ($show_text_label) {
                        echo '<dt class="yrwp-field__label">' . esc_html($label) . '</dt>';
                    }
                    echo '<dd class="yrwp-field__value">' . esc_html(\YRWP\I18N::format_value($key, $instant_details[$key])) . '</dd>';
                    echo '</div>';
                };
                ?>

                <dl class="yrwp-fields yrwp-fields--instant">

                    <?php $render_field('wind_speed', $instant_details, $detail_field_config); ?>

                    <?php $render_field('wind_from_direction', $instant_details, $detail_field_config); ?>

                    <?php $render_field('relative_humidity', $instant_details, $detail_field_config); ?>

                    <?php $render_field('air_pressure_at_sea_level', $instant_details, $detail_field_config); ?>

                    <?php $render_field('cloud_area_fraction', $instant_details, $detail_field_config); ?>

                    <?php $render_field('precipitation_amount', $instant_details, $detail_field_config); ?>

                </dl>

            <?php else : ?>
                <div class="yrwp-empty"><?php echo esc_html(\YRWP\I18N::t('no_instant_details')); ?></div>
            <?php endif; ?>
        </section>

        <!-- Debug dump (optional) -->
        <?php if (!empty($meta['debug'])) : ?>
            <details class="yrwp-debug">
                <summary><?php echo esc_html(\YRWP\I18N::t('debug')); ?></summary>
                <pre><?php echo esc_html(wp_json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
            </details>
        <?php endif; ?>

    </div>

</div>
