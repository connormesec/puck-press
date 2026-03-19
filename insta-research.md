# Instagram Post Import Feature — Research Notes

## Overview

The Instagram import feature pulls posts from an Instagram account via a third-party AWS Lambda proxy, converts them into `pp_insta_post` WordPress custom post type entries, and saves their images to the media library. It runs on a cron schedule and also exposes manual test actions in the admin UI.

---

## Files Involved

| File | Role |
|------|------|
| `includes/instagram-post-importer/class-puck-press-instagram-post-importer.php` | Core importer: fetch, deduplicate, create posts, save images |
| `admin/components/insta-post-importer/instagram-post-admin-display.php` | Admin settings page UI and AJAX handlers |
| `admin/js/insta-post/puck-press-insta-post-admin.js` | Client-side jQuery for the two test buttons |
| `admin/class-puck-press-admin.php` | Registers AJAX hooks (`pp_get_example_posts`, `pp_get_example_posts_and_create`) |
| `includes/class-puck-press-cron.php` | Calls `run_daily()` on the configured cron schedule |
| `includes/class-puck-press.php` | Registers `pp_insta_post` CPT and sets up rewrite slug `instagram` |
| `includes/class-puck-press-activator.php` | Re-registers CPT on plugin activation |

---

## Data Model

### WordPress Options (settings)

| Option key | Description |
|------------|-------------|
| `pp_enable_insta_post` | `1`/`0` — feature on/off toggle |
| `pp_insta_scraper_api_key` | Bearer token for the AWS Lambda API |
| `pp_insta_handle` | Instagram username to import from |

### Custom Post Type: `pp_insta_post`

- **Slug / archive URL:** `/instagram/`
- **Single post URL:** `/instagram/{post-name}/`
- **Supports:** title, editor, thumbnail, custom-fields
- **REST API:** enabled (`show_in_rest: true`)
- **Post meta:** `_insta_post_id` — stores the original Instagram post ID (used for duplicate detection)
- **Taxonomy:** Tagged with the `instagram` term in `post_tag` on every import

---

## External API

- **Endpoint:** `https://8qoqtj3pm0.execute-api.us-east-2.amazonaws.com/default/getWpPostFromInstaAPI`
- **Method:** POST, timeout 30s
- **Auth:** `Authorization: Bearer {pp_insta_scraper_api_key}`
- **Request body:**
  ```json
  {
    "instagram_handle": "team_handle",
    "existing_post_ids": ["123", "456"]
  }
  ```
- **Response:**
  ```json
  {
    "new_posts": [
      {
        "post_id":            "123456",
        "postSlug":           "some-slug",
        "postTitle":          "Caption title",
        "postText":           "Full caption text",
        "imgSrc":             "https://...",
        "featuredImageBuffer": "<base64 string>"
      }
    ]
  }
  ```

The `existing_post_ids` list is sent so the Lambda can filter out already-imported posts server-side. The plugin also does client-side deduplication as a second pass.

---

## Import Flow (End-to-End)

### Automatic (cron)

1. `Puck_Press_Cron::run_cron_task()` fires on the configured schedule (default: `twicedaily`).
2. Checks `pp_enable_insta_post` option. If disabled, skips.
3. Instantiates `Puck_Press_Instagram_Post_Importer` and calls `run_daily()`.
4. `run_daily()` calls `get_existing_insta_ids()` to build the dedupe list, then `fetch_instagram_posts($existing_ids)`.
5. For each returned post, calls `create_instagram_post(...)`.
6. Messages (success/error) are logged to `puck_press_cron_last_log` option.
7. After 2+ consecutive failures the cron emails the site admin (throttled: max once per 24 hours). Failure counts stored in `puck_press_cron_failure_counts`.

### Manual (admin UI)

- **"Get Example Posts"** → `wp_ajax_pp_get_example_posts` → `fetch_instagram_posts()` only, no post creation. Returns JSON array for display.
- **"Get Example Posts And Create Posts"** → `wp_ajax_pp_get_example_posts_and_create` → fetch + create for each post. Returns `{ successful_imports: [...], failed_imports: [...] }`.

---

## Duplicate Detection

### `get_existing_insta_ids()`

Two sources are combined:

1. **Post meta query** — finds all `pp_insta_post` posts (non-trash) that have `_insta_post_id` meta set.
2. **Legacy slug query** — finds older Instagram-tagged posts by slug pattern (for backward compatibility with posts created before meta was used).

The union is deduplicated and sent to the API so the Lambda skips those posts.

### Inside `create_instagram_post()`

Before inserting, the method does a second local check:
- Exact `in_array()` match on Instagram ID.
- `preg_grep()` regex check on existing slugs to catch slug variations like `{slug}-123456`.

---

## Post Creation: `create_instagram_post()`

1. **Duplicate check** — bail if Instagram ID already imported.
2. **Tag setup** — ensure the `instagram` `post_tag` term exists; create it if not.
3. **Slug generation** — `sanitize_title()` the provided slug, truncate to 60 chars at word boundary; append Instagram ID if a slug collision exists.
4. **`wp_insert_post()`** — status passed in (`publish` for cron, configurable).
5. **`update_post_meta( $post_id, '_insta_post_id', $insta_id )`**
6. **`wp_set_post_tags()`** with the `instagram` tag.
7. **`save_base64_image_to_media()`** — decode base64, write file to uploads, create attachment, generate thumbnails, set as featured image via `set_post_thumbnail()`.

---

## Image Handling: `save_base64_image_to_media()`

- Strips data-URI prefix if present.
- `base64_decode()` → write to `wp_upload_dir()` path.
- `wp_check_filetype()` validates the extension.
- `wp_insert_attachment()` creates the media library entry.
- `wp_generate_attachment_metadata()` + `wp_update_attachment_metadata()` generate thumbnails.
- Returns attachment ID (or `WP_Error`).

### `base64_to_img_tag_auto_mime()`

Helper for admin preview only (not used in post creation). Detects MIME type from base64 header bytes (PNG, JPEG, GIF; defaults to PNG) and returns an inline `<img>` data-URI tag.

---

## Admin UI

**Location:** Puck Press admin → "Insta Post" tab

### Settings form

- Enable/disable checkbox
- API key text field
- Instagram handle text field
- Save button

### Test section

- **"Get Example Posts"** — previews posts in a 3-column grid without saving anything.
- **"Get Example Posts And Create Posts"** — imports posts and shows a success/failure breakdown.

### Post preview grid

Each card shows: featured image (via base64 data URI), title, caption, slug, and (if created) the new post ID. Red text = failed; green text = successful.

---

## AJAX Endpoints

| Action | Handler class | Handler method | Nonce |
|--------|---------------|----------------|-------|
| `pp_get_example_posts` | `Puck_Press_Admin_Instagram_Post_Importer_Display` | `ajax_get_example_posts()` | `pp_insta_post_nonce` |
| `pp_get_example_posts_and_create` | `Puck_Press_Admin_Instagram_Post_Importer_Display` | `ajax_get_example_posts_and_create()` | `pp_insta_post_nonce` |

Both are registered in `Puck_Press_Admin::register_ajax_hooks()`.

---

## JavaScript (`puck-press-insta-post-admin.js`)

Two jQuery click handlers:

- `#pp-get-example-posts` → POST to `ajaxurl` with action `pp_get_example_posts` + nonce. On success, renders post cards via `renderPostCard()`.
- `#pp-get-example-posts-and-create` → POST to `ajaxurl` with action `pp_get_example_posts_and_create` + nonce. On success, renders successful and failed import cards.

Both buttons are disabled during the request and re-enabled on completion.

Data localized via `ppInstaPost` object: `{ nonce, ajaxurl }`.

---

## Cron Details

| Property | Value |
|----------|-------|
| Hook | `puck_press_cron_hook` |
| Default schedule | `twicedaily` |
| Enable option | `puck_press_cron_enabled` |
| Log option | `puck_press_cron_last_log` |
| Failure count option | `puck_press_cron_failure_counts` |
| Alert threshold | 2 consecutive failures |
| Alert throttle | 1 email per 24 hours |

---

## Helper Methods (quick reference)

| Method | Purpose |
|--------|---------|
| `title_to_slug($title)` | Sanitize + truncate title to a URL slug (max 60 chars) |
| `base64_to_img_tag_auto_mime($b64, $alt, $class)` | Build inline `<img>` data-URI for admin preview |
| `save_base64_image_to_media($b64, $filename, $post_id)` | Decode + save image to WP media library, return attachment ID |
| `get_existing_insta_ids($limit)` | Return all known Instagram IDs (meta + legacy slugs) for dedupe |

---

## Frontend Access

- **Archive:** `/instagram/` — standard WP archive template for `pp_insta_post`
- **Single:** `/instagram/{post-name}/` — standard WP single post template
- No dedicated shortcode; no custom template manager class (unlike schedule/roster/stats modules)
- Posts are query-able via `WP_Query` or the REST API

---

## Notable Design Decisions

- The AWS Lambda proxy handles the actual Instagram scraping; the plugin only communicates with that proxy.
- Deduplication is two-layered: IDs sent to the API pre-filter, then local checks guard against race conditions or stale ID lists.
- Images are stored as proper WP attachments (not hotlinked), so they remain available if the source URL changes.
- There is no dedicated frontend template class for Instagram — unlike other modules (schedule, roster, stats), it relies entirely on the WordPress theme's archive/single templates.
- The feature can be fully disabled without losing configuration, since the enable flag is separate from the API key and handle.
