<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCP_ThankYou {

    public static function init() {
        add_action( 'woocommerce_thankyou', array( __CLASS__, 'render_registration_prompt' ), 5 );
        add_action( 'wp_ajax_nopriv_pcp_register_from_thankyou', array( __CLASS__, 'handle_ajax_register' ) );
    }

    public static function render_registration_prompt( $order_id ) {
        // Already logged in — just show balance
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $balance = PCP_Points::get_balance( $user_id );
            echo '<div class="pcp-thankyou-box pcp-logged-in">';
            echo '<h3>🏆 আপনার পয়েন্ট ব্যালেন্স: <strong>' . $balance . ' পয়েন্ট</strong></h3>';
            echo '<p>এই অর্ডার কমপ্লিট হলে আরও পয়েন্ট যোগ হবে।</p>';
            echo '</div>';
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        // Calculate how many points they'll earn
        $total        = $order->get_subtotal();
        $rate         = (float) PCP_Settings::get( 'points_per_taka' );
        $per          = (float) PCP_Settings::get( 'taka_per_earn' );
        $order_pts    = (int) floor( ( $total / $per ) * $rate );
        $signup_bonus = (int) PCP_Settings::get( 'signup_bonus' );
        $total_pts    = $order_pts + $signup_bonus;
        $taka_value   = PCP_Points::points_to_taka( $total_pts );

        $billing_email = $order->get_billing_email();
        $billing_phone = $order->get_billing_phone();

        ?>
        <div class="pcp-thankyou-box" id="pcp-register-box">
            <div class="pcp-points-earned-banner">
                🎉 এই অর্ডার থেকে আপনি পাচ্ছেন <strong><?php echo $order_pts; ?> পয়েন্ট</strong>
                + নতুন অ্যাকাউন্ট বোনাস <strong><?php echo $signup_bonus; ?> পয়েন্ট</strong>
                = মোট <strong><?php echo $total_pts; ?> পয়েন্ট</strong>
                (মূল্য ≈ <?php echo $taka_value; ?> টাকা ছাড়)
            </div>

            <h3>🏆 পয়েন্ট পেতে এখনই অ্যাকাউন্ট তৈরি করুন!</h3>
            <p>মাত্র ৩০ সেকেন্ডের কাজ — ফোন নম্বর আগেই আছে, শুধু পাসওয়ার্ড দিন।</p>

            <form id="pcp-register-form">
                <input type="hidden" name="action"   value="pcp_register_from_thankyou">
                <input type="hidden" name="nonce"    value="<?php echo wp_create_nonce('pcp_nonce'); ?>">
                <input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

                <div class="pcp-form-row">
                    <label>নাম</label>
                    <input type="text" name="display_name" value="<?php echo esc_attr( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?>" required>
                </div>

                <div class="pcp-form-row">
                    <label>ইমেইল</label>
                    <input type="email" name="email" value="<?php echo esc_attr( $billing_email ); ?>" required>
                </div>

                <?php if ( $billing_phone ) : ?>
                <div class="pcp-form-row">
                    <label>ফোন</label>
                    <input type="text" value="<?php echo esc_attr( $billing_phone ); ?>" readonly style="background:#f0f0f0;">
                    <input type="hidden" name="phone" value="<?php echo esc_attr( $billing_phone ); ?>">
                </div>
                <?php endif; ?>

                <div class="pcp-form-row">
                    <label>পাসওয়ার্ড তৈরি করুন</label>
                    <input type="password" name="password" required minlength="6" placeholder="কমপক্ষে ৬ অক্ষর">
                </div>

                <button type="submit" class="pcp-btn-register">
                    ✅ অ্যাকাউন্ট তৈরি করুন ও পয়েন্ট নিন
                </button>

                <p class="pcp-skip"><a href="#" onclick="document.getElementById('pcp-register-box').style.display='none';return false;">এখন না, পরে করব</a></p>
            </form>

            <div id="pcp-register-success" style="display:none;">
                <h3>✅ অ্যাকাউন্ট তৈরি হয়ে গেছে!</h3>
                <p>আপনার <strong><?php echo $total_pts; ?> পয়েন্ট</strong> যোগ হয়েছে। পরের অর্ডারে ছাড় পাবেন!</p>
            </div>
        </div>
        <?php
    }

    public static function handle_ajax_register() {
        check_ajax_referer( 'pcp_nonce', 'nonce' );

        $email    = sanitize_email( $_POST['email'] ?? '' );
        $name     = sanitize_text_field( $_POST['display_name'] ?? '' );
        $password = $_POST['password'] ?? '';
        $order_id = (int) ( $_POST['order_id'] ?? 0 );
        $phone    = sanitize_text_field( $_POST['phone'] ?? '' );

        if ( ! $email || ! $password || strlen( $password ) < 6 ) {
            wp_send_json_error( array( 'message' => 'সঠিক ইমেইল ও পাসওয়ার্ড দিন।' ) );
        }

        if ( email_exists( $email ) ) {
            wp_send_json_error( array( 'message' => 'এই ইমেইল দিয়ে আগেই অ্যাকাউন্ট আছে। লগইন করুন।' ) );
        }

        $username = sanitize_user( explode( '@', $email )[0] . '_' . substr( uniqid(), -4 ) );

        $user_id = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
        }

        // Update display name & phone
        wp_update_user( array( 'ID' => $user_id, 'display_name' => $name ) );
        if ( $phone ) update_user_meta( $user_id, 'billing_phone', $phone );

        // Link order to this user
        if ( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order && ! $order->get_customer_id() ) {
                $order->set_customer_id( $user_id );
                $order->save();
            }
        }

        // Award signup bonus (user_register hook may already have fired without user_id context)
        // so we manually ensure it:
        $already = get_user_meta( $user_id, '_pcp_signup_bonus_given', true );
        if ( ! $already ) {
            $bonus = (int) PCP_Settings::get( 'signup_bonus' );
            if ( $bonus > 0 ) {
                PCP_Points::add_points( $user_id, $bonus, 'signup', 'Account creation bonus' );
            }
            update_user_meta( $user_id, '_pcp_signup_bonus_given', 1 );
        }

        // Log them in
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );

        $balance = PCP_Points::get_balance( $user_id );
        wp_send_json_success( array(
            'message' => 'অ্যাকাউন্ট তৈরি হয়েছে!',
            'balance' => $balance,
        ));
    }
}