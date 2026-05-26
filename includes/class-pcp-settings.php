<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCP_Settings {

    public static function init() {}

    public static function defaults() {
        return array(
            'points_per_taka'             => 1,
            'taka_per_earn'               => 10,
            'signup_bonus'                => 50,
            'review_points'               => 100,
            'referral_share_points'       => 5,
            'referral_purchase_points'    => 150,
            'referred_friend_points'      => 50,
            'points_to_taka_rate'         => 1,
            'points_expiry_days'          => 180,
            'tier_pro_min'                => 500,
            'tier_legend_min'             => 1000,
            'tier_pro_earn_multiplier'    => 1.5,
            'tier_legend_earn_multiplier' => 2.0,
            'tier_legend_free_shipping'   => 1,
            'max_redeem_percent'          => 50,
            'plugin_enabled'              => 1,
        );
    }

    public static function get( $key ) {
        $defaults = self::defaults();
        return get_option( 'pcp_' . $key, isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );
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
            // Checkboxes — if not set, save 0
            if ( ! isset( $data[ $field ] ) && in_array( $field, array('tier_legend_free_shipping','plugin_enabled') ) ) {
                self::update( $field, 0 );
            }
        }
    }
}
