=== LoadGate Forge ===
Contributors: gunjanjaswal
Donate link: https://ko-fi.com/gunjanjaswal
Tags: performance, plugins, conditional, optimization, speed
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stop selected plugins from loading on chosen front-end URLs. Lighter pages, reversible, and admin, login and REST are never touched.

== Description ==

Most sites run a few plugins that are only needed on one or two pages: a form plugin used on the contact page, a gallery plugin used in the portfolio, a chat widget you only want on the homepage. Loading all of them on every request is wasted work.

LoadGate Forge lets you say "do not load this plugin on these URLs" and takes it off those requests entirely, so the code never runs there.

* Pick a URL path and the plugins that should sit out on it.
* Match by "contains", "starts with", or an exact path.
* A master switch turns every rule off in one click.
* Nothing is deleted and nothing is permanent. Clear a rule and the plugin loads normally again.

To take a plugin off a request, the decision has to happen before WordPress loads plugins. LoadGate Forge does that through a small must-use loader it installs for you on activation, and removes when you deactivate.

= What it does not touch =

Rules only ever apply to normal front-end page views. The admin area, the login screen, REST API calls, cron and WP-CLI are always left with every plugin active, so you cannot lock yourself out of the dashboard.

= Good to know =

* Matching happens before the page template is known, so rules are based on the request URL, not on conditional tags like is_page().
* Disabling a plugin that builds a page will break that page. Test each URL after you save a rule.
* Single-site installs. Multisite network activation is not supported yet.

== Installation ==

1. Upload the `loadgateforge` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate the plugin. It installs its loader into `wp-content/mu-plugins/`.
3. Go to Settings then LoadGate Forge and add your first rule.

If you see a warning that the loader could not be installed, make sure `wp-content/mu-plugins/` exists and is writable, then reactivate.

== Frequently Asked Questions ==

= Will this break my admin area? =

No. Rules never apply to admin, login, REST or cron requests. Even a rule that matches everything only affects front-end page views.

= What happens to a plugin I disable on a page? =

Its code simply does not load for that request, as if it were deactivated, but only there. On every other URL it runs as usual. Remove the rule and it loads everywhere again.

= Why URL paths instead of "this page" or "posts in this category"? =

The choice of which plugins to load happens very early, before WordPress has worked out which page you asked for. At that point the request URL is all there is to go on, so rules match the URL.

= It says the loader could not be installed. =

LoadGate Forge needs to write one file into `wp-content/mu-plugins/`. On most hosts that works automatically. If the folder is missing or not writable, create it and make it writable, then deactivate and reactivate the plugin.

= Does it work on multisite? =

Not yet. This first version targets single-site installs.

== Screenshots ==

1. The LoadGate Forge settings screen: a rule choosing which plugins to skip on a URL, plus the master switch.

== Changelog ==

= 1.0.0 =
* First release: per-URL plugin disabling through an auto-installed must-use loader, with match types, a master switch, and admin, login, REST and cron always excluded.
