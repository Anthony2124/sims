<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';

// Ensure gender column exists
if (!$pdo->query("SHOW COLUMNS FROM students LIKE 'gender'")->fetch()) {
    $pdo->exec("ALTER TABLE students ADD COLUMN gender ENUM('Male','Female','Other') DEFAULT NULL");
}

// ── Filters ──────────────────────────────────────────────────────────────────
$f_status  = $_GET['status']  ?? '';
$f_gender  = $_GET['gender']  ?? '';
$f_type    = $_GET['type']    ?? '';
$f_spec    = $_GET['spec']    ?? '';

$where = ['1=1'];
$params = [];
if ($f_status) { $where[] = 's.status = ?';        $params[] = $f_status; }
if ($f_gender) { $where[] = 's.gender = ?';         $params[] = $f_gender; }
if ($f_type)   { $where[] = 's.student_type = ?';   $params[] = $f_type; }
if ($f_spec)   { $where[] = 's.specialization = ?'; $params[] = $f_spec; }

$whereStr = implode(' AND ', $where);

$students = $pdo->prepare("SELECT s.* FROM students s WHERE $whereStr ORDER BY s.id DESC");
$students->execute($params);
$students = $students->fetchAll();

// ── Analytics data (always full, not filtered) ────────────────────────────────
$all = $pdo->query("SELECT status, gender, student_type, specialization, year_level FROM students")->fetchAll();

$stats = [
    'total'     => count($all),
    'active'    => 0, 'inactive' => 0,
    'male'      => 0, 'female'   => 0, 'other_g' => 0,
    'regular'   => 0, 'irregular' => 0,
    'ap'        => 0, 'dd'        => 0, 'no_spec' => 0,
    'yr1'=>0,'yr2'=>0,'yr3'=>0,'yr4'=>0,
];
foreach ($all as $r) {
    if ($r['status'] === 'active')   $stats['active']++;
    if ($r['status'] === 'inactive') $stats['inactive']++;
    if ($r['gender'] === 'Male')     $stats['male']++;
    if ($r['gender'] === 'Female')   $stats['female']++;
    if ($r['gender'] === 'Other')    $stats['other_g']++;
    if ($r['student_type'] === 'Regular')   $stats['regular']++;
    if ($r['student_type'] === 'Irregular') $stats['irregular']++;
    if (strpos($r['specialization'] ?? '', 'AP') !== false) $stats['ap']++;
    elseif (strpos($r['specialization'] ?? '', 'DD') !== false) $stats['dd']++;
    else $stats['no_spec']++;
    if ($r['year_level'] === '1st Year') $stats['yr1']++;
    if ($r['year_level'] === '2nd Year') $stats['yr2']++;
    if ($r['year_level'] === '3rd Year') $stats['yr3']++;
    if ($r['year_level'] === '4th Year') $stats['yr4']++;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Analytics — SIMS</title>
  <link rel="stylesheet" href="/sims/assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
  <style>
    body { background:#1a1a1a; display:block; padding:0; min-height:100vh; overflow-x:hidden; }
    /* ── Sidebar ── */
    .dash-sidebar {
      position:fixed; top:0; left:0; width:240px; height:100vh; background:#111;
      border-right:1px solid rgba(255,255,255,0.07);
      display:flex; flex-direction:column; padding:2rem 1.25rem; gap:.5rem; z-index:100;
    }
    .dash-sidebar .brand { display:flex; align-items:center; gap:.75rem; margin-bottom:2rem; }
    .dash-sidebar .brand-icon {
      width:40px; height:40px; background:#800000; border-radius:10px;
      display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0;
    }
    .dash-sidebar .brand-name { font-family:'Outfit',sans-serif; font-weight:800; font-size:1.15rem; color:#fff; letter-spacing:-.03em; }
    .dash-sidebar .brand-sub  { font-size:.7rem; color:#888; letter-spacing:.05em; text-transform:uppercase; }
    .nav-item {
      display:flex; align-items:center; gap:.65rem; padding:.6rem .9rem; border-radius:8px;
      color:#aaa; text-decoration:none; font-size:.875rem; font-weight:500; transition:all .2s;
    }
    .nav-item:hover { background:rgba(255,255,255,0.05); color:#fff; }
    .nav-item.active { background:rgba(128,0,0,0.25); color:#f47171; border-left:3px solid #800000; padding-left:calc(.9rem - 3px); }
    .nav-icon { width:18px; text-align:center; font-size:1rem; }
    .nav-badge { margin-left:auto; background:#800000; color:#fff; font-size:.68rem; font-weight:700; padding:.15rem .5rem; border-radius:99px; }
    .nav-divider { height:1px; background:rgba(255,255,255,0.06); margin:.75rem 0; }
    .sidebar-footer { margin-top:auto; }
    .sign-out-btn {
      display:flex; align-items:center; gap:.65rem; padding:.6rem .9rem; border-radius:8px;
      color:#f87171; text-decoration:none; font-size:.875rem; font-weight:500; transition:all .2s; width:100%;
    }
    .sign-out-btn:hover { background:rgba(248,113,113,0.1); }
    /* ── Main ── */
    .dash-main { margin-left:240px; min-height:100vh; display:flex; flex-direction:column; }
    .dash-topbar {
      display:flex; align-items:center; justify-content:space-between; padding:1.25rem 2rem;
      border-bottom:1px solid rgba(255,255,255,0.06); background:#1a1a1a; position:sticky; top:0; z-index:50;
    }
    .dash-topbar h1 { font-family:'Outfit',sans-serif; font-size:1.35rem; font-weight:700; color:#fff; letter-spacing:-.03em; }
    .dash-body { padding:2rem; flex:1; }
    /* ── Filter Bar ── */
    .filter-bar {
      display:flex; flex-wrap:wrap; gap:.75rem; align-items:center;
      background:#222; border:1px solid rgba(255,255,255,0.07); border-radius:12px;
      padding:1rem 1.25rem; margin-bottom:2rem;
    }
    .filter-bar label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:#666; }
    .filter-bar select {
      background:#1a1a1a; border:1px solid rgba(255,255,255,0.1); color:#ddd;
      border-radius:8px; padding:.4rem .8rem; font-size:.82rem; cursor:pointer; outline:none;
    }
    .filter-bar select:focus { border-color:#800000; }
    .filter-btn {
      padding:.4rem 1rem; background:#800000; color:#fff; border:none; border-radius:8px;
      font-size:.82rem; font-weight:600; cursor:pointer; transition:background .2s;
    }
    .filter-btn:hover { background:#a52a2a; }
    .filter-reset { color:#888; font-size:.8rem; text-decoration:none; }
    .filter-reset:hover { color:#fff; }
    /* ── Charts Grid ── */
    .charts-grid {
      display:grid; grid-template-columns:repeat(2,1fr);
      gap:1.5rem; margin-bottom:2rem;
    }
    .chart-card {
      background:#222; border:1px solid rgba(255,255,255,0.07); border-radius:14px;
      padding:1.5rem; position:relative; cursor:pointer; transition:border-color .2s;
    }
    .chart-card:hover { border-color:rgba(128,0,0,0.5); }
    .chart-card h3 {
      font-family:'Outfit',sans-serif; font-size:.9rem; font-weight:700; color:#fff;
      margin-bottom:1rem; display:flex; align-items:center; gap:.5rem;
    }
    .chart-canvas-wrap { position:relative; height:220px; }
    .chart-click-hint { font-size:.7rem; color:#555; margin-top:.5rem; text-align:center; }
    /* ── Stats Row ── */
    .stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:2rem; }
    .stat-card { background:#222; border:1px solid rgba(255,255,255,0.07); border-radius:12px; padding:1.25rem 1.5rem; }
    .stat-card .label { font-size:.72rem; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:#888; margin-bottom:.4rem; }
    .stat-card .value { font-family:'Outfit',sans-serif; font-size:2rem; font-weight:800; color:#fff; }
    .stat-card .value.red   { color:#f47171; }
    .stat-card .value.green { color:#6fcf97; }
    .stat-card .value.amber { color:#fbbf24; }
    .stat-card .value.blue  { color:#93c5fd; }
    /* ── Filtered Table ── */
    .table-card { background:#222; border:1px solid rgba(255,255,255,0.07); border-radius:12px; overflow:hidden; margin-bottom:2rem; }
    .table-card-header { display:flex; align-items:center; justify-content:space-between; padding:1.1rem 1.5rem; border-bottom:1px solid rgba(255,255,255,0.07); }
    .table-card-header h2 { font-family:'Outfit',sans-serif; font-size:1rem; font-weight:700; color:#fff; margin:0; }
    .record-count { font-size:.8rem; color:#666; }
    table { width:100%; border-collapse:collapse; }
    th { background:#1e1e1e; color:#666; font-size:.7rem; letter-spacing:.1em; text-transform:uppercase; padding:.75rem 1.5rem; border-bottom:1px solid rgba(255,255,255,0.06); font-weight:700; text-align:left; }
    td { padding:.9rem 1.5rem; color:#ccc; font-size:.875rem; border-bottom:1px solid rgba(255,255,255,0.04); vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    tbody tr:hover td { background:rgba(255,255,255,0.03); color:#fff; }
    .empty-state { text-align:center; padding:3rem; color:#555; }
    .empty-state .empty-icon { font-size:2.5rem; margin-bottom:.75rem; }
    .chip { display:inline-flex; align-items:center; gap:.3rem; padding:.2rem .65rem; border-radius:99px; font-size:.72rem; font-weight:600; }
    .chip-active   { background:rgba(111,207,151,0.14); color:#6fcf97; border:1px solid rgba(111,207,151,0.3); }
    .chip-inactive { background:rgba(248,113,113,0.14); color:#f87171; border:1px solid rgba(248,113,113,0.3); }
    .active-filter-tag {
      display:inline-flex; align-items:center; gap:.4rem; background:rgba(128,0,0,0.2);
      color:#f47171; border:1px solid rgba(128,0,0,0.4); border-radius:99px;
      padding:.2rem .7rem; font-size:.75rem; font-weight:600;
    }
    @media(max-width:900px) {
      .dash-sidebar { display:none; }
      .dash-main { margin-left:0; }
      .charts-grid { grid-template-columns:1fr; }
      .stats-row { grid-template-columns:1fr 1fr; }
    }
  </style>
</head>
<body>

<!-- Sidebar -->
<nav class="dash-sidebar">
  <div class="brand">
    <div class="brand-icon">🛡️</div>
    <div>
      <div class="brand-name">SIMS</div>
      <div class="brand-sub">Admin Dashboard</div>
    </div>
  </div>
  <a class="nav-item" href="/sims/admin/dashboard.php?tab=students"><span class="nav-icon">👥</span> All Students</a>
  <a class="nav-item" href="/sims/admin/dashboard.php?tab=pending"><span class="nav-icon">⏳</span> Pending Approval</a>
  <a class="nav-item" href="/sims/admin/dashboard.php?tab=teachers"><span class="nav-icon">📚</span> Teachers</a>
  <a class="nav-item active" href="/sims/admin/analytics.php"><span class="nav-icon">📊</span> Analytics</a>
  <div class="nav-divider"></div>
  <a class="nav-item" href="/sims/admin/add_student.php"><span class="nav-icon">➕</span> Add Student</a>
  <a class="nav-item" href="/sims/admin/add_teacher.php"><span class="nav-icon">➕</span> Add Teacher</a>
  <div class="nav-divider"></div>
  <div class="sidebar-footer">
    <a class="sign-out-btn" href="/sims/logout.php" onclick="return confirm('Are you sure you want to sign out?');"><span class="nav-icon">🚪</span> Sign Out</a>
  </div>
</nav>

<div class="dash-main">
  <div class="dash-topbar">
    <h1>📊 Student Analytics</h1>
    <?php if ($f_status || $f_gender || $f_type || $f_spec): ?>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
        <?php if ($f_status): ?><span class="active-filter-tag">Status: <?php echo htmlspecialchars($f_status); ?></span><?php endif; ?>
        <?php if ($f_gender): ?><span class="active-filter-tag">Gender: <?php echo htmlspecialchars($f_gender); ?></span><?php endif; ?>
        <?php if ($f_type):   ?><span class="active-filter-tag">Type: <?php echo htmlspecialchars($f_type); ?></span><?php endif; ?>
        <?php if ($f_spec):   ?><span class="active-filter-tag">Spec: <?php echo htmlspecialchars($f_spec); ?></span><?php endif; ?>
        <a href="/sims/admin/analytics.php" style="color:#888;font-size:.8rem;">✕ Clear</a>
      </div>
    <?php endif; ?>
  </div>

  <div class="dash-body">

    <!-- Stats Row -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="label">Total Students</div>
        <div class="value"><?php echo $stats['total']; ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Active</div>
        <div class="value green"><?php echo $stats['active']; ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Regular</div>
        <div class="value blue"><?php echo $stats['regular']; ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Irregular</div>
        <div class="value amber"><?php echo $stats['irregular']; ?></div>
      </div>
    </div>

    <!-- Filter Bar -->
    <form method="get" action="/sims/admin/analytics.php" class="filter-bar">
      <label>Status</label>
      <select name="status">
        <option value="">All</option>
        <option value="active"   <?php echo $f_status==='active'?'selected':''; ?>>Active</option>
        <option value="inactive" <?php echo $f_status==='inactive'?'selected':''; ?>>Inactive</option>
      </select>
      <label>Gender</label>
      <select name="gender">
        <option value="">All</option>
        <option value="Male"   <?php echo $f_gender==='Male'?'selected':''; ?>>Male</option>
        <option value="Female" <?php echo $f_gender==='Female'?'selected':''; ?>>Female</option>
        <option value="Other"  <?php echo $f_gender==='Other'?'selected':''; ?>>Other</option>
      </select>
      <label>Student Type</label>
      <select name="type">
        <option value="">All</option>
        <option value="Regular"   <?php echo $f_type==='Regular'?'selected':''; ?>>Regular</option>
        <option value="Irregular" <?php echo $f_type==='Irregular'?'selected':''; ?>>Irregular</option>
      </select>
      <label>Specialization</label>
      <select name="spec">
        <option value="">All</option>
        <option value="AP - Application Programming" <?php echo $f_spec==='AP - Application Programming'?'selected':''; ?>>AP — Application Programming</option>
        <option value="DD - Digital Design"          <?php echo $f_spec==='DD - Digital Design'?'selected':''; ?>>DD — Digital Design</option>
      </select>
      <button type="submit" class="filter-btn">Apply Filters</button>
      <a href="/sims/admin/analytics.php" class="filter-reset">Reset</a>
    </form>

    <!-- Charts -->
    <div class="charts-grid">

      <!-- Status Chart -->
      <div class="chart-card" id="card-status">
        <h3>🟢 Active vs Inactive</h3>
        <div class="chart-canvas-wrap"><canvas id="chartStatus"></canvas></div>
        <p class="chart-click-hint">Click a segment to filter the table below</p>
      </div>

      <!-- Gender Chart -->
      <div class="chart-card" id="card-gender">
        <h3>⚧ Gender Distribution</h3>
        <div class="chart-canvas-wrap"><canvas id="chartGender"></canvas></div>
        <p class="chart-click-hint">Click a segment to filter the table below</p>
      </div>

      <!-- Student Type Chart -->
      <div class="chart-card" id="card-type">
        <h3>🎓 Regular vs Irregular</h3>
        <div class="chart-canvas-wrap"><canvas id="chartType"></canvas></div>
        <p class="chart-click-hint">Click a segment to filter the table below</p>
      </div>

      <!-- Specialization Chart -->
      <div class="chart-card" id="card-spec">
        <h3>💡 Specialization</h3>
        <div class="chart-canvas-wrap"><canvas id="chartSpec"></canvas></div>
        <p class="chart-click-hint">Click a segment to filter the table below</p>
      </div>

      <!-- Year Level Bar Chart (full width) -->
      <div class="chart-card" style="grid-column:1/-1;" id="card-year">
        <h3>📅 Students per Year Level</h3>
        <div class="chart-canvas-wrap" style="height:200px;"><canvas id="chartYear"></canvas></div>
        <p class="chart-click-hint">Click a bar to see details</p>
      </div>

    </div>

    <!-- Filtered Table -->
    <div class="table-card">
      <div class="table-card-header">
        <h2>Student Records <?php echo ($f_status||$f_gender||$f_type||$f_spec) ? '<span style="color:#800000;font-size:.8rem;">(Filtered)</span>' : ''; ?></h2>
        <span class="record-count"><?php echo count($students); ?> record<?php echo count($students)!==1?'s':''; ?></span>
      </div>
      <table>
        <thead>
          <tr>
            <th>#</th><th>Student ID</th><th>Name</th><th>Course</th>
            <th>Year</th><th>Type</th><th>Specialization</th><th>Gender</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($students)): ?>
          <tr><td colspan="9"><div class="empty-state"><div class="empty-icon">🔍</div><p>No students match the current filters.</p></div></td></tr>
          <?php else: $idx=1; foreach ($students as $s): ?>
          <tr>
            <td><?php echo $idx++; ?></td>
            <td><?php echo htmlspecialchars($s['student_id']?:'—'); ?></td>
            <td style="font-weight:600;color:#fff;"><?php echo htmlspecialchars($s['first_name'].' '.$s['last_name']); ?></td>
            <td><?php echo htmlspecialchars($s['course']?:'—'); ?></td>
            <td><?php echo htmlspecialchars($s['year_level']?:'—'); ?></td>
            <td><?php echo htmlspecialchars($s['student_type']?:'—'); ?></td>
            <td><?php echo htmlspecialchars($s['specialization']?:'—'); ?></td>
            <td><?php echo htmlspecialchars($s['gender']?:'—'); ?></td>
            <td><span class="chip chip-<?php echo $s['status']; ?>"><?php echo htmlspecialchars($s['status']); ?></span></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<script>
const baseUrl = '/sims/admin/analytics.php';

// ── Helper to build URL with one filter ──────────────────────────────────────
function filterUrl(key, val) {
  const p = new URLSearchParams(window.location.search);
  if (p.get(key) === val) { p.delete(key); } else { p.set(key, val); }
  return baseUrl + '?' + p.toString();
}

// ── Shared chart defaults ─────────────────────────────────────────────────────
Chart.defaults.color = '#888';
Chart.defaults.font.family = "'Outfit', sans-serif";

const palette = {
  green:  ['rgba(111,207,151,0.85)', 'rgba(248,113,113,0.85)'],
  gender: ['rgba(147,197,253,0.85)', 'rgba(251,146,148,0.85)', 'rgba(167,139,250,0.85)'],
  type:   ['rgba(99,179,237,0.85)',  'rgba(246,173,85,0.85)'],
  spec:   ['rgba(128,0,0,0.85)',     'rgba(251,191,36,0.85)', 'rgba(100,100,100,0.6)'],
  year:   ['rgba(128,0,0,0.8)','rgba(180,80,80,0.8)','rgba(220,120,120,0.8)','rgba(251,191,36,0.8)'],
};

function makeDoughnut(id, labels, data, colors, filterKey, filterVals) {
  const ctx = document.getElementById(id).getContext('2d');
  const chart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{ data, backgroundColor: colors, borderColor: '#222', borderWidth: 3, hoverOffset: 10 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '60%',
      plugins: {
        legend: { position: 'bottom', labels: { padding: 16, boxWidth: 12 } },
        tooltip: { callbacks: {
          label: ctx => ` ${ctx.label}: ${ctx.parsed} students`
        }}
      },
      onClick: (e, els) => {
        if (!els.length) return;
        const i = els[0].index;
        window.location.href = filterUrl(filterKey, filterVals[i]);
      }
    }
  });
  return chart;
}

// ── Status chart ──────────────────────────────────────────────────────────────
makeDoughnut('chartStatus',
  ['Active', 'Inactive'],
  [<?php echo $stats['active'].','.$stats['inactive']; ?>],
  palette.green, 'status', ['active','inactive']
);

// ── Gender chart ──────────────────────────────────────────────────────────────
makeDoughnut('chartGender',
  ['Male', 'Female', 'Other'],
  [<?php echo $stats['male'].','.$stats['female'].','.$stats['other_g']; ?>],
  palette.gender, 'gender', ['Male','Female','Other']
);

// ── Student type chart ────────────────────────────────────────────────────────
makeDoughnut('chartType',
  ['Regular', 'Irregular'],
  [<?php echo $stats['regular'].','.$stats['irregular']; ?>],
  palette.type, 'type', ['Regular','Irregular']
);

// ── Specialization chart ──────────────────────────────────────────────────────
makeDoughnut('chartSpec',
  ['AP - Application Programming', 'DD - Digital Design', 'No Specialization'],
  [<?php echo $stats['ap'].','.$stats['dd'].','.$stats['no_spec']; ?>],
  palette.spec, 'spec', ['AP - Application Programming','DD - Digital Design','']
);

// ── Year level bar chart ──────────────────────────────────────────────────────
const ctxYear = document.getElementById('chartYear').getContext('2d');
new Chart(ctxYear, {
  type: 'bar',
  data: {
    labels: ['1st Year','2nd Year','3rd Year','4th Year'],
    datasets: [{
      label: 'Students',
      data: [<?php echo $stats['yr1'].','.$stats['yr2'].','.$stats['yr3'].','.$stats['yr4']; ?>],
      backgroundColor: palette.year,
      borderRadius: 8,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: ctx => ` ${ctx.parsed.y} students` } }
    },
    scales: {
      x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#888' } },
      y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#888', stepSize: 1 }, beginAtZero: true }
    },
    onClick: (e, els) => {
      if (!els.length) return;
      const labels = ['1st Year','2nd Year','3rd Year','4th Year'];
      // No direct filter for year_level yet; scroll to table
      document.querySelector('.table-card').scrollIntoView({ behavior:'smooth' });
    }
  }
});
</script>
</body>
</html>
