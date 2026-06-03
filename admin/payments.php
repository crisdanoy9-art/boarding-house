<?php
require_once __DIR__ . '/../includes/session.php';
requireAdmin();

$pageTitle = 'Payment Records';
$db = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST[CSRF_TOKEN_NAME] ?? '')) { $errors[] = 'Invalid request.'; }
    else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add_payment') {
            $tenantId = (int)$_POST['tenant_id'];
            $amount   = (float)$_POST['amount'];
            $payDate  = sanitizeInput($_POST['payment_date'] ?? '');
            $dueDate  = sanitizeInput($_POST['due_date'] ?? '');
            $month    = sanitizeInput($_POST['payment_month'] ?? '');
            $method   = sanitizeInput($_POST['payment_method'] ?? 'cash');
            $refNum   = sanitizeInput($_POST['reference_number'] ?? '');
            $notes    = sanitizeInput($_POST['notes'] ?? '');
            $status   = sanitizeInput($_POST['status'] ?? 'paid');
            if (!$tenantId || !$amount || !$payDate) { $errors[] = 'Tenant, amount, and date required.'; }
            else {
                try {
                    $db->prepare("INSERT INTO bh.payments(tenant_id,amount,payment_date,due_date,payment_month,payment_method,reference_number,status,notes,recorded_by) VALUES(?,?,?,?,?,?,?,?,?,?)")
                       ->execute([$tenantId,$amount,$payDate,$dueDate?:null,$month,$method,$refNum,$status,$notes,$_SESSION['user_id']]);
                    redirect(APP_URL.'/admin/payments.php','Payment recorded!');
                } catch (PDOException $e) { $errors[] = 'Error recording payment.'; }
            }
        }
    }
}

$tenantFilter = (int)($_GET['tenant_id'] ?? 0);
$statusFilter = sanitizeInput($_GET['status'] ?? '');
$page = max(1,(int)($_GET['page']??1)); $perPage=15;

$where = 'WHERE 1=1';
if ($tenantFilter) $where .= " AND p.tenant_id=$tenantFilter";
if ($statusFilter) $where .= " AND p.status='$statusFilter'";

$total  = $db->query("SELECT COUNT(*) FROM bh.payments p $where")->fetchColumn();
$pager  = paginate($total,$perPage,$page);
$payments = $db->query("
    SELECT p.*, u.name AS tenant_name, r.room_number, f.floor_number
    FROM bh.payments p
    JOIN bh.tenants t ON t.id=p.tenant_id
    JOIN bh.users u   ON u.id=t.user_id
    JOIN bh.rooms r   ON r.id=t.room_id
    JOIN bh.floors f  ON f.id=r.floor_id
    $where ORDER BY p.payment_date DESC LIMIT $perPage OFFSET {$pager['offset']}
")->fetchAll();

$activeTenants = $db->query("SELECT t.id,u.name,r.room_number,f.floor_number,r.price FROM bh.tenants t JOIN bh.users u ON u.id=t.user_id JOIN bh.rooms r ON r.id=t.room_id JOIN bh.floors f ON f.id=r.floor_id WHERE t.status='active' ORDER BY u.name")->fetchAll();
$summary = $db->query("SELECT SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) AS total_paid, SUM(CASE WHEN status='pending' THEN amount ELSE 0 END) AS total_pending, SUM(CASE WHEN status='overdue' THEN amount ELSE 0 END) AS total_overdue FROM bh.payments p $where")->fetch();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_nav.php'; ?>

<?php foreach ($errors as $err): ?>
<div class="flash flash-error" style="margin-bottom:14px;"><i class="fas fa-times-circle"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<div class="stats-grid mb-4" style="grid-template-columns:repeat(3,1fr);">
    <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><div class="stat-value" style="font-size:1.3rem;"><?= formatCurrency((float)($summary['total_paid']??0)) ?></div><div class="stat-label">Total Paid</div></div></div>
    <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-clock"></i></div><div class="stat-info"><div class="stat-value" style="font-size:1.3rem;"><?= formatCurrency((float)($summary['total_pending']??0)) ?></div><div class="stat-label">Pending</div></div></div>
    <div class="stat-card"><div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div><div class="stat-info"><div class="stat-value" style="font-size:1.3rem;"><?= formatCurrency((float)($summary['total_overdue']??0)) ?></div><div class="stat-label">Overdue</div></div></div>
</div>

<div class="d-flex align-center justify-between mb-4" style="flex-wrap:wrap;gap:12px;">
    <div class="d-flex gap-2" style="flex-wrap:wrap;">
        <?php foreach ([''=>'All','paid'=>'Paid','pending'=>'Pending','overdue'=>'Overdue'] as $v=>$l): ?>
        <a href="?status=<?= $v ?>&tenant_id=<?= $tenantFilter ?>" class="btn btn-sm <?= $statusFilter===$v?'btn-primary':'btn-ghost' ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-ghost" onclick="exportCSV('paymentsTable','payments.csv')"><i class="fas fa-download"></i> Export</button>
        <button class="btn btn-primary btn-sm" data-modal-open="addPaymentModal"><i class="fas fa-plus"></i> Record Payment</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Payment Records <span style="font-size:.78rem;color:var(--muted);">(<?= $total ?>)</span></span>
    </div>
    <?php if (empty($payments)): ?>
    <div class="empty-state"><i class="fas fa-receipt"></i><h3>No Payments Found</h3></div>
    <?php else: ?>
    <div class="table-container">
        <table id="paymentsTable">
            <thead><tr><th>#</th><th>Tenant</th><th>Room</th><th>Amount</th><th>Date</th><th>Month</th><th>Method</th><th>Status</th><th>Receipt</th></tr></thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td style="color:var(--muted);font-size:.78rem;">#<?= str_pad($p['id'],6,'0',STR_PAD_LEFT) ?></td>
                    <td><?= e($p['tenant_name']) ?></td>
                    <td>F<?= $p['floor_number'] ?>-<?= e($p['room_number']) ?></td>
                    <td style="color:var(--gold);font-weight:700;"><?= formatCurrency($p['amount']) ?></td>
                    <td style="font-size:.82rem;"><?= formatDate($p['payment_date'],'M d, Y') ?></td>
                    <td style="font-size:.82rem;"><?= $p['payment_month']?date('M Y',strtotime($p['payment_month'].'-01')):'—' ?></td>
                    <td style="text-transform:capitalize;"><?= e(str_replace('_',' ',$p['payment_method'])) ?></td>
                    <td><?php $bm=['paid'=>'success','pending'=>'warning','overdue'=>'danger']; ?><span class="badge badge-<?= $bm[$p['status']]??'muted' ?>"><?= ucfirst($p['status']) ?></span></td>
                    <td><?php if($p['status']==='paid'): ?><a href="<?= APP_URL ?>/admin/receipts.php?view=<?= $p['id'] ?>" class="btn btn-sm btn-ghost"><i class="fas fa-file-invoice"></i></a><?php else: ?><span style="color:var(--muted);">—</span><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager['total_pages'] > 1): ?>
    <div style="padding:16px;"><div class="pagination">
        <?php for($p=1;$p<=$pager['total_pages'];$p++): ?>
        <a href="?status=<?= $statusFilter ?>&page=<?= $p ?>" class="page-link <?= $p==$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div></div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Add Payment Modal -->
<div class="modal-overlay" id="addPaymentModal">
    <div class="modal" style="max-width:540px;">
        <div class="modal-header"><span class="modal-title">Record Payment</span><button class="modal-close" data-modal-close="addPaymentModal">&times;</button></div>
        <div class="modal-body">
            <form method="POST" data-validate>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_payment">
                <div class="form-group">
                    <label class="form-label">Tenant *</label>
                    <select name="tenant_id" class="form-control" required onchange="fillAmt(this)">
                        <option value="">Select Tenant</option>
                        <?php foreach ($activeTenants as $at): ?>
                        <option value="<?= $at['id'] ?>" data-price="<?= $at['price'] ?>"><?= e($at['name']) ?> — F<?= $at['floor_number'] ?> Rm<?= e($at['room_number']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group"><label class="form-label">Amount *</label><input type="number" id="payAmt" name="amount" class="form-control" placeholder="1300" min="0" step="0.01" required></div>
                    <div class="form-group"><label class="form-label">Payment Month</label><input type="month" name="payment_month" class="form-control" value="<?= date('Y-m') ?>"></div>
                    <div class="form-group"><label class="form-label">Payment Date *</label><input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="form-group"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Method</label><select name="payment_method" class="form-control"><option value="cash">Cash</option><option value="gcash">GCash</option><option value="bank_transfer">Bank Transfer</option><option value="check">Check</option></select></div>
                    <div class="form-group"><label class="form-label">Status</label><select name="status" class="form-control"><option value="paid">Paid</option><option value="pending">Pending</option><option value="overdue">Overdue</option></select></div>
                </div>
                <div class="form-group"><label class="form-label">Reference Number</label><input type="text" name="reference_number" class="form-control" placeholder="GCash ref or check no."></div>
                <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2" placeholder="Optional..."></textarea></div>
                <div class="modal-footer" style="padding:0;margin-top:16px;border:none;">
                    <button type="button" class="btn btn-ghost" data-modal-close="addPaymentModal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>function fillAmt(s){const o=s.options[s.selectedIndex];if(o.dataset.price)document.getElementById('payAmt').value=o.dataset.price;}</script>

</main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
