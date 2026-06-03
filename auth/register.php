<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/session.php';
if(isLoggedIn()){header('Location: '.APP_URL.'/user/dashboard.php');exit();}
$errors=[];$data=['name'=>'','email'=>'','phone'=>''];
if($_SERVER['REQUEST_METHOD']==='POST'){
if(!validateCSRF($_POST[CSRF_TOKEN_NAME]??'')){$errors[]='Invalid request.';}
else{$data['name']=sanitizeInput($_POST['name']??'');$data['email']=sanitizeInput($_POST['email']??'');$data['phone']=sanitizeInput($_POST['phone']??'');$password=$_POST['password']??'';$passwordConfirm=$_POST['password_confirm']??'';
if(!$data['name']||!$data['email']||!$password){$errors[]='Please fill in all required fields.';}
if($data['name']&&strlen($data['name'])<2){$errors[]='Name must be at least 2 characters.';}
if($data['email']&&!validateEmail($data['email'])){$errors[]='Invalid email address.';}
if($password&&strlen($password)<8){$errors[]='Password must be at least 8 characters.';}
if($password!==$passwordConfirm){$errors[]='Passwords do not match.';}
if(empty($errors)){try{$db=getDB();$s=$db->prepare('SELECT id FROM bh.users WHERE email=?');$s->execute([$data['email']]);
if($s->fetch()){$errors[]='Email already exists.';}
else{$db->prepare('INSERT INTO bh.users(name,email,password,phone,role) VALUES(?,?,?,?,?)')->execute([$data['name'],$data['email'],hashPassword($password),$data['phone'],'tenant']);redirect(APP_URL.'/auth/login.php','Account created! Please sign in.','success');}}
catch(PDOException $e){$errors[]='Server error.';error_log($e->getMessage());}}}
}
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Register — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css"></head><body>
<div class="auth-page">
<div class="auth-visual admin-mode"><div class="auth-visual-grid"></div><div class="auth-visual-content"><div class="auth-visual-logo"><i class="fas fa-building"></i></div><h1><?= APP_NAME ?></h1><p>Join our community. Find your perfect bed. Simple booking, comfortable living.</p><div class="auth-stats"><div class="auth-stat"><div class="auth-stat-num">₱1,300</div><div class="auth-stat-label">Per Month</div></div><div class="auth-stat"><div class="auth-stat-num">72</div><div class="auth-stat-label">Beds</div></div></div></div></div>
<div class="auth-form-side"><div class="auth-card" style="max-width:460px;">
<div class="auth-card-header"><h2>Create Account</h2><p>Register as a tenant to start booking</p></div>
<?php if(!empty($errors)): ?><div class="flash flash-error" style="margin-bottom:18px;"><i class="fas fa-times-circle"></i><div><?php foreach($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div></div><?php endif; ?>
<form method="POST" data-validate>
<?= csrfField() ?>
<div class="form-group"><label class="form-label">Full Name *</label><div class="input-group"><i class="fas fa-user input-icon"></i><input type="text" name="name" class="form-control" value="<?= e($data['name']) ?>" placeholder="Juan Dela Cruz" required minlength="2"></div></div>
<div class="form-group"><label class="form-label">Email Address *</label><div class="input-group"><i class="fas fa-envelope input-icon"></i><input type="email" name="email" class="form-control" value="<?= e($data['email']) ?>" placeholder="your@email.com" required></div></div>
<div class="form-group"><label class="form-label">Phone Number</label><div class="input-group"><i class="fas fa-phone input-icon"></i><input type="text" name="phone" class="form-control" value="<?= e($data['phone']) ?>" placeholder="09171234567"></div></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
<div class="form-group"><label class="form-label">Password *</label><div class="input-group"><i class="fas fa-lock input-icon"></i><input type="password" id="pw" name="password" class="form-control" placeholder="Min. 8 chars" required minlength="8"></div></div>
<div class="form-group"><label class="form-label">Confirm *</label><div class="input-group"><i class="fas fa-lock input-icon"></i><input type="password" name="password_confirm" class="form-control" placeholder="Repeat" required data-match="pw"></div></div>
</div>
<button type="submit" class="btn btn-primary btn-full" style="margin-top:6px;"><i class="fas fa-user-plus"></i> Create Account</button>
</form>
<hr class="divider">
<p class="text-center text-muted" style="font-size:.85rem;">Have an account? <a href="<?= APP_URL ?>/auth/login.php" class="text-gold">Sign in</a></p>
</div></div></div>
<script src="<?= APP_URL ?>/assets/js/main.js"></script></body></html>
