<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();
$pageTitle = 'My Payments';
$db = getDB();
$uid = $_SESSION['user_id'];
$tenant = $db->prepare("SELECT t.id,r.price FROM bh.tenants t JOIN bh.rooms r ON r.id=t.room_id WHERE t.user_id=? AND t.status='active'");
$tenant->execute([$uid]); $tenant = $tenant->fetch();
$payments = [];
$totalPaid = $totalPending = $totalOverdue = 0;
if ($tenant) {
    $stmt = $db->prepare("SELECT * FROM bh.payments WHERE tenant_id=? ORDER BY payment_date DESC");
    $stmt->execute([$tenant['id']]); $payments = $stmt->fetchAll();
    foreach($payments as $p){
        if($p['status']==='paid') $totalPaid+=$p['amount'];
        elseif($p['status']==='pending') $totalPending+=$p['amount'];
        elseif($p['status']==='overdue') $totalOverdue+=$p['amount'];
    }
}
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/tenant_nav.php';
?>
<div class="d-flex align-center justify-between mb-4">
    <div><h2 style="font-family:var(--font-display);font-size:1.5rem;color:var(--white);">My Payments</h2><p style="color:var(--muted);font-size:.85rem;">Your payment history and status</p></div>
    <a href="<?= APP_URL ?>/user/receipt.php" class="btn btn-outline btn-sm"><i class="fas fa-file-invoice"></i> View Receipts</a>
</div>
<?php if(!$tenant): ?>
<div class="empty-state"><i class="fas fa-receipt"></i><h3>No Active Tenancy</h3><p>Book a room to start your payment history.</p><a href="<?= APP_URL ?>/user/book_room.php" class="btn btn-primary" style="margin-top:16px;">Book a Room</a></div>
<?php else: ?>
<div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
    <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><div class="stat-value" style="font-size:1.3rem;"><?= formatCurrency($totalPaid) ?></div><div class="stat-label">Total Paid</div></div></div>
    <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-clock"></i></div><div class="stat-info"><div class="stat-value" style="font-size:1.3rem;"><?= formatCurrency($totalPending) ?></div><div class="stat-label">Pending</div></div></div>
    <div class="stat-card"><div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div><div class="stat-info"><div class="stat-value" style="font-size:1.3rem;"><?= formatCurrency($totalOverdue) ?></div><div class="stat-label">Overdue</div></div></div>
    <div class="stat-card"><div class="stat-icon gold"><i class="fas fa-peso-sign"></i></div><div class="stat-info"><div class="stat-value" style="font-size:1.3rem;"><?= formatCurrency($tenant['price']) ?></div><div class="stat-label">Monthly Due</div></div></div>
</div>
<?php if(empty($payments)): ?>
<div class="empty-state"><i class="fas fa-receipt"></i><h3>No Payment Records Yet</h3><p>Admin will record your payments here.</p></div>
<?php else: ?>
<div class="card">
    <div class="card-header"><span class="card-title">Payment History</span><button class="btn btn-sm btn-ghost" onclick="exportCSV('pmtTbl','payments.csv')"><i class="fas fa-download"></i> Export</button></div>
    <div class="table-container">
        <table id="pmtTbl">
            <thead><tr><th>Date</th><th>Month</th><th>Amount</th><th>Method</th><th>Reference</th><th>Status</th><th>Receipt</th></tr></thead>
            <tbody>
                <?php foreach($payments as $p): ?>
                <tr>
                    <td><?= formatDate($p['payment_date']) ?></td>
                    <td><?= $p['payment_month']?date('M Y',strtotime($p['payment_month'].'-01')):'—' ?></td>
                    <td style="color:var(--gold);font-weight:700;"><?= formatCurrency($p['amount']) ?></td>
                    <td style="text-transform:capitalize;"><?= e(str_replace('_',' ',$p['payment_method'])) ?></td>
                    <td style="font-size:.8rem;color:var(--muted);"><?= e($p['reference_number']??'—') ?></td>
                    <td><?php $bm=['paid'=>'success','pending'=>'warning','overdue'=>'danger']; ?><span class="badge badge-<?= $bm[$p['status']]??'muted' ?>"><?= ucfirst($p['status']) ?></span></td>
                    <td><?= $p['status']==='paid'?'<a href="'.APP_URL.'/user/receipt.php?id='.$p['id'].'" class="btn btn-sm btn-ghost"><i class="fas fa-file-invoice"></i></a>':'—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
