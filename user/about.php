<?php require_once __DIR__.'/../includes/session.php'; requireLogin(); $pageTitle='About This System'; include __DIR__.'/../includes/header.php'; include __DIR__.'/../includes/tenant_nav.php'; ?>
<div style="max-width:1100px;margin:0 auto;">
<div class="about-hero"><div style="position:relative;z-index:1;"><div style="width:66px;height:66px;border-radius:50%;background:linear-gradient(135deg,var(--gold),var(--gold-dk));display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-family:var(--font-display);font-size:.92rem;font-weight:900;color:#06060e;box-shadow:var(--shadow-gold);">NBH</div><h1 style="font-family:var(--font-display);font-size:1.7rem;color:var(--white);margin-bottom:6px;">Nadelas Boarding House</h1><div style="font-size:.72rem;color:var(--gold);text-transform:uppercase;letter-spacing:.12em;margin-bottom:10px;">Online Booking & Management System v3.0</div><p style="color:var(--muted);max-width:700px;margin:0 auto;font-size:.95rem;line-height:1.8;">The <strong style="color:var(--gold);">Nadelas Boarding House Management System</strong> is a complete digital solution that automates room reservations, tenant records, payment tracking, and occupancy monitoring. Designed for both administrators and tenants, it eliminates paper‑based processes and provides real‑time insights to run the boarding house efficiently.</p></div></div>

<!-- Extended description -->
<div style="background:linear-gradient(135deg, rgba(201,168,76,.05), rgba(201,168,76,.01)); border:1px solid rgba(201,168,76,.12); border-radius:var(--r-xl); padding:28px 32px; margin-bottom:32px;">
    <div style="font-family:var(--font-display); font-size:1.2rem; color:var(--gold); margin-bottom:8px;">Why a digital management system?</div>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-top:16px;">
        <div>
            <div style="font-weight:700; color:var(--white); margin-bottom:8px;">📊 For Administrators</div>
            <ul style="list-style:none; color:var(--muted); font-size:.85rem; line-height:1.7;">
                <li><i class="fas fa-check-circle" style="color:var(--success); font-size:.7rem;"></i> Central dashboard with real‑time occupancy & income charts</li>
                <li><i class="fas fa-check-circle" style="color:var(--success); font-size:.7rem;"></i> Manage rooms, beds, and floor layouts</li>
                <li><i class="fas fa-check-circle" style="color:var(--success); font-size:.7rem;"></i> Approve or reject tenant reservations</li>
                <li><i class="fas fa-check-circle" style="color:var(--success); font-size:.7rem;"></i> Record payments, print receipts, track overdue balances</li>
                <li><i class="fas fa-check-circle" style="color:var(--success); font-size:.7rem;"></i> Broadcast announcements to tenants</li>
                <li><i class="fas fa-check-circle" style="color:var(--success); font-size:.7rem;"></i> Generate income & occupancy reports (CSV export)</li>
            </ul>
        </div>
        <div>
            <div style="font-weight:700; color:var(--white); margin-bottom:8px;">👥 For Tenants</div>
            <ul style="list-style:none; color:var(--muted); font-size:.85rem; line-height:1.7;">
                <li><i class="fas fa-check-circle" style="color:var(--success); font-size:.7rem;"></i> Browse available rooms and beds (interactive floor plans)</li>
                <li><i class="fas fa-check-circle" style="color:var(--success); font-size:.7rem;"></i> Submit online reservations (no paperwork)</li>
                <li><i class="fas fa-check-circle" style="color:var(--success); font-size:.7rem;"></i> View payment history and download receipts</li>
                <li><i class="fas fa-check-circle" style="color:var(--success); font-size:.7rem;"></i> Receive real‑time announcements from admin</li>
                <li><i class="fas fa-check-circle" style="color:var(--success); font-size:.7rem;"></i> Update personal profile and contact details</li>
                <li><i class="fas fa-check-circle" style="color:var(--success); font-size:.7rem;"></i> Access house rules and policies anytime</li>
            </ul>
        </div>
    </div>
</div>

<!-- System Features Card -->
<div class="card mb-4"><div class="card-header"><span class="card-title"><i class="fas fa-star" style="color:var(--gold);margin-right:7px;"></i>System Features</span></div><div class="card-body"><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">
<?php foreach([
    ['fa-building','Room & Bed Management','Manage multiple floors, rooms, and individual beds. Set monthly rates, capacity, and amenities per room.'],
    ['fa-calendar-check','Online Booking System','Tenants can reserve any available bed online – approval workflow ensures proper management.'],
    ['fa-file-invoice','Payment Tracking & Receipts','Record payments (cash, bank transfer), print official receipts, and monitor overdue accounts.'],
    ['fa-chart-line','Analytics Dashboard','Real‑time occupancy charts, monthly income graphs, and room status overview.'],
    ['fa-bullhorn','Announcements','Send dismissible announcements to all tenants or target specific groups. Expiry dates supported.'],
    ['fa-envelope','Automated Notifications','Email alerts for payment reminders, reservation status changes, and system updates.'],
    ['fa-shield-alt','Secure Authentication','BCrypt password hashing, CSRF protection, XSS filtering, and role‑based access (admin/tenant).'],
    ['fa-mobile-alt','Responsive Design','Fully responsive layout that works on desktops, tablets, and mobile devices.'],
    ['fa-chart-pie','Income & Occupancy Reports','Export CSV reports of monthly income, overdue tenants, and occupancy trends.'],
    ['fa-clock','Scheduled Auto‑Vacate','3 months overdue automatically deactivates tenant and frees the bed (house rule enforcement).'],
    ['fa-gavel','House Rules Library','Built‑in rules reference with categories (payment, curfew, cleanliness, prohibited items).'],
    ['fa-code-branch','Full‑Stack PHP/PostgreSQL','Modern tech stack: PHP 8, PostgreSQL 15, Chart.js, PDO, prepared statements for security.']
] as [$icon,$title,$desc]): ?>
<div style="display:flex;gap:12px;align-items:flex-start;padding:10px;background:var(--surface2);border-radius:var(--r-md);">
    <div style="width:36px;height:36px;border-radius:var(--r-md);background:rgba(201,168,76,.12);display:flex;align-items:center;justify-content:center;color:var(--gold);flex-shrink:0;"><i class="fas <?= $icon ?>"></i></div>
    <div><div style="font-weight:600;color:var(--white);font-size:.86rem;"><?= $title ?></div><div style="font-size:.76rem;color:var(--muted);line-height:1.5;"><?= $desc ?></div></div>
</div>
<?php endforeach; ?>
</div></div></div>

<!-- Developer Card -->
<div class="card"><div class="card-header"><span class="card-title"><i class="fas fa-laptop-code" style="color:var(--gold);margin-right:7px;"></i>Developer</span></div><div class="card-body" style="text-align:center;padding:28px;"><div class="dev-avatar-default" style="width:76px;height:76px;font-size:1.35rem;margin-bottom:12px;">CD</div><div style="font-family:var(--font-display);font-size:1.2rem;color:var(--white);margin-bottom:4px;">Cris Danoy</div><div style="font-size:.78rem;color:var(--gold);margin-bottom:12px;">Full-Stack Developer · System Architect</div><p style="color:var(--muted);font-size:.83rem;max-width:400px;margin:0 auto 18px;line-height:1.72;">Designed and built the complete Nadelas BH Management System from scratch – database design, backend logic, and responsive UI.</p><div style="display:flex;justify-content:center;gap:10px;flex-wrap:wrap;"><?php foreach([['fa-phone','09633951825','tel:09633951825'],['fa-envelope','crisdanoy9@gmail.com','mailto:crisdanoy9@gmail.com'],['fab fa-facebook','Facebook','https://www.facebook.com/cris.danoy.7/']] as [$i,$l,$h]): ?><a href="<?= $h ?>" target="_blank" class="btn btn-ghost btn-sm"><i class="<?= $i ?>"></i> <?= $l ?></a><?php endforeach; ?></div></div></div>
<div style="text-align:center;margin-top:18px;font-size:.74rem;color:var(--muted);">© <?= date('Y') ?> Nadelas Boarding House · Version 3.0</div>
</div>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>