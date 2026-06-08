<?php
require_once __DIR__ . '/../includes/session.php';
requireAdmin();

$pageTitle = 'Manage Rooms';
$db = getDB();
$errors = [];

define('DEFAULT_MONTHLY_RATE', 1300);
define('DEFAULT_CAPACITY', 4);   // default beds per room

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_room') {
            $floorId    = (int)$_POST['floor_id'];
            $roomNumber = sanitizeInput($_POST['room_number'] ?? '');
            $price      = (float)($_POST['price'] ?: DEFAULT_MONTHLY_RATE);
            $capacity   = (int)($_POST['capacity'] ?: DEFAULT_CAPACITY);
            $status     = sanitizeInput($_POST['status'] ?? 'available');
            $amenities  = sanitizeInput($_POST['amenities'] ?? '');
            $desc       = sanitizeInput($_POST['description'] ?? '');

            if (!$floorId || !$roomNumber) {
                $errors[] = 'Floor and room number are required.';
            } elseif ($capacity < 1 || $capacity > 20) {
                $errors[] = 'Capacity must be between 1 and 20 beds.';
            } else {
                try {
                    $db->beginTransaction();
                    $stmt = $db->prepare(
                        'INSERT INTO bh.rooms (floor_id, room_number, price, capacity, status, amenities, description, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->execute([$floorId, $roomNumber, $price, $capacity, $status, $amenities, $desc]);
                    $roomId = (int)$db->lastInsertId();
                    // Create beds according to capacity
                    for ($i = 1; $i <= $capacity; $i++) {
                        $db->prepare('INSERT INTO bh.beds (room_id, bed_number, status) VALUES (?, ?, ?)')
                           ->execute([$roomId, $i, 'available']);
                    }
                    $db->commit();
                    redirect(APP_URL . '/admin/rooms.php', "Room {$roomNumber} added with {$capacity} bed(s) at ₱" . number_format($price, 0) . '/mo per bed!');
                } catch (PDOException $e) {
                    $db->rollBack();
                    $errors[] = 'Error adding room: ' . $e->getMessage();
                }
            }

        } elseif ($action === 'edit_room') {
            $roomId    = (int)$_POST['room_id'];
            $price     = (float)($_POST['price'] ?: DEFAULT_MONTHLY_RATE);
            $newCapacity = (int)($_POST['capacity'] ?: DEFAULT_CAPACITY);
            $status    = sanitizeInput($_POST['status'] ?? 'available');
            $amenities = sanitizeInput($_POST['amenities'] ?? '');
            $desc      = sanitizeInput($_POST['description'] ?? '');

            if ($newCapacity < 1 || $newCapacity > 20) {
                $errors[] = 'Capacity must be between 1 and 20 beds.';
            } else {
                try {
                    $db->beginTransaction();

                    // Update room details
                    $db->prepare('UPDATE bh.rooms SET price=?, capacity=?, status=?, amenities=?, description=?, updated_at=NOW() WHERE id=?')
                       ->execute([$price, $newCapacity, $status, $amenities, $desc, $roomId]);

                    // Get current beds
                    $existing = $db->prepare('SELECT id, bed_number FROM bh.beds WHERE room_id=? ORDER BY bed_number');
                    $existing->execute([$roomId]);
                    $existingBeds = $existing->fetchAll(PDO::FETCH_ASSOC);
                    $currentCount = count($existingBeds);

                    if ($newCapacity > $currentCount) {
                        // Add new beds
                        for ($i = $currentCount + 1; $i <= $newCapacity; $i++) {
                            $db->prepare('INSERT INTO bh.beds (room_id, bed_number, status) VALUES (?, ?, ?)')
                               ->execute([$roomId, $i, 'available']);
                        }
                    } elseif ($newCapacity < $currentCount) {
                        // Remove extra beds – only if they are not occupied
                        $toDelete = array_slice($existingBeds, $newCapacity);
                        foreach ($toDelete as $bed) {
                            // Check status
                            if ($bed['status'] !== 'available') {
                                throw new Exception("Cannot remove bed {$bed['bed_number']} because it is {$bed['status']}.");
                            }
                            $db->prepare('DELETE FROM bh.beds WHERE id=?')->execute([$bed['id']]);
                        }
                    }

                    $db->commit();
                    redirect(APP_URL . '/admin/rooms.php', 'Room updated successfully!');
                } catch (Exception $e) {
                    $db->rollBack();
                    $errors[] = 'Error updating room: ' . $e->getMessage();
                }
            }

        } elseif ($action === 'delete_room') {
            $roomId = (int)$_POST['room_id'];
            try {
                $db->prepare('DELETE FROM bh.rooms WHERE id=?')->execute([$roomId]);
                redirect(APP_URL . '/admin/rooms.php', 'Room deleted.', 'warning');
            } catch (PDOException $e) {
                $errors[] = 'Cannot delete room with active tenants or reservations.';
            }
        }
    }
}

// Fetch floors
$floors = $db->query('SELECT * FROM bh.floors ORDER BY floor_number')->fetchAll();
$currentFloor = (int)($_GET['floor'] ?? ($floors[0]['floor_number'] ?? 1));

// Fetch rooms for current floor
$rooms = $db->prepare("
    SELECT r.*, f.floor_number,
           COUNT(b.id) AS total_beds,
           SUM(CASE WHEN b.status='available' THEN 1 ELSE 0 END) AS available_beds,
           SUM(CASE WHEN b.status='occupied'  THEN 1 ELSE 0 END) AS occupied_beds
    FROM bh.rooms r
    JOIN bh.floors f ON f.id = r.floor_id
    LEFT JOIN bh.beds b ON b.room_id = r.id
    WHERE f.floor_number = ?
    GROUP BY r.id, f.floor_number
    ORDER BY r.room_number
");
$rooms->execute([$currentFloor]);
$rooms = $rooms->fetchAll();

// Fetch single room for edit
$editRoom = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare('SELECT r.*, f.floor_number FROM bh.rooms r JOIN bh.floors f ON f.id=r.floor_id WHERE r.id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $editRoom = $stmt->fetch();
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_nav.php'; ?>

<?php foreach ($errors as $err): ?>
<div class="flash flash-error" style="margin-bottom:16px;"><i class="fas fa-times-circle"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<!-- Toolbar -->
<div class="d-flex align-center justify-between mb-4" style="flex-wrap:wrap;gap:12px;">
    <div class="floor-tabs" style="margin-bottom:0;">
        <?php foreach ($floors as $floor): ?>
        <a href="?floor=<?= $floor['floor_number'] ?>"
           class="floor-tab <?= $currentFloor == $floor['floor_number'] ? 'active' : '' ?>">
            <i class="fas fa-layer-group"></i> <?= e($floor['floor_name']) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <button class="btn btn-primary" data-modal-open="addRoomModal">
        <i class="fas fa-plus"></i> Add Room
    </button>
</div>

<!-- Default rate info -->
<div class="deposit-info mb-4">
    <div class="deposit-info-icon"><i class="fas fa-info-circle"></i></div>
    <div class="deposit-info-text">
        <div class="deposit-info-title">Standard Rate: ₱1,300/month per bed</div>
        <div class="deposit-info-sub">Each room can have a custom number of beds. The monthly rate applies per bed/tenant. An advance deposit is collected upon room approval.</div>
    </div>
</div>

<!-- Rooms Grid -->
<?php if (empty($rooms)): ?>
<div class="empty-state">
    <i class="fas fa-door-open"></i>
    <h3>No Rooms on This Floor</h3>
    <p>Click "Add Room" to get started.</p>
</div>
<?php else: ?>
<div class="rooms-grid">
    <?php foreach ($rooms as $room): ?>
    <?php
    $bedStmt = $db->prepare('SELECT * FROM bh.beds WHERE room_id=? ORDER BY bed_number');
    $bedStmt->execute([$room['id']]);
    $beds = $bedStmt->fetchAll();
    $statusBadge = ['available'=>'success','full'=>'danger','maintenance'=>'warning'];
    $b = $statusBadge[$room['status']] ?? 'muted';
    ?>
    <div class="room-card">
        <div class="room-card-header">
            <div>
                <div class="room-number">Room <?= e($room['room_number']) ?></div>
                <div class="room-floor">Floor <?= $room['floor_number'] ?></div>
                <div class="room-capacity" style="font-size:0.68rem;color:var(--gold);margin-top:2px;"><?= $room['capacity'] ?> beds total</div>
            </div>
            <span class="badge badge-<?= $b ?>"><?= ucfirst($room['status']) ?></span>
        </div>
        <div class="card-body">
            <div class="room-price">₱1,300<span>/bed/month</span></div>
            <?php if ($room['amenities']): ?>
            <div class="room-amenities">
                <?php foreach (explode(',', $room['amenities']) as $a): ?>
                <span class="amenity-tag"><?= e(trim($a)) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Bed visualization -->
            <div class="beds-row">
                <?php foreach ($beds as $bed): ?>
                <div class="bed-slot <?= $bed['status'] ?>"
                     title="Bed <?= $bed['bed_number'] ?> — <?= ucfirst($bed['status']) ?>">
                    B<?= $bed['bed_number'] ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="font-size:0.8rem;color:var(--clr-muted);margin-bottom:16px;">
                <span style="color:var(--clr-success);"><i class="fas fa-bed"></i> <?= $room['available_beds'] ?> available</span>
                &nbsp;&bull;&nbsp;
                <span style="color:var(--clr-danger);"><i class="fas fa-user"></i> <?= $room['occupied_beds'] ?> occupied</span>
                &nbsp;&bull;&nbsp;
                <?= $room['total_beds'] ?> total
            </div>

            <div class="d-flex gap-2">
                <a href="?floor=<?= $currentFloor ?>&edit=<?= $room['id'] ?>"
                   class="btn btn-sm btn-ghost" style="flex:1;">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="<?= APP_URL ?>/admin/beds.php?room_id=<?= $room['id'] ?>"
                   class="btn btn-sm btn-outline" style="flex:1;">
                    <i class="fas fa-bed"></i> Beds
                </a>
                <form method="POST" style="flex:0;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_room">
                    <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger"
                            data-confirm="Delete Room <?= e($room['room_number']) ?>? This cannot be undone.">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Add Room Modal -->
<div class="modal-overlay" id="addRoomModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add New Room</span>
            <button class="modal-close" data-modal-close="addRoomModal">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" data-validate>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_room">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Floor *</label>
                        <select name="floor_id" class="form-control" required>
                            <option value="">Select Floor</option>
                            <?php foreach ($floors as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= $currentFloor == $f['floor_number'] ? 'selected' : '' ?>>
                                <?= e($f['floor_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Room Number *</label>
                        <input type="text" name="room_number" class="form-control" placeholder="e.g. 101" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Monthly Price / Bed</label>
                        <input type="number" name="price" class="form-control"
                               value="<?= DEFAULT_MONTHLY_RATE ?>" min="0" step="50">
                        <div style="font-size:0.75rem;color:var(--clr-muted);margin-top:4px;">
                            Default: ₱1,300 per bed/month
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Number of Beds (Capacity) *</label>
                        <input type="number" name="capacity" class="form-control"
                               value="<?= DEFAULT_CAPACITY ?>" min="1" max="20" required>
                        <div style="font-size:0.75rem;color:var(--clr-muted);margin-top:4px;">
                            Each bed will be created automatically.
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="available">Available</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Amenities</label>
                    <input type="text" name="amenities" class="form-control"
                           placeholder="WiFi, Aircon, Private Bathroom">
                    <div style="font-size:0.75rem;color:var(--clr-muted);margin-top:4px;">Separate with commas</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Room details..."></textarea>
                </div>

                <div class="modal-footer" style="padding:0;border:none;">
                    <button type="button" class="btn btn-ghost" data-modal-close="addRoomModal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Room Modal -->
<?php if ($editRoom): ?>
<div class="modal-overlay open" id="editRoomModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Edit Room <?= e($editRoom['room_number']) ?></span>
            <a href="?floor=<?= $currentFloor ?>" class="modal-close">&times;</a>
        </div>
        <div class="modal-body">
            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="edit_room">
                <input type="hidden" name="room_id" value="<?= $editRoom['id'] ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Monthly Price / Bed</label>
                        <input type="number" name="price" class="form-control"
                               value="<?= $editRoom['price'] ?: DEFAULT_MONTHLY_RATE ?>" min="0" step="50" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Number of Beds (Capacity) *</label>
                        <input type="number" name="capacity" class="form-control"
                               value="<?= $editRoom['capacity'] ?: DEFAULT_CAPACITY ?>" min="1" max="20" required>
                        <div style="font-size:0.75rem;color:var(--clr-warning);margin-top:4px;">
                            <i class="fas fa-exclamation-triangle"></i> Reducing capacity will delete extra beds – only if they are available.
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <?php foreach (['available','full','maintenance'] as $s): ?>
                        <option value="<?= $s ?>" <?= $editRoom['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Amenities</label>
                    <input type="text" name="amenities" class="form-control"
                           value="<?= e($editRoom['amenities'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= e($editRoom['description'] ?? '') ?></textarea>
                </div>

                <div class="modal-footer" style="padding:0;border:none;">
                    <a href="?floor=<?= $currentFloor ?>" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

</main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>