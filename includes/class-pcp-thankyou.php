<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCP_ThankYou {

    public static function init() {
        add_action( 'woocommerce_thankyou',                      array( __CLASS__, 'render_registration_prompt' ), 5 );
        add_action( 'wp_ajax_nopriv_pcp_register_from_thankyou', array( __CLASS__, 'handle_ajax_register' ) );
    }

    public static function render_registration_prompt( $order_id ) {
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

        // If the order's billing email already has an account, don't show signup form
        $billing_email = $order->get_billing_email();
        if ( $billing_email && email_exists( $billing_email ) ) {
            echo '<div class="pcp-thankyou-box pcp-logged-in">';
            echo '<h3>🏆 পয়েন্ট পেতে আপনার অ্যাকাউন্টে লগইন করুন!</h3>';
            echo '<p>এই ইমেইলে আগেই একটি অ্যাকাউন্ট আছে। <a href="' . esc_url( wc_get_page_permalink('myaccount') ) . '">লগইন করুন</a> এবং আপনার পয়েন্ট দেখুন।</p>';
            echo '</div>';
            return;
        }

        $total        = $order->get_subtotal();
        $rate         = (float) PCP_Settings::get('points_per_taka');
        $per          = (float) PCP_Settings::get('taka_per_earn');
        $order_pts    = (int) floor( ($total / $per) * $rate );
        $signup_bonus = (int) PCP_Settings::get('signup_bonus');
        $total_pts    = $order_pts + $signup_bonus;
        $taka_value   = PCP_Points::points_to_taka( $total_pts );

        $billing_phone = $order->get_billing_phone();
        ?>
        <div class="pcp-thankyou-box" id="pcp-register-box">
            <div class="pcp-points-earned-banner">
                🎉 এই অর্ডার থেকে আপনি পাচ্ছেন <strong><?php echo $order_pts; ?> পয়েন্ট</strong>
                <?php if ( $signup_bonus > 0 ) : ?>
                    + নতুন অ্যাকাউন্ট বোনাস <strong><?php echo $signup_bonus; ?> পয়েন্ট</strong>
                <?php endif; ?>
                = মোট <strong><?php echo $total_pts; ?> পয়েন্ট</strong>
                (মূল্য ≈ <?php echo $taka_value; ?> টাকা ছাড়)
            </div>

            <h3>🏆 পয়েন্ট পেতে এখনই অ্যাকাউন্ট তৈরি করুন!</h3>
            <p>মাত্র ৩০ সেকেন্ডের কাজ — ফোন নম্বর আগেই আছে, শুধু পাসওয়ার্ড দিন।</p>

            <form id="pcp-register-form">
                <input type="hidden" name="action"   value="pcp_register_from_thankyou">
                <input type="hidden" name="nonce"    value="<?php echo wp_create_nonce('pcp_nonce'); ?>">
                <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                <?php
                // Pass the referral cookie value into the form so the AJAX
                // handler can use it without relying on cookies in the AJAX request
                $ref_cookie = isset( $_COOKIE['pcp_ref'] ) ? sanitize_text_field( $_COOKIE['pcp_ref'] ) : '';
                ?>
                <input type="hidden" name="ref_code" value="<?php echo esc_attr($ref_cookie); ?>">

                <div class="pcp-form-row">
                    <label>নাম</label>
                    <input type="text" name="display_name" value="<?php echo esc_attr($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?>" required>
                </div>
                <div class="pcp-form-row">
                    <label>ইমেইল</label>
                    <input type="email" name="email" value="<?php echo esc_attr($billing_email); ?>" required>
                </div>
                <?php if ( $billing_phone ) : ?>
                <div class="pcp-form-row">
                    <label>ফোন</label>
                    <input type="text" value="<?php echo esc_attr($billing_phone); ?>" readonly style="background:#f0f0f0;">
                    <input type="hidden" name="phone" value="<?php echo esc_attr($billing_phone); ?>">
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
        $ref_code = sanitize_text_field( $_POST['ref_code'] ?? '' );

        if ( ! $email || ! $password || strlen($password) < 6 ) {
            wp_send_json_error( array('message' => 'সঠিক ইমেইল ও পাসওয়ার্ড দিন।') );
        }
        if ( email_exists( $email ) ) {
            wp_send_json_error( array('message' => 'এই ইমেইল দিয়ে আগেই অ্যাকাউন্ট আছে। লগইন করুন।') );
        }

        // Tell the user_register hook to skip referral (we handle it manually below)
        define( 'PCP_DOING_THANKYOU_REGISTER', true );

        $username = sanitize_user( explode('@', $email)[0] . '_' . substr( uniqid(), -4 ) );
        $user_id  = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( array('message' => $user_id->get_error_message()) );
        }

        wp_update_user( array('ID' => $user_id, 'display_name' => $name) );
        if ( $phone ) update_user_meta( $user_id, 'billing_phone', $phone );

        // Link order to new user
        if ( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order && ! $order->get_customer_id() ) {
                $order->set_customer_id( $user_id );
                $order->save();
            }
        }

        // Award signup bonus — guard inside award_signup_bonus prevents double-award
        // (award_signup_bonus is also hooked to user_register but only awards once)
        PCP_Points::award_signup_bonus( $user_id );

        // Handle referral — pass ref_code explicitly so we don't rely on AJAX cookies
        PCP_Referral::link_referral_on_register( $user_id, $ref_code ?: null );

        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );

        $balance = PCP_Points::get_balance( $user_id );
        wp_send_json_success( array(
            'message' => 'অ্যাকাউন্ট তৈরি হয়েছে!',
            'balance' => $balance,
        ));
    }
}