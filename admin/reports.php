<?php
require_once __DIR__ . '/../includes/session.php';
requireAdmin();

$pageTitle = 'Reports';
$db = getDB();

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

// Monthly income per month
$monthlyIncome = $db->query("
    SELECT TO_CHAR(payment_date,'YYYY-MM') AS ym,
           TO_CHAR(payment_date,'Month YYYY') AS label,
           SUM(amount) AS total,
           COUNT(*) AS count
    FROM bh.payments
    WHERE status='paid' AND EXTRACT(YEAR FROM payment_date)=$year
    GROUP BY TO_CHAR(payment_date,'YYYY-MM'), TO_CHAR(payment_date,'Month YYYY')
    ORDER BY ym
")->fetchAll();

// Occupancy per floor
$floorStats = $db->query("
    SELECT f.floor_number, f.floor_name,
           COUNT(r.id) AS rooms,
           COUNT(b.id) AS total_beds,
           SUM(CASE WHEN b.status='occupied' THEN 1 ELSE 0 END) AS occupied,
           ROUND(SUM(CASE WHEN b.status='occupied' THEN 1 ELSE 0 END)*100.0/NULLIF(COUNT(b.id),0),1) AS rate
    FROM bh.floors f
    LEFT JOIN bh.rooms r ON r.floor_id=f.id
    LEFT JOIN bh.beds b ON b.room_id=r.id
    GROUP BY f.id, f.floor_number, f.floor_name
    ORDER BY f.floor_number
")->fetchAll();

// Most occupied rooms
$topRooms = $db->query("
    SELECT r.room_number, f.floor_number,
           COUNT(b.id) AS total_beds,
           SUM(CASE WHEN b.status='occupied' THEN 1 ELSE 0 END) AS occupied
    FROM bh.rooms r
    JOIN bh.floors f ON f.id=r.floor_id
    LEFT JOIN bh.beds b ON b.room_id=r.id
    GROUP BY r.id, r.room_number, f.floor_number
    ORDER BY occupied DESC
    LIMIT 10
")->fetchAll();

// Tenant list
$tenantList = $db->query("
    SELECT u.name, u.email, u.phone,
           r.room_number, f.floor_number, b.bed_number, r.price,
           t.move_in_date,
           (SELECT SUM(amount) FROM bh.payments p WHERE p.tenant_id=t.id AND p.status='paid') AS total_paid,
           (SELECT SUM(amount) FROM bh.payments p WHERE p.tenant_id=t.id AND p.status='pending') AS total_pending
    FROM bh.tenants t
    JOIN bh.users u ON u.id=t.user_id
    JOIN bh.rooms r ON r.id=t.room_id
    JOIN bh.floors f ON f.id=r.floor_id
    JOIN bh.beds b ON b.id=t.bed_id
    WHERE t.status='active'
    ORDER BY f.floor_number, r.room_number
")->fetchAll();

// Yearly total
$yearTotal = array_sum(array_column($monthlyIncome, 'total'));

// Chart data
$incomeLabels = array_map(fn($r) => trim($r['label']), $monthlyIncome);
$incomeValues = array_map(fn($r) => (float)$r['total'], $monthlyIncome);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_nav.php'; ?>

<!-- Year selector -->
<div class="d-flex align-center justify-between mb-4" style="flex-wrap:wrap;gap:12px;">
    <div class="d-flex gap-2 align-center">
        <label class="form-label mb-0">Year:</label>
        <form method="GET" class="d-flex gap-2">
            <select name="year" class="form-control" style="width:120px;" onchange="this.form.submit()">
                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                <option value="<?= $y ?>" <?= $y==$year?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-ghost" onclick="window.print()">
            <i class="fas fa-print"></i> Print Report
        </button>
        <button class="btn btn-sm btn-outline" onclick="exportCSV('tenantListTable','tenant-report.csv')">
            <i class="fas fa-download"></i> Export Tenant List
        </button>
    </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid mb-4" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card">
        <div class="stat-icon gold"><i class="fas fa-peso-sign"></i></div>
        <div class="stat-info">
            <div class="stat-value" style="font-size:1.4rem;"><?= formatCurrency($yearTotal) ?></div>
            <div class="stat-label"><?= $year ?> Total Income</div>
        </div>
    </div>
    <?php foreach ($floorStats as $fs): ?>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-building"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $fs['rate'] ?>%</div>
            <div class="stat-label">Floor <?= $fs['floor_number'] ?> Occupancy</div>
            <div class="stat-sub"><?= $fs['occupied'] ?>/<?= $fs['total_beds'] ?> beds</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Income Chart -->
<div class="card mb-4">
    <div class="card-header">
        <span class="card-title">Monthly Income — <?= $year ?></span>
        <span style="color:var(--clr-gold);font-weight:600;"><?= formatCurrency($yearTotal) ?> total</span>
    </div>
    <div class="card-body">
        <div class="chart-container" style="height:260px;">
            <canvas id="incomeChart"></canvas>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
    <!-- Monthly Breakdown Table -->
    <div class="card">
        <div class="card-header"><span class="card-title">Monthly Breakdown</span></div>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Month</th><th>Payments</th><th>Income</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($monthlyIncome)): ?>
                    <tr><td colspan="3" class="text-center text-muted" style="padding:20px;">No data</td></tr>
                    <?php else: ?>
                    <?php foreach ($monthlyIncome as $m): ?>
                    <tr>
                        <td><?= e(trim($m['label'])) ?></td>
                        <td><?= $m['count'] ?></td>
                        <td style="color:var(--clr-gold);font-weight:600;"><?= formatCurrency($m['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="border-top:2px solid var(--clr-gold);">
                        <td colspan="2" style="font-weight:600;color:var(--clr-white);">Total</td>
                        <td style="color:var(--clr-gold);font-weight:700;"><?= formatCurrency($yearTotal) ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Rooms -->
    <div class="card">
        <div class="card-header"><span class="card-title">Room Occupancy</span></div>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Room</th><th>Floor</th><th>Beds</th><th>Rate</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($topRooms as $tr): ?>
                    <?php $rate = $tr['total_beds'] > 0 ? round($tr['occupied']/$tr['total_beds']*100) : 0; ?>
                    <tr>
                        <td>Rm <?= e($tr['room_number']) ?></td>
                        <td><?= $tr['floor_number'] ?></td>
                        <td><?= $tr['occupied'] ?>/<?= $tr['total_beds'] ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="flex:1;height:6px;background:rgba(255,255,255,0.06);border-radius:3px;overflow:hidden;">
                                    <div style="height:100%;width:<?= $rate ?>%;background:var(--clr-gold);border-radius:3px;"></div>
                                </div>
                                <span style="font-size:0.78rem;"><?= $rate ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Tenant List -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Active Tenant List</span>
        <span style="font-size:0.82rem;color:var(--clr-muted);"><?= count($tenantList) ?> tenants</span>
    </div>
    <div class="table-container">
        <table id="tenantListTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Floor</th>
                    <th>Room</th>
                    <th>Bed</th>
                    <th>Monthly Rate</th>
                    <th>Move-In</th>
                    <th>Total Paid</th>
                    <th>Pending</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tenantList as $t): ?>
                <tr>
                    <td><?= e($t['name']) ?></td>
                    <td style="font-size:0.8rem;">
                        <div><?= e($t['email']) ?></div>
                        <div style="color:var(--clr-muted);"><?= e($t['phone']??'') ?></div>
                    </td>
                    <td><?= $t['floor_number'] ?></td>
                    <td><?= e($t['room_number']) ?></td>
                    <td><?= $t['bed_number'] ?></td>
                    <td style="color:var(--clr-gold);"><?= formatCurrency($t['price']) ?></td>
                    <td><?= formatDate($t['move_in_date']) ?></td>
                    <td style="color:var(--clr-success);"><?= formatCurrency((float)($t['total_paid']??0)) ?></td>
                    <td style="color:var(--clr-warning);"><?= formatCurrency((float)($t['total_pending']??0)) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
window.incomeData = {
    labels: <?= json_encode($incomeLabels) ?>,
    values: <?= json_encode($incomeValues) ?>
};
</script>

</main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
