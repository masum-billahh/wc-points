<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCP_Points {

    public static function init() {
        // Award points when order completes
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'award_order_points' ) );

        // Enqueue frontend assets
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

        // Show points balance in My Account
        add_action( 'woocommerce_before_my_account', array( __CLASS__, 'display_points_dashboard' ) );

        // Handle photo review points (WooCommerce native reviews)
        add_action( 'comment_post', array( __CLASS__, 'maybe_award_review_points' ), 10, 2 );

        // Handle account registration bonus
        add_action( 'user_register', array( __CLASS__, 'award_signup_bonus' ) );
    }

    public static function enqueue_scripts() {
        wp_enqueue_style( 'pcp-style', PCP_URL . 'public/css/pcp-style.css', array(), PCP_VERSION );
        wp_enqueue_script( 'pcp-script', PCP_URL . 'public/js/pcp-script.js', array( 'jquery' ), PCP_VERSION, true );
        wp_localize_script( 'pcp-script', 'pcp_data', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'pcp_nonce' ),
        ));
    }

    // -------------------------------------------------------------------------
    // Core CRUD
    // -------------------------------------------------------------------------

    public static function add_points( $user_id, $points, $type, $description = '', $order_id = null ) {
        global $wpdb;
        if ( ! $user_id || $points == 0 ) return false;

        $expiry_days = (int) PCP_Settings::get( 'points_expiry_days' );
        $expires_at  = ( $expiry_days > 0 )
            ? date( 'Y-m-d H:i:s', strtotime( "+{$expiry_days} days" ) )
            : null;

        $wpdb->insert(
            $wpdb->prefix . PCP_TABLE,
            array(
                'user_id'     => $user_id,
                'points'      => $points,
                'type'        => $type,
                'description' => $description,
                'order_id'    => $order_id,
                'expires_at'  => $expires_at,
            ),
            array( '%d', '%d', '%s', '%s', '%d', '%s' )
        );

        return $wpdb->insert_id;
    }

    public static function deduct_points( $user_id, $points, $type, $description = '', $order_id = null ) {
        return self::add_points( $user_id, -absint( $points ), $type, $description, $order_id );
    }

    public static function get_balance( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . PCP_TABLE;
        $now   = current_time( 'mysql' );

        $balance = $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0) FROM {$table}
             WHERE user_id = %d
               AND (expires_at IS NULL OR expires_at > %s)",
            $user_id, $now
        ));

        return max( 0, (int) $balance );
    }

    public static function get_history( $user_id, $limit = 20 ) {
        global $wpdb;
        $table = $wpdb->prefix . PCP_TABLE;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id, $limit
        ));
    }

    // -------------------------------------------------------------------------
    // Earning triggers
    // -------------------------------------------------------------------------

    public static function award_order_points( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $user_id = $order->get_customer_id();
        if ( ! $user_id ) return;

        // Prevent double-awarding
        if ( get_post_meta( $order_id, '_pcp_points_awarded', true ) ) return;

        $total  = $order->get_subtotal(); // before discounts — adjust if needed
        $rate   = (float) PCP_Settings::get( 'points_per_taka' );
        $per    = (float) PCP_Settings::get( 'taka_per_earn' );
        $points = (int) floor( ( $total / $per ) * $rate );

        // Apply tier multiplier
        $multiplier = PCP_Tiers::get_earn_multiplier( $user_id );
        $points     = (int) floor( $points * $multiplier );

        if ( $points > 0 ) {
            self::add_points(
                $user_id,
                $points,
                'order',
                sprintf( 'Order #%d — earned %d points', $order_id, $points ),
                $order_id
            );
            update_post_meta( $order_id, '_pcp_points_awarded', $points );
        }

        // Trigger referral purchase reward
        PCP_Referral::handle_purchase( $user_id, $order_id );
    }

    public static function award_signup_bonus( $user_id ) {
        $bonus = (int) PCP_Settings::get( 'signup_bonus' );
        if ( $bonus > 0 ) {
            self::add_points( $user_id, $bonus, 'signup', 'Account creation bonus' );
        }
    }

    public static function maybe_award_review_points( $comment_id, $comment_approved ) {
        if ( ! $comment_approved ) return;

        $comment = get_comment( $comment_id );
        if ( ! $comment || $comment->comment_type !== 'review' ) return;

        // Check if review has an image (stored as meta by most themes/plugins)
        // We simply award for any approved product review; adjust logic if you track images separately
        $user_id = $comment->user_id;
        if ( ! $user_id ) return;

        // Prevent duplicate review points for the same comment
        if ( get_comment_meta( $comment_id, '_pcp_review_points_awarded', true ) ) return;

        $pts = (int) PCP_Settings::get( 'review_points' );
        if ( $pts > 0 ) {
            self::add_points( $user_id, $pts, 'review', 'Product review points', null );
            update_comment_meta( $comment_id, '_pcp_review_points_awarded', 1 );
        }
    }

    // -------------------------------------------------------------------------
    // Points value helpers
    // -------------------------------------------------------------------------

    public static function points_to_taka( $points ) {
        $rate = (float) PCP_Settings::get( 'points_to_taka_rate' );
        return $points * $rate;
    }

    public static function taka_to_points( $taka ) {
        $rate = (float) PCP_Settings::get( 'points_to_taka_rate' );
        if ( $rate <= 0 ) return 0;
        return (int) floor( $taka / $rate );
    }

    // -------------------------------------------------------------------------
    // My Account dashboard widget
    // -------------------------------------------------------------------------

    public static function display_points_dashboard() {
        if ( ! is_user_logged_in() ) return;
        $user_id = get_current_user_id();
        $balance = self::get_balance( $user_id );
        $taka    = self::points_to_taka( $balance );
        $tier    = PCP_Tiers::get_tier( $user_id );
        $history = self::get_history( $user_id, 10 );
        $ref_url = PCP_Referral::get_referral_url( $user_id );

        include PCP_PATH . 'public/templates/my-account-dashboard.php';
    }
}