<?php
if (!defined('ABSPATH')) exit;
/**
 * Template: current compact
 *
 * Available variables (from Shortcode::load_template):
 *  - $meta (array)
 *  - $period (string)
 *  - $timeseries (array)
 *  - $days (array)
 *  - $updated_at (?int)
 *  - $template (?string)
 */

wp_enqueue_style('yrwp-template-current-compact', YRWP_URL . 'assets/style/templates/template-current-compact.css', ['yrwp-frontend'], YRWP_VERSION);

$row = $timeseries[0] ?? null;
if (!$row) {
    echo '<div class="yrwp-body yrwp-error">' . esc_html(\YRWP\I18N::t('no_data')) . '</div>';
    return;
}

$instant_details = $row['data']['instant']['details'] ?? [];
$next_1 = $row['data']['next_1_hours'] ?? [];
$symbol_1 = $next_1['summary']['symbol_code'] ?? null;
?>

<div class="yrwp-body yrwp-template-current-compact yrwp-template-current-full">
    <div class="yrwp-current-full" data-yrwp-period="<?php echo esc_attr($period ?? 'current'); ?>">
        <div class="main-compact">
            <section class="yrwp-current-full__symbols">
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
            </section>

            <div class="main-left-info">
                <div class="yrwp-custom-text-short"><span><?php echo esc_html(\YRWP\I18N::t('today')); ?></span></div>
                <section class="yrwp-current-full__instant">
                    <?php if (!empty($instant_details) && is_array($instant_details)) : ?>
                        <?php
                        $render_field = static function ($key, $instant_details) {
                            if (!isset($instant_details[$key])) return;
                            echo '<div class="yrwp-field yrwp-field-' . esc_attr(sanitize_key($key)) . '" data-field="' . esc_attr($key) . '">';
                            if ($key === 'air_temperature') {
                                echo '<dd class="yrwp-field__value">' . esc_html((string) $instant_details[$key]) . '&deg;</dd>';
                            } else {
                                echo '<dd class="yrwp-field__value">' . esc_html(\YRWP\I18N::format_value($key, $instant_details[$key])) . '</dd>';
                            }
                            echo '</div>';
                        };
                        ?>
                        <dl class="yrwp-fields yrwp-fields--instant">
                            <?php $render_field('air_temperature', $instant_details); ?>
                        </dl>
                    <?php else : ?>
                        <div class="yrwp-empty"><?php echo esc_html(\YRWP\I18N::t('no_instant_details')); ?></div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>
</div>
