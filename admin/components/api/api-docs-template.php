<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<style>
.pp-api-docs details { margin-bottom: 1.5em; }
.pp-api-docs summary { cursor: pointer; font-size: 1.05em; padding: 6px 0; font-weight: 600; }
.pp-api-docs .pp-api-endpoint { margin: 1em 0 1.5em 1em; border-left: 3px solid #2271b1; padding-left: 1em; }
.pp-api-docs .pp-method { display: inline-block; background: #2271b1; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 3px; margin-right: 6px; vertical-align: middle; }
.pp-api-docs pre { background: #f6f7f7; border: 1px solid #ddd; padding: 12px; overflow-x: auto; font-size: 12px; max-height: 300px; }
.pp-api-docs table.widefat { margin: 8px 0; }
.pp-api-docs table.widefat th { background: #f6f7f7; }
.pp-api-docs .pp-api-desc { color: #50575e; margin: 4px 0 8px; }
</style>

<div class="pp-api-docs pp-container">
    <main class="pp-main">

        <div class="pp-section-header">
            <div>
                <h1 class="pp-section-title">Puck Press REST API</h1>
                <p class="pp-section-description">Read-only JSON API for all Puck Press data. Base URL: <code><?php echo esc_url( $base_url ); ?></code></p>
            </div>
        </div>

        <!-- ── Schedules ──────────────────────────────────────── -->
        <details open>
            <summary>Schedules</summary>

            <div class="pp-api-endpoint">
                <span class="pp-method">GET</span>
                <a href="<?php echo esc_url( $base_url . '/schedules' ); ?>" target="_blank"><code>/schedules</code></a>
                <p class="pp-api-desc">Returns all configured schedules.</p>
            </div>

            <div class="pp-api-endpoint">
                <span class="pp-method">GET</span>
                <code>/schedules/{id}/games</code>
                <p class="pp-api-desc">All games for a schedule. Supports filtering and pagination.</p>
                <table class="widefat striped" style="max-width:600px;">
                    <thead><tr><th>Param</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>status</code></td><td>all</td><td><code>all</code>, <code>completed</code>, or <code>upcoming</code></td></tr>
                        <tr><td><code>team</code></td><td>—</td><td>Filter by team ID</td></tr>
                        <tr><td><code>page</code></td><td>1</td><td>Page number</td></tr>
                        <tr><td><code>per_page</code></td><td>50</td><td>Results per page (max 100)</td></tr>
                        <tr><td><code>limit</code></td><td>—</td><td>Hard cap (overrides pagination)</td></tr>
                    </tbody>
                </table>
            </div>
        </details>

        <!-- ── Scores & Upcoming ──────────────────────────────── -->
        <details>
            <summary>Recent Scores &amp; Upcoming Games</summary>

            <div class="pp-api-endpoint">
                <span class="pp-method">GET</span>
                <code>/schedules/{id}/scores/recent</code>
                <p class="pp-api-desc">Most recently completed games, newest first.</p>
                <table class="widefat striped" style="max-width:400px;">
                    <thead><tr><th>Param</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>limit</code></td><td>5</td><td>Max 20</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="pp-api-endpoint">
                <span class="pp-method">GET</span>
                <code>/schedules/{id}/games/upcoming</code>
                <p class="pp-api-desc">Next N unplayed games, soonest first.</p>
                <table class="widefat striped" style="max-width:400px;">
                    <thead><tr><th>Param</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>limit</code></td><td>5</td><td>Max 20</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="pp-api-endpoint">
                <span class="pp-method">GET</span>
                <code>/schedules/{id}/games/next</code>
                <p class="pp-api-desc">Single next game. Returns <code>null</code> in <code>data</code> if none.</p>
            </div>
        </details>

        <!-- ── Record & Standings ─────────────────────────────── -->
        <details>
            <summary>Record &amp; Standings</summary>

            <div class="pp-api-endpoint">
                <span class="pp-method">GET</span>
                <code>/schedules/{id}/record</code>
                <p class="pp-api-desc">Overall W/L/OTL record with home/away splits and goal stats.</p>
            </div>

            <div class="pp-api-endpoint">
                <span class="pp-method">GET</span>
                <code>/schedules/{id}/standings</code>
                <p class="pp-api-desc">Multi-team standings sorted by points (W&times;2 + OTL + T).</p>
                <table class="widefat striped" style="max-width:400px;">
                    <thead><tr><th>Param</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>mode</code></td><td>conference</td><td><code>conference</code> or <code>overall</code></td></tr>
                    </tbody>
                </table>
            </div>
        </details>

        <!-- ── Stats ──────────────────────────────────────────── -->
        <details>
            <summary>Stats</summary>

            <div class="pp-api-endpoint">
                <span class="pp-method">GET</span>
                <a href="<?php echo esc_url( $base_url . '/stats/skaters' ); ?>" target="_blank"><code>/stats/skaters</code></a>
                <p class="pp-api-desc">Full skater statistics table.</p>
                <table class="widefat striped" style="max-width:600px;">
                    <thead><tr><th>Param</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>teams</code></td><td>—</td><td>Comma-separated team IDs</td></tr>
                        <tr><td><code>sort</code></td><td>points</td><td><code>points</code>, <code>goals</code>, <code>assists</code>, <code>games_played</code>, <code>penalty_minutes</code></td></tr>
                        <tr><td><code>page</code></td><td>1</td><td>Page number</td></tr>
                        <tr><td><code>per_page</code></td><td>50</td><td>Results per page (max 100)</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="pp-api-endpoint">
                <span class="pp-method">GET</span>
                <a href="<?php echo esc_url( $base_url . '/stats/goalies' ); ?>" target="_blank"><code>/stats/goalies</code></a>
                <p class="pp-api-desc">Full goalie statistics table.</p>
                <table class="widefat striped" style="max-width:600px;">
                    <thead><tr><th>Param</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>teams</code></td><td>—</td><td>Comma-separated team IDs</td></tr>
                        <tr><td><code>sort</code></td><td>wins</td><td><code>wins</code>, <code>save_percentage</code>, <code>goals_against_average</code>, <code>games_played</code></td></tr>
                    </tbody>
                </table>
            </div>

            <div class="pp-api-endpoint">
                <span class="pp-method">GET</span>
                <a href="<?php echo esc_url( $base_url . '/stats/leaders' ); ?>" target="_blank"><code>/stats/leaders</code></a>
                <p class="pp-api-desc">Category leaders (goals, assists, points, etc.).</p>
                <table class="widefat striped" style="max-width:600px;">
                    <thead><tr><th>Param</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>teams</code></td><td>—</td><td>Comma-separated team IDs</td></tr>
                        <tr><td><code>categories</code></td><td>all</td><td>Comma-separated: <code>goals,assists,points,pim,gaa,saves,sv_pct,wins</code></td></tr>
                    </tbody>
                </table>
            </div>
        </details>

        <!-- ── Roster & Players ───────────────────────────────── -->
        <details>
            <summary>Roster &amp; Players</summary>

            <div class="pp-api-endpoint">
                <span class="pp-method">GET</span>
                <code>/rosters/{id}/players</code>
                <p class="pp-api-desc">Full roster for a roster group.</p>
                <table class="widefat striped" style="max-width:400px;">
                    <thead><tr><th>Param</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>pos</code></td><td>—</td><td>Filter by position: <code>F</code>, <code>D</code>, <code>G</code></td></tr>
                    </tbody>
                </table>
            </div>

            <div class="pp-api-endpoint">
                <span class="pp-method">GET</span>
                <code>/players/{player_id}</code>
                <p class="pp-api-desc">Single player detail with stats. Returns first match if <code>team_id</code> omitted.</p>
                <table class="widefat striped" style="max-width:400px;">
                    <thead><tr><th>Param</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>team_id</code></td><td>—</td><td>Disambiguate if player exists on multiple teams</td></tr>
                    </tbody>
                </table>
            </div>
        </details>

        <!-- ── Teams ──────────────────────────────────────────── -->
        <details>
            <summary>Teams</summary>

            <div class="pp-api-endpoint">
                <span class="pp-method">GET</span>
                <a href="<?php echo esc_url( $base_url . '/teams' ); ?>" target="_blank"><code>/teams</code></a>
                <p class="pp-api-desc">All registered teams.</p>
            </div>
        </details>

        <!-- ── Archives ───────────────────────────────────────── -->
        <details>
            <summary>Archives</summary>

            <div class="pp-api-endpoint">
                <span class="pp-method">GET</span>
                <a href="<?php echo esc_url( $base_url . '/archives' ); ?>" target="_blank"><code>/archives</code></a>
                <p class="pp-api-desc">List all available season archives.</p>
                <table class="widefat striped" style="max-width:400px;">
                    <thead><tr><th>Param</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>teams</code></td><td>—</td><td>Comma-separated team IDs</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="pp-api-endpoint">
                <span class="pp-method">GET</span>
                <code>/archives/{season_key}/stats/skaters</code>
                <p class="pp-api-desc">Skater stats for an archived season.</p>
            </div>

            <div class="pp-api-endpoint">
                <span class="pp-method">GET</span>
                <code>/archives/{season_key}/stats/goalies</code>
                <p class="pp-api-desc">Goalie stats for an archived season.</p>
            </div>

            <div class="pp-api-endpoint">
                <span class="pp-method">GET</span>
                <code>/archives/{season_key}/scores</code>
                <p class="pp-api-desc">All game results for an archived season.</p>
            </div>
        </details>

        <!-- ── Awards ─────────────────────────────────────────── -->
        <details>
            <summary>Awards</summary>

            <div class="pp-api-endpoint">
                <span class="pp-method">GET</span>
                <a href="<?php echo esc_url( $base_url . '/awards' ); ?>" target="_blank"><code>/awards</code></a>
                <p class="pp-api-desc">All awards, filterable by year or parent group.</p>
                <table class="widefat striped" style="max-width:400px;">
                    <thead><tr><th>Param</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>year</code></td><td>—</td><td>Filter by year</td></tr>
                        <tr><td><code>parent</code></td><td>—</td><td>Filter by parent group (case-insensitive)</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="pp-api-endpoint">
                <span class="pp-method">GET</span>
                <code>/awards/{slug}/players</code>
                <p class="pp-api-desc">Players for a specific award by slug.</p>
            </div>
        </details>

        <!-- ── Data Shortcodes ────────────────────────────────── -->
        <details>
            <summary>Data Shortcodes</summary>

            <div class="pp-api-endpoint">
                <p class="pp-api-desc">Plain-text shortcodes for embedding live data in any page content. No JavaScript required.</p>

                <h4 style="margin:1em 0 0.5em;"><code>[pp-last-game]</code></h4>
                <p class="pp-api-desc">Single value from the most recently completed game.</p>
                <table class="widefat striped" style="max-width:600px;">
                    <thead><tr><th>Attribute</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>schedule</code></td><td>(main)</td><td>Schedule slug or ID</td></tr>
                        <tr><td><code>field</code></td><td>date</td><td><code>date</code>, <code>time</code>, <code>opponent</code>, <code>opponent_logo</code>, <code>target_team</code>, <code>target_logo</code>, <code>status</code>, <code>score</code>, <code>venue</code>, <code>location</code>, <code>result</code></td></tr>
                        <tr><td><code>date_format</code></td><td>M j, Y</td><td>PHP date format (for <code>field="date"</code>)</td></tr>
                    </tbody>
                </table>

                <h4 style="margin:1em 0 0.5em;"><code>[pp-next-game]</code></h4>
                <p class="pp-api-desc">Single value from the next unplayed game.</p>
                <table class="widefat striped" style="max-width:600px;">
                    <thead><tr><th>Attribute</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>schedule</code></td><td>(main)</td><td>Schedule slug or ID</td></tr>
                        <tr><td><code>field</code></td><td>date</td><td><code>date</code>, <code>time</code>, <code>opponent</code>, <code>opponent_logo</code>, <code>target_team</code>, <code>target_logo</code>, <code>venue</code>, <code>location</code>, <code>ticket_link</code>, <code>promo_header</code></td></tr>
                        <tr><td><code>date_format</code></td><td>M j, Y</td><td>PHP date format (for <code>field="date"</code>)</td></tr>
                    </tbody>
                </table>

                <h4 style="margin:1em 0 0.5em;"><code>[pp-top-scorer]</code></h4>
                <p class="pp-api-desc">Single value for the current points leader.</p>
                <table class="widefat striped" style="max-width:600px;">
                    <thead><tr><th>Attribute</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>teams</code></td><td>(all)</td><td>Comma-separated team IDs</td></tr>
                        <tr><td><code>field</code></td><td>name</td><td><code>name</code>, <code>goals</code>, <code>assists</code>, <code>points</code>, <code>games_played</code>, <code>team</code>, <code>headshot</code>, <code>pos</code></td></tr>
                    </tbody>
                </table>

                <h4 style="margin:1em 0 0.5em;"><code>[pp-record-text]</code></h4>
                <p class="pp-api-desc">Season record as text. Drop it inline: "Our record is [pp-record-text]"</p>
                <table class="widefat striped" style="max-width:600px;">
                    <thead><tr><th>Attribute</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>schedule</code></td><td>(main)</td><td>Schedule slug or ID</td></tr>
                        <tr><td><code>field</code></td><td>record</td><td><code>record</code> (10-4-2), <code>home_record</code>, <code>away_record</code>, <code>wins</code>, <code>losses</code>, <code>otl</code>, <code>ties</code>, <code>gf</code>, <code>ga</code>, <code>diff</code> (+14), <code>gp</code>, <code>win_pct</code> (.714), <code>points</code></td></tr>
                    </tbody>
                </table>

                <h4 style="margin:1em 0 0.5em;"><code>[pp-streak]</code></h4>
                <p class="pp-api-desc">Current win/loss streak, e.g. "W3", "L1", "OTL2".</p>
                <table class="widefat striped" style="max-width:400px;">
                    <thead><tr><th>Attribute</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>schedule</code></td><td>(main)</td><td>Schedule slug or ID</td></tr>
                    </tbody>
                </table>

                <h4 style="margin:1em 0 0.5em;"><code>[pp-top-goalie]</code></h4>
                <p class="pp-api-desc">Top goalie by a stat. Sorted by <code>sort</code> attribute.</p>
                <table class="widefat striped" style="max-width:600px;">
                    <thead><tr><th>Attribute</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>teams</code></td><td>(all)</td><td>Comma-separated team IDs</td></tr>
                        <tr><td><code>sort</code></td><td>wins</td><td>Rank by: <code>wins</code>, <code>save_percentage</code>, <code>goals_against_average</code> (lowest first)</td></tr>
                        <tr><td><code>field</code></td><td>name</td><td><code>name</code>, <code>wins</code>, <code>losses</code>, <code>gaa</code>, <code>sv_pct</code>, <code>saves</code>, <code>games_played</code>, <code>team</code>, <code>headshot</code></td></tr>
                    </tbody>
                </table>

                <h4 style="margin:1em 0 0.5em;"><code>[pp-games-remaining]</code></h4>
                <p class="pp-api-desc">Number of upcoming games as a plain number.</p>
                <table class="widefat striped" style="max-width:400px;">
                    <thead><tr><th>Attribute</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>schedule</code></td><td>(main)</td><td>Schedule slug or ID</td></tr>
                    </tbody>
                </table>

                <h4 style="margin:1em 0 0.5em;"><code>[pp-next-home-game]</code></h4>
                <p class="pp-api-desc">Same fields as [pp-next-game] but specifically the next <strong>home</strong> game.</p>
                <table class="widefat striped" style="max-width:600px;">
                    <thead><tr><th>Attribute</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>schedule</code></td><td>(main)</td><td>Schedule slug or ID</td></tr>
                        <tr><td><code>field</code></td><td>date</td><td>Same fields as [pp-next-game]</td></tr>
                        <tr><td><code>date_format</code></td><td>M j, Y</td><td>PHP date format</td></tr>
                    </tbody>
                </table>

                <h4 style="margin:1em 0 0.5em;"><code>[pp-player]</code></h4>
                <p class="pp-api-desc">Look up any player by name or jersey number and return a specific field.</p>
                <table class="widefat striped" style="max-width:600px;">
                    <thead><tr><th>Attribute</th><th>Default</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>lookup</code></td><td>—</td><td>Player name (partial match) or jersey number</td></tr>
                        <tr><td><code>teams</code></td><td>(all)</td><td>Comma-separated team IDs to scope search</td></tr>
                        <tr><td><code>field</code></td><td>name</td><td><code>name</code>, <code>number</code>, <code>pos</code>, <code>ht</code>, <code>wt</code>, <code>shoots</code>, <code>hometown</code>, <code>last_team</code>, <code>class</code>, <code>major</code>, <code>team</code>, <code>headshot</code>, <code>hero_image</code>, <code>slug</code>, <code>url</code></td></tr>
                    </tbody>
                </table>

                <h4 style="margin:1em 0 0.5em;">Examples</h4>
                <pre>We beat [pp-last-game field="opponent"] [pp-last-game field="score"]!

Season record: [pp-record-text] | Streak: [pp-streak]
Win pct: [pp-record-text field="win_pct"] | Games left: [pp-games-remaining]

Next home game: [pp-next-home-game field="date"] vs [pp-next-home-game field="opponent"]

Points leader: [pp-top-scorer field="name"] ([pp-top-scorer field="points"] pts)
Starting goalie: [pp-top-goalie field="name" sort="wins"] ([pp-top-goalie field="wins" sort="wins"]W)

#[pp-player lookup="97" field="number"] [pp-player lookup="97" field="name"]
  from [pp-player lookup="97" field="hometown"]
  &lt;a href="[pp-player lookup="97" field="url"]"&gt;View profile&lt;/a&gt;</pre>
            </div>
        </details>

    </main>
</div>
