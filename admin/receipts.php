<?php
require_once __DIR__ . '/../includes/session.php';
requireAdmin();
$pageTitle = 'Receipt Records';
$db = getDB();
$search = sanitizeInput($_GET['q'] ?? '');
$page = max(1,(int)($_GET['page']??1)); $perPage=20;

// Fixed WHERE clause: join through beds
$where = "WHERE p.status='paid'";
if($search) $where .= " AND (u.name ILIKE '%$search%' OR p.reference_number ILIKE '%$search%')";

// Count query (fixed)
$totalStmt = $db->prepare("
    SELECT COUNT(*) 
    FROM bh.payments p 
    JOIN bh.tenants t ON t.id = p.tenant_id
    JOIN bh.users u ON u.id = t.user_id
    $where
");
$totalStmt->execute();
$total = $totalStmt->fetchColumn();
$pager = paginate($total, $perPage, $page);

// Main query with proper joins: tenants -> beds -> rooms -> floors
$payments = $db->prepare("
    SELECT p.*, u.name AS tenant_name, r.room_number, f.floor_number, b.bed_number
    FROM bh.payments p
    JOIN bh.tenants t ON t.id = p.tenant_id
    JOIN bh.users u ON u.id = t.user_id
    JOIN bh.beds b ON b.id = t.bed_id
    JOIN bh.rooms r ON r.id = b.room_id
    JOIN bh.floors f ON f.id = r.floor_id
    $where
    ORDER BY p.payment_date DESC
    LIMIT :limit OFFSET :offset
");
$payments->bindValue(':limit', $perPage, PDO::PARAM_INT);
$payments->bindValue(':offset', $pager['offset'], PDO::PARAM_INT);
$payments->execute();
$payments = $payments->fetchAll();

$totalIncome = $db->query("SELECT COALESCE(SUM(amount),0) FROM bh.payments WHERE status='paid'")->fetchColumn();

include __DIR__.'/../includes/header.php'; 
include __DIR__.'/../includes/admin_nav.php';
?>
<div class="d-flex align-center justify-between mb-4" style="flex-wrap:wrap;gap:12px;">
<div><h2 style="font-family:var(--font-display);font-size:1.4rem;color:var(--white);">Receipt Records</h2><p style="color:var(--muted);font-size:.82rem;">All official paid payment receipts</p></div>
<div class="d-flex gap-2"><button class="btn btn-ghost btn-sm" onclick="exportCSV('rcpTbl','receipts.csv')"><i class="fas fa-download"></i> Export</button></div>
</div>
<div class="stats-grid mb-4" style="grid-template-columns:repeat(3,1fr);">
<div class="stat-card"><div class="stat-icon gold"><i class="fas fa-peso-sign"></i></div><div class="stat-info"><div class="stat-value" style="font-size:1.3rem;"><?= formatCurrency((float)$totalIncome) ?></div><div class="stat-label">Total Collected</div></div></div>
<div class="stat-card"><div class="stat-icon green"><i class="fas fa-file-invoice"></i></div><div class="stat-info"><div class="stat-value"><?= $total ?></div><div class="stat-label">Total Receipts</div></div></div>
<div class="stat-card"><div class="stat-icon blue"><i class="fas fa-calendar-alt"></i></div><div class="stat-info"><div class="stat-value" style="font-size:1rem;"><?= date('F Y') ?></div><div class="stat-label">Current Period</div></div></div>
</div>
<form method="GET" class="d-flex gap-2 mb-4"><div class="search-input-wrap" style="flex:1;"><i class="fas fa-search"></i><input type="text" name="q" class="form-control" placeholder="Search by tenant, reference..." value="<?= e($search) ?>"></div><button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button><?php if($search): ?><a href="?" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i></a><?php endif; ?></form>
<div class="card"><div class="card-header"><span class="card-title">Paid Receipts <span style="font-size:.78rem;color:var(--muted);">(<?= $total ?>)</span></span></div>
<?php if(empty($payments)): ?><div class="empty-state"><i class="fas fa-file-invoice"></i><h3>No Receipts Found</h3></div><?php else: ?>
<div class="table-container"><table id="rcpTbl"><thead><tr><th>Receipt #</th><th>Tenant</th><th>Room</th><th>Month Paid</th><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead><tbody>
<?php foreach($payments as $p): ?>
<tr><td style="font-family:monospace;color:var(--muted);font-size:.78rem;">#<?= str_pad($p['id'],6,'0',STR_PAD_LEFT) ?></td><td><?= e($p['tenant_name']) ?></td><td>F<?= $p['floor_number'] ?> Rm<?= e($p['room_number']) ?></td><td><?= $p['payment_month']?date('M Y',strtotime($p['payment_month'].'-01')):'—' ?></td><td style="font-size:.82rem;"><?= formatDate($p['payment_date'],'M d, Y') ?></td><td style="color:var(--gold);font-weight:700;"><?= formatCurrency($p['amount']) ?></td><td style="text-transform:capitalize;"><?= e(str_replace('_',' ',$p['payment_method'])) ?></td><td style="font-family:monospace;font-size:.76rem;color:var(--muted);"><?= e($p['reference_number']??'—') ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if($pager['total_pages']>1): ?><div style="padding:16px;"><div class="pagination"><?php for($pg=1;$pg<=$pager['total_pages'];$pg++): ?><a href="?q=<?= urlencode($search) ?>&page=<?= $pg ?>" class="page-link <?= $pg==$page?'active':'' ?>"><?= $pg ?></a><?php endfor; ?></div></div><?php endif; ?>
<?php endif; ?>
</div>
</main></div>
<?php include __DIR__.'/../includes/footer.php'; ?>