<?php
require_once __DIR__ . '/session.php';
$currentUser = isLoggedIn() ? getCurrentUser() : [];
$flash       = getFlash();
$pageTitle   = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <?php if(isset($extraCSS)) echo $extraCSS; ?>
</head>
<body class="<?= isAdmin() ? 'admin-body' : 'tenant-body' ?>">

<?php if(!empty($flash)): ?>
<div class="flash-container" id="flashMessage">
    <div class="flash flash-<?= e($flash['type']) ?>">
        <i class="fas fa-<?= $flash['type']==='success'?'check-circle':($flash['type']==='error'?'times-circle':'info-circle') ?>"></i>
        <?= e($flash['message']) ?>
        <button onclick="this.parentElement.parentElement.remove()" class="flash-close">&times;</button>
    </div>
</div>
<?php endif; ?>
