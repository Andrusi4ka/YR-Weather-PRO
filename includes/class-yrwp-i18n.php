<?php

namespace YRWP;

if (!defined('ABSPATH')) exit;

class I18N
{
    private const DEFAULT_LANGUAGE = 'en';

    public static function get_supported_languages(): array
    {
        return [
            'en' => 'English',
            'nb' => 'Norsk Bokmal',
            'uk' => 'Українська',
        ];
    }

    public static function sanitize_language(?string $language): string
    {
        $language = sanitize_key((string) $language);
        return array_key_exists($language, self::get_supported_languages()) ? $language : self::DEFAULT_LANGUAGE;
    }

    public static function get_language(): string
    {
        $options = get_option(Admin::OPT_KEY, []);
        return self::sanitize_language($options['language'] ?? self::DEFAULT_LANGUAGE);
    }

    public static function info_content(): array
    {
        $language = self::get_language();
        $content = self::get_info_content_map();

        return $content[$language] ?? $content[self::DEFAULT_LANGUAGE];
    }

    public static function t(string $key, array $replacements = []): string
    {
        $language = self::get_language();
        $translations = self::get_translations();
        $text = $translations[$language][$key] ?? $translations[self::DEFAULT_LANGUAGE][$key] ?? $key;

        foreach ($replacements as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }

        return $text;
    }

    public static function field_label(string $key): string
    {
        $labels = [
            'en' => [
                'air_temperature' => 'Temperature',
                'wind_speed' => 'Wind speed',
                'wind_from_direction' => 'Wind direction',
                'relative_humidity' => 'Humidity',
                'air_pressure_at_sea_level' => 'Pressure',
                'cloud_area_fraction' => 'Cloud cover',
                'precipitation_amount' => 'Precipitation',
                'symbol_code' => 'Weather conditions',
            ],
            'nb' => [
                'air_temperature' => 'Temperatur',
                'wind_speed' => 'Vindstyrke',
                'wind_from_direction' => 'Vindretning',
                'relative_humidity' => 'Luftfuktighet',
                'air_pressure_at_sea_level' => 'Lufttrykk',
                'cloud_area_fraction' => 'Skydekke',
                'precipitation_amount' => 'Nedbor',
                'symbol_code' => 'Værforhold',
            ],
            'uk' => [
                'air_temperature' => 'Температура',
                'wind_speed' => 'Швидкість вітру',
                'wind_from_direction' => 'Напрям вітру',
                'relative_humidity' => 'Вологість',
                'air_pressure_at_sea_level' => 'Тиск',
                'cloud_area_fraction' => 'Хмарність',
                'precipitation_amount' => 'Опади',
                'symbol_code' => 'Погодні умови',
            ],
        ];

        $language = self::get_language();
        return $labels[$language][$key] ?? $labels[self::DEFAULT_LANGUAGE][$key] ?? $key;
    }

    public static function symbol_description(?string $code): string
    {
        if (!$code) {
            return '';
        }

        $descriptions = self::get_symbol_descriptions();
        $language = self::get_language();

        if (isset($descriptions[$language][$code])) {
            return $descriptions[$language][$code];
        }

        if (isset($descriptions[self::DEFAULT_LANGUAGE][$code])) {
            return $descriptions[self::DEFAULT_LANGUAGE][$code];
        }

        return ucwords(str_replace(['_', '-'], ' ', $code));
    }

    public static function format_value(string $key, $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        switch ($key) {
            case 'air_temperature':
                return $value . ' °C';
            case 'wind_speed':
                return $value . ' m/s';
            case 'relative_humidity':
                return $value . ' %';
            case 'air_pressure_at_sea_level':
                return $value . ' hPa';
            case 'cloud_area_fraction':
                return $value . ' %';
            case 'precipitation_amount':
                return $value . ' mm';
            case 'wind_from_direction':
                return $value . ' °';
            default:
                return (string) $value;
        }
    }

    public static function format_date(int $timestamp): string
    {
        return wp_date((string) get_option('date_format', 'F j, Y'), $timestamp);
    }

    public static function format_short_date(int $timestamp): string
    {
        $day = (int) gmdate('j', $timestamp);
        $month = (int) gmdate('n', $timestamp);
        $month_name = self::short_month_name($month);
        $language = self::get_language();

        if ($language === 'en') {
            return $month_name . ' ' . $day;
        }

        return $day . ' ' . $month_name;
    }

    public static function weekday_name(int $timestamp): string
    {
        $index = (int) gmdate('w', $timestamp);
        $language = self::get_language();
        $days = [
            'en' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            'nb' => ['Sondag', 'Mandag', 'Tirsdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lordag'],
            'uk' => ['Неділя', 'Понеділок', 'Вівторок', 'Середа', 'Четвер', 'Пʼятниця', 'Субота'],
        ];

        return $days[$language][$index] ?? $days[self::DEFAULT_LANGUAGE][$index];
    }

    public static function weekday_short_name(int $timestamp): string
    {
        $index = (int) gmdate('w', $timestamp);
        $language = self::get_language();
        $days = [
            'en' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            'nb' => ['son', 'man', 'tir', 'ons', 'tor', 'fre', 'lor'],
            'uk' => ["\u{41d}\u{434}", "\u{41f}\u{43d}", "\u{412}\u{442}", "\u{421}\u{440}", "\u{427}\u{442}", "\u{41f}\u{442}", "\u{421}\u{431}"],
        ];

        return $days[$language][$index] ?? $days[self::DEFAULT_LANGUAGE][$index];
    }

    public static function format_date_range(int $start, int $end): string
    {
        return self::format_date($start) . ' - ' . self::format_date($end);
    }

    private static function month_name(int $month): string
    {
        $language = self::get_language();
        $months = [
            'en' => [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            'nb' => [1 => 'januar', 'februar', 'mars', 'april', 'mai', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'desember'],
            'uk' => [1 => 'січня', 'лютого', 'березня', 'квітня', 'травня', 'червня', 'липня', 'серпня', 'вересня', 'жовтня', 'листопада', 'грудня'],
        ];

        return $months[$language][$month] ?? $months[self::DEFAULT_LANGUAGE][$month] ?? '';
    }

    private static function short_month_name(int $month): string
    {
        $language = self::get_language();
        $months = [
            'en' => [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'nb' => [1 => 'jan', 'feb', 'mar', 'apr', 'mai', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'des'],
            'uk' => [1 => 'січ', 'лют', 'бер', 'кві', 'тра', 'чер', 'лип', 'сер', 'вер', 'жов', 'лис', 'гру'],
        ];

        return $months[$language][$month] ?? $months[self::DEFAULT_LANGUAGE][$month] ?? '';
    }

    private static function get_translations(): array
    {
        return [
            'en' => [
                'settings' => 'Settings',
                'language' => 'Language',
                'contact_email' => 'Contact email for User-Agent',
                'access_key' => 'Access key',
                'access_key_help' => 'Enter any 32-character key. Without it, the plugin is disabled.',
                'access_key_saved' => 'Access key saved. The plugin is enabled.',
                'access_key_required' => 'Access key required. Enter a 32-character key in plugin settings.',
                'saved_email' => 'Saved: {email} (you can replace it anytime)',
                'user_agent_help' => 'Required by MET Norway Terms: identify yourself in User-Agent.',
                'save' => 'Save',
                'info' => 'Info',
                'yr_weather_info' => 'YR Weather Info',
                'copy_paste' => 'Copy and paste into a page or post.',
                'shortcode' => 'Shortcode',
                'widget_settings' => 'Widget Settings',
                'weather_widget' => 'Weather Widget',
                'yr_weather' => 'YR Weather',
                'add_weather_widget' => 'Add Weather Widget',
                'edit_weather_widget' => 'Edit Weather Widget',
                'current_weather' => 'Current weather',
                'today' => 'Today',
                'days_7' => '7 days',
                'days_9' => '9 days',
                'updated' => 'Updated {time}',
                'loading_weather' => 'Loading weather...',
                'coordinates_not_set' => 'Coordinates are not set.',
                'weather_unavailable' => 'Weather unavailable.',
                'loading_weather' => 'Loading weather…',
                'geo_unsupported' => 'Geolocation is not supported.',
                'geo_denied' => 'Location permission denied.',
                'met_credit' => 'Weather data: MET Norway',
                'coordinates_not_set' => 'Coordinates are not set.',
                'invalid_api_response' => 'Invalid API response.',
                'no_data' => 'No data.',
                'no_data_today' => 'No data for today.',
                'no_temperature_today' => 'No temperature data for today.',
                'no_forecast_data' => 'No forecast data.',
                'no_instant_details' => 'No instant details.',
                'debug' => 'Debug',
                'temperature' => 'Temperature',
                'wind' => 'Wind',
                'humidity' => 'Humidity',
                'clouds' => 'Clouds',
                'forecast_7' => '7-day forecast',
                'forecast_9' => '9-day forecast',
                'forecast_period' => 'Forecast period',
                'template' => 'Template',
                'location_source' => 'Location source',
                'city' => 'City',
                'manual_coordinates' => 'Manual coordinates',
                'update_frequency' => 'Update frequency',
                'clock_format' => 'Clock format',
                'show_title' => 'Show title',
                'custom_css_class' => 'Custom CSS class',
                'custom_css' => 'Custom CSS',
                'manual_template' => 'Manual template',
                'measurement_units' => 'Measurement units',
                'hourly_block_template' => 'Hourly block template',
                'hour_step' => 'Hour step',
                'day_block_template' => 'Day block template',
                'show_today_day' => "Show today's day",
                'short_text' => 'Short text',
                'long_text' => 'Long text',
                'enabled' => 'Enabled',
                'show_on_site' => 'Show on site',
                'default' => 'Default',
                'compact' => 'Compact',
                'manual' => 'Manual',
                'json' => 'JSON',
                'manual_coords' => 'Manual coordinates',
                'city_search' => 'City search',
                'visitor_geolocation' => 'Visitor geolocation (browser)',
                'copy' => 'Copy',
                'version_label' => 'Version',
                'current_version_label' => 'Current version',
                'version_footer' => '{label}: {version} ® {year}',
            ],
            'nb' => [
                'settings' => 'Innstillinger',
                'language' => 'Sprak',
                'contact_email' => 'Kontakt e-post for User-Agent',
                'access_key' => 'Tilgangsnokkel',
                'access_key_help' => 'Skriv inn en valgfri nokkel pa 32 tegn. Uten den er pluginen deaktivert.',
                'access_key_saved' => 'Tilgangsnokkel lagret. Pluginen er aktiv.',
                'access_key_required' => 'Tilgangsnokkel kreves. Legg inn en nokkel pa 32 tegn i plugininnstillingene.',
                'saved_email' => 'Lagret: {email} (du kan endre den nar som helst)',
                'user_agent_help' => 'Kreves av vilkarene til MET Norway: identifiser deg i User-Agent.',
                'save' => 'Lagre',
                'info' => 'Info',
                'yr_weather_info' => 'YR Weather Info',
                'copy_paste' => 'Kopier og lim inn i en side eller et innlegg.',
                'shortcode' => 'Shortcode',
                'widget_settings' => 'Widget-innstillinger',
                'weather_widget' => 'Vaerwidget',
                'yr_weather' => 'YR Weather',
                'add_weather_widget' => 'Legg til vaerwidget',
                'edit_weather_widget' => 'Rediger vaerwidget',
                'current_weather' => 'Naavaerende vaer',
                'today' => 'I dag',
                'days_7' => '7 dager',
                'days_9' => '9 dager',
                'updated' => 'Oppdatert {time}',
                'loading_weather' => 'Laster vaer...',
                'coordinates_not_set' => 'Koordinater er ikke satt.',
                'weather_unavailable' => 'Vaerdata er utilgjengelig.',
                'loading_weather' => 'Laster værdata…',
                'geo_unsupported' => 'Posisjon støttes ikke.',
                'geo_denied' => 'Tilgang til posisjon ble avvist.',
                'met_credit' => 'Værdata: Meteorologisk institutt',
                'coordinates_not_set' => 'Koordinater er ikke angitt.',
                'invalid_api_response' => 'Ugyldig API-svar.',
                'no_data' => 'Ingen data.',
                'no_data_today' => 'Ingen data for i dag.',
                'no_temperature_today' => 'Ingen temperaturdata for i dag.',
                'no_forecast_data' => 'Ingen prognosedata.',
                'no_instant_details' => 'Ingen instant details.',
                'debug' => 'Debug',
                'temperature' => 'Temperatur',
                'wind' => 'Vind',
                'humidity' => 'Luftfuktighet',
                'clouds' => 'Skydekke',
                'forecast_7' => '7-dagersvarsel',
                'forecast_9' => '9-dagersvarsel',
                'forecast_period' => 'Varselperiode',
                'template' => 'Mal',
                'location_source' => 'Stedskilde',
                'city' => 'By',
                'manual_coordinates' => 'Manuelle koordinater',
                'update_frequency' => 'Oppdateringsfrekvens',
                'clock_format' => 'Tidsformat',
                'show_title' => 'Vis tittel',
                'custom_css_class' => 'Tilpasset CSS-klasse',
                'custom_css' => 'Tilpasset CSS',
                'manual_template' => 'Manuell mal',
                'measurement_units' => 'Malenheter',
                'hourly_block_template' => 'Timeblokk-mal',
                'hour_step' => 'Timesteg',
                'day_block_template' => 'Dagblokk-mal',
                'show_today_day' => 'Vis dagens dag',
                'short_text' => 'Kort tekst',
                'long_text' => 'Lang tekst',
                'enabled' => 'Aktivert',
                'show_on_site' => 'Vis pa nettstedet',
                'default' => 'Standard',
                'compact' => 'Kompakt',
                'manual' => 'Manuell',
                'json' => 'JSON',
                'manual_coords' => 'Manuelle koordinater',
                'city_search' => 'Bysok',
                'visitor_geolocation' => 'Besoksgeolokasjon (nettleser)',
                'copy' => 'Kopier',
                'version_label' => 'Versjon',
                'current_version_label' => 'Gjeldende versjon',
                'version_footer' => '{label}: {version} ® {year}',
            ],
            'uk' => [
                'settings' => 'Настройки',
                'language' => 'Мова',
                'contact_email' => 'Контактний email для User-Agent',
                'access_key' => 'Ключ доступу',
                'access_key_help' => 'Введіть будь-який ключ із 32 символів. Без нього плагін не працюватиме.',
                'access_key_saved' => 'Ключ доступу збережено. Плагін активний.',
                'access_key_required' => 'Потрібен ключ доступу. Введіть у налаштуваннях ключ із 32 символів.',
                'saved_email' => 'Збережено: {email} (можна змінити будь-коли)',
                'user_agent_help' => 'Вимагається правилами MET Norway: вкажіть себе в User-Agent.',
                'save' => 'Зберегти',
                'info' => 'Інфо',
                'yr_weather_info' => 'Інформація YR Weather',
                'copy_paste' => 'Скопіюйте та вставте у сторінку або запис.',
                'shortcode' => 'Шорткод',
                'widget_settings' => 'Налаштування віджета',
                'weather_widget' => 'Погодній віджет',
                'yr_weather' => 'YR Weather',
                'add_weather_widget' => 'Додати погодний віджет',
                'edit_weather_widget' => 'Редагувати погодний віджет',
                'current_weather' => 'Поточна погода',
                'today' => 'Сьогодні',
                'days_7' => '7 днів',
                'days_9' => '9 днів',
                'updated' => 'Оновлено {time}',
                'loading_weather' => 'Завантаження погоди...',
                'coordinates_not_set' => 'Координати не задані.',
                'weather_unavailable' => 'Дані про погоду недоступні.',
                'loading_weather' => 'Завантаження погоди…',
                'geo_unsupported' => 'Геолокація не підтримується.',
                'geo_denied' => 'Доступ до місцезнаходження заборонено.',
                'met_credit' => 'Дані про погоду: MET Norway',
                'coordinates_not_set' => 'Координати не задано.',
                'invalid_api_response' => 'Некоректна відповідь API.',
                'no_data' => 'Немає даних.',
                'no_data_today' => 'Немає даних на сьогодні.',
                'no_temperature_today' => 'Немає температурних даних на сьогодні.',
                'no_forecast_data' => 'Немає даних прогнозу.',
                'no_instant_details' => 'Немає instant details.',
                'debug' => 'Debug',
                'temperature' => 'Температура',
                'wind' => 'Вітер',
                'humidity' => 'Вологість',
                'clouds' => 'Хмарність',
                'forecast_7' => 'Прогноз на 7 днів',
                'forecast_9' => 'Прогноз на 9 днів',
                'forecast_period' => 'Період прогнозу',
                'template' => 'Шаблон',
                'location_source' => 'Джерело локації',
                'city' => 'Місто',
                'manual_coordinates' => 'Ручні координати',
                'update_frequency' => 'Частота оновлення',
                'clock_format' => 'Формат часу',
                'show_title' => 'Показувати заголовок',
                'custom_css_class' => 'Власний CSS клас',
                'custom_css' => 'Власний CSS',
                'manual_template' => 'Ручний шаблон',
                'measurement_units' => 'Одиниці виміру',
                'hourly_block_template' => 'Шаблон погодинного блоку',
                'hour_step' => 'Крок годин',
                'day_block_template' => 'Шаблон денного блоку',
                'show_today_day' => 'Показувати сьогоднішній день',
                'short_text' => 'Короткий текст',
                'long_text' => 'Довгий текст',
                'enabled' => 'Увімкнено',
                'show_on_site' => 'Показувати на сайті',
                'default' => 'Стандартний',
                'compact' => 'Компактний',
                'manual' => 'Ручний',
                'json' => 'JSON',
                'manual_coords' => 'Ручні координати',
                'city_search' => 'Пошук міста',
                'visitor_geolocation' => 'Геолокація відвідувача (браузер)',
                'copy' => 'Копіювати',
                'version_label' => 'Версія',
                'current_version_label' => 'Поточна версія',
                'version_footer' => '{label}: {version} ® {year}',
            ],
        ];
    }

    private static function get_symbol_descriptions(): array
    {
        return [
            'en' => [
                'clearsky_day' => 'Clear sky',
                'clearsky_night' => 'Clear sky at night',
                'clearsky_polartwilight' => 'Clear sky',
                'fair_day' => 'Fair',
                'fair_night' => 'Fair night',
                'fair_polartwilight' => 'Fair',
                'partlycloudy_day' => 'Partly cloudy',
                'partlycloudy_night' => 'Partly cloudy night',
                'partlycloudy_polartwilight' => 'Partly cloudy',
                'cloudy' => 'Cloudy',
                'fog' => 'Fog',
                'rain' => 'Rain',
                'lightrain' => 'Light rain',
                'heavyrain' => 'Heavy rain',
                'rainshowers_day' => 'Rain showers',
                'rainshowers_night' => 'Rain showers',
                'rainshowers_polartwilight' => 'Rain showers',
                'snow' => 'Snow',
                'lightsnow' => 'Light snow',
                'heavysnow' => 'Heavy snow',
                'sleet' => 'Sleet',
                'lightsleet' => 'Light sleet',
                'heavysleet' => 'Heavy sleet',
                'thunderstorm' => 'Thunderstorm',
                'rainandthunder' => 'Rain and thunder',
                'snowandthunder' => 'Snow and thunder',
            ],
            'nb' => [
                'clearsky_day' => 'Klar himmel',
                'clearsky_night' => 'Klar himmel om natten',
                'clearsky_polartwilight' => 'Klar himmel',
                'fair_day' => 'Lettskyet',
                'fair_night' => 'Lettskyet natt',
                'fair_polartwilight' => 'Lettskyet',
                'partlycloudy_day' => 'Delvis skyet',
                'partlycloudy_night' => 'Delvis skyet natt',
                'partlycloudy_polartwilight' => 'Delvis skyet',
                'cloudy' => 'Skyet',
                'fog' => 'Take',
                'rain' => 'Regn',
                'lightrain' => 'Lett regn',
                'heavyrain' => 'Kraftig regn',
                'rainshowers_day' => 'Regnbyger',
                'rainshowers_night' => 'Regnbyger',
                'rainshowers_polartwilight' => 'Regnbyger',
                'snow' => 'Sno',
                'lightsnow' => 'Lett sno',
                'heavysnow' => 'Kraftig sno',
                'sleet' => 'Sludd',
                'lightsleet' => 'Lett sludd',
                'heavysleet' => 'Kraftig sludd',
                'thunderstorm' => 'Tordenvaer',
                'rainandthunder' => 'Regn og torden',
                'snowandthunder' => 'Sno og torden',
            ],
            'uk' => [
                'clearsky_day' => 'Ясно',
                'clearsky_night' => 'Ясна ніч',
                'clearsky_polartwilight' => 'Ясно',
                'fair_day' => 'Мінлива хмарність',
                'fair_night' => 'Мінлива хмарність вночі',
                'fair_polartwilight' => 'Мінлива хмарність',
                'partlycloudy_day' => 'Малохмарно',
                'partlycloudy_night' => 'Малохмарно вночі',
                'partlycloudy_polartwilight' => 'Малохмарно',
                'cloudy' => 'Хмарно',
                'fog' => 'Туман',
                'rain' => 'Дощ',
                'lightrain' => 'Невеликий дощ',
                'heavyrain' => 'Сильний дощ',
                'rainshowers_day' => 'Дощові опади',
                'rainshowers_night' => 'Дощові опади',
                'rainshowers_polartwilight' => 'Дощові опади',
                'snow' => 'Сніг',
                'lightsnow' => 'Невеликий сніг',
                'heavysnow' => 'Сильний сніг',
                'sleet' => 'Мокрий сніг',
                'lightsleet' => 'Невеликий мокрий сніг',
                'heavysleet' => 'Сильний мокрий сніг',
                'thunderstorm' => 'Гроза',
                'rainandthunder' => 'Дощ з грозою',
                'snowandthunder' => 'Сніг з грозою',
            ],
        ];
    }

    private static function get_info_content_map(): array
    {
        return [
            'en' => [
                'header_title' => 'YR Weather Pro',
                'header_intro' => 'The plugin uses official MET Norway forecast data, combines it with local SVG icons and template logic, and outputs weather widgets through shortcodes. This page summarizes the APIs, database usage, requirements, features, template modes, and important usage notes.',
                'sections' => [
                    [
                        'title' => 'Where The Plugin Gets Data',
                        'items' => [
                            '<strong>Weather forecast:</strong> official MET Norway API endpoint <code>{forecast_api}</code>.',
                            '<strong>Relation to YR:</strong> this is the same MET forecast platform used by <code>yr.no</code>.',
                            '<strong>City autocomplete:</strong> Open-Meteo geocoding endpoint <code>{geocoding_api}</code> is used only for finding coordinates by city name.',
                            '<strong>Icons:</strong> both weather icons and detail icons are stored locally in the plugin package.',
                        ],
                    ],
                    [
                        'title' => 'Database Usage',
                        'items' => [
                            'The plugin <strong>does not create new custom database tables</strong>.',
                            'The plugin <strong>does not add new SQL columns</strong> to the WordPress database schema.',
                            'Weather widgets are stored as custom post type entries in <code>wp_posts</code>.',
                            'Per-widget settings are stored in <code>wp_postmeta</code> under the meta key <code>_yrwp</code>.',
                            'Global plugin settings are stored in <code>wp_options</code> under the option key <code>yrwp_options</code>.',
                            'Cached weather responses are stored through WordPress transients, which usually means additional records in <code>wp_options</code> such as <code>yrwp_cache_*</code>.',
                        ],
                    ],
                    [
                        'title' => 'Important API Rules',
                        'items' => [
                            'MET Norway expects a valid <strong>User-Agent</strong> that identifies your website or application.',
                            'The plugin provides a dedicated contact email field in <strong>Settings</strong>.',
                            'Using a real contact email is strongly recommended for compliance and supportability.',
                            'Weather responses are cached with WordPress transients to reduce repeated API traffic.',
                        ],
                    ],
                    [
                        'title' => 'Forecast Periods',
                        'items' => [
                            '<strong>Current weather</strong>',
                            '<strong>Today</strong>',
                            '<strong>7 days</strong>',
                            '<strong>9 days</strong>',
                        ],
                        'description' => 'Each period can use its own template type.',
                    ],
                    [
                        'title' => 'Template Types',
                        'items' => [
                            '<strong>Default:</strong> ready-made standard layout.',
                            '<strong>Compact:</strong> reduced layout for smaller blocks.',
                            '<strong>Manual:</strong> custom HTML templates with placeholders.',
                            '<strong>JSON:</strong> structured raw output for integrations or debugging.',
                        ],
                    ],
                    [
                        'title' => 'Manual Template Features',
                        'items' => [
                            'Editable HTML templates directly in the admin area.',
                            'Placeholder lists for all supported manual template modes.',
                            'Click-to-copy placeholders in admin for faster editing.',
                            'Optional display of measurement units for formatted Manual placeholders.',
                            'Separate block templates for hourly and daily repeated items.',
                        ],
                    ],
                    [
                        'title' => 'Icons',
                        'items' => [
                            'Weather icons are mapped from MET <code>symbol_code</code> values.',
                            'Detail icons are available for temperature, wind, humidity, pressure, precipitation and cloud cover.',
                            'Icons are loaded from local plugin assets, not from a remote CDN.',
                        ],
                        'description' => 'Assets base path: <code>{icons_path}</code>',
                    ],
                    [
                        'title' => 'Location Modes',
                        'items' => [
                            '<strong>Manual coordinates</strong> for exact fixed points.',
                            '<strong>City search</strong> for quick coordinate lookup.',
                            '<strong>Visitor geolocation</strong> via browser permission and plugin REST flow.',
                        ],
                    ],
                    [
                        'title' => 'Key Plugin Capabilities',
                        'items' => [
                            'Create multiple widget instances and configure each one independently.',
                            'Use shortcode output with <code>[yr_weather id="123"]</code>.',
                            'Apply per-widget custom CSS.',
                            'Use built-in templates or fully custom Manual layouts.',
                            'Choose refresh intervals for cached weather data.',
                            'Render localized labels and condition text from plugin logic.',
                        ],
                    ],
                    [
                        'title' => 'Best Practices',
                        'items' => [
                            'Always fill in the User-Agent email before production use.',
                            'Do not set refresh intervals lower than you actually need.',
                            'Keep custom CSS scoped to widget classes to avoid accidental global styling.',
                            'Remember that Manual templates render placeholders only and do not execute PHP code.',
                            'Expect visitor geolocation to depend on browser permission and device support.',
                        ],
                    ],
                ],
            ],
            'nb' => [
                'header_title' => 'YR Weather Pro',
                'header_intro' => 'Pluginen bruker offisielle vaerdata fra MET Norway, kombinerer dem med lokale SVG-ikoner og mallogikk, og viser vaerwidgeter via shortcodes. Denne siden oppsummerer API-er, databasebruk, krav, funksjoner, maltyper og viktige merknader.',
                'sections' => [
                    [
                        'title' => 'Hvor pluginen henter data',
                        'items' => [
                            '<strong>Vaervarsel:</strong> offisielt MET Norway-endepunkt <code>{forecast_api}</code>.',
                            '<strong>Forholdet til YR:</strong> dette er den samme prognoseplattformen som brukes av <code>yr.no</code>.',
                            '<strong>Bysok:</strong> Open-Meteo geokodingsendepunkt <code>{geocoding_api}</code> brukes bare til a finne koordinater etter bynavn.',
                            '<strong>Ikoner:</strong> bade vaerikoner og detaljikoner lagres lokalt i pluginpakken.',
                        ],
                    ],
                    [
                        'title' => 'Databasebruk',
                        'items' => [
                            'Pluginen <strong>oppretter ikke nye egendefinerte databasetabeller</strong>.',
                            'Pluginen <strong>legger ikke til nye SQL-kolonner</strong> i WordPress-databasen.',
                            'Vaerwidgeter lagres som custom post type-poster i <code>wp_posts</code>.',
                            'Innstillinger per widget lagres i <code>wp_postmeta</code> under metanokkelen <code>_yrwp</code>.',
                            'Globale plugininnstillinger lagres i <code>wp_options</code> under option-nokkelen <code>yrwp_options</code>.',
                            'Bufrede vaersvar lagres via WordPress transients, som vanligvis betyr ekstra rader i <code>wp_options</code> som <code>yrwp_cache_*</code>.',
                        ],
                    ],
                    [
                        'title' => 'Viktige API-regler',
                        'items' => [
                            'MET Norway forventer en gyldig <strong>User-Agent</strong> som identifiserer nettstedet eller applikasjonen din.',
                            'Pluginen har et eget kontaktfelt for e-post i <strong>Innstillinger</strong>.',
                            'Det anbefales sterkt a bruke en ekte kontakt-e-post for samsvar og support.',
                            'Vaersvar bufres med WordPress transients for a redusere gjentatt API-trafikk.',
                        ],
                    ],
                    [
                        'title' => 'Varselperioder',
                        'items' => [
                            '<strong>Naavaerende vaer</strong>',
                            '<strong>I dag</strong>',
                            '<strong>7 dager</strong>',
                            '<strong>9 dager</strong>',
                        ],
                        'description' => 'Hver periode kan bruke sin egen maltype.',
                    ],
                    [
                        'title' => 'Maltyper',
                        'items' => [
                            '<strong>Standard:</strong> ferdig standardoppsett.',
                            '<strong>Kompakt:</strong> redusert oppsett for mindre blokker.',
                            '<strong>Manuell:</strong> egendefinerte HTML-maler med plassholdere.',
                            '<strong>JSON:</strong> strukturert rautdata for integrasjoner eller feilsoking.',
                        ],
                    ],
                    [
                        'title' => 'Funksjoner i manuelle maler',
                        'items' => [
                            'Redigerbare HTML-maler direkte i adminomradet.',
                            'Lister over plassholdere for alle stottede manuelle malmoduser.',
                            'Klikk-for-a-kopiere plassholdere i admin for raskere redigering.',
                            'Valgfri visning av malenheter for formaterte manuelle plassholdere.',
                            'Separate blokkmaler for gjentatte time- og dagselementer.',
                        ],
                    ],
                    [
                        'title' => 'Ikoner',
                        'items' => [
                            'Vaerikoner knyttes til MET-verdier fra <code>symbol_code</code>.',
                            'Detaljikoner finnes for temperatur, vind, luftfuktighet, trykk, nedbor og skydekke.',
                            'Ikonene lastes fra lokale pluginressurser, ikke fra et eksternt CDN.',
                        ],
                        'description' => 'Grunnsti for ressurser: <code>{icons_path}</code>',
                    ],
                    [
                        'title' => 'Lokasjonsmoduser',
                        'items' => [
                            '<strong>Manuelle koordinater</strong> for eksakte faste punkter.',
                            '<strong>Bysok</strong> for raskt oppslag av koordinater.',
                            '<strong>Besoksgeolokasjon</strong> via nettlesertillatelse og pluginens REST-flyt.',
                        ],
                    ],
                    [
                        'title' => 'Viktige pluginmuligheter',
                        'items' => [
                            'Opprett flere widgetinstanser og konfigurer hver av dem separat.',
                            'Bruk shortcode-utdata med <code>[yr_weather id="123"]</code>.',
                            'Bruk egendefinert CSS per widget.',
                            'Bruk innebygde maler eller helt manuelle oppsett.',
                            'Velg oppdateringsintervaller for bufrede vaerdata.',
                            'Vis lokaliserte etiketter og vaertekster fra pluginlogikken.',
                        ],
                    ],
                    [
                        'title' => 'Beste praksis',
                        'items' => [
                            'Fyll alltid inn User-Agent-e-post før produksjonsbruk.',
                            'Ikke sett kortere oppdateringsintervall enn du faktisk trenger.',
                            'Hold egendefinert CSS innenfor widgetklasser for a unnga utilsiktet global styling.',
                            'Husk at manuelle maler bare renderer plassholdere og ikke utforer PHP.',
                            'Besoksgeolokasjon avhenger av nettlesertillatelse og enhetsstotte.',
                        ],
                    ],
                ],
            ],
            'uk' => [
                'header_title' => 'YR Weather Pro',
                'header_intro' => 'Плагін використовує офіційні дані прогнозу MET Norway, поєднує їх з локальними SVG-іконками та логікою шаблонів і виводить погодні віджети через шорткоди. На цій сторінці зібрано інформацію про API, використання бази даних, вимоги, можливості, типи шаблонів і важливі примітки.',
                'sections' => [
                    [
                        'title' => 'Звідки плагін бере дані',
                        'items' => [
                            '<strong>Прогноз погоди:</strong> офіційний endpoint MET Norway <code>{forecast_api}</code>.',
                            '<strong>Звʼязок з YR:</strong> це та сама платформа прогнозу MET, яку використовує <code>yr.no</code>.',
                            '<strong>Пошук міста:</strong> endpoint геокодування Open-Meteo <code>{geocoding_api}</code> використовується лише для пошуку координат за назвою міста.',
                            '<strong>Іконки:</strong> іконки погоди та деталізовані іконки зберігаються локально в пакеті плагіну.',
                        ],
                    ],
                    [
                        'title' => 'Використання бази даних',
                        'items' => [
                            'Плагін <strong>не створює нових власних таблиць бази даних</strong>.',
                            'Плагін <strong>не додає нових SQL-полів</strong> у схему бази даних WordPress.',
                            'Погодні віджети зберігаються як записи custom post type у <code>wp_posts</code>.',
                            'Налаштування кожного віджета зберігаються в <code>wp_postmeta</code> під meta key <code>_yrwp</code>.',
                            'Глобальні налаштування плагіну зберігаються в <code>wp_options</code> під option key <code>yrwp_options</code>.',
                            'Кешовані погодні відповіді зберігаються через WordPress transients, тобто зазвичай це додаткові записи в <code>wp_options</code> на кшталт <code>yrwp_cache_*</code>.',
                        ],
                    ],
                    [
                        'title' => 'Важливі правила API',
                        'items' => [
                            'MET Norway очікує коректний <strong>User-Agent</strong>, який ідентифікує ваш сайт або застосунок.',
                            'У плагіні є окреме поле контактного email у вкладці <strong>Настройки</strong>.',
                            'Використання реального контактного email настійно рекомендується для відповідності вимогам і підтримки.',
                            'Погодні відповіді кешуються через WordPress transients, щоб зменшити кількість повторних API-запитів.',
                        ],
                    ],
                    [
                        'title' => 'Періоди прогнозу',
                        'items' => [
                            '<strong>Поточна погода</strong>',
                            '<strong>Сьогодні</strong>',
                            '<strong>7 днів</strong>',
                            '<strong>9 днів</strong>',
                        ],
                        'description' => 'Кожен період може використовувати власний тип шаблону.',
                    ],
                    [
                        'title' => 'Типи шаблонів',
                        'items' => [
                            '<strong>Стандартний:</strong> готовий стандартний макет.',
                            '<strong>Компактний:</strong> зменшений макет для невеликих блоків.',
                            '<strong>Ручний:</strong> власні HTML-шаблони з плейсхолдерами.',
                            '<strong>JSON:</strong> структурований сирий вивід для інтеграцій або налагодження.',
                        ],
                    ],
                    [
                        'title' => 'Можливості ручних шаблонів',
                        'items' => [
                            'Редаговані HTML-шаблони безпосередньо в адмінці.',
                            'Списки плейсхолдерів для всіх підтримуваних режимів ручних шаблонів.',
                            'Клік для копіювання плейсхолдерів в адмінці для швидшого редагування.',
                            'Необовʼязковий показ одиниць виміру для форматованих ручних плейсхолдерів.',
                            'Окремі шаблони блоків для повторюваних погодинних і денних елементів.',
                        ],
                    ],
                    [
                        'title' => 'Іконки',
                        'items' => [
                            'Іконки погоди привʼязуються до значень MET <code>symbol_code</code>.',
                            'Деталізовані іконки доступні для температури, вітру, вологості, тиску, опадів і хмарності.',
                            'Іконки завантажуються з локальних ресурсів плагіну, а не з віддаленого CDN.',
                        ],
                        'description' => 'Базовий шлях до ресурсів: <code>{icons_path}</code>',
                    ],
                    [
                        'title' => 'Режими локації',
                        'items' => [
                            '<strong>Ручні координати</strong> для точних фіксованих точок.',
                            '<strong>Пошук міста</strong> для швидкого підбору координат.',
                            '<strong>Геолокація відвідувача</strong> через дозвіл браузера і REST-механізм плагіну.',
                        ],
                    ],
                    [
                        'title' => 'Ключові можливості плагіну',
                        'items' => [
                            'Створення кількох екземплярів віджетів і окреме налаштування кожного.',
                            'Використання shortcode-виводу через <code>[yr_weather id="123"]</code>.',
                            'Застосування власного CSS для кожного віджета.',
                            'Використання вбудованих шаблонів або повністю ручних макетів.',
                            'Вибір інтервалів оновлення для кешованих погодних даних.',
                            'Виведення локалізованих підписів і текстів погодних станів логікою плагіну.',
                        ],
                    ],
                    [
                        'title' => 'Рекомендації',
                        'items' => [
                            'Завжди заповнюйте email для User-Agent перед використанням у продакшені.',
                            'Не встановлюйте коротший інтервал оновлення, ніж вам реально потрібен.',
                            'Тримайте власний CSS у межах класів віджета, щоб уникнути випадкового глобального впливу.',
                            'Памʼятайте, що ручні шаблони лише рендерять плейсхолдери і не виконують PHP-код.',
                            'Геолокація відвідувача залежить від дозволу браузера і підтримки пристрою.',
                        ],
                    ],
                ],
            ],
        ];
    }
}
