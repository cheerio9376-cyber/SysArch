<?php
// admin/analytics.php — Dashboard analytics with charts + time range filter
require 'includes/admin_auth.php';
$current_page = 'analytics';

// ── Time range param ──────────────────────────────────────
$period = $_GET['period'] ?? 'month';
$allowed_periods = ['week' => 7, 'month' => 30, 'quarter' => 90];
$days = $allowed_periods[$period] ?? 30;

// ── Purpose distribution (filtered by period) ────────────
$purpose_data = $conn->query("
    SELECT purpose, COUNT(*) AS total
    FROM sitins
    WHERE purpose IS NOT NULL AND purpose != ''
      AND sit_in_time >= CURDATE() - INTERVAL {$days} DAY
    GROUP BY purpose ORDER BY total DESC
")->fetch_all(MYSQLI_ASSOC);

// ── Daily sit-ins for selected period ────────────────────
$daily_data = $conn->query("
    SELECT DATE(sit_in_time) AS day, COUNT(*) AS cnt
    FROM sitins
    WHERE sit_in_time >= CURDATE() - INTERVAL " . ($days - 1) . " DAY
    GROUP BY DATE(sit_in_time)
    ORDER BY day ASC
")->fetch_all(MYSQLI_ASSOC);

// ── Lab usage (filtered by period) ───────────────────────
$lab_data = $conn->query("
    SELECT lab, COUNT(*) AS total
    FROM sitins
    WHERE lab IS NOT NULL AND lab != ''
      AND sit_in_time >= CURDATE() - INTERVAL {$days} DAY
    GROUP BY lab ORDER BY total DESC
")->fetch_all(MYSQLI_ASSOC);

// ── Task completion breakdown (filtered by period) ────────
$task_data = $conn->query("
    SELECT task_status, COUNT(*) AS total
    FROM sitins
    WHERE status = 'completed'
      AND task_status IS NOT NULL
      AND sit_in_time >= CURDATE() - INTERVAL {$days} DAY
    GROUP BY task_status
")->fetch_all(MYSQLI_ASSOC);

// ── Summary stats (filtered by period) ───────────────────
$period_clause = "sit_in_time >= CURDATE() - INTERVAL {$days} DAY";

$total_sessions     = $conn->query("SELECT COUNT(*) FROM sitins WHERE $period_clause")->fetch_row()[0];
$avg_duration       = $conn->query("SELECT ROUND(AVG(duration_minutes),1) FROM sitins WHERE duration_minutes IS NOT NULL AND $period_clause")->fetch_row()[0] ?? 0;
$unique_students    = $conn->query("SELECT COUNT(DISTINCT student_id) FROM sitins WHERE $period_clause")->fetch_row()[0];
$completed_sessions = $conn->query("SELECT COUNT(*) FROM sitins WHERE status='completed' AND $period_clause")->fetch_row()[0];
$total_students_all = $conn->query("SELECT COUNT(*) FROM students WHERE role='student'")->fetch_row()[0];

$task_map = [];
foreach ($task_data as $t) $task_map[$t['task_status']] = (int)$t['total'];
$t_complete   = $task_map['complete']   ?? 0;
$t_incomplete = $task_map['incomplete'] ?? 0;
$t_total      = $t_complete + $t_incomplete;

$period_labels = ['week' => 'This Week', 'month' => 'Last 30 Days', 'quarter' => 'Last 3 Months'];
$period_label  = $period_labels[$period];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics | CCS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'includes/admin_navbar.php'; ?>
<div class="admin-wrapper">
<div class="admin-content">

    <!-- ── Page Header ── -->
    <div class="page-header d-flex flex-wrap align-items-center gap-3">
        <div>
            <h2>Analytics</h2>
            <small class="text-muted">Sit-in usage patterns and system statistics</small>
        </div>
        <div class="ms-auto">
            <div class="dropdown">
                <button class="btn btn-purple dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-calendar3 me-1"></i><?= $period_label ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item <?= $period==='week'    ? 'active' : '' ?>" href="?period=week">This Week</a></li>
                    <li><a class="dropdown-item <?= $period==='month'   ? 'active' : '' ?>" href="?period=month">Last 30 Days</a></li>
                    <li><a class="dropdown-item <?= $period==='quarter' ? 'active' : '' ?>" href="?period=quarter">Last 3 Months</a></li>
                </ul>
            </div>
        </div>
    </div>


    <div class="row g-4">

        <!-- ── Purpose Pie Chart ── -->
        <div class="col-lg-5">
            <div class="card-ccs p-4 h-100">
                <h5 class="fw-bold mb-3" style="color:#5a3d82;">Sit-In Purpose Distribution</h5>
                <?php if (empty($purpose_data)): ?>
                    <div class="empty-chart">No data for this period.</div>
                <?php else: ?>
                    <canvas id="purposeChart" height="280"></canvas>
                    <div class="mt-3" id="purposeLegend"></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Daily Line Chart ── -->
        <div class="col-lg-7">
            <div class="card-ccs p-4 h-100">
                <h5 class="fw-bold mb-3" style="color:#5a3d82;">Daily Sit-Ins (<?= $period_label ?>)</h5>
                <canvas id="dailyChart" height="200"></canvas>
            </div>
        </div>

        <!-- ── Lab Bar Chart ── -->
        <div class="col-lg-6">
            <div class="card-ccs p-4">
                <h5 class="fw-bold mb-3" style="color:#5a3d82;">Lab Usage</h5>
                <?php if (empty($lab_data)): ?>
                    <div class="empty-chart">No data for this period.</div>
                <?php else: ?>
                    <canvas id="labChart" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Purpose table ── -->
        <div class="col-lg-6">
            <div class="card-ccs p-0">
                <div class="p-3 border-bottom fw-600" style="color:#5a3d82;">
                  Purpose Breakdown
                </div>
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Purpose</th><th class="text-end">Sessions</th><th class="text-end">%</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($purpose_data)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">No data for this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($purpose_data as $p):
                            $pct = $total_sessions ? round($p['total']/$total_sessions*100,1) : 0;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($p['purpose']) ?></td>
                            <td class="text-end fw-600"><?= $p['total'] ?></td>
                            <td class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <div class="progress flex-grow-1" style="height:8px;max-width:80px;">
                                        <div class="progress-bar" style="width:<?= $pct ?>%;background:#5a3d82;"></div>
                                    </div>
                                    <span class="small text-muted"><?= $pct ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div></div>

<footer class="adm-footer">
    <small>&copy; <?= date('Y') ?> College of Computer Studies &bull; CCS Sit-In Monitoring System</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () =>
    document.getElementById('adminSidebar').classList.toggle('show'));

const PALETTE = ['#5a3d82','#d4a017','#27ae60','#2980b9','#e74c3c','#8e44ad','#16a085','#f39c12','#2c3e50','#1abc9c'];
const DAYS    = <?= $days ?>;
const PERIOD  = '<?= $period ?>';

// ── Build date axis (day-level for week/month, week-level for quarter) ──
function buildAxis(rawData) {
    if (PERIOD !== 'quarter') {
        const labels = [], counts = [];
        for (let i = DAYS - 1; i >= 0; i--) {
            const d = new Date();
            d.setDate(d.getDate() - i);
            const key = d.toISOString().slice(0, 10);
            const monthFmt = DAYS <= 7
                ? d.toLocaleDateString('en', { weekday: 'short', month: 'short', day: 'numeric' })
                : d.toLocaleDateString('en', { month: 'short', day: 'numeric' });
            labels.push(monthFmt);
            const found = rawData.find(r => r.day === key);
            counts.push(found ? parseInt(found.cnt) : 0);
        }
        return { labels, counts };
    } else {
        // Group by ISO week (Mon–Sun) for 3-month view
        const weekMap = {}, weekOrder = [];
        for (let i = DAYS - 1; i >= 0; i--) {
            const d = new Date();
            d.setDate(d.getDate() - i);
            const dow = d.getDay();
            const mon = new Date(d);
            mon.setDate(d.getDate() - ((dow + 6) % 7));
            const wkey = mon.toISOString().slice(0, 10);
            if (!weekMap[wkey]) {
                weekMap[wkey] = 0;
                weekOrder.push(wkey);
            }
            const key = d.toISOString().slice(0, 10);
            const found = rawData.find(r => r.day === key);
            if (found) weekMap[wkey] += parseInt(found.cnt);
        }
        const labels = weekOrder.map(k => {
            const d = new Date(k + 'T00:00:00');
            return 'Wk ' + d.toLocaleDateString('en', { month: 'short', day: 'numeric' });
        });
        return { labels, counts: weekOrder.map(k => weekMap[k]) };
    }
}

const rawDaily = <?= json_encode($daily_data) ?>;
const { labels: tLabels, counts: tCounts } = buildAxis(rawDaily);

// ── Trend line chart ─────────────────────────────────────
new Chart(document.getElementById('dailyChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: tLabels,
        datasets: [{
            label: PERIOD === 'quarter' ? 'Sessions / Week' : 'Sessions',
            data: tCounts,
            borderColor: '#5a3d82',
            backgroundColor: 'rgba(90,61,130,.10)',
            tension: 0.4,
            fill: true,
            pointRadius: 4,
            pointBackgroundColor: '#5a3d82',
            pointHoverRadius: 6,
        }]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1, color: '#888' }, grid: { color: '#f5f0ff' } },
            x: { ticks: { maxRotation: 45, color: '#888', font: { size: 10 } }, grid: { display: false } }
        },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#5a3d82',
                titleColor: '#fff',
                bodyColor: '#e0d6f5',
                padding: 10,
                cornerRadius: 8,
            }
        }
    }
});

// ── Purpose doughnut ─────────────────────────────────────
const purposeRaw = <?= json_encode($purpose_data) ?>;
if (purposeRaw.length) {
    new Chart(document.getElementById('purposeChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: purposeRaw.map(r => r.purpose),
            datasets: [{ data: purposeRaw.map(r => r.total), backgroundColor: PALETTE, borderWidth: 2, borderColor: '#fff' }]
        },
        options: {
            responsive: true,
            cutout: '62%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            const tot = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            return ` ${ctx.label}: ${ctx.parsed} (${((ctx.parsed/tot)*100).toFixed(1)}%)`;
                        }
                    }
                }
            }
        }
    });

    const legendEl = document.getElementById('purposeLegend');
    const tot = purposeRaw.reduce((a, r) => a + parseInt(r.total), 0);
    legendEl.innerHTML = purposeRaw.map((r, i) => `
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="legend-dot" style="background:${PALETTE[i]};"></span>
            <span class="small flex-fill">${r.purpose}</span>
            <span class="badge bg-light text-dark">${r.total}</span>
            <span class="small text-muted" style="min-width:36px;">${tot ? ((r.total/tot)*100).toFixed(1) : 0}%</span>
        </div>
    `).join('');
}

// ── Lab bar chart ────────────────────────────────────────
const labRaw = <?= json_encode($lab_data) ?>;
if (labRaw.length) {
    new Chart(document.getElementById('labChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: labRaw.map(r => r.lab),
            datasets: [{
                label: 'Sessions',
                data: labRaw.map(r => r.total),
                backgroundColor: PALETTE,
                borderRadius: 8,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, color: '#888' }, grid: { color: '#f5f0ff' } },
                x: { ticks: { color: '#888' }, grid: { display: false } }
            }
        }
    });
}

</script>

<style>
.text-purple { color:var(--purple,#5a3d82)!important; }
.empty-chart { text-align:center;color:#aaa;padding:40px 10px;font-size:0.85rem; }
.legend-dot  { display:inline-block;width:11px;height:11px;border-radius:3px;flex-shrink:0; }
</style>
</body>
</html>