<?php
require_once __DIR__ . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user'])) { header('Location: /sims'); exit; }

// Ensure password-related columns exist
try {
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'must_change_password'")->fetch())
        $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'password_reset_token'")->fetch())
        $pdo->exec("ALTER TABLE users ADD COLUMN password_reset_token VARCHAR(64) NULL DEFAULT NULL");
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'password_reset_expires'")->fetch())
        $pdo->exec("ALTER TABLE users ADD COLUMN password_reset_expires DATETIME NULL DEFAULT NULL");
} catch (Exception $e) { /* ignore */ }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email    = trim($_POST['email'] ?? '');
  $pass     = $_POST['password'] ?? '';
  $req_role = $_POST['role'] ?? 'student';

  $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
  $stmt->execute([$email]);
  $user = $stmt->fetch();

  if ($user && password_verify($pass, $user['password'])) {
    if ($user['role'] !== $req_role) {
      $error = 'Access denied: this account belongs to the <strong>' . htmlspecialchars($user['role']) . '</strong> portal.';
    } elseif ($user['role'] === 'student' && empty($user['is_verified'])) {
      $error = 'Your account is pending admin approval.';
    } else {
      $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
      // Force password change if admin-created account
      if (!empty($user['must_change_password']) && $user['role'] === 'student') {
        header('Location: /sims/student/change_password.php');
      } else {
        header('Location: /sims');
      }
      exit;
    }
  } else {
    $error = 'Invalid email or password.';
  }
}

$active_role = $_POST['role'] ?? $_GET['role'] ?? 'student';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SIMS — Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
      height: 100%;
      font-family: 'Inter', sans-serif;
    }

    body {
      display: flex;
      min-height: 100vh;
      background: #f5ede0;
    }

    /* ═══════════════════════════
       LEFT PANEL — deep red
    ═══════════════════════════ */
    .left-panel {
      width: 340px;
      flex-shrink: 0;
      background: #8b0000;
      display: flex;
      flex-direction: column;
      padding: 2.25rem 1.6rem;
      position: relative;
      overflow: hidden;
      z-index: 1;
    }

    /* Subtle decorative arcs on the left panel */
    .left-panel::before {
      content: '';
      position: absolute;
      bottom: -80px;
      right: -80px;
      width: 340px;
      height: 340px;
      border-radius: 50%;
      background: rgba(255,255,255,0.04);
      pointer-events: none;
    }
    .left-panel::after {
      content: '';
      position: absolute;
      bottom: -40px;
      right: -120px;
      width: 200px;
      height: 200px;
      border-radius: 50%;
      background: rgba(255,255,255,0.04);
      pointer-events: none;
    }

    .sims-logo {
      font-family: 'Outfit', sans-serif;
      font-weight: 800;
      font-size: 1.9rem;
      color: #fff;
      letter-spacing: -.04em;
      margin-bottom: 2.75rem;
      display: flex;
      align-items: center;
      gap: .5rem;
    }

    .sims-logo .logo-dot {
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: #f5ede0;
      margin-top: 2px;
    }

    .role-nav { display: flex; flex-direction: column; gap: .6rem; }

    .role-btn {
      display: flex;
      align-items: center;
      gap: .7rem;
      width: 100%;
      padding: .75rem 1.1rem;
      font-family: 'Outfit', sans-serif;
      font-weight: 600;
      font-size: .92rem;
      color: #3d1010;
      background: #f5ede0;
      border: none;
      border-radius: 9px;
      cursor: pointer;
      transition: all .22s ease;
      box-shadow: 0 2px 10px rgba(0,0,0,0.18);
      text-align: left;
      position: relative;
      overflow: hidden;
    }

    .role-btn::after {
      content: '';
      position: absolute;
      left: 0; top: 0; bottom: 0;
      width: 4px;
      background: #8b0000;
      border-radius: 0 3px 3px 0;
      opacity: 0;
      transition: opacity .2s;
    }

    .role-btn:hover {
      background: #fff;
      transform: translateX(5px);
      box-shadow: 0 4px 18px rgba(0,0,0,0.22);
    }

    .role-btn.active {
      background: #fff;
      color: #8b0000;
      transform: translateX(5px);
      box-shadow: 0 4px 20px rgba(0,0,0,0.25);
    }
    .role-btn.active::after { opacity: 1; }

    .role-btn .btn-icon {
      font-size: 1.1rem;
      width: 26px;
      text-align: center;
    }

    .left-footer {
      margin-top: auto;
      padding-top: 1.5rem;
      font-size: .73rem;
      color: rgba(255,255,255,0.38);
      line-height: 1.5;
    }

    /* ═══════════════════════════
       RIGHT PANEL — full beige
    ═══════════════════════════ */
    .right-panel {
      flex: 1;
      background: #f5ede0;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 3rem 4rem;
      position: relative;
      overflow: hidden;
    }

    /* ── Decorative background circles (the oval/circle the user drew) ── */
    .deco-ring {
      position: absolute;
      border-radius: 50%;
      pointer-events: none;
    }
    /* Large outer ring — far right */
    .deco-ring-1 {
      width: 520px; height: 520px;
      right: -140px; top: 50%;
      transform: translateY(-50%);
      border: 2px solid rgba(139,0,0,0.10);
    }
    /* Medium ring */
    .deco-ring-2 {
      width: 360px; height: 360px;
      right: -60px; top: 50%;
      transform: translateY(-50%);
      border: 1.5px solid rgba(139,0,0,0.08);
    }
    /* Small ring */
    .deco-ring-3 {
      width: 200px; height: 200px;
      right: 20px; top: 50%;
      transform: translateY(-50%);
      border: 1.5px solid rgba(139,0,0,0.07);
      background: rgba(139,0,0,0.025);
    }
    /* Top-left accent blob */
    .deco-blob-tl {
      position: absolute;
      width: 180px; height: 180px;
      top: -60px; right: 160px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(139,0,0,0.07) 0%, transparent 70%);
      pointer-events: none;
    }
    /* Bottom right accent */
    .deco-blob-br {
      position: absolute;
      width: 240px; height: 240px;
      bottom: -80px; right: 80px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(139,0,0,0.06) 0%, transparent 70%);
      pointer-events: none;
    }

    /* Floating stat chips (decorative) */
    .deco-chips {
      position: absolute;
      right: 60px;
      top: 50%;
      transform: translateY(-50%);
      display: flex;
      flex-direction: column;
      gap: 1rem;
      z-index: 1;
    }

    .deco-chip {
      background: #fff;
      border: 1px solid #e2ccb0;
      border-radius: 12px;
      padding: .7rem 1.1rem;
      display: flex;
      align-items: center;
      gap: .7rem;
      box-shadow: 0 4px 20px rgba(139,0,0,0.07);
      min-width: 170px;
      animation: float-chip 4s ease-in-out infinite;
    }

    .deco-chip:nth-child(2) { animation-delay: 1.3s; }
    .deco-chip:nth-child(3) { animation-delay: 2.6s; }

    @keyframes float-chip {
      0%, 100% { transform: translateY(0); }
      50%       { transform: translateY(-6px); }
    }

    .deco-chip .chip-icon {
      font-size: 1.4rem;
      width: 36px; height: 36px;
      border-radius: 9px;
      background: #f5ede0;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }

    .deco-chip .chip-text strong {
      display: block;
      font-family: 'Outfit', sans-serif;
      font-weight: 700;
      font-size: .95rem;
      color: #2c1a0e;
    }

    .deco-chip .chip-text span {
      font-size: .73rem;
      color: #9a7a58;
    }

    /* ═══════════════════════════
       LOGIN CARD
    ═══════════════════════════ */
    .login-wrap {
      position: relative;
      z-index: 2;
      width: 100%;
      max-width: 500px;
      flex-shrink: 0;
      margin-right: 240px; /* Offset to center it between the sidebar and the right decorative elements */
    }

    .login-card {
      background: #fff;
      border: 1.5px solid #dcc9ae;
      border-radius: 18px;
      padding: 3rem 3rem;
      box-shadow: 0 12px 50px rgba(139,0,0,0.11), 0 3px 10px rgba(0,0,0,0.06);
      animation: card-in .5s cubic-bezier(.22,1,.36,1) both;
    }

    @keyframes card-in {
      from { opacity:0; transform: translateY(24px) scale(.97); }
      to   { opacity:1; transform: translateY(0) scale(1); }
    }

    .role-badge {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      font-family: 'Outfit', sans-serif;
      font-size: .75rem;
      font-weight: 700;
      letter-spacing: .11em;
      text-transform: uppercase;
      color: #8b0000;
      background: rgba(139,0,0,0.07);
      border: 1px solid rgba(139,0,0,0.15);
      border-radius: 20px;
      padding: .35rem .85rem;
      margin-bottom: 1.5rem;
    }

    .login-card h2 {
      font-family: 'Outfit', sans-serif;
      font-size: 1.8rem;
      font-weight: 700;
      color: #2c1a0e;
      margin-bottom: .4rem;
      line-height: 1.2;
    }

    .login-card .subtitle {
      font-size: 1rem;
      color: #9a7a58;
      margin-bottom: 2rem;
    }

    .form-field { margin-top: 1.2rem; }

    .form-field label {
      display: block;
      font-size: .8rem;
      font-weight: 600;
      color: #6b4c2c;
      letter-spacing: .08em;
      text-transform: uppercase;
      margin-bottom: .45rem;
    }

    .field-wrap {
      position: relative;
    }

    .field-wrap .field-icon {
      position: absolute;
      left: 1.1rem; top: 50%;
      transform: translateY(-50%);
      font-size: 1.1rem;
      pointer-events: none;
      opacity: .55;
    }

    .form-field input {
      display: block;
      width: 100%;
      padding: .85rem 1.2rem .85rem 2.8rem;
      font-family: 'Inter', sans-serif;
      font-size: 1rem;
      color: #2c1a0e;
      background: #fdf8f3;
      border: 1.5px solid #d6c4a8;
      border-radius: 9px;
      outline: none;
      transition: border-color .2s, box-shadow .2s, background .2s;
    }

    .form-field input::placeholder { color: #c4a87a; }

    .form-field input:focus {
      border-color: #8b0000;
      background: #fff;
      box-shadow: 0 0 0 3px rgba(139,0,0,0.10);
    }

    .btn-login {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: .5rem;
      width: 100%;
      margin-top: 2rem;
      padding: 1rem 1.5rem;
      font-family: 'Outfit', sans-serif;
      font-weight: 700;
      font-size: 1.1rem;
      color: #fff;
      background: linear-gradient(135deg, #9b0000, #6b0000);
      border: none;
      border-radius: 10px;
      cursor: pointer;
      letter-spacing: .03em;
      transition: opacity .2s, transform .15s, box-shadow .2s;
      box-shadow: 0 5px 20px rgba(139,0,0,0.38);
    }

    .btn-login:hover {
      opacity: .9;
      transform: translateY(-2px);
      box-shadow: 0 10px 28px rgba(139,0,0,0.45);
    }

    .btn-login:active { transform: translateY(0); }

    .card-footer {
      margin-top: 1.5rem;
      text-align: center;
      font-size: .9rem;
      color: #9a7a58;
    }

    .card-footer a {
      color: #8b0000;
      font-weight: 600;
      text-decoration: none;
    }
    .card-footer a:hover { text-decoration: underline; }

    .alert-error {
      display: flex;
      align-items: flex-start;
      gap: .55rem;
      margin-bottom: 1.1rem;
      padding: .78rem 1rem;
      background: rgba(224,82,82,0.08);
      border: 1px solid rgba(224,82,82,0.28);
      border-radius: 9px;
      color: #b52b2b;
      font-size: .845rem;
      font-weight: 500;
      animation: shake .38s ease;
    }

    @keyframes shake {
      0%,100% { transform: translateX(0); }
      20%      { transform: translateX(-5px); }
      40%      { transform: translateX(5px); }
      60%      { transform: translateX(-3px); }
      80%      { transform: translateX(3px); }
    }

    /* ─── Responsive ─── */
    @media (max-width: 900px) {
      .deco-chips { display: none; }
      .right-panel { justify-content: center; padding: 2.5rem 1.5rem; }
      .login-wrap { margin-right: 0; }
    }

    @media (max-width: 680px) {
      body { flex-direction: column; }
      .left-panel {
        width: 100%; flex-direction: row; flex-wrap: wrap;
        align-items: center; padding: 1.1rem 1.25rem; gap: .5rem;
      }
      .sims-logo { margin-bottom: 0; margin-right: auto; font-size: 1.55rem; }
      .role-nav { flex-direction: row; gap: .4rem; }
      .role-btn { width: auto; padding: .55rem .9rem; font-size: .82rem; }
      .left-footer { display: none; }
      .right-panel { padding: 2rem 1rem; }
      .login-card { padding: 2rem 1.5rem; }
    }
  </style>
</head>
<body>

<!-- ════════ LEFT: Red Panel ════════ -->
<aside class="left-panel">
  <div class="sims-logo">
    <span>SIMS</span>
    <div class="logo-dot"></div>
  </div>

  <nav class="role-nav">
    <button class="role-btn <?= $active_role === 'student' ? 'active' : '' ?>"
            onclick="selectRole('student')" id="btn-student" type="button">
      <span class="btn-icon">🎒</span> Student
    </button>
    <button class="role-btn <?= $active_role === 'teacher' ? 'active' : '' ?>"
            onclick="selectRole('teacher')" id="btn-teacher" type="button">
      <span class="btn-icon">📚</span> Teacher
    </button>
    <button class="role-btn <?= $active_role === 'admin' ? 'active' : '' ?>"
            onclick="selectRole('admin')" id="btn-admin" type="button">
      <span class="btn-icon">🛡️</span> Admin
    </button>
  </nav>

  <div class="left-footer">
    Student Information<br>Management System<br>© <?= date('Y') ?> SIMS
  </div>
</aside>

<!-- ════════ RIGHT: Full Beige + Decorative Elements ════════ -->
<main class="right-panel">

  <!-- Background decorative rings (the circle the user drew) -->
  <div class="deco-ring deco-ring-1"></div>
  <div class="deco-ring deco-ring-2"></div>
  <div class="deco-ring deco-ring-3"></div>
  <div class="deco-blob-tl"></div>
  <div class="deco-blob-br"></div>

  <!-- Floating info chips on the far right -->
  <div class="deco-chips" aria-hidden="true">
    <div class="deco-chip">
      <div class="chip-icon">📊</div>
      <div class="chip-text">
        <strong>Dashboard</strong>
        <span>Real-time analytics</span>
      </div>
    </div>
    <div class="deco-chip">
      <div class="chip-icon">👥</div>
      <div class="chip-text">
        <strong>Students</strong>
        <span>Manage enrollments</span>
      </div>
    </div>
    <div class="deco-chip">
      <div class="chip-icon">📋</div>
      <div class="chip-text">
        <strong>Records</strong>
        <span>Track performance</span>
      </div>
    </div>
  </div>

  <!-- Login Card -->
  <div class="login-wrap">
    <div class="login-card">

      <div class="role-badge">
        <span id="role-icon-display"><?= $active_role === 'student' ? '🎒' : ($active_role === 'teacher' ? '📚' : '🛡️') ?></span>
        <span id="role-name-display"><?= ucfirst(htmlspecialchars($active_role)) ?></span> Portal
      </div>

      <h2>Welcome back</h2>
      <p class="subtitle">Sign in to continue to your dashboard.</p>

      <?php if ($error): ?>
        <div class="alert-error">⚠ <?= $error ?></div>
      <?php endif; ?>

      <form method="post" id="login-form">
        <input type="hidden" name="role" id="role-input" value="<?= htmlspecialchars($active_role) ?>">

        <div class="form-field">
          <label for="email">Username / Email</label>
          <div class="field-wrap">
            <span class="field-icon">✉</span>
            <input type="email" id="email" name="email"
                   placeholder="you@school.edu"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   required autofocus>
          </div>
        </div>

        <div class="form-field">
          <label for="password">Password</label>
          <div class="field-wrap">
            <span class="field-icon">🔒</span>
            <input type="password" id="password" name="password"
                   placeholder="••••••••" required>
          </div>
          <div id="forgot-link-wrap" style="text-align:right;margin-top:.45rem;<?= $active_role !== 'student' ? 'display:none;' : '' ?>">
            <a href="/sims/forgot_password.php" style="font-size:.8rem;color:#8b0000;text-decoration:none;font-weight:500;">Forgot password?</a>
          </div>
        </div>

        <button type="submit" class="btn-login">Login →</button>
      </form>

      <div class="card-footer" id="card-footer-links">
        <?php if ($active_role === 'student'): ?>
          <p>No account? <a href="/sims/register_student.php">Register here</a></p>
        <?php else: ?>
          <p>SIMS · Student Information Management System</p>
        <?php endif; ?>
      </div>

    </div>
  </div>

</main>

<script>
  const roles = {
    student: { icon: '🎒', label: 'Student', showRegister: true  },
    teacher: { icon: '📚', label: 'Teacher', showRegister: false },
    admin:   { icon: '🛡️', label: 'Admin',   showRegister: false },
  };

  function selectRole(role) {
    document.getElementById('role-input').value = role;

    const r = roles[role];
    document.getElementById('role-icon-display').textContent = r.icon;
    document.getElementById('role-name-display').textContent = r.label;

    ['student', 'teacher', 'admin'].forEach(k => {
      document.getElementById('btn-' + k).classList.toggle('active', k === role);
    });

    const footer = document.getElementById('card-footer-links');
    footer.innerHTML = r.showRegister
      ? '<p>No account? <a href="/sims/register_student.php">Register here</a></p>'
      : '<p>SIMS · Student Information Management System</p>';

    // Show "Forgot password?" only for student role
    const forgotWrap = document.getElementById('forgot-link-wrap');
    if (forgotWrap) forgotWrap.style.display = role === 'student' ? 'block' : 'none';

    document.getElementById('email').focus();
  }
</script>

</body>
</html>
