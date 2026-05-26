<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCP_Tiers {

    public static function init() {
        // Apply free shipping for Legend tier
        add_filter( 'woocommerce_package_rates', array( __CLASS__, 'apply_legend_free_shipping' ), 100, 2 );
    }

    public static function get_tier( $user_id ) {
        // Tier is based on TOTAL LIFETIME points earned (not current balance)
        global $wpdb;
        $table = $wpdb->prefix . PCP_TABLE;
        $total_earned = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(points),0) FROM {$table} WHERE user_id = %d AND points > 0",
            $user_id
        ));

        $legend_min = (int) PCP_Settings::get( 'tier_legend_min' );
        $pro_min    = (int) PCP_Settings::get( 'tier_pro_min' );

        if ( $total_earned >= $legend_min ) {
            return array( 'slug' => 'legend', 'label' => '🥇 Legend', 'total' => $total_earned );
        } elseif ( $total_earned >= $pro_min ) {
            return array( 'slug' => 'pro',    'label' => '🥈 Pro Champ', 'total' => $total_earned );
        } else {
            return array( 'slug' => 'champ',  'label' => '🥉 Champ', 'total' => $total_earned );
        }
    }

    public static function get_earn_multiplier( $user_id ) {
        $tier = self::get_tier( $user_id );
        switch ( $tier['slug'] ) {
            case 'legend':
                return (float) PCP_Settings::get( 'tier_legend_earn_multiplier' );
            case 'pro':
                return (float) PCP_Settings::get( 'tier_pro_earn_multiplier' );
            default:
                return 1.0;
        }
    }

    public static function apply_legend_free_shipping( $rates, $package ) {
        if ( ! is_user_logged_in() ) return $rates;
        if ( ! (int) PCP_Settings::get( 'tier_legend_free_shipping' ) ) return $rates;

        $user_id = get_current_user_id();
        $tier    = self::get_tier( $user_id );
        if ( $tier['slug'] !== 'legend' ) return $rates;

        foreach ( $rates as $rate_id => $rate ) {
            if ( 'free_shipping' === $rate->method_id ) continue;
            // Set cost to 0 and label it as Legend perk
            $rates[ $rate_id ]->cost  = 0;
            $rates[ $rate_id ]->label = $rate->label . ' (Legend Free Shipping)';
            foreach ( $rates[ $rate_id ]->taxes as $key => $tax ) {
                $rates[ $rate_id ]->taxes[ $key ] = 0;
            }
        }
        return $rates;
    }

    public static function get_all_tiers_display() {
        return array(
            array(
                'slug'       => 'champ',
                'label'      => '🥉 Champ',
                'min'        => 0,
                'max'        => (int) PCP_Settings::get( 'tier_pro_min' ) - 1,
                'multiplier' => '1x',
                'perks'      => 'প্রতি ' . PCP_Settings::get('taka_per_earn') . ' টাকায় ' . PCP_Settings::get('points_per_taka') . ' পয়েন্ট',
            ),
            array(
                'slug'       => 'pro',
                'label'      => '🥈 Pro Champ',
                'min'        => (int) PCP_Settings::get( 'tier_pro_min' ),
                'max'        => (int) PCP_Settings::get( 'tier_legend_min' ) - 1,
                'multiplier' => PCP_Settings::get('tier_pro_earn_multiplier') . 'x',
                'perks'      => PCP_Settings::get('tier_pro_earn_multiplier') . 'x পয়েন্ট প্রতিটি অর্ডারে',
            ),
            array(
                'slug'       => 'legend',
                'label'      => '🥇 Legend',
                'min'        => (int) PCP_Settings::get( 'tier_legend_min' ),
                'max'        => null,
                'multiplier' => PCP_Settings::get('tier_legend_earn_multiplier') . 'x',
                'perks'      => PCP_Settings::get('tier_legend_earn_multiplier') . 'x পয়েন্ট + ফ্রি শিপিং',
            ),
        );
    }
}