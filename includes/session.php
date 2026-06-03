<?php
require_once __DIR__ . '/../config/database.php';

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params(['lifetime'=>SESSION_LIFETIME,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Strict']);
        session_start();
    }
}
function isLoggedIn(): bool { startSecureSession(); return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']); }
function isAdmin(): bool    { return isLoggedIn() && ($_SESSION['role']??'') === 'admin'; }
function requireLogin(): void { if(!isLoggedIn()){header('Location: '.APP_URL.'/auth/login.php?redirect='.urlencode($_SERVER['REQUEST_URI']));exit();} }
function requireAdmin(): void { requireLogin(); if(!isAdmin()){header('Location: '.APP_URL.'/user/dashboard.php');exit();} }
function generateCSRF(): string { startSecureSession(); if(empty($_SESSION[CSRF_TOKEN_NAME]))$_SESSION[CSRF_TOKEN_NAME]=bin2hex(random_bytes(32)); return $_SESSION[CSRF_TOKEN_NAME]; }
function validateCSRF(string $token): bool { startSecureSession(); if(!isset($_SESSION[CSRF_TOKEN_NAME]))return false; $v=hash_equals($_SESSION[CSRF_TOKEN_NAME],$token); if($v)$_SESSION[CSRF_TOKEN_NAME]=bin2hex(random_bytes(32)); return $v; }
function csrfField(): string { return '<input type="hidden" name="'.CSRF_TOKEN_NAME.'" value="'.generateCSRF().'">'; }
function e(string $str): string { return htmlspecialchars($str,ENT_QUOTES|ENT_HTML5,'UTF-8'); }
function sanitizeInput(string $input): string { return trim(strip_tags($input)); }
function redirect(string $url, string $message='', string $type='success'): void { startSecureSession(); if($message)$_SESSION['flash']=['message'=>$message,'type'=>$type]; header('Location: '.$url); exit(); }
function getFlash(): array { startSecureSession(); $f=$_SESSION['flash']??[]; unset($_SESSION['flash']); return $f; }
function hashPassword(string $p): string { return password_hash($p,PASSWORD_BCRYPT,['cost'=>12]); }
function verifyPassword(string $p, string $h): bool { return password_verify($p,$h); }
function validateEmail(string $e): bool { return filter_var($e,FILTER_VALIDATE_EMAIL)!==false; }
function formatCurrency(float $a): string { return '₱'.number_format($a,2); }
function formatDate(string $d, string $f='M d, Y'): string { if(!$d||$d==='0000-00-00')return '—'; return date($f,strtotime($d)); }
function logActivity(string $action, string $details=''): void { error_log(sprintf('[%s] User %d: %s — %s',date('Y-m-d H:i:s'),$_SESSION['user_id']??0,$action,$details)); }
function paginate(int $total, int $perPage, int $currentPage): array { $tp=(int)ceil($total/max($perPage,1)); return ['total'=>$total,'per_page'=>$perPage,'current_page'=>$currentPage,'total_pages'=>$tp,'offset'=>($currentPage-1)*$perPage]; }

function getCurrentUser(): array {
    startSecureSession();
    if(!isLoggedIn())return [];
    try { $db=getDB(); $s=$db->prepare('SELECT id,name,email,phone,role,profile_image,address,created_at FROM bh.users WHERE id=? AND is_active=TRUE'); $s->execute([$_SESSION['user_id']]); return $s->fetch()?:[];}
    catch(PDOException $e){return [];}
}

function getProfilePicUrl(?string $filename): string {
    if($filename && $filename!=='default.png'){
        $path=__DIR__.'/../assets/images/profiles/'.$filename;
        if(file_exists($path))return APP_URL.'/assets/images/profiles/'.rawurlencode($filename);
    }
    return '';
}

function uploadProfilePic(array $file): string|false {
    $allowed=['image/jpeg','image/png','image/gif','image/webp'];
    if(!in_array($file['type'],$allowed)||$file['size']>3*1024*1024)return false;
    $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
    $name='profile_'.session_id().'_'.time().'.'.$ext;
    $dest=__DIR__.'/../assets/images/profiles/'.$name;
    if(!move_uploaded_file($file['tmp_name'],$dest))return false;
    return $name;
}

startSecureSession();
