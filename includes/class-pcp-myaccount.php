<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCP_MyAccount {

    public static function init() {
        add_filter( 'woocommerce_account_menu_items',        array( __CLASS__, 'add_menu_item' ), 40 );
        add_action( 'woocommerce_account_pcp-points_endpoint', array( __CLASS__, 'render_page' ) );
        add_action( 'init',                                  array( __CLASS__, 'register_endpoint' ) );
    }

    public static function register_endpoint() {
        add_rewrite_endpoint( 'pcp-points', EP_ROOT | EP_PAGES );
    }

    public static function add_menu_item( $items ) {
        // Insert before logout
        $logout = $items['customer-logout'] ?? null;
        unset( $items['customer-logout'] );
        $items['pcp-points'] = '🏆 আমার পয়েন্ট';
        if ( $logout ) $items['customer-logout'] = $logout;
        return $items;
    }

    public static function render_page() {
        $user_id = get_current_user_id();
        $balance = PCP_Points::get_balance( $user_id );
        $taka    = PCP_Points::points_to_taka( $balance );
        $tier    = PCP_Tiers::get_tier( $user_id );
        $history = PCP_Points::get_history( $user_id, 20 );
        $ref_url = PCP_Referral::get_referral_url( $user_id );

        include PCP_PATH . 'public/templates/my-account-points.php';
    }
}