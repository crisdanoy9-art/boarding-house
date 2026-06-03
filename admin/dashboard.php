<?php
require_once __DIR__ . '/../includes/session.php';
requireAdmin();

$pageTitle = 'Dashboard';

try {
    $db = getDB();
    $stats = $db->query("
        SELECT
            (SELECT COUNT(*) FROM bh.rooms)                          AS total_rooms,
            (SELECT COUNT(*) FROM bh.rooms WHERE status='available') AS available_rooms,
            (SELECT COUNT(*) FROM bh.rooms WHERE status='full')      AS full_rooms,
            (SELECT COUNT(*) FROM bh.tenants WHERE status='active')  AS active_tenants,
            (SELECT COUNT(*) FROM bh.reservations WHERE status='pending') AS pending_reservations,
            (SELECT COUNT(*) FROM bh.beds WHERE status='available')  AS available_beds,
            (SELECT COUNT(*) FROM bh.beds WHERE status='occupied')   AS occupied_beds,
            (SELECT COALESCE(SUM(amount),0) FROM bh.payments WHERE status='paid'
                AND DATE_TRUNC('month',payment_date)=DATE_TRUNC('month',CURRENT_DATE)) AS monthly_income,
            (SELECT COALESCE(SUM(amount),0) FROM bh.payments WHERE status='overdue') AS overdue_total
    ")->fetch();

    $floorOccupancy = $db->query("
        SELECT f.floor_number, f.floor_name,
               COUNT(b.id) AS total_beds,
               SUM(CASE WHEN b.status='occupied' THEN 1 ELSE 0 END) AS occupied,
               SUM(CASE WHEN b.status='available' THEN 1 ELSE 0 END) AS available
        FROM bh.floors f
        JOIN bh.rooms r ON r.floor_id=f.id
        JOIN bh.beds b  ON b.room_id=r.id
        GROUP BY f.id, f.floor_number, f.floor_name
        ORDER BY f.floor_number
    ")->fetchAll();

    $recentReservations = $db->query("
        SELECT res.id, u.name AS tenant_name, r.room_number, f.floor_number,
               b.bed_number, res.move_in_date, res.status, res.created_at,
               res.notes
        FROM bh.reservations res
        JOIN bh.users u ON u.id=res.user_id
        JOIN bh.rooms r ON r.id=res.room_id
        JOIN bh.floors f ON f.id=r.floor_id
        JOIN bh.beds b  ON b.id=res.bed_id
        ORDER BY CASE res.status WHEN 'pending' THEN 0 ELSE 1 END, res.created_at DESC
        LIMIT 8
    ")->fetchAll();

    $monthlyIncome = $db->query("
        SELECT TO_CHAR(payment_date,'Mon YY') AS month_label, SUM(amount) AS total
        FROM bh.payments
        WHERE status='paid' AND payment_date >= CURRENT_DATE - INTERVAL '6 months'
        GROUP BY DATE_TRUNC('month',payment_date), TO_CHAR(payment_date,'Mon YY')
        ORDER BY DATE_TRUNC('month',payment_date)
    ")->fetchAll();

    $roomStatus = $db->query("SELECT status, COUNT(*) AS cnt FROM bh.rooms GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Overdue tenants
    $overdueCount = $db->query("SELECT COUNT(DISTINCT tenant_id) FROM bh.payments WHERE status='overdue'")->fetchColumn();

    // Recent GCash reservations with refs
    $gcashPending = $db->query("
        SELECT res.id, u.name, res.notes, res.created_at, r.room_number, b.bed_number
        FROM bh.reservations res
        JOIN bh.users u ON u.id=res.user_id
        JOIN bh.rooms r ON r.id=res.room_id
        JOIN bh.beds b  ON b.id=res.bed_id
        WHERE res.status='pending' AND res.notes ILIKE 'GCASH_REF:%'
        ORDER BY res.created_at DESC LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    $stats = []; $floorOccupancy = []; $recentReservations = [];
    $monthlyIncome = []; $roomStatus = []; $overdueCount = 0; $gcashPending = [];
    error_log($e->getMessage());
}

$chartLabels   = array_column($floorOccupancy, 'floor_name') ?: ['Floor 1','Floor 2','Floor 3'];
$chartOccupied = array_column($floorOccupancy, 'occupied')   ?: [0,0,0];
$chartAvail    = array_column($floorOccupancy, 'available')  ?: [0,0,0];
$incomeLabels  = array_column($monthlyIncome, 'month_label');
$incomeValues  = array_map(fn($r) => (float)$r['total'], $monthlyIncome);
$chartStatus   = [(int)($roomStatus['available']??0),(int)($roomStatus['full']??0),(int)($roomStatus['maintenance']??0)];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_nav.php'; ?>

<!-- Stats Grid -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));margin-bottom:22px;">
    <div class="stat-card">
        <div class="stat-icon gold"><i class="fas fa-door-open"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $stats['total_rooms']??0 ?></div>
            <div class="stat-label">Total Rooms</div>
            <div class="stat-sub"><?= $stats['available_rooms']??0 ?> available</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $stats['active_tenants']??0 ?></div>
            <div class="stat-label">Active Tenants</div>
            <div class="stat-sub"><?= $stats['occupied_beds']??0 ?> beds occupied</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $stats['pending_reservations']??0 ?></div>
            <div class="stat-label">Pending Reservations</div>
            <div class="stat-sub">Awaiting approval</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold"><i class="fas fa-peso-sign"></i></div>
        <div class="stat-info">
            <div class="stat-value" style="font-size:1.3rem;"><?= formatCurrency((float)($stats['monthly_income']??0)) ?></div>
            <div class="stat-label">This Month</div>
            <div class="stat-sub">Paid income</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $overdueCount ?></div>
            <div class="stat-label">Overdue Tenants</div>
            <div class="stat-sub"><?= formatCurrency((float)($stats['overdue_total']??0)) ?> total</div>
        </div>
    </div>
</div>

<!-- GCash Pending Verifications -->
<?php if (!empty($gcashPending)): ?>
<div class="card mb-4" style="border-color:rgba(0,100,200,.3);">
    <div class="card-header" style="background:rgba(0,60,160,.08);">
        <span class="card-title">
            <span style="color:#5ecfff;font-size:1rem;margin-right:7px;">📱</span>
            GCash Payment Verifications Needed
            <span class="badge" style="background:var(--info);color:#fff;margin-left:8px;"><?= count($gcashPending) ?></span>
        </span>
        <a href="<?= APP_URL ?>/admin/reservations.php?status=pending" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div style="padding:4px 0;">
        <?php foreach ($gcashPending as $gp):
            preg_match('/GCASH_REF:\s*([^\s|]+)/', $gp['notes']??'', $m);
            $ref = $m[1] ?? '—';
        ?>
        <div style="display:flex;align-items:center;gap:14px;padding:12px 22px;border-bottom:1px solid var(--border);">
            <div style="width:36px;height:36px;background:linear-gradient(135deg,#0060a0,#003566);border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;color:#5ecfff;font-size:.85rem;flex-shrink:0;">G</div>
            <div style="flex:1;">
                <div style="font-weight:600;color:var(--white);font-size:.88rem;"><?= e($gp['name']) ?></div>
                <div style="font-size:.76rem;color:var(--muted);">
                    Rm <?= e($gp['room_number']) ?>, Bed <?= $gp['bed_number'] ?> &bull;
                    GCash Ref: <strong style="color:#5ecfff;font-family:monospace;"><?= e($ref) ?></strong>
                </div>
            </div>
            <div style="font-size:.75rem;color:var(--muted);"><?= formatDate($gp['created_at'],'M d, H:i') ?></div>
            <a href="<?= APP_URL ?>/admin/reservations.php?id=<?= $gp['id'] ?>" class="btn btn-sm btn-primary">Verify & Approve</a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Charts -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:22px;margin-bottom:22px;">
    <div class="card">
        <div class="card-header"><span class="card-title">Occupancy by Floor</span></div>
        <div class="card-body"><div class="chart-container"><canvas id="occupancyChart"></canvas></div></div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Room Status</span></div>
        <div class="card-body"><div class="chart-container"><canvas id="roomStatusChart"></canvas></div></div>
    </div>
</div>

<!-- Income Chart -->
<div class="card mb-4">
    <div class="card-header">
        <span class="card-title">Monthly Income — Last 6 Months</span>
        <a href="<?= APP_URL ?>/admin/reports.php" class="btn btn-sm btn-outline">Full Report</a>
    </div>
    <div class="card-body">
        <div class="chart-container" style="height:220px;"><canvas id="incomeChart"></canvas></div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-4">
    <div class="card-header"><span class="card-title"><i class="fas fa-zap" style="color:var(--gold);margin-right:6px;"></i>Quick Actions</span></div>
    <div class="card-body">
        <div class="quick-actions-grid">
            <a href="<?= APP_URL ?>/admin/reservations.php?status=pending" class="quick-action-card">
                <i class="fas fa-calendar-check"></i>
                <strong>Reservations</strong>
                <span><?= $stats['pending_reservations']??0 ?> pending</span>
            </a>
            <a href="<?= APP_URL ?>/admin/payments.php" class="quick-action-card">
                <i class="fas fa-credit-card"></i>
                <strong>Record Payment</strong>
                <span>Add new payment</span>
            </a>
            <a href="<?= APP_URL ?>/admin/announcements.php" class="quick-action-card">
                <i class="fas fa-bullhorn"></i>
                <strong>Announcement</strong>
                <span>Post notice</span>
            </a>
            <a href="<?= APP_URL ?>/admin/tenants.php" class="quick-action-card">
                <i class="fas fa-users"></i>
                <strong>Tenants</strong>
                <span><?= $stats['active_tenants']??0 ?> active</span>
            </a>
            <a href="<?= APP_URL ?>/admin/receipts.php" class="quick-action-card">
                <i class="fas fa-file-invoice"></i>
                <strong>Receipts</strong>
                <span>View all records</span>
            </a>
            <a href="<?= APP_URL ?>/admin/maintenance.php" class="quick-action-card">
                <i class="fas fa-tools"></i>
                <strong>Maintenance</strong>
                <span>System settings</span>
            </a>
        </div>
    </div>
</div>

<!-- Recent Reservations Table -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Recent Reservations</span>
        <a href="<?= APP_URL ?>/admin/reservations.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <?php if (empty($recentReservations)): ?>
    <div class="empty-state"><i class="fas fa-calendar-times"></i><h3>No Reservations Yet</h3></div>
    <?php else: ?>
    <div class="table-container">
        <table>
            <thead>
                <tr><th>#</th><th>Tenant</th><th>Room</th><th>Bed</th><th>Move-In</th><th>GCash Ref</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($recentReservations as $res):
                    $badgeMap = ['pending'=>'warning','approved'=>'success','rejected'=>'danger','cancelled'=>'muted'];
                    $badge = $badgeMap[$res['status']] ?? 'muted';
                    preg_match('/GCASH_REF:\s*([^\s|]+)/', $res['notes']??'', $rm);
                    $gcashRef = $rm[1] ?? '';
                ?>
                <tr>
                    <td style="color:var(--muted);font-size:.78rem;">#<?= $res['id'] ?></td>
                    <td style="font-weight:500;"><?= e($res['tenant_name']) ?></td>
                    <td>F<?= $res['floor_number'] ?>-<?= e($res['room_number']) ?></td>
                    <td>Bed <?= $res['bed_number'] ?></td>
                    <td style="font-size:.82rem;"><?= formatDate($res['move_in_date'],'M d, Y') ?></td>
                    <td>
                        <?php if ($gcashRef): ?>
                        <span style="font-family:monospace;font-size:.76rem;color:#5ecfff;background:rgba(0,60,160,.15);padding:2px 7px;border-radius:4px;">
                            📱 <?= e($gcashRef) ?>
                        </span>
                        <?php else: ?>
                        <span style="color:var(--muted);font-size:.76rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?= $badge ?>"><?= ucfirst($res['status']) ?></span></td>
                    <td>
                        <a href="<?= APP_URL ?>/admin/reservations.php" class="btn btn-sm btn-ghost">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
window.occupancyData = { labels:<?= json_encode($chartLabels) ?>, occupied:<?= json_encode($chartOccupied) ?>, available:<?= json_encode($chartAvail) ?> };
window.roomStatusData = <?= json_encode($chartStatus) ?>;
window.incomeData = { labels:<?= json_encode($incomeLabels) ?>, values:<?= json_encode($incomeValues) ?> };
</script>

</main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
