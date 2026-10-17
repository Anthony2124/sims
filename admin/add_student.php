<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

// Ensure required columns exist
try {
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'must_change_password'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'is_verified'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0");
    }
} catch (Exception $e) {}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $email      = trim($_POST['email']      ?? '');
    $phone      = trim($_POST['phone']      ?? '');
    $address    = trim($_POST['address']    ?? '');
    $course     = trim($_POST['course']     ?? '');
    $year_level = trim($_POST['year_level'] ?? '');
    $birthdate  = trim($_POST['birthdate']  ?? '') ?: null;
    $password   = $_POST['password']  ?? '';
    $password2  = $_POST['password2'] ?? '';

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = 'First name, last name, and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        // Check duplicate email in users
        $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $dup->execute([$email]);
        if ($dup->fetch()) {
            $error = 'An account with that email already exists.';
        } else {
            $name   = trim($first_name . ' ' . $last_name);
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // Insert into users (is_verified=1 — admin already verified, must_change_password=1)
            $pdo->prepare('INSERT INTO users (name, email, password, role, is_verified, must_change_password) VALUES (?, ?, ?, "student", 1, 1)')
                ->execute([$name, $email, $hashed]);

            // Insert into students
            $pdo->prepare('INSERT INTO students (student_id, first_name, last_name, email, phone, address, course, year_level, birthdate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$student_id, $first_name, $last_name, $email, $phone, $address, $course, $year_level, $birthdate]);

            // Send welcome email with temp password info
            $login_url = rtrim(SITE_URL, '/') . BASE_URL . '/select_role.php?role=student';
            $email_body = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;'>
              <div style='background:#800000;padding:24px 32px;border-radius:10px 10px 0 0;text-align:center;'>
                <h1 style='color:#fff;margin:0;font-size:1.6rem;letter-spacing:1px;'>🎓 SIMS</h1>
                <p style='color:#f5c6c6;margin:6px 0 0;font-size:.9rem;'>Student Information Management System</p>
              </div>
              <div style='background:#fff8f0;padding:32px;border-radius:0 0 10px 10px;border:1px solid #f0dcd0;'>
                <h2 style='color:#800000;margin:0 0 12px;'>Welcome to SIMS! 🎉</h2>
                <p style='color:#444;'>Hi <strong>" . htmlspecialchars($name) . "</strong>,</p>
                <p style='color:#444;'>Your student account has been created by the administrator. Here are your login details:</p>
                <table style='margin:1.25rem 0;border-collapse:collapse;'>
                  <tr><td style='padding:6px 12px;font-weight:bold;color:#555;'>Email:</td><td style='padding:6px 12px;'>" . htmlspecialchars($email) . "</td></tr>
                  <tr><td style='padding:6px 12px;font-weight:bold;color:#555;'>Temp Password:</td><td style='padding:6px 12px;font-family:monospace;font-size:1.1rem;'>" . htmlspecialchars($password) . "</td></tr>
                </table>
                <p style='color:#c0392b;font-weight:bold;'>⚠ You will be required to change your password on first login.</p>
                <p style='text-align:center;margin:24px 0;'>
                  <a href='" . $login_url . "' style='display:inline-block;padding:12px 28px;background:#800000;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;'>Log in to SIMS →</a>
                </p>
              </div>
            </div>";
            send_mail($email, 'SIMS — Your Student Account Has Been Created', $email_body);

            header('Location: /sims/admin/dashboard.php?tab=students&created=1');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Student — SIMS Admin</title>
  <link rel="stylesheet" href="/sims/assets/css/style.css">
  <style>
    body { background: #1a1a1a; display: block; padding: 0; }
    .dash-sidebar {
      position: fixed; top: 0; left: 0; width: 240px; height: 100vh;
      background: #111; border-right: 1px solid rgba(255,255,255,0.07);
      display: flex; flex-direction: column; padding: 2rem 1.25rem; gap: .5rem; z-index: 100;
    }
    .dash-sidebar .brand { display: flex; align-items: center; gap: .75rem; margin-bottom: 2rem; }
    .dash-sidebar .brand-icon { width: 40px; height: 40px; background: #800000; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    .dash-sidebar .brand-name { font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.15rem; color: #fff; letter-spacing: -.03em; }
    .dash-sidebar .brand-sub { font-size: .7rem; color: #888; letter-spacing: .05em; text-transform: uppercase; }
    .nav-item { display: flex; align-items: center; gap: .65rem; padding: .6rem .9rem; border-radius: 8px; color: #aaa; text-decoration: none; font-size: .875rem; font-weight: 500; transition: all .2s; }
    .nav-item:hover { background: rgba(255,255,255,0.05); color: #fff; }
    .nav-icon { width: 18px; text-align: center; font-size: 1rem; }
    .sidebar-footer { margin-top: auto; }
    .sign-out-btn { display: flex; align-items: center; gap: .65rem; padding: .6rem .9rem; border-radius: 8px; color: #f87171; text-decoration: none; font-size: .875rem; font-weight: 500; transition: all .2s; width: 100%; }
    .sign-out-btn:hover { background: rgba(248,113,113,0.1); }
    .dash-main { margin-left: 240px; min-height: 100vh; display: flex; flex-direction: column; }
    .dash-topbar { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 2rem; border-bottom: 1px solid rgba(255,255,255,0.06); background: #1a1a1a; position: sticky; top: 0; z-index: 50; }
    .dash-topbar h1 { font-family: 'Outfit', sans-serif; font-size: 1.35rem; font-weight: 700; color: #fff; letter-spacing: -.03em; }
    .dash-body { padding: 2rem; flex: 1; }

    .form-card {
      background: #222; border: 1px solid rgba(255,255,255,0.07);
      border-radius: 14px; padding: 2rem 2.5rem; max-width: 700px;
    }
    .form-card h2 { font-family: 'Outfit', sans-serif; font-size: 1.1rem; font-weight: 700; color: #fff; margin-bottom: 1.5rem; letter-spacing: -.02em; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem 1.5rem; }
    .form-grid .full { grid-column: 1 / -1; }
    .form-group { display: flex; flex-direction: column; gap: .4rem; }
    .form-group label { font-size: .72rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: #888; }
    .form-group input, .form-group select {
      padding: .65rem .9rem; background: #2c2c2c; border: 1px solid rgba(255,255,255,0.12);
      border-radius: 8px; color: #e0e0e0; font-size: .9rem; font-family: 'Inter', sans-serif;
      outline: none; transition: border-color .2s;
    }
    .form-group input:focus, .form-group select:focus { border-color: #800000; }
    .form-group input::placeholder { color: #555; }
    .divider { grid-column: 1 / -1; border: none; border-top: 1px solid rgba(255,255,255,0.07); margin: .5rem 0; }
    .section-label { grid-column: 1 / -1; font-size: .72rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #800000; margin-top: .5rem; }
    .strength-bar { height: 3px; border-radius: 99px; background: rgba(255,255,255,0.1); margin-top: .4rem; overflow: hidden; }
    .strength-fill { height: 100%; border-radius: 99px; width: 0; transition: width .3s, background .3s; }
    .alert-error { padding: .75rem 1rem; background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.28); border-radius: 8px; color: #f87171; font-size: .875rem; margin-bottom: 1.25rem; }
    .notice { padding: .75rem 1rem; background: rgba(251,191,36,0.08); border: 1px solid rgba(251,191,36,0.25); border-radius: 8px; color: #fbbf24; font-size: .82rem; margin-bottom: 1.25rem; line-height: 1.55; }
    .btn-submit {
      margin-top: 1.5rem; padding: .8rem 2rem;
      background: #800000; color: #fff; border: none;
      border-radius: 9px; font-family: 'Outfit', sans-serif; font-weight: 700; font-size: .95rem;
      cursor: pointer; transition: background .2s, transform .15s;
    }
    .btn-submit:hover { background: #a52a2a; transform: translateY(-1px); }
    .back-link { color: #888; text-decoration: none; font-size: .875rem; display: inline-flex; align-items: center; gap: .4rem; margin-top: 1rem; }
    .back-link:hover { color: #fff; }
    @media (max-width: 900px) { .dash-sidebar { display: none; } .dash-main { margin-left: 0; } }
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
  <a class="nav-item" href="/sims/admin/dashboard.php?tab=students"><span class="nav-icon">👥</span> All Students</a>
  <a class="nav-item" href="/sims/admin/dashboard.php?tab=pending"><span class="nav-icon">⏳</span> Pending Approval</a>
  <a class="nav-item" href="/sims/admin/dashboard.php?tab=teachers"><span class="nav-icon">📚</span> Teachers</a>
  <a class="nav-item" href="/sims/admin/analytics.php"><span class="nav-icon">📊</span> Analytics</a>
  <div class="sidebar-footer">
    <a class="sign-out-btn" href="/sims/logout.php" onclick="return confirm('Sign out?')"><span class="nav-icon">🚪</span> Sign Out</a>
  </div>
</nav>

<div class="dash-main">
  <div class="dash-topbar">
    <h1>Add Student</h1>
  </div>
  <div class="dash-body">
    <div class="form-card">
      <h2>👤 New Student Account</h2>

      <div class="notice">
        ⚠ The student will receive an email with their temporary password.<br>
        They will be required to <strong>change their password on first login</strong>.
      </div>

      <?php if ($error): ?>
        <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" class="form-grid">
        <div class="section-label">Student Info</div>

        <div class="form-group">
          <label>Student ID</label>
          <input name="student_id" placeholder="2024-00001" value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Course</label>
          <input name="course" placeholder="BS Computer Science" value="<?= htmlspecialchars($_POST['course'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>First Name <span style="color:#f87171">*</span></label>
          <input name="first_name" placeholder="Juan" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Last Name <span style="color:#f87171">*</span></label>
          <input name="last_name" placeholder="Dela Cruz" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
        </div>
        <div class="form-group full">
          <label>Email Address <span style="color:#f87171">*</span></label>
          <input type="email" name="email" placeholder="student@school.edu" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input name="phone" placeholder="+63 900 000 0000" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Year Level</label>
          <input name="year_level" placeholder="1st Year" value="<?= htmlspecialchars($_POST['year_level'] ?? '') ?>">
        </div>
        <div class="form-group full">
          <label>Address</label>
          <input name="address" placeholder="City, Province" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Birthdate</label>
          <input type="date" name="birthdate" value="<?= htmlspecialchars($_POST['birthdate'] ?? '') ?>">
        </div>

        <hr class="divider">
        <div class="section-label">Temporary Password</div>

        <div class="form-group">
          <label>Temporary Password <span style="color:#f87171">*</span></label>
          <input type="password" id="adm-pass" name="password" placeholder="Min. 6 characters" required autocomplete="new-password">
          <div class="strength-bar"><div class="strength-fill" id="adm-strength"></div></div>
        </div>
        <div class="form-group">
          <label>Confirm Password <span style="color:#f87171">*</span></label>
          <input type="password" name="password2" placeholder="Repeat password" required autocomplete="new-password">
        </div>

        <div class="full">
          <button type="submit" class="btn-submit">Create Student Account →</button>
        </div>
      </form>
      <a class="back-link" href="/sims/admin/dashboard.php?tab=students">← Back to dashboard</a>
    </div>
  </div>
</div>

<script>
  const passInput = document.getElementById('adm-pass');
  const fill = document.getElementById('adm-strength');
  if (passInput && fill) {
    passInput.addEventListener('input', () => {
      const v = passInput.value;
      let score = 0;
      if (v.length >= 6) score++;
      if (v.length >= 10) score++;
      if (/[A-Z]/.test(v)) score++;
      if (/[0-9]/.test(v)) score++;
      if (/[^A-Za-z0-9]/.test(v)) score++;
      fill.style.width = (score / 5 * 100) + '%';
      fill.style.background = ['#e05252','#e07a52','#e0c052','#6fcf97','#4caf7d'][score - 1] || '#555';
    });
  }
</script>
</body>
</html>
