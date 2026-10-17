<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');
require_once __DIR__ . '/../includes/db.php';
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /sims/teacher/list_students.php'); exit; }
$stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
$stmt->execute([$id]);
$s = $stmt->fetch();
if (!$s) { echo 'Not found'; exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [$_POST['phone'], $_POST['address'], $_POST['course'], $_POST['year_level'], $id];
    $upd = $pdo->prepare('UPDATE students SET phone=?, address=?, course=?, year_level=? WHERE id=?');
    $upd->execute($data);
    header('Location: /sims/teacher/list_students.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Student — Teacher · SIMS</title>
  <link rel="stylesheet" href="/sims/assets/css/style.css">
</head>
<body>
<div class="page-blob"></div>
<div class="container">
  <div class="site-brand">
    <div class="brand-icon">📚</div>
    <span class="brand-name">Teacher Portal</span>
  </div>
  <h2>Edit Student</h2>
  <p style="margin-top:0;margin-bottom:1rem">
    Editing: <strong><?php echo htmlspecialchars($s['first_name'].' '.$s['last_name']); ?></strong>
  </p>
  <form method="post">
    <label>Phone</label><input name="phone" value="<?php echo htmlspecialchars($s['phone']); ?>">
    <label>Address</label><input name="address" value="<?php echo htmlspecialchars($s['address']); ?>">
    <label>Course</label><input name="course" value="<?php echo htmlspecialchars($s['course']); ?>">
    <label>Year level</label><input name="year_level" value="<?php echo htmlspecialchars($s['year_level']); ?>">
    <button type="submit">Save changes →</button>
  </form>
  <p><a href="/sims/teacher/list_students.php">← Back to list</a></p>
</div>
</body>
</html>
