<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/session.php';

if (isLoggedIn()) {
    header('Location: '.(isAdmin() ? APP_URL.'/admin/dashboard.php' : APP_URL.'/user/dashboard.php'));
    exit();
}
try {
    $db    = getDB();
    $stats = $db->query("SELECT COUNT(*) AS total_rooms, COUNT(CASE WHEN status='available' THEN 1 END) AS available_rooms, (SELECT COUNT(*) FROM bh.beds WHERE status='available') AS available_beds FROM bh.rooms")->fetch();
} catch (Exception $e) {
    $stats = ['total_rooms'=>18,'available_rooms'=>16,'available_beds'=>60];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nadelas Boarding House — DAPITAN CITY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>

<?php $flash=getFlash(); if(!empty($flash)): ?>
<div class="flash-container" id="flashMessage">
    <div class="flash flash-<?= e($flash['type']) ?>">
        <i class="fas fa-<?= $flash['type']==='success'?'check-circle':'info-circle' ?>"></i>
        <?= e($flash['message']) ?>
        <button onclick="this.parentElement.parentElement.remove()" class="flash-close">&times;</button>
    </div>
</div>
<?php endif; ?>

<!-- ── Navbar ── -->
<nav class="public-nav">
    <div class="public-nav-logo" style="display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--gold),var(--gold-dk));display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:.62rem;font-weight:900;color:#05050d;flex-shrink:0;">NBH</div>
        <span>Nadelas Boarding House</span>
    </div>
    <ul class="public-nav-links">
        <li><a href="#features">Features</a></li>
        <li><a href="#rules">Rules</a></li>
        <li><a href="#developer">About</a></li>
        <li><a href="<?= APP_URL ?>/auth/login.php">Login</a></li>
    </ul>
    <div class="public-nav-actions">
        <a href="<?= APP_URL ?>/auth/login.php"    class="btn btn-outline btn-sm">Sign In</a>
        <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-primary btn-sm">Sign Up</a>
    </div>
</nav>

<!-- ── Hero ── -->
<section class="hero">
    <div class="hero-grid"></div>
    <div class="hero-content">
        <div class="hero-eyebrow"><i class="fas fa-star"></i> Nadelas Boarding House · DAPITAN CITY</div>
        <h1>Your <span class="accent">Perfect Room</span><br>Awaits You</h1>
        <p>Modern, affordable boarding rooms with secure online booking. Live well, pay smart.</p>
        <div class="hero-cta">
            <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-primary btn-lg"><i class="fas fa-bed"></i> Book a Room</a>
        </div>
    </div>
</section>

<!-- ── Stats Bar ── -->
<section style="background:var(--bg2);border-top:1px solid var(--border);border-bottom:1px solid var(--border);padding:32px 40px;">
    <div style="max-width:780px;margin:0 auto;display:grid;grid-template-columns:repeat(3,1fr);gap:28px;text-align:center;">
        <div><div style="font-family:var(--font-display);font-size:2.4rem;color:var(--gold);"><?= $stats['total_rooms'] ?></div><div style="color:var(--muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.1em;">Total Rooms</div></div>
        <div><div style="font-family:var(--font-display);font-size:2.4rem;color:var(--success);"><?= $stats['available_rooms'] ?></div><div style="color:var(--muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.1em;">Available Now</div></div>
        <div><div style="font-family:var(--font-display);font-size:2.4rem;color:var(--gold);">₱1,300</div><div style="color:var(--muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.1em;">Per Month / Bed</div></div>
    </div>
</section>

<!-- ── Features ── -->
<section class="features-section" id="features">
    <h2 class="section-title">Why Choose Nadelas?</h2>
    <p class="section-sub">Everything you need for comfortable, affordable boarding life</p>
    <div class="features-grid">
        <?php foreach ([
            ['fa-wifi',         'High-Speed WiFi',    'Stay connected with reliable internet in every room.'],
            ['fa-shield-alt',   '24/7 Security',      'Round-the-clock security for your peace of mind.'],
            ['fa-calendar-check','Easy Online Booking','Reserve your bed online in minutes — no paperwork.'],
            ['fa-water',        'Water Included',     'Utilities included in your monthly ₱1,300 rate.'],
            ['fa-map-marker-alt','Prime Location',    'Near schools, markets, and transport in CDO.'],
            ['fa-mobile-alt',   'GCash Payments',     'Pay your deposit and rent cashlessly via GCash.'],
        ] as [$ico,$title,$desc]): ?>
        <div class="feature-card">
            <div class="feature-icon"><i class="fas <?= $ico ?>"></i></div>
            <h3><?= $title ?></h3>
            <p><?= $desc ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ── House Rules ── -->
<section id="rules" style="background:var(--bg2);border-top:1px solid var(--border);border-bottom:1px solid var(--border);padding:70px 40px;">
    <div style="max-width:1100px;margin:0 auto;">
        <div style="text-align:center;margin-bottom:40px;">
            <span style="display:inline-flex;align-items:center;gap:7px;padding:5px 16px;border-radius:99px;background:rgba(201,168,76,.1);border:1px solid rgba(201,168,76,.25);font-size:.72rem;font-weight:700;color:var(--gold);text-transform:uppercase;letter-spacing:.1em;margin-bottom:12px;"><i class="fas fa-gavel"></i> House Policies</span>
            <h2 class="section-title">Rules & Regulations</h2>
            <p class="section-sub">All tenants must follow these rules. Violations may result in eviction.</p>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:18px;margin-bottom:24px;">
        <?php foreach ([
            ['01','Payment Policy','peso-sign',['Monthly rent of ₱1,300 due on the 1st of every month','₱1,300 advance deposit required upon approval','Payments via Cash or GCash (09633951825)','3 months unpaid = automatic eviction','Late penalties apply after the 5th of the month']],
            ['02','Curfew & Visitors','clock',['Curfew strictly at 10:00 PM nightly','Visitors allowed until 8:00 PM only','Overnight visitors are strictly prohibited','Visitors must sign in at the entrance','Tenants responsible for visitor conduct']],
            ['03','Cleanliness & Noise','broom',['Keep your room and shared areas clean','Garbage in designated bins only','No loud music after 9:00 PM','Shared bathrooms must be left clean','No food inside rooms (pest prevention)']],
            ['04','Prohibited Items','ban',['Smoking & vaping strictly prohibited inside','No alcohol consumption in rooms','Illegal drugs and weapons absolutely banned','No pets allowed','No cooking appliances in rooms']],
            ['05','Security & Property','shield-alt',['Tenants responsible for own valuables','Do not share keys with outsiders','Report damage or issues immediately','Willful damage charged to tenant','Always lock your room when leaving']],
            ['06','Check-Out Policy','door-open',['15-day advance notice required','Room must be left clean','Settle all balances before leaving','Return keys to admin upon departure','Advance deposit is non-refundable']],
        ] as [$num,$title,$icon,$items]): ?>
        <div class="rule-card-tenant">
            <div class="rule-card-header">
                <div class="rule-num"><?= $num ?></div>
                <div><div style="font-family:var(--font-display);color:var(--white);font-size:.95rem;"><?= $title ?></div></div>
            </div>
            <ul class="rule-list">
                <?php foreach ($items as $item): ?>
                <li><i class="fas fa-diamond"></i><?= $item ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
        </div>
        <div style="background:rgba(240,82,82,.06);border:1px solid rgba(240,82,82,.18);border-radius:var(--r-lg);padding:14px 20px;text-align:center;">
            <i class="fas fa-exclamation-triangle" style="color:var(--danger);margin-right:7px;"></i>
            <span style="font-size:.84rem;color:var(--muted);">Violation of rules may result in <strong style="color:var(--danger);">immediate eviction</strong> without refund. All tenants sign an agreement upon move-in.</span>
        </div>
    </div>
</section>

<!-- ── CTA ── -->
<section style="background:linear-gradient(135deg,rgba(201,168,76,.1) 0%,transparent 60%);border-top:1px solid var(--border);padding:64px 40px;text-align:center;">
    <h2 style="font-family:var(--font-display);font-size:1.9rem;color:var(--white);margin-bottom:12px;">Ready to Move In?</h2>
    <p style="color:var(--muted);max-width:400px;margin:0 auto 28px;font-size:.88rem;">Create your account, choose your bed, and start your comfortable stay with us.</p>
    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;">
        <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-primary btn-lg"><i class="fas fa-user-plus"></i> Create Account</a>
        <a href="<?= APP_URL ?>/auth/login.php"    class="btn btn-outline btn-lg"><i class="fas fa-sign-in-alt"></i> Sign In</a>
    </div>
</section>

<!-- ── Developer Section ── -->
<section id="developer" class="dev-section">
    <div style="max-width:900px;margin:0 auto;text-align:center;">
        <span style="display:inline-flex;align-items:center;gap:7px;padding:5px 16px;border-radius:99px;background:rgba(201,168,76,.1);border:1px solid rgba(201,168,76,.25);font-size:.72rem;font-weight:700;color:var(--gold);text-transform:uppercase;letter-spacing:.1em;margin-bottom:16px;"><i class="fas fa-laptop-code"></i> System Developer</span>
        <h2 class="section-title" style="margin-bottom:8px;">Built with ♥ by Cris Danoy</h2>
        <p style="color:var(--muted);font-size:.88rem;max-width:520px;margin:0 auto 36px;line-height:1.75;">
            Full-stack developer who designed and built the entire Nadelas Boarding House Online Booking & Management System — from database to UI.
        </p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;margin-bottom:32px;">
            <div class="dev-card">
                <div class="dev-avatar-default" style="width:72px;height:72px;font-size:1.3rem;">CD</div>
                <div style="font-family:var(--font-display);font-size:1.1rem;color:var(--white);margin-bottom:4px;">Cris Danoy</div>
                <div style="font-size:.76rem;color:var(--gold);margin-bottom:12px;">Full-Stack Developer · System Architect</div>
                <div style="display:flex;flex-direction:column;gap:7px;">
                    <?php foreach ([['fa-phone','09633951825','tel:09633951825'],['fa-envelope','crisdanoy9@gmail.com','mailto:crisdanoy9@gmail.com'],['fab fa-facebook','Facebook Profile','https://www.facebook.com/cris.danoy.7/']] as [$ico,$lbl,$href]): ?>
                    <a href="<?= $href ?>" target="_blank" class="tech-badge" style="justify-content:center;">
                        <i class="<?= $ico ?>"></i> <?= $lbl ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="dev-card" style="text-align:left;">
                <div style="font-family:var(--font-display);font-size:.9rem;color:var(--white);margin-bottom:14px;"><i class="fas fa-code" style="color:var(--gold);margin-right:7px;"></i>Tech Stack</div>
                <div style="display:flex;flex-wrap:wrap;gap:7px;">
                    <?php foreach (['PHP 8','PostgreSQL 15','HTML5','CSS3','JS ES6','Chart.js','PDO','BCrypt','Apache'] as $t): ?>
                    <span class="tech-badge"><?= $t ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="dev-card" style="text-align:left;">
                <div style="font-family:var(--font-display);font-size:.9rem;color:var(--white);margin-bottom:14px;"><i class="fas fa-star" style="color:var(--gold);margin-right:7px;"></i>System Features</div>
                <ul style="list-style:none;display:grid;gap:6px;">
                    <?php foreach (['18 Rooms · 72 Beds · 3 Floors','Admin announcement system','Real-time occupancy charts','Printable payment receipts','Profile photo uploads','Maintenance mode lockdown','PostgreSQL advanced features'] as $f): ?>
                    <li style="font-size:.76rem;color:var(--muted);display:flex;gap:6px;"><i class="fas fa-check" style="color:var(--gold);margin-top:3px;"></i><?= $f ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ── Footer ── -->
<footer style="background:var(--bg);border-top:1px solid var(--border);padding:24px 40px;text-align:center;color:var(--muted);font-size:.78rem;">
    <p>© <?= date('Y') ?> <strong style="color:var(--white);">Nadelas Boarding House</strong> · All Rights Reserved</p>
    <p style="margin-top:6px;">Developed by <a href="https://www.facebook.com/cris.danoy.7/" target="_blank" style="color:var(--gold);">Cris Danoy</a> · Version 3.0 ·
    <a href="<?= APP_URL ?>/auth/login.php" style="color:var(--gold);">Admin Login</a></p>
</footer>

<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>