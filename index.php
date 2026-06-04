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
        <li><a href="#about">About</a></li>
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

<!-- ── About Section (Pricing & developer credit removed) ── -->
<section id="about" style="background:var(--bg);padding:70px 40px;">
    <div style="max-width:1100px;margin:0 auto;">
        <!-- Main heading -->
        <div style="text-align:center;margin-bottom:48px;">
            <h2 class="section-title" style="font-size:2.2rem;">A Smarter Way to Manage Boarding Houses</h2>
            <p style="color:var(--muted);max-width:750px;margin:16px auto 0;line-height:1.7;">
                The <strong style="color:var(--gold);">Nadelas Boarding House Management System</strong> is a web-based platform designed to simplify and digitize the day-to-day operations of a boarding house.
                It eliminates paper-based processes and manual record-keeping by providing a centralized, easy-to-use system for both administrators and tenants.
            </p>
        </div>

        <!-- Admin & Tenant roles -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-bottom:56px;">
            <div style="background:rgba(201,168,76,.05);border-left:4px solid var(--gold);border-radius:var(--r-md);padding:28px;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                    <i class="fas fa-user-shield" style="font-size:2rem;color:var(--gold);"></i>
                    <h3 style="font-family:var(--font-display);color:var(--white);font-size:1.3rem;margin:0;">For Administrators</h3>
                </div>
                <p style="color:var(--muted);font-size:.9rem;line-height:1.7;">Manage rooms and beds by floor, approve or reject tenant reservations, track monthly payments, monitor occupancy rates in real-time, and generate income and occupancy reports — all from a single dashboard.</p>
            </div>
            <div style="background:rgba(201,168,76,.05);border-left:4px solid var(--gold);border-radius:var(--r-md);padding:28px;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                    <i class="fas fa-users" style="font-size:2rem;color:var(--gold);"></i>
                    <h3 style="font-family:var(--font-display);color:var(--white);font-size:1.3rem;margin:0;">For Tenants</h3>
                </div>
                <p style="color:var(--muted);font-size:.9rem;line-height:1.7;">Browse available rooms, select a specific bed, submit reservations online, view their payment history, and update their profile — anytime, anywhere.</p>
            </div>
        </div>

        <!-- Value proposition / why it matters -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-bottom:48px;">
            <div style="text-align:center;">
                <i class="fas fa-file-alt" style="font-size:2rem;color:var(--gold);margin-bottom:12px;"></i>
                <div style="font-weight:600;color:var(--white);margin-bottom:6px;">Paperless Operations</div>
                <p style="color:var(--muted);font-size:.75rem;">No more lost records or messy ledgers</p>
            </div>
            <div style="text-align:center;">
                <i class="fas fa-chart-simple" style="font-size:2rem;color:var(--gold);margin-bottom:12px;"></i>
                <div style="font-weight:600;color:var(--white);margin-bottom:6px;">Real-Time Insights</div>
                <p style="color:var(--muted);font-size:.75rem;">Occupancy & income at a glance</p>
            </div>
            <div style="text-align:center;">
                <i class="fas fa-mobile-alt" style="font-size:2rem;color:var(--gold);margin-bottom:12px;"></i>
                <div style="font-weight:600;color:var(--white);margin-bottom:6px;">Anywhere Access</div>
                <p style="color:var(--muted);font-size:.75rem;">Manage or book from any device</p>
            </div>
        </div>

        <!-- No pricing block, no developer credit line -->
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