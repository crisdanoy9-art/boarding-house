<?php
// Ensure helper function exists (fallback)
if (!function_exists('getProfilePicUrl')) {
    function getProfilePicUrl($image) {
        if (empty($image) || $image === 'default.png') {
            return '';
        }
        return APP_URL . '/uploads/profile/' . rawurlencode($image);
    }
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$profilePicUrl = getProfilePicUrl($currentUser['profile_image'] ?? '');
?>
<!-- Tenant Sidebar -->
<aside class="tenant-sidebar" id="tenantSidebar">
    <!-- Header -->
    <div class="sidebar-header">
        <div class="nbh-logo">NBH</div>
        <div class="sidebar-brand">
            <div class="sidebar-brand-name">Nadelas</div>
            <div class="sidebar-brand-sub">Boarding House</div>
        </div>
        <button class="sidebar-toggle" id="tenantSidebarToggle" aria-label="Collapse">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <!-- User info -->
    <div class="sidebar-user">
        <div class="user-avatar">
            <?php if($profilePicUrl): ?>
            <img src="<?= e($profilePicUrl) ?>" alt="" style="width:38px;height:38px;border-radius:50%;object-fit:cover;">
            <?php else: ?>
            <i class="fas fa-user"></i>
            <?php endif; ?>
        </div>
        <div class="user-info">
            <span class="user-name"><?= e($_SESSION['name'] ?? 'Tenant') ?></span>
            <span class="user-role">Tenant</span>
        </div>
    </div>

    <!-- Navigation links -->
    <ul class="sidebar-nav">
        <?php
        $navItems = [
            'dashboard'     => ['Dashboard', 'fa-home'],
            'book_room'     => ['Book Room', 'fa-bed'],
            'reservations'  => ['Reservations', 'fa-calendar-check'],
            'payments'      => ['Payments', 'fa-receipt'],
            'receipt'       => ['Receipts', 'fa-file-invoice'],
            'rules'         => ['Rules', 'fa-gavel'],
            'profile'       => ['Profile', 'fa-user'],
            'about'         => ['About', 'fa-info-circle']
        ];
        foreach ($navItems as $page => [$label, $icon]):
            $isActive = ($currentPage === $page) ? 'active' : '';
        ?>
        <li class="nav-item <?= $isActive ?>">
            <a href="<?= APP_URL ?>/user/<?= $page ?>.php" class="nav-link">
                <i class="fas <?= $icon ?>"></i>
                <span><?= $label ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- Logout link -->
    <div class="sidebar-footer">
        <a href="<?= APP_URL ?>/auth/logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<!-- Main wrapper for content -->
<div class="tenant-main-wrapper" id="tenantMainWrapper">
    <div class="tenant-content">