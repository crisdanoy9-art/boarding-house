<?php
require_once __DIR__ . '/../includes/session.php';
requireAdmin();

$pageTitle = 'System Maintenance';
$db = getDB();
$errors = [];

try {
    $db->query("CREATE TABLE IF NOT EXISTS bh.system_settings (key VARCHAR(100) PRIMARY KEY, value TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
} catch (Exception $e) {}

function getSetting($db, $key, $default = '') {
    try { $s=$db->prepare("SELECT value FROM bh.system_settings WHERE key=?"); $s->execute([$key]); $r=$s->fetch(); return $r?$r['value']:$default; } catch(Exception $e){return $default;}
}
function setSetting($db, $key, $value) {
    try { $db->prepare("INSERT INTO bh.system_settings(key,value,updated_at) VALUES(?,?,NOW()) ON CONFLICT(key) DO UPDATE SET value=EXCLUDED.value,updated_at=NOW()")->execute([$key,$value]); } catch(Exception $e){}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST[CSRF_TOKEN_NAME] ?? '')) { $errors[] = 'Invalid request.'; }
    else {
        $action = $_POST['action'] ?? '';
        if ($action === 'toggle_maintenance') {
            $isOn = getSetting($db,'maintenance_mode','0') === '1';
            setSetting($db,'maintenance_mode',$isOn?'0':'1');
            setSetting($db,'maintenance_message',sanitizeInput($_POST['message']??'System is under maintenance.'));
            setSetting($db,'maintenance_eta',sanitizeInput($_POST['eta']??''));
            redirect(APP_URL.'/admin/maintenance.php','Maintenance mode '.($isOn?'disabled':'enabled').'.');
        }
        if ($action === 'save_settings') {
            setSetting($db,'site_name',sanitizeInput($_POST['site_name']??APP_NAME));
            setSetting($db,'admin_phone',sanitizeInput($_POST['admin_phone']??''));
            setSetting($db,'admin_facebook',sanitizeInput($_POST['admin_facebook']??''));
            setSetting($db,'admin_email',sanitizeInput($_POST['admin_email']??''));
            redirect(APP_URL.'/admin/maintenance.php','Settings saved!');
        }
    }
}

$maintenanceOn = getSetting($db,'maintenance_mode','0') === '1';
$maintMsg      = getSetting($db,'maintenance_message','System is under maintenance. Please check back later.');
$maintEta      = getSetting($db,'maintenance_eta','');
$adminPhone    = getSetting($db,'admin_phone','09633951825');
$adminFacebook = getSetting($db,'admin_facebook','https://www.facebook.com/cris.danoy.7/');
$adminEmail    = getSetting($db,'admin_email','crisdanoy9@gmail.com');
$siteName      = getSetting($db,'site_name',APP_NAME);

// DB stats
try {
    $dbSize   = $db->query("SELECT pg_size_pretty(pg_database_size(current_database()))")->fetchColumn();
    $tblCount = $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='bh'")->fetchColumn();
    $idxCount = $db->query("SELECT COUNT(*) FROM pg_indexes WHERE schemaname='bh'")->fetchColumn();
} catch(Exception $e) { $dbSize='—'; $tblCount='—'; $idxCount='—'; }
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_nav.php'; ?>

<?php foreach ($errors as $err): ?>
<div class="flash flash-error mb-3"><i class="fas fa-times-circle"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<?php if ($maintenanceOn): ?>
<div class="announcement-card ann-danger mb-4">
    <div class="ann-icon"><i class="fas fa-tools"></i></div>
    <div>
        <div class="ann-title">🔧 MAINTENANCE MODE IS ACTIVE</div>
        <div class="ann-body">Tenants are locked out. Only admins can access the system.</div>
    </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px;">
    <!-- Maintenance toggle -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-tools" style="color:var(--gold);margin-right:6px;"></i>Maintenance Mode</span>
            <span class="badge badge-<?= $maintenanceOn?'danger':'success' ?>"><?= $maintenanceOn?'ACTIVE':'OFF' ?></span>
        </div>
        <div class="card-body">
            <p style="color:var(--muted);font-size:.85rem;margin-bottom:18px;">
                When enabled, all tenant pages show a maintenance screen. Only admins have access.
            </p>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="toggle_maintenance">
                <div class="form-group">
                    <label class="form-label">Maintenance Message</label>
                    <textarea name="message" class="form-control" rows="3"><?= e($maintMsg) ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Expected Return Time (optional)</label>
                    <input type="datetime-local" name="eta" class="form-control" value="<?= e($maintEta) ?>">
                    <div class="form-hint">Shown to tenants as estimated return time.</div>
                </div>
                <button type="submit" class="btn <?= $maintenanceOn?'btn-success':'btn-danger' ?> btn-full"
                        data-confirm="<?= $maintenanceOn?'Restore tenant access?':'Lock out all tenants?' ?>">
                    <i class="fas fa-<?= $maintenanceOn?'check':'tools' ?>"></i>
                    <?= $maintenanceOn?'Disable Maintenance Mode':'Enable Maintenance Mode' ?>
                </button>
            </form>
        </div>
    </div>

    <!-- System Settings -->
    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-cog" style="color:var(--gold);margin-right:6px;"></i>System Settings</span></div>
        <div class="card-body">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_settings">
                <div class="form-group">
                    <label class="form-label">Site Name</label>
                    <div class="input-group"><i class="fas fa-building input-icon"></i>
                    <input type="text" name="site_name" class="form-control" value="<?= e($siteName) ?>"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Owner Phone / GCash</label>
                    <div class="input-group"><i class="fas fa-phone input-icon"></i>
                    <input type="text" name="admin_phone" class="form-control" value="<?= e($adminPhone) ?>"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Facebook Page URL</label>
                    <div class="input-group"><i class="fab fa-facebook input-icon"></i>
                    <input type="url" name="admin_facebook" class="form-control" value="<?= e($adminFacebook) ?>"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Admin Email</label>
                    <div class="input-group"><i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="admin_email" class="form-control" value="<?= e($adminEmail) ?>"></div>
                </div>
                <button type="submit" class="btn btn-primary btn-full"><i class="fas fa-save"></i> Save Settings</button>
            </form>
        </div>
    </div>
</div>

<!-- System Info Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;margin-bottom:22px;">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-server"></i></div>
        <div class="stat-info"><div class="stat-value" style="font-size:.95rem;">PHP <?= PHP_VERSION ?></div><div class="stat-label">PHP Version</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold"><i class="fas fa-database"></i></div>
        <div class="stat-info"><div class="stat-value" style="font-size:.95rem;"><?= $dbSize ?></div><div class="stat-label">Database Size</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-table"></i></div>
        <div class="stat-info"><div class="stat-value"><?= $tblCount ?></div><div class="stat-label">DB Tables</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-bolt"></i></div>
        <div class="stat-info"><div class="stat-value"><?= $idxCount ?></div><div class="stat-label">DB Indexes</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold"><i class="fas fa-code-branch"></i></div>
        <div class="stat-info"><div class="stat-value" style="font-size:.95rem;">v3.0</div><div class="stat-label">System Version</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-calendar"></i></div>
        <div class="stat-info"><div class="stat-value" style="font-size:.95rem;"><?= date('Y') ?></div><div class="stat-label">Academic Year<div class="stat-sub">2025–2026</div></div></div>
    </div>
</div>

<!-- Developer Section -->
<div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-user-astronaut" style="color:var(--gold);margin-right:7px;"></i>System Developer</span></div>
    <div class="card-body" style="display:grid;grid-template-columns:auto 1fr;gap:24px;align-items:start;">
        <div style="text-align:center;">
            <div class="dev-avatar-default" style="width:80px;height:80px;font-size:1.4rem;">CD</div>
            <div style="font-family:var(--font-display);font-size:1rem;color:var(--white);margin-top:10px;">Cris Danoy</div>
            <div style="font-size:.72rem;color:var(--gold);">Full-Stack Developer</div>
        </div>
        <div>
            <p style="color:var(--muted);font-size:.84rem;line-height:1.72;margin-bottom:14px;">
                Designed and built the entire Nadelas Boarding House Online Booking & Management System —
                including database architecture, backend logic, frontend UI/UX, security implementation, and GCash integration.
            </p>
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
                <?php foreach ([
                    ['fa-phone',       '09633951825',          'tel:09633951825'],
                    ['fa-envelope',    'crisdanoy9@gmail.com', 'mailto:crisdanoy9@gmail.com'],
                    ['fab fa-facebook','cris.danoy.7',         'https://www.facebook.com/cris.danoy.7/'],
                ] as [$ico,$lbl,$href]): ?>
                <a href="<?= $href ?>" target="_blank" class="tech-badge">
                    <i class="<?= $ico ?>"></i> <?= $lbl ?>
                </a>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach (['PHP 8','PostgreSQL 15','HTML5','CSS3','JavaScript ES6','Chart.js','PDO','BCrypt','GCash API'] as $tech): ?>
                <span class="tech-badge"><i class="fas fa-check-circle"></i> <?= $tech ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

</main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
