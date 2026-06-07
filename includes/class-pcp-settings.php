<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCP_Settings {

    public static function init() {}

    public static function defaults() {
        return array(
            // Earning
            'points_per_taka'             => 1,
            'taka_per_earn'               => 10,
            'signup_bonus'                => 50,
            'review_points'               => 100,

            // Referral
            'referral_share_points'       => 5,
            'referral_purchase_points'    => 150,
            'referred_friend_points'      => 50,

            // Redeeming
            'points_to_taka_rate'         => 1,
            'max_redeem_percent'          => 50,

            // Expiry
            'points_expiry_days'          => 180,

            // Tiers — thresholds & multipliers
            'tier_pro_min'                => 500,
            'tier_legend_min'             => 1000,
            'tier_pro_earn_multiplier'    => 1.5,
            'tier_legend_earn_multiplier' => 2.0,
            'tier_legend_free_shipping'   => 1,

            // Tier — names & icons (customisable)
            'tier_champ_name'             => 'Champ',
            'tier_champ_icon'             => '🥉',
            'tier_pro_name'               => 'Pro Champ',
            'tier_pro_icon'               => '🥈',
            'tier_legend_name'            => 'Legend',
            'tier_legend_icon'            => '🥇',

            // Misc
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

    /**
     * Returns the full display label for a tier slug: icon + name.
     */
    public static function tier_label( $slug ) {
        $icon = self::get( 'tier_' . $slug . '_icon' );
        $name = self::get( 'tier_' . $slug . '_name' );
        return trim( $icon . ' ' . $name );
    }

    public static function save_all( $data ) {
        $defaults = self::defaults();
        $fields   = array_keys( $defaults );

        $checkbox_fields = array( 'tier_legend_free_shipping', 'plugin_enabled' );

        $numeric_fields = array(
            'points_per_taka', 'taka_per_earn', 'signup_bonus', 'review_points',
            'referral_share_points', 'referral_purchase_points', 'referred_friend_points',
            'points_to_taka_rate', 'max_redeem_percent', 'points_expiry_days',
            'tier_pro_min', 'tier_legend_min',
            'tier_pro_earn_multiplier', 'tier_legend_earn_multiplier',
        );

        foreach ( $fields as $field ) {
            if ( in_array( $field, $checkbox_fields ) ) {
                self::update( $field, isset( $data[ $field ] ) ? 1 : 0 );
                continue;
            }

            if ( ! isset( $data[ $field ] ) ) {
                continue;
            }

            $value = $data[ $field ];

            if ( in_array( $field, $numeric_fields ) ) {
                $value = is_numeric( $value ) ? $value + 0 : $defaults[ $field ];
            } else {
                $value = sanitize_text_field( $value );
            }

            self::update( $field, $value );
        }
    }
}