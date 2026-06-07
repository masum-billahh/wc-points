<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCP_Admin {

    public static function init() {
        add_action( 'admin_menu',            array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_init',            array( __CLASS__, 'handle_save' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

        add_filter( 'manage_users_columns',       array( __CLASS__, 'add_users_column' ) );
        add_filter( 'manage_users_custom_column', array( __CLASS__, 'render_users_column' ), 10, 3 );

        add_action( 'wp_ajax_pcp_admin_adjust_points', array( __CLASS__, 'ajax_adjust_points' ) );
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private static function site_name() {
        return get_bloginfo('name');
    }

    // ── Menu ──────────────────────────────────────────────────────────

    public static function register_menu() {
        $site = self::site_name();

        add_menu_page(
            $site . ' Points',
            $site . ' Points',
            'manage_options',
            'pcp-points',
            array( __CLASS__, 'page_dashboard' ),
            'dashicons-star-filled',
            56
        );
        add_submenu_page( 'pcp-points', 'Dashboard',  'Dashboard',  'manage_options', 'pcp-points',    array( __CLASS__, 'page_dashboard' ) );
        add_submenu_page( 'pcp-points', 'Settings',   'Settings',   'manage_options', 'pcp-settings',  array( __CLASS__, 'page_settings' ) );
        add_submenu_page( 'pcp-points', 'Points Log', 'Points Log', 'manage_options', 'pcp-log',       array( __CLASS__, 'page_log' ) );
        add_submenu_page( 'pcp-points', 'Customers',  'Customers',  'manage_options', 'pcp-customers', array( __CLASS__, 'page_customers' ) );
    }

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'pcp' ) === false ) return;
        wp_enqueue_style(  'pcp-admin-style',  PCP_URL . 'admin/css/pcp-admin.css',  array(),          PCP_VERSION );
        wp_enqueue_script( 'pcp-admin-script', PCP_URL . 'admin/js/pcp-admin.js',   array('jquery'),  PCP_VERSION, true );
        wp_localize_script( 'pcp-admin-script', 'pcp_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pcp_admin_nonce'),
        ));
    }

    // ── Dashboard ─────────────────────────────────────────────────────

    public static function page_dashboard() {
        global $wpdb;
        $table = $wpdb->prefix . PCP_TABLE;
        $site  = self::site_name();

        $total_points_issued   = (int) $wpdb->get_var( "SELECT COALESCE(SUM(points),0) FROM {$table} WHERE points > 0" );
        $total_points_redeemed = abs( (int) $wpdb->get_var( "SELECT COALESCE(SUM(points),0) FROM {$table} WHERE points < 0 AND type='redeem'" ) );
        $total_users_with_pts  = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$table}" );
        $recent_log            = $wpdb->get_results( "SELECT l.*, u.user_login FROM {$table} l LEFT JOIN {$wpdb->users} u ON l.user_id=u.ID ORDER BY l.created_at DESC LIMIT 15" );
        ?>
        <div class="wrap pcp-admin-wrap">
            <h1>🏆 <?php echo esc_html($site); ?> Points — Dashboard</h1>
            <div class="pcp-stat-cards">
                <div class="pcp-stat-card">
                    <h3><?php echo number_format($total_points_issued); ?></h3>
                    <p>Total Points Issued</p>
                </div>
                <div class="pcp-stat-card">
                    <h3><?php echo number_format($total_points_redeemed); ?></h3>
                    <p>Total Points Redeemed</p>
                </div>
                <div class="pcp-stat-card">
                    <h3><?php echo number_format($total_users_with_pts); ?></h3>
                    <p>Customers with Points</p>
                </div>
            </div>

            <h2>Recent Activity</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th>Date</th><th>User</th><th>Points</th><th>Type</th><th>Description</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $recent_log as $row ) : ?>
                    <tr>
                        <td><?php echo esc_html($row->created_at); ?></td>
                        <td><?php echo esc_html($row->user_login); ?></td>
                        <td class="<?php echo $row->points > 0 ? 'pcp-positive' : 'pcp-negative'; ?>">
                            <?php echo ($row->points > 0 ? '+' : '') . $row->points; ?>
                        </td>
                        <td><span class="pcp-badge pcp-badge-<?php echo esc_attr($row->type); ?>"><?php echo esc_html($row->type); ?></span></td>
                        <td><?php echo esc_html($row->description); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // ── Settings ──────────────────────────────────────────────────────

    public static function handle_save() {
        if (
            isset( $_POST['pcp_save_settings'] ) &&
            check_admin_referer('pcp_save_settings') &&
            current_user_can('manage_options')
        ) {
            PCP_Settings::save_all( $_POST );
            add_settings_error( 'pcp_settings', 'pcp_saved', '✅ Settings saved!', 'updated' );
        }
    }

    public static function page_settings() {
        settings_errors('pcp_settings');
        $site = self::site_name();
        $g    = function( $k ) { return PCP_Settings::get( $k ); };
        ?>
        <div class="wrap pcp-admin-wrap">
            <h1>⚙️ <?php echo esc_html($site); ?> Points — Settings</h1>
            <form method="post">
                <?php wp_nonce_field('pcp_save_settings'); ?>
                <div class="pcp-settings-grid">

                    <!-- EARNING -->
                    <div class="pcp-settings-section">
                        <h2>🪙 Earning Points</h2>
                        <table class="form-table">
                            <tr>
                                <th>Points earned per spend</th>
                                <td>
                                    প্রতি <input type="number" name="taka_per_earn" value="<?php echo esc_attr($g('taka_per_earn')); ?>" min="1" step="1" class="small-text"> টাকায়
                                    <input type="number" name="points_per_taka" value="<?php echo esc_attr($g('points_per_taka')); ?>" min="0.1" step="0.1" class="small-text"> পয়েন্ট
                                    <p class="description">Default: প্রতি ১০ টাকায় ১ পয়েন্ট</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Signup Bonus Points</th>
                                <td>
                                    <input type="number" name="signup_bonus" value="<?php echo esc_attr($g('signup_bonus')); ?>" min="0" class="small-text"> পয়েন্ট
                                    <p class="description">New account creation bonus</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Review Points</th>
                                <td>
                                    <input type="number" name="review_points" value="<?php echo esc_attr($g('review_points')); ?>" min="0" class="small-text"> পয়েন্ট
                                    <p class="description">Points for leaving a product review</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- REFERRAL -->
                    <div class="pcp-settings-section">
                        <h2>🔗 Referral Points</h2>
                        <table class="form-table">
                            <tr>
                                <th>Referral Signup Points</th>
                                <td>
                                    <input type="number" name="referral_share_points" value="<?php echo esc_attr($g('referral_share_points')); ?>" min="0" class="small-text"> পয়েন্ট
                                    <p class="description">Referrer gets these when friend registers</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Referral Purchase Points</th>
                                <td>
                                    <input type="number" name="referral_purchase_points" value="<?php echo esc_attr($g('referral_purchase_points')); ?>" min="0" class="small-text"> পয়েন্ট
                                    <p class="description">Referrer gets these when friend makes first purchase</p>
                                </td>
                            </tr>
                            <tr>
                                <th>New Friend Welcome Points</th>
                                <td>
                                    <input type="number" name="referred_friend_points" value="<?php echo esc_attr($g('referred_friend_points')); ?>" min="0" class="small-text"> পয়েন্ট
                                    <p class="description">Bonus for the new user who joined via referral</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- REDEEMING -->
                    <div class="pcp-settings-section">
                        <h2>💸 Redeeming Points</h2>
                        <table class="form-table">
                            <tr>
                                <th>Points → Taka Rate</th>
                                <td>
                                    ১ পয়েন্ট = <input type="number" name="points_to_taka_rate" value="<?php echo esc_attr($g('points_to_taka_rate')); ?>" min="0.01" step="0.01" class="small-text"> টাকা
                                    <p class="description">Default: 1 point = 1 taka</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Max Redeem per Order</th>
                                <td>
                                    <input type="number" name="max_redeem_percent" value="<?php echo esc_attr($g('max_redeem_percent')); ?>" min="1" max="100" class="small-text"> % of order total
                                    <p class="description">Maximum % of order value payable by points</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- EXPIRY -->
                    <div class="pcp-settings-section">
                        <h2>⏳ Points Expiry</h2>
                        <table class="form-table">
                            <tr>
                                <th>Points Expiry (days)</th>
                                <td>
                                    <input type="number" name="points_expiry_days" value="<?php echo esc_attr($g('points_expiry_days')); ?>" min="0" class="small-text"> দিন
                                    <p class="description">0 = never expire. Default: 180 days</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- TIERS — thresholds -->
                    <div class="pcp-settings-section">
                        <h2>🏅 Tier Thresholds & Multipliers</h2>
                        <table class="form-table">
                            <tr>
                                <th>Pro Tier — Min Lifetime Points</th>
                                <td><input type="number" name="tier_pro_min" value="<?php echo esc_attr($g('tier_pro_min')); ?>" min="1" class="small-text"></td>
                            </tr>
                            <tr>
                                <th>Pro Tier — Earn Multiplier</th>
                                <td><input type="number" name="tier_pro_earn_multiplier" value="<?php echo esc_attr($g('tier_pro_earn_multiplier')); ?>" min="1" step="0.1" class="small-text">x</td>
                            </tr>
                            <tr>
                                <th>Legend Tier — Min Lifetime Points</th>
                                <td><input type="number" name="tier_legend_min" value="<?php echo esc_attr($g('tier_legend_min')); ?>" min="1" class="small-text"></td>
                            </tr>
                            <tr>
                                <th>Legend Tier — Earn Multiplier</th>
                                <td><input type="number" name="tier_legend_earn_multiplier" value="<?php echo esc_attr($g('tier_legend_earn_multiplier')); ?>" min="1" step="0.1" class="small-text">x</td>
                            </tr>
                            <tr>
                                <th>Legend Tier — Free Shipping</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="tier_legend_free_shipping" value="1" <?php checked(1, $g('tier_legend_free_shipping')); ?>>
                                        Enable free shipping for top tier
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- TIERS — names & icons -->
                    <div class="pcp-settings-section">
                        <h2>🎨 Tier Names &amp; Icons</h2>
                        <p class="description" style="margin:0 0 12px;">Customise each tier's display name and emoji icon.</p>
                        <table class="form-table">
                            <?php
                            $tier_rows = array(
                                'champ'  => 'Base Tier',
                                'pro'    => 'Mid Tier',
                                'legend' => 'Top Tier',
                            );
                            foreach ( $tier_rows as $slug => $label ) : ?>
                            <tr>
                                <th><?php echo esc_html($label); ?></th>
                                <td>
                                    <input type="text" name="tier_<?php echo $slug; ?>_icon"
                                           value="<?php echo esc_attr($g('tier_' . $slug . '_icon')); ?>"
                                           class="small-text" style="width:60px;" placeholder="🥉">
                                    <input type="text" name="tier_<?php echo $slug; ?>_name"
                                           value="<?php echo esc_attr($g('tier_' . $slug . '_name')); ?>"
                                           class="regular-text" placeholder="<?php echo esc_attr($label); ?>">
                                    <p class="description">Preview: <strong><?php echo esc_html($g('tier_' . $slug . '_icon') . ' ' . $g('tier_' . $slug . '_name')); ?></strong></p>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                    <!-- MISC -->
                    <div class="pcp-settings-section">
                        <h2>🔧 Misc</h2>
                        <table class="form-table">
                            <tr>
                                <th>Plugin Enabled</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="plugin_enabled" value="1" <?php checked(1, $g('plugin_enabled')); ?>>
                                        Enable the points system
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>

                </div><!-- .pcp-settings-grid -->
                <p><input type="submit" name="pcp_save_settings" class="button button-primary button-large" value="💾 Save Settings"></p>
            </form>
        </div>
        <?php
    }

    // ── Points Log ────────────────────────────────────────────────────

    public static function page_log() {
        global $wpdb;
        $table    = $wpdb->prefix . PCP_TABLE;
        $site     = self::site_name();
        $per_page = 30;
        $page     = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $offset   = ( $page - 1 ) * $per_page;

        $type_filter = sanitize_text_field( $_GET['type'] ?? '' );
        $user_filter = sanitize_text_field( $_GET['user'] ?? '' );

        // Build where clause and args separately for data query and count query
        $where      = '1=1';
        $where_args = array();

        if ( $type_filter ) {
            $where       .= ' AND l.type = %s';
            $where_args[] = $type_filter;
        }
        if ( $user_filter ) {
            $where       .= ' AND u.user_login LIKE %s';
            $where_args[] = '%' . $wpdb->esc_like( $user_filter ) . '%';
        }

        // Count query
        $count_sql = "SELECT COUNT(*) FROM {$table} l LEFT JOIN {$wpdb->users} u ON l.user_id=u.ID WHERE {$where}";
        $total     = (int) ( $where_args
            ? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$where_args ) )
            : $wpdb->get_var( $count_sql )
        );

        $pages = (int) ceil( $total / $per_page );

        // Data query
        $data_sql  = "SELECT l.*, u.user_login FROM {$table} l LEFT JOIN {$wpdb->users} u ON l.user_id=u.ID WHERE {$where} ORDER BY l.created_at DESC LIMIT %d OFFSET %d";
        $data_args = array_merge( $where_args, array( $per_page, $offset ) );
        $rows      = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$data_args ) );

        $types = $wpdb->get_col( "SELECT DISTINCT type FROM {$table} ORDER BY type" );
        ?>
        <div class="wrap pcp-admin-wrap">
            <h1>📋 <?php echo esc_html($site); ?> Points — Log</h1>
            <form method="get" style="margin-bottom:15px;display:flex;gap:10px;align-items:center;">
                <input type="hidden" name="page" value="pcp-log">
                <input type="text" name="user" placeholder="Filter by username" value="<?php echo esc_attr($user_filter); ?>" class="regular-text">
                <select name="type">
                    <option value="">All Types</option>
                    <?php foreach ( $types as $t ) : ?>
                        <option value="<?php echo esc_attr($t); ?>" <?php selected($t, $type_filter); ?>><?php echo esc_html($t); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" class="button" value="Filter">
                <?php if ( $type_filter || $user_filter ) : ?>
                    <a href="?page=pcp-log" class="button">Clear</a>
                <?php endif; ?>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th width="50">ID</th>
                    <th>Date</th>
                    <th>User</th>
                    <th width="80">Points</th>
                    <th width="120">Type</th>
                    <th>Description</th>
                    <th width="80">Order</th>
                    <th>Expires</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $rows as $row ) : ?>
                    <tr>
                        <td><?php echo $row->id; ?></td>
                        <td><?php echo esc_html( date('d M Y H:i', strtotime($row->created_at)) ); ?></td>
                        <td><a href="<?php echo get_edit_user_link($row->user_id); ?>"><?php echo esc_html($row->user_login); ?></a></td>
                        <td class="<?php echo $row->points > 0 ? 'pcp-positive' : 'pcp-negative'; ?>" style="font-weight:bold;">
                            <?php echo ($row->points > 0 ? '+' : '') . number_format($row->points); ?>
                        </td>
                        <td><span class="pcp-badge pcp-badge-<?php echo esc_attr($row->type); ?>"><?php echo esc_html($row->type); ?></span></td>
                        <td><?php echo esc_html($row->description); ?></td>
                        <td><?php echo $row->order_id ? '<a href="' . get_edit_post_link($row->order_id) . '">#' . $row->order_id . '</a>' : '—'; ?></td>
                        <td><?php echo $row->expires_at ? esc_html(date('d M Y', strtotime($row->expires_at))) : '∞'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ( $pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php echo paginate_links(array(
                        'base'    => add_query_arg('paged', '%#%'),
                        'format'  => '',
                        'current' => $page,
                        'total'   => $pages,
                    )); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── Customers ─────────────────────────────────────────────────────

    public static function page_customers() {
        global $wpdb;
        $table    = $wpdb->prefix . PCP_TABLE;
        $site     = self::site_name();
        $per_page = 20;
        $page     = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $offset   = ( $page - 1 ) * $per_page;
        $search   = sanitize_text_field( $_GET['s'] ?? '' );

        $now = current_time('mysql');

        // Build a consistent WHERE for both count and data
        if ( $search ) {
            $search_where = $wpdb->prepare(
                "WHERE (u.user_login LIKE %s OR u.user_email LIKE %s)",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        } else {
            $search_where = '';
        }

        // Count — match what the data query's HAVING clause does
        $count_sql = "
            SELECT COUNT(*) FROM (
                SELECT u.ID
                FROM {$wpdb->users} u
                LEFT JOIN {$table} l ON u.ID = l.user_id
                {$search_where}
                GROUP BY u.ID
                HAVING COALESCE(SUM(CASE WHEN l.points > 0 THEN l.points ELSE 0 END), 0) > 0
            ) AS sub";
        $total = (int) $wpdb->get_var( $count_sql );
        $pages = (int) ceil( $total / $per_page );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT u.ID, u.user_login, u.user_email,
                COALESCE(
                    SUM(CASE WHEN l.points > 0 AND (l.expires_at IS NULL OR l.expires_at > %s) THEN l.points ELSE 0 END)
                    + SUM(CASE WHEN l.points < 0 THEN l.points ELSE 0 END),
                0) AS balance,
                COALESCE(SUM(CASE WHEN l.points > 0 THEN l.points ELSE 0 END), 0) AS total_earned
             FROM {$wpdb->users} u
             LEFT JOIN {$table} l ON u.ID = l.user_id
             {$search_where}
             GROUP BY u.ID
             HAVING total_earned > 0
             ORDER BY balance DESC
             LIMIT %d OFFSET %d",
            $now, $per_page, $offset
        ));
        ?>
        <div class="wrap pcp-admin-wrap">
            <h1>👥 <?php echo esc_html($site); ?> Points — Customers</h1>
            <form method="get" style="margin-bottom:15px;display:flex;gap:10px;">
                <input type="hidden" name="page" value="pcp-customers">
                <input type="text" name="s" placeholder="Search username or email" value="<?php echo esc_attr($search); ?>" class="regular-text">
                <input type="submit" class="button" value="Search">
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Current Balance</th>
                    <th>Total Earned</th>
                    <th>Tier</th>
                    <th>Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $rows as $row ) :
                    $tier = PCP_Tiers::get_tier( $row->ID );
                ?>
                    <tr>
                        <td><a href="<?php echo get_edit_user_link($row->ID); ?>"><?php echo esc_html($row->user_login); ?></a></td>
                        <td><?php echo esc_html($row->user_email); ?></td>
                        <td><strong><?php echo number_format($row->balance); ?> pts</strong></td>
                        <td><?php echo number_format($row->total_earned); ?> pts</td>
                        <td><?php echo esc_html($tier['label']); ?></td>
                        <td>
                            <button class="button pcp-adjust-btn"
                                data-user="<?php echo $row->ID; ?>"
                                data-login="<?php echo esc_attr($row->user_login); ?>"
                                data-balance="<?php echo $row->balance; ?>">
                                ✏️ Adjust Points
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ( $pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php echo paginate_links(array(
                        'base'    => add_query_arg('paged', '%#%'),
                        'current' => $page,
                        'total'   => $pages,
                    )); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Adjust Modal -->
            <div id="pcp-adjust-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
                <div style="background:#fff;padding:30px;border-radius:8px;width:400px;max-width:95%;">
                    <h2>✏️ Adjust Points for <span id="pcp-modal-login"></span></h2>
                    <p>Current balance: <strong id="pcp-modal-balance"></strong> pts</p>
                    <label>Amount (use negative to deduct):</label><br>
                    <input type="number" id="pcp-modal-amount" placeholder="e.g. 100 or -50" style="width:100%;margin:8px 0;padding:8px;" class="regular-text"><br>
                    <label>Reason:</label><br>
                    <input type="text" id="pcp-modal-reason" placeholder="Admin adjustment reason" style="width:100%;margin:8px 0;padding:8px;" class="regular-text"><br>
                    <input type="hidden" id="pcp-modal-user-id">
                    <div style="margin-top:15px;display:flex;gap:10px;">
                        <button class="button button-primary" id="pcp-modal-save">Save</button>
                        <button class="button" id="pcp-modal-cancel">Cancel</button>
                    </div>
                    <p id="pcp-modal-msg" style="margin-top:10px;color:green;display:none;"></p>
                </div>
            </div>
        </div>
        <?php
    }

    // ── Users list column ─────────────────────────────────────────────

    public static function add_users_column( $columns ) {
        $columns['pcp_points'] = '🏆 Points';
        return $columns;
    }

    public static function render_users_column( $output, $column_name, $user_id ) {
        if ( $column_name !== 'pcp_points' ) return $output;
        $balance = PCP_Points::get_balance( $user_id );
        $tier    = PCP_Tiers::get_tier( $user_id );
        return "<strong>{$balance}</strong> pts <br><small>" . esc_html($tier['label']) . "</small>";
    }

    // ── AJAX: manual adjust ───────────────────────────────────────────

    public static function ajax_adjust_points() {
        check_ajax_referer( 'pcp_admin_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) wp_send_json_error();

        $user_id = (int) ( $_POST['user_id'] ?? 0 );
        $amount  = (int) ( $_POST['amount']  ?? 0 );
        $reason  = sanitize_text_field( $_POST['reason'] ?? 'Admin manual adjustment' );

        if ( ! $user_id || $amount == 0 ) {
            wp_send_json_error( array('message' => 'Invalid data') );
        }

        if ( $amount > 0 ) {
            PCP_Points::add_points( $user_id, $amount, 'admin', $reason );
        } else {
            PCP_Points::deduct_points( $user_id, absint($amount), 'admin', $reason );
        }

        $new_balance = PCP_Points::get_balance( $user_id );
        wp_send_json_success( array(
            'balance' => $new_balance,
            'message' => ( $amount > 0 ? "+{$amount}" : $amount ) . ' points applied. New balance: ' . $new_balance,
        ));
    }
}