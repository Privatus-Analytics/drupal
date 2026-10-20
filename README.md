# Privatus Analytics

Adds the cookieless [Privatus Analytics](https://privatusanalytics.com)
tracking script to your Drupal pages.

Privatus Analytics counts visits, sources, pages and conversions without
cookies, local storage or fingerprinting, so analytics does not need a consent
banner by itself. Check with your own counsel for your country.

The module only prints the browser script in the page head. It never sends
events from your server and sets no cookies.

You need a Privatus Analytics account to see reports. Sign up at
https://privatusanalytics.com.

## Requirements

* Drupal 10.3 or later, or Drupal 11.
* PHP 8.1 or later.
* A Privatus Analytics site id (it starts with `pa_`).

## Installation

Install it like any other module.

```
composer require drupal/privatus_analytics
drush en privatus_analytics
```

## Configuration

1. Go to Administration, Configuration, System, Privatus Analytics
(`/admin/config/system/privatus-analytics`).
2. Enter your site id and save. Nothing is loaded until you do.
3. Optional: choose which roles are not tracked, add excluded paths, turn on
modules such as click maps or Core Web Vitals.

### Settings

* Site ID: The `data-site` attribute. Required.
* Script host: The address that serves `/js/pa.js`. Defaults to
  `https://privatusstats.com`. Change it only if you host your own copy.
* Do not track administration pages: Skips every administration route (on by
  default).
* Do not track users with these roles: Logged-in users with a chosen role get
  no script.
* Excluded paths: Comma-separated paths the script never tracks
  (`data-exclude`).
* Single-page app mode, manual pageviews, modules, allowed domains, path
  masks, extra query parameters, canonical URL, hash routing: Map one to one
  to the script's `data-*` attributes. See the [tracker
  attributes](https://privatusanalytics.com/docs/tracker/attributes).

### Permissions

* **Administer Privatus Analytics** changes the settings. It is marked
  restricted.
* **Do not track with Privatus Analytics** keeps a user out of your reports.
  Give it to editors and administrators. Note that user 1 always has every
  permission, so user 1 is never tracked.

## External service

After you save a site id, every page loads
`https://privatusstats.com/js/pa.js` in the visitor's browser. The script
sends the page address (query parameters are removed except campaign
parameters and any you allow), the referrer, the browser language, the screen
width, the time zone name and event names to
`https://privatusstats.com/api/event`. The visitor's IP address is read in
memory to derive an approximate country and to count unique visits, and it is
not stored.

* Terms of service: https://privatusanalytics.com/legal/terms
* Privacy policy: https://privatusanalytics.com/legal/privacy

## Caching

The tag is added with cache contexts for the user's permissions, roles and the
route, so Drupal's page cache and Dynamic Page Cache serve the right output.
The settings are a cache tag, so saving them refreshes every page.

## Tests

Unit and kernel tests live in `tests/`. Run them from a Drupal root:

```
vendor/bin/phpunit -c web/core web/modules/contrib/privatus_analytics/tests
```

## License

GPL-2.0-or-later. See https://www.gnu.org/licenses/old-licenses/gpl-2.0.html.
