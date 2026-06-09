<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

$navItems = [
    ['file'=>'dashboard',    'icon'=>'tachometer-alt', 'label'=>'Dashboard',      'url'=>APP_URL.'/admin/dashboard.php'],
    ['file'=>'rooms',        'icon'=>'door-open',       'label'=>'Rooms',          'url'=>APP_URL.'/admin/rooms.php'],
    ['file'=>'reservations', 'icon'=>'calendar-check',  'label'=>'Reservations',   'url'=>APP_URL.'/admin/reservations.php'],
    ['file'=>'tenants',      'icon'=>'users',            'label'=>'Tenants',        'url'=>APP_URL.'/admin/tenants.php'],
    ['file'=>'payments',     'icon'=>'credit-card',      'label'=>'Payments',       'url'=>APP_URL.'/admin/payments.php'],
    ['file'=>'receipts',     'icon'=>'file-invoice',     'label'=>'Receipt Records','url'=>APP_URL.'/admin/receipts.php'],
    ['file'=>'reports',      'icon'=>'chart-bar',        'label'=>'Reports',        'url'=>APP_URL.'/admin/reports.php'],
    ['file'=>'announcements','icon'=>'bullhorn',          'label'=>'Announcements',  'url'=>APP_URL.'/admin/announcements.php'],
    ['file'=>'maintenance',  'icon'=>'tools',             'label'=>'Maintenance',    'url'=>APP_URL.'/admin/maintenance.php'],
    ['file'=>'about',        'icon'=>'info-circle',       'label'=>'About System',   'url'=>APP_URL.'/admin/about.php'],
];

$pendingCount = 0; $annCount = 0;
try {
    $pendingCount = (int)getDB()->query("SELECT COUNT(*) FROM bh.reservations WHERE status='pending'")->fetchColumn();
    $annCount     = (int)getDB()->query("SELECT COUNT(*) FROM bh.announcements WHERE is_active=TRUE AND (expires_at IS NULL OR expires_at>NOW())")->fetchColumn();
} catch(Exception $e){}
?>
<nav class="sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <div class="nbh-logo" style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--gold),var(--gold-dk));display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:.6rem;font-weight:900;color:#06060e;box-shadow:var(--shadow-gold);flex-shrink:0;">NBH</div>
        <div class="sidebar-brand" style="flex:1;min-width:0;">
            <span class="sidebar-brand-name" style="font-family:var(--font-display);font-size:.9rem;color:var(--gold);font-weight:700;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Nadelas BH</span>
            <span style="font-size:.62rem;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;">Admin Panel</span>
        </div>
        <!-- Sidebar toggle button removed from here -->
    </div>

    <div class="sidebar-user">
        <div class="user-avatar" style="background:linear-gradient(135deg,var(--gold),var(--gold-dk));">
            <?php
            $picUrl = getProfilePicUrl($currentUser['profile_image'] ?? '');
            if($picUrl): ?>
            <img src="<?= e($picUrl) ?>" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
            <?php else: ?><i class="fas fa-user-shield" style="color:#06060e;"></i><?php endif; ?>
        </div>
        <div class="user-info">
            <span class="user-name"><?= e($currentUser['name'] ?? 'Admin') ?></span>
            <span class="user-role" style="color:var(--gold);font-size:.65rem;text-transform:uppercase;letter-spacing:.07em;">Administrator</span>
        </div>
    </div>

    <ul class="sidebar-nav">
        <?php foreach($navItems as $item): ?>
        <li class="nav-item <?= ($currentPage===$item['file'])?'active':'' ?>">
            <a href="<?= $item['url'] ?>" class="nav-link">
                <i class="fas fa-<?= $item['icon'] ?>"></i>
                <span><?= $item['label'] ?></span>
                <?php if($item['file']==='reservations' && $pendingCount>0): ?>
                <span class="nav-badge"><?= $pendingCount ?></span>
                <?php endif; ?>
                <?php if($item['file']==='announcements' && $annCount>0): ?>
                <span class="nav-badge" style="background:var(--info);"><?= $annCount ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="sidebar-footer">
        <a href="<?= APP_URL ?>/auth/logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span>
        </a>
    </div>
</nav>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="main-wrapper" id="mainWrapper">
    <header class="topbar">
        <!-- Topbar toggle button removed from here -->
        <h1 class="page-title"><?= e($pageTitle ?? 'Dashboard') ?></h1>
        <div class="topbar-actions" style="margin-left:auto;display:flex;align-items:center;gap:13px;">
            <?php if($annCount>0): ?>
            <a href="<?= APP_URL ?>/admin/announcements.php" class="notif-btn" title="<?= $annCount ?> active announcement<?= $annCount>1?'s':'' ?>">
                <i class="fas fa-bullhorn"></i><span class="notif-badge"><?= $annCount ?></span>
            </a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/admin/reservations.php?status=pending" class="notif-btn" title="Pending reservations">
                <i class="fas fa-bell"></i>
                <?php if($pendingCount>0): ?><span class="notif-badge"><?= $pendingCount ?></span><?php endif; ?>
            </a>
            <a href="<?= APP_URL ?>/auth/logout.php" class="btn btn-sm btn-outline" style="border-color:rgba(255,255,255,.12);color:var(--muted);">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>
    <main class="main-content">