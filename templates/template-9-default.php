<?php
if (!defined('ABSPATH')) exit;
/**
 * Template: 9 days default
 * $meta, $period, $timeseries, $days, $updated_at
 */

wp_enqueue_style('yrwp-template-9-default', YRWP_URL . 'assets/style/templates/template-9-default.css', ['yrwp-frontend'], YRWP_VERSION);

$forecast_days = array_slice($days, 0, 9, true);
if (!$forecast_days) {
    echo '<div class="yrwp-body yrwp-error">' . esc_html(\YRWP\I18N::t('no_forecast_data')) . '</div>';
    return;
}

$clock_format = \YRWP\Shortcode::get_site_time_format();
$forecast_dates = array_keys($forecast_days);
$range_start = $forecast_dates ? strtotime((string) $forecast_dates[0] . ' 00:00:00') : false;
$range_end = $forecast_dates ? strtotime((string) end($forecast_dates) . ' 00:00:00') : false;
$range_label = $range_start && $range_end
    ? \YRWP\I18N::format_date_range($range_start, $range_end)
    : \YRWP\I18N::t('forecast_9');

$detail_fields = [
    'air_temperature' => ['icon' => 'temperature.svg'],
    'wind_speed' => ['icon' => 'wind_speed.svg'],
    'wind_from_direction' => ['icon' => 'wind_direction.svg'],
    'relative_humidity' => ['icon' => 'humidity.svg'],
    'air_pressure_at_sea_level' => ['icon' => 'pressure.svg'],
    'cloud_area_fraction' => ['icon' => 'precipitation_amount.svg'],
    'precipitation_amount' => ['icon' => 'precipitation.svg'],
];

$build_day_card = static function (string $date, array $rows): array {
    $timestamp = strtotime($date . ' 00:00:00');
    $aggregate_values = [];
    $symbol_code = '';

    foreach ($rows as $row) {
        $details = $row['data']['instant']['details'] ?? [];
        foreach ($details as $key => $value) {
            if (!is_numeric($value)) {
                continue;
            }

            $aggregate_values[$key][] = $value;
        }

        $precipitation_amount = $row['data']['next_1_hours']['details']['precipitation_amount']
            ?? $row['data']['next_6_hours']['details']['precipitation_amount']
            ?? null;

        if ($precipitation_amount !== null && is_numeric($precipitation_amount)) {
            $aggregate_values['precipitation_amount'][] = $precipitation_amount;
        }

        if ($symbol_code === '') {
            $symbol_code = \YRWP\Shortcode::get_row_symbol_code($row);
        }
    }

    if (empty($aggregate_values['air_temperature'])) {
        return [];
    }

    $summary = [];
    foreach ($aggregate_values as $key => $values) {
        if ($key === 'air_temperature') {
            $summary[$key] = [
                'min' => min($values),
                'max' => max($values),
            ];
            continue;
        }

        $summary[$key] = round(array_sum($values) / count($values), 1);
    }

    return [
        'weekday' => $timestamp ? \YRWP\I18N::weekday_name($timestamp) : $date,
        'date' => $timestamp ? \YRWP\I18N::format_date($timestamp) : $date,
        'summary' => $summary,
        'icon' => $symbol_code ? \YRWP\Shortcode::get_weather_icon_svg($symbol_code) : '',
        'text' => $symbol_code ? \YRWP\I18N::symbol_description($symbol_code) : '',
    ];
};

$forecast_cards = [];
$overview_pool = [];
foreach ($forecast_days as $date => $rows) {
    $card = $build_day_card((string) $date, $rows);
    if (!$card) {
        continue;
    }

    $forecast_cards[] = $card;

    foreach ($card['summary'] as $summary_key => $summary_value) {
        if ($summary_key === 'air_temperature' && is_array($summary_value)) {
            $overview_pool['air_temperature_min'][] = $summary_value['min'];
            $overview_pool['air_temperature_max'][] = $summary_value['max'];
            continue;
        }

        if (is_numeric($summary_value)) {
            $overview_pool[$summary_key][] = $summary_value;
        }
    }
}

if (!$forecast_cards) {
    echo '<div class="yrwp-body yrwp-error">' . esc_html(\YRWP\I18N::t('no_forecast_data')) . '</div>';
    return;
}

$forecast_overview = [];
if (!empty($overview_pool['air_temperature_min']) && !empty($overview_pool['air_temperature_max'])) {
    $forecast_overview['air_temperature'] = [
        'min' => min($overview_pool['air_temperature_min']),
        'max' => max($overview_pool['air_temperature_max']),
    ];
}

foreach (['wind_speed', 'relative_humidity', 'cloud_area_fraction', 'precipitation_amount'] as $overview_key) {
    if (!empty($overview_pool[$overview_key])) {
        $forecast_overview[$overview_key] = round(array_sum($overview_pool[$overview_key]) / count($overview_pool[$overview_key]), 1);
    }
}

$render_detail = static function (string $key, $value, array $config): void {
    if ($value === null || $value === '') {
        return;
    }

    $label = \YRWP\I18N::field_label($key);
    $icon_file = $config['icon'] ?? '';
    $icon_url = is_string($icon_file) && $icon_file !== ''
        ? \YRWP\Icons::get_detail_icon_url($icon_file)
        : '';

    echo '<div class="yrwp-forecast-default__detail yrwp-forecast-default__detail--' . esc_attr(sanitize_key($key)) . '">';
    if ($icon_url !== '') {
        echo '<span class="yrwp-forecast-default__detail-label yrwp-forecast-default__detail-label--icon" title="' . esc_attr($label) . '">';
        echo '<img src="' . esc_url($icon_url) . '" alt="' . esc_attr($label) . '">';
        echo '</span>';
    } else {
        echo '<span class="yrwp-forecast-default__detail-label">' . esc_html($label) . '</span>';
    }

    echo '<span class="yrwp-forecast-default__detail-value">' . esc_html(\YRWP\I18N::format_value($key, $value)) . '</span>';
    echo '</div>';
};
?>

<div class="yrwp-body yrwp-template-9-default">
    <div class="yrwp-forecast-default">
        <section class="yrwp-forecast-default__hero">
            <div class="yrwp-forecast-default__hero-copy">
                <p class="yrwp-forecast-default__eyebrow"><?php echo esc_html(\YRWP\I18N::t('days_9')); ?></p>
                <h3 class="yrwp-forecast-default__title"><?php echo esc_html($range_label); ?></h3>

                <?php if (!empty($updated_at)) : ?>
                    <p class="yrwp-forecast-default__updated">
                        <?php echo esc_html(\YRWP\I18N::t('updated', ['time' => wp_date($clock_format, (int) $updated_at)])); ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="yrwp-forecast-default__overview">
                <?php if (!empty($forecast_overview['air_temperature']) && is_array($forecast_overview['air_temperature'])) : ?>
                    <div class="yrwp-forecast-default__overview-item">
                        <span class="yrwp-forecast-default__overview-label"><?php echo esc_html(\YRWP\I18N::t('temperature')); ?></span>
                        <span class="yrwp-forecast-default__overview-value">
                            <?php echo esc_html($forecast_overview['air_temperature']['min']); ?>&deg;C / <?php echo esc_html($forecast_overview['air_temperature']['max']); ?>&deg;C
                        </span>
                    </div>
                <?php endif; ?>

                <?php if (isset($forecast_overview['wind_speed'])) : ?>
                    <div class="yrwp-forecast-default__overview-item">
                        <span class="yrwp-forecast-default__overview-label"><?php echo esc_html(\YRWP\I18N::t('wind')); ?></span>
                        <span class="yrwp-forecast-default__overview-value"><?php echo esc_html(\YRWP\I18N::format_value('wind_speed', $forecast_overview['wind_speed'])); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (isset($forecast_overview['relative_humidity'])) : ?>
                    <div class="yrwp-forecast-default__overview-item">
                        <span class="yrwp-forecast-default__overview-label"><?php echo esc_html(\YRWP\I18N::t('humidity')); ?></span>
                        <span class="yrwp-forecast-default__overview-value"><?php echo esc_html(\YRWP\I18N::format_value('relative_humidity', $forecast_overview['relative_humidity'])); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (isset($forecast_overview['cloud_area_fraction'])) : ?>
                    <div class="yrwp-forecast-default__overview-item">
                        <span class="yrwp-forecast-default__overview-label"><?php echo esc_html(\YRWP\I18N::t('clouds')); ?></span>
                        <span class="yrwp-forecast-default__overview-value"><?php echo esc_html(\YRWP\I18N::format_value('cloud_area_fraction', $forecast_overview['cloud_area_fraction'])); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="yrwp-forecast-default__timeline yrwp-forecast-default__timeline--9">
            <?php foreach ($forecast_cards as $card) : ?>
                <article class="yrwp-forecast-default__card">
                    <div class="yrwp-forecast-default__card-top">
                        <div>
                            <p class="yrwp-forecast-default__day"><?php echo esc_html($card['weekday']); ?></p>
                            <?php if ($card['text'] !== '') : ?>
                                <p class="yrwp-forecast-default__weather-text"><?php echo esc_html($card['text']); ?></p>
                            <?php endif; ?>
                        </div>
                        <time class="yrwp-forecast-default__date"><?php echo esc_html($card['date']); ?></time>
                    </div>

                    <div class="yrwp-forecast-default__hero-row">
                        <?php if ($card['icon'] !== '') : ?>
                            <div class="yrwp-forecast-default__icon"><?php echo $card['icon']; ?></div>
                        <?php endif; ?>

                        <?php if (!empty($card['summary']['air_temperature']) && is_array($card['summary']['air_temperature'])) : ?>
                            <div class="yrwp-forecast-default__temperature">
                                <?php echo esc_html($card['summary']['air_temperature']['min']); ?>&deg;<br><?php echo esc_html($card['summary']['air_temperature']['max']); ?>&deg;
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="yrwp-forecast-default__details">
                        <?php foreach ($card['summary'] as $detail_key => $detail_value) : ?>
                            <?php
                            if ($detail_key === 'air_temperature') {
                                continue;
                            }

                            $render_detail($detail_key, $detail_value, $detail_fields[$detail_key] ?? []);
                            ?>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </div>
</div>
