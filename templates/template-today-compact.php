<?php
if (!defined('ABSPATH')) exit;
/**
 * Template: today compact
 * $meta, $period, $timeseries, $days, $updated_at
 */

wp_enqueue_style('yrwp-template-today-compact', YRWP_URL . 'assets/style/templates/template-today-compact.css', ['yrwp-frontend'], YRWP_VERSION);

$today_date = array_key_first($days);
$today_rows = $today_date ? ($days[$today_date] ?? []) : [];

if (!$today_rows) {
    echo '<div class="yrwp-body yrwp-error">' . esc_html(\YRWP\I18N::t('no_data_today')) . '</div>';
    return;
}

$day_timestamp = $today_date ? strtotime($today_date . ' 00:00:00') : false;
$weekday_label = $day_timestamp ? \YRWP\I18N::format_short_date($day_timestamp) : \YRWP\I18N::t('today');

$temperatures = [];
$symbol_code = '';

foreach ($today_rows as $row) {
    $temp = $row['data']['instant']['details']['air_temperature'] ?? null;
    if (is_numeric($temp)) {
        $temperatures[] = $temp;
    }

    if ($symbol_code === '') {
        $symbol_code = (string) ($row['data']['next_1_hours']['summary']['symbol_code'] ?? '');
    }
}

if (!$temperatures) {
    echo '<div class="yrwp-body yrwp-error">' . esc_html(\YRWP\I18N::t('no_temperature_today')) . '</div>';
    return;
}

$min_temp = min($temperatures);
$max_temp = max($temperatures);
$weather_icon = $symbol_code ? \YRWP\Shortcode::get_weather_icon_svg($symbol_code) : '';
$weather_text = $symbol_code ? \YRWP\I18N::symbol_description($symbol_code) : '';
?>

<div class="yrwp-body yrwp-template-today-compact">
    <div class="yrwp-today-compact">
        <?php if ($weather_icon !== '') : ?>
            <div class="yrwp-today-compact__icon"><?php echo $weather_icon; ?></div>
        <?php endif; ?>

        <div class="yrwp-today-compact__content">
            <div><span class="yrwp-today-compact__weekday"><?php echo esc_html(\YRWP\I18N::t('today')); ?></span></div>

            <div class="yrwp-today-compact__temps">
                <span class="yrwp-today-compact__temp yrwp-today-compact__temp--max"><?php echo esc_html((string) $max_temp); ?>&deg;</span>
            </div>

            <div class="yrwp-today-compact__range">
                <span class="yrwp-today-compact__range-value">
                <?php echo esc_html(\YRWP\I18N::format_value('air_temperature', $min_temp)); ?>
                /
                <?php echo esc_html(\YRWP\I18N::format_value('air_temperature', $max_temp)); ?>
                </span>
            </div>
        </div>
    </div>
</div>
