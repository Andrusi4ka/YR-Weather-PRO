=== YR Weather Pro ===
Contributors: andrusi4ka
Tags: weather, forecast, yr, met norway, widget
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 9.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Weather widgets with data from MET Norway (yr.no): current weather, today, 7 or 9 days, with ready-made, manual or JSON templates.

== Description ==

YR Weather Pro adds a "YR Weather" menu where administrators create weather widgets and place them with `[yr_weather id="123"]`.

* Any number of widgets, each with its own location: coordinates, a city (with search) or the visitor's location from the browser.
* Periods: current weather, today, 7 days, 9 days.
* Templates: default, compact, manual (your own HTML with tokens) and JSON.
* Choice of fields, custom texts, custom CSS class and CSS per widget.
* Own SVG icons from `wp-content/uploads/yrwp-icons/`, theme template overrides in `your-theme/yr-weather-pro/`.
* Forecasts cached per widget; texts in English, Norwegian and Ukrainian, following the WordPress language.

Weather data: [MET Norway](https://www.met.no/), licensed under CC BY 4.0 (the default templates show a credit line, which each widget can switch off). City search and time zones: [Open-Meteo](https://open-meteo.com/).

Bundles [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) 5 (MIT).

== Installation ==

1. Download `yr-weather-pro.zip` from the latest release on https://github.com/Andrusi4ka/YR-Weather-PRO/releases.
2. In WordPress go to Plugins > Add New > Upload Plugin and upload the zip, then activate it.
3. Under YR Weather > Settings enter a contact e-mail (MET asks for one in requests).
4. Add a widget, choose its location and template, and paste its shortcode into a page.

Later versions are offered under Plugins like any other update.

== Frequently Asked Questions ==

= What is removed when I delete the plugin? =

Its settings, all weather widgets and the cached forecasts.

= Where do updates come from? =

From this plugin's GitHub releases. The plugin header's "Update URI" stops WordPress from offering a wordpress.org plugin of the same folder name.

== Changelog ==

= 9.1.1 =
* "Settings" link next to Deactivate in the Plugins list.

= 9.1.0 =
* Removed the access key: the plugin works as soon as it is activated (weather, city search, REST). A key saved by an older version is ignored and dropped on the next settings save.
* Removed the language setting: texts follow the WordPress language (the user's in the admin, the site's on the site); Ukrainian and Norwegian have their own texts, other languages get English.
* New widget setting "Data credit": the MET Norway credit line can be switched off per widget (on by default, also for existing widgets).
* Icons page: both tables show the manual template placeholder; the outdated note about matching file names is gone (an uploaded SVG can have any name).
* Fixed: the time zone of a widget at fixed coordinates was never found (the Open-Meteo address used does not exist); it now comes from Open-Meteo's forecast API.

= 9.0.0 =
* Updates from GitHub releases, shown under Plugins like any other update.
* Security: the REST endpoints take coordinates only for widgets in visitor location mode, rounded to about 1 km, so they can no longer be used to make the site fetch and cache forecasts for any number of places.
* Security: weather widgets (with their CSS and templates) can be created and edited by administrators only; before, any user who could write posts could.
* Fixed: custom CSS turned ">" into "&gt;", breaking child selectors. CSS saved that way is repaired when shown.
* The stylesheets and script load only on pages with a weather widget, not on every page.
* Default templates show a "MET Norway" credit line, as the data licence asks.
* Loading and error texts on the site follow the plugin language.
* Template previews in the admin are animated WebP instead of GIF: the plugin is about 18 MB smaller.
* Fixed: a visitor location widget with a manual or JSON template closed a tag it had not opened.
* The cached time zones are removed when the plugin is deleted.
* Code cleanup.

= 8.4 =
* Last version before GitHub releases.
