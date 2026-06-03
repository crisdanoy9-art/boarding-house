<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
if(isLoggedIn()){header('Location: '.(isAdmin()?APP_URL.'/admin/dashboard.php':APP_URL.'/user/dashboard.php'));exit();}
$errors=[];$email='';
$roleSelect=sanitizeInput($_POST['role']??$_GET['role']??'tenant');
if(!in_array($roleSelect,['admin','tenant']))$roleSelect='tenant';
if($_SERVER['REQUEST_METHOD']==='POST'){
if(!validateCSRF($_POST[CSRF_TOKEN_NAME]??'')){$errors[]='Invalid request.';}
else{
$email=sanitizeInput($_POST['email']??'');
$password=$_POST['password']??'';
$roleSelect=sanitizeInput($_POST['role']??'tenant');
if(!in_array($roleSelect,['admin','tenant']))$roleSelect='tenant';
if(!$email||!$password){$errors[]='Please fill in all fields.';}
elseif(!validateEmail($email)){$errors[]='Invalid email.';}
else{try{$db=getDB();$stmt=$db->prepare('SELECT id,name,email,password,role,is_active FROM bh.users WHERE email=? AND role=?');$stmt->execute([$email,$roleSelect]);$user=$stmt->fetch();
if(!$user||!verifyPassword($password,$user['password'])){$errors[]='Invalid credentials or wrong role selected.';}
elseif(!$user['is_active']){$errors[]='Account deactivated. Contact admin.';}
else{session_regenerate_id(true);$_SESSION['user_id']=$user['id'];$_SESSION['name']=$user['name'];$_SESSION['email']=$user['email'];$_SESSION['role']=$user['role'];
logActivity('LOGIN','Logged in as '.$user['role']);
$redirect=$_GET['redirect']??'';
if($user['role']==='admin'){redirect($redirect?:APP_URL.'/admin/dashboard.php','Welcome back, '.$user['name'].'!');}
else{redirect($redirect?:APP_URL.'/user/dashboard.php','Welcome back, '.$user['name'].'!');}}}
catch(PDOException $e){$errors[]='Server error.';error_log($e->getMessage());}}}}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Sign In — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<style>
.role-toggle{display:grid;grid-template-columns:1fr 1fr;background:var(--bg);border:1px solid var(--border);border-radius:var(--r-lg);padding:5px;margin-bottom:24px;}
.role-toggle input{display:none;}
.role-toggle label{display:flex;align-items:center;justify-content:center;gap:7px;padding:11px;border-radius:var(--r-md);font-size:.88rem;font-weight:600;color:var(--muted);cursor:pointer;transition:var(--t);}
.role-toggle label:hover{color:var(--text);background:rgba(255,255,255,.04);}
#role_admin:checked+label{background:linear-gradient(135deg,var(--gold),var(--gold-dk));color:#06060e;box-shadow:0 2px 12px rgba(201,168,76,.25);}
#role_tenant:checked+label{background:linear-gradient(135deg,#4caf80,#2d8050);color:#fff;box-shadow:0 2px 12px rgba(76,175,128,.25);}
.btn-role{width:100%;padding:13px;border:none;border-radius:var(--r-md);font-family:var(--font-body);font-size:.95rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:8px;transition:var(--t);}
.is-admin .btn-role{background:linear-gradient(135deg,var(--gold),var(--gold-dk));color:#06060e;}
.is-tenant .btn-role{background:linear-gradient(135deg,#4caf80,#2d8050);color:#fff;}
</style>
</head><body>
<div class="auth-page">
<div class="auth-visual <?= $roleSelect==='admin'?'admin-mode':'tenant-mode' ?>" id="authVisual">
<div class="auth-visual-grid"></div>
<div class="auth-visual-content">
<div class="auth-visual-logo"><i class="fas fa-<?= $roleSelect==='admin'?'user-shield':'building' ?>" id="visIcon"></i></div>
<h1 id="visTitle"><?= APP_NAME ?></h1>
<p id="visDesc"><?= $roleSelect==='admin'?'Administrator portal. Manage rooms, tenants, payments, and reports.':'Your premium boarding house. Safe, comfortable, always home.' ?></p>
<div class="auth-stats"><div class="auth-stat"><div class="auth-stat-num">3</div><div class="auth-stat-label">Floors</div></div><div class="auth-stat"><div class="auth-stat-num">18</div><div class="auth-stat-label">Rooms</div></div><div class="auth-stat"><div class="auth-stat-num">72</div><div class="auth-stat-label">Beds</div></div></div>
</div></div>
<div class="auth-form-side"><div class="auth-card">
<div style="display:inline-flex;align-items:center;gap:6px;padding:5px 14px;border-radius:99px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:14px;background:rgba(201,168,76,.1);border:1px solid rgba(201,168,76,.25);color:var(--gold);" id="rolePill"><i class="fas fa-<?= $roleSelect==='admin'?'user-shield':'user' ?>" id="pillIcon"></i><span id="pillText"><?= $roleSelect==='admin'?'Admin Login':'Tenant Login' ?></span></div>
<div class="auth-card-header"><h2>Welcome Back</h2><p>Select your role, then sign in</p></div>
<div class="role-toggle"><input type="radio" name="ui" id="role_admin" <?= $roleSelect==='admin'?'checked':'' ?>><label for="role_admin"><i class="fas fa-user-shield"></i> Administrator</label><input type="radio" name="ui" id="role_tenant" <?= $roleSelect==='tenant'?'checked':'' ?>><label for="role_tenant"><i class="fas fa-user"></i> Tenant</label></div>
<?php if(!empty($errors)): ?><div class="flash flash-error" style="margin-bottom:18px;"><i class="fas fa-times-circle"></i><div><?php foreach($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div></div><?php endif; ?>
<form method="POST" data-validate id="loginForm" class="login-form is-<?= $roleSelect ?>">
<?= csrfField() ?>
<input type="hidden" name="role" id="hiddenRole" value="<?= e($roleSelect) ?>">
<div class="form-group"><label class="form-label">Email Address</label><div class="input-group"><i class="fas fa-envelope input-icon"></i><input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" placeholder="your@email.com" required autocomplete="email"></div></div>
<div class="form-group"><label class="form-label">Password</label><div class="input-group"><i class="fas fa-lock input-icon"></i><input type="password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password"></div></div>
<button type="submit" class="btn-role" id="submitBtn"><i class="fas fa-sign-in-alt" id="submitIcon"></i><span id="submitTxt">Sign In as <?= $roleSelect==='admin'?'Administrator':'Tenant' ?></span></button>
</form>
<hr class="divider">
<p class="text-center text-muted" style="font-size:.85rem;">No account? <a href="<?= APP_URL ?>/auth/register.php" class="text-gold">Register here</a></p>
<p class="text-center" style="margin-top:10px;"><a href="<?= APP_URL ?>/index.php" class="text-muted" style="font-size:.8rem;"><i class="fas fa-arrow-left"></i> Back to Home</a></p>
<div style="margin-top:22px;padding:16px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-md);">
<p style="font-size:.72rem;color:var(--muted);text-align:center;margin-bottom:10px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;">Demo Credentials</p>
<div id="demoAdmin" style="<?= $roleSelect!=='admin'?'display:none;':'' ?>text-align:center;font-size:.8rem;"><div style="color:var(--gold);font-weight:700;">Admin</div><div style="color:var(--muted);">admin@boardinghouse.com</div><div style="color:var(--muted);">Password: <strong>password</strong></div><button onclick="fillDemo('admin@boardinghouse.com')" style="margin-top:8px;background:rgba(201,168,76,.1);border:1px solid rgba(201,168,76,.3);color:var(--gold);padding:5px 14px;border-radius:var(--r-md);font-size:.76rem;cursor:pointer;"><i class="fas fa-magic"></i> Auto-fill</button></div>
<div id="demoTenant" style="<?= $roleSelect!=='tenant'?'display:none;':'' ?>text-align:center;font-size:.8rem;"><div style="color:var(--success);font-weight:700;">Tenant</div><div style="color:var(--muted);">juan@email.com</div><div style="color:var(--muted);">Password: <strong>password</strong></div><button onclick="fillDemo('juan@email.com')" style="margin-top:8px;background:rgba(62,207,110,.1);border:1px solid rgba(62,207,110,.3);color:var(--success);padding:5px 14px;border-radius:var(--r-md);font-size:.76rem;cursor:pointer;"><i class="fas fa-magic"></i> Auto-fill</button></div>
</div>
</div></div></div>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script>
const A=['Administrator','admin-mode','user-shield','Admin Login','Sign In as Administrator','admin@boardinghouse.com'];
const T=['Tenant','tenant-mode','user','Tenant Login','Sign In as Tenant','juan@email.com'];
function sw(role){const[rt,vc,pi,pt,st,de]=role==='admin'?A:T;document.getElementById('hiddenRole').value=role;document.getElementById('loginForm').className='login-form is-'+role;document.getElementById('authVisual').className='auth-visual '+vc;document.getElementById('pillIcon').className='fas fa-'+pi;document.getElementById('pillText').textContent=pt;document.getElementById('submitIcon').className='fas fa-'+(role==='admin'?'user-shield':'sign-in-alt');document.getElementById('submitTxt').textContent=st;document.getElementById('demoAdmin').style.display=role==='admin'?'':'none';document.getElementById('demoTenant').style.display=role==='tenant'?'':'none';document.getElementById('email').value='';document.querySelector('input[name=password]').value='';}
document.getElementById('role_admin').addEventListener('change',()=>sw('admin'));
document.getElementById('role_tenant').addEventListener('change',()=>sw('tenant'));
function fillDemo(e){document.getElementById('email').value=e;document.querySelector('input[name=password]').value='password';}
</script></body></html>
