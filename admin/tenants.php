<?php
require_once __DIR__ . '/../includes/session.php';
requireAdmin();

$pageTitle = 'Manage Tenants';
$db = getDB();
$errors = [];

// Handle actions (Remove Tenant)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $action   = $_POST['action'] ?? '';
        $tenantId = (int)$_POST['tenant_id'];

        if ($action === 'remove_tenant') {
            try {
                $db->beginTransaction();

                // 1. Get current tenant details (include bed_id and user_id)
                $t = $db->prepare('SELECT id, user_id, bed_id FROM bh.tenants WHERE id=?');
                $t->execute([$tenantId]);
                $tenant = $t->fetch();

                if ($tenant) {
                    // 2. Deactivate tenant record
                    $db->prepare("UPDATE bh.tenants SET status='inactive', move_out_date=NOW(), updated_at=NOW() WHERE id=?")
                       ->execute([$tenantId]);

                    // 3. Free the bed (set status to available; tenant_id column no longer exists)
                    $db->prepare("UPDATE bh.beds SET status='available' WHERE id=?")
                       ->execute([$tenant['bed_id']]);

                    // 4. Deactivate the user account (optional)
                    $db->prepare("UPDATE bh.users SET is_active=FALSE WHERE id=?")
                       ->execute([$tenant['user_id']]);

                    // 5. Update room status based on actual bed occupancy
                    // First, get the room_id from the bed
                    $roomStmt = $db->prepare("SELECT room_id FROM bh.beds WHERE id=?");
                    $roomStmt->execute([$tenant['bed_id']]);
                    $roomId = $roomStmt->fetchColumn();

                    if ($roomId) {
                        $db->prepare("
                            UPDATE bh.rooms 
                            SET status = CASE 
                                WHEN (SELECT COUNT(*) FROM bh.beds WHERE room_id = ? AND status = 'occupied') >= (SELECT capacity FROM bh.rooms WHERE id = ?)
                                THEN 'full'
                                ELSE 'available'
                            END
                            WHERE id = ?
                        ")->execute([$roomId, $roomId, $roomId]);
                    }

                    $db->commit();
                    redirect(APP_URL . '/admin/tenants.php', 'Tenant removed and room status updated.', 'warning');
                }
            } catch (PDOException $e) {
                $db->rollBack();
                $errors[] = 'Error removing tenant: ' . $e->getMessage();
            }
        }
    }
}

// --- Display Tenants Table (fixed joins) ---

$statusFilter = sanitizeInput($_GET['status'] ?? 'active');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 15;
$search       = sanitizeInput($_GET['q'] ?? '');

// Build WHERE clause (using tenants.status)
$where = "WHERE t.status = '$statusFilter'";
if ($search) $where .= " AND (u.name ILIKE '%$search%' OR u.email ILIKE '%$search%')";

// Count total
$totalStmt = $db->query("SELECT COUNT(*) FROM bh.tenants t JOIN bh.users u ON u.id = t.user_id $where");
$total = $totalStmt->fetchColumn();
$pager = paginate($total, $perPage, $page);

// Fetch tenants with proper joins (tenants → beds → rooms → floors)
$tenants = $db->prepare("
    SELECT t.*, u.name, u.email, u.phone,
           r.room_number, f.floor_number, b.bed_number, r.price
    FROM bh.tenants t
    JOIN bh.users u ON u.id = t.user_id
    JOIN bh.beds b ON b.id = t.bed_id
    JOIN bh.rooms r ON r.id = b.room_id
    JOIN bh.floors f ON f.id = r.floor_id
    $where
    ORDER BY t.created_at DESC
    LIMIT $perPage OFFSET {$pager['offset']}
");
$tenants->execute();
$tenants = $tenants->fetchAll();
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_nav.php'; ?>

<main class="content">
    <div class="d-flex align-center justify-between mb-4" style="flex-wrap:wrap;gap:12px;">
        <div class="d-flex gap-2">
            <a href="?status=active"   class="btn btn-sm <?= $statusFilter==='active' ?'btn-primary':'btn-ghost' ?>">Active</a>
            <a href="?status=inactive" class="btn btn-sm <?= $statusFilter==='inactive'?'btn-primary':'btn-ghost' ?>">Inactive</a>
        </div>
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" name="q" class="form-control" placeholder="Search tenants..." value="<?= e($search) ?>">
            </div>
            <button type="submit" class="btn btn-ghost btn-sm"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title"><?= ucfirst($statusFilter) ?> Tenants (<?= $total ?>)</span>
        </div>
        
        <?php if (empty($tenants)): ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h3>No <?= $statusFilter ?> tenants found.</h3>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Room & Bed</th>
                            <th>Monthly Rate</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenants as $t): ?>
                        <tr>
                            <td>
                                <strong><?= e($t['name']) ?></strong><br>
                                <small class="text-muted"><?= e($t['email']) ?></small>
                            </td>
                            <td>
                                Floor <?= $t['floor_number'] ?> - Rm <?= e($t['room_number']) ?><br>
                                <small>Bed <?= $t['bed_number'] ?></small>
                            </td>
                            <td class="text-gold"><?= formatCurrency($t['price']) ?></td>
                            <td><span class="badge badge-<?= $t['status']==='active'?'success':'muted' ?>"><?= ucfirst($t['status']) ?></span></td>
                            <td>
                                <?php if ($t['status'] === 'active'): ?>
                                <form method="POST" onsubmit="return confirm('Remove this tenant? Room status will be updated.');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="remove_tenant">
                                    <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-user-minus"></i> Remove</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>