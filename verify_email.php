<?php
require_once __DIR__ . '/includes/db.php';
if (empty($_GET['token'])) {
    echo 'Invalid verification token.';
    exit;
}
$token = $_GET['token'];
$stmt = $pdo->prepare('SELECT * FROM users WHERE verify_token = ? LIMIT 1');
$stmt->execute([$token]);
$user = $stmt->fetch();
if (!$user) {
    echo 'Invalid or expired token.';
    exit;
}
if (!empty($user['verify_expires']) && strtotime($user['verify_expires']) < time()) {
    echo 'Verification token has expired.';
    exit;
}
$upd = $pdo->prepare('UPDATE users SET is_verified = 1, verify_token = NULL, verify_expires = NULL WHERE id = ?');
$upd->execute([$user['id']]);
// Optionally, log the user in or redirect
header('Location: /sims/select_role.php');
exit;
