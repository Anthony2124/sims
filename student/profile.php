<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');
require_once __DIR__ . '/../includes/db.php';
$user = current_user();

// Auto-migrate new columns
foreach ([
    'profile_picture' => "ALTER TABLE students ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL",
    'student_type'    => "ALTER TABLE students ADD COLUMN student_type ENUM('Regular','Irregular') DEFAULT NULL",
    'specialization'  => "ALTER TABLE students ADD COLUMN specialization VARCHAR(50) DEFAULT NULL",
    'birthdate'       => "ALTER TABLE students ADD COLUMN birthdate DATE DEFAULT NULL",
    'address'         => "ALTER TABLE students ADD COLUMN address TEXT",
    'year_level'      => "ALTER TABLE students ADD COLUMN year_level VARCHAR(20) DEFAULT NULL",
    'gender'          => "ALTER TABLE students ADD COLUMN gender ENUM('Male','Female','Other') DEFAULT NULL",
] as $col => $sql) {
    if (!$pdo->query("SHOW COLUMNS FROM students LIKE '$col'")->fetch()) $pdo->exec($sql);
}

$stmt = $pdo->prepare('SELECT * FROM students WHERE email = ? LIMIT 1');
$stmt->execute([$user['email']]);
$s = $stmt->fetch();
if (!$s) {
    // Orphan user record — no matching student entry. Clear session and redirect.
    session_destroy();
    header('Location: /sims/select_role.php?error=no_student_record');
    exit;
}

$success = '';
$error = '';

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file = $_FILES['profile_picture'];
    if (!in_array($file['type'], $allowed)) {
        $error = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $error = 'Image must be under 5MB.';
    } else {
        $uploadDir = __DIR__ . '/../assets/uploads/profile/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        // Delete old picture
        if (!empty($s['profile_picture']) && file_exists(__DIR__ . '/../' . $s['profile_picture'])) {
            unlink(__DIR__ . '/../' . $s['profile_picture']);
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'student_' . $s['id'] . '_' . time() . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $uploadDir . $filename);
        $path = 'assets/uploads/profile/' . $filename;
        $pdo->prepare('UPDATE students SET profile_picture = ? WHERE id = ?')->execute([$path, $s['id']]);
        $s['profile_picture'] = $path;
        $success = 'Profile picture updated!';
    }
}

// Handle profile info update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_info'])) {
    $year = $_POST['year_level'] ?? '';
    $spec = in_array($year, ['3rd Year','4th Year']) ? ($_POST['specialization'] ?? '') : null;
    $upd = $pdo->prepare('UPDATE students SET phone=?,address=?,course=?,year_level=?,birthdate=?,student_type=?,specialization=?,gender=? WHERE id=?');
    $upd->execute([$_POST['phone']??'', $_POST['address']??'', $_POST['course']??'', $year, $_POST['birthdate']?:null, $_POST['student_type']??null, $spec, $_POST['gender']??null, $s['id']]);
    $success = 'Profile updated successfully!';
    $stmt->execute([$user['email']]);
    $s = $stmt->fetch();
}

$initials = strtoupper(substr($s['first_name'],0,1) . substr($s['last_name'],0,1));
$profilePic = !empty($s['profile_picture']) ? '/sims/' . $s['profile_picture'] : '';
$needs_spec = in_array($s['year_level'] ?? '', ['3rd Year','4th Year']);
// Completion %
$comp_fields = ['phone','address','course','year_level','birthdate','student_type','profile_picture','student_id'];
if ($needs_spec) $comp_fields[] = 'specialization';
$filled = count(array_filter($comp_fields, fn($f) => !empty($s[$f])));
$completion = round($filled / count($comp_fields) * 100);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile — SIMS</title>
  <link rel="stylesheet" href="/sims/assets/css/style.css">
  <style>
    body {
      background: #1a1a1a;
      display: block;
      padding: 0;
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* ── Sidebar ── */
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
      width: 40px; height: 40px; background: #0d8a72; border-radius: 10px;
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
      background: rgba(13,138,114,0.25); color: #34d399;
      border-left: 3px solid #0d8a72; padding-left: calc(.9rem - 3px);
    }
    .nav-icon { width: 18px; text-align: center; font-size: 1rem; }
    .nav-divider { height: 1px; background: rgba(255,255,255,0.06); margin: .75rem 0; }
    .sidebar-footer { margin-top: auto; }
    .sign-out-btn {
      display: flex; align-items: center; gap: .65rem;
      padding: .6rem .9rem; border-radius: 8px;
      color: #34d399; text-decoration: none;
      font-size: .875rem; font-weight: 500; transition: all .2s; width: 100%;
    }
    .sign-out-btn:hover { background: rgba(52,211,153,0.1); }

    /* ── Main ── */
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
    .dash-body { padding: 2rem; flex: 1; }

    /* ── Profile Header ── */
    .profile-header {
      background: #222222;
      border: 1px solid rgba(255,255,255,0.07);
      border-radius: 16px;
      padding: 2.5rem;
      display: flex;
      align-items: center;
      gap: 2rem;
      margin-bottom: 2rem;
      position: relative;
      overflow: hidden;
    }
    .profile-header::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 100px;
      background: linear-gradient(135deg, #0d8a72 0%, #065f46 100%);
      z-index: 0;
    }

    .avatar-wrapper {
      position: relative;
      z-index: 1;
      flex-shrink: 0;
    }
    .avatar {
      width: 120px; height: 120px;
      border-radius: 50%;
      border: 4px solid #222;
      display: flex; align-items: center; justify-content: center;
      font-family: 'Outfit', sans-serif;
      font-weight: 800; font-size: 2.5rem;
      color: #fff;
      background: linear-gradient(135deg, #0d8a72, #10b981);
      overflow: hidden;
      box-shadow: 0 6px 24px rgba(0,0,0,0.5);
    }
    .avatar img {
      width: 100%; height: 100%;
      object-fit: cover;
    }
    .avatar-upload-btn {
      position: absolute;
      bottom: 2px; right: 2px;
      width: 34px; height: 34px;
      border-radius: 50%;
      background: #0d8a72;
      border: 3px solid #222;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      transition: all .2s;
      font-size: .85rem;
      color: #fff;
      z-index: 2;
    }
    .avatar-upload-btn:hover {
      background: #10b981;
      transform: scale(1.1);
    }
    .avatar-upload-btn input[type="file"] {
      display: none;
    }

    .profile-info {
      z-index: 1;
      padding-top: 2rem;
    }
    .profile-name {
      font-family: 'Outfit', sans-serif;
      font-size: 1.65rem;
      font-weight: 800;
      color: #fff;
      letter-spacing: -.03em;
      margin-bottom: .25rem;
    }
    .profile-email {
      color: #888;
      font-size: .9rem;
      margin-bottom: .5rem;
    }
    .profile-badges {
      display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .5rem;
    }
    .badge {
      display: inline-flex; align-items: center; gap: .3rem;
      padding: .25rem .7rem;
      border-radius: 99px;
      font-size: .72rem;
      font-weight: 600;
    }
    .badge-student {
      background: rgba(147,197,253,0.12);
      color: #93c5fd;
      border: 1px solid rgba(147,197,253,0.25);
    }
    .badge-active {
      background: rgba(111,207,151,0.12);
      color: #6fcf97;
      border: 1px solid rgba(111,207,151,0.25);
    }
    .badge-active::before {
      content: ''; width: 6px; height: 6px; border-radius: 50%; background: #6fcf97;
    }

    /* ── Info Cards ── */
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    .info-card {
      background: #222222;
      border: 1px solid rgba(255,255,255,0.07);
      border-radius: 12px;
      padding: 1.75rem;
    }
    .info-card h3 {
      font-family: 'Outfit', sans-serif;
      font-size: .95rem;
      font-weight: 700;
      color: #fff;
      margin-bottom: 1.25rem;
      display: flex;
      align-items: center;
      gap: .5rem;
    }
    .info-row {
      display: flex;
      justify-content: space-between;
      padding: .65rem 0;
      border-bottom: 1px solid rgba(255,255,255,0.04);
    }
    .info-row:last-child { border-bottom: none; }
    .info-label {
      font-size: .78rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #666;
    }
    .info-value {
      font-size: .875rem;
      color: #ddd;
      font-weight: 500;
    }
    .info-value.empty { color: #444; font-style: italic; }

    /* ── Edit Form ── */
    .edit-card {
      background: #222222;
      border: 1px solid rgba(255,255,255,0.07);
      border-radius: 12px;
      padding: 1.75rem;
    }
    .edit-card h3 {
      font-family: 'Outfit', sans-serif;
      font-size: .95rem;
      font-weight: 700;
      color: #fff;
      margin-bottom: 1.25rem;
      display: flex;
      align-items: center;
      gap: .5rem;
    }
    .edit-card label { color: #999; margin-top: .75rem; }
    .edit-card input, .edit-card select {
      background: #1a1a1a !important;
      border-color: rgba(255,255,255,0.1) !important;
      color: #eee !important;
    }
    .edit-card input:focus {
      border-color: #0d8a72 !important;
      box-shadow: 0 0 0 3px rgba(13,138,114,0.3) !important;
    }
    .edit-card input::placeholder { color: #555 !important; }
    .edit-card button {
      margin-top: 1.5rem; width: 100%;
      background: #0d8a72 !important;
    }
    .edit-card button:hover {
      background: #10b981 !important;
    }

    /* ── Alerts ── */
    .alert {
      padding: .75rem 1.25rem;
      border-radius: 8px;
      font-size: .85rem;
      font-weight: 500;
      margin-bottom: 1.5rem;
    }
    .alert-success {
      background: rgba(111,207,151,0.1);
      border: 1px solid rgba(111,207,151,0.25);
      color: #6fcf97;
    }
    .alert-error {
      background: rgba(248,113,113,0.1);
      border: 1px solid rgba(248,113,113,0.25);
      color: #f87171;
    }

    @media (max-width: 900px) {
      .dash-sidebar { display: none; }
      .dash-main { margin-left: 0; }
      .info-grid { grid-template-columns: 1fr; }
      .profile-header { flex-direction: column; text-align: center; }
      .profile-info { padding-top: 0; }
      .profile-badges { justify-content: center; }
    }
  </style>
</head>
<body>

<!-- Sidebar -->
<nav class="dash-sidebar">
  <div class="brand">
    <div class="brand-icon">🎓</div>
    <div>
      <div class="brand-name">SIMS</div>
      <div class="brand-sub">Student Portal</div>
    </div>
  </div>

  <a class="nav-item active" href="/sims/student/profile.php">
    <span class="nav-icon">👤</span> My Profile
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
    <h1>My Profile</h1>
  </div>

  <div class="dash-body">

    <?php if ($success): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Profile Header -->
    <div class="profile-header">
      <div class="avatar-wrapper">
        <div class="avatar">
          <?php if ($profilePic): ?>
            <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profile">
          <?php else: ?>
            <?php echo $initials; ?>
          <?php endif; ?>
        </div>
        <form method="post" enctype="multipart/form-data" id="avatarForm">
          <label class="avatar-upload-btn" title="Change profile picture">
            📷
            <input type="file" name="profile_picture" accept="image/*"
                   onchange="document.getElementById('avatarForm').submit();">
          </label>
        </form>
      </div>
      <div class="profile-info">
        <div class="profile-name"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></div>
        <div class="profile-email"><?php echo htmlspecialchars($s['email']); ?></div>
        <div class="profile-badges">
          <span class="badge badge-student">🎓 Student</span>
          <?php if (!empty($s['student_type'])): ?>
            <span class="badge badge-student"><?php echo htmlspecialchars($s['student_type']); ?></span>
          <?php endif; ?>
          <?php if ($s['status'] === 'active'): ?>
            <span class="badge badge-active">Active</span>
          <?php endif; ?>
        </div>
        <!-- Completion Bar -->
        <div style="margin-top:1.25rem;min-width:260px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.35rem;">
            <span style="font-size:.75rem;color:#888;font-weight:600;text-transform:uppercase;letter-spacing:.06em;">Profile Completion</span>
            <span style="font-size:.85rem;font-weight:700;color:<?php echo $completion>=80?'#34d399':($completion>=50?'#fbbf24':'#f87171'); ?>"><?php echo $completion; ?>%</span>
          </div>
          <div style="background:rgba(255,255,255,0.08);border-radius:99px;height:8px;overflow:hidden;">
            <div style="height:100%;width:<?php echo $completion; ?>%;border-radius:99px;transition:width .6s ease;
              background:<?php echo $completion>=80?'linear-gradient(90deg,#0d8a72,#34d399)':($completion>=50?'linear-gradient(90deg,#b45309,#fbbf24)':'linear-gradient(90deg,#991b1b,#f87171)'); ?>;"></div>
          </div>
          <?php if ($completion < 100): ?>
            <div style="font-size:.72rem;color:#666;margin-top:.35rem;">
              <?php echo (count($comp_fields) - $filled); ?> field(s) remaining
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Info Grid -->
    <div class="info-grid">

      <!-- Academic Info -->
      <div class="info-card">
        <h3>📋 Academic Information</h3>
        <div class="info-row">
          <span class="info-label">Student ID</span>
          <span class="info-value <?php echo empty($s['student_id']) ? 'empty' : ''; ?>">
            <?php echo htmlspecialchars($s['student_id'] ?: 'Not set'); ?>
          </span>
        </div>
        <div class="info-row">
          <span class="info-label">Course</span>
          <span class="info-value <?php echo empty($s['course']) ? 'empty' : ''; ?>">
            <?php echo htmlspecialchars($s['course'] ?: 'Not set'); ?>
          </span>
        </div>
        <div class="info-row">
          <span class="info-label">Year Level</span>
          <span class="info-value <?php echo empty($s['year_level']) ? 'empty' : ''; ?>">
            <?php echo htmlspecialchars($s['year_level'] ?: 'Not set'); ?>
          </span>
        </div>
        <div class="info-row">
          <span class="info-label">Student Type</span>
          <span class="info-value <?php echo empty($s['student_type']) ? 'empty' : ''; ?>">
            <?php echo htmlspecialchars($s['student_type'] ?: 'Not set'); ?>
          </span>
        </div>
        <?php if ($needs_spec): ?>
        <div class="info-row">
          <span class="info-label">Specialization</span>
          <span class="info-value <?php echo empty($s['specialization']) ? 'empty' : ''; ?>">
            <?php echo htmlspecialchars($s['specialization'] ?: 'Not set'); ?>
          </span>
        </div>
        <?php endif; ?>
        <div class="info-row">
          <span class="info-label">Enrolled</span>
          <span class="info-value"><?php echo date('M d, Y', strtotime($s['created_at'])); ?></span>
        </div>
      </div>

      <!-- Personal Info -->
      <div class="info-card">
        <h3>👤 Personal Information</h3>
        <div class="info-row">
          <span class="info-label">Full Name</span>
          <span class="info-value"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Email</span>
          <span class="info-value"><?php echo htmlspecialchars($s['email']); ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Phone</span>
          <span class="info-value <?php echo empty($s['phone']) ? 'empty' : ''; ?>">
            <?php echo htmlspecialchars($s['phone'] ?: 'Not set'); ?>
          </span>
        </div>
        <div class="info-row">
          <span class="info-label">Address</span>
          <span class="info-value <?php echo empty($s['address']) ? 'empty' : ''; ?>">
            <?php echo htmlspecialchars($s['address'] ?: 'Not set'); ?>
          </span>
        </div>
        <div class="info-row">
          <span class="info-label">Birthdate</span>
          <span class="info-value <?php echo empty($s['birthdate']) ? 'empty' : ''; ?>">
            <?php echo $s['birthdate'] ? date('M d, Y', strtotime($s['birthdate'])) : 'Not set'; ?>
          </span>
        </div>
      </div>
    </div>

    <!-- Edit Profile -->
    <div class="edit-card">
      <h3>✏️ Edit Profile</h3>
      <form method="post">
        <input type="hidden" name="save_info" value="1">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div>
            <label>Phone</label>
            <input name="phone" value="<?php echo htmlspecialchars($s['phone']??''); ?>" placeholder="+63 900 000 0000">
          </div>
          <div>
            <label>Birthdate</label>
            <input type="date" name="birthdate" value="<?php echo htmlspecialchars($s['birthdate']??''); ?>">
          </div>
          <div style="grid-column:1/-1;">
            <label>Address</label>
            <input name="address" value="<?php echo htmlspecialchars($s['address']??''); ?>" placeholder="House No., Street, City, Province">
          </div>
          <div>
            <label>Course</label>
            <input name="course" value="<?php echo htmlspecialchars($s['course']??''); ?>" placeholder="BS Computer Science">
          </div>
          <div>
            <label>Year Level</label>
            <select name="year_level" id="yearSelect">
              <option value="">-- Select --</option>
              <?php foreach (['1st Year','2nd Year','3rd Year','4th Year'] as $yr): ?>
                <option value="<?php echo $yr; ?>" <?php echo ($s['year_level']??'')===$yr?'selected':''; ?>><?php echo $yr; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Student Type</label>
            <select name="student_type">
              <option value="">-- Select --</option>
              <option value="Regular" <?php echo ($s['student_type']??'')==='Regular'?'selected':''; ?>>Regular</option>
              <option value="Irregular" <?php echo ($s['student_type']??'')==='Irregular'?'selected':''; ?>>Irregular</option>
            </select>
          </div>
          <div>
            <label>Gender</label>
            <select name="gender">
              <option value="">-- Select --</option>
              <option value="Male"   <?php echo ($s['gender']??'')==='Male'?'selected':''; ?>>Male</option>
              <option value="Female" <?php echo ($s['gender']??'')==='Female'?'selected':''; ?>>Female</option>
              <option value="Other"  <?php echo ($s['gender']??'')==='Other'?'selected':''; ?>>Other</option>
            </select>
          </div>
          <div id="specWrap" style="<?php echo $needs_spec?'':'display:none;'; ?>">
            <label>Specialization</label>
            <select name="specialization">
              <option value="">-- Select --</option>
              <option value="AP - Application Programming" <?php echo ($s['specialization']??'')==='AP - Application Programming'?'selected':''; ?>>AP — Application Programming</option>
              <option value="DD - Digital Design" <?php echo ($s['specialization']??'')==='DD - Digital Design'?'selected':''; ?>>DD — Digital Design</option>
            </select>
          </div>
        </div>
        <button type="submit">Save Changes →</button>
      </form>
    </div>

  </div>
</div>

<script>
  const yearSelect = document.getElementById('yearSelect');
  const specWrap   = document.getElementById('specWrap');
  function toggleSpec() {
    const v = yearSelect.value;
    specWrap.style.display = (v === '3rd Year' || v === '4th Year') ? '' : 'none';
  }
  yearSelect.addEventListener('change', toggleSpec);
</script>
</body>
</html>
