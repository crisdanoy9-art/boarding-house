<?php
require_once __DIR__ . '/../includes/session.php';
requireAdmin();
$roomId = (int)($_GET['room_id'] ?? 0);
if (!$roomId) { redirect(APP_URL.'/admin/rooms.php','No room selected.','error'); }
$db = getDB();
$room = $db->prepare("SELECT r.*,f.floor_number,f.floor_name FROM bh.rooms r JOIN bh.floors f ON f.id=r.floor_id WHERE r.id=?");
$room->execute([$roomId]); $room=$room->fetch();
if(!$room){redirect(APP_URL.'/admin/rooms.php','Room not found.','error');}
$pageTitle='Beds — Room '.$room['room_number'];
if($_SERVER['REQUEST_METHOD']==='POST'){if(!validateCSRF($_POST[CSRF_TOKEN_NAME]??'')){redirect(APP_URL.'/admin/beds.php?room_id='.$roomId,'Invalid request.','error');}$action=$_POST['action']??'';$bedId=(int)$_POST['bed_id'];if($action==='free_bed'){$db->prepare("UPDATE bh.beds SET status='available',tenant_id=NULL WHERE id=? AND room_id=?")->execute([$bedId,$roomId]);redirect(APP_URL.'/admin/beds.php?room_id='.$roomId,'Bed freed.');}elseif($action==='set_reserved'){$db->prepare("UPDATE bh.beds SET status='reserved' WHERE id=? AND room_id=?")->execute([$bedId,$roomId]);redirect(APP_URL.'/admin/beds.php?room_id='.$roomId,'Bed reserved.');}}
$beds=$db->prepare("SELECT b.*,u.name AS tenant_name,u.email AS tenant_email FROM bh.beds b LEFT JOIN bh.users u ON u.id=b.tenant_id WHERE b.room_id=? ORDER BY b.bed_number");$beds->execute([$roomId]);$beds=$beds->fetchAll();
include __DIR__.'/../includes/header.php';include __DIR__.'/../includes/admin_nav.php';
?>
<div class="d-flex align-center gap-3 mb-4"><a href="<?= APP_URL ?>/admin/rooms.php?floor=<?= $room['floor_number'] ?>" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a><div><h2 style="font-family:var(--font-display);color:var(--white);font-size:1.2rem;">Room <?= e($room['room_number']) ?> — Beds</h2><p class="text-muted" style="font-size:.82rem;"><?= e($room['floor_name']) ?> &bull; <?= formatCurrency($room['price']) ?>/mo</p></div></div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px;">
<?php foreach($beds as $bed): $bm=['available'=>'success','occupied'=>'danger','reserved'=>'warning']; ?>
<div class="card" style="border-color:<?= $bed['status']==='occupied'?'rgba(240,82,82,.3)':($bed['status']==='reserved'?'rgba(240,168,50,.3)':'rgba(62,207,110,.3)') ?>">
    <div class="card-header"><span class="card-title"><i class="fas fa-bed" style="color:var(--gold);"></i> Bed <?= $bed['bed_number'] ?></span><span class="badge badge-<?= $bm[$bed['status']]??'muted' ?>"><?= ucfirst($bed['status']) ?></span></div>
    <div class="card-body">
        <?php if($bed['status']==='occupied'&&$bed['tenant_name']): ?><div style="margin-bottom:14px;"><div class="form-label">Tenant</div><div style="color:var(--white);font-weight:600;"><?= e($bed['tenant_name']) ?></div><div style="font-size:.78rem;color:var(--muted);"><?= e($bed['tenant_email']) ?></div></div><form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="free_bed"><input type="hidden" name="bed_id" value="<?= $bed['id'] ?>"><button type="submit" class="btn btn-sm btn-danger btn-full" data-confirm="Free this bed?"><i class="fas fa-unlock"></i> Free Bed</button></form>
        <?php elseif($bed['status']==='available'): ?><p class="text-muted" style="font-size:.85rem;margin-bottom:14px;">Available for booking.</p><form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="set_reserved"><input type="hidden" name="bed_id" value="<?= $bed['id'] ?>"><button type="submit" class="btn btn-sm btn-warning btn-full"><i class="fas fa-lock"></i> Mark Reserved</button></form>
        <?php else: ?><p class="text-muted" style="font-size:.85rem;margin-bottom:14px;">Currently reserved.</p><form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="free_bed"><input type="hidden" name="bed_id" value="<?= $bed['id'] ?>"><button type="submit" class="btn btn-sm btn-success btn-full"><i class="fas fa-check"></i> Make Available</button></form><?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>
</main></div>
<?php include __DIR__.'/../includes/footer.php'; ?>
