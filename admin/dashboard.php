<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

// ── Actions ──
if (isset($_GET['delete_student'])) {
    $id = (int)$_GET['delete_student'];
    // Get email so we can also delete the users record
    $st = $pdo->prepare('SELECT email FROM students WHERE id = ?');
    $st->execute([$id]);
    $stu = $st->fetch();
    $pdo->prepare('DELETE FROM students WHERE id = ?')->execute([$id]);
    if ($stu && !empty($stu['email'])) {
        $pdo->prepare('DELETE FROM users WHERE email = ? AND role = "student"')->execute([$stu['email']]);
    }
    header('Location: /sims/admin/dashboard.php?tab=students'); exit;
}
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    $stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ? AND role = "student" AND is_verified = 0');
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if ($u) {
        $pdo->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([$id]);
        // Notify student of approval
        $login_url = rtrim(SITE_URL, '/') . BASE_URL . '/select_role.php?role=student';
        $email_body = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;'>
              <div style='background:#800000;padding:24px 32px;border-radius:10px 10px 0 0;text-align:center;'>
                <h1 style='color:#fff;margin:0;font-size:1.6rem;letter-spacing:1px;'>🎓 SIMS</h1>
                <p style='color:#f5c6c6;margin:6px 0 0;font-size:.9rem;'>Account Notification</p>
              </div>
              <div style='background:#fff8f0;padding:32px;border-radius:0 0 10px 10px;border:1px solid #f0dcd0;'>
                <h2 style='color:#800000;margin:0 0 12px;'>Account Approved ✅</h2>
                <p style='color:#444;'>Hello <strong>" . htmlspecialchars($u['name']) . "</strong>,</p>
                <p style='color:#444;'>Great news! Your registration has been approved by the administrator.</p>
                <p style='color:#444;'>You can now log in to the Student Information Management System.</p>
                <p style='text-align:center;margin:24px 0;'><a href='" . $login_url . "' style='display:inline-block; padding:12px 28px; background:#800000; color:#fff; text-decoration:none; border-radius:8px; font-weight:bold;'>Log in to SIMS →</a></p>
              </div>
            </div>";
        send_mail($u['email'], 'SIMS — Your Account Has Been Approved', $email_body);
    }
    header('Location: /sims/admin/dashboard.php?tab=pending'); exit;
}
if (isset($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    $user = $pdo->prepare('SELECT name, email FROM users WHERE id = ? AND role = "student"');
    $user->execute([$id]);
    $u = $user->fetch();
    if ($u) {
        $pdo->prepare('DELETE FROM students WHERE email = ?')->execute([$u['email']]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        // Notify student of rejection
        $email_body = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;'>
              <div style='background:#800000;padding:24px 32px;border-radius:10px 10px 0 0;text-align:center;'>
                <h1 style='color:#fff;margin:0;font-size:1.6rem;letter-spacing:1px;'>🎓 SIMS</h1>
                <p style='color:#f5c6c6;margin:6px 0 0;font-size:.9rem;'>Account Notification</p>
              </div>
              <div style='background:#fff8f0;padding:32px;border-radius:0 0 10px 10px;border:1px solid #f0dcd0;'>
                <h2 style='color:#800000;margin:0 0 12px;'>Registration Declined</h2>
                <p style='color:#444;'>Hello <strong>" . htmlspecialchars($u['name']) . "</strong>,</p>
                <p style='color:#444;'>We regret to inform you that your registration for SIMS has been declined.</p>
                <p style='color:#444;'>Please contact the school administration if you believe this is a mistake.</p>
              </div>
            </div>";
        send_mail($u['email'], 'SIMS — Registration Declined', $email_body);
    }
    header('Location: /sims/admin/dashboard.php?tab=pending'); exit;
}
if (isset($_GET['delete_teacher'])) {
    $id = (int)$_GET['delete_teacher'];
    $pdo->prepare('DELETE FROM users WHERE id = ? AND role = "teacher"')->execute([$id]);
    header('Location: /sims/admin/dashboard.php?tab=teachers'); exit;
}

// ── Queries ──
// Ensure gender column exists
if (!$pdo->query("SHOW COLUMNS FROM students LIKE 'gender'")->fetch()) {
    $pdo->exec("ALTER TABLE students ADD COLUMN gender ENUM('Male','Female','Other') DEFAULT NULL");
}
$students = $pdo->query('
    SELECT s.*, u.created_at AS registered_at
    FROM students s
    LEFT JOIN users u ON u.email = s.email AND u.role = "student"
    ORDER BY s.id DESC
')->fetchAll();
$pending  = $pdo->query('SELECT * FROM users WHERE role = "student" AND is_verified = 0 ORDER BY id DESC')->fetchAll();
$teachers = $pdo->query('SELECT * FROM users WHERE role = "teacher" ORDER BY id DESC')->fetchAll();

$tab = $_GET['tab'] ?? 'students';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard — SIMS</title>
  <link rel="stylesheet" href="/sims/assets/css/style.css">
  <style>
    body {
      background: #1a1a1a;
      display: block;
      padding: 0;
      min-height: 100vh;
      overflow-x: hidden;
    }
    .dash-sidebar {
      position: fixed; top: 0; left: 0;
      width: 240px; height: 100vh;
      background: #111111;
      border-right: 1px solid rgba(255,255,255,0.07);
      display: flex; flex-direction: column;
      padding: 2rem 1.25rem; gap: .5rem; z-index: 100;
    }
    .dash-sidebar .brand {
      display: flex; align-items: center; gap: .75rem; margin-bottom: 2rem;
    }
    .dash-sidebar .brand-icon {
      width: 40px; height: 40px; background: #800000; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.2rem; flex-shrink: 0;
    }
    .dash-sidebar .brand-name {
      font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.15rem;
      color: #ffffff; letter-spacing: -.03em;
    }
    .dash-sidebar .brand-sub {
      font-size: .7rem; color: #888; letter-spacing: .05em; text-transform: uppercase;
    }
    .nav-item {
      display: flex; align-items: center; gap: .65rem;
      padding: .6rem .9rem; border-radius: 8px;
      color: #aaa; text-decoration: none;
      font-size: .875rem; font-weight: 500; transition: all .2s;
    }
    .nav-item:hover { background: rgba(255,255,255,0.05); color: #fff; }
    .nav-item.active {
      background: rgba(128,0,0,0.25); color: #f47171;
      border-left: 3px solid #800000; padding-left: calc(.9rem - 3px);
    }
    .nav-icon { width: 18px; text-align: center; font-size: 1rem; }
    .nav-badge {
      margin-left: auto; background: #800000; color: #fff;
      font-size: .68rem; font-weight: 700; padding: .15rem .5rem;
      border-radius: 99px; min-width: 20px; text-align: center;
    }
    .nav-divider { height: 1px; background: rgba(255,255,255,0.06); margin: .75rem 0; }
    .sidebar-footer { margin-top: auto; }
    .sign-out-btn {
      display: flex; align-items: center; gap: .65rem;
      padding: .6rem .9rem; border-radius: 8px;
      color: #f87171; text-decoration: none;
      font-size: .875rem; font-weight: 500; transition: all .2s; width: 100%;
    }
    .sign-out-btn:hover { background: rgba(248,113,113,0.1); }

    .dash-main {
      margin-left: 240px; min-height: 100vh;
      display: flex; flex-direction: column;
    }
    .dash-topbar {
      display: flex; align-items: center; justify-content: space-between;
      padding: 1.25rem 2rem;
      border-bottom: 1px solid rgba(255,255,255,0.06);
      background: #1a1a1a; position: sticky; top: 0; z-index: 50;
    }
    .dash-topbar h1 {
      font-family: 'Outfit', sans-serif; font-size: 1.35rem;
      font-weight: 700; color: #ffffff; letter-spacing: -.03em;
    }
    .topbar-actions { display: flex; align-items: center; gap: .75rem; }
    .btn-add {
      display: inline-flex; align-items: center; gap: .4rem;
      padding: .55rem 1.1rem; background: #800000; color: #fff;
      font-family: 'Outfit', sans-serif; font-size: .875rem; font-weight: 600;
      border-radius: 8px; text-decoration: none;
      transition: background .2s, transform .2s;
    }
    .btn-add:hover { background: #a52a2a; transform: translateY(-1px); }

    .dash-body { padding: 2rem; flex: 1; }

    .stats-row {
      display: grid; grid-template-columns: repeat(4, 1fr);
      gap: 1rem; margin-bottom: 2rem;
    }
    .stat-card {
      background: #222222; border: 1px solid rgba(255,255,255,0.07);
      border-radius: 12px; padding: 1.25rem 1.5rem;
    }
    .stat-card .label {
      font-size: .72rem; font-weight: 600; letter-spacing: .08em;
      text-transform: uppercase; color: #888; margin-bottom: .4rem;
    }
    .stat-card .value {
      font-family: 'Outfit', sans-serif; font-size: 2rem; font-weight: 800; color: #fff;
    }
    .stat-card .value.red { color: #f47171; }
    .stat-card .value.green { color: #6fcf97; }
    .stat-card .value.amber { color: #fbbf24; }
    .stat-card .value.blue { color: #93c5fd; }

    .table-card {
      background: #222222; border: 1px solid rgba(255,255,255,0.07);
      border-radius: 12px; overflow: hidden; margin-bottom: 2rem;
    }
    .table-card-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 1.1rem 1.5rem;
      border-bottom: 1px solid rgba(255,255,255,0.07);
    }
    .table-card-header h2 {
      font-family: 'Outfit', sans-serif; font-size: 1rem; font-weight: 700;
      color: #fff; letter-spacing: -.02em;
      background: none; -webkit-text-fill-color: unset; margin: 0;
    }
    .record-count { font-size: .8rem; color: #666; }
    .table-card table { width: 100%; border-collapse: collapse; margin: 0; }
    .table-card th {
      background: #1e1e1e; color: #666; font-size: .7rem;
      letter-spacing: .1em; text-transform: uppercase;
      padding: .75rem 1.5rem;
      border-bottom: 1px solid rgba(255,255,255,0.06); font-weight: 700;
      text-align: left;
    }
    .table-card td {
      padding: .9rem 1.5rem; color: #ccc; font-size: .875rem;
      border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: middle;
    }
    .table-card tr:last-child td { border-bottom: none; }
    .table-card tbody tr:hover td { background: rgba(255,255,255,0.03); color: #fff; }
    .student-name { font-weight: 600; color: #fff; }
    .empty-state { text-align: center; padding: 3rem; color: #555; }
    .empty-state .empty-icon { font-size: 2.5rem; margin-bottom: .75rem; }
    .empty-state p { font-size: .9rem; }

    .action-btn {
      display: inline-flex; align-items: center; gap: .3rem;
      padding: .3rem .7rem; border-radius: 6px;
      font-size: .78rem; font-weight: 500; text-decoration: none;
      border: 1px solid transparent; transition: all .2s;
    }
    .action-btn-edit {
      color: #93c5fd; border-color: rgba(147,197,253,0.2); background: rgba(147,197,253,0.06);
    }
    .action-btn-edit:hover {
      background: rgba(147,197,253,0.15); border-color: rgba(147,197,253,0.4); color: #fff;
    }
    .action-btn-approve {
      color: #6fcf97; border-color: rgba(111,207,151,0.2); background: rgba(111,207,151,0.06);
    }
    .action-btn-approve:hover {
      background: rgba(111,207,151,0.15); border-color: rgba(111,207,151,0.4); color: #fff;
    }
    .action-btn-delete {
      color: #f87171; border-color: rgba(248,113,113,0.2); background: rgba(248,113,113,0.06);
    }
    .action-btn-delete:hover {
      background: rgba(248,113,113,0.15); border-color: rgba(248,113,113,0.4); color: #fff;
    }

    .chip { font-size: .72rem; }
    .chip-pending {
      background: rgba(251,191,36,0.14); color: #fbbf24;
      border: 1px solid rgba(251,191,36,0.3);
      display: inline-flex; align-items: center; gap: .3rem;
      padding: .2rem .65rem; border-radius: 99px;
      font-size: .72rem; font-weight: 600;
    }
    .chip-pending::before {
      content:''; width:6px; height:6px; border-radius:50%; background: #fbbf24;
    }

    @media (max-width: 900px) {
      .dash-sidebar { display: none; }
      .dash-main { margin-left: 0; }
      .stats-row { grid-template-columns: 1fr 1fr; }
    }

    /* ── Sort & Search ── */
    .table-toolbar {
      display: flex; align-items: center; gap: .75rem;
      padding: .85rem 1.5rem;
      border-bottom: 1px solid rgba(255,255,255,0.07);
      flex-wrap: wrap;
    }
    .search-input {
      flex: 1; min-width: 180px;
      padding: .5rem .9rem;
      background: #2a2a2a; border: 1px solid rgba(255,255,255,0.1);
      border-radius: 8px; color: #e0e0e0; font-size: .875rem;
      outline: none; transition: border-color .2s;
    }
    .search-input:focus { border-color: #800000; }
    .search-input::placeholder { color: #555; }
    .filter-select {
      padding: .5rem .85rem;
      background: #2a2a2a; border: 1px solid rgba(255,255,255,0.1);
      border-radius: 8px; color: #ccc; font-size: .875rem;
      outline: none; cursor: pointer; transition: border-color .2s;
    }
    .filter-select:focus { border-color: #800000; }
    .sort-count { font-size: .78rem; color: #555; margin-left: auto; white-space: nowrap; }
    th.sortable {
      cursor: pointer;
      user-select: none;
      white-space: nowrap;
      transition: color .15s;
    }
    th.sortable:hover { color: #ccc; }
    th.sortable.sort-asc  { color: #f47171; }
    th.sortable.sort-desc { color: #f47171; }
    th.sortable .sort-icon {
      display: inline-block;
      margin-left: 5px;
      opacity: .35;
      font-size: .65rem;
      vertical-align: middle;
      transition: opacity .15s;
    }
    th.sortable:hover .sort-icon { opacity: .7; }
    th.sortable.sort-asc  .sort-icon,
    th.sortable.sort-desc .sort-icon { opacity: 1; }
    .no-results { text-align: center; padding: 2.5rem; color: #555; display: none; }
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

  <a class="nav-item <?php echo $tab === 'students' ? 'active' : ''; ?>" href="/sims/admin/dashboard.php?tab=students">
    <span class="nav-icon">👥</span> All Students
  </a>
  <a class="nav-item <?php echo $tab === 'pending' ? 'active' : ''; ?>" href="/sims/admin/dashboard.php?tab=pending">
    <span class="nav-icon">⏳</span> Pending Approval
    <?php if (count($pending)): ?><span class="nav-badge"><?php echo count($pending); ?></span><?php endif; ?>
  </a>
  <a class="nav-item <?php echo $tab === 'teachers' ? 'active' : ''; ?>" href="/sims/admin/dashboard.php?tab=teachers">
    <span class="nav-icon">📚</span> Teachers
  </a>
  <a class="nav-item" href="/sims/admin/analytics.php">
    <span class="nav-icon">📊</span> Analytics
  </a>

  <div class="nav-divider"></div>

  <a class="nav-item" href="/sims/admin/add_student.php">
    <span class="nav-icon">➕</span> Add Student
  </a>
  <a class="nav-item" href="/sims/admin/add_teacher.php">
    <span class="nav-icon">➕</span> Add Teacher
  </a>

  <div class="nav-divider"></div>

  <div class="sidebar-footer">
    <a class="sign-out-btn" href="/sims/logout.php" onclick="return confirm('Are you sure you want to sign out?');">
      <span class="nav-icon">🚪</span> Sign Out
    </a>
  </div>
</nav>

<!-- Main -->
<div class="dash-main">

  <div class="dash-topbar">
    <h1>Admin Dashboard</h1>
    <div class="topbar-actions">
      <?php if ($tab === 'students'): ?>
        <a class="btn-add" href="/sims/admin/add_student.php">＋ Add Student</a>
      <?php elseif ($tab === 'teachers'): ?>
        <a class="btn-add" href="/sims/admin/add_teacher.php">＋ Add Teacher</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="dash-body">

    <!-- Stats -->
    <?php
      $totalStudents = count($students);
      $activeStudents = count(array_filter($students, fn($s) => $s['status'] === 'active'));
      $pendingCount = count($pending);
      $teacherCount = count($teachers);
    ?>
    <div class="stats-row">
      <div class="stat-card">
        <div class="label">Total Students</div>
        <div class="value"><?php echo $totalStudents; ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Active Students</div>
        <div class="value green"><?php echo $activeStudents; ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Pending Approval</div>
        <div class="value amber"><?php echo $pendingCount; ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Teachers</div>
        <div class="value blue"><?php echo $teacherCount; ?></div>
      </div>
    </div>

    <!-- ═══ TAB: STUDENTS ═══ -->
    <?php if ($tab === 'students'): ?>
    <div class="table-card">
      <div class="table-card-header">
        <h2>All Students</h2>
        <span class="record-count" id="stu-count"><?php echo $totalStudents; ?> record<?php echo $totalStudents !== 1 ? 's' : ''; ?></span>
      </div>
      <!-- Search + Filter toolbar -->
      <div class="table-toolbar">
        <input class="search-input" id="stu-search" type="text" placeholder="🔍  Search name, email, ID…" oninput="filterStudents()">
        <select class="filter-select" id="stu-status-filter" onchange="filterStudents()">
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
          <option value="pending">Pending</option>
        </select>
        <select class="filter-select" id="stu-date-sort" onchange="applyDateSort()">
          <option value="">Sort by Date…</option>
          <option value="latest">📅 Latest First</option>
          <option value="earliest">📅 Earliest First</option>
        </select>
        <span class="sort-count" id="stu-visible-count"></span>
      </div>
      <table id="students-table">
        <thead>
          <tr>
            <th style="width:40px">#</th>
            <th class="sortable" data-col="1" data-type="text">Student ID <span class="sort-icon">⇅</span></th>
            <th class="sortable" data-col="2" data-type="text">Name <span class="sort-icon">⇅</span></th>
            <th class="sortable" data-col="3" data-type="text">Email <span class="sort-icon">⇅</span></th>
            <th class="sortable" data-col="4" data-type="text">Status <span class="sort-icon">⇅</span></th>
            <th class="sortable" data-col="5" data-type="date" id="th-date">Date Added <span class="sort-icon">⇅</span></th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="students-tbody">
          <?php if (empty($students)): ?>
          <tr><td colspan="6"><div class="empty-state"><div class="empty-icon">📭</div><p>No student records found.</p></div></td></tr>
          <?php else: ?>
          <?php $idx = 1; foreach ($students as $s): ?>
          <tr data-date="<?php echo $s['registered_at'] ? strtotime($s['registered_at']) : $s['id']; ?>">
            <td><?php echo $idx++; ?></td>
            <td><?php echo htmlspecialchars($s['student_id'] ?: '—'); ?></td>
            <td><div class="student-name"><?php echo htmlspecialchars($s['first_name'].' '.$s['last_name']); ?></div></td>
            <td><?php echo htmlspecialchars($s['email']); ?></td>
            <td>
              <?php $status = $s['status'] ?? ''; ?>
              <span class="chip chip-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status); ?></span>
            </td>
            <td>
              <small style="color:#666;">
                <?php echo $s['registered_at'] ? date('M d, Y', strtotime($s['registered_at'])) : '—'; ?>
              </small>
            </td>
            <td>
              <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <a class="action-btn action-btn-edit" href="/sims/admin/edit_student.php?id=<?php echo $s['id']; ?>">✏️ Edit</a>
                <a class="action-btn action-btn-delete" href="/sims/admin/dashboard.php?delete_student=<?php echo $s['id']; ?>&tab=students"
                   onclick="return confirm('Permanently delete this student?')">🗑 Delete</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
      <div class="no-results" id="no-results">😶 No students match your search.</div>
    </div>

    <!-- ═══ TAB: PENDING ═══ -->
    <?php elseif ($tab === 'pending'): ?>
    <div class="table-card">
      <div class="table-card-header">
        <h2>Pending Student Approvals</h2>
        <span class="record-count"><?php echo $pendingCount; ?> pending</span>
      </div>
      <table>
        <thead>
          <tr><th>#</th><th>Name</th><th>Email</th><th>Registered</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (empty($pending)): ?>
          <tr><td colspan="6"><div class="empty-state"><div class="empty-icon">✅</div><p>No pending approvals. All caught up!</p></div></td></tr>
          <?php else: ?>
          <?php $idx = 1; foreach ($pending as $p): ?>
          <tr>
            <td><?php echo $idx++; ?></td>
            <td><div class="student-name"><?php echo htmlspecialchars($p['name']); ?></div></td>
            <td><?php echo htmlspecialchars($p['email']); ?></td>
            <td><?php echo date('M d, Y', strtotime($p['created_at'] ?? 'now')); ?></td>
            <td><span class="chip-pending">Pending</span></td>
            <td>
              <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <a class="action-btn action-btn-approve" href="/sims/admin/dashboard.php?approve=<?php echo $p['id']; ?>&tab=pending"
                   onclick="return confirm('Approve this student?')">✅ Approve</a>
                <a class="action-btn action-btn-delete" href="/sims/admin/dashboard.php?reject=<?php echo $p['id']; ?>&tab=pending"
                   onclick="return confirm('Reject and delete this registration?')">❌ Reject</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- ═══ TAB: TEACHERS ═══ -->
    <?php elseif ($tab === 'teachers'): ?>
    <div class="table-card">
      <div class="table-card-header">
        <h2>All Teachers</h2>
        <span class="record-count"><?php echo $teacherCount; ?> teacher<?php echo $teacherCount !== 1 ? 's' : ''; ?></span>
      </div>
      <table>
        <thead>
          <tr><th>#</th><th>Name</th><th>Email</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (empty($teachers)): ?>
          <tr><td colspan="4"><div class="empty-state"><div class="empty-icon">📭</div><p>No teacher accounts yet. Click "Add Teacher" to create one.</p></div></td></tr>
          <?php else: ?>
          <?php $idx = 1; foreach ($teachers as $t): ?>
          <tr>
            <td><?php echo $idx++; ?></td>
            <td><div class="student-name"><?php echo htmlspecialchars($t['name']); ?></div></td>
            <td><?php echo htmlspecialchars($t['email']); ?></td>
            <td>
              <a class="action-btn action-btn-delete" href="/sims/admin/dashboard.php?delete_teacher=<?php echo $t['id']; ?>&tab=teachers"
                 onclick="return confirm('Delete this teacher account?')">🗑 Delete</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  </div>
</div>

</body>

<script>
// ── Student Table: Sort + Search/Filter ──────────────────────────────────────
(function () {
  const tbody    = document.getElementById('students-tbody');
  const searchEl = document.getElementById('stu-search');
  const statusEl = document.getElementById('stu-status-filter');
  const dateSel  = document.getElementById('stu-date-sort');
  const noResults= document.getElementById('no-results');
  const countEl  = document.getElementById('stu-visible-count');
  const headers  = document.querySelectorAll('#students-table th.sortable');

  let sortCol = -1, sortAsc = true;

  // ── Column header sort ──
  headers.forEach(th => {
    th.addEventListener('click', () => {
      const col  = parseInt(th.dataset.col);
      const type = th.dataset.type || 'text';

      if (sortCol === col) { sortAsc = !sortAsc; } else { sortCol = col; sortAsc = true; }

      // Reset all icons, highlight active
      headers.forEach(h => {
        h.classList.remove('sort-asc', 'sort-desc');
        h.querySelector('.sort-icon').textContent = '⇅';
      });
      th.classList.add(sortAsc ? 'sort-asc' : 'sort-desc');
      th.querySelector('.sort-icon').textContent = sortAsc ? '▲' : '▼';

      // Reset date dropdown when column sort is used
      if (dateSel) dateSel.value = '';

      const all = getDataRows();
      all.sort((a, b) => {
        let aVal, bVal;
        if (type === 'date') {
          aVal = parseInt(a.dataset.date || '0');
          bVal = parseInt(b.dataset.date || '0');
          return sortAsc ? aVal - bVal : bVal - aVal;
        } else {
          aVal = (a.cells[col]?.textContent || '').trim().toLowerCase();
          bVal = (b.cells[col]?.textContent || '').trim().toLowerCase();
          return sortAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
        }
      });

      all.forEach(r => tbody.appendChild(r));
      reNumber();
      filterStudents();
    });
  });

  // ── Date dropdown sort (Earliest / Latest) ──
  window.applyDateSort = function () {
    const val = dateSel?.value;
    if (!val) return;

    // Clear column sort indicators
    headers.forEach(h => {
      h.classList.remove('sort-asc', 'sort-desc');
      h.querySelector('.sort-icon').textContent = '⇅';
    });
    sortCol = -1;

    // Highlight the Date Added column header
    const thDate = document.getElementById('th-date');
    if (thDate) {
      thDate.classList.add(val === 'latest' ? 'sort-desc' : 'sort-asc');
      thDate.querySelector('.sort-icon').textContent = val === 'latest' ? '▼' : '▲';
    }

    const all = getDataRows();
    all.sort((a, b) => {
      const aTs = parseInt(a.dataset.date || '0');
      const bTs = parseInt(b.dataset.date || '0');
      return val === 'latest' ? bTs - aTs : aTs - bTs;
    });

    all.forEach(r => tbody.appendChild(r));
    reNumber();
    filterStudents();
  };

  // ── Search + status filter ──
  window.filterStudents = function () {
    const q      = (searchEl?.value || '').toLowerCase().trim();
    const status = (statusEl?.value || '').toLowerCase();
    const rows   = tbody.querySelectorAll('tr');
    let visible  = 0;

    rows.forEach(row => {
      if (row.querySelector('[colspan]')) return;
      const text      = row.textContent.toLowerCase();
      const chipEl    = row.querySelector('.chip');
      const rowStatus = chipEl ? chipEl.textContent.toLowerCase().trim() : '';

      const matchQ      = !q      || text.includes(q);
      const matchStatus = !status || rowStatus === status;

      if (matchQ && matchStatus) {
        row.style.display = '';
        visible++;
      } else {
        row.style.display = 'none';
      }
    });

    noResults.style.display = visible === 0 ? 'block' : 'none';
    const total = getDataRows().length;
    countEl.textContent = visible < total ? `${visible} of ${total} shown` : '';
    reNumber();
  };

  function getDataRows() {
    return Array.from(tbody.querySelectorAll('tr')).filter(r => !r.querySelector('[colspan]'));
  }

  function reNumber() {
    let i = 1;
    tbody.querySelectorAll('tr').forEach(row => {
      if (row.style.display === 'none' || row.querySelector('[colspan]')) return;
      row.cells[0].textContent = i++;
    });
  }
})();
</script>
</html>

