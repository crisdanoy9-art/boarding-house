<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();

$pageTitle = 'My Dashboard';
$db  = getDB();
$uid = $_SESSION['user_id'];
$user = getCurrentUser();

// Active tenancy (via beds)
$tenancy = $db->prepare("
    SELECT t.*, r.room_number, r.price, f.floor_number, f.floor_name,
           b.bed_number, r.amenities, t.advance_deposit_paid
    FROM bh.tenants t
    JOIN bh.beds b ON b.id = t.bed_id
    JOIN bh.rooms r ON r.id = b.room_id
    JOIN bh.floors f ON f.id = r.floor_id
    WHERE t.user_id = ? AND t.status = 'active'
    LIMIT 1
");
$tenancy->execute([$uid]);
$tenancy = $tenancy->fetch();

// Pending reservation
$pendingRes = $db->prepare("SELECT id,status FROM bh.reservations WHERE user_id=? AND status IN ('pending','approved') ORDER BY created_at DESC LIMIT 1");
$pendingRes->execute([$uid]);
$pendingRes = $pendingRes->fetch();

// Recent reservations (correct join via beds)
$reservations = $db->prepare("
    SELECT res.*, r.room_number, f.floor_number, b.bed_number
    FROM bh.reservations res
    JOIN bh.beds b ON b.id = res.bed_id
    JOIN bh.rooms r ON r.id = b.room_id
    JOIN bh.floors f ON f.id = r.floor_id
    WHERE res.user_id = ?
    ORDER BY res.created_at DESC
    LIMIT 5
");
$reservations->execute([$uid]);
$reservations = $reservations->fetchAll();

// Payments
$payments = null; $totalPaid=0; $totalOverdue=0; $pendingDue=0;
if ($tenancy) {
    $payments = $db->prepare("SELECT * FROM bh.payments WHERE tenant_id=? ORDER BY payment_date DESC LIMIT 5");
    $payments->execute([$tenancy['id']]);
    $payments = $payments->fetchAll();
    foreach ($payments as $p) {
        if ($p['status']==='paid')    $totalPaid    += $p['amount'];
        if ($p['status']==='overdue') $totalOverdue += $p['amount'];
        if ($p['status']==='pending') $pendingDue   += $p['amount'];
    }
    // Count overdue months
    $oStmt = $db->prepare("SELECT COUNT(*) FROM bh.payments WHERE tenant_id=? AND status='overdue'");
    $oStmt->execute([$tenancy['id']]);
    $overdueMonths = (int)$oStmt->fetchColumn();
}

// Settings
function getS($db,$key,$def=''){try{$s=$db->prepare("SELECT value FROM bh.system_settings WHERE key=?");$s->execute([$key]);$r=$s->fetch();return $r?$r['value']:$def;}catch(Exception $e){return $def;}}
$adminPhone    = getS($db,'admin_phone','+63 633 951 825');
$adminFacebook = getS($db,'admin_facebook','https://facebook.com/');
$adminEmail    = getS($db,'admin_email','crisdanoy9@gmail.com');

// Days until next payment (1st of next month)
$daysUntilDue = (int)date('d') <= 1 ? 0 : (int)(strtotime('first day of next month') - time()) / 86400;
$paymentWarning = $daysUntilDue <= 3 && $daysUntilDue >= 0;
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/tenant_nav.php'; ?>

<!-- 3-day payment warning -->
<?php if ($paymentWarning && $tenancy): ?>
<div class="announcement-card ann-danger mb-4">
    <div class="ann-icon"><i class="fas fa-bell"></i></div>
    <div>
        <div class="ann-title">⚠ Payment Due in <?= $daysUntilDue === 0 ? 'TODAY' : $daysUntilDue.' day'.($daysUntilDue>1?'s':'') ?>!</div>
        <div class="ann-body">Monthly rent of <strong>₱1,300</strong> is due on the 1st of every month. Avoid penalties by paying on time.</div>
    </div>
    <a href="<?= APP_URL ?>/user/payments.php" class="btn btn-sm btn-danger" style="flex-shrink:0;">Pay Now</a>
</div>
<?php endif; ?>

<!-- Overdue warning (3 months) -->
<?php if (($overdueMonths ?? 0) >= 2): ?>
<div class="announcement-card ann-danger mb-4">
    <div class="ann-icon"><i class="fas fa-exclamation-triangle"></i></div>
    <div>
        <div class="ann-title">🚨 <?= $overdueMonths ?> Month<?= $overdueMonths>1?'s':'' ?> Overdue — Room at Risk!</div>
        <div class="ann-body">
            After <strong>3 months</strong> of non-payment, your room assignment will be automatically cancelled per house rules.
            <?php if ($overdueMonths >= 3): ?><strong>This is your final warning.</strong><?php endif; ?>
        </div>
    </div>
    <a href="<?= APP_URL ?>/user/payments.php" class="btn btn-sm btn-danger" style="flex-shrink:0;">Settle Now</a>
</div>
<?php endif; ?>

<!-- Welcome banner -->
<div style="background:linear-gradient(135deg,rgba(24,24,42,.97) 0%,rgba(30,30,50,.97) 100%);
            border:1px solid var(--border);border-radius:var(--r-xl);
            padding:30px 34px;margin-bottom:24px;position:relative;overflow:hidden;">
    <div style="position:absolute;right:0;top:0;bottom:0;width:260px;background:radial-gradient(ellipse at right,rgba(201,168,76,.07) 0%,transparent 70%);pointer-events:none;"></div>
    <div style="position:absolute;right:32px;top:50%;transform:translateY(-50%);font-size:6rem;color:rgba(201,168,76,.04);pointer-events:none;"><i class="fas fa-building"></i></div>
    <div style="position:relative;">
        <div style="font-size:.7rem;color:var(--gold);text-transform:uppercase;letter-spacing:.15em;margin-bottom:8px;font-weight:700;">
            <i class="fas fa-circle" style="font-size:.4rem;vertical-align:middle;animation:pulse 2s infinite;"></i> Welcome Back
        </div>
        <h1 style="font-family:var(--font-display);font-size:1.85rem;color:var(--white);margin-bottom:8px;line-height:1.2;"><?= e($user['name']) ?></h1>
        <?php if ($tenancy): ?>
        <p style="color:var(--muted);font-size:.9rem;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <i class="fas fa-map-marker-alt" style="color:var(--gold);"></i>
            Floor <?= $tenancy['floor_number'] ?> — Room <?= e($tenancy['room_number']) ?>, Bed <?= $tenancy['bed_number'] ?>
            <span class="badge badge-success"><i class="fas fa-check-circle"></i> Active Tenant</span>
            <?php if (!($tenancy['advance_deposit_paid']??false)): ?>
            <span class="badge badge-warning"><i class="fas fa-exclamation"></i> Deposit Pending</span>
            <?php endif; ?>
        </p>
        <?php elseif ($pendingRes): ?>
        <p style="color:var(--muted);">
            <i class="fas fa-clock" style="color:var(--warning);"></i>
            Reservation awaiting admin approval.
            <a href="<?= APP_URL ?>/user/reservations.php" style="color:var(--gold);">View status →</a>
        </p>
        <?php else: ?>
        <p style="color:var(--muted);">
            <i class="fas fa-info-circle"></i> No active room.
            <a href="<?= APP_URL ?>/user/book_room.php" style="color:var(--gold);font-weight:600;">Book one now →</a>
        </p>
        <?php endif; ?>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
    <div class="stat-card">
        <div class="stat-icon gold"><i class="fas fa-door-open"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $tenancy ? 'Rm '.$tenancy['room_number'] : '—' ?></div>
            <div class="stat-label">My Room</div>
            <?php if ($tenancy): ?><div class="stat-sub"><i class="fas fa-layer-group"></i> Floor <?= $tenancy['floor_number'] ?></div><?php endif; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-peso-sign"></i></div>
        <div class="stat-info">
            <div class="stat-value" style="font-size:1.35rem;">₱1,300</div>
            <div class="stat-label">Monthly Rate</div>
            <div class="stat-sub">Due every 1st</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold"><i class="fas fa-receipt"></i></div>
        <div class="stat-info">
            <div class="stat-value" style="font-size:1.2rem;"><?= formatCurrency($totalPaid) ?></div>
            <div class="stat-label">Total Paid</div>
            <?php if ($totalOverdue>0): ?><div style="font-size:.75rem;color:var(--danger);margin-top:4px;"><i class="fas fa-exclamation-triangle"></i> <?= formatCurrency($totalOverdue) ?> overdue</div><?php endif; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $daysUntilDue<=3?'red':'blue' ?>"><i class="fas fa-calendar-day"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $daysUntilDue === 0 ? 'TODAY' : $daysUntilDue.'d' ?></div>
            <div class="stat-label">Until Next Due</div>
            <div class="stat-sub">1st of the month</div>
        </div>
    </div>
</div>

<!-- Two-column grid: My Room (left) and Contact Us (right) -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px;">
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-home" style="color:var(--gold);font-size:.85rem;"></i> My Room</span>
            <?php if (!$tenancy): ?>
            <a href="<?= APP_URL ?>/user/book_room.php" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Book</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($tenancy): ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div><div class="form-label">Floor</div><div style="color:var(--white);font-weight:500;"><?= e($tenancy['floor_name']) ?></div></div>
                <div><div class="form-label">Room</div><div style="color:var(--gold);font-weight:700;font-family:var(--font-display);font-size:1.3rem;"><?= e($tenancy['room_number']) ?></div></div>
                <div><div class="form-label">Bed</div><div style="color:var(--white);">Bed <?= $tenancy['bed_number'] ?></div></div>
                <div><div class="form-label">Monthly Rate</div><div style="color:var(--gold);font-weight:700;">₱1,300</div></div>
                <div><div class="form-label">Advance Deposit</div>
                    <span class="badge badge-<?= ($tenancy['advance_deposit_paid']??false)?'success':'warning' ?>">
                        <?= ($tenancy['advance_deposit_paid']??false)?'✓ Paid':'Pending' ?>
                    </span>
                </div>
                <div><div class="form-label">Status</div><span class="badge badge-success"><i class="fas fa-check-circle"></i> Active</span></div>
            </div>
            <?php if ($tenancy['amenities']): ?>
            <div style="margin-top:16px;padding-top:14px;border-top:1px solid rgba(255,255,255,.05);">
                <div class="form-label mb-2">Amenities</div>
                <div class="room-amenities">
                    <?php foreach (explode(',',$tenancy['amenities']) as $a): ?>
                    <span class="amenity-tag"><i class="fas fa-check" style="font-size:.55rem;"></i> <?= e(trim($a)) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="empty-state" style="padding:28px 0;">
                <i class="fas fa-bed"></i><h3>No Active Room</h3>
                <a href="<?= APP_URL ?>/user/book_room.php" class="btn btn-primary" style="margin-top:14px;"><i class="fas fa-search"></i> Browse Rooms</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-headset" style="color:var(--gold);font-size:.85rem;"></i> Contact Us</span>
            <span class="badge badge-success" style="font-size:.65rem;"><i class="fas fa-circle" style="font-size:.4rem;"></i> Available</span>
        </div>
        <div class="card-body" style="padding:18px 20px;">
            <p style="color:var(--muted);font-size:.83rem;margin-bottom:16px;">For concerns, maintenance requests, or payment queries:</p>
            <div style="display:grid;gap:10px;">
                <a href="tel:<?= e($adminPhone) ?>" style="display:flex;align-items:center;gap:12px;padding:11px 14px;background:rgba(62,207,110,.06);border:1px solid rgba(62,207,110,.14);border-radius:var(--r-md);color:var(--text);font-size:.85rem;transition:var(--t);"
                   onmouseover="this.style.borderColor='rgba(62,207,110,.3)'" onmouseout="this.style.borderColor='rgba(62,207,110,.14)'">
                    <div style="width:32px;height:32px;background:rgba(62,207,110,.12);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-phone" style="color:var(--success);font-size:.85rem;"></i></div>
                    <div><div style="font-size:.7rem;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;">Phone / WhatsApp</div><div style="font-weight:600;color:var(--white);"><?= e($adminPhone) ?></div></div>
                </a>
                <a href="<?= e($adminFacebook) ?>" target="_blank" style="display:flex;align-items:center;gap:12px;padding:11px 14px;background:rgba(24,119,242,.06);border:1px solid rgba(24,119,242,.14);border-radius:var(--r-md);color:var(--text);font-size:.85rem;transition:var(--t);">
                    <div style="width:32px;height:32px;background:rgba(24,119,242,.12);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fab fa-facebook-messenger" style="color:#00b2ff;font-size:.85rem;"></i></div>
                    <div><div style="font-size:.7rem;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;">Facebook Messenger</div><div style="font-weight:600;color:var(--white);">Message on Facebook</div></div>
                </a>
                <a href="mailto:<?= e($adminEmail) ?>" style="display:flex;align-items:center;gap:12px;padding:11px 14px;background:rgba(201,168,76,.05);border:1px solid rgba(201,168,76,.14);border-radius:var(--r-md);color:var(--text);font-size:.85rem;transition:var(--t);">
                    <div style="width:32px;height:32px;background:rgba(201,168,76,.1);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-envelope" style="color:var(--gold);font-size:.85rem;"></i></div>
                    <div><div style="font-size:.7rem;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;">Email</div><div style="font-weight:600;color:var(--white);font-size:.83rem;"><?= e($adminEmail) ?></div></div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Recent payments -->
<?php if ($payments && !empty($payments)): ?>
<div class="card mb-4">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-receipt" style="color:var(--gold);font-size:.85rem;"></i> Recent Payments</span>
        <a href="<?= APP_URL ?>/user/payments.php" class="btn btn-sm btn-ghost">View All + Pay</a>
    </div>
    <div class="table-container">
        <table>
            <thead><tr><th>Date</th><th>Month</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?= formatDate($p['payment_date']) ?></td>
                    <td><?= e($p['payment_month']??'—') ?></td>
                    <td style="color:var(--gold);font-weight:700;"><?= formatCurrency($p['amount']) ?></td>
                    <td style="text-transform:capitalize;"><?= e(str_replace('_',' ',$p['payment_method'])) ?></td>
                    <td><?php $bm=['paid'=>'success','pending'=>'warning','overdue'=>'danger']; ?>
                        <span class="badge badge-<?= $bm[$p['status']]??'muted' ?>"><?= ucfirst($p['status']) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Reservations card – full width -->
<div class="card mb-4">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-calendar-check" style="color:var(--gold);font-size:.85rem;"></i> Reservations</span>
        <a href="<?= APP_URL ?>/user/reservations.php" class="btn btn-sm btn-ghost">All</a>
    </div>
    <?php if (empty($reservations)): ?>
    <div class="empty-state" style="padding:28px;"><i class="fas fa-calendar-times"></i><h3>No Reservations</h3></div>
    <?php else: ?>
    <?php foreach ($reservations as $res):
        $bm=['pending'=>'warning','approved'=>'success','rejected'=>'danger','cancelled'=>'muted']; ?>
    <div style="display:flex;align-items:center;gap:12px;padding:13px 22px;border-bottom:1px solid rgba(255,255,255,.04);">
        <div style="width:7px;height:7px;border-radius:50%;flex-shrink:0;background:<?= ['warning'=>'var(--warning)','success'=>'var(--success)','danger'=>'var(--danger)','muted'=>'var(--muted)'][$bm[$res['status']]??'muted'] ?>"></div>
        <div style="flex:1;min-width:0;">
            <div style="font-weight:500;color:var(--white);font-size:.86rem;">F<?= $res['floor_number'] ?> Rm<?= e($res['room_number']) ?> Bed<?= $res['bed_number'] ?></div>
            <div style="font-size:.73rem;color:var(--muted);"><?= formatDate($res['created_at'],'M d, Y') ?></div>
        </div>
        <span class="badge badge-<?= $bm[$res['status']]??'muted' ?>"><?= ucfirst($res['status']) ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Dashboard Footer -->
<div style="text-align:center; margin-top:32px; padding:20px 0; border-top:1px solid var(--border); color:var(--muted); font-size:.78rem;">
    <p>© <?= date('Y') ?> Nadelas Boarding House · All Rights Reserved</p>
</div>

<style>
@keyframes pulse{0%,100%{opacity:.8}50%{opacity:1}}
</style>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>