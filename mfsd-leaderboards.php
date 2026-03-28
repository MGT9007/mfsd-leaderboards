<?php
/**
 * Plugin Name: MFSD Leaderboards
 * Description: Displays arcade game leaderboards in a cards/grid layout. Shows top 10 per game + current student's rank.
 * Version: 1.0.0
 * Author: MisterT9007
 * Requires Plugins: mfsd-arcade
 */

if (!defined('ABSPATH')) exit;

final class MFSD_Leaderboards {

    const VERSION = '1.0.0';

    public static function instance() {
        static $i = null;
        return $i ?: $i = new self();
    }

    private function __construct() {
        add_action('init', array($this, 'register_assets'));
        add_shortcode('mfsd_leaderboards', array($this, 'shortcode'));
    }

    /* ================================================================
       ASSETS
       ================================================================ */
    public function register_assets() {
        $base = plugin_dir_url(__FILE__);
        wp_register_style('mfsd-leaderboards',  $base . 'assets/leaderboards.css', array(), self::VERSION);
        wp_register_script('mfsd-leaderboards', $base . 'assets/leaderboards.js',  array(), self::VERSION, true);
    }

    /* ================================================================
       SHORTCODE — [mfsd_leaderboards]
       ================================================================ */
    public function shortcode($atts) {
        global $wpdb;

        $atts = shortcode_atts(array(
            'limit' => 10,   /* top N per game */
        ), $atts);

        $limit = max(1, min(50, (int) $atts['limit']));

        /* Check arcade tables exist */
        $games_table  = $wpdb->prefix . 'mfsd_arcade_games';
        $scores_table = $wpdb->prefix . 'mfsd_arcade_scores';

        if ($wpdb->get_var("SHOW TABLES LIKE '$games_table'") !== $games_table) {
            return '<p class="mfsd-lb-msg">Leaderboards are not available yet — the Arcade plugin is required.</p>';
        }

        /* Get all active games */
        $games = $wpdb->get_results(
            "SELECT id, title, slug, category, thumbnail_url
             FROM $games_table
             WHERE active = 1
             ORDER BY sort_order ASC, title ASC",
            ARRAY_A
        );

        if (empty($games)) {
            return '<p class="mfsd-lb-msg">No games available yet. Check back soon!</p>';
        }

        /* Current student */
        $student_id = is_user_logged_in() ? get_current_user_id() : 0;

        /* Build leaderboard data per game */
        $boards = array();
        foreach ($games as $game) {
            $slug = $game['slug'];

            /* Top N scores */
            $scores = $wpdb->get_results($wpdb->prepare(
                "SELECT initials, score, student_id, created_at
                 FROM $scores_table
                 WHERE game_slug = %s
                 ORDER BY score DESC, created_at ASC
                 LIMIT %d",
                $slug, $limit
            ), ARRAY_A);

            /* Total unique players */
            $player_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT student_id) FROM $scores_table WHERE game_slug = %s",
                $slug
            ));

            /* Current student's personal best + rank */
            $my_best = null;
            $my_rank = null;
            $in_top  = false;

            if ($student_id) {
                $my_best = $wpdb->get_row($wpdb->prepare(
                    "SELECT initials, score
                     FROM $scores_table
                     WHERE student_id = %d AND game_slug = %s
                     ORDER BY score DESC LIMIT 1",
                    $student_id, $slug
                ), ARRAY_A);

                if ($my_best) {
                    $my_rank = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT student_id) + 1
                         FROM $scores_table
                         WHERE game_slug = %s AND score > %d",
                        $slug, (int) $my_best['score']
                    ));

                    /* Check if student appears in top N */
                    foreach ($scores as $s) {
                        if ((int) $s['student_id'] === $student_id) {
                            $in_top = true;
                            break;
                        }
                    }
                }
            }

            $boards[] = array(
                'game'         => $game,
                'scores'       => $scores,
                'player_count' => $player_count,
                'my_best'      => $my_best,
                'my_rank'      => $my_rank,
                'in_top'       => $in_top,
            );
        }

        wp_enqueue_style('mfsd-leaderboards');
        wp_enqueue_script('mfsd-leaderboards');

        return $this->render($boards, $student_id, $limit);
    }

    /* ================================================================
       RENDER
       ================================================================ */
    private function render($boards, $student_id, $limit) {
        ob_start();
        ?>
        <div class="mfsd-lbs" id="mfsd-lbs-root">

            <div class="mfsd-lbs-header">
                <h2 class="mfsd-lbs-title">Leaderboards</h2>
                <p class="mfsd-lbs-subtitle">Top <?php echo (int) $limit; ?> players across all games</p>
            </div>

            <div class="mfsd-lbs-grid">
                <?php foreach ($boards as $b):
                    $game   = $b['game'];
                    $scores = $b['scores'];
                ?>
                <div class="mfsd-lbs-card">

                    <!-- Card header -->
                    <div class="mfsd-lbs-card-head">
                        <div class="mfsd-lbs-card-title-row">
                            <h3 class="mfsd-lbs-card-title"><?php echo esc_html($game['title']); ?></h3>
                            <span class="mfsd-lbs-card-cat"><?php echo esc_html(ucfirst($game['category'])); ?></span>
                        </div>
                        <span class="mfsd-lbs-card-players"><?php echo (int) $b['player_count']; ?> player<?php echo $b['player_count'] !== 1 ? 's' : ''; ?></span>
                    </div>

                    <!-- Scores table -->
                    <div class="mfsd-lbs-table-wrap">
                        <?php if (empty($scores)): ?>
                            <p class="mfsd-lbs-empty">No scores yet — be the first!</p>
                        <?php else: ?>
                            <table class="mfsd-lbs-table">
                                <thead>
                                    <tr>
                                        <th class="mfsd-lbs-th-rank">#</th>
                                        <th class="mfsd-lbs-th-name">Name</th>
                                        <th class="mfsd-lbs-th-score">Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($scores as $i => $s):
                                        $rank  = $i + 1;
                                        $is_me = $student_id && (int) $s['student_id'] === $student_id;
                                    ?>
                                    <tr class="<?php echo $is_me ? 'mfsd-lbs-me' : ''; ?>">
                                        <td class="mfsd-lbs-rank"><?php
                                            if ($rank === 1) echo '<span class="mfsd-lbs-medal mfsd-lbs-gold">🥇</span>';
                                            elseif ($rank === 2) echo '<span class="mfsd-lbs-medal mfsd-lbs-silver">🥈</span>';
                                            elseif ($rank === 3) echo '<span class="mfsd-lbs-medal mfsd-lbs-bronze">🥉</span>';
                                            else echo $rank;
                                        ?></td>
                                        <td class="mfsd-lbs-name"><?php echo esc_html($s['initials']); ?><?php if ($is_me) echo ' <span class="mfsd-lbs-you">YOU</span>'; ?></td>
                                        <td class="mfsd-lbs-score"><?php echo number_format((int) $s['score']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <!-- Student rank (if not in top N) -->
                    <?php if ($student_id && $b['my_best'] && !$b['in_top']): ?>
                    <div class="mfsd-lbs-my-rank">
                        <span class="mfsd-lbs-my-rank-label">Your rank</span>
                        <span class="mfsd-lbs-my-rank-value">#<?php echo (int) $b['my_rank']; ?></span>
                        <span class="mfsd-lbs-my-rank-score"><?php echo number_format((int) $b['my_best']['score']); ?> pts</span>
                    </div>
                    <?php elseif ($student_id && !$b['my_best']): ?>
                    <div class="mfsd-lbs-my-rank mfsd-lbs-no-score">
                        <span class="mfsd-lbs-my-rank-label">You haven't played yet — give it a go!</span>
                    </div>
                    <?php endif; ?>

                </div>
                <?php endforeach; ?>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }
}

MFSD_Leaderboards::instance();
