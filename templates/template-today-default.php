<?php
if (!defined('ABSPATH')) exit;
/**
 * Template: today default
 * $meta, $period, $timeseries, $days, $updated_at
 */

wp_enqueue_style('yrwp-template-today-default', YRWP_URL . 'assets/style/templates/template-today-default.css', ['yrwp-frontend'], YRWP_VERSION);

$today_date = array_key_first($days);
$today_rows = $today_date ? ($days[$today_date] ?? []) : [];

if (!$today_rows) {
    echo '<div class="yrwp-body yrwp-error">' . esc_html(\YRWP\I18N::t('no_data_today')) . '</div>';
    return;
}

$clock_format = \YRWP\Shortcode::get_site_time_format();
$day_timestamp = $today_date ? strtotime($today_date . ' 00:00:00') : false;
$today_label = $day_timestamp ? \YRWP\I18N::format_date($day_timestamp) : \YRWP\I18N::t('today');
$weekday_label = $day_timestamp ? \YRWP\I18N::weekday_name($day_timestamp) : '';

$aggregate_values = [];
foreach ($today_rows as $aggregate_row) {
    $aggregate_details = $aggregate_row['data']['instant']['details'] ?? [];
    foreach ($aggregate_details as $aggregate_key => $aggregate_value) {
        if (!is_numeric($aggregate_value)) {
            continue;
        }

        $aggregate_values[$aggregate_key][] = $aggregate_value;
    }
}

$day_aggregate = [];
foreach ($aggregate_values as $aggregate_key => $aggregate_bucket) {
    if ($aggregate_key === 'air_temperature') {
        $day_aggregate[$aggregate_key] = [
            'min' => min($aggregate_bucket),
            'max' => max($aggregate_bucket),
        ];
        continue;
    }

    $day_aggregate[$aggregate_key] = round(array_sum($aggregate_bucket) / count($aggregate_bucket), 1);
}

$detail_fields = [
    'air_temperature' => ['icon' => 'temperature.svg'],
    'wind_speed' => ['icon' => 'wind_speed.svg'],
    'wind_from_direction' => ['icon' => 'wind_direction.svg'],
    'relative_humidity' => ['icon' => 'humidity.svg'],
    'air_pressure_at_sea_level' => ['icon' => 'pressure.svg'],
    'cloud_area_fraction' => ['icon' => 'precipitation_amount.svg'],
    'precipitation_amount' => ['icon' => 'precipitation.svg'],
];

$render_detail = static function (string $key, $value, array $config): void {
    if ($value === null || $value === '') {
        return;
    }

    $label = \YRWP\I18N::field_label($key);
    $icon_file = $config['icon'] ?? '';
    $icon_url = is_string($icon_file) && $icon_file !== ''
        ? \YRWP\Icons::get_detail_icon_url($icon_file)
        : '';

    echo '<div class="yrwp-today-default__detail yrwp-today-default__detail--' . esc_attr(sanitize_key($key)) . '">';
    if ($icon_url !== '') {
        echo '<span class="yrwp-today-default__detail-label yrwp-today-default__detail-label--icon" title="' . esc_attr($label) . '">';
        echo '<img src="' . esc_url($icon_url) . '" alt="' . esc_attr($label) . '">';
        echo '</span>';
    } else {
        echo '<span class="yrwp-today-default__detail-label">' . esc_html($label) . '</span>';
    }

    echo '<span class="yrwp-today-default__detail-value">' . esc_html(\YRWP\I18N::format_value($key, $value)) . '</span>';
    echo '</div>';
};
?>

<div class="yrwp-body yrwp-template-today-default">
    <div class="yrwp-today-default">
        <section class="yrwp-today-default__hero">
            <div class="yrwp-today-default__hero-copy">
                <?php if ($weekday_label !== '') : ?>
                    <p class="yrwp-today-default__eyebrow"><?php echo esc_html($weekday_label); ?></p>
                <?php endif; ?>

                <h3 class="yrwp-today-default__title"><?php echo esc_html($today_label); ?></h3>

                <?php if (!empty($updated_at)) : ?>
                    <p class="yrwp-today-default__updated">
                        <?php echo esc_html(\YRWP\I18N::t('updated', ['time' => wp_date($clock_format, (int) $updated_at)])); ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="yrwp-today-default__overview">
                <?php if (!empty($day_aggregate['air_temperature']) && is_array($day_aggregate['air_temperature'])) : ?>
                    <div class="yrwp-today-default__overview-item">
                        <span class="yrwp-today-default__overview-label"><?php echo esc_html(\YRWP\I18N::t('temperature')); ?></span>
                        <span class="yrwp-today-default__overview-value">
                            <?php echo esc_html($day_aggregate['air_temperature']['min']); ?>°C / <?php echo esc_html($day_aggregate['air_temperature']['max']); ?>°C
                        </span>
                    </div>
                <?php endif; ?>

                <?php if (isset($day_aggregate['wind_speed'])) : ?>
                    <div class="yrwp-today-default__overview-item">
                        <span class="yrwp-today-default__overview-label"><?php echo esc_html(\YRWP\I18N::t('wind')); ?></span>
                        <span class="yrwp-today-default__overview-value"><?php echo esc_html(\YRWP\I18N::format_value('wind_speed', $day_aggregate['wind_speed'])); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (isset($day_aggregate['relative_humidity'])) : ?>
                    <div class="yrwp-today-default__overview-item">
                        <span class="yrwp-today-default__overview-label"><?php echo esc_html(\YRWP\I18N::t('humidity')); ?></span>
                        <span class="yrwp-today-default__overview-value"><?php echo esc_html(\YRWP\I18N::format_value('relative_humidity', $day_aggregate['relative_humidity'])); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (isset($day_aggregate['cloud_area_fraction'])) : ?>
                    <div class="yrwp-today-default__overview-item">
                        <span class="yrwp-today-default__overview-label"><?php echo esc_html(\YRWP\I18N::t('clouds')); ?></span>
                        <span class="yrwp-today-default__overview-value"><?php echo esc_html(\YRWP\I18N::format_value('cloud_area_fraction', $day_aggregate['cloud_area_fraction'])); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="yrwp-today-default__timeline">
            <?php foreach ($today_rows as $row) :
                $time_iso = (string) ($row['time'] ?? '');
                $timestamp = $time_iso ? strtotime($time_iso) : false;
                $time_label = $timestamp ? wp_date($clock_format, $timestamp) : '';
                $instant = $row['data']['instant']['details'] ?? [];
                $next_1 = $row['data']['next_1_hours'] ?? [];
                $symbol_code = (string) ($next_1['summary']['symbol_code'] ?? '');
                $weather_text = \YRWP\I18N::symbol_description($symbol_code);
                $weather_icon = $symbol_code ? \YRWP\Shortcode::get_weather_icon_svg($symbol_code) : '';
                $precipitation_amount = $next_1['details']['precipitation_amount'] ?? ($row['data']['next_6_hours']['details']['precipitation_amount'] ?? null);
            ?>
                <article class="yrwp-today-default__card">
                    <div class="yrwp-today-default__card-top">
                        <time class="yrwp-today-default__time"><?php echo esc_html($time_label); ?></time>
                        <?php if ($weather_text !== '') : ?>
                            <p class="yrwp-today-default__weather-text"><?php echo esc_html($weather_text); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="yrwp-today-default__hero-row">
                        <?php if ($weather_icon !== '') : ?>
                            <div class="yrwp-today-default__icon"><?php echo $weather_icon; ?></div>
                        <?php endif; ?>

                        <?php if (isset($instant['air_temperature'])) : ?>
                            <div class="yrwp-today-default__temperature">
                                <?php echo esc_html(\YRWP\I18N::format_value('air_temperature', $instant['air_temperature'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="yrwp-today-default__details">
                        <?php foreach ($instant as $detail_key => $detail_value) : ?>
                            <?php $render_detail($detail_key, $detail_value, $detail_fields[$detail_key] ?? []); ?>
                        <?php endforeach; ?>

                        <?php if (!array_key_exists('precipitation_amount', $instant)) : ?>
                            <?php $render_detail('precipitation_amount', $precipitation_amount, $detail_fields['precipitation_amount']); ?>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </div>
</div>
