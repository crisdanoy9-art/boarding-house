<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();
$pageTitle = 'Payment Receipts';
$db=$uid=$user=null;
$db = getDB(); $uid=$_SESSION['user_id']; $user=getCurrentUser();
$tenant=$db->prepare("SELECT t.*,r.room_number,r.price,f.floor_number,f.floor_name,b.bed_number FROM bh.tenants t JOIN bh.rooms r ON r.id=t.room_id JOIN bh.floors f ON f.id=r.floor_id JOIN bh.beds b ON b.id=t.bed_id WHERE t.user_id=? AND t.status='active' LIMIT 1");
$tenant->execute([$uid]); $tenant=$tenant->fetch();
$payments=[];
if($tenant){$s=$db->prepare("SELECT * FROM bh.payments WHERE tenant_id=? AND status='paid' ORDER BY payment_date DESC");$s->execute([$tenant['id']]);$payments=$s->fetchAll();}
$viewId=(int)($_GET['id']??0); $singlePay=null;
if($viewId&&$tenant){$s=$db->prepare("SELECT * FROM bh.payments WHERE id=? AND tenant_id=?");$s->execute([$viewId,$tenant['id']]);$singlePay=$s->fetch();}
include __DIR__.'/../includes/header.php'; include __DIR__.'/../includes/tenant_nav.php';
?>
<div class="receipt-print-wrap" id="printWrap"></div>
<div class="d-flex align-center justify-between mb-4" style="flex-wrap:wrap;gap:12px;">
<div><h2 style="font-family:var(--font-display);font-size:1.5rem;color:var(--white);"><i class="fas fa-file-invoice" style="color:var(--gold);margin-right:8px;"></i>Payment Receipts</h2><p style="color:var(--muted);font-size:.85rem;">Official receipts from Nadelas Boarding House (NBH)</p></div>
<?php if($singlePay): ?><div class="d-flex gap-2"><a href="<?= APP_URL ?>/user/receipt.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> All</a><button onclick="printRcpt()" class="btn btn-primary btn-sm"><i class="fas fa-print"></i> Print</button></div><?php endif; ?>
</div>
<?php if(!$tenant): ?><div class="empty-state"><i class="fas fa-file-invoice"></i><h3>No Active Tenancy</h3><p>Book a room first.</p></div>
<?php elseif($singlePay): ?>
<div id="rcptArea">
<div class="receipt-card" id="rcptCard">
<div class="receipt-hdr"><div class="receipt-circle">NBH</div><div class="receipt-org">NADELAS BOARDING HOUSE</div><div class="receipt-sub-txt">Official Payment Receipt</div><div class="receipt-no">Receipt #<?= str_pad($singlePay['id'],6,'0',STR_PAD_LEFT) ?></div></div>
<div class="receipt-body">
<div class="receipt-amount-box"><div><div style="font-size:.68rem;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:3px;">Amount Paid</div><div class="receipt-amount-big"><?= formatCurrency($singlePay['amount']) ?></div></div><div class="receipt-paid-stamp"><i class="fas fa-check-circle"></i> PAID</div></div>
<?php foreach([['Tenant Name',e($user['name'])],['Room / Floor / Bed','Room '.e($tenant['room_number']).', '.e($tenant['floor_name']).', Bed '.$tenant['bed_number']],['Month Paid',($singlePay['payment_month']?date('F Y',strtotime($singlePay['payment_month'].'-01')):date('F Y',strtotime($singlePay['payment_date'])))],['Date of Payment',formatDate($singlePay['payment_date'],'F d, Y')],['Payment Method',ucfirst(str_replace('_',' ',$singlePay['payment_method']))],['Reference No.',e($singlePay['reference_number']??'—')]] as [$l,$v]): ?>
<div class="receipt-row"><span class="receipt-lbl"><?= $l ?></span><span class="receipt-val"><?= $v ?></span></div><?php endforeach; ?>
</div>
<div class="receipt-ftr"><div style="font-size:.7rem;color:var(--muted);margin-bottom:8px;">Thank you for your payment!</div><div style="font-size:.66rem;color:var(--muted);line-height:1.75;"><strong style="color:var(--white);">NADELAS BOARDING HOUSE (NBH)</strong><br>Official receipt. Keep for your records.<br>Issued: <?= date('F d, Y, g:i A') ?></div></div>
</div>
<div class="no-print" style="text-align:center;margin-top:16px;"><a href="<?= APP_URL ?>/user/receipt.php" class="btn btn-ghost"><i class="fas fa-list"></i> All Receipts</a> <button onclick="printRcpt()" class="btn btn-primary" style="margin-left:10px;"><i class="fas fa-print"></i> Print / PDF</button></div>
</div>
<?php else: ?>
<?php if(empty($payments)): ?><div class="empty-state"><i class="fas fa-file-invoice"></i><h3>No Paid Receipts Yet</h3><p>Receipts appear after admin records your payments.</p></div><?php else: ?>
<div style="background:linear-gradient(135deg,rgba(201,168,76,.08),rgba(201,168,76,.02));border:1px solid rgba(201,168,76,.2);border-radius:var(--r-xl);padding:18px 24px;margin-bottom:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
<div class="receipt-circle" style="flex-shrink:0;margin:0;">NBH</div>
<div style="flex:1;"><div style="font-family:var(--font-display);font-size:1rem;color:var(--white);"><?= e($user['name']) ?></div><div style="font-size:.78rem;color:var(--muted);">Room <?= e($tenant['room_number']) ?>, Floor <?= $tenant['floor_number'] ?>, Bed <?= $tenant['bed_number'] ?></div></div>
<div style="text-align:right;"><div style="font-family:var(--font-display);font-size:1.4rem;color:var(--gold);"><?= formatCurrency(array_sum(array_column($payments,'amount'))) ?></div><div style="font-size:.68rem;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;">Total Paid · <?= count($payments) ?> receipts</div></div>
</div>
<div style="display:grid;gap:9px;">
<?php foreach($payments as $p): $ml=$p['payment_month']?date('F Y',strtotime($p['payment_month'].'-01')):date('F Y',strtotime($p['payment_date'])); ?>
<div class="receipt-list-item" onclick="location.href='?id=<?= $p['id'] ?>'">
<div style="width:40px;height:40px;border-radius:var(--r-md);background:rgba(201,168,76,.1);border:1px solid rgba(201,168,76,.2);display:flex;align-items:center;justify-content:center;color:var(--gold);flex-shrink:0;"><i class="fas fa-file-invoice"></i></div>
<div style="flex:1;min-width:0;"><div style="font-weight:600;color:var(--white);font-size:.88rem;"><?= $ml ?> <span style="font-size:.68rem;color:var(--muted);margin-left:6px;">#<?= str_pad($p['id'],6,'0',STR_PAD_LEFT) ?></span></div><div style="font-size:.74rem;color:var(--muted);"><?= ucfirst(str_replace('_',' ',$p['payment_method'])) ?> · <?= formatDate($p['payment_date'],'M d, Y') ?><?= $p['reference_number']?' · <span style="color:var(--gold);">'.e($p['reference_number']).'</span>':'' ?></div></div>
<div style="text-align:right;flex-shrink:0;"><div style="font-family:var(--font-display);font-size:1.05rem;color:var(--gold);font-weight:700;"><?= formatCurrency($p['amount']) ?></div><span class="badge badge-success" style="font-size:.6rem;"><i class="fas fa-check"></i> Paid</span></div>
<div style="color:var(--muted);">›</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; endif; ?>
</div>
<script>function printRcpt(){const h=document.getElementById('rcptCard').outerHTML;const w=document.getElementById('printWrap');w.innerHTML=h;w.style.display='block';window.print();w.style.display='none';w.innerHTML='';}</script>
<?php include __DIR__.'/../includes/footer.php'; ?>
