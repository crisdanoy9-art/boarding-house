<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();

$pageTitle = 'Book a Room';
$db  = getDB();
$uid = $_SESSION['user_id'];
$errors = [];

define('GCASH_NUMBER', '09633951825');
define('GCASH_NAME',   'Cris Danoy');

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
        $gcashRef = sanitizeInput($_POST['gcash_reference'] ?? '');
        $notes    = sanitizeInput($_POST['notes'] ?? '');

        if (!$roomId || !$bedId || !$moveIn) { $errors[] = 'Please select a room, bed, and move-in date.'; }
        elseif (strlen($gcashRef) < 8) { $errors[] = 'GCash reference number is required (min. 8 characters). Please send ₱1,300 first.'; }
        elseif (strtotime($moveIn) < strtotime('today')) { $errors[] = 'Move-in date cannot be in the past.'; }
        else {
            try {
                $db->beginTransaction();
                $bc = $db->prepare("SELECT id FROM bh.beds WHERE id=? AND room_id=? AND status='available'");
                $bc->execute([$bedId,$roomId]);
                if (!$bc->fetch()) throw new Exception('That bed is no longer available. Please pick another.');

                $fullNotes = "GCASH_REF: {$gcashRef}".($notes?" | {$notes}":'');
                $db->prepare("INSERT INTO bh.reservations(user_id,room_id,bed_id,move_in_date,notes,status,created_at) VALUES(?,?,?,?,?,'pending',NOW())")->execute([$uid,$roomId,$bedId,$moveIn,$fullNotes]);
                $db->prepare("UPDATE bh.beds SET status='reserved',tenant_id=? WHERE id=?")->execute([$uid,$bedId]);
                $db->commit();
                redirect(APP_URL.'/user/reservations.php','✅ Reservation submitted! GCash ref #'.$gcashRef.' noted. Admin will verify and approve.','success');
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
    <?php foreach ([['1','Select Bed'],['2','Pay GCash'],['3','Submit Ref'],['4','Admin Approves']] as $i=>[$n,$lbl]): ?>
    <div style="display:flex;flex-direction:column;align-items:center;flex:1;position:relative;">
        <?php if($i<3): ?><div style="position:absolute;top:17px;left:50%;right:-50%;height:2px;background:var(--border2);z-index:0;"></div><?php endif; ?>
        <div style="width:34px;height:34px;border-radius:50%;background:<?= $i===0?'var(--gold)':'var(--surface2)' ?>;border:2px solid <?= $i===0?'var(--gold)':'var(--border2)' ?>;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:<?= $i===0?'#06060e':'var(--muted)' ?>;z-index:1;position:relative;"><?= $n ?></div>
        <div style="font-size:.66rem;color:<?= $i===0?'var(--gold)':'var(--muted)' ?>;margin-top:5px;text-align:center;"><?= $lbl ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Deposit notice -->
<?php if (!$existingTenancy && !$pendingRes): ?>
<div class="deposit-info mb-4">
    <div class="deposit-info-icon"><i class="fas fa-peso-sign"></i></div>
    <div class="deposit-info-text">
        <div class="deposit-info-title">₱1,300 Advance Deposit via GCash Required</div>
        <div class="deposit-info-sub">Send <strong>₱1,300</strong> to GCash <strong><?= GCASH_NUMBER ?></strong> (<?= GCASH_NAME ?>) first. Enter the reference number when submitting. No payment = no reservation approval.</div>
    </div>
    <div style="text-align:right;flex-shrink:0;">
        <div style="font-family:var(--font-display);font-size:1.4rem;color:var(--gold);">₱1,300</div>
        <div style="font-size:.68rem;color:var(--muted);text-transform:uppercase;">deposit</div>
    </div>
</div>
<?php endif; ?>

<div class="d-flex align-center justify-between mb-3">
    <div>
        <h2 style="font-family:var(--font-display);font-size:1.4rem;color:var(--white);">Available Rooms</h2>
        <p style="color:var(--muted);font-size:.82rem;">Click a green bed to select it, then enter your GCash reference</p>
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
            <div style="font-size:.72rem;color:var(--muted);">
                <i class="fas fa-info-circle" style="color:var(--gold);"></i>
                GCash deposit: <strong style="color:var(--gold);">₱1,300</strong> to <?= GCASH_NUMBER ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══ Booking Overlay Modal ══ -->
<div class="booking-overlay" id="bookingOverlay">
<div class="booking-panel">
    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border);">
        <div>
            <div style="font-family:var(--font-display);font-size:1.05rem;color:var(--white);">Complete Your Booking</div>
            <div style="font-size:.76rem;color:var(--muted);">F<span id="panFloor">—</span> · Rm <span id="panRoom">—</span> · Bed <span id="panBed">—</span></div>
        </div>
        <button onclick="closeBooking()" style="background:none;border:none;color:var(--muted);font-size:1.3rem;cursor:pointer;padding:4px 8px;border-radius:var(--r-sm);">×</button>
    </div>

    <div style="padding:20px 22px;">
        <!-- Step 1: GCash -->
        <div class="gcash-card mb-4">
            <div style="font-size:.62rem;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.12em;margin-bottom:7px;position:relative;z-index:1;">Step 1 — Send Advance Deposit</div>
            <div class="gcash-logo" style="position:relative;z-index:1;">G<span>Cash</span></div>
            <div class="gcash-qr-box" style="position:relative;z-index:1;">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=155x155&data=<?= urlencode('GCash|'.GCASH_NUMBER.'|'.GCASH_NAME) ?>&bgcolor=ffffff&color=000000&margin=4"
                     alt="GCash QR" width="155" height="155">
            </div>
            <div class="gcash-number" style="position:relative;z-index:1;"><?= GCASH_NUMBER ?></div>
            <div class="gcash-name-sub" style="position:relative;z-index:1;"><?= GCASH_NAME ?> · Nadelas Boarding House</div>
            <div class="gcash-amount" style="position:relative;z-index:1;"><i class="fas fa-peso-sign"></i> Send exactly ₱1,300.00</div>
        </div>

        <!-- Instructions -->
        <div style="background:rgba(240,168,50,.07);border:1px solid rgba(240,168,50,.2);border-radius:var(--r-md);padding:13px 16px;margin-bottom:18px;font-size:.8rem;">
            <div style="color:var(--warning);font-weight:700;margin-bottom:7px;"><i class="fas fa-exclamation-triangle"></i> Before submitting:</div>
            <ul style="list-style:none;display:grid;gap:5px;color:var(--muted);">
                <li><i class="fas fa-check" style="color:var(--gold);margin-right:5px;"></i>Open GCash → Send Money → <?= GCASH_NUMBER ?></li>
                <li><i class="fas fa-check" style="color:var(--gold);margin-right:5px;"></i>Amount: <strong style="color:var(--white);">₱1,300.00 exactly</strong></li>
                <li><i class="fas fa-check" style="color:var(--gold);margin-right:5px;"></i>Copy your GCash <strong style="color:var(--white);">Reference Number</strong> after sending</li>
                <li><i class="fas fa-check" style="color:var(--gold);margin-right:5px;"></i>Enter it below — admin will verify before approving</li>
            </ul>
        </div>

        <!-- Step 2: Form -->
        <div style="font-size:.63rem;color:var(--gold);text-transform:uppercase;letter-spacing:.1em;font-weight:700;margin-bottom:12px;">Step 2 — Submit Reservation Details</div>

        <form method="POST" action="" data-validate id="bookingForm">
            <?= csrfField() ?>
            <input type="hidden" name="room_id" id="formRoomId">
            <input type="hidden" name="bed_id"  id="formBedId">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label class="form-label">GCash Reference No. *</label>
                    <div class="input-group">
                        <i class="fas fa-hashtag input-icon"></i>
                        <input type="text" name="gcash_reference" id="gcashRefInput"
                               class="form-control" placeholder="e.g. 1234567890"
                               required minlength="8"
                               style="padding-left:38px;font-family:monospace;font-weight:700;letter-spacing:.04em;">
                    </div>
                    <div class="form-hint">From your GCash receipt</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Move-In Date *</label>
                    <input type="date" name="move_in_date" id="formMoveIn" class="form-control"
                           min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                </div>
            </div>

            <!-- Summary -->
            <div style="background:rgba(201,168,76,.07);border:1px solid rgba(201,168,76,.2);border-radius:var(--r-md);padding:13px 16px;margin-bottom:16px;">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;font-size:.8rem;">
                    <div><div style="color:var(--muted);margin-bottom:2px;">Room</div><div style="color:var(--white);font-weight:600;">F<span id="sumFloor">—</span> · Rm <span id="sumRoom">—</span></div></div>
                    <div><div style="color:var(--muted);margin-bottom:2px;">Bed</div><div style="color:var(--white);font-weight:600;">Bed <span id="sumBed">—</span></div></div>
                    <div><div style="color:var(--muted);margin-bottom:2px;">Monthly</div><div style="color:var(--gold);font-weight:700;">₱1,300</div></div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <button type="button" onclick="closeBooking()" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary" onclick="return validateRef()">
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
    setTimeout(() => document.getElementById('gcashRefInput')?.focus(), 400);
}
function closeBooking() {
    document.getElementById('bookingOverlay').classList.remove('open');
    document.body.style.overflow = '';
    if (selBedEl) { selBedEl.style.outline=''; selBedEl.style.boxShadow=''; selBedEl=null; }
}
function validateRef() {
    const r = document.getElementById('gcashRefInput').value.trim();
    if (!r || r.length < 8) {
        document.getElementById('gcashRefInput').style.borderColor = 'var(--danger)';
        document.getElementById('gcashRefInput').focus();
        return false;
    }
    return true;
}
document.getElementById('bookingOverlay').addEventListener('click', function(e) { if(e.target===this) closeBooking(); });
</script>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
