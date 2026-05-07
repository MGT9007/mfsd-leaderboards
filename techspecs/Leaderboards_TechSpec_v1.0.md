# MFSD Leaderboards — Technical Specification v1.0

**Plugin directory:** `mfsd-leaderboards/`
**Shortcode(s):** `[mfsd_leaderboards]` (optional `limit` attribute)
**Version:** 1.0.0
**Author:** MisterT9007
**Purpose:** Displays a card-grid leaderboard for every active arcade game. Each card shows the top N players (default 10, configurable via the `limit` shortcode attribute), a player count, medal icons for top-3, and — for logged-in students — their own personal best score and rank highlighted in the list or shown in a footer if they fall outside the top N. The plugin reads data exclusively from tables created by `mfsd-arcade` and adds no tables of its own.

---

## File Structure

| File | Purpose |
|------|---------|
| `mfsd-leaderboards.php` | Single-file plugin: singleton class, shortcode registration, asset registration, all DB queries, HTML rendering |
| `assets/leaderboards.css` | Dark gaming theme styles for the card grid, score table, medal icons, and student rank footer |
| `assets/leaderboards.js` | Minimal JS: staggered entrance animation for cards on page load |

---

## Database Schema

This plugin creates **no tables of its own**. It reads from two tables created by the `mfsd-arcade` plugin:

### wp_mfsd_arcade_games (read-only)

| Column | Used by leaderboards |
|--------|----------------------|
| `id` | JOIN key |
| `title` | Card heading |
| `slug` | Scores lookup key |
| `category` | Category badge on card |
| `thumbnail_url` | (selected but not rendered in current version) |
| `active` | Filtered to `active = 1` only |
| `sort_order` | Primary sort for card order |

### wp_mfsd_arcade_scores (read-only)

| Column | Used by leaderboards |
|--------|----------------------|
| `game_slug` | Filter by game |
| `student_id` | Identify current student's rows |
| `initials` | Display name in table |
| `score` | Ranking value |
| `created_at` | Tiebreaker (earlier date wins for equal scores) |

---

## Key Flows

### 1. Shortcode renders leaderboards

1. `[mfsd_leaderboards limit="10"]` is placed on a WordPress page. The `limit` attribute accepts 1–50 (default 10).
2. On shortcode execution, the plugin checks whether `wp_mfsd_arcade_games` exists. If not, a user-friendly error message is returned and no further queries are made.
3. All games with `active = 1` are fetched, ordered by `sort_order ASC, title ASC`.
4. If no games exist, a "No games available yet" message is returned.
5. For each game, three queries are run:
   - **Top N scores** for that `game_slug`, ordered `score DESC, created_at ASC` with `LIMIT $limit`.
   - **Unique player count** via `COUNT(DISTINCT student_id)`.
   - **Current student's personal best** (if logged in): highest score row for the current `student_id` and `game_slug`.
6. If a personal best exists, a fourth query calculates the student's rank: `COUNT(DISTINCT student_id) + 1 WHERE score > $my_best_score`.
7. A flag `$in_top` is set if the current student appears in the top-N result set.
8. All per-game data is assembled into a `$boards` array, then passed to `render()`.
9. CSS and JS assets are enqueued (registered earlier on `init`; enqueued only here, inside the shortcode, so they only load on pages with the shortcode).

### 2. HTML rendering

The `render()` method produces:

- An outer `.mfsd-lbs` container with CSS custom properties for the dark theme.
- A centred header showing "Leaderboards" and the limit ("Top 10 players across all games").
- A CSS Grid (`.mfsd-lbs-grid`, `auto-fill`, minimum 320px columns) of `.mfsd-lbs-card` elements.
- Each card:
  - **Header:** game title, capitalised category badge, player count.
  - **Table:** rank (medal emoji for positions 1–3, number otherwise), initials, score (number-formatted). The current student's row receives class `mfsd-lbs-me` (gold highlight + "YOU" badge).
  - **Footer:** if the student has a score but is outside the top N, shows `Your rank #N` and their personal best score. If the student has no score, shows "You haven't played yet — give it a go!".

### 3. Card entrance animation

On `DOMContentLoaded`, `leaderboards.js` finds all `.mfsd-lbs-card` elements and sets each to `opacity:0, translateY(12px)`, then uses `setTimeout` with an 80ms-per-card stagger to fade them in and translate to their natural position.

---

## AJAX / REST Endpoints

None. The plugin performs all data fetching server-side at shortcode render time. There are no AJAX handlers or REST routes.

---

## Admin Panel

None. This plugin has no admin settings page. Game data is managed by `mfsd-arcade`.

---

## SteveGPT Integration

Not applicable. This plugin does not call SteveGPT or the Anthropic Claude API.

---

## Assets

| File | Handle | Dependencies | When loaded |
|------|--------|-------------|-------------|
| `assets/leaderboards.css` | `mfsd-leaderboards` | None | Enqueued inside the shortcode callback when the shortcode is present |
| `assets/leaderboards.js` | `mfsd-leaderboards` | None | Enqueued inside the shortcode callback; loaded in footer (`true`) |

### `assets/leaderboards.css` — Design

Uses CSS custom properties scoped to `.mfsd-lbs`:

| Variable | Value | Purpose |
|---------|-------|---------|
| `--lb-bg` | `#0d1117` | Page background |
| `--lb-surface` | `#161b22` | Card background |
| `--lb-border` | `#30363d` | Card borders |
| `--lb-accent` | `#58a6ff` | Blue highlight, "YOU" badge |
| `--lb-gold` | `#fbbf24` | Score text, current student row |
| `--lb-silver` | `#c0c0c0` | Silver medal |
| `--lb-bronze` | `#cd7f32` | Bronze medal |

Cards have hover lift (`translateY(-2px)`) and border-color transition to accent. Responsive: single-column below 700px.

---

## Security

| Check | Where |
|-------|-------|
| `if (!defined('ABSPATH')) exit` | Top of main file |
| `$wpdb->prepare()` | All queries using `$slug`, `$limit`, `$student_id`, `$my_best['score']` |
| `esc_html()` | All user-facing string output (game title, category, initials) |
| `(int)` cast | All numeric values (scores, ranks, player counts, student ID) |
| `number_format()` | Scores displayed to users |
| `max(1, min(50, (int)))` | `limit` attribute validation |
| `is_user_logged_in()` | Personal best and rank queries only run when `$student_id > 0` |
| `SHOW TABLES LIKE` guard | Returns early with a safe message if arcade table does not exist |

No nonce is required because this is a read-only display shortcode with no state-changing operations.

---

## Inter-Plugin Dependencies

| Dependency | Type | Notes |
|-----------|------|-------|
| `mfsd-arcade` | Required | Must be active and have created `wp_mfsd_arcade_games` and `wp_mfsd_arcade_scores`. The leaderboard plugin checks for the games table at render time and displays a graceful error if absent. Declared as `Requires Plugins: mfsd-arcade` in the plugin header. |

---

## Version History

| Version | Changes |
|---------|---------|
| 1.0.0 | Initial release. Card grid leaderboard, top-N per game, personal rank, staggered card animations, dark gaming theme |
