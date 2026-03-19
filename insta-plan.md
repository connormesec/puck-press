# Instagram Per-Team Feature — Implementation Plan

## Goal

Extend Instagram import from a single-handle global feature to a per-team feature. Each team gets its own Instagram handle. The API key stays global. Posts imported for a team are tagged with that team's ID. The admin UI is redesigned around a team selector with per-team controls and test buttons.

---

## Design Decisions

| Question | Decision |
|----------|----------|
| Where is the API key stored? | Still global — `pp_insta_scraper_api_key` (unchanged) |
| Where are per-team handles stored? | WP options: `pp_team_{team_id}_insta_handle` |
| How is a team "enabled"? | Presence of a non-empty handle is sufficient — no separate per-team enable flag. Global `pp_enable_insta_post` remains the master on/off switch. |
| How are imported posts attributed? | New post meta `_pp_team_id` stored alongside `_insta_post_id` |
| How does duplicate detection scope? | Per-team — only check existing posts with matching `_pp_team_id` |
| What does cron do? | Loops all teams, skips those without a handle, imports for each |
| What is the active team in the UI? | Reuses `pp_admin_active_team_id` WP option (already used by teams tab) |

---

## Files to Create or Modify

### PHP — Core Importer

**`includes/instagram-post-importer/class-puck-press-instagram-post-importer.php`** — modify

Changes:
- Add `get_team_insta_handle(int $team_id): ?string`
- Add `save_team_insta_handle(int $team_id, string $handle): void`
- Modify `get_existing_insta_ids()` to accept `int $team_id` and filter by `_pp_team_id` post meta
- Modify `create_instagram_post()` to accept `int $team_id` and store it as `_pp_team_id` meta
- Add `run_for_team(int $team_id): array` — isolated import for one team (returns log messages)
- Modify `run_daily()` — loop all teams, call `run_for_team()` for each with a configured handle

### PHP — Admin Display

**`admin/components/insta-post-importer/instagram-post-admin-display.php`** — rewrite

New UI layout (see [UI Layout](#ui-layout) section below):
- Section 1: Global Settings — API key field + global master enable/disable + save button
- Section 2: Per-Team Settings — team selector dropdown + handle field + save handle button + per-team test buttons
- Remove the old single-handle field from global settings
- All AJAX methods updated to pass `team_id`

New AJAX methods on `Puck_Press_Admin_Instagram_Post_Importer_Display`:
- `ajax_save_team_handle()` — saves `pp_team_{team_id}_insta_handle`
- `ajax_get_team_example_posts()` — fetch-only for selected team; returns raw post data for display
- `ajax_create_team_insta_post()` — creates a single post from already-fetched data (insta_id, title, content, slug, image_buffer, team_id)

Remove old methods:
- `ajax_get_example_posts()` (replaced by team-scoped version)
- `ajax_get_example_posts_and_create()` (replaced — creation is now per-card, not bulk fetch+create)

### PHP — AJAX Registration

**`admin/class-puck-press-admin.php`** — modify `register_ajax_hooks()`

Remove:
```php
add_action( 'wp_ajax_pp_get_example_posts', ... );
add_action( 'wp_ajax_pp_get_example_posts_and_create', ... );
```

Add:
```php
add_action( 'wp_ajax_pp_save_team_insta_handle',   array( $instagram_display, 'ajax_save_team_handle' ) );
add_action( 'wp_ajax_pp_get_team_example_posts',   array( $instagram_display, 'ajax_get_team_example_posts' ) );
add_action( 'wp_ajax_pp_create_team_insta_post',   array( $instagram_display, 'ajax_create_team_insta_post' ) );
```

### JavaScript

**`admin/js/insta-post/puck-press-insta-post-admin.js`** — rewrite

New behavior:
- On team selector change → update the handle input field with the selected team's saved handle (from localized data); clear any displayed results
- "Save Handle" button → POST `pp_save_team_insta_handle` with `team_id` + `handle`
- "Get Example Posts" button → POST `pp_get_team_example_posts` with `team_id`; renders post cards and a "Create All" button
- Each post card has its own "Create Post" button → POST `pp_create_team_insta_post` with the full post data for that card; on success the button is replaced with "Created — Post #{id}"
- "Create All" button → iterates over all un-created cards and fires `pp_create_team_insta_post` sequentially for each

Localized data object (passed from admin display PHP via `wp_localize_script`):
```js
ppInstaPost = {
  nonce: '...',
  ajaxurl: '...',
  teams: [
    { id: 1, name: 'Bruins', handle: 'bruins_hockey' },
    { id: 2, name: 'Canucks', handle: '' },
    ...
  ]
}
```

This lets the JS populate the handle field immediately on team switch without an extra AJAX round-trip.

---

## UI Layout

```
┌─────────────────────────────────────────────────────────────────┐
│  Instagram Post Import                                          │
├─────────────────────────────────────────────────────────────────┤
│  GLOBAL SETTINGS                                                │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  [ ] Enable Instagram Post Import (master switch)          │ │
│  │  API Key: [__________________________________]             │ │
│  │                                          [Save Settings]   │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                 │
│  PER-TEAM SETTINGS                                              │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  Team:   [▼ Bruins                          ]              │ │
│  │  Handle: [@bruins_hockey___________________]  [Save Handle]│ │
│  │                                                            │ │
│  │  [Get Example Posts]                                       │ │
│  │                                                            │ │
│  │  — after fetch —                                           │ │
│  │  [Create All]                                              │ │
│  │                                                            │ │
│  │  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐       │ │
│  │  │ [img]        │ │ [img]        │ │ [img]        │       │ │
│  │  │ Title        │ │ Title        │ │ Title        │       │ │
│  │  │ Caption      │ │ Caption      │ │ Caption...   │       │ │
│  │  │ slug         │ │ slug         │ │ slug         │       │ │
│  │  │ [Create Post]│ │ [Create Post]│ │ ✓ Created #4 │       │ │
│  │  └──────────────┘ └──────────────┘ └──────────────┘       │ │
│  └────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

---

## Data Storage

### WP Options

| Option key | Description |
|------------|-------------|
| `pp_enable_insta_post` | Master enable/disable (unchanged) |
| `pp_insta_scraper_api_key` | Global Bearer token (unchanged) |
| `pp_team_{team_id}_insta_handle` | Instagram handle for a specific team |

The old `pp_insta_handle` option is **removed** from the save handler. It can be migrated (see [Migration](#migration)) or simply left in place and ignored.

### Post Meta on `pp_insta_post`

| Meta key | Description |
|----------|-------------|
| `_insta_post_id` | Original Instagram post ID (unchanged) |
| `_pp_team_id` | Team ID that imported this post (NEW) |

---

## Code Changes — Detailed

### 1. `Puck_Press_Instagram_Post_Importer`

```php
// NEW — get/save per-team handle
public static function get_team_insta_handle( int $team_id ): string {
    return (string) get_option( 'pp_team_' . $team_id . '_insta_handle', '' );
}

public static function save_team_insta_handle( int $team_id, string $handle ): void {
    update_option( 'pp_team_' . $team_id . '_insta_handle', sanitize_text_field( $handle ) );
}

// MODIFIED — scope duplicate detection to one team
public function get_existing_insta_ids( int $team_id, int $limit = -1 ): array {
    global $wpdb;
    // Query posts with matching _pp_team_id AND _insta_post_id
    $results = $wpdb->get_col( $wpdb->prepare(
        "SELECT pm1.meta_value FROM {$wpdb->postmeta} pm1
         INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
         WHERE pm1.meta_key = '_insta_post_id'
         AND pm2.meta_key = '_pp_team_id'
         AND pm2.meta_value = %d",
        $team_id
    ) );
    return array_unique( $results );
}

// MODIFIED — accept team_id, store as _pp_team_id meta
public function create_instagram_post(
    string $title,
    string $content,
    string $status,
    string $slug,
    string $b64_image,
    string $image_name,
    string $insta_id,
    int    $team_id       // NEW parameter
): int|WP_Error {
    // ... existing duplicate check, tag setup, wp_insert_post() ...
    update_post_meta( $post_id, '_insta_post_id', $insta_id );
    update_post_meta( $post_id, '_pp_team_id', $team_id );    // NEW
    // ... rest unchanged ...
}

// NEW — import for a single team, returns log messages
public function run_for_team( int $team_id ): array {
    $messages = array();
    $handle   = self::get_team_insta_handle( $team_id );
    if ( empty( $handle ) ) {
        return array( '[SKIP] Team ' . $team_id . ' has no Instagram handle configured.' );
    }
    $existing_ids = $this->get_existing_insta_ids( $team_id );
    $posts        = $this->fetch_instagram_posts( $existing_ids, $handle );
    if ( is_wp_error( $posts ) ) {
        $messages[] = '[ERROR] ' . $posts->get_error_message();
        return $messages;
    }
    foreach ( $posts as $post ) {
        $result = $this->create_instagram_post(
            $post['post_title'],
            $post['post_body'],
            'publish',
            $post['slug'],
            $post['image_buffer'],
            $post['insta_id'] . '.jpg',
            $post['insta_id'],
            $team_id           // pass team_id
        );
        if ( is_wp_error( $result ) ) {
            $messages[] = '[ERROR] ' . $result->get_error_message();
        } else {
            $messages[] = '[OK] Created post ID ' . $result . ' for handle @' . $handle;
        }
    }
    return $messages;
}

// MODIFIED — run_daily loops all teams
public function run_daily(): array {
    $messages   = array();
    $teams_util = new Puck_Press_Teams_Wpdb_Utils();
    $teams      = $teams_util->get_all_teams();
    if ( empty( $teams ) ) {
        return array( '[SKIP] No teams configured.' );
    }
    foreach ( $teams as $team ) {
        $team_messages = $this->run_for_team( (int) $team['id'] );
        $messages      = array_merge( $messages, $team_messages );
    }
    return $messages;
}
```

Note: `fetch_instagram_posts()` needs a small signature change to accept `$handle` as a parameter instead of always reading from the global option:

```php
// BEFORE
public function fetch_instagram_posts( array $existing_ids = array() ): array|WP_Error {
    $handle = get_option( 'pp_insta_handle', '' );
    ...
}

// AFTER
public function fetch_instagram_posts( array $existing_ids = array(), string $handle = '' ): array|WP_Error {
    if ( empty( $handle ) ) {
        $handle = get_option( 'pp_insta_handle', '' ); // fallback for legacy callers
    }
    ...
}
```

### 2. Admin Display PHP

`ajax_save_team_handle()`:
```php
public function ajax_save_team_handle(): void {
    check_ajax_referer( 'pp_insta_post_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }
    $team_id = isset( $_POST['team_id'] ) ? (int) $_POST['team_id'] : 0;
    $handle  = isset( $_POST['handle'] ) ? sanitize_text_field( wp_unslash( $_POST['handle'] ) ) : '';
    if ( $team_id <= 0 ) {
        wp_send_json_error( 'Invalid team ID.' );
    }
    Puck_Press_Instagram_Post_Importer::save_team_insta_handle( $team_id, $handle );
    wp_send_json_success( array( 'handle' => $handle ) );
}
```

`ajax_get_team_example_posts()`:
```php
public function ajax_get_team_example_posts(): void {
    check_ajax_referer( 'pp_insta_post_nonce', 'nonce' );
    $team_id  = isset( $_POST['team_id'] ) ? (int) $_POST['team_id'] : 0;
    $handle   = Puck_Press_Instagram_Post_Importer::get_team_insta_handle( $team_id );
    $importer = new Puck_Press_Instagram_Post_Importer();
    // Pass no existing IDs — we want to see everything the API returns,
    // even posts already imported, so the user can decide what to create.
    $posts = $importer->fetch_instagram_posts( array(), $handle );
    if ( is_wp_error( $posts ) ) {
        wp_send_json_error( $posts->get_error_message() );
    }
    wp_send_json_success( $posts );
}
```

`ajax_create_team_insta_post()`:
```php
public function ajax_create_team_insta_post(): void {
    check_ajax_referer( 'pp_insta_post_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }
    $team_id      = isset( $_POST['team_id'] )      ? (int) $_POST['team_id']                              : 0;
    $insta_id     = isset( $_POST['insta_id'] )     ? sanitize_text_field( wp_unslash( $_POST['insta_id'] ) )     : '';
    $title        = isset( $_POST['post_title'] )   ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) )   : '';
    $content      = isset( $_POST['post_body'] )    ? wp_kses_post( wp_unslash( $_POST['post_body'] ) )           : '';
    $slug         = isset( $_POST['slug'] )         ? sanitize_title( wp_unslash( $_POST['slug'] ) )              : '';
    $image_buffer = isset( $_POST['image_buffer'] ) ? sanitize_text_field( wp_unslash( $_POST['image_buffer'] ) ) : '';

    if ( $team_id <= 0 || empty( $insta_id ) ) {
        wp_send_json_error( 'Missing required fields.' );
    }

    $importer = new Puck_Press_Instagram_Post_Importer();
    $result   = $importer->create_instagram_post(
        $title,
        $content,
        'publish',
        $slug,
        $image_buffer,
        $insta_id . '.jpg',
        $insta_id,
        $team_id
    );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }
    wp_send_json_success( array( 'post_id' => $result ) );
}
```

Note: `ajax_get_team_example_posts()` intentionally passes an empty `$existing_ids` array so the user sees all posts the API returns — including those that may already be imported. The per-card "Create Post" button will hit `create_instagram_post()` which does its own duplicate check and returns a `WP_Error` if the post already exists; the JS surfaces that error on the card.

### 3. Localize Script

In the admin display `render()` method, build the teams array for JS:

```php
$teams_util   = new Puck_Press_Teams_Wpdb_Utils();
$all_teams    = $teams_util->get_all_teams();
$teams_for_js = array_map( function( $t ) {
    return array(
        'id'     => (int) $t['id'],
        'name'   => $t['name'],
        'handle' => Puck_Press_Instagram_Post_Importer::get_team_insta_handle( (int) $t['id'] ),
    );
}, $all_teams );

wp_localize_script( 'puck-press-insta-post-admin', 'ppInstaPost', array(
    'nonce'   => wp_create_nonce( 'pp_insta_post_nonce' ),
    'ajaxurl' => admin_url( 'admin-ajax.php' ),
    'teams'   => $teams_for_js,
) );
```

### 4. JavaScript

```js
// ppInstaPost.teams is an array of { id, name, handle }

const teamSelect  = document.getElementById('pp-team-selector');
const handleInput = document.getElementById('pp-team-insta-handle');
const resultsArea = document.getElementById('pp-insta-results');

// Build teams map for O(1) lookup
const teamsMap = {};
ppInstaPost.teams.forEach(t => { teamsMap[t.id] = t; });

// --- Team selector ---

teamSelect.addEventListener('change', () => {
  const team = teamsMap[teamSelect.value];
  handleInput.value = team ? team.handle : '';
  clearResults();
});

if (teamSelect.value && teamsMap[teamSelect.value]) {
  handleInput.value = teamsMap[teamSelect.value].handle;
}

// --- Save handle ---

$('#pp-save-team-handle').on('click', function () {
  $.post(ppInstaPost.ajaxurl, {
    action: 'pp_save_team_insta_handle',
    nonce:   ppInstaPost.nonce,
    team_id: teamSelect.value,
    handle:  handleInput.value.trim(),
  }, function (res) {
    if (res.success) {
      teamsMap[teamSelect.value].handle = res.data.handle;
      showMessage('Handle saved.');
    } else {
      showMessage('Error: ' + res.data, true);
    }
  });
});

// --- Fetch example posts ---

$('#pp-get-team-example-posts').on('click', function () {
  const btn = $(this).prop('disabled', true);
  clearResults();
  $.post(ppInstaPost.ajaxurl, {
    action: 'pp_get_team_example_posts',
    nonce:   ppInstaPost.nonce,
    team_id: teamSelect.value,
  }, function (res) {
    if (res.success) {
      renderPostCards(res.data);          // renders cards + "Create All" button
    } else {
      showMessage('Error: ' + res.data, true);
    }
  }).always(() => btn.prop('disabled', false));
});

// --- Render post cards ---

function renderPostCards(posts) {
  if (!posts || !posts.length) {
    showMessage('No new posts found.');
    return;
  }

  // "Create All" button
  const createAllBtn = $('<button class="button">Create All</button>');
  resultsArea.appendChild(createAllBtn[0]);

  // Post card grid
  const grid = $('<div class="pp-insta-grid"></div>');
  posts.forEach((post, i) => {
    const card = $(`
      <div class="pp-insta-card" data-index="${i}">
        <img src="data:image/jpeg;base64,${post.image_buffer}" alt="">
        <p class="pp-insta-title">${post.post_title}</p>
        <p class="pp-insta-body">${post.post_body}</p>
        <p class="pp-insta-slug">${post.slug}</p>
        <button class="button pp-create-post-btn">Create Post</button>
        <p class="pp-insta-status"></p>
      </div>
    `);
    card.find('.pp-create-post-btn').on('click', function () {
      createPost(post, $(this), card.find('.pp-insta-status'));
    });
    grid.append(card);
  });
  resultsArea.appendChild(grid[0]);

  // Wire up "Create All"
  createAllBtn.on('click', async function () {
    $(this).prop('disabled', true);
    const btns = grid.find('.pp-create-post-btn:not(:disabled)');
    for (const btn of btns.toArray()) {
      const card    = $(btn).closest('.pp-insta-card');
      const index   = parseInt(card.data('index'));
      const statusEl = card.find('.pp-insta-status');
      await createPost(posts[index], $(btn), statusEl);
    }
    $(this).prop('disabled', false);
  });
}

// --- Per-card create ---

function createPost(post, btn, statusEl) {
  return new Promise(resolve => {
    btn.prop('disabled', true).text('Creating...');
    $.post(ppInstaPost.ajaxurl, {
      action:       'pp_create_team_insta_post',
      nonce:        ppInstaPost.nonce,
      team_id:      teamSelect.value,
      insta_id:     post.insta_id,
      post_title:   post.post_title,
      post_body:    post.post_body,
      slug:         post.slug,
      image_buffer: post.image_buffer,
    }, function (res) {
      if (res.success) {
        btn.text('Created — Post #' + res.data.post_id).addClass('pp-created');
        statusEl.text('').removeClass('pp-error');
      } else {
        btn.prop('disabled', false).text('Create Post');
        statusEl.text('Error: ' + res.data).addClass('pp-error');
      }
    }).always(resolve);
  });
}

function clearResults() {
  $(resultsArea).empty();
}

function showMessage(msg, isError = false) {
  $(resultsArea).html(`<p class="${isError ? 'pp-error' : ''}">${msg}</p>`);
}
```

---

## Migration

The old single `pp_insta_handle` option is not deleted automatically. To migrate:

1. After deploying, open the admin Insta Post tab.
2. Select the team that the old handle belonged to.
3. Type in or paste the handle and click "Save Handle".
4. The old global option can then be left in place (ignored) or cleaned up via `delete_option( 'pp_insta_handle' )` in a one-time admin action.

No DB schema changes are required — all new data goes into WP options and post meta.

---

## Cron Behavior After Change

### The timeout problem

The external API is slow. If `run_daily()` calls it sequentially for each team, total execution time grows linearly with team count and will exceed the 60-second PHP timeout on sites with more than a handful of teams.

### Solution: async dispatch via non-blocking loopback

The cron job becomes a lightweight **dispatcher** — it fires one non-blocking HTTP loopback request per team and returns immediately. Each loopback request handles exactly one team's import in its own PHP process with its own 60-second clock.

```
cron fires
  └── run_daily()
        ├── dispatch_team_import(team_id=1)  ← non-blocking, returns instantly
        ├── dispatch_team_import(team_id=2)  ← non-blocking, returns instantly
        └── dispatch_team_import(team_id=3)  ← non-blocking, returns instantly

(meanwhile, in parallel)
  Team 1 process: run_for_team(1) → API call → create posts → log
  Team 2 process: run_for_team(2) → API call → create posts → log
  Team 3 process: run_for_team(3) → API call → create posts → log
```

### New AJAX action: `pp_run_team_insta_import`

A dedicated server-side-only AJAX action handles a single team's full import. It is secured with a secret key (not a nonce, since it is called server-to-server, not from the browser).

```php
// In register_ajax_hooks():
add_action( 'wp_ajax_nopriv_pp_run_team_insta_import', array( $instagram_display, 'ajax_run_team_insta_import' ) );
```

Handler:
```php
public function ajax_run_team_insta_import(): void {
    // Verify shared secret instead of nonce (no logged-in user context)
    $secret = isset( $_POST['secret'] ) ? sanitize_text_field( wp_unslash( $_POST['secret'] ) ) : '';
    if ( $secret !== get_option( 'pp_insta_loopback_secret' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }
    $team_id  = isset( $_POST['team_id'] ) ? (int) $_POST['team_id'] : 0;
    $importer = new Puck_Press_Instagram_Post_Importer();
    $messages = $importer->run_for_team( $team_id );
    // Log messages into per-team option so they can be reviewed
    update_option( 'pp_insta_team_' . $team_id . '_last_log', $messages );
    wp_send_json_success( array( 'messages' => $messages ) );
}
```

The loopback secret is a random string generated once on activation and stored in `pp_insta_loopback_secret`:
```php
// On plugin activation (class-puck-press-activator.php)
if ( ! get_option( 'pp_insta_loopback_secret' ) ) {
    update_option( 'pp_insta_loopback_secret', wp_generate_password( 32, false ) );
}
```

### Updated `run_daily()`

```php
public function run_daily(): array {
    $messages   = array( '[INFO] Instagram dispatcher started.' );
    $teams_util = new Puck_Press_Teams_Wpdb_Utils();
    $teams      = $teams_util->get_all_teams();
    $secret     = get_option( 'pp_insta_loopback_secret', '' );

    foreach ( $teams as $team ) {
        $team_id = (int) $team['id'];
        $handle  = Puck_Press_Instagram_Post_Importer::get_team_insta_handle( $team_id );
        if ( empty( $handle ) ) {
            $messages[] = '[SKIP] Team ' . $team_id . ' has no handle.';
            continue;
        }
        // Non-blocking loopback — returns immediately, import runs in its own process
        wp_remote_post(
            admin_url( 'admin-ajax.php' ),
            array(
                'timeout'   => 0.01,   // near-zero — fire and forget
                'blocking'  => false,
                'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
                'body'      => array(
                    'action'  => 'pp_run_team_insta_import',
                    'secret'  => $secret,
                    'team_id' => $team_id,
                ),
            )
        );
        $messages[] = '[DISPATCHED] Team ' . $team_id . ' (@' . $handle . ')';
    }
    return $messages;
}
```

### Failure tracking

Because each team's import runs in its own process, failure counts are tracked per-team:
- Option key: `puck_press_cron_failure_counts` becomes an associative array keyed by `insta_team_{team_id}`
- Alert email threshold (2 consecutive failures) and throttle (1 per 24 hours) apply independently per team
- Each team's last import log is stored in `pp_insta_team_{team_id}_last_log`

---

## Implementation Order

1. **`class-puck-press-activator.php`** — generate and store `pp_insta_loopback_secret` on activation if not already set
2. **`class-puck-press-instagram-post-importer.php`** — add `get_team_insta_handle()` / `save_team_insta_handle()`, update `get_existing_insta_ids()` signature, update `create_instagram_post()` to accept `$team_id`, update `fetch_instagram_posts()` signature, add `run_for_team()`, update `run_daily()` to dispatch non-blocking loopbacks
3. **`instagram-post-admin-display.php`** — rewrite `render()` HTML for new two-section layout, replace old AJAX methods with `ajax_save_team_handle()`, `ajax_get_team_example_posts()`, `ajax_create_team_insta_post()`, add `ajax_run_team_insta_import()`, update `wp_localize_script` call to include teams array
4. **`admin/class-puck-press-admin.php`** — remove old hook registrations, add the four new ones (including `wp_ajax_nopriv_pp_run_team_insta_import`)
5. **`puck-press-insta-post-admin.js`** — rewrite: team selector, save handle, fetch cards, per-card Create Post button, Create All button
6. Manual migration of the old `pp_insta_handle` value

Each step is independently testable: after step 2, `run_for_team()` is callable directly; after step 4, the dispatch loop can be triggered via cron test; after step 5, the full UI flow works end-to-end.

---

## What Is NOT Changing

- API key storage and form field (global, unchanged)
- The AWS Lambda endpoint and request/response format
- The `pp_insta_post` CPT registration, rewrite slug, REST API settings
- The image save pipeline (`save_base64_image_to_media()`)
- The `base64_to_img_tag_auto_mime()` helper
- The "Instagram" tag assignment on each imported post
- The `title_to_slug()` helper
- Cron scheduling infrastructure (hook name, schedule, failure email logic)
- The global master enable flag `pp_enable_insta_post`

---

## Todo List

### Phase 1 — Activation & Secret Generation ✅
- [x] In `class-puck-press-activator.php`, add a check: if `pp_insta_loopback_secret` option does not exist, generate it with `wp_generate_password( 32, false )` and store it via `update_option()`
- [x] Verify the secret persists across plugin deactivate/reactivate cycles (should not regenerate if already set)

### Phase 2 — Core Importer (`class-puck-press-instagram-post-importer.php`) ✅

#### Per-team handle helpers
- [x] Add static method `get_team_insta_handle( int $team_id ): string` — reads `pp_team_{team_id}_insta_handle` option, returns empty string if not set
- [x] Add static method `save_team_insta_handle( int $team_id, string $handle ): void` — sanitizes and writes the option

#### `fetch_instagram_posts()` signature
- [x] Add `string $handle = ''` parameter
- [x] If `$handle` is empty, fall back to reading the legacy `pp_insta_handle` global option so existing callers are not broken

#### `get_existing_insta_ids()` — scope to team
- [x] Add `int $team_id` as first parameter
- [x] Replace the current global meta query with a JOIN query: `_insta_post_id` WHERE `_pp_team_id = $team_id`
- [x] Remove the legacy slug-based fallback query (no longer needed now that all posts will have both meta keys)

#### `create_instagram_post()` — store team attribution
- [x] Add `int $team_id` as the final parameter
- [x] After `wp_insert_post()`, call `update_post_meta( $post_id, '_pp_team_id', $team_id )`
- [x] Ensure the existing `_insta_post_id` meta write is unaffected

#### `run_for_team()` — new method
- [x] Add `public function run_for_team( int $team_id ): array`
- [x] Read handle via `get_team_insta_handle()`; return a `[SKIP]` message and bail if empty
- [x] Call `get_existing_insta_ids( $team_id )` to build dedupe list
- [x] Call `fetch_instagram_posts( $existing_ids, $handle )`
- [x] Loop returned posts, call `create_instagram_post( ..., $team_id )` for each
- [x] Collect and return `[OK]` / `[ERROR]` log messages per post

#### `run_daily()` — become a dispatcher
- [x] Fetch all teams via `Puck_Press_Teams_Wpdb_Utils::get_all_teams()`
- [x] For each team: skip if no handle; otherwise call `wp_remote_post()` to `admin_url('admin-ajax.php')` with `blocking: false`, `timeout: 0.01`, action `pp_run_team_insta_import`, secret, team_id
- [x] Log `[DISPATCHED]` or `[SKIP]` per team and return the messages array

### Phase 3 — Admin Display (`instagram-post-admin-display.php`) ✅

#### HTML / render()
- [x] Remove the old single-handle input field from the global settings form
- [x] Keep the global settings section: master enable checkbox + API key field + Save Settings button
- [x] Add per-team settings section below global: team table with handle inputs, enable checkboxes, Save buttons per row
- [x] Add test section with team `<select>`, Fetch Posts button, and results grid
- [x] Pass `id`/`class` attributes that match what the JS expects

#### wp_localize_script()
- Note: handle data is available server-side in the table rows; JS reads values directly from inputs

#### `ajax_save_team_handle()` — new method
- [x] Verify nonce (`pp_insta_post_nonce`)
- [x] Read `team_id` (int), `handle` (sanitized string), `enabled` (0/1) from `$_POST`
- [x] Bail with `wp_send_json_error` if `team_id <= 0`
- [x] Save handle and enabled options
- [x] Return `wp_send_json_success`

#### `ajax_get_team_example_posts()` — replace old fetch method
- [x] Verify nonce
- [x] Read `team_id` from `$_POST`
- [x] Get handle from option
- [x] Call `fetch_instagram_posts( $existing_ids, $handle )` — filters already-imported posts
- [x] Return error or success with posts array

#### `ajax_create_team_insta_post()` — replace old create method
- [x] Verify nonce
- [x] Read and sanitize: `team_id`, `insta_id`, `post_title`, `post_body`, `slug`, `image_buffer` from `$_POST`
- [x] Bail if `team_id <= 0` or `insta_id` empty
- [x] Call `create_instagram_post()` with all fields + `team_id`
- [x] Return `wp_send_json_error` on `WP_Error`, or `wp_send_json_success( [ 'post_id' => $result ] )`

#### Loopback handler
- [x] Implemented as `Puck_Press_Instagram_Post_Importer::handle_loopback_team_import()` (static method on importer class)

#### Remove old methods
- [x] Deleted `ajax_get_example_posts()`
- [x] Deleted `ajax_get_example_posts_and_create()`

### Phase 4 — AJAX Hook Registration (`admin/class-puck-press-admin.php`) ✅

- [x] Remove `add_action( 'wp_ajax_pp_get_example_posts', ... )`
- [x] Remove `add_action( 'wp_ajax_pp_get_example_posts_and_create', ... )`
- [x] Add `add_action( 'wp_ajax_pp_save_team_handle', array( $insta_post_display, 'ajax_save_team_handle' ) )`
- [x] Add `add_action( 'wp_ajax_pp_get_team_example_posts', array( $insta_post_display, 'ajax_get_team_example_posts' ) )`
- [x] Add `add_action( 'wp_ajax_pp_create_team_insta_post', array( $insta_post_display, 'ajax_create_team_insta_post' ) )`
- [x] Add `add_action( 'wp_ajax_nopriv_pp_run_team_insta_import', array( 'Puck_Press_Instagram_Post_Importer', 'handle_loopback_team_import' ) )`
- [x] Add `add_action( 'wp_ajax_pp_run_team_insta_import', ... )` (logged-in fallback)

### Phase 5 — JavaScript (`admin/js/insta-post/puck-press-insta-post-admin.js`) ✅

#### Save handle (per-row in teams table)
- [x] Wire `.pp-save-team-handle` click → POST `pp_save_team_handle` with `team_id`, `handle`, `enabled`
- [x] On success: show "Saved" confirmation in row
- [x] On error: show error message in row

#### Get example posts
- [x] Wire `#pp-fetch-team-posts` click → disable button, clear grid, POST `pp_get_team_example_posts` with `team_id` from `#pp-test-team-select`
- [x] On success: call `buildPostCard()` per post, show container
- [x] On error: show error message
- [x] Always re-enable button on complete

#### buildPostCard()
- [x] Build card with image, title, caption, slug, insta ID
- [x] Append a "Create Post" button and status span per card
- [x] Bind button to `handleCreatePost()`

#### handleCreatePost()
- [x] Disable btn, set status to "Creating…"
- [x] POST `pp_create_team_insta_post` with all post fields + `team_id`
- [x] On success: show "Created (ID #)" in status, lock button to "Created"
- [x] On error: re-enable btn, show error in status

#### Utilities
- [x] `escapeHtml()` — sanitize user content before injecting into DOM

#### Cleanup
- [x] Removed all old button bindings (`#pp-get-example-posts`, `#pp-get-example-posts-and-create`)

### Phase 6 — Migration & Verification

- [ ] After deploying, open the Insta Post admin tab
- [ ] Select the team that owned the old handle; type the handle into the handle field and click Save Handle
- [ ] Verify the handle is saved by switching away and back to the team
- [ ] Click "Get Example Posts" and confirm cards render
- [ ] Click "Create Post" on one card; confirm the post appears in WP admin under Instagram
- [ ] Click "Create Post" again on the same card; confirm the duplicate-detection error appears on the card
- [ ] Trigger cron manually (WP Cron UI or `wp cron event run puck_press_cron_hook`); confirm loopback dispatch log shows `[DISPATCHED]` entries
- [ ] Confirm `pp_insta_team_{team_id}_last_log` option is written after the loopback completes
- [ ] Leave old `pp_insta_handle` option in place (ignore) — clean up only once confident the new system is stable
