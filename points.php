<?php
/**
 * Plugin Name: User Retention - Points & Referral
 * Plugin URI:  https://playchamp.net
 * Description: Loyalty points, referral system, tiers, and thank-you page registration prompt for WooCommerce.
 * Version:     1.2.0
 * Author:      Masum
 * Text Domain: playchamp-points
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PCP_VERSION',  '1.2.0' );
define( 'PCP_PATH',     plugin_dir_path( __FILE__ ) );
define( 'PCP_URL',      plugin_dir_url( __FILE__ ) );
define( 'PCP_TABLE',    'pcp_points_log' );

require_once PCP_PATH . 'includes/class-pcp-db.php';
require_once PCP_PATH . 'includes/class-pcp-settings.php';
require_once PCP_PATH . 'includes/class-pcp-points.php';
require_once PCP_PATH . 'includes/class-pcp-referral.php';
require_once PCP_PATH . 'includes/class-pcp-tiers.php';
require_once PCP_PATH . 'includes/class-pcp-thankyou.php';
require_once PCP_PATH . 'includes/class-pcp-checkout.php';
require_once PCP_PATH . 'admin/class-pcp-admin.php';
require_once PCP_PATH . 'includes/class-pcp-myaccount.php';

register_activation_hook( __FILE__, array( 'PCP_DB', 'install' ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="error"><p><strong>PlayChamp Points</strong> requires WooCommerce to be active.</p></div>';
        });
        return;
    }
    PCP_Settings::init();
    PCP_Points::init();
    PCP_Referral::init();
    PCP_Tiers::init();
    PCP_ThankYou::init();
    PCP_Checkout::init();
    PCP_Admin::init();
    PCP_MyAccount::init();
});