<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Variables available from PCP_MyAccount::render_page():
 *   $user_id, $balance, $taka, $tier, $history, $history_total, $ref_url
 */

$tiers        = PCP_Tiers::get_all_tiers_display();
$pro_min      = (int) PCP_Settings::get('tier_pro_min');
$legend_min   = (int) PCP_Settings::get('tier_legend_min');
$total_earned = $tier['total'];
$share_pts    = (int) PCP_Settings::get('referral_share_points');
$purchase_pts = (int) PCP_Settings::get('referral_purchase_points');
$friend_pts   = (int) PCP_Settings::get('referred_friend_points');

$per_page     = 10;
$total_pages  = (int) ceil( $history_total / $per_page );

$base_taka   = (float) PCP_Settings::get('taka_per_earn');
$base_pts    = (float) PCP_Settings::get('points_per_taka');
$multiplier  = PCP_Tiers::get_earn_multiplier( $user_id );
$actual_pts  = $base_pts * $multiplier;
?>

<div class="pcp-page">

    <!-- ── Hero Balance Card ───────────────────────────────────── -->
    <div class="pcp-hero">
        <div class="pcp-hero__left">
            <p class="pcp-hero__label">আপনার পয়েন্ট ব্যালেন্স</p>
            <p class="pcp-hero__pts"><?php echo number_format($balance); ?> <span>pts</span></p>
            <p class="pcp-hero__taka">≈ <?php echo number_format($taka, 0); ?> টাকা ছাড়</p>
        </div>
        <div class="pcp-hero__right">
            <div class="pcp-tier-pill pcp-tier-<?php echo esc_attr($tier['slug']); ?>">
                <?php echo esc_html($tier['label']); ?>
            </div>
            <?php
            if ( $tier['slug'] === 'champ' && $pro_min > 0 ) :
                $needed = max( 0, $pro_min - $total_earned );
                $pct    = min( 100, round( ($total_earned / $pro_min) * 100 ) );
                $next_label = PCP_Settings::tier_label('pro');
            elseif ( $tier['slug'] === 'pro' && $legend_min > $pro_min ) :
                $needed = max( 0, $legend_min - $total_earned );
                $pct    = min( 100, round( (($total_earned - $pro_min) / ($legend_min - $pro_min)) * 100 ) );
                $next_label = PCP_Settings::tier_label('legend');
            endif;

            if ( $tier['slug'] !== 'legend' ) : ?>
            <p class="pcp-hero__next">
                পরের tier <strong><?php echo esc_html($next_label); ?></strong> পেতে আরও <strong><?php echo number_format($needed); ?> pts</strong> লাগবে
            </p>
            <div class="pcp-progressbar">
                <div class="pcp-progressbar__fill pcp-progressbar__fill--<?php echo esc_attr($tier['slug']); ?>" style="width:<?php echo $pct; ?>%"></div>
            </div>
            <?php else : ?>
            <p class="pcp-hero__next">🎉 সর্বোচ্চ Tier!<?php if ( (int) PCP_Settings::get('tier_legend_free_shipping') ) echo ' ফ্রি শিপিং Enjoy করুন'; ?></p>
            <?php endif; ?>
        </div>
    </div>
	
	<div style="display:flex;gap:12px;margin-bottom:4px;flex-wrap:wrap;">
		<div style="flex:1;min-width:140px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px 18px;text-align:center;">
			<div style="font-size:1.5rem;font-weight:800;color:#16a34a;">+<?php echo number_format($total_earned_all); ?></div>
			<div style="font-size:.8rem;color:#6b7280;">মোট অর্জিত পয়েন্ট</div>
		</div>
		<div style="flex:1;min-width:140px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px 18px;text-align:center;">
			<div style="font-size:1.5rem;font-weight:800;color:#dc2626;">−<?php echo number_format($total_redeemed); ?></div>
			<div style="font-size:.8rem;color:#6b7280;">মোট রিডিম করা পয়েন্ট</div>
		</div>
	</div>

    <!-- ── Tiers ───────────────────────────────────────────────── -->
    <h3 class="pcp-section-heading">🏅 Tier System</h3>
    <div class="pcp-tiers">
        <?php foreach ( $tiers as $t ) :
            $active = $tier['slug'] === $t['slug'];
        ?>
        <div class="pcp-tier-card <?php echo $active ? 'pcp-tier-card--active' : ''; ?> pcp-tier-card--<?php echo esc_attr($t['slug']); ?>">
            <?php if ( $active ) : ?><span class="pcp-tier-card__you">আপনি এখানে</span><?php endif; ?>
            <div class="pcp-tier-card__icon"><?php echo esc_html(PCP_Settings::get('tier_' . $t['slug'] . '_icon')); ?></div>
<div class="pcp-tier-card__label"><?php echo esc_html(PCP_Settings::get('tier_' . $t['slug'] . '_name')); ?></div>
            <div class="pcp-tier-card__pts"><?php echo $t['min'] === 0 ? '0+' : number_format($t['min']) . '+'; ?> pts</div>
            <div class="pcp-tier-card__multi"><?php echo esc_html($t['multiplier']); ?> earn</div>
            <div class="pcp-tier-card__perks"><?php echo esc_html($t['perks']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── How to Earn ─────────────────────────────────────────── -->
    <h3 class="pcp-section-heading">💡 পয়েন্ট কীভাবে পাবেন</h3>
    <div class="pcp-earn-grid">
        <div class="pcp-earn-card">
			<span class="pcp-earn-card__icon">🛒</span>
			<strong>অর্ডার করুন</strong>
			<p>প্রতি <?php echo esc_html($base_taka); ?> টাকায় <?php echo esc_html($actual_pts); ?> পয়েন্ট
			<?php if ( $multiplier > 1 ) echo '<small style="color:#7c3aed;"> (' . $multiplier . 'x bonus!)</small>'; ?></p>
		</div>
        <div class="pcp-earn-card">
            <span class="pcp-earn-card__icon">⭐</span>
            <strong>রিভিউ দিন</strong>
            <p>প্রতিটি রিভিউতে <?php echo esc_html(PCP_Settings::get('review_points')); ?> পয়েন্ট</p>
        </div>
        <div class="pcp-earn-card">
            <span class="pcp-earn-card__icon">👥</span>
            <strong>রেফার করুন</strong>
            <p>বন্ধু অ্যাকাউন্ট খুললেে <?php echo $share_pts; ?> + অর্ডার কমপ্লিট করলে আরও <?php echo $purchase_pts; ?> পয়েন্ট</p>
        </div>
        <div class="pcp-earn-card">
			<span class="pcp-earn-card__icon">🎁</span>
			<strong>রেফারেল বোনাস</strong>
			<p>আপনার লিংক দিয়ে বন্ধু অ্যাকাউন্ট খুললেেে <strong>আপনি ও আপনার বন্ধু উভয়ই</strong> <?php echo $friend_pts; ?> পয়েন্ট পাবেন।</p>
		</div>
    </div>

    <!-- ── Referral ────────────────────────────────────────────── -->
    <h3 class="pcp-section-heading">🔗 আপনার রেফারেল লিংক</h3>
    <div class="pcp-referral">
        <p>এই লিংকটি আপনার বন্ধুকে পাঠান, তারা অ্যাকাউন্ট খুলে কেনাকাটা করলে আপনি পয়েন্ট পাবেন।</p>
        <div class="pcp-referral__row">
            <input type="text" readonly id="pcp-ref-input" value="<?php echo esc_attr($ref_url); ?>" onclick="this.select()">
            <button onclick="navigator.clipboard.writeText('<?php echo esc_js($ref_url); ?>');this.textContent=' Copied!';setTimeout(()=>this.textContent='কপি করুন',2000);" class="pcp-referral__btn">কপি করুন</button>
        </div>
    </div>
	
	 <!-- ── FAQ ────────────────────────────────────────────── -->
	<h3 class="pcp-section-heading">❓ সাধারণ প্রশ্নোত্তর</h3>
	<div style="display:flex;flex-direction:column;gap:10px;margin-bottom:8px;">
	<?php
	$faqs = [
		['প্রশ্ন: পয়েন্ট কতদিন পর মেয়াদ শেষ হয়?',
		 'উত্তর: পয়েন্ট অর্জনের ' . PCP_Settings::get('points_expiry_days') . ' দিন পরে মেয়াদ শেষ হয়।'],
		['প্রশ্ন: কীভাবে পয়েন্ট রিডিম করব?',
		 'উত্তর: চেকআউট পেজে "পয়েন্ট দিয়ে ছাড় নিন" বাটনে ক্লিক করলে স্বয়ংক্রিয়ভাবে পয়েন্ট ডিসকাউন্ট প্রযোজ্য হবে।'],
		['প্রশ্ন: ১ পয়েন্ট = কত টাকা?',
		 'উত্তর: ১ পয়েন্ট = ' . PCP_Settings::get('points_to_taka_rate') . ' টাকা।'],
		['প্রশ্ন: একটি অর্ডারে সর্বোচ্চ কত পয়েন্ট ব্যবহার করা যাবে?',
		 'উত্তর: অর্ডার টোটালের সর্বোচ্চ ' . PCP_Settings::get('max_redeem_percent') . '% পয়েন্ট দিয়ে পরিশোধ করা যাবে।'],
		['প্রশ্ন: রেফারেল পয়েন্ট কখন যোগ হবে?',
		 'উত্তর: আপনার বন্ধু অ্যাকাউন্ট খোলার সাথে সাথে ' . PCP_Settings::get('referral_share_points') . ' পয়েন্ট এবং প্রথম অর্ডার কমপ্লিট হলে আরও ' . PCP_Settings::get('referral_purchase_points') . ' পয়েন্ট যোগ হবে।'],
	];
	foreach ( $faqs as $faq ) : ?>
	<details style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;cursor:pointer;">
		<summary style="font-weight:700;font-size:.9rem;color:#111827;list-style:none;display:flex;justify-content:space-between;">
			<?php echo esc_html($faq[0]); ?> <span>▾</span>
		</summary>
		<p style="margin:10px 0 0;font-size:.88rem;color:#4b5563;"><?php echo esc_html($faq[1]); ?></p>
	</details>
	<?php endforeach; ?>
	</div>

    <!-- ── Points History ──────────────────────────────────────── -->
    <h3 class="pcp-section-heading">📋 পয়েন্ট ইতিহাস</h3>

    <?php if ( $history ) : ?>
    <div class="pcp-history" id="pcp-history-list">
        <?php foreach ( $history as $row ) :
            $positive = $row->points > 0;
        ?>
        <div class="pcp-history__row">
            <div class="pcp-history__icon <?php echo $positive ? 'pcp-history__icon--in' : 'pcp-history__icon--out'; ?>">
                <?php echo $positive ? '+' : '−'; ?>
            </div>
            <div class="pcp-history__info">
                <strong><?php echo esc_html($row->description ?: $row->type); ?></strong>
                <small><?php echo date('d M Y', strtotime($row->created_at)); ?><?php echo $row->expires_at ? ' · মেয়াদ: ' . date('d M Y', strtotime($row->expires_at)) : ''; ?></small>
            </div>
            <div class="pcp-history__pts <?php echo $positive ? 'pcp-pts--in' : 'pcp-pts--out'; ?>">
                <?php echo ($positive ? '+' : '') . number_format($row->points); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ( $total_pages > 1 ) : ?>
    <div class="pcp-history-pagination" id="pcp-history-pagination"
         data-current="1"
         data-total="<?php echo $total_pages; ?>"
         data-nonce="<?php echo wp_create_nonce('pcp_nonce'); ?>">
        <button class="pcp-page-btn" id="pcp-history-first" disabled>«« প্রথম</button>
		<button class="pcp-page-btn" id="pcp-history-prev" disabled>← আগে</button>
		<span id="pcp-history-page-info">১ / <?php echo $total_pages; ?></span>
		<button class="pcp-page-btn" id="pcp-history-next">পরে →</button>
    </div>
    <?php endif; ?>

    <?php else : ?>
        <p style="color:#6b7280;">এখনও কোনো পয়েন্ট ইতিহাস নেই।</p>
    <?php endif; ?>

</div>