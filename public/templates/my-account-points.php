<?php if ( ! defined( 'ABSPATH' ) ) exit;
$tiers        = PCP_Tiers::get_all_tiers_display();
$pro_min      = (int) PCP_Settings::get('tier_pro_min');
$legend_min   = (int) PCP_Settings::get('tier_legend_min');
$total_earned = $tier['total'];
$share_pts    = (int) PCP_Settings::get('referral_share_points');
$purchase_pts = (int) PCP_Settings::get('referral_purchase_points');
$friend_pts   = (int) PCP_Settings::get('referred_friend_points');
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
            <div class="pcp-tier-pill pcp-tier-<?php echo $tier['slug']; ?>">
                <?php echo $tier['label']; ?>
            </div>
            <?php
            if ( $tier['slug'] === 'champ' ) :
                $needed = $pro_min - $total_earned;
                $pct    = $pro_min > 0 ? min(100, round(($total_earned / $pro_min) * 100)) : 0;
            elseif ( $tier['slug'] === 'pro' ) :
                $needed = $legend_min - $total_earned;
                $pct    = min(100, round((($total_earned - $pro_min) / ($legend_min - $pro_min)) * 100));
            endif;
            if ( $tier['slug'] !== 'legend' ) : ?>
            <p class="pcp-hero__next">পরের tier-এ আরও <strong><?php echo number_format($needed); ?> pts</strong></p>
            <div class="pcp-progressbar">
                <div class="pcp-progressbar__fill pcp-progressbar__fill--<?php echo $tier['slug']; ?>" style="width:<?php echo $pct; ?>%"></div>
            </div>
            <?php else : ?>
            <p class="pcp-hero__next">🎉 সর্বোচ্চ Tier! ফ্রি শিপিং চালু আছে।</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Tiers ───────────────────────────────────────────────── -->
    <h3 class="pcp-section-heading">🏅 Tier System</h3>
    <div class="pcp-tiers">
        <?php foreach ( $tiers as $t ) :
            $active = $tier['slug'] === $t['slug'];
        ?>
        <div class="pcp-tier-card <?php echo $active ? 'pcp-tier-card--active' : ''; ?> pcp-tier-card--<?php echo $t['slug']; ?>">
            <?php if ($active) : ?><span class="pcp-tier-card__you">আপনি এখানে</span><?php endif; ?>
            <div class="pcp-tier-card__icon"><?php echo $t['label']; ?></div>
            <div class="pcp-tier-card__pts"><?php echo $t['min'] === 0 ? '0+' : number_format($t['min']) . '+'; ?> pts</div>
            <div class="pcp-tier-card__multi"><?php echo $t['multiplier']; ?> earn</div>
            <div class="pcp-tier-card__perks"><?php echo $t['perks']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── How to Earn ─────────────────────────────────────────── -->
    <h3 class="pcp-section-heading">💡 পয়েন্ট কীভাবে পাবেন</h3>
    <div class="pcp-earn-grid">
        <div class="pcp-earn-card">
            <span class="pcp-earn-card__icon">🛒</span>
            <strong>অর্ডার করুন</strong>
            <p>প্রতি <?php echo PCP_Settings::get('taka_per_earn'); ?> টাকায় <?php echo PCP_Settings::get('points_per_taka'); ?> পয়েন্ট</p>
        </div>
        <div class="pcp-earn-card">
            <span class="pcp-earn-card__icon">⭐</span>
            <strong>রিভিউ দিন</strong>
            <p>প্রতিটি রিভিউতে <?php echo PCP_Settings::get('review_points'); ?> পয়েন্ট</p>
        </div>
        <div class="pcp-earn-card">
            <span class="pcp-earn-card__icon">👥</span>
            <strong>রেফার করুন</strong>
            <p>বন্ধু যোগ দিলে <?php echo $share_pts; ?> + কিনলে আরও <?php echo $purchase_pts; ?> পয়েন্ট</p>
        </div>
        <div class="pcp-earn-card">
            <span class="pcp-earn-card__icon">🎁</span>
            <strong>রেফারেল বোনাস</strong>
            <p>রেফারেল লিংকে যোগ দিলে <?php echo $friend_pts; ?> পয়েন্ট</p>
        </div>
    </div>

    <!-- ── Referral ────────────────────────────────────────────── -->
    <h3 class="pcp-section-heading">🔗 আপনার রেফারেল লিংক</h3>
    <div class="pcp-referral">
        <p>বন্ধুকে এই লিংক পাঠান এবং তারা যোগ দিলে ও কিনলে পয়েন্ট পান।</p>
        <div class="pcp-referral__row">
            <input type="text" readonly id="pcp-ref-input" value="<?php echo esc_attr($ref_url); ?>" onclick="this.select()">
            <button onclick="navigator.clipboard.writeText('<?php echo esc_js($ref_url); ?>');this.textContent='✅ Copied!';setTimeout(()=>this.textContent='কপি করুন',2000);" class="pcp-referral__btn">কপি করুন</button>
        </div>
    </div>

    <!-- ── Points History ──────────────────────────────────────── -->
    <h3 class="pcp-section-heading">📋 পয়েন্ট ইতিহাস</h3>
    <?php if ( $history ) : ?>
    <div class="pcp-history">
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
    <?php else : ?>
        <p style="color:#6b7280;">এখনও কোনো পয়েন্ট ইতিহাস নেই।</p>
    <?php endif; ?>

</div>