<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCP_Checkout {

    public static function init() {
        // Show points balance + redeem option on checkout page
        add_action( 'woocommerce_before_order_notes', array( __CLASS__, 'render_redeem_section' ) );

        // Apply points discount via session
        add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'apply_points_discount' ) );

        // AJAX: toggle points redemption
        add_action( 'wp_ajax_pcp_toggle_redeem', array( __CLASS__, 'handle_toggle_redeem' ) );

        // Clear session after order placed
        add_action( 'woocommerce_thankyou', array( __CLASS__, 'clear_redeem_session' ) );

        // Deduct points after order is placed
        add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'deduct_on_order_created' ) );
    }

    // -------------------------------------------------------------------------
    // Render the redeem section on checkout
    // -------------------------------------------------------------------------

    public static function render_redeem_section( $checkout ) {
        if ( ! is_user_logged_in() ) return;

        $user_id  = get_current_user_id();
        $balance  = PCP_Points::get_balance( $user_id );
        if ( $balance <= 0 ) return;

        $taka_value    = PCP_Points::points_to_taka( $balance );
        $cart_total    = WC()->cart->get_subtotal();
        $max_percent   = (int) PCP_Settings::get( 'max_redeem_percent' );
        $max_taka      = floor( $cart_total * ( $max_percent / 100 ) );
        $redeemable    = min( $taka_value, $max_taka );
        $redeemable_pts = PCP_Points::taka_to_points( $redeemable );
        $is_applied    = WC()->session->get( 'pcp_redeem_active' );

        ?>
        <div class="pcp-checkout-redeem" id="pcp-checkout-redeem">
            <h3>🏆 আপনার পয়েন্ট ব্যালেন্স: <strong><?php echo $balance; ?> পয়েন্ট</strong> (≈ <?php echo number_format($taka_value, 0); ?> টাকা)</h3>
            <?php if ( $redeemable > 0 ) : ?>
                <p>এই অর্ডারে সর্বোচ্চ <strong><?php echo $redeemable_pts; ?> পয়েন্ট</strong> ব্যবহার করে <strong><?php echo number_format($redeemable, 0); ?> টাকা</strong> ছাড় পেতে পারেন।</p>
                <?php if ( $is_applied ) : ?>
                    <button type="button" class="pcp-btn-redeem pcp-active" id="pcp-toggle-redeem" data-active="1">
                        ✅ পয়েন্ট প্রয়োগ হয়েছে — বাতিল করুন
                    </button>
                <?php else : ?>
                    <button type="button" class="pcp-btn-redeem" id="pcp-toggle-redeem" data-active="0">
                        🎁 পয়েন্ট দিয়ে <?php echo number_format($redeemable, 0); ?> টাকা ছাড় নিন
                    </button>
                <?php endif; ?>
            <?php else : ?>
                <p>এই অর্ডারে পয়েন্ট ব্যবহার করা যাবে না (সর্বোচ্চ সীমা অতিক্রম করেছে)।</p>
            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Apply discount fee to cart
    // -------------------------------------------------------------------------

    public static function apply_points_discount( $cart ) {
        if ( ! is_user_logged_in() ) return;
        if ( ! WC()->session->get( 'pcp_redeem_active' ) ) return;

        $user_id      = get_current_user_id();
        $balance      = PCP_Points::get_balance( $user_id );
        $taka_value   = PCP_Points::points_to_taka( $balance );
        $cart_total   = $cart->get_subtotal();
        $max_percent  = (int) PCP_Settings::get( 'max_redeem_percent' );
        $max_taka     = floor( $cart_total * ( $max_percent / 100 ) );
        $discount     = min( $taka_value, $max_taka );

        if ( $discount > 0 ) {
            $cart->add_fee( '🏆 পয়েন্ট ছাড়', -$discount, false );
            // Store how much was redeemed in session for later deduction
            WC()->session->set( 'pcp_redeem_taka', $discount );
        }
    }

    // -------------------------------------------------------------------------
    // AJAX toggle
    // -------------------------------------------------------------------------

    public static function handle_toggle_redeem() {
        check_ajax_referer( 'pcp_nonce', 'nonce' );

        $active = (int) ( $_POST['active'] ?? 0 );
        WC()->session->set( 'pcp_redeem_active', $active );
        if ( ! $active ) {
            WC()->session->set( 'pcp_redeem_taka', 0 );
        }

        wp_send_json_success( array( 'active' => $active ) );
    }

    // -------------------------------------------------------------------------
    // Deduct points when order is created
    // -------------------------------------------------------------------------

    public static function deduct_on_order_created( $order ) {
        if ( ! is_user_logged_in() ) return;

        $taka = (float) WC()->session->get( 'pcp_redeem_taka', 0 );
        if ( $taka <= 0 ) return;

        $user_id = get_current_user_id();
        $pts     = PCP_Points::taka_to_points( $taka );

        PCP_Points::deduct_points(
            $user_id,
            $pts,
            'redeem',
            sprintf( 'Order #%d — redeemed %d points (%.0f টাকা ছাড়)', $order->get_id(), $pts, $taka ),
            $order->get_id()
        );

        // Store on order for reference
        $order->update_meta_data( '_pcp_points_redeemed', $pts );
        $order->update_meta_data( '_pcp_taka_discount',   $taka );
        $order->save();

        WC()->session->set( 'pcp_redeem_active', 0 );
        WC()->session->set( 'pcp_redeem_taka',   0 );
    }

    // -------------------------------------------------------------------------
    // Clear session after thank you page loads
    // -------------------------------------------------------------------------

    public static function clear_redeem_session( $order_id ) {
        WC()->session->set( 'pcp_redeem_active', 0 );
        WC()->session->set( 'pcp_redeem_taka',   0 );
    }
}
