<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();

$pageTitle = 'My Reservations';
$db  = getDB();
$uid = $_SESSION['user_id'];
$errors = [];

// Cancel reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $action = $_POST['action'] ?? '';
        $resId  = (int)$_POST['reservation_id'];

        if ($action === 'cancel') {
            try {
                $db->beginTransaction();

                $res = $db->prepare("SELECT * FROM bh.reservations WHERE id=? AND user_id=? AND status='pending'");
                $res->execute([$resId, $uid]);
                $reservation = $res->fetch();

                if ($reservation) {
                    $db->prepare("UPDATE bh.reservations SET status='cancelled', updated_at=NOW() WHERE id=?")
                       ->execute([$resId]);
                    $db->prepare("UPDATE bh.beds SET status='available' WHERE id=? AND status='reserved'")
                       ->execute([$reservation['bed_id']]);
                    $db->commit();
                    redirect(APP_URL . '/user/reservations.php', 'Reservation cancelled.', 'warning');
                } else {
                    $errors[] = 'Reservation cannot be cancelled.';
                    $db->rollBack();
                }
            } catch (PDOException $e) {
                $db->rollBack();
                $errors[] = 'Error cancelling reservation.';
            }
        }
    }
}

// Fetch all user reservations using normalized schema (bed_id → beds → rooms → floors)
$reservations = $db->prepare("
    SELECT res.*, 
           r.room_number, f.floor_number, f.floor_name,
           b.bed_number, r.price
    FROM bh.reservations res
    JOIN bh.beds b ON b.id = res.bed_id
    JOIN bh.rooms r ON r.id = b.room_id
    JOIN bh.floors f ON f.id = r.floor_id
    WHERE res.user_id = ?
    ORDER BY res.created_at DESC
");
$reservations->execute([$uid]);
$reservations = $reservations->fetchAll();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/tenant_nav.php'; ?>

<?php foreach ($errors as $err): ?>
<div class="flash flash-error" style="margin-bottom:16px;"><i class="fas fa-times-circle"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<div class="d-flex align-center justify-between mb-4">
    <div>
        <h2 style="font-family:var(--font-display);font-size:1.6rem;color:var(--clr-white);">My Reservations</h2>
        <p class="text-muted" style="font-size:0.88rem;">Track the status of your room bookings</p>
    </div>
    <a href="<?= APP_URL ?>/user/book_room.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> New Booking
    </a>
</div>

<?php if (empty($reservations)): ?>
<div class="empty-state" style="padding:80px 20px;">
    <i class="fas fa-calendar-times"></i>
    <h3>No Reservations Yet</h3>
    <p>You haven't made any room reservations.</p>
    <a href="<?= APP_URL ?>/user/book_room.php" class="btn btn-primary" style="margin-top:20px;">
        <i class="fas fa-bed"></i> Browse Available Rooms
    </a>
</div>
<?php else: ?>
<div style="display:grid;gap:16px;">
    <?php foreach ($reservations as $res): ?>
    <?php
    $bm = ['pending'=>'warning','approved'=>'success','rejected'=>'danger','cancelled'=>'muted'];
    $badge = $bm[$res['status']] ?? 'muted';
    $icons = ['pending'=>'clock','approved'=>'check-circle','rejected'=>'times-circle','cancelled'=>'ban'];
    $icon  = $icons[$res['status']] ?? 'info-circle';
    ?>
    <div class="card">
        <div class="card-body" style="padding:24px;">
            <div style="display:grid;grid-template-columns:auto 1fr auto;gap:20px;align-items:start;">
                <div style="width:48px;height:48px;border-radius:var(--radius-md);
                            background:rgba(201,168,76,0.1);display:flex;
                            align-items:center;justify-content:center;
                            color:var(--clr-gold);font-size:1.3rem;">
                    <i class="fas fa-bed"></i>
                </div>

                <div>
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:8px;">
                        <h3 style="font-family:var(--font-display);color:var(--clr-white);font-size:1.1rem;">
                            <?= e($res['floor_name']) ?> — Room <?= e($res['room_number']) ?>, Bed <?= $res['bed_number'] ?>
                        </h3>
                        <span class="badge badge-<?= $badge ?>">
                            <i class="fas fa-<?= $icon ?>"></i> <?= ucfirst($res['status']) ?>
                        </span>
                    </div>
                    <div style="display:flex;gap:24px;flex-wrap:wrap;font-size:0.85rem;color:var(--clr-muted);">
                        <span><i class="fas fa-calendar"></i> Move-in: <strong style="color:var(--clr-text);"><?= formatDate($res['move_in_date']) ?></strong></span>
                        <span><i class="fas fa-peso-sign"></i> <strong style="color:var(--clr-gold);"><?= formatCurrency($res['price']) ?>/month</strong></span>
                        <span><i class="fas fa-clock"></i> Booked: <?= formatDate($res['created_at'], 'M d, Y g:i A') ?></span>
                    </div>
                    <?php if ($res['admin_notes']): ?>
                    <div style="margin-top:10px;padding:10px 14px;background:rgba(255,255,255,0.04);border-radius:var(--radius-md);font-size:0.82rem;color:var(--clr-muted);">
                        <i class="fas fa-comment"></i> Admin Note: <?= e($res['admin_notes']) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div>
                    <?php if ($res['status'] === 'pending'): ?>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="reservation_id" value="<?= $res['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="Cancel this reservation?">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </form>
                    <?php elseif ($res['status'] === 'approved'): ?>
                    <span class="badge badge-success" style="padding:10px 14px;">
                        <i class="fas fa-check-circle"></i> Approved
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>