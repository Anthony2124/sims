<?php
require_once __DIR__ . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$token   = trim($_GET['token'] ?? '');
$error   = '';
$success = false;
$user    = null;

// Validate token — compare expiry in PHP to avoid MySQL timezone issues
if ($token) {
    $stmt = $pdo->prepare('SELECT id, name, email, password_reset_expires FROM users WHERE password_reset_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    // Check expiry using PHP time() so timezone differences don't matter
    if ($row && strtotime($row['password_reset_expires']) > time()) {
        $user = $row;
    }
}

if (!$token || !$user) {
    $error = 'This password reset link is invalid or has expired. Please request a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $pass  = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL, must_change_password = 0 WHERE id = ?')
            ->execute([$hashed, $user['id']]);
        $success = true;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Password — SIMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; font-family: 'Inter', sans-serif; }
    body { display: flex; min-height: 100vh; background: #f5ede0; }
    .left-panel {
      width: 340px; flex-shrink: 0; background: #8b0000;
      display: flex; flex-direction: column;
      padding: 2.25rem 1.6rem; position: relative; overflow: hidden; z-index: 1;
    }
    .left-panel::before {
      content: ''; position: absolute; bottom: -80px; right: -80px;
      width: 340px; height: 340px; border-radius: 50%;
      background: rgba(255,255,255,0.04); pointer-events: none;
    }
    .left-panel::after {
      content: ''; position: absolute; bottom: -40px; right: -120px;
      width: 200px; height: 200px; border-radius: 50%;
      background: rgba(255,255,255,0.04); pointer-events: none;
    }
    .sims-logo {
      font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.9rem;
      color: #fff; letter-spacing: -.04em; margin-bottom: 2.75rem;
      display: flex; align-items: center; gap: .5rem;
    }
    .sims-logo .logo-dot { width: 9px; height: 9px; border-radius: 50%; background: #f5ede0; margin-top: 2px; }
    .left-footer { margin-top: auto; padding-top: 1.5rem; font-size: .73rem; color: rgba(255,255,255,0.38); line-height: 1.5; }
    .right-panel {
      flex: 1; background: #f5ede0;
      display: flex; align-items: center; justify-content: center;
      padding: 3rem 4rem; position: relative;
    }
    .card {
      background: #fff; border: 1.5px solid #dcc9ae; border-radius: 18px; padding: 3rem;
      box-shadow: 0 12px 50px rgba(139,0,0,0.11), 0 3px 10px rgba(0,0,0,0.06);
      width: 100%; max-width: 440px;
      animation: card-in .5s cubic-bezier(.22,1,.36,1) both;
    }
    @keyframes card-in {
      from { opacity:0; transform: translateY(24px) scale(.97); }
      to   { opacity:1; transform: translateY(0) scale(1); }
    }
    .card-icon { width: 56px; height: 56px; background: rgba(139,0,0,0.08); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 1.4rem; }
    .card h2 { font-family: 'Outfit', sans-serif; font-size: 1.6rem; font-weight: 700; color: #2c1a0e; margin-bottom: .4rem; }
    .card .subtitle { font-size: .92rem; color: #9a7a58; margin-bottom: 2rem; line-height: 1.55; }
    .form-field { margin-top: 1.2rem; }
    label { display: block; font-size: .78rem; font-weight: 600; color: #6b4c2c; letter-spacing: .08em; text-transform: uppercase; margin-bottom: .45rem; }
    input[type=password] { display: block; width: 100%; padding: .85rem 1.1rem; font-family: 'Inter', sans-serif; font-size: 1rem; color: #2c1a0e; background: #fdf8f3; border: 1.5px solid #d6c4a8; border-radius: 9px; outline: none; transition: border-color .2s, box-shadow .2s, background .2s; }
    input[type=password]:focus { border-color: #8b0000; background: #fff; box-shadow: 0 0 0 3px rgba(139,0,0,0.10); }
    .strength-bar { height: 4px; border-radius: 99px; background: #e8d5b7; margin-top: .5rem; overflow: hidden; }
    .strength-fill { height: 100%; border-radius: 99px; width: 0; transition: width .3s, background .3s; }
    .btn { display: flex; align-items: center; justify-content: center; width: 100%; margin-top: 1.8rem; padding: 1rem; font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1rem; color: #fff; background: linear-gradient(135deg, #9b0000, #6b0000); border: none; border-radius: 10px; cursor: pointer; box-shadow: 0 5px 20px rgba(139,0,0,0.38); transition: opacity .2s, transform .15s; }
    .btn:hover { opacity: .9; transform: translateY(-2px); }
    .alert-error { margin-bottom: 1.2rem; padding: .75rem 1rem; background: rgba(224,82,82,0.08); border: 1px solid rgba(224,82,82,0.28); border-radius: 9px; color: #b52b2b; font-size: .85rem; }
    .alert-success { text-align: center; padding: 1.5rem 1rem; }
    .alert-success .check { font-size: 3rem; margin-bottom: 1rem; }
    .alert-success h3 { font-family: 'Outfit', sans-serif; color: #2c1a0e; margin-bottom: .5rem; }
    .alert-success p { color: #9a7a58; font-size: .9rem; }
    .back-link { display: block; text-align: center; margin-top: 1.25rem; font-size: .875rem; color: #9a7a58; text-decoration: none; }
    .back-link:hover { color: #8b0000; }
  </style>
</head>
<body>

<aside class="left-panel">
  <div class="sims-logo">
    <span>SIMS</span>
    <div class="logo-dot"></div>
  </div>
  <div class="left-footer">
    Student Information<br>Management System<br>© <?= date('Y') ?> SIMS
  </div>
</aside>

<main class="right-panel">
  <div class="card">
    <?php if ($success): ?>
      <div class="alert-success">
        <div class="check">✅</div>
        <h3>Password Updated!</h3>
        <p>Your password has been reset successfully. You can now log in with your new password.</p>
        <a class="btn" style="margin-top:1.5rem;text-decoration:none;" href="/sims/select_role.php?role=student">Go to Login →</a>
      </div>

    <?php elseif (!$user): ?>
      <div class="card-icon">⚠️</div>
      <h2>Link Expired</h2>
      <p class="subtitle">This reset link is invalid or has already been used. Please request a new one.</p>
      <?php if ($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <a class="btn" style="text-decoration:none;" href="/sims/forgot_password.php">Request New Link →</a>
      <a class="back-link" href="/sims/select_role.php?role=student">← Back to login</a>

    <?php else: ?>
      <div class="card-icon">🔐</div>
      <h2>Set New Password</h2>
      <p class="subtitle">Hi <strong><?= htmlspecialchars($user['name']) ?></strong>, choose a strong new password.</p>
      <?php if ($error): ?><div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="form-field">
          <label for="rp-pass">New Password</label>
          <input type="password" id="rp-pass" name="password" placeholder="Min. 6 characters" required autocomplete="new-password">
          <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
        </div>
        <div class="form-field">
          <label for="rp-pass2">Confirm New Password</label>
          <input type="password" id="rp-pass2" name="password2" placeholder="Repeat your password" required autocomplete="new-password">
        </div>
        <button class="btn" type="submit">Update Password →</button>
      </form>
      <a class="back-link" href="/sims/select_role.php?role=student">← Back to login</a>
    <?php endif; ?>
  </div>
</main>

<script>
  const passInput = document.getElementById('rp-pass');
  const fill = document.getElementById('strength-fill');
  if (passInput && fill) {
    passInput.addEventListener('input', () => {
      const v = passInput.value;
      let score = 0;
      if (v.length >= 6)  score++;
      if (v.length >= 10) score++;
      if (/[A-Z]/.test(v)) score++;
      if (/[0-9]/.test(v)) score++;
      if (/[^A-Za-z0-9]/.test(v)) score++;
      const pct = (score / 5) * 100;
      const colors = ['#e05252','#e07a52','#e0c052','#6fcf97','#4caf7d'];
      fill.style.width  = pct + '%';
      fill.style.background = colors[score - 1] || '#e8d5b7';
    });
  }
</script>
</body>
</html>
