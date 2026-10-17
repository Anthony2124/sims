<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /sims/admin/dashboard.php'); exit; }
$stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
$stmt->execute([$id]);
$s = $stmt->fetch();
if (!$s) { echo 'Not found'; exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $year = $_POST['year_level'] ?? '';
    $spec = in_array($year, ['3rd Year','4th Year']) ? ($_POST['specialization'] ?? '') : null;
    $data = [$_POST['student_id'], $_POST['first_name'], $_POST['last_name'], $_POST['email'],
             $_POST['phone'], $_POST['address'], $_POST['course'], $year,
             $_POST['birthdate']?:null, $_POST['status'], $_POST['gender']??null,
             $_POST['student_type']??null, $spec, $id];
    $upd = $pdo->prepare('UPDATE students SET student_id=?, first_name=?, last_name=?, email=?, phone=?, address=?, course=?, year_level=?, birthdate=?, status=?, gender=?, student_type=?, specialization=? WHERE id=?');
    $upd->execute($data);
    header('Location: /sims/admin/dashboard.php?tab=students');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Student — SIMS</title>
  <link rel="stylesheet" href="/sims/assets/css/style.css">
</head>
<body>
<div class="page-blob"></div>
<div class="container">
  <div class="site-brand">
    <div class="brand-icon">🛡️</div>
    <span class="brand-name">Admin Panel</span>
  </div>
  <h2>Edit Student</h2>
  <form method="post">
    <label>Student ID</label><input name="student_id" value="<?php echo htmlspecialchars($s['student_id']); ?>">
    <label>First name</label><input name="first_name" value="<?php echo htmlspecialchars($s['first_name']); ?>">
    <label>Last name</label><input name="last_name" value="<?php echo htmlspecialchars($s['last_name']); ?>">
    <label>Email address</label><input type="email" name="email" value="<?php echo htmlspecialchars($s['email']); ?>">
    <label>Phone</label><input name="phone" value="<?php echo htmlspecialchars($s['phone']); ?>">
    <label>Address</label><input name="address" value="<?php echo htmlspecialchars($s['address']); ?>">
    <label>Course</label><input name="course" value="<?php echo htmlspecialchars($s['course']); ?>">
    <label>Year level</label>
    <select name="year_level" id="yr">
      <option value="">-- Select --</option>
      <?php foreach (['1st Year','2nd Year','3rd Year','4th Year'] as $yr): ?>
        <option value="<?php echo $yr; ?>" <?php echo ($s['year_level']??'')===$yr?'selected':''; ?>><?php echo $yr; ?></option>
      <?php endforeach; ?>
    </select>
    <label>Gender</label>
    <select name="gender">
      <option value="">-- Select --</option>
      <option value="Male"   <?php echo ($s['gender']??'')==='Male'?'selected':''; ?>>Male</option>
      <option value="Female" <?php echo ($s['gender']??'')==='Female'?'selected':''; ?>>Female</option>
      <option value="Other"  <?php echo ($s['gender']??'')==='Other'?'selected':''; ?>>Other</option>
    </select>
    <label>Student Type</label>
    <select name="student_type">
      <option value="">-- Select --</option>
      <option value="Regular"   <?php echo ($s['student_type']??'')==='Regular'?'selected':''; ?>>Regular</option>
      <option value="Irregular" <?php echo ($s['student_type']??'')==='Irregular'?'selected':''; ?>>Irregular</option>
    </select>
    <div id="specWrap" style="<?php echo in_array($s['year_level']??'',['3rd Year','4th Year'])?'':'display:none;'; ?>">
      <label>Specialization</label>
      <select name="specialization">
        <option value="">-- Select --</option>
        <option value="AP - Application Programming" <?php echo ($s['specialization']??'')==='AP - Application Programming'?'selected':''; ?>>AP — Application Programming</option>
        <option value="DD - Digital Design"          <?php echo ($s['specialization']??'')==='DD - Digital Design'?'selected':''; ?>>DD — Digital Design</option>
      </select>
    </div>
    <label>Birthdate</label><input type="date" name="birthdate" value="<?php echo htmlspecialchars($s['birthdate']??''); ?>">
    <label>Status</label>
    <select name="status">
      <option value="active"   <?php if($s['status']==='active')   echo 'selected'; ?>>Active</option>
      <option value="inactive" <?php if($s['status']==='inactive') echo 'selected'; ?>>Inactive</option>
    </select>
    <button type="submit">Save changes →</button>
  </form>
  <p><a href="/sims/admin/dashboard.php?tab=students">← Back to dashboard</a></p>
</div>
<script>
  const yr = document.getElementById('yr');
  const sw = document.getElementById('specWrap');
  yr.addEventListener('change', () => {
    sw.style.display = (yr.value === '3rd Year' || yr.value === '4th Year') ? '' : 'none';
  });
</script>
</body>
</html>
