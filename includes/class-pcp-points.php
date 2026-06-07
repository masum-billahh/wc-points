<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCP_Points {

    public static function init() {
        // Award points when order completes
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'award_order_points' ) );

        // Auto-assign guest orders to existing account by billing email
        add_action( 'woocommerce_checkout_order_created',  array( __CLASS__, 'maybe_assign_order_to_user' ) );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'maybe_assign_order_to_user' ) );

        add_action( 'wp_enqueue_scripts',                 array( __CLASS__, 'enqueue_scripts' ) );
        add_action( 'transition_comment_status',          array( __CLASS__, 'maybe_award_review_points' ), 10, 3 );
        add_action( 'user_register',                      array( __CLASS__, 'award_signup_bonus' ) );

        // AJAX: paginated points history
        add_action( 'wp_ajax_pcp_get_history', array( __CLASS__, 'ajax_get_history' ) );
    }

    public static function enqueue_scripts() {
        wp_enqueue_style(  'pcp-style',  PCP_URL . 'public/css/pcp-style.css',  array(), PCP_VERSION );
        wp_enqueue_script( 'pcp-script', PCP_URL . 'public/js/pcp-script.js', array('jquery'), PCP_VERSION, true );
        wp_localize_script( 'pcp-script', 'pcp_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pcp_nonce'),
        ));
    }

    // ── Core CRUD ─────────────────────────────────────────────────────

    public static function add_points( $user_id, $points, $type, $description = '', $order_id = null ) {
        global $wpdb;
        $user_id = (int) $user_id;
        if ( ! $user_id || $points == 0 ) return false;

        $expiry_days = (int) PCP_Settings::get('points_expiry_days');
        $expires_at  = ( $expiry_days > 0 )
            ? date('Y-m-d H:i:s', strtotime("+{$expiry_days} days"))
            : null;

        $wpdb->insert(
            $wpdb->prefix . PCP_TABLE,
            array(
                'user_id'     => $user_id,
                'points'      => $points,
                'type'        => $type,
                'description' => $description,
                'order_id'    => $order_id ? (int) $order_id : null,
                'expires_at'  => $expires_at,
            ),
            array('%d','%d','%s','%s','%d','%s')
        );

        return $wpdb->insert_id;
    }

    public static function deduct_points( $user_id, $points, $type, $description = '', $order_id = null ) {
        $user_id = (int) $user_id;
        $points  = absint( $points );

        // Never deduct more than the current balance
        $balance = self::get_balance( $user_id );
        $points  = min( $points, $balance );

        if ( $points <= 0 ) return false;

        return self::add_points( $user_id, -$points, $type, $description, $order_id );
    }

    public static function get_balance( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . PCP_TABLE;
        $now   = current_time('mysql');

        $balance = $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(points),0) FROM {$table}
             WHERE user_id = %d AND (expires_at IS NULL OR expires_at > %s)",
            (int) $user_id, $now
        ));

        return max( 0, (int) $balance );
    }

    public static function get_history( $user_id, $limit = 10, $offset = 0 ) {
        global $wpdb;
        $table = $wpdb->prefix . PCP_TABLE;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            (int) $user_id, (int) $limit, (int) $offset
        ));
    }

    public static function get_history_count( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . PCP_TABLE;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
            (int) $user_id
        ));
    }

    // ── AJAX: paginated history ────────────────────────────────────────

    public static function ajax_get_history() {
        check_ajax_referer( 'pcp_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error();

        $user_id  = get_current_user_id();
        $per_page = 10;
        $page     = max( 1, (int) ( $_POST['page'] ?? 1 ) );
        $offset   = ( $page - 1 ) * $per_page;

        $rows  = self::get_history( $user_id, $per_page, $offset );
        $total = self::get_history_count( $user_id );
        $pages = (int) ceil( $total / $per_page );

        $html = '';
        foreach ( $rows as $row ) {
            $positive = $row->points > 0;
            $icon_cls = $positive ? 'pcp-history__icon--in' : 'pcp-history__icon--out';
            $pts_cls  = $positive ? 'pcp-pts--in' : 'pcp-pts--out';
            $icon_sym = $positive ? '+' : '−';
            $pts_str  = ( $positive ? '+' : '' ) . number_format( $row->points );
            $desc     = esc_html( $row->description ?: $row->type );
            $date     = date( 'd M Y', strtotime( $row->created_at ) );
            $expiry   = $row->expires_at ? ' · মেয়াদ: ' . date( 'd M Y', strtotime( $row->expires_at ) ) : '';

            $html .= "
            <div class='pcp-history__row'>
                <div class='pcp-history__icon {$icon_cls}'>{$icon_sym}</div>
                <div class='pcp-history__info'>
                    <strong>{$desc}</strong>
                    <small>{$date}{$expiry}</small>
                </div>
                <div class='pcp-history__pts {$pts_cls}'>{$pts_str}</div>
            </div>";
        }

        wp_send_json_success( array(
            'html'         => $html,
            'current_page' => $page,
            'total_pages'  => $pages,
            'total'        => $total,
        ));
    }

    // ── Earning triggers ──────────────────────────────────────────────

    /**
     * Auto-assign a guest order to an existing WP user account
     * that matches the billing email — before points are awarded.
     */
    public static function maybe_assign_order_to_user( $order ) {
        if ( ! $order instanceof WC_Abstract_Order ) {
            $order = wc_get_order( $order );
        }
        if ( ! $order ) return;

        // Already has a customer — nothing to do
        if ( $order->get_customer_id() ) return;

        $email = $order->get_billing_email();
        if ( ! $email ) return;

        $user = get_user_by( 'email', $email );
        if ( ! $user ) return;

        $order->set_customer_id( $user->ID );
        $order->save();
    }

    public static function award_order_points( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $user_id = $order->get_customer_id();
        if ( ! $user_id ) return;

        // HPOS-compatible guard — use order meta, not post meta
        if ( $order->get_meta( '_pcp_points_awarded' ) ) return;

        $total  = $order->get_subtotal();
        $rate   = (float) PCP_Settings::get('points_per_taka');
        $per    = (float) PCP_Settings::get('taka_per_earn');
        $points = (int) floor( ($total / $per) * $rate );

        $multiplier = PCP_Tiers::get_earn_multiplier( $user_id );
        $points     = (int) floor( $points * $multiplier );

        if ( $points > 0 ) {
            self::add_points(
                $user_id, $points, 'order',
                sprintf('Order #%d — earned %d points', $order_id, $points),
                $order_id
            );
            // HPOS-compatible meta save
            $order->update_meta_data( '_pcp_points_awarded', $points );
            $order->save();
        }

        PCP_Referral::handle_purchase( $user_id, $order_id );
    }

    /**
     * Award signup bonus.
     * Uses a meta guard so it's safe to call multiple times (hook + manual).
     */
    public static function award_signup_bonus( $user_id ) {
        $user_id = (int) $user_id;

        // Guard: only award once per user
        if ( get_user_meta( $user_id, '_pcp_signup_bonus_given', true ) ) return;
        update_user_meta( $user_id, '_pcp_signup_bonus_given', 1 );

        $bonus = (int) PCP_Settings::get('signup_bonus');
        if ( $bonus > 0 ) {
            self::add_points( $user_id, $bonus, 'signup', 'Account creation bonus' );
        }
    }

    /**
     * Award review points.
     * Fix: WooCommerce product reviews have comment_type = '' (empty),
     * not 'review'. Check post_type instead.
     */
    public static function maybe_award_review_points($new_status, $old_status, $comment) {

		// Only when it becomes approved
		if ($new_status !== 'approved') return;

		// WooCommerce product reviews only
		$post = get_post($comment->comment_post_ID);
		if (!$post || $post->post_type !== 'product') return;

		$user_id = (int) $comment->user_id;
		if (!$user_id) return;

		// prevent duplicates
		if (get_comment_meta($comment->comment_ID, '_pcp_review_points_awarded', true)) return;

		$pts = (int) PCP_Settings::get('review_points');
		if ($pts <= 0) return;

		self::add_points(
			$user_id,
			$pts,
			'review',
			'Product review points',
			$comment->comment_post_ID
		);

		update_comment_meta($comment->comment_ID, '_pcp_review_points_awarded', 1);
	}

    // ── Value helpers ─────────────────────────────────────────────────

    public static function points_to_taka( $points ) {
        $rate = (float) PCP_Settings::get('points_to_taka_rate');
        return $points * $rate;
    }

    public static function taka_to_points( $taka ) {
        $rate = (float) PCP_Settings::get('points_to_taka_rate');
        if ( $rate <= 0 ) return 0;
        return (int) floor( $taka / $rate );
    }
}