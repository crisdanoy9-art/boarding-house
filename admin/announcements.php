<?php
require_once __DIR__ . '/../includes/session.php';
requireAdmin();

$pageTitle = 'Announcements';
$db = getDB();
$errors = [];

// Ensure table exists
try {
    $db->query("
        CREATE TABLE IF NOT EXISTS bh.announcements (
            id SERIAL PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            type VARCHAR(20) DEFAULT 'info' CHECK (type IN ('info','warning','danger','success','gold')),
            icon VARCHAR(50) DEFAULT 'bullhorn',
            audience VARCHAR(10) DEFAULT 'all' CHECK (audience IN ('all','tenants','admin')),
            is_active BOOLEAN DEFAULT TRUE,
            expires_at TIMESTAMP,
            created_by INTEGER REFERENCES bh.users(id),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $title    = sanitizeInput($_POST['title'] ?? '');
            $body     = sanitizeInput($_POST['body'] ?? '');
            $type     = sanitizeInput($_POST['type'] ?? 'info');
            $icon     = sanitizeInput($_POST['icon'] ?? 'bullhorn');
            $audience = sanitizeInput($_POST['audience'] ?? 'all');
            $expires  = sanitizeInput($_POST['expires_at'] ?? '');

            if (!$title || !$body) { $errors[] = 'Title and body are required.'; }
            else {
                try {
                    $db->prepare("
                        INSERT INTO bh.announcements (title, body, type, icon, audience, expires_at, created_by)
                        VALUES (?,?,?,?,?,?,?)
                    ")->execute([$title, $body, $type, $icon, $audience, $expires ?: null, $_SESSION['user_id']]);
                    redirect(APP_URL.'/admin/announcements.php', 'Announcement posted!');
                } catch (Exception $e) { $errors[] = $e->getMessage(); }
            }

        } elseif ($action === 'toggle') {
            $id = (int)$_POST['ann_id'];
            $db->prepare("UPDATE bh.announcements SET is_active = NOT is_active WHERE id=?")->execute([$id]);
            redirect(APP_URL.'/admin/announcements.php', 'Status updated.');

        } elseif ($action === 'delete') {
            $id = (int)$_POST['ann_id'];
            $db->prepare("DELETE FROM bh.announcements WHERE id=?")->execute([$id]);
            redirect(APP_URL.'/admin/announcements.php', 'Announcement deleted.', 'warning');
        }
    }
}

$announcements = $db->query("
    SELECT a.*, u.name AS creator
    FROM bh.announcements a
    LEFT JOIN bh.users u ON u.id = a.created_by
    ORDER BY a.created_at DESC
")->fetchAll();

$typeIcons = [
    'info'    => ['fa-info-circle',      'ann-info'],
    'warning' => ['fa-exclamation-triangle','ann-warning'],
    'danger'  => ['fa-exclamation-circle','ann-danger'],
    'success' => ['fa-check-circle',     'ann-success'],
    'gold'    => ['fa-star',             'ann-gold'],
];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_nav.php'; ?>

<?php foreach ($errors as $err): ?>
<div class="flash flash-error mb-3"><i class="fas fa-times-circle"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<!-- Quick announcement types -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:24px;">
    <?php
    $quickTypes = [
        ['Monthly Payment Due', 'This is a reminder that monthly rent of ₱1,300 is due on the 1st of the month.', 'warning', 'fa-calendar-day', 'ann-warning', 'Monthly Reminder'],
        ['3-Day Payment Warning', 'Your monthly payment is due in 3 days. Please settle your balance to avoid penalties.', 'danger', 'fa-clock', 'ann-danger', 'Payment Alert'],
        ['Water Interruption', 'There will be a water interruption scheduled. Please store sufficient water.', 'info', 'fa-tint-slash', 'ann-info', 'Utility Notice'],
        ['Power Interruption', 'There will be a power interruption. Please prepare accordingly.', 'warning', 'fa-bolt', 'ann-warning', 'Utility Notice'],
        ['Meeting Notice', 'A house meeting is scheduled. All tenants are required to attend.', 'gold', 'fa-users', 'ann-gold', 'Meeting'],
    ];
    foreach ($quickTypes as [$title, $body, $type, $icon, $cls, $label]):
    ?>
    <button onclick="fillQuickAnn(<?= htmlspecialchars(json_encode([$title,$body,$type,str_replace('fa-','',$icon)]),ENT_QUOTES) ?>)"
            class="announcement-card <?= $cls ?>" style="border:none;cursor:pointer;text-align:left;width:100%;transition:var(--t);">
        <div class="ann-icon"><i class="fas <?= $icon ?>"></i></div>
        <div>
            <div class="ann-title" style="font-size:.8rem;"><?= $label ?></div>
            <div class="ann-body" style="font-size:.72rem;margin-top:2px;">Quick post template</div>
        </div>
    </button>
    <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1.8fr;gap:24px;align-items:start;">
    <!-- Add form -->
    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-plus" style="color:var(--gold);font-size:.85rem;"></i> Post Announcement</span></div>
        <div class="card-body">
            <form method="POST" data-validate>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" id="annTitle" class="form-control" placeholder="e.g. Monthly Payment Reminder" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Message *</label>
                    <textarea name="body" id="annBody" class="form-control" rows="4" placeholder="Announcement message..." required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select name="type" id="annType" class="form-control">
                            <option value="info">ℹ Info (Blue)</option>
                            <option value="warning">⚠ Warning (Orange)</option>
                            <option value="danger">🚨 Urgent (Red)</option>
                            <option value="success">✅ Good News (Green)</option>
                            <option value="gold">⭐ Important (Gold)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Audience</label>
                        <select name="audience" class="form-control">
                            <option value="all">All Users</option>
                            <option value="tenants">Tenants Only</option>
                            <option value="admin">Admin Only</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Icon (FA class)</label>
                        <input type="text" name="icon" id="annIcon" class="form-control" value="bullhorn" placeholder="e.g. calendar-day">
                        <div class="form-hint">FontAwesome icon name without "fa-"</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expires At</label>
                        <input type="datetime-local" name="expires_at" class="form-control">
                        <div class="form-hint">Leave blank = permanent</div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-full">
                    <i class="fas fa-paper-plane"></i> Post Announcement
                </button>
            </form>
        </div>
    </div>

    <!-- List -->
    <div>
        <?php if (empty($announcements)): ?>
        <div class="empty-state" style="padding:60px;">
            <i class="fas fa-bullhorn"></i>
            <h3>No Announcements</h3>
            <p>Post your first announcement using the form.</p>
        </div>
        <?php else: ?>
        <div style="display:grid;gap:14px;">
            <?php foreach ($announcements as $ann):
                [$faIcon, $cls] = $typeIcons[$ann['type']] ?? ['fa-info-circle','ann-info'];
                $expired = $ann['expires_at'] && strtotime($ann['expires_at']) < time();
            ?>
            <div class="announcement-card <?= $cls ?>" style="<?= (!$ann['is_active'] || $expired) ? 'opacity:.5;' : '' ?>">
                <div class="ann-icon"><i class="fas fa-<?= e($ann['icon']) ?>"></i></div>
                <div style="flex:1;min-width:0;">
                    <div class="d-flex align-center justify-between gap-2" style="flex-wrap:wrap;">
                        <div class="ann-title"><?= e($ann['title']) ?></div>
                        <div class="d-flex gap-2">
                            <span class="badge badge-<?= ['info'=>'info','warning'=>'warning','danger'=>'danger','success'=>'success','gold'=>'gold'][$ann['type']] ?>">
                                <?= ucfirst($ann['type']) ?>
                            </span>
                            <?php if ($expired): ?>
                            <span class="badge badge-muted">Expired</span>
                            <?php elseif ($ann['is_active']): ?>
                            <span class="badge badge-success"><i class="fas fa-circle" style="font-size:.4rem;"></i> Live</span>
                            <?php else: ?>
                            <span class="badge badge-muted">Off</span>
                            <?php endif; ?>
                            <span class="badge badge-muted" style="text-transform:capitalize;"><?= e($ann['audience']) ?></span>
                        </div>
                    </div>
                    <div class="ann-body"><?= e($ann['body']) ?></div>
                    <div class="ann-date">
                        Posted by <?= e($ann['creator'] ?? 'Admin') ?> — <?= formatDate($ann['created_at'], 'M d, Y g:i A') ?>
                        <?php if ($ann['expires_at']): ?>&nbsp;• Expires: <?= formatDate($ann['expires_at'], 'M d, Y g:i A') ?><?php endif; ?>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <form method="POST" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="ann_id" value="<?= $ann['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-ghost">
                                <i class="fas fa-<?= $ann['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                <?= $ann['is_active'] ? 'Disable' : 'Enable' ?>
                            </button>
                        </form>
                        <form method="POST" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="ann_id" value="<?= $ann['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" data-confirm="Delete this announcement?">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function fillQuickAnn([title, body, type, icon]) {
    document.getElementById('annTitle').value = title;
    document.getElementById('annBody').value  = body;
    document.getElementById('annType').value  = type;
    document.getElementById('annIcon').value  = icon;
}
</script>

</main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>