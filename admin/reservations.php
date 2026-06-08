<?php
require_once __DIR__ . '/../includes/session.php';
requireAdmin();

$pageTitle = 'Manage Reservations';
$db = getDB();
$errors = [];
$success = '';

// Handle approval/rejection/cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $action = $_POST['action'] ?? '';
        $resId  = (int)$_POST['reservation_id'];
        $notes  = sanitizeInput($_POST['admin_notes'] ?? '');

        try {
            $db->beginTransaction();

            // Fetch the reservation with bed and room details
            $stmt = $db->prepare("
                SELECT res.*, b.room_id, b.bed_number, b.status AS bed_status
                FROM bh.reservations res
                JOIN bh.beds b ON b.id = res.bed_id
                WHERE res.id = ? FOR UPDATE
            ");
            $stmt->execute([$resId]);
            $res = $stmt->fetch();
            if (!$res) throw new Exception('Reservation not found.');

            if ($action === 'approve') {
                if ($res['status'] !== 'pending') throw new Exception('Only pending reservations can be approved.');
                // Check if bed is still available
                if ($res['bed_status'] !== 'available') throw new Exception('Bed is no longer available (status: ' . $res['bed_status'] . ').');

                // Update reservation status
                $db->prepare("UPDATE bh.reservations SET status='approved', admin_notes=?, approved_by=?, approved_at=NOW(), updated_at=NOW() WHERE id=?")
                   ->execute([$notes, $_SESSION['user_id'], $resId]);
                // Mark bed as occupied
                $db->prepare("UPDATE bh.beds SET status='occupied' WHERE id=?")->execute([$res['bed_id']]);
                // Create tenant record
                $db->prepare("
                    INSERT INTO bh.tenants (user_id, bed_id, reservation_id, move_in_date, status, advance_deposit_paid)
                    VALUES (?, ?, ?, ?, 'active', FALSE)
                ")->execute([$res['user_id'], $res['bed_id'], $resId, $res['move_in_date']]);
                $db->commit();
                $success = "Reservation #{$resId} approved. Tenant record created.";
            }
            elseif ($action === 'reject') {
                if ($res['status'] !== 'pending') throw new Exception('Only pending reservations can be rejected.');
                $db->prepare("UPDATE bh.reservations SET status='rejected', admin_notes=?, approved_by=?, updated_at=NOW() WHERE id=?")
                   ->execute([$notes, $_SESSION['user_id'], $resId]);
                // Free the bed (status back to available)
                $db->prepare("UPDATE bh.beds SET status='available' WHERE id=?")->execute([$res['bed_id']]);
                $db->commit();
                $success = "Reservation #{$resId} rejected.";
            }
            elseif ($action === 'cancel') {
                if (!in_array($res['status'], ['pending', 'approved'])) throw new Exception('Reservation cannot be cancelled.');
                $db->prepare("UPDATE bh.reservations SET status='cancelled', admin_notes=?, updated_at=NOW() WHERE id=?")
                   ->execute([$notes, $resId]);
                if ($res['status'] === 'approved') {
                    $db->prepare("UPDATE bh.beds SET status='available' WHERE id=?")->execute([$res['bed_id']]);
                    $db->prepare("UPDATE bh.tenants SET status='inactive', move_out_date=NOW() WHERE reservation_id=?")->execute([$resId]);
                } else {
                    $db->prepare("UPDATE bh.beds SET status='available' WHERE id=?")->execute([$res['bed_id']]);
                }
                $db->commit();
                $success = "Reservation #{$resId} cancelled.";
            }
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

// Fetch all reservations with proper joins, including current bed status
$reservations = $db->prepare("
    SELECT 
        res.*,
        u.name AS tenant_name,
        u.email AS tenant_email,
        u.phone AS tenant_phone,
        r.room_number,
        f.floor_number,
        b.bed_number,
        r.price,
        b.status AS bed_current_status
    FROM bh.reservations res
    JOIN bh.users u ON u.id = res.user_id
    JOIN bh.beds b ON b.id = res.bed_id
    JOIN bh.rooms r ON r.id = b.room_id
    JOIN bh.floors f ON f.id = r.floor_id
    ORDER BY 
        CASE res.status 
            WHEN 'pending' THEN 1 
            WHEN 'approved' THEN 2 
            WHEN 'rejected' THEN 3 
            WHEN 'cancelled' THEN 4 
        END,
        res.created_at DESC
");
$reservations->execute();
$reservations = $reservations->fetchAll();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_nav.php'; ?>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>
<?php endif; ?>
<?php if ($success): ?>
    <div class="flash flash-success"><?= e($success) ?></div>
<?php endif; ?>

<div class="d-flex align-center justify-between mb-4">
    <div>
        <h1 style="font-family:var(--font-display); font-size:1.6rem;">Manage Reservations</h1>
        <p class="text-muted">Review and process tenant booking requests – chosen bed is highlighted in gold.</p>
    </div>
</div>

<?php if (empty($reservations)): ?>
    <div class="empty-state">
        <i class="fas fa-calendar-alt"></i>
        <h3>No Reservations Found</h3>
        <p>There are no booking requests yet.</p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tenant</th>
                        <th>Room / Bed</th>
                        <th>Bed Status</th>
                        <th>Move-in Date</th>
                        <th>Booked On</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $res): ?>
                        <?php
                        // Determine bed status badge color
                        $bedStatusClass = match($res['bed_current_status']) {
                            'available' => 'success',
                            'occupied' => 'danger',
                            'reserved' => 'warning',
                            default => 'muted'
                        };
                        // For pending reservations, highlight the bed number
                        $bedHighlight = ($res['status'] === 'pending') ? 'style="background:rgba(201,168,76,0.2); padding:2px 8px; border-radius:20px; font-weight:bold;"' : '';
                        ?>
                        <tr>
                            <td>#<?= $res['id'] ?></td>
                            <td>
                                <strong><?= e($res['tenant_name']) ?></strong><br>
                                <small><?= e($res['tenant_email']) ?></small>
                            </td>
                            <td>
                                Floor <?= $res['floor_number'] ?> · Room <?= e($res['room_number']) ?><br>
                                <span <?= $bedHighlight ?>>
                                    <i class="fas fa-bed"></i> Bed <?= $res['bed_number'] ?>
                                </span>
                                · ₱<?= number_format($res['price'], 2) ?>/mo
                            </td>
                            <td>
                                <span class="badge badge-<?= $bedStatusClass ?>">
                                    <?= ucfirst($res['bed_current_status']) ?>
                                </span>
                            </td>
                            <td><?= formatDate($res['move_in_date']) ?></td>
                            <td><?= formatDate($res['created_at'], 'M d, Y g:i A') ?></td>
                            <td>
                                <?php
                                $statusClass = match($res['status']) {
                                    'pending' => 'warning',
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    'cancelled' => 'muted',
                                    default => 'muted'
                                };
                                ?>
                                <span class="badge badge-<?= $statusClass ?>"><?= ucfirst($res['status']) ?></span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline" onclick="openActionModal(<?= $res['id'] ?>, '<?= $res['status'] ?>')">
                                    <i class="fas fa-cog"></i> Process
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Modal for processing reservation -->
<div class="modal-overlay" id="actionModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Process Reservation</span>
            <button class="modal-close" data-modal-close="actionModal">&times;</button>
        </div>
        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="reservation_id" id="modalResId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Action</label>
                    <select name="action" id="modalAction" class="form-control" required>
                        <option value="">Select Action</option>
                        <option value="approve">Approve</option>
                        <option value="reject">Reject</option>
                        <option value="cancel">Cancel</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Admin Notes (optional)</label>
                    <textarea name="admin_notes" class="form-control" rows="3" placeholder="Reason for rejection or cancellation..."></textarea>
                </div>
                <div id="actionWarning" class="info-banner" style="margin-top:12px; display:none;">
                    <i class="fas fa-info-circle"></i> <span id="warningText"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-modal-close="actionModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
function openActionModal(resId, status) {
    document.getElementById('modalResId').value = resId;
    const actionSelect = document.getElementById('modalAction');
    const warningDiv = document.getElementById('actionWarning');
    const warningSpan = document.getElementById('warningText');
    actionSelect.value = '';
    warningDiv.style.display = 'none';
    
    actionSelect.onchange = function() {
        if (this.value === 'approve' && status !== 'pending') {
            warningSpan.innerText = 'Only pending reservations can be approved.';
            warningDiv.style.display = 'flex';
        } else if (this.value === 'reject' && status !== 'pending') {
            warningSpan.innerText = 'Only pending reservations can be rejected.';
            warningDiv.style.display = 'flex';
        } else if (this.value === 'cancel' && !['pending','approved'].includes(status)) {
            warningSpan.innerText = 'Only pending or approved reservations can be cancelled.';
            warningDiv.style.display = 'flex';
        } else {
            warningDiv.style.display = 'none';
        }
    };
    
    document.getElementById('actionModal').classList.add('open');
}
</script>

</main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>