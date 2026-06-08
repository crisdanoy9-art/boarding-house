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

// Handle bed actions
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!validateCSRF($_POST[CSRF_TOKEN_NAME]??'')){
        redirect(APP_URL.'/admin/beds.php?room_id='.$roomId,'Invalid request.','error');
    }
    $action=$_POST['action']??'';
    $bedId=(int)$_POST['bed_id'];
    if($action==='free_bed'){
        // Free the bed: set status to available and deactivate any active tenant for this bed
        $db->beginTransaction();
        try {
            // Update bed status
            $db->prepare("UPDATE bh.beds SET status='available' WHERE id=? AND room_id=?")->execute([$bedId, $roomId]);
            // Deactivate any active tenant for this bed
            $db->prepare("UPDATE bh.tenants SET status='inactive', move_out_date=NOW() WHERE bed_id=? AND status='active'")->execute([$bedId]);
            $db->commit();
            redirect(APP_URL.'/admin/beds.php?room_id='.$roomId,'Bed freed.');
        } catch (Exception $e) {
            $db->rollBack();
            redirect(APP_URL.'/admin/beds.php?room_id='.$roomId,'Error freeing bed.','error');
        }
    } elseif($action==='set_reserved'){
        $db->prepare("UPDATE bh.beds SET status='reserved' WHERE id=? AND room_id=?")->execute([$bedId, $roomId]);
        redirect(APP_URL.'/admin/beds.php?room_id='.$roomId,'Bed marked as reserved.');
    } elseif($action==='make_available'){
        $db->prepare("UPDATE bh.beds SET status='available' WHERE id=? AND room_id=?")->execute([$bedId, $roomId]);
        redirect(APP_URL.'/admin/beds.php?room_id='.$roomId,'Bed is now available.');
    }
}

// Fetch beds with current tenant information (if any)
$beds = $db->prepare("
    SELECT b.*, 
           t.id AS tenant_id, t.user_id, t.move_in_date,
           u.name AS tenant_name, u.email AS tenant_email
    FROM bh.beds b
    LEFT JOIN bh.tenants t ON t.bed_id = b.id AND t.status = 'active'
    LEFT JOIN bh.users u ON u.id = t.user_id
    WHERE b.room_id = ?
    ORDER BY b.bed_number
");
$beds->execute([$roomId]);
$beds = $beds->fetchAll();

include __DIR__.'/../includes/header.php';
include __DIR__.'/../includes/admin_nav.php';
?>
<div class="d-flex align-center gap-3 mb-4"><a href="<?= APP_URL ?>/admin/rooms.php?floor=<?= $room['floor_number'] ?>" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a><div><h2 style="font-family:var(--font-display);color:var(--white);font-size:1.2rem;">Room <?= e($room['room_number']) ?> — Beds</h2><p class="text-muted" style="font-size:.82rem;"><?= e($room['floor_name']) ?> &bull; <?= formatCurrency($room['price']) ?>/mo</p></div></div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px;">
<?php foreach($beds as $bed): 
    $bm=['available'=>'success','occupied'=>'danger','reserved'=>'warning'];
    $status = $bed['status'];
?>
<div class="card" style="border-color:<?= $status==='occupied'?'rgba(240,82,82,.3)':($status==='reserved'?'rgba(240,168,50,.3)':'rgba(62,207,110,.3)') ?>">
    <div class="card-header"><span class="card-title"><i class="fas fa-bed" style="color:var(--gold);"></i> Bed <?= $bed['bed_number'] ?></span><span class="badge badge-<?= $bm[$status]??'muted' ?>"><?= ucfirst($status) ?></span></div>
    <div class="card-body">
        <?php if($status==='occupied' && $bed['tenant_name']): ?>
            <div style="margin-bottom:14px;">
                <div class="form-label">Tenant</div>
                <div style="color:var(--white);font-weight:600;"><?= e($bed['tenant_name']) ?></div>
                <div style="font-size:.78rem;color:var(--muted);"><?= e($bed['tenant_email']) ?></div>
                <div style="font-size:.7rem;color:var(--muted);margin-top:4px;">Move-in: <?= formatDate($bed['move_in_date']) ?></div>
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="free_bed">
                <input type="hidden" name="bed_id" value="<?= $bed['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger btn-full" data-confirm="Free this bed? This will vacate the tenant.">
                    <i class="fas fa-unlock"></i> Free Bed
                </button>
            </form>
        <?php elseif($status==='available'): ?>
            <p class="text-muted" style="font-size:.85rem;margin-bottom:14px;">Available for booking.</p>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="set_reserved">
                <input type="hidden" name="bed_id" value="<?= $bed['id'] ?>">
                <button type="submit" class="btn btn-sm btn-warning btn-full"><i class="fas fa-lock"></i> Mark Reserved</button>
            </form>
        <?php else: /* reserved */ ?>
            <p class="text-muted" style="font-size:.85rem;margin-bottom:14px;">Currently reserved.</p>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="make_available">
                <input type="hidden" name="bed_id" value="<?= $bed['id'] ?>">
                <button type="submit" class="btn btn-sm btn-success btn-full"><i class="fas fa-check"></i> Make Available</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>
</main></div>
<?php include __DIR__.'/../includes/footer.php'; ?>