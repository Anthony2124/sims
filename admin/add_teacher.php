<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'Email already registered.';
        } else {
            $ins = $pdo->prepare('INSERT INTO users (name, email, password, role, is_verified) VALUES (?, ?, ?, ?, 1)');
            $ins->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'teacher']);
            $success = 'Teacher account created successfully!';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Teacher — SIMS</title>
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
      width: 240px; height: 100vh; background: #111111;
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
    .nav-divider { height: 1px; background: rgba(255,255,255,0.06); margin: .75rem 0; }
    .sidebar-footer { margin-top: auto; }
    .sign-out-btn {
      display: flex; align-items: center; gap: .65rem;
      padding: .6rem .9rem; border-radius: 8px;
      color: #f87171; text-decoration: none;
      font-size: .875rem; font-weight: 500; transition: all .2s; width: 100%;
    }
    .sign-out-btn:hover { background: rgba(248,113,113,0.1); }

    .dash-main { margin-left: 240px; min-height: 100vh; display: flex; flex-direction: column; }
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
    .dash-body { padding: 2rem; flex: 1; }

    .form-card {
      background: #222222; border: 1px solid rgba(255,255,255,0.07);
      border-radius: 12px; padding: 2rem 2.5rem; max-width: 520px;
    }
    .form-card h2 {
      font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700;
      color: #fff; margin-bottom: 1.5rem;
      background: none; -webkit-text-fill-color: unset; text-align: left;
    }
    .form-card label {
      color: #999; margin-top: 1rem;
    }
    .form-card input {
      background: #1a1a1a !important;
      border-color: rgba(255,255,255,0.1) !important;
      color: #eee !important;
    }
    .form-card input:focus {
      border-color: #800000 !important;
      box-shadow: 0 0 0 3px rgba(128,0,0,0.3) !important;
    }
    .form-card input::placeholder { color: #555 !important; }
    .form-card button {
      width: 100%; margin-top: 1.5rem;
    }
    .form-card .error {
      background: rgba(248,113,113,0.1); border-color: rgba(248,113,113,0.3);
    }
    .form-card .success {
      background: rgba(111,207,151,0.1); border-color: rgba(111,207,151,0.3);
    }

    .back-link {
      display: inline-flex; align-items: center; gap: .4rem;
      margin-top: 1.25rem; color: #888; text-decoration: none;
      font-size: .85rem; transition: color .2s;
    }
    .back-link:hover { color: #fff; }

    @media (max-width: 900px) {
      .dash-sidebar { display: none; }
      .dash-main { margin-left: 0; }
    }
  </style>
</head>
<body>

<nav class="dash-sidebar">
  <div class="brand">
    <div class="brand-icon">🛡️</div>
    <div>
      <div class="brand-name">SIMS</div>
      <div class="brand-sub">Admin Dashboard</div>
    </div>
  </div>

  <a class="nav-item" href="/sims/admin/dashboard.php?tab=students">
    <span class="nav-icon">👥</span> All Students
  </a>
  <a class="nav-item" href="/sims/admin/dashboard.php?tab=pending">
    <span class="nav-icon">⏳</span> Pending Approval
  </a>
  <a class="nav-item" href="/sims/admin/dashboard.php?tab=teachers">
    <span class="nav-icon">📚</span> Teachers
  </a>

  <div class="nav-divider"></div>

  <a class="nav-item" href="/sims/admin/add_student.php">
    <span class="nav-icon">➕</span> Add Student
  </a>
  <a class="nav-item active" href="/sims/admin/add_teacher.php">
    <span class="nav-icon">➕</span> Add Teacher
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
    <h1>Add Teacher Account</h1>
  </div>

  <div class="dash-body">
    <div class="form-card">
      <h2>New Teacher</h2>

      <?php if ($error): ?><p class="error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
      <?php if ($success): ?><p class="success"><?php echo htmlspecialchars($success); ?></p><?php endif; ?>

      <form method="post">
        <label>Full Name</label>
        <input name="name" placeholder="e.g. Maria Santos" required>

        <label>Email Address</label>
        <input type="email" name="email" placeholder="teacher@school.edu" required>

        <label>Password</label>
        <input type="password" name="password" placeholder="Min. 6 characters" required>

        <button type="submit">Create Teacher Account →</button>
      </form>

      <a class="back-link" href="/sims/admin/dashboard.php?tab=teachers">← Back to Teachers</a>
    </div>
  </div>
</div>

</body>
</html>
