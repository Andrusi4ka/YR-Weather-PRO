<?php
if (!defined('ABSPATH')) exit;
/**
 * Template: 9 days compact
 * $meta, $period, $timeseries, $days, $updated_at
 */

wp_enqueue_style('yrwp-template-9-compact', YRWP_URL . 'assets/style/templates/template-9-compact.css', ['yrwp-frontend'], YRWP_VERSION);

$forecast_days = array_slice($days, 0, 9, true);
if (!$forecast_days) {
    echo '<div class="yrwp-body yrwp-error">' . esc_html(\YRWP\I18N::t('no_forecast_data')) . '</div>';
    return;
}

$build_day_card = static function (string $date, array $rows): array {
    $timestamp = strtotime($date . ' 00:00:00');
    $temperatures = [];
    $symbol_code = '';

    foreach ($rows as $row) {
        $temp = $row['data']['instant']['details']['air_temperature'] ?? null;
        if (is_numeric($temp)) {
            $temperatures[] = $temp;
        }

        if ($symbol_code === '') {
            $symbol_code = \YRWP\Shortcode::get_row_symbol_code($row);
        }
    }

    if (!$temperatures) {
        return [];
    }

    return [
        'weekday' => $timestamp ? \YRWP\I18N::weekday_name($timestamp) : $date,
        'date' => $timestamp ? \YRWP\I18N::format_date($timestamp) : $date,
        'min' => min($temperatures),
        'max' => max($temperatures),
        'icon' => $symbol_code ? \YRWP\Shortcode::get_weather_icon_svg($symbol_code) : '',
        'text' => $symbol_code ? \YRWP\I18N::symbol_description($symbol_code) : '',
    ];
};
?>

<div class="yrwp-body yrwp-template-9-compact">
    <section class="yrwp-forecast-compact__timeline yrwp-forecast-compact__timeline--9">
        <?php foreach ($forecast_days as $date => $rows) :
            $card = $build_day_card((string) $date, $rows);
            if (!$card) continue;
        ?>
            <article class="yrwp-forecast-compact__card">
                <div class="yrwp-forecast-compact__card-top">
                    <div>
                        <p class="yrwp-forecast-compact__day"><?php echo esc_html($card['weekday']); ?></p>
                        <?php if ($card['text'] !== '') : ?>
                            <p class="yrwp-forecast-compact__weather-text"><?php echo esc_html($card['text']); ?></p>
                        <?php endif; ?>
                    </div>
                    <time class="yrwp-forecast-compact__date"><?php echo esc_html($card['date']); ?></time>
                </div>

                <div class="yrwp-forecast-compact__hero-row">
                    <?php if ($card['icon'] !== '') : ?>
                        <div class="yrwp-forecast-compact__icon"><?php echo $card['icon']; ?></div>
                    <?php endif; ?>

                    <div class="yrwp-forecast-compact__temperature">
                        <?php echo esc_html($card['min']); ?>&deg; / <?php echo esc_html($card['max']); ?>&deg;
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</div>
