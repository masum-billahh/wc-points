<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCP_Settings {

    public static function init() {}

    /** All default values */
    public static function defaults() {
        return array(
            // Earning
            'points_per_taka'           => 1,      // points earned per X taka spent
            'taka_per_earn'             => 10,     // spend this many taka to earn points_per_taka
            'signup_bonus'              => 50,     // points for creating account
            'review_points'             => 100,    // points for leaving a photo review
            'referral_share_points'     => 5,      // points just for sharing referral link (friend registers)
            'referral_purchase_points'  => 150,    // points for referrer when friend PURCHASES
            'referred_friend_points'    => 50,     // bonus points for the new friend who used referral link

            // Redeeming
            'points_to_taka_rate'       => 1,      // 1 point = X taka discount (default 1=1)

            // Expiry
            'points_expiry_days'        => 180,    // 0 = never expire

            // Tiers
            'tier_pro_min'              => 500,
            'tier_legend_min'           => 1000,
            'tier_pro_earn_multiplier'  => 1.5,
            'tier_legend_earn_multiplier' => 2.0,
            'tier_legend_free_shipping' => 1,      // 1 = yes

            // Misc
            'max_redeem_percent'        => 50,     // max % of order total payable by points
            'plugin_enabled'            => 1,
        );
    }

    public static function get( $key ) {
        $defaults = self::defaults();
        $value    = get_option( 'pcp_' . $key, isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );
        return $value;
    }

    public static function update( $key, $value ) {
        update_option( 'pcp_' . $key, $value );
    }

    public static function save_all( $data ) {
        $fields = array_keys( self::defaults() );
        foreach ( $fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                self::update( $field, sanitize_text_field( $data[ $field ] ) );
            }
        }
    }
}