<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');
require_once __DIR__ . '/../includes/db.php';
if (isset($_GET['soft_delete'])) {
    $id = (int)$_GET['soft_delete'];
    $stmt = $pdo->prepare('UPDATE students SET status = "inactive", deleted_at = NOW() WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: /sims/teacher/list_students.php');
    exit;
}
$stmt = $pdo->query('SELECT * FROM students ORDER BY id DESC');
$students = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Students — Teacher · SIMS</title>
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
      position: fixed;
      top: 0; left: 0;
      width: 240px;
      height: 100vh;
      background: #111111;
      border-right: 1px solid rgba(255,255,255,0.07);
      display: flex;
      flex-direction: column;
      padding: 2rem 1.25rem;
      gap: .5rem;
      z-index: 100;
    }
    .dash-sidebar .brand {
      display: flex;
      align-items: center;
      gap: .75rem;
      margin-bottom: 2rem;
    }
    .dash-sidebar .brand-icon {
      width: 40px; height: 40px;
      background: #800000;
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.2rem;
      flex-shrink: 0;
    }
    .dash-sidebar .brand-name {
      font-family: 'Outfit', sans-serif;
      font-weight: 800;
      font-size: 1.15rem;
      color: #ffffff;
      letter-spacing: -.03em;
    }
    .dash-sidebar .brand-sub {
      font-size: .7rem;
      color: #888;
      letter-spacing: .05em;
      text-transform: uppercase;
    }
    .nav-item {
      display: flex;
      align-items: center;
      gap: .65rem;
      padding: .6rem .9rem;
      border-radius: 8px;
      color: #aaa;
      text-decoration: none;
      font-size: .875rem;
      font-weight: 500;
      transition: all .2s;
    }
    .nav-item:hover, .nav-item.active {
      background: rgba(255,255,255,0.05);
      color: #fff;
    }
    .nav-item.active {
      background: rgba(128,0,0,0.25);
      color: #f47171;
      border-left: 3px solid #800000;
      padding-left: calc(.9rem - 3px);
    }
    .nav-icon { width: 18px; text-align: center; font-size: 1rem; }
    .nav-divider { height: 1px; background: rgba(255,255,255,0.06); margin: .75rem 0; }
    .sidebar-footer { margin-top: auto; }
    .sign-out-btn {
      display: flex;
      align-items: center;
      gap: .65rem;
      padding: .6rem .9rem;
      border-radius: 8px;
      color: #f87171;
      text-decoration: none;
      font-size: .875rem;
      font-weight: 500;
      transition: all .2s;
      width: 100%;
    }
    .sign-out-btn:hover { background: rgba(248,113,113,0.1); }
    .dash-main {
      margin-left: 240px;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .dash-topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.25rem 2rem;
      border-bottom: 1px solid rgba(255,255,255,0.06);
      background: #1a1a1a;
      position: sticky;
      top: 0;
      z-index: 50;
    }
    .dash-topbar h1 {
      font-family: 'Outfit', sans-serif;
      font-size: 1.35rem;
      font-weight: 700;
      color: #ffffff;
      letter-spacing: -.03em;
    }
    .dash-body { padding: 2rem; flex: 1; }
    .stats-row {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1rem;
      margin-bottom: 2rem;
    }
    .stat-card {
      background: #222222;
      border: 1px solid rgba(255,255,255,0.07);
      border-radius: 12px;
      padding: 1.25rem 1.5rem;
    }
    .stat-card .label {
      font-size: .72rem;
      font-weight: 600;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: #888;
      margin-bottom: .4rem;
    }
    .stat-card .value {
      font-family: 'Outfit', sans-serif;
      font-size: 2rem;
      font-weight: 800;
      color: #fff;
    }
    .stat-card .value.red { color: #f47171; }
    .stat-card .value.green { color: #6fcf97; }
    .table-card {
      background: #222222;
      border: 1px solid rgba(255,255,255,0.07);
      border-radius: 12px;
      overflow: hidden;
    }
    .table-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.1rem 1.5rem;
      border-bottom: 1px solid rgba(255,255,255,0.07);
    }
    .table-card-header h2 {
      font-family: 'Outfit', sans-serif;
      font-size: 1rem;
      font-weight: 700;
      color: #fff;
      letter-spacing: -.02em;
      background: none;
      -webkit-text-fill-color: unset;
      margin: 0;
    }
    .record-count { font-size: .8rem; color: #666; }
    .table-card table { width: 100%; border-collapse: collapse; margin: 0; }
    .table-card th {
      background: #1e1e1e;
      color: #666;
      font-size: .7rem;
      letter-spacing: .1em;
      text-transform: uppercase;
      padding: .75rem 1.5rem;
      border-bottom: 1px solid rgba(255,255,255,0.06);
      font-weight: 700;
    }
    .table-card td {
      padding: .9rem 1.5rem;
      color: #ccc;
      font-size: .875rem;
      border-bottom: 1px solid rgba(255,255,255,0.04);
      vertical-align: middle;
    }
    .table-card tr:last-child td { border-bottom: none; }
    .table-card tbody tr:hover td { background: rgba(255,255,255,0.03); color: #fff; }
    .student-name { font-weight: 600; color: #fff; }
    .empty-state { text-align: center; padding: 3rem; color: #555; }
    .empty-state .empty-icon { font-size: 2.5rem; margin-bottom: .75rem; }
    .empty-state p { font-size: .9rem; }
    .action-btn {
      display: inline-flex;
      align-items: center;
      gap: .3rem;
      padding: .3rem .7rem;
      border-radius: 6px;
      font-size: .78rem;
      font-weight: 500;
      text-decoration: none;
      border: 1px solid transparent;
      transition: all .2s;
    }
    .action-btn-edit {
      color: #93c5fd;
      border-color: rgba(147,197,253,0.2);
      background: rgba(147,197,253,0.06);
    }
    .action-btn-edit:hover {
      background: rgba(147,197,253,0.15);
      border-color: rgba(147,197,253,0.4);
      color: #fff;
    }
    .action-btn-deactivate {
      color: #f87171;
      border-color: rgba(248,113,113,0.2);
      background: rgba(248,113,113,0.06);
    }
    .action-btn-deactivate:hover {
      background: rgba(248,113,113,0.15);
      border-color: rgba(248,113,113,0.4);
      color: #fff;
    }
    .chip { font-size: .72rem; }
    @media (max-width: 900px) {
      .dash-sidebar { display: none; }
      .dash-main { margin-left: 0; }
      .stats-row { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>

<nav class="dash-sidebar">
  <div class="brand">
    <div class="brand-icon">📚</div>
    <div>
      <div class="brand-name">SIMS</div>
      <div class="brand-sub">Teacher Portal</div>
    </div>
  </div>

  <a class="nav-item active" href="/sims/teacher/list_students.php">
    <span class="nav-icon">👥</span> Student Records
  </a>

  <div class="nav-divider"></div>

  <div class="sidebar-footer">
    <a class="sign-out-btn" href="/sims/logout.php" onclick="return confirm('Are you sure you want to sign out?');">
      <span class="nav-icon">🚪</span> Sign Out
    </a>
  </div>
</nav>

<div class="dash-main">

  <div class="dash-topbar">
    <h1>Student Records</h1>
  </div>

  <div class="dash-body">

    <?php
      $total    = count($students);
      $active   = count(array_filter($students, fn($s) => $s['status'] === 'active'));
      $inactive = $total - $active;
    ?>
    <div class="stats-row">
      <div class="stat-card">
        <div class="label">Total Students</div>
        <div class="value"><?php echo $total; ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Active</div>
        <div class="value green"><?php echo $active; ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Inactive</div>
        <div class="value red"><?php echo $inactive; ?></div>
      </div>
    </div>

    <div class="table-card">
      <div class="table-card-header">
        <h2>All Students</h2>
        <span class="record-count"><?php echo $total; ?> record<?php echo $total !== 1 ? 's' : ''; ?></span>
      </div>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Student ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($students)): ?>
          <tr>
            <td colspan="6">
              <div class="empty-state">
                <div class="empty-icon">📭</div>
                <p>No student records found.</p>
              </div>
            </td>
          </tr>
          <?php else: ?>
          <?php foreach ($students as $s): ?>
          <tr>
            <td><?php echo $s['id']; ?></td>
            <td><?php echo htmlspecialchars($s['student_id'] ?: '—'); ?></td>
            <td>
              <div class="student-name"><?php echo htmlspecialchars($s['first_name'].' '.$s['last_name']); ?></div>
            </td>
            <td><?php echo htmlspecialchars($s['email']); ?></td>
            <td>
              <?php $status = htmlspecialchars($s['status']); ?>
              <span class="chip chip-<?php echo $status; ?>"><?php echo $status; ?></span>
            </td>
            <td>
              <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <a class="action-btn action-btn-edit"
                   href="/sims/teacher/edit_student.php?id=<?php echo $s['id']; ?>">✏️ Edit</a>
                <a class="action-btn action-btn-deactivate"
                   href="/sims/teacher/list_students.php?soft_delete=<?php echo $s['id']; ?>"
                   onclick="return confirm('Mark this student as inactive?')">🚫 Deactivate</a>
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

</body>
</html>

