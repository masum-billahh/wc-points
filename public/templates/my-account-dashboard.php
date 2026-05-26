<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
// Variables available: $user_id, $balance, $taka, $tier, $history, $ref_url
$tiers = PCP_Tiers::get_all_tiers_display();
?>

<div class="pcp-dashboard">

    <!-- Balance Card -->
    <div class="pcp-balance-card">
        <div>
            <div class="pcp-points-num"><?php echo number_format($balance); ?></div>
            <div class="pcp-points-label">পয়েন্ট ব্যালেন্স</div>
            <div class="pcp-taka-val">≈ <?php echo number_format($taka, 0); ?> টাকা ছাড়</div>
        </div>
        <div class="pcp-tier-badge"><?php echo $tier['label']; ?></div>
    </div>

    <!-- Tier Progression -->
    <div class="pcp-section-title">🏅 Tier System</div>
    <table class="pcp-tier-table">
        <thead>
            <tr>
                <th>Tier</th>
                <th>Points Needed</th>
                <th>Multiplier</th>
                <th>Perks</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $tiers as $t ) : ?>
            <tr class="<?php echo $tier['slug'] === $t['slug'] ? 'pcp-current-tier' : ''; ?>">
                <td><?php echo $t['label']; ?></td>
                <td>
                    <?php if ( $t['min'] === 0 ) : ?>
                        শুরু থেকে
                    <?php else : ?>
                        <?php echo number_format($t['min']); ?>+
                    <?php endif; ?>
                </td>
                <td><?php echo $t['multiplier']; ?></td>
                <td><?php echo $t['perks']; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php
    // Progress to next tier
    $pro_min    = (int) PCP_Settings::get('tier_pro_min');
    $legend_min = (int) PCP_Settings::get('tier_legend_min');
    $total_earned = $tier['total'];

    if ( $tier['slug'] === 'champ' ) :
        $needed = $pro_min - $total_earned;
        $pct    = min( 100, round( ($total_earned / $pro_min) * 100 ) );
        ?>
        <p>🥈 Pro Champ হতে আরও <strong><?php echo number_format($needed); ?> পয়েন্ট</strong> দরকার।</p>
        <div style="background:#e5e7eb;border-radius:999px;height:10px;margin-bottom:20px;">
            <div style="background:#f59e0b;width:<?php echo $pct; ?>%;height:100%;border-radius:999px;"></div>
        </div>
    <?php elseif ( $tier['slug'] === 'pro' ) :
        $needed = $legend_min - $total_earned;
        $pct    = min( 100, round( (($total_earned - $pro_min) / ($legend_min - $pro_min)) * 100 ) );
        ?>
        <p>🥇 Legend হতে আরও <strong><?php echo number_format($needed); ?> পয়েন্ট</strong> দরকার।</p>
        <div style="background:#e5e7eb;border-radius:999px;height:10px;margin-bottom:20px;">
            <div style="background:#7c3aed;width:<?php echo $pct; ?>%;height:100%;border-radius:999px;"></div>
        </div>
    <?php else : ?>
        <p>🥇 আপনি সর্বোচ্চ Legend Tier এ আছেন! ফ্রি শিপিং উপভোগ করুন।</p>
    <?php endif; ?>

    <!-- Points History -->
    <div class="pcp-section-title">📋 পয়েন্ট ইতিহাস</div>
    <?php if ( $history ) : ?>
    <table class="pcp-history-table">
        <thead>
            <tr>
                <th>তারিখ</th>
                <th>পয়েন্ট</th>
                <th>বিবরণ</th>
                <th>মেয়াদ</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $history as $row ) : ?>
            <tr>
                <td><?php echo esc_html( date('d M Y', strtotime($row->created_at)) ); ?></td>
                <td class="<?php echo $row->points > 0 ? 'pcp-pts-positive' : 'pcp-pts-negative'; ?>">
                    <?php echo ($row->points > 0 ? '+' : '') . number_format($row->points); ?>
                </td>
                <td><?php echo esc_html($row->description ?: $row->type); ?></td>
                <td><?php echo $row->expires_at ? esc_html(date('d M Y', strtotime($row->expires_at))) : '∞'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?>
        <p>এখনও কোনো পয়েন্ট ইতিহাস নেই।</p>
    <?php endif; ?>

    <!-- Referral -->
    <div class="pcp-referral-box">
        <h3>🎁 বন্ধুকে রেফার করুন</h3>
        <?php
        $share_pts    = (int) PCP_Settings::get('referral_share_points');
        $purchase_pts = (int) PCP_Settings::get('referral_purchase_points');
        ?>
        <p>
            বন্ধু আপনার লিংক দিয়ে যোগ দিলে <strong><?php echo $share_pts; ?> পয়েন্ট</strong>,<br>
            আর প্রথম অর্ডার দিলে আরও <strong><?php echo $purchase_pts; ?> পয়েন্ট</strong> পাবেন!
        </p>
        <input type="text" readonly value="<?php echo esc_attr($ref_url); ?>"
               onclick="this.select()"
               style="width:100%;padding:8px;margin:8px 0;border:1px solid #a78bfa;border-radius:6px;font-size:.9rem;background:#fff;">
        <button
            onclick="navigator.clipboard.writeText('<?php echo esc_js($ref_url); ?>');this.textContent='✅ Copied!';setTimeout(()=>this.textContent='লিংক কপি করুন',2000);"
            class="button"
            style="background:#7c3aed;color:#fff;border-color:#7c3aed;">
            লিংক কপি করুন
        </button>
    </div>

</div>
