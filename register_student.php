<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';
if (session_status() === PHP_SESSION_NONE)
  session_start();

$error = '';
$success = '';

if (isset($_GET['reset_reg'])) {
  unset($_SESSION['pending_reg']);
  header('Location: /sims/register_student.php');
  exit;
}

// ── Step 1: handle the registration form submission ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {

  $first_name = trim($_POST['first_name'] ?? '');
  $last_name = trim($_POST['last_name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $password2 = $_POST['password2'] ?? '';
  $student_id = trim($_POST['student_id'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $course = trim($_POST['course'] ?? '');
  $gender = trim($_POST['gender'] ?? '');

  if (empty($first_name) || empty($last_name)) {
    $error = 'Please provide your first and last name.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please provide a valid email.';
  } elseif (strlen($password) < 6) {
    $error = 'Password must be at least 6 characters.';
  } elseif ($password !== $password2) {
    $error = 'Passwords do not match.';
  } else {
    // Check duplicate
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
      $error = 'That email is already registered. Please sign in or use a different email.';
    } else {
      // Generate 6-digit OTP
      $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
      $expires = date('Y-m-d H:i:s', time() + 600); // 10 minutes

      // Store everything in session so we can resume after OTP
      $_SESSION['pending_reg'] = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'student_id' => $student_id,
        'phone' => $phone,
        'course' => $course,
        'gender' => $gender,
        'otp' => $otp,
        'otp_exp' => $expires,
        'attempts' => 0,
      ];
      session_write_close(); // Prevent session lock while sending mail

      // Send OTP email to student
      $name = trim($first_name . ' ' . $last_name);
      $email_body = "
                <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;'>
                  <div style='background:#800000;padding:24px 32px;border-radius:10px 10px 0 0;text-align:center;'>
                    <h1 style='color:#fff;margin:0;font-size:1.6rem;letter-spacing:1px;'>🎓 SIMS</h1>
                    <p style='color:#f5c6c6;margin:6px 0 0;font-size:.9rem;'>Student Information Management System</p>
                  </div>
                  <div style='background:#fff8f0;padding:32px;border-radius:0 0 10px 10px;border:1px solid #f0dcd0;'>
                    <h2 style='color:#800000;margin:0 0 12px;'>Email Verification</h2>
                    <p style='color:#444;'>Hi <strong>" . htmlspecialchars($name) . "</strong>,</p>
                    <p style='color:#444;'>Use the one-time code below to confirm your email and complete your registration:</p>
                    <div style='text-align:center;margin:28px 0;'>
                      <span style='display:inline-block;font-size:2.8rem;font-weight:800;letter-spacing:12px;
                                   color:#800000;background:#fff;border:2px dashed #800000;
                                   border-radius:10px;padding:16px 28px;'>" . $otp . "</span>
                    </div>
                    <p style='color:#666;font-size:.9rem;'>⏳ This code expires in <strong>10 minutes</strong>.</p>
                    <p style='color:#888;font-size:.8rem;margin-top:24px;'>If you didn't request this, you can safely ignore this email.</p>
                  </div>
                </div>";

      $sent = send_mail($email, 'SIMS — Your Email Verification Code', $email_body);

      if ($sent) {
        $success = 'otp_sent';
      } else {
        // Still allow proceeding even if mail fails (fallback)
        $error = 'Could not send OTP email. Please check your email address or try again.';
        unset($_SESSION['pending_reg']);
      }
    }
  }
}

// ── Step 2: handle OTP verification ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
  $entered_otp = trim($_POST['otp'] ?? '');

  if (empty($_SESSION['pending_reg'])) {
    $error = 'Session expired. Please register again.';
  } else {
    $reg = &$_SESSION['pending_reg'];
    $reg['attempts']++;

    if ($reg['attempts'] > 5) {
      unset($_SESSION['pending_reg']);
      $error = 'Too many incorrect attempts. Please register again.';
    } elseif (strtotime($reg['otp_exp']) < time()) {
      unset($_SESSION['pending_reg']);
      $error = 'Your OTP has expired. Please register again.';
    } elseif ($entered_otp !== $reg['otp']) {
      $error = 'Incorrect code. Please try again. (' . (5 - $reg['attempts']) . ' attempts left)';
      $success = 'otp_sent'; // keep showing OTP form
    } else {
      // OTP correct → save to DB, is_verified = 0 (still needs admin approval)
      $name = trim($reg['first_name'] . ' ' . $reg['last_name']);

      // Ensure column exists
      $row = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_verified'")->fetch();
      if (!$row) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 0");
      }

      $ins = $pdo->prepare('INSERT INTO users (name, email, password, role, is_verified) VALUES (?, ?, ?, ?, 0)');
      $ins->execute([$name, $reg['email'], $reg['password'], 'student']);

      $ins2 = $pdo->prepare('INSERT INTO students (student_id, first_name, last_name, email, phone, course, gender) VALUES (?, ?, ?, ?, ?, ?, ?)');
      $ins2->execute([
        $reg['student_id'],
        $reg['first_name'],
        $reg['last_name'],
        $reg['email'],
        $reg['phone'],
        $reg['course'],
        $reg['gender'],
      ]);

      // Send notification email to admin for approval
      $admin_email = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@example.com';
      $approve_url = rtrim(SITE_URL, '/') . BASE_URL . '/admin/dashboard.php?tab=pending';
      $admin_body = "
                <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;'>
                  <div style='background:#800000;padding:24px 32px;border-radius:10px 10px 0 0;text-align:center;'>
                    <h1 style='color:#fff;margin:0;font-size:1.6rem;letter-spacing:1px;'>🎓 SIMS</h1>
                    <p style='color:#f5c6c6;margin:6px 0 0;font-size:.9rem;'>Admin Notification</p>
                  </div>
                  <div style='background:#fff8f0;padding:32px;border-radius:0 0 10px 10px;border:1px solid #f0dcd0;'>
                    <h2 style='color:#800000;margin:0 0 12px;'>New Student Registration</h2>
                    <p style='color:#444;'>A new student has verified their email and is waiting for your approval.</p>
                    <table style='border-collapse:collapse; margin:1rem 0;'>
                        <tr><td style='padding:6px 12px; font-weight:bold; color:#555;'>Name:</td><td style='padding:6px 12px;'>" . htmlspecialchars($name) . "</td></tr>
                        <tr><td style='padding:6px 12px; font-weight:bold; color:#555;'>Email:</td><td style='padding:6px 12px;'>" . htmlspecialchars($reg['email']) . "</td></tr>
                        <tr><td style='padding:6px 12px; font-weight:bold; color:#555;'>Student ID:</td><td style='padding:6px 12px;'>" . htmlspecialchars($reg['student_id'] ?: 'Not provided') . "</td></tr>
                        <tr><td style='padding:6px 12px; font-weight:bold; color:#555;'>Course:</td><td style='padding:6px 12px;'>" . htmlspecialchars($reg['course'] ?: 'Not provided') . "</td></tr>
                    </table>
                    <p><a href='" . $approve_url . "' style='display:inline-block; padding:10px 24px; background:#800000; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold;'>Review & Approve</a></p>
                    <p style='color:#888; font-size:0.85rem; margin-top:1rem;'>— SIMS Admin Notification</p>
                  </div>
                </div>";
                
      unset($_SESSION['pending_reg']);
      session_write_close(); // Prevent session lock while sending mail
      
      send_mail($admin_email, 'SIMS — New Student Registration Pending Approval', $admin_body);

      $success = 'registered';
    }
  }
}

// ── Step 3: handle resend OTP ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend_otp') {
  if (empty($_SESSION['pending_reg'])) {
    $error = 'Session expired. Please register again.';
  } else {
    $reg = &$_SESSION['pending_reg'];
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 600);
    $reg['otp'] = $otp;
    $reg['otp_exp'] = $expires;
    $reg['attempts'] = 0;
    
    session_write_close(); // Prevent session lock while sending mail

    $name = trim($reg['first_name'] . ' ' . $reg['last_name']);
    $email_body = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;'>
              <div style='background:#800000;padding:24px 32px;border-radius:10px 10px 0 0;text-align:center;'>
                <h1 style='color:#fff;margin:0;font-size:1.6rem;letter-spacing:1px;'>🎓 SIMS</h1>
                <p style='color:#f5c6c6;margin:6px 0 0;font-size:.9rem;'>Student Information Management System</p>
              </div>
              <div style='background:#fff8f0;padding:32px;border-radius:0 0 10px 10px;border:1px solid #f0dcd0;'>
                <h2 style='color:#800000;margin:0 0 12px;'>New Verification Code</h2>
                <p style='color:#444;'>Hi <strong>" . htmlspecialchars($name) . "</strong>, here is your new OTP:</p>
                <div style='text-align:center;margin:28px 0;'>
                  <span style='display:inline-block;font-size:2.8rem;font-weight:800;letter-spacing:12px;
                               color:#800000;background:#fff;border:2px dashed #800000;
                               border-radius:10px;padding:16px 28px;'>" . $otp . "</span>
                </div>
                <p style='color:#666;font-size:.9rem;'>⏳ Expires in <strong>10 minutes</strong>.</p>
              </div>
            </div>";

    $sent = send_mail($reg['email'], 'SIMS — New Verification Code', $email_body);
    if ($sent) {
      $success = 'otp_sent';
      $info = 'A new code has been sent to ' . htmlspecialchars($reg['email']) . '.';
    } else {
      $error = 'Could not resend email. Please try again.';
      $success = 'otp_sent';
    }
  }
}

// Determine which UI to show
$show_otp = ($success === 'otp_sent') || (!empty($_SESSION['pending_reg']) && $success !== 'registered');
$show_done = ($success === 'registered');
$pending_email = $show_otp ? ($_SESSION['pending_reg']['email'] ?? '') : '';
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student Registration — SIMS</title>
  <link rel="stylesheet" href="/sims/assets/css/style.css">
  <style>
    /* ── OTP box ─────────────────────────────────────────── */
    .otp-wrapper {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1.5rem;
      padding: 2rem 0;
    }

    .otp-icon {
      font-size: 3rem;
    }

    .otp-label {
      text-align: center;
      color: var(--text-secondary);
      font-size: .95rem;
      line-height: 1.6;
      max-width: 340px;
    }

    .otp-inputs {
      display: flex;
      gap: .6rem;
      justify-content: center;
    }

    .otp-inputs input[type=text] {
      width: 48px;
      height: 58px;
      text-align: center;
      font-size: 1.6rem;
      font-weight: 800;
      border: 2px solid var(--border);
      border-radius: 10px;
      background: var(--bg-input, #fff);
      color: var(--primary);
      transition: border-color .2s, box-shadow .2s;
    }

    .otp-inputs input[type=text]:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(128, 0, 0, .15);
    }

    .otp-inputs input[type=text].filled {
      border-color: var(--primary);
      background: rgba(128, 0, 0, .05);
    }

    .otp-actions {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: .8rem;
      width: 100%;
    }

    .otp-actions button[type=submit] {
      width: 100%;
    }

    .resend-link {
      background: none;
      border: none;
      cursor: pointer;
      color: var(--primary);
      font-size: .85rem;
      text-decoration: underline;
      padding: 0;
    }

    .resend-link:hover {
      opacity: .8;
    }

    .success-box {
      text-align: center;
      padding: 2.5rem 1rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1rem;
    }

    .success-box .check {
      font-size: 4rem;
    }

    .success-box h3 {
      color: var(--primary);
      margin: 0;
    }

    .success-box p {
      color: var(--text-secondary);
      margin: 0;
      font-size: .95rem;
    }

    .info-msg {
      background: rgba(128, 0, 0, .08);
      color: var(--primary);
      border-radius: 8px;
      padding: .7rem 1rem;
      font-size: .88rem;
      text-align: center;
      margin-bottom: .5rem;
    }

    /* hidden OTP aggregator */
    #otp_combined {
      display: none;
    }

    /* ── Layout Overrides to match select_role.php ── */
    body {
      padding: 0;
      margin: 0;
      align-items: stretch;
      justify-content: flex-start;
    }
    .split-layout {
      max-width: none;
      margin: 0;
    }
    .split-left {
      flex: 0 0 340px;
      position: relative;
      overflow: hidden;
    }
    .split-left::before {
      content: ''; position: absolute; bottom: -80px; right: -80px;
      width: 340px; height: 340px; border-radius: 50%;
      background: rgba(255,255,255,0.04); pointer-events: none;
    }
    .split-left::after {
      content: ''; position: absolute; bottom: -40px; right: -120px;
      width: 200px; height: 200px; border-radius: 50%;
      background: rgba(255,255,255,0.04); pointer-events: none;
    }
    .split-right {
      flex: 1;
    }
    @media (max-width: 680px) {
      .split-layout { flex-direction: column; }
      .split-left { flex: none; width: 100%; padding: 2rem 1.5rem; }
    }
  </style>
</head>

<body>
  <div class="page-blob"></div>
  <div class="split-layout">
    <div class="split-left">
      <div class="brand-icon">🎓</div>
      <div class="brand-name">SIMS</div>
      <p>Your comprehensive Student Information Management System.</p>
    </div>

    <div class="split-right">
      <div class="container">

        <?php if ($show_done): ?>
          <!-- ── SUCCESS SCREEN ───────────────────────────────────── -->
          <div class="success-box">
            <div class="check">📨</div>
            <h3>Email verified!</h3>
            <p>Your email has been confirmed successfully.<br>Your account is now <strong>pending admin approval</strong>.
            </p>
            <p style="color:var(--text-secondary);font-size:.88rem;margin-top:.5rem;">The administrator has been notified
              and will review your registration.<br>You'll receive an email once your account is approved.</p>
            <a href="/sims/select_role.php" style="margin-top:1rem;display:inline-block;padding:.75rem 2rem;
           background:var(--primary);color:#fff;border-radius:8px;font-weight:700;text-decoration:none;">
              Go to Login →
            </a>
            <a href="/sims/select_role.php" style="font-size:.85rem;color:var(--text-secondary);">← Back to role
              selection</a>
          </div>

        <?php elseif ($show_otp): ?>
          <!-- ── OTP VERIFICATION SCREEN ──────────────────────────── -->
          <h2>Verify your email</h2>
          <?php if (!empty($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
          <?php endif; ?>
          <?php if (!empty($info)): ?>
            <p class="info-msg"><?php echo $info; ?></p>
          <?php endif; ?>

          <div class="otp-wrapper">
            <div class="otp-icon">📧</div>
            <div class="otp-label">
              We sent a <strong>6-digit verification code</strong> to<br>
              <strong><?php echo htmlspecialchars($pending_email); ?></strong><br>
              Enter it below to activate your account.
            </div>

            <form method="post" id="otp-form">
              <input type="hidden" name="action" value="verify_otp">
              <input type="hidden" name="otp" id="otp_combined">
              <div class="otp-inputs" id="otp-boxes">
                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" id="d1">
                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" id="d2">
                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" id="d3">
                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" id="d4">
                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" id="d5">
                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" id="d6">
              </div>
              <div class="otp-actions" style="margin-top:1.5rem;">
                <button type="submit">Verify Code →</button>
              </div>
            </form>

            <form method="post" style="margin:0;">
              <input type="hidden" name="action" value="resend_otp">
              <div style="text-align:center;font-size:.85rem;color:var(--text-secondary);">
                Didn't receive it?
                <button type="submit" class="resend-link">Resend code</button>
              </div>
            </form>

            <a href="/sims/register_student.php?reset_reg=1" style="font-size:.82rem;color:var(--text-secondary);">← Use a
              different email</a>
          </div>

        <?php else: ?>
          <!-- ── REGISTRATION FORM ─────────────────────────────────── -->
          <h2>Create your account</h2>
          <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
          <?php endif; ?>
          <form method="post" class="form-grid">
            <input type="hidden" name="action" value="register">
            <div class="form-group">
              <label>First name</label>
              <input name="first_name" placeholder="Juan" value="<?php echo htmlspecialchars($first_name ?? ''); ?>"
                required>
            </div>
            <div class="form-group">
              <label>Last name</label>
              <input name="last_name" placeholder="Dela Cruz" value="<?php echo htmlspecialchars($last_name ?? ''); ?>"
                required>
            </div>

            <div class="form-group">
              <label>Email address</label>
              <input type="email" name="email" placeholder="you@school.edu"
                value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
            </div>
            <div class="form-group">
              <label>Phone</label>
              <input name="phone" placeholder="+63 900 000 0000" value="<?php echo htmlspecialchars($phone ?? ''); ?>">
            </div>

            <div class="form-group">
              <label>Password</label>
              <input type="password" name="password" placeholder="Min. 6 characters" required>
            </div>
            <div class="form-group">
              <label>Confirm Password</label>
              <input type="password" name="password2" placeholder="Repeat password" required>
            </div>

            <div class="form-group">
              <label>Student ID <span style="opacity:.5;font-size:.7rem;text-transform:none">(opt)</span></label>
              <input name="student_id" placeholder="2024-00001"
                value="<?php echo htmlspecialchars($student_id ?? ''); ?>">
            </div>
            <div class="form-group">
              <label>Course</label>
              <input name="course" placeholder="BS Computer Science"
                value="<?php echo htmlspecialchars($course ?? ''); ?>">
            </div>
            <div class="form-group">
              <label>Gender</label>
              <select name="gender" required>
                <option value="">-- Select Gender --</option>
                <option value="Male" <?php echo ($gender ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo ($gender ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                <option value="Other" <?php echo ($gender ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
              </select>
            </div>

            <div class="form-group full-width">
              <button type="submit" style="width: 100%;">Send Verification Code →</button>
            </div>
          </form>
          <p><a href="/sims/select_role.php">Already have an account? Sign in</a></p>
          <p><a href="/sims/select_role.php">← Back to role selection</a></p>
        <?php endif; ?>

      </div>
    </div>
  </div>

  <script>
    // ── OTP digit-box UX ────────────────────────────────────────────────────────
    (function () {
      const boxes = Array.from(document.querySelectorAll('#otp-boxes input'));
      if (!boxes.length) return;

      const combined = document.getElementById('otp_combined');
      const form = document.getElementById('otp-form');

      function updateCombined() {
        combined.value = boxes.map(b => b.value).join('');
      }

      boxes.forEach((box, i) => {
        box.addEventListener('input', e => {
          // Allow only digits
          box.value = box.value.replace(/\D/g, '').slice(-1);
          box.classList.toggle('filled', box.value !== '');
          updateCombined();
          if (box.value && i < boxes.length - 1) boxes[i + 1].focus();
        });

        box.addEventListener('keydown', e => {
          if (e.key === 'Backspace' && !box.value && i > 0) boxes[i - 1].focus();
        });

        box.addEventListener('paste', e => {
          e.preventDefault();
          const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
          text.split('').slice(0, 6).forEach((ch, j) => {
            if (boxes[j]) { boxes[j].value = ch; boxes[j].classList.add('filled'); }
          });
          updateCombined();
          const nextEmpty = boxes.find(b => !b.value);
          (nextEmpty || boxes[5]).focus();
        });
      });

      form.addEventListener('submit', e => {
        updateCombined();
        if (combined.value.length < 6) {
          e.preventDefault();
          boxes[0].focus();
        }
      });

      // Auto-focus first box
      boxes[0].focus();
    })();
  </script>
</body>

</html>