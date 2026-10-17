<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Auto-add columns if missing
try {
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'password_reset_token'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN password_reset_token VARCHAR(64) NULL DEFAULT NULL");
    }
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'password_reset_expires'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN password_reset_expires DATETIME NULL DEFAULT NULL");
    }
} catch (Exception $e) { /* ignore */ }

$sent    = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = ? AND role = "student" LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always show "sent" to avoid user enumeration
        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 900); // 15 min
            $pdo->prepare('UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?')
                ->execute([$token, $expires, $user['id']]);

            $reset_url = rtrim(SITE_URL, '/') . BASE_URL . '/reset_password.php?token=' . urlencode($token);
            $body = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;'>
              <div style='background:#800000;padding:24px 32px;border-radius:10px 10px 0 0;text-align:center;'>
                <h1 style='color:#fff;margin:0;font-size:1.6rem;letter-spacing:1px;'>🎓 SIMS</h1>
                <p style='color:#f5c6c6;margin:6px 0 0;font-size:.9rem;'>Student Information Management System</p>
              </div>
              <div style='background:#fff8f0;padding:32px;border-radius:0 0 10px 10px;border:1px solid #f0dcd0;'>
                <h2 style='color:#800000;margin:0 0 12px;'>Reset Your Password</h2>
                <p style='color:#444;'>Hi <strong>" . htmlspecialchars($user['name']) . "</strong>,</p>
                <p style='color:#444;'>Click the button below to reset your password. This link expires in <strong>15 minutes</strong>.</p>
                <p style='text-align:center;margin:28px 0;'>
                  <a href='" . $reset_url . "' style='display:inline-block;padding:12px 28px;background:#800000;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;font-size:1rem;'>Reset Password →</a>
                </p>
                <p style='color:#888;font-size:.82rem;'>If you didn't request this, you can safely ignore this email.</p>
                <p style='color:#aaa;font-size:.78rem;margin-top:1rem;word-break:break-all;'>Link: " . $reset_url . "</p>
              </div>
            </div>";
            send_mail($email, 'SIMS — Password Reset Request', $body);
        }
        $sent = true;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password — SIMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; font-family: 'Inter', sans-serif; }
    body {
      display: flex;
      min-height: 100vh;
      background: #f5ede0;
    }
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
    .left-panel::before {
      content: '';
      position: absolute;
      bottom: -80px; right: -80px;
      width: 340px; height: 340px;
      border-radius: 50%;
      background: rgba(255,255,255,0.04);
      pointer-events: none;
    }
    .left-panel::after {
      content: '';
      position: absolute;
      bottom: -40px; right: -120px;
      width: 200px; height: 200px;
      border-radius: 50%;
      background: rgba(255,255,255,0.04);
      pointer-events: none;
    }
    .sims-logo {
      font-family: 'Outfit', sans-serif;
      font-weight: 800; font-size: 1.9rem;
      color: #fff; letter-spacing: -.04em;
      margin-bottom: 2.75rem;
      display: flex; align-items: center; gap: .5rem;
    }
    .sims-logo .logo-dot {
      width: 9px; height: 9px; border-radius: 50%;
      background: #f5ede0; margin-top: 2px;
    }
    .left-footer {
      margin-top: auto;
      padding-top: 1.5rem;
      font-size: .73rem;
      color: rgba(255,255,255,0.38);
      line-height: 1.5;
    }
    .right-panel {
      flex: 1;
      background: #f5ede0;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 3rem 4rem;
      position: relative;
    }
    .card {
      background: #fff;
      border: 1.5px solid #dcc9ae;
      border-radius: 18px;
      padding: 3rem;
      box-shadow: 0 12px 50px rgba(139,0,0,0.11), 0 3px 10px rgba(0,0,0,0.06);
      width: 100%;
      max-width: 440px;
      animation: card-in .5s cubic-bezier(.22,1,.36,1) both;
    }
    @keyframes card-in {
      from { opacity:0; transform: translateY(24px) scale(.97); }
      to   { opacity:1; transform: translateY(0) scale(1); }
    }
    .card-icon {
      width: 56px; height: 56px;
      background: rgba(139,0,0,0.08);
      border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.6rem;
      margin-bottom: 1.4rem;
    }
    .card h2 {
      font-family: 'Outfit', sans-serif;
      font-size: 1.6rem; font-weight: 700;
      color: #2c1a0e; margin-bottom: .4rem;
    }
    .card .subtitle {
      font-size: .92rem; color: #9a7a58; margin-bottom: 2rem; line-height: 1.55;
    }
    label {
      display: block;
      font-size: .78rem; font-weight: 600;
      color: #6b4c2c; letter-spacing: .08em;
      text-transform: uppercase; margin-bottom: .45rem;
    }
    input[type=email] {
      display: block; width: 100%;
      padding: .85rem 1.1rem;
      font-family: 'Inter', sans-serif; font-size: 1rem;
      color: #2c1a0e; background: #fdf8f3;
      border: 1.5px solid #d6c4a8; border-radius: 9px;
      outline: none;
      transition: border-color .2s, box-shadow .2s, background .2s;
    }
    input[type=email]:focus {
      border-color: #8b0000; background: #fff;
      box-shadow: 0 0 0 3px rgba(139,0,0,0.10);
    }
    .btn {
      display: flex; align-items: center; justify-content: center; gap: .5rem;
      width: 100%; margin-top: 1.5rem;
      padding: 1rem; font-family: 'Outfit', sans-serif;
      font-weight: 700; font-size: 1rem; color: #fff;
      background: linear-gradient(135deg, #9b0000, #6b0000);
      border: none; border-radius: 10px; cursor: pointer;
      box-shadow: 0 5px 20px rgba(139,0,0,0.38);
      transition: opacity .2s, transform .15s;
    }
    .btn:hover { opacity: .9; transform: translateY(-2px); }
    .alert-error {
      margin-bottom: 1.2rem; padding: .75rem 1rem;
      background: rgba(224,82,82,0.08); border: 1px solid rgba(224,82,82,0.28);
      border-radius: 9px; color: #b52b2b; font-size: .85rem;
    }
    .alert-success {
      margin-bottom: 1.2rem; padding: 1rem 1.1rem;
      background: rgba(72,187,120,0.08); border: 1px solid rgba(72,187,120,0.3);
      border-radius: 9px; color: #276749; font-size: .9rem; line-height: 1.55;
    }
    .back-link {
      display: block; text-align: center; margin-top: 1.25rem;
      font-size: .875rem; color: #9a7a58; text-decoration: none;
    }
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
    <div class="card-icon">🔑</div>
    <h2>Forgot Password?</h2>
    <p class="subtitle">Enter your registered email and we'll send you a link to reset your password.</p>

    <?php if ($sent): ?>
      <div class="alert-success">
        ✅ If that email is registered, you'll receive a reset link shortly.<br>
        <small style="color:#555;">Check your inbox (and spam folder) within a few minutes.</small>
      </div>
      <a class="back-link" href="/sims/select_role.php?role=student">← Back to login</a>
    <?php else: ?>
      <?php if ($error): ?>
        <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <label for="fp-email">Email address</label>
        <input type="email" id="fp-email" name="email"
               placeholder="you@school.edu"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               required autofocus>
        <button class="btn" type="submit">Send Reset Link →</button>
      </form>
      <a class="back-link" href="/sims/select_role.php?role=student">← Back to login</a>
    <?php endif; ?>
  </div>
</main>

</body>
</html>
