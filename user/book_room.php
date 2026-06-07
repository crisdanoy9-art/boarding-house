<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();

$pageTitle = 'Book a Room';
$db  = getDB();
$uid = $_SESSION['user_id'];
$errors = [];

$existing = $db->prepare("SELECT id FROM bh.tenants WHERE user_id=? AND status='active'");
$existing->execute([$uid]); $existingTenancy = $existing->fetch();

$existingRes = $db->prepare("SELECT id,status FROM bh.reservations WHERE user_id=? AND status IN ('pending','approved')");
$existingRes->execute([$uid]); $pendingRes = $existingRes->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST[CSRF_TOKEN_NAME] ?? '')) { $errors[] = 'Invalid request.'; }
    elseif ($existingTenancy) { $errors[] = 'You already have an active room.'; }
    elseif ($pendingRes) { $errors[] = 'You have a pending/approved reservation.'; }
    else {
        $roomId   = (int)$_POST['room_id'];
        $bedId    = (int)$_POST['bed_id'];
        $moveIn   = sanitizeInput($_POST['move_in_date'] ?? '');
        $notes    = sanitizeInput($_POST['notes'] ?? '');

        if (!$roomId || !$bedId || !$moveIn) { $errors[] = 'Please select a room, bed, and move-in date.'; }
        elseif (strtotime($moveIn) < strtotime('today')) { $errors[] = 'Move-in date cannot be in the past.'; }
        else {
            try {
                $db->beginTransaction();
                $bc = $db->prepare("SELECT id FROM bh.beds WHERE id=? AND room_id=? AND status='available'");
                $bc->execute([$bedId,$roomId]);
                if (!$bc->fetch()) throw new Exception('That bed is no longer available. Please pick another.');

                $db->prepare("INSERT INTO bh.reservations(user_id,room_id,bed_id,move_in_date,notes,status,created_at) VALUES(?,?,?,?,?,'pending',NOW())")->execute([$uid,$roomId,$bedId,$moveIn,$notes]);
                $db->prepare("UPDATE bh.beds SET status='reserved' WHERE id=?")->execute([$bedId]);
                $db->commit();
                redirect(APP_URL.'/user/reservations.php','✅ Reservation submitted! Admin will review and approve.','success');
            } catch (Exception $e) { $db->rollBack(); $errors[] = $e->getMessage(); }
        }
    }
}

$currentFloor = (int)($_GET['floor'] ?? 1);
$floors = $db->query("SELECT * FROM bh.floors ORDER BY floor_number")->fetchAll();
$rooms = $db->prepare("
    SELECT r.*, f.floor_number, f.floor_name,
           COUNT(b.id) AS total_beds,
           SUM(CASE WHEN b.status='available' THEN 1 ELSE 0 END) AS available_count
    FROM bh.rooms r
    JOIN bh.floors f ON f.id=r.floor_id
    LEFT JOIN bh.beds b ON b.room_id=r.id
    WHERE f.floor_number=? AND r.status != 'maintenance'
    GROUP BY r.id,f.floor_number,f.floor_name
    ORDER BY r.room_number
");
$rooms->execute([$currentFloor]); $rooms = $rooms->fetchAll();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/tenant_nav.php'; ?>

<?php foreach ($errors as $err): ?>
<div class="flash flash-error" style="margin-bottom:14px;"><i class="fas fa-times-circle"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<?php if ($existingTenancy): ?>
<div class="warning-banner"><i class="fas fa-info-circle"></i> <div>You already have an active room. <a href="<?= APP_URL ?>/user/dashboard.php" style="color:var(--gold);font-weight:600;">Dashboard →</a></div></div>
<?php elseif ($pendingRes): ?>
<div class="info-banner"><i class="fas fa-clock"></i> <div>You have a <strong><?= $pendingRes['status'] ?></strong> reservation pending. <a href="<?= APP_URL ?>/user/reservations.php" style="color:var(--gold);font-weight:600;">View it →</a></div></div>
<?php endif; ?>

<!-- Booking Flow Steps -->
<?php if (!$existingTenancy && !$pendingRes): ?>
<div style="display:flex;align-items:center;gap:0;margin-bottom:22px;overflow:hidden;">
    <?php foreach ([['1','Select Bed'],['2','Submit Details'],['3','Admin Approves']] as $i=>[$n,$lbl]): ?>
    <div style="display:flex;flex-direction:column;align-items:center;flex:1;position:relative;">
        <?php if($i<2): ?><div style="position:absolute;top:17px;left:50%;right:-50%;height:2px;background:var(--border2);z-index:0;"></div><?php endif; ?>
        <div style="width:34px;height:34px;border-radius:50%;background:<?= $i===0?'var(--gold)':'var(--surface2)' ?>;border:2px solid <?= $i===0?'var(--gold)':'var(--border2)' ?>;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:<?= $i===0?'#06060e':'var(--muted)' ?>;z-index:1;position:relative;"><?= $n ?></div>
        <div style="font-size:.66rem;color:<?= $i===0?'var(--gold)':'var(--muted)' ?>;margin-top:5px;text-align:center;"><?= $lbl ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="d-flex align-center justify-between mb-3">
    <div>
        <h2 style="font-family:var(--font-display);font-size:1.4rem;color:var(--white);">Available Rooms</h2>
        <p style="color:var(--muted);font-size:.82rem;">Click a green bed to select it, then submit your reservation.</p>
    </div>
</div>

<div class="floor-tabs mb-3">
    <?php foreach ($floors as $f): ?>
    <a href="?floor=<?= $f['floor_number'] ?>" class="floor-tab <?= $currentFloor==$f['floor_number']?'active':'' ?>">
        <i class="fas fa-layer-group"></i> <?= e($f['floor_name']) ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Legend -->
<div class="d-flex gap-3 mb-3" style="font-size:.78rem;flex-wrap:wrap;">
    <span class="d-flex gap-2 align-center"><div style="width:10px;height:10px;border-radius:2px;background:rgba(62,207,110,.25);border:1px solid rgba(62,207,110,.4);"></div><span style="color:var(--muted);">Available</span></span>
    <span class="d-flex gap-2 align-center"><div style="width:10px;height:10px;border-radius:2px;background:rgba(240,82,82,.2);border:1px solid rgba(240,82,82,.3);"></div><span style="color:var(--muted);">Occupied</span></span>
    <span class="d-flex gap-2 align-center"><div style="width:10px;height:10px;border-radius:2px;background:rgba(240,168,50,.2);border:1px solid rgba(240,168,50,.3);"></div><span style="color:var(--muted);">Reserved</span></span>
</div>

<!-- Rooms Grid -->
<?php if (empty($rooms)): ?>
<div class="empty-state"><i class="fas fa-door-closed"></i><h3>No Rooms Available</h3><p>Try another floor.</p></div>
<?php else: ?>
<div class="rooms-grid">
    <?php foreach ($rooms as $room):
        $bedStmt = $db->prepare("SELECT * FROM bh.beds WHERE room_id=? ORDER BY bed_number");
        $bedStmt->execute([$room['id']]); $roomBeds = $bedStmt->fetchAll();
        $hasAvail = $room['available_count'] > 0;
    ?>
    <div class="room-card">
        <div class="room-card-header">
            <div>
                <div class="room-number">Room <?= e($room['room_number']) ?></div>
                <div class="room-floor">Floor <?= $room['floor_number'] ?></div>
            </div>
            <span class="badge badge-<?= $hasAvail?'success':'danger' ?>"><?= $hasAvail?$room['available_count'].' open':'Full' ?></span>
        </div>
        <div class="card-body">
            <div class="room-price">₱1,300<span>/bed/month</span></div>
            <?php if ($room['amenities']): ?>
            <div class="room-amenities">
                <?php foreach (array_slice(explode(',', $room['amenities']),0,3) as $a): ?>
                <span class="amenity-tag"><?= e(trim($a)) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="beds-row">
                <?php foreach ($roomBeds as $bed): ?>
                <div class="bed-slot <?= $bed['status'] ?>"
                     data-bed-id="<?= $bed['id'] ?>"
                     data-room-id="<?= $room['id'] ?>"
                     data-room="<?= e($room['room_number']) ?>"
                     data-bed-num="<?= $bed['bed_number'] ?>"
                     data-floor="<?= $room['floor_number'] ?>"
                     title="Bed <?= $bed['bed_number'] ?> — <?= ucfirst($bed['status']) ?>"
                     <?= ($bed['status']==='available' && !$existingTenancy && !$pendingRes) ? 'onclick="selectBed(this)"' : '' ?>>
                    B<?= $bed['bed_number'] ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Booking Overlay Modal (simplified, no GCash) -->
<div class="booking-overlay" id="bookingOverlay">
<div class="booking-panel">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border);">
        <div>
            <div style="font-family:var(--font-display);font-size:1.05rem;color:var(--white);">Complete Your Booking</div>
            <div style="font-size:.76rem;color:var(--muted);">F<span id="panFloor">—</span> · Rm <span id="panRoom">—</span> · Bed <span id="panBed">—</span></div>
        </div>
        <button onclick="closeBooking()" style="background:none;border:none;color:var(--muted);font-size:1.3rem;cursor:pointer;padding:4px 8px;border-radius:var(--r-sm);">×</button>
    </div>

    <div style="padding:20px 22px;">
        <form method="POST" action="" data-validate id="bookingForm">
            <?= csrfField() ?>
            <input type="hidden" name="room_id" id="formRoomId">
            <input type="hidden" name="bed_id"  id="formBedId">

            <div class="form-group">
                <label class="form-label">Move-In Date *</label>
                <input type="date" name="move_in_date" id="formMoveIn" class="form-control"
                       min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Additional Notes (optional)</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Any special requests or information for admin..."></textarea>
            </div>

            <div style="background:rgba(201,168,76,.07);border:1px solid rgba(201,168,76,.2);border-radius:var(--r-md);padding:13px 16px;margin-bottom:16px;">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;font-size:.8rem;">
                    <div><div style="color:var(--muted);margin-bottom:2px;">Room</div><div style="color:var(--white);font-weight:600;">F<span id="sumFloor">—</span> · Rm <span id="sumRoom">—</span></div></div>
                    <div><div style="color:var(--muted);margin-bottom:2px;">Bed</div><div style="color:var(--white);font-weight:600;">Bed <span id="sumBed">—</span></div></div>
                    <div><div style="color:var(--muted);margin-bottom:2px;">Monthly</div><div style="color:var(--gold);font-weight:700;">₱1,300</div></div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <button type="button" onclick="closeBooking()" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Submit Reservation
                </button>
            </div>
        </form>
    </div>
</div>
</div>

<script>
let selBedEl = null;
function selectBed(el) {
    if (selBedEl) { selBedEl.style.outline=''; selBedEl.style.boxShadow=''; }
    selBedEl = el;
    el.style.outline = '2px solid var(--gold)';
    el.style.boxShadow = '0 0 14px rgba(201,168,76,.4)';

    document.getElementById('panFloor').textContent = el.dataset.floor;
    document.getElementById('panRoom').textContent  = el.dataset.room;
    document.getElementById('panBed').textContent   = el.dataset.bedNum;
    document.getElementById('sumFloor').textContent = el.dataset.floor;
    document.getElementById('sumRoom').textContent  = el.dataset.room;
    document.getElementById('sumBed').textContent   = el.dataset.bedNum;
    document.getElementById('formRoomId').value     = el.dataset.roomId;
    document.getElementById('formBedId').value      = el.dataset.bedId;

    const overlay = document.getElementById('bookingOverlay');
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeBooking() {
    document.getElementById('bookingOverlay').classList.remove('open');
    document.body.style.overflow = '';
    if (selBedEl) { selBedEl.style.outline=''; selBedEl.style.boxShadow=''; selBedEl=null; }
}
document.getElementById('bookingOverlay').addEventListener('click', function(e) { if(e.target===this) closeBooking(); });
</script>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>