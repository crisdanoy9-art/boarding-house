<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();

$pageTitle = 'My Profile';
$db  = getDB();
$uid = $_SESSION['user_id'];
$errors = [];

$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $name  = sanitizeInput($_POST['name']  ?? '');
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $addr  = sanitizeInput($_POST['address'] ?? '');

            if (!$name) { $errors[] = 'Name is required.'; }
            else {
                $db->prepare("UPDATE bh.users SET name=?, phone=?, address=?, updated_at=NOW() WHERE id=?")
                   ->execute([$name, $phone, $addr, $uid]);
                $_SESSION['name'] = $name;
                redirect(APP_URL . '/user/profile.php', 'Profile updated successfully!');
            }

        } elseif ($action === 'change_password') {
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (!$current || !$new) { $errors[] = 'All password fields are required.'; }
            elseif (strlen($new) < 8) { $errors[] = 'New password must be at least 8 characters.'; }
            elseif ($new !== $confirm) { $errors[] = 'New passwords do not match.'; }
            else {
                $stmt = $db->prepare("SELECT password FROM bh.users WHERE id=?");
                $stmt->execute([$uid]);
                $row = $stmt->fetch();
                if (!verifyPassword($current, $row['password'])) {
                    $errors[] = 'Current password is incorrect.';
                } else {
                    $db->prepare("UPDATE bh.users SET password=? WHERE id=?")
                       ->execute([hashPassword($new), $uid]);
                    redirect(APP_URL . '/user/profile.php', 'Password changed successfully!');
                }
            }
        }
    }
    $user = getCurrentUser(); // Refresh
}

// Get tenancy info (via beds)
$tenancy = $db->prepare("
    SELECT t.*, r.room_number, f.floor_number, b.bed_number, r.price
    FROM bh.tenants t
    JOIN bh.beds b ON b.id = t.bed_id
    JOIN bh.rooms r ON r.id = b.room_id
    JOIN bh.floors f ON f.id = r.floor_id
    WHERE t.user_id = ? AND t.status = 'active'
    LIMIT 1
");
$tenancy->execute([$uid]);
$tenancy = $tenancy->fetch();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/tenant_nav.php'; ?>

<?php foreach ($errors as $err): ?>
<div class="flash flash-error" style="margin-bottom:16px;"><i class="fas fa-times-circle"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:24px;align-items:start;">
    <!-- Profile Card -->
    <div class="card">
        <div class="card-body" style="text-align:center;padding:36px 24px;">
            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--clr-gold),var(--clr-gold-dk));
                        display:flex;align-items:center;justify-content:center;
                        font-size:2rem;color:#0f0f17;margin:0 auto 20px;">
                <i class="fas fa-user"></i>
            </div>
            <h2 style="font-family:var(--font-display);color:var(--clr-white);margin-bottom:4px;"><?= e($user['name']) ?></h2>
            <p class="text-muted" style="font-size:0.88rem;"><?= e($user['email']) ?></p>
            <span class="badge badge-gold" style="margin-top:12px;">Tenant</span>

            <?php if ($tenancy): ?>
            <div style="margin-top:24px;padding:16px;background:rgba(201,168,76,0.08);border:1px solid var(--clr-border);border-radius:var(--radius-md);">
                <div class="form-label" style="margin-bottom:8px;">Current Room</div>
                <div style="font-family:var(--font-display);font-size:1.4rem;color:var(--clr-gold);">
                    Rm <?= e($tenancy['room_number']) ?>
                </div>
                <div class="text-muted" style="font-size:0.82rem;">
                    Floor <?= $tenancy['floor_number'] ?> — Bed <?= $tenancy['bed_number'] ?>
                </div>
                <div style="color:var(--clr-gold);font-weight:600;margin-top:6px;">
                    <?= formatCurrency($tenancy['price']) ?>/month
                </div>
            </div>
            <?php endif; ?>

            <div style="margin-top:16px;text-align:left;">
                <div class="form-label">Member Since</div>
                <div class="text-muted"><?= formatDate($user['created_at'] ?? date('Y-m-d')) ?></div>
            </div>
        </div>
    </div>

    <div style="display:grid;gap:24px;">
        <!-- Update Profile -->
        <div class="card">
            <div class="card-header"><span class="card-title">Personal Information</span></div>
            <div class="card-body">
                <form method="POST" data-validate>
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <div class="input-group">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" name="name" class="form-control"
                                       value="<?= e($user['name']) ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <div class="input-group">
                                <i class="fas fa-phone input-icon"></i>
                                <input type="text" name="phone" class="form-control"
                                       value="<?= e($user['phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled
                                   style="opacity:0.5;">
                        </div>
                        <div style="font-size:0.78rem;color:var(--clr-muted);margin-top:4px;">
                            Contact admin to change your email address.
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Home Address</label>
                        <textarea name="address" class="form-control" rows="3"
                                  placeholder="Your home address..."><?= e($user['address'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-header"><span class="card-title">Change Password</span></div>
            <div class="card-body">
                <form method="POST" data-validate>
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label class="form-label">Current Password *</label>
                        <div class="input-group">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="current_password" class="form-control"
                                   placeholder="Your current password" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">New Password *</label>
                            <div class="input-group">
                                <i class="fas fa-key input-icon"></i>
                                <input type="password" id="newPass" name="new_password" class="form-control"
                                       placeholder="Min 8 characters" required minlength="8">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm New Password *</label>
                            <div class="input-group">
                                <i class="fas fa-key input-icon"></i>
                                <input type="password" name="confirm_password" class="form-control"
                                       placeholder="Repeat new password" required data-match="newPass">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline">
                        <i class="fas fa-lock"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>