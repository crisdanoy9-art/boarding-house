// ============================================================
// NADELAS BOARDING HOUSE — MAIN JS v3.0
// Smooth, modern, responsive interactions
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
    initFlash();
    initSidebar();
    initMobileNav();
    initModals();
    initConfirm();
    initFormValidation();
    initTableSearch();
    if (document.getElementById('occupancyChart')) initCharts();

    // ── Public mobile nav toggle ──
    const pubToggle = document.getElementById('publicNavToggle');
    const pubLinks = document.querySelector('.public-nav-links');
    if (pubToggle && pubLinks) {
        pubToggle.addEventListener('click', () => pubLinks.classList.toggle('open'));
        document.addEventListener('click', (e) => {
            if (!pubToggle.contains(e.target) && !pubLinks.contains(e.target)) {
                pubLinks.classList.remove('open');
            }
        });
    }
});

// ── Flash auto-dismiss ──
function initFlash() {
    const flash = document.getElementById('flashMessage');
    if (flash) {
        setTimeout(() => {
            flash.style.animation = 'slideOutRight .4s ease forwards';
            flash.addEventListener('animationend', () => flash.remove(), { once: true });
        }, 5000);
    }
}

// inject slideOutRight
const style = document.createElement('style');
style.textContent = '@keyframes slideOutRight{to{transform:translateX(120%);opacity:0}}';
document.head.appendChild(style);

// ── Admin sidebar ──
function initSidebar() {
    const toggle   = document.getElementById('sidebarToggle');
    const topToggle= document.getElementById('topbarToggle');
    const sidebar  = document.getElementById('adminSidebar');
    const wrapper  = document.getElementById('mainWrapper');
    const overlay  = document.getElementById('sidebarOverlay');

    // Persist collapsed state
    const collapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (collapsed && window.innerWidth > 1100) {
        sidebar?.classList.add('collapsed');
    }

    toggle?.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    });

    // Mobile: topbar toggle
    topToggle?.addEventListener('click', () => {
        sidebar?.classList.toggle('open');
        overlay?.classList.toggle('show');
    });

    overlay?.addEventListener('click', () => {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('show');
    });

    // Close on nav link click (mobile)
    sidebar?.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 1100) {
                sidebar.classList.remove('open');
                overlay?.classList.remove('show');
            }
        });
    });
}

// ── Tenant mobile nav ──
function initMobileNav() {
    const toggle = document.getElementById('navToggle');
    const links  = document.getElementById('navLinks');
    if (toggle && links) {
        toggle.addEventListener('click', () => links.classList.toggle('open'));
        // Close on outside click
        document.addEventListener('click', e => {
            if (!toggle.contains(e.target) && !links.contains(e.target)) {
                links.classList.remove('open');
            }
        });
    }
}

// ── Modals ──
function initModals() {
    document.querySelectorAll('[data-modal-open]').forEach(btn => {
        btn.addEventListener('click', () => openModal(btn.dataset.modalOpen));
    });
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => closeModal(btn.dataset.modalClose));
    });
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) closeModal(overlay.id);
        });
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(m => closeModal(m.id));
        }
    });
}

function openModal(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
}

// ── Confirm dialogs ──
function initConfirm() {
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm(this.dataset.confirm || 'Are you sure?')) e.preventDefault();
        });
    });
}

// ── Form validation ──
function initFormValidation() {
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', function(e) {
            let valid = true;

            this.querySelectorAll('[required]').forEach(field => {
                clearFieldError(field);
                if (!field.value.trim()) {
                    showFieldError(field, 'This field is required');
                    valid = false;
                }
            });

            this.querySelectorAll('input[type="email"]').forEach(field => {
                if (field.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
                    showFieldError(field, 'Please enter a valid email');
                    valid = false;
                }
            });

            this.querySelectorAll('[data-match]').forEach(field => {
                const target = document.getElementById(field.dataset.match);
                if (target && field.value !== target.value) {
                    showFieldError(field, 'Passwords do not match');
                    valid = false;
                }
            });

            if (!valid) e.preventDefault();
        });

        // Live clear
        form.querySelectorAll('input, select, textarea').forEach(f => {
            f.addEventListener('input', () => clearFieldError(f));
        });
    });
}

function showFieldError(field, msg) {
    field.style.borderColor = 'var(--danger)';
    const existing = field.parentElement.querySelector('.form-error');
    if (!existing) {
        const err = document.createElement('div');
        err.className = 'form-error';
        err.textContent = msg;
        field.parentElement.appendChild(err);
    }
}

function clearFieldError(field) {
    field.style.borderColor = '';
    field.parentElement.querySelector('.form-error')?.remove();
}

// ── Table search ──
function initTableSearch() {
    document.querySelectorAll('[data-search-table]').forEach(input => {
        input.addEventListener('input', function() {
            const table = document.getElementById(this.dataset.searchTable);
            if (!table) return;
            const q = this.value.toLowerCase();
            table.querySelectorAll('tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    });
}

// ── Charts ──
function initCharts() {
    Chart.defaults.color = '#7e7a8c';
    Chart.defaults.font.family = "'DM Sans', sans-serif";
    Chart.defaults.font.size   = 12;

    const tooltipOpts = {
        backgroundColor: '#18182a',
        borderColor: 'rgba(201,168,76,.3)',
        borderWidth: 1,
        padding: 12,
        cornerRadius: 10,
        titleColor: '#fff',
        bodyColor: '#9ca3af',
    };

    // Occupancy chart
    const occCtx = document.getElementById('occupancyChart');
    if (occCtx && window.occupancyData) {
        new Chart(occCtx, {
            type: 'bar',
            data: {
                labels: window.occupancyData.labels,
                datasets: [
                    { label:'Occupied', data:window.occupancyData.occupied,  backgroundColor:'rgba(201,168,76,.75)',  borderColor:'#c9a84c', borderWidth:1, borderRadius:6 },
                    { label:'Available',data:window.occupancyData.available, backgroundColor:'rgba(62,207,110,.45)', borderColor:'#3ecf6e', borderWidth:1, borderRadius:6 },
                ]
            },
            options: {
                responsive:true, maintainAspectRatio:false,
                plugins: { legend:{ position:'top' }, tooltip:tooltipOpts },
                scales: {
                    x:{ grid:{ color:'rgba(255,255,255,.04)' } },
                    y:{ grid:{ color:'rgba(255,255,255,.04)' }, beginAtZero:true }
                }
            }
        });
    }

    // Room status donut
    const sCtx = document.getElementById('roomStatusChart');
    if (sCtx && window.roomStatusData) {
        new Chart(sCtx, {
            type:'doughnut',
            data: {
                labels:['Available','Full','Maintenance'],
                datasets:[{ data:window.roomStatusData, backgroundColor:['rgba(62,207,110,.8)','rgba(240,82,82,.8)','rgba(240,168,50,.8)'], borderColor:['#3ecf6e','#f05252','#f0a832'], borderWidth:2, hoverOffset:6 }]
            },
            options:{
                responsive:true, maintainAspectRatio:false, cutout:'66%',
                plugins:{ legend:{ position:'bottom' }, tooltip:tooltipOpts }
            }
        });
    }

    // Income line
    const iCtx = document.getElementById('incomeChart');
    if (iCtx && window.incomeData) {
        new Chart(iCtx, {
            type:'line',
            data: {
                labels:window.incomeData.labels,
                datasets:[{ label:'Monthly Income (₱)', data:window.incomeData.values, borderColor:'#c9a84c', backgroundColor:'rgba(201,168,76,.07)', borderWidth:2.5, fill:true, tension:.4, pointBackgroundColor:'#c9a84c', pointRadius:4, pointHoverRadius:7 }]
            },
            options:{
                responsive:true, maintainAspectRatio:false,
                plugins:{ legend:{display:false}, tooltip:{ ...tooltipOpts, callbacks:{ label:ctx => '₱'+ctx.raw.toLocaleString() } } },
                scales:{
                    x:{ grid:{ color:'rgba(255,255,255,.04)' } },
                    y:{ grid:{ color:'rgba(255,255,255,.04)' }, beginAtZero:true }
                }
            }
        });
    }
}

// ── Utilities ──
function exportCSV(tableId, filename='export.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;
    const rows = Array.from(table.querySelectorAll('tr'));
    const csv = rows.map(row =>
        Array.from(row.querySelectorAll('th,td'))
            .map(cell => '"' + cell.textContent.replace(/"/g,'""').trim() + '"')
            .join(',')
    ).join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], { type:'text/csv;charset=utf-8;' }));
    a.download = filename;
    a.click();
}

function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toLocaleString('en-PH', { minimumFractionDigits:2 });
}