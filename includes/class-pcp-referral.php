<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCP_Referral {

    public static function init() {
        add_action( 'init',          array( __CLASS__, 'capture_referral_code' ) );
        // NOTE: user_register hook intentionally removed.
        // link_referral_on_register() is called explicitly from thankyou AJAX
        // and from wp-login.php registration. Calling it via hook AND manually
        // caused double-award. All call sites invoke it directly instead.
        add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'show_referral_link' ) );

        // Hook into the standard WP registration form (not thankyou AJAX)
        add_action( 'user_register', array( __CLASS__, 'hook_link_referral' ), 20 );
    }

    /**
     * Called only from the hook for standard WP/WooCommerce registration.
     * The thankyou AJAX path calls link_referral_on_register() directly,
     * so this hook is skipped there (wp_create_user fires inside AJAX context
     * where we set a flag beforehand to prevent double-run).
     */
    public static function hook_link_referral( $new_user_id ) {
        // If this registration is happening inside our thankyou AJAX handler,
        // skip — the handler calls link_referral_on_register() directly after.
        if ( defined('PCP_DOING_THANKYOU_REGISTER') && PCP_DOING_THANKYOU_REGISTER ) return;
        self::link_referral_on_register( $new_user_id );
    }

    // ── Referral code ─────────────────────────────────────────────────

    public static function get_referral_code( $user_id ) {
        $code = get_user_meta( $user_id, '_pcp_referral_code', true );
        if ( ! $code ) {
            $code = self::generate_unique_code( $user_id );
            update_user_meta( $user_id, '_pcp_referral_code', $code );
        }
        return $code;
    }

    /**
     * Generate a collision-resistant referral code and verify uniqueness.
     */
    private static function generate_unique_code( $user_id ) {
        $attempts = 0;
        do {
            $seed = $user_id . AUTH_KEY . $attempts . wp_generate_password( 4, false );
            $code = 'PC' . strtoupper( substr( md5( $seed ), 0, 8 ) );
            $existing = self::get_user_by_referral_code( $code );
            $attempts++;
        } while ( $existing && $existing !== (int) $user_id && $attempts < 10 );

        return $code;
    }

    public static function get_referral_url( $user_id ) {
        return add_query_arg( 'ref', self::get_referral_code( $user_id ), home_url('/') );
    }

    // ── Cookie capture ────────────────────────────────────────────────

    public static function capture_referral_code() {
        if ( ! empty( $_GET['ref'] ) ) {
            $code = sanitize_text_field( $_GET['ref'] );
            // Basic format validation
            if ( preg_match( '/^PC[A-Z0-9]{8}$/', $code ) ) {
                if ( ! headers_sent() ) {
                    setcookie( 'pcp_ref', $code, time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
                }
                $_COOKIE['pcp_ref'] = $code;
            }
        }
    }

    // ── Link referral on new registration ─────────────────────────────

    /**
     * Links a newly registered user to their referrer.
     * Safe to call multiple times — guarded by _pcp_referral_linked meta.
     *
     * @param int $new_user_id
     * @param string|null $cookie_override  Pass cookie value explicitly from AJAX context.
     */
    public static function link_referral_on_register( $new_user_id, $cookie_override = null ) {
        $new_user_id = (int) $new_user_id;

        // Guard: only link once
        if ( get_user_meta( $new_user_id, '_pcp_referral_linked', true ) ) return;
        update_user_meta( $new_user_id, '_pcp_referral_linked', 1 );

        // Use explicitly passed cookie value (from AJAX) or fall back to $_COOKIE
        $code = $cookie_override
            ? sanitize_text_field( $cookie_override )
            : ( isset( $_COOKIE['pcp_ref'] ) ? sanitize_text_field( $_COOKIE['pcp_ref'] ) : '' );

        if ( ! $code ) return;

        $referrer_id = self::get_user_by_referral_code( $code );
        if ( ! $referrer_id || $referrer_id === $new_user_id ) return;

        update_user_meta( $new_user_id, '_pcp_referred_by', $referrer_id );

        // Signup bonus to referrer
        $share_pts = (int) PCP_Settings::get('referral_share_points');
        if ( $share_pts > 0 ) {
            PCP_Points::add_points(
                $referrer_id, $share_pts, 'referral_signup',
                sprintf( 'Referral signup bonus — user #%d registered', $new_user_id )
            );
        }

        // Welcome bonus to the new friend
        $friend_pts = (int) PCP_Settings::get('referred_friend_points');
        if ( $friend_pts > 0 ) {
            PCP_Points::add_points(
                $new_user_id, $friend_pts, 'referred_friend',
                'Welcome bonus — you joined via a referral link!'
            );
        }
    }

    // ── Purchase bonus ────────────────────────────────────────────────

    public static function handle_purchase( $buyer_user_id, $order_id ) {
        $buyer_user_id = (int) $buyer_user_id;

        if ( get_user_meta( $buyer_user_id, '_pcp_referral_purchase_rewarded', true ) ) return;

        $referrer_id = (int) get_user_meta( $buyer_user_id, '_pcp_referred_by', true );
        if ( ! $referrer_id ) return;

        update_user_meta( $buyer_user_id, '_pcp_referral_purchase_rewarded', 1 );

        $pts = (int) PCP_Settings::get('referral_purchase_points');
        if ( $pts > 0 ) {
            PCP_Points::add_points(
                $referrer_id, $pts, 'referral_purchase',
                sprintf( 'Referral purchase bonus — your friend placed order #%d', $order_id ),
                $order_id
            );
        }
    }

    // ── Lookup ────────────────────────────────────────────────────────

    public static function get_user_by_referral_code( $code ) {
        $users = get_users( array(
            'meta_key'   => '_pcp_referral_code',
            'meta_value' => sanitize_text_field( $code ),
            'number'     => 1,
            'fields'     => 'ID',
        ));
        return ! empty( $users ) ? (int) $users[0] : null;
    }

    // ── Dashboard widget ──────────────────────────────────────────────

    public static function show_referral_link() {
        $user_id      = get_current_user_id();
        $url          = self::get_referral_url( $user_id );
        $share_pts    = (int) PCP_Settings::get('referral_share_points');
        $purchase_pts = (int) PCP_Settings::get('referral_purchase_points');

        echo '<div class="pcp-referral-box">';
        echo '<h3>🎁 বন্ধুকে রেফার করুন</h3>';
        echo '<p>বন্ধু যোগ দিলে <strong>' . $share_pts . ' পয়েন্ট</strong>, আর কিনলে আরও <strong>' . $purchase_pts . ' পয়েন্ট</strong> পাবেন!</p>';
        echo '<input type="text" readonly value="' . esc_attr($url) . '" onclick="this.select()" style="width:100%;padding:8px;margin:8px 0;">';
        echo '<button onclick="navigator.clipboard.writeText(\'' . esc_js($url) . '\');this.textContent=\'Copied!\'" class="button">লিংক কপি করুন</button>';
        echo '</div>';
    }
}