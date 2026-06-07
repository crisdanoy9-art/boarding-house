<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();
$pageTitle = 'House Rules';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/tenant_nav.php';
?>

<div style="max-width:1200px;margin:0 auto;">
    <div style="text-align:center;margin-bottom:48px;">
        <span style="display:inline-flex;align-items:center;gap:7px;padding:5px 16px;border-radius:99px;background:rgba(201,168,76,.1);border:1px solid rgba(201,168,76,.25);font-size:.72rem;font-weight:700;color:var(--gold);text-transform:uppercase;letter-spacing:.1em;margin-bottom:12px;">
            <i class="fas fa-gavel"></i> House Policies
        </span>
        <h2 class="section-title" style="font-size:1.9rem;color:var(--white);margin-bottom:8px;">Rules & Regulations</h2>
        <p class="section-sub" style="color:var(--muted);">All tenants must follow these rules. Violations may result in eviction.</p>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:24px;">
        <?php 
        $rules = [
            ['fa-clock', 'Quiet Hours', 'Quiet hours are strictly observed from 10:00 PM to 6:00 AM. Loud music, shouting, and disruptive behavior are not allowed during these hours.'],
            ['fa-user-friends', 'Visitor Policy', 'Visitors are welcome until 9:00 PM only. Overnight visitors are strictly prohibited. All visitors must be registered at the front office.'],
            ['fa-smoking-ban', 'No Smoking & Vaping', 'Smoking and vaping are strictly prohibited inside all rooms and common areas. Violators may be asked to vacate the premises.'],
            ['fa-wine-bottle', 'No Alcohol / Illegal Substances', 'Possession or consumption of alcohol inside the boarding house is discouraged. Illegal substances are absolutely prohibited and will result in immediate eviction.'],
            ['fa-broom', 'Cleanliness & Hygiene', 'Keep your bed space and surrounding area clean at all times. Shared spaces such as bathrooms and common areas must be tidied after use. Schedule bathroom cleaning rotation.'],
            ['fa-money-bill-wave', 'Monthly Rent Payment', 'Rent of ₱1,300/month is due on the 1st of every month. A ₱1,300 advance deposit is required upon approval. Late payments may incur penalties. Payments accepted in cash only.'],
            ['fa-lightbulb', 'Electricity Conservation', 'Turn off all lights, electric fans, and appliances when not in use or when leaving the room. High-wattage appliances (e.g., electric stoves) are not allowed.'],
            ['fa-water', 'Water Usage', 'Avoid wasting water. Report any leaking faucets or pipes to management immediately. Do not use the bathroom for laundry purposes without permission.'],
            ['fa-tools', 'Property Care', 'Tenants are responsible for any damage caused to the room, furniture, or facilities. Nailing, drilling, or altering the room walls and fixtures is not allowed.'],
            ['fa-door-closed', 'Curfew', 'The main gate is locked at 11:00 PM. Tenants who will be late must inform management in advance.'],
            ['fa-paw', 'No Pets Allowed', 'Keeping pets of any kind inside the boarding house is strictly not allowed to maintain cleanliness and avoid disturbances.'],
            ['fa-hand-peace', 'Respect & Conduct', 'Treat all co-tenants and management with respect. Harassment, bullying, or any form of misconduct will not be tolerated.']
        ];
        foreach ($rules as [$icon, $title, $desc]): ?>
        <div class="rule-card-tenant" style="background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:20px;transition:all 0.2s;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
                <i class="fas <?= $icon ?>" style="color:var(--gold);font-size:1.4rem;"></i>
                <h3 style="font-family:var(--font-display);color:var(--white);font-size:1rem;margin:0;"><?= $title ?></h3>
            </div>
            <p style="color:var(--muted);font-size:.8rem;line-height:1.6;margin:0;"><?= $desc ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="background:rgba(240,82,82,.06);border:1px solid rgba(240,82,82,.18);border-radius:var(--r-lg);padding:14px 20px;text-align:center;margin-top:32px;">
        <i class="fas fa-exclamation-triangle" style="color:var(--danger);margin-right:7px;"></i>
        <span style="font-size:.84rem;color:var(--muted);">Violation of rules may result in <strong style="color:var(--danger);">immediate eviction</strong> without refund. All tenants sign an agreement upon move‑in.</span>
    </div>

    <div style="background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.18);border-radius:var(--r-lg);padding:14px 20px;text-align:center;margin-top:16px;">
        <span style="font-size:.82rem;color:var(--muted);">Questions? <a href="tel:09633951825" style="color:var(--gold);">09633951825</a> or <a href="https://www.facebook.com/cris.danoy.7/" target="_blank" style="color:var(--gold);"><i class="fab fa-facebook"></i> Facebook</a></span>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>