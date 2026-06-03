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
<nav class="tenant-nav" id="tenantNav">
    <!-- LEFT: Logo -->
    <a href="<?= APP_URL ?>/user/dashboard.php"
       style="display:flex;align-items:center;gap:10px;text-decoration:none;flex-shrink:0;margin-right:4px;">
        <div class="nbh-logo-mark">NBH</div>
        <div>
            <div class="nav-brand-name">Nadelas</div>
            <div class="nav-brand-tag">Boarding House</div>
        </div>
    </a>

    <button class="nav-toggle" id="navToggle" aria-label="Menu"><i class="fas fa-bars"></i></button>

    <!-- RIGHT: Links -->
    <ul class="nav-links" id="navLinks">
        <li><a href="<?= APP_URL ?>/user/dashboard.php"    class="<?= $currentPage==='dashboard'   ?'active':'' ?>"><i class="fas fa-home"></i> Home</a></li>
        <li><a href="<?= APP_URL ?>/user/book_room.php"    class="<?= $currentPage==='book_room'    ?'active':'' ?>"><i class="fas fa-bed"></i> Book Room</a></li>
        <li><a href="<?= APP_URL ?>/user/reservations.php" class="<?= $currentPage==='reservations' ?'active':'' ?>"><i class="fas fa-calendar-check"></i> Reservations</a></li>
        <li><a href="<?= APP_URL ?>/user/payments.php"     class="<?= $currentPage==='payments'     ?'active':'' ?>"><i class="fas fa-receipt"></i> Payments</a></li>
        <li><a href="<?= APP_URL ?>/user/receipt.php"      class="<?= $currentPage==='receipt'      ?'active':'' ?>"><i class="fas fa-file-invoice"></i> Receipts</a></li>
        <li><a href="<?= APP_URL ?>/user/rules.php"        class="<?= $currentPage==='rules'        ?'active':'' ?>"><i class="fas fa-gavel"></i> Rules</a></li>
        <li><a href="<?= APP_URL ?>/user/profile.php"      class="<?= $currentPage==='profile'      ?'active':'' ?>">
            <?php if($profilePicUrl): ?>
            <img src="<?= e($profilePicUrl) ?>" alt="" style="width:20px;height:20px;border-radius:50%;object-fit:cover;border:1.5px solid var(--gold);">
            <?php else: ?><i class="fas fa-user"></i><?php endif; ?>
            Profile</a></li>
        <li><a href="<?= APP_URL ?>/user/about.php"        class="<?= $currentPage==='about'        ?'active':'' ?>"><i class="fas fa-info-circle"></i> About</a></li>
        <li><a href="<?= APP_URL ?>/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</nav>

<!-- Announcement banners -->
<?php if(!empty($activeAnnouncements)): ?>
<div style="padding:10px 18px 0;max-width:1240px;margin:0 auto;">
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
<script>function dismissAnn(id){const el=document.getElementById('ann-'+id);if(el){el.style.cssText+='opacity:0;transform:translateY(-8px);transition:.3s ease';setTimeout(()=>el.remove(),320);}}</script>
<?php endif; ?>

<div class="tenant-content">
