<?php
require_once __DIR__ . '/includes/auth.php';
if (empty($_SESSION['user'])) {
    header('Location: /sims/select_role.php');
    exit;
}
$u = current_user();
if ($u['role'] === 'admin') header('Location: /sims/admin/dashboard.php');
elseif ($u['role'] === 'teacher') header('Location: /sims/teacher/list_students.php');
else header('Location: /sims/student/profile.php');
exit;
