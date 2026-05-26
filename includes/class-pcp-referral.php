<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCP_Referral {

    public static function init() {
        // Capture referral code from URL on every page load
        add_action( 'init', array( __CLASS__, 'capture_referral_code' ) );

        // When a new user registers, link them to referrer
        add_action( 'user_register', array( __CLASS__, 'link_referral_on_register' ), 20 );

        // Add referral link to My Account
        add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'show_referral_link' ) );
    }

    // -------------------------------------------------------------------------
    // URL helpers
    // -------------------------------------------------------------------------

    public static function get_referral_code( $user_id ) {
        $code = get_user_meta( $user_id, '_pcp_referral_code', true );
        if ( ! $code ) {
            $code = 'PC' . strtoupper( substr( md5( $user_id . AUTH_KEY ), 0, 8 ) );
            update_user_meta( $user_id, '_pcp_referral_code', $code );
        }
        return $code;
    }

    public static function get_referral_url( $user_id ) {
        return add_query_arg( 'ref', self::get_referral_code( $user_id ), home_url( '/' ) );
    }

    // -------------------------------------------------------------------------
    // Capture & store referral code in session / cookie
    // -------------------------------------------------------------------------

    public static function capture_referral_code() {
        if ( ! empty( $_GET['ref'] ) ) {
            $code = sanitize_text_field( $_GET['ref'] );
            // Store in cookie for 30 days
            if ( ! headers_sent() ) {
                setcookie( 'pcp_ref', $code, time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
            }
            $_COOKIE['pcp_ref'] = $code;
        }
    }

    // -------------------------------------------------------------------------
    // Link new user to referrer when they register
    // -------------------------------------------------------------------------

    public static function link_referral_on_register( $new_user_id ) {
        $code = isset( $_COOKIE['pcp_ref'] ) ? sanitize_text_field( $_COOKIE['pcp_ref'] ) : '';
        if ( ! $code ) return;

        // Find the referrer by code
        $referrer_id = self::get_user_by_referral_code( $code );
        if ( ! $referrer_id || $referrer_id == $new_user_id ) return;

        // Link new user to referrer
        update_user_meta( $new_user_id, '_pcp_referred_by', $referrer_id );

        // Award referrer small points just for the referral registration (configurable)
        $share_pts = (int) PCP_Settings::get( 'referral_share_points' );
        if ( $share_pts > 0 ) {
            PCP_Points::add_points(
                $referrer_id,
                $share_pts,
                'referral_signup',
                sprintf( 'Referral signup bonus — user #%d registered', $new_user_id )
            );
        }

        // Award new friend a bonus for using a referral link
        $friend_pts = (int) PCP_Settings::get( 'referred_friend_points' );
        if ( $friend_pts > 0 ) {
            PCP_Points::add_points(
                $new_user_id,
                $friend_pts,
                'referred_friend',
                'Welcome bonus — you joined via a referral link!'
            );
        }
    }

    // -------------------------------------------------------------------------
    // Award PURCHASE referral points when referred friend completes an order
    // -------------------------------------------------------------------------

    public static function handle_purchase( $buyer_user_id, $order_id ) {
        // Only fires the FIRST time the referred user makes a purchase
        $already_rewarded = get_user_meta( $buyer_user_id, '_pcp_referral_purchase_rewarded', true );
        if ( $already_rewarded ) return;

        $referrer_id = get_user_meta( $buyer_user_id, '_pcp_referred_by', true );
        if ( ! $referrer_id ) return;

        $pts = (int) PCP_Settings::get( 'referral_purchase_points' );
        if ( $pts > 0 ) {
            PCP_Points::add_points(
                (int) $referrer_id,
                $pts,
                'referral_purchase',
                sprintf( 'Referral purchase bonus — your friend placed order #%d', $order_id ),
                $order_id
            );
        }

        // Mark so we don't reward again
        update_user_meta( $buyer_user_id, '_pcp_referral_purchase_rewarded', 1 );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public static function get_user_by_referral_code( $code ) {
        $users = get_users( array(
            'meta_key'   => '_pcp_referral_code',
            'meta_value' => $code,
            'number'     => 1,
            'fields'     => 'ID',
        ));
        return ! empty( $users ) ? (int) $users[0] : null;
    }

    public static function show_referral_link() {
        $user_id = get_current_user_id();
        $url     = self::get_referral_url( $user_id );
        echo '<div class="pcp-referral-box">';
        echo '<h3>🎁 বন্ধুকে রেফার করুন</h3>';
        $share_pts    = (int) PCP_Settings::get( 'referral_share_points' );
        $purchase_pts = (int) PCP_Settings::get( 'referral_purchase_points' );
        echo '<p>আপনার বন্ধু শুধু লিংক দিয়ে যোগ দিলে <strong>' . $share_pts . ' পয়েন্ট</strong>, আর কিনলে আরও <strong>' . $purchase_pts . ' পয়েন্ট</strong> পাবেন!</p>';
        echo '<input type="text" readonly value="' . esc_attr( $url ) . '" onclick="this.select()" style="width:100%;padding:8px;margin:8px 0;" />';
        echo '<button onclick="navigator.clipboard.writeText(\'' . esc_js( $url ) . '\');this.textContent=\'✅ Copied!\'" class="button">লিংক কপি করুন</button>';
        echo '</div>';
    }
}