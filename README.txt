=== Puck Press ===
Contributors: connormesec
Tags: hockey, schedule, roster, stats, standings
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.22
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Puck Press — A WordPress plugin for ice hockey teams. Display schedules, rosters, stats, standings, and more via shortcodes.

== Description ==

Puck Press is a WordPress plugin built for hockey team websites. It pulls game schedules, rosters, and player stats from external league APIs (ACHA, USPHL), caches everything in custom database tables, and exposes the data through shortcodes and a REST API.

There is no build pipeline — deploy by copying the plugin directory into wp-content/plugins/ and activating it.

== Features ==

= Schedule =
Import and display game schedules from ACHA, USPHL, or CSV. Games are stored in a custom DB table and merged with admin overrides before display.

Shortcode: [pp-schedule schedule="slug"]

Multiple display templates are available: accordion, arena, conference, pill, and slate. Each is configurable with color pickers in the admin.

= Game Slider =
A compact widget showing recent and upcoming games in a horizontal slider format. Good for headers and sidebars.

Shortcode: [pp-slider schedule="slug"]

Templates: compact, gameslider, scoreboard, scorestrip.

= Roster =
Display player rosters imported from the league API or entered manually. Supports multiple roster groups (e.g. varsity + JV). Each player gets a detail page at /player/{slug} with position, stats, and bio info.

Shortcode: [pp-roster roster="slug"]

Templates: cardstack, photogrid.

= Player Stats =
Display a full skater and goalie stats table. Filterable by team. Stats are imported from the league API and stored locally.

Shortcode: [pp-stats team="1,2"]

Template: standard (sortable columns).

= Stat Leaders =
Show top skaters or goalies ranked by points, goals, GAA, save percentage, etc. Scoped to a specific roster group.

Shortcodes: [pp-stat-leaders-skaters roster="slug"] / [pp-stat-leaders-goalies roster="slug"]

Templates: standard leaders, tabbed.

= Standings =
Display division standings fetched from the ACHA API. Stats are computed locally from schedule data: points (W×2 + OTL + T), streak, last-10, goal differential. The plugin deduplicates API calls when multiple teams share a division.

Shortcode: [pp-standings team="slug"]

= Team Record =
Show the team's current win/loss/OTL record, home/away splits, goals for/against, goal differential, and win percentage.

Shortcode: [pp-record schedule="slug"]

Templates: record, conference, slim_conference.

= Awards =
Display team or player awards grouped by category. Supports year filtering via AJAX. Award groups, icons, and colors are all configurable in the admin.

Shortcode: [pp-awards]

= Post Slider =
A visual slider of recent WordPress posts or custom post types, useful for news and announcements sections.

Shortcode: [pp-post-slider post_type="post" count="6" more_url="#" more_text="More Posts"]

Templates: carousel, stories.

= League News =
Pulls and displays the latest news from the league API as a card grid.

Shortcode: [pp-league-news]

= Data Shortcodes =
Inline shortcodes that output a single value, useful for building custom layouts, callout boxes, or hero sections.

[pp-last-game schedule="slug" field="date|opponent|score|result|venue|..."]
[pp-next-game schedule="slug" field="date|opponent|venue|..."]
[pp-next-home-game schedule="slug" field="date|opponent|..."]
[pp-record-inline schedule="slug" field="record|home_record|away_record|diff|win_pct|points|gp"]
[pp-streak schedule="slug"]
[pp-games-remaining schedule="slug"]
[pp-top-scorer teams="1,2" field="name|goals|assists|points|..."]
[pp-top-goalie teams="1,2" field="name|wins|gaa|sv_pct|..." sort="wins|goals_against_average"]
[pp-player lookup="name or number" teams="1" field="name|number|pos|ht|wt|hometown|..."]

= REST API =
Read-only REST endpoints for schedules, rosters, stats, standings, and more. Endpoints are auto-discovered from includes/api/endpoints/ — drop a new class file there to add an endpoint.

= Admin UI =
Each feature has a dedicated admin card for configuration: schedule import, roster management, team setup, color pickers for template theming, and preview cards that render a live preview of the current template settings.

= Scheduled Refresh =
A WP-Cron job (twicedaily by default) refreshes schedule, roster, standings, and stats data from external APIs. It tracks consecutive failures and sends alert emails if a feed goes down.

== Installation ==

1. Upload the puck-press directory to wp-content/plugins/.
2. Activate the plugin through the WordPress Plugins screen.
3. Configure your teams, schedules, and rosters under the Puck Press admin menu.

== Frequently Asked Questions ==

= How do I test time-sensitive logic locally? =
Define PP_FAKE_NOW as a Unix timestamp in wp-config.php. Schedule display and record calculations will use that value as the current time.

= Can I display multiple rosters (varsity + JV)? =
Yes. Create separate roster groups in the admin, each with its own slug, and use [pp-roster roster="slug"] for each one.

= What leagues are supported for automatic data import? =
ACHA and USPHL are supported with API-based importers. Any schedule can also be imported from CSV.

== Changelog ==

= 1.0.22 =
* Add standings module, REST API, and data shortcodes
* Add tabbed stat leaders template
* Add scorestrip slider template
* Add show_title attribute to awards shortcode
* Add transparent background option to awards color picker
