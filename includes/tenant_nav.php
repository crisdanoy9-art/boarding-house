<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Fetch active announcements
$activeAnnouncements = [];
try {
    $dbNav = getDB();
    $annStmt = $dbNav->prepare("
        SELECT * FROM bh.announcements
        WHERE is_active=TRUE AND audience IN ('all','tenants')
          AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY CASE type WHEN 'danger' THEN 0 WHEN 'warning' THEN 1 WHEN 'gold' THEN 2 ELSE 3 END, created_at DESC
        LIMIT 3
    ");
    $annStmt->execute();
    $activeAnnouncements = $annStmt->fetchAll();
} catch(Exception $e) {}

$profilePicUrl = getProfilePicUrl($currentUser['profile_image'] ?? '');
?>
<!-- Tenant Sidebar -->
<aside class="tenant-sidebar" id="tenantSidebar">
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
        <li class="nav-item">
            <a href="<?= APP_URL ?>/auth/logout.php" class="logout-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</aside>

<!-- Main wrapper for content + announcements -->
<div class="tenant-main-wrapper" id="tenantMainWrapper">
    <!-- Announcement banners (inside main wrapper) -->
    <?php if(!empty($activeAnnouncements)): ?>
    <div class="announcements-container">
    <?php
    $typeMap=['info'=>['ann-strip-info','fa-info-circle','rgba(56,189,248,.15)','var(--info)'],'warning'=>['ann-strip-warning','fa-exclamation-triangle','rgba(240,168,50,.15)','var(--warning)'],'danger'=>['ann-strip-danger','fa-exclamation-circle','rgba(240,82,82,.15)','var(--danger)'],'success'=>['ann-strip-success','fa-check-circle','rgba(62,207,110,.15)','var(--success)'],'gold'=>['ann-strip-gold','fa-star','rgba(201,168,76,.15)','var(--gold)']];
    foreach($activeAnnouncements as $ann):
        [$sCls,$defIcon,$iconBg,$iconClr]=$typeMap[$ann['type']]??$typeMap['info'];
        $faIcon=$ann['icon']?'fa-'.e($ann['icon']):$defIcon;
    ?>
    <div class="ann-strip <?= $sCls ?>" id="ann-<?= $ann['id'] ?>">
        <div class="ann-strip-icon" style="background:<?= $iconBg ?>;color:<?= $iconClr ?>;"><i class="fas <?= $faIcon ?>"></i></div>
        <div style="flex:1;min-width:0;">
            <div class="ann-strip-title"><?= e($ann['title']) ?></div>
            <div class="ann-strip-body"><?= e($ann['body']) ?></div>
        </div>
        <button class="ann-dismiss" onclick="dismissAnn(<?= $ann['id'] ?>)">×</button>
    </div>
    <?php endforeach; ?>
    </div>
    <script>function dismissAnn(id){const el=document.getElementById('ann-'+id);if(el){el.style.opacity='0';el.style.transform='translateY(-8px)';setTimeout(()=>el.remove(),300);}}</script>
    <?php endif; ?>

    <!-- Dynamic tenant content will be inserted here -->
    <div class="tenant-content">