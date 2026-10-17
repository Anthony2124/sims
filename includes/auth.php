<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';

function current_user() {
    if (!empty($_SESSION['user'])) return $_SESSION['user'];
    return null;
}

function require_login($roles = null) {
    if (empty($_SESSION['user'])) {
        $url = '/sims/select_role.php';
        
        // Try to guess role from requested path or parameter
        $r = is_array($roles) ? ($roles[0] ?? null) : $roles;
        if (!$r && strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) $r = 'admin';
        if (!$r && strpos($_SERVER['REQUEST_URI'], '/teacher/') !== false) $r = 'teacher';
        
        if ($r) {
            $url .= '?role=' . urlencode($r);
            $url .= '&redirect=' . urlencode($_SERVER['REQUEST_URI']);
        } else {
            $url .= '?redirect=' . urlencode($_SERVER['REQUEST_URI']);
        }
        
        header('Location: ' . $url);
        exit;
    }
}

function require_role($roles = []) {
    require_login($roles);
    $user = current_user();
    if (!$user || !in_array($user['role'], (array)$roles)) {
        http_response_code(403);
        echo 'Forbidden - insufficient permissions.';
        exit;
    }
}

function is_admin() { $u = current_user(); return $u && $u['role'] === 'admin'; }
function is_teacher() { $u = current_user(); return $u && $u['role'] === 'teacher'; }
function is_student() { $u = current_user(); return $u && $u['role'] === 'student'; }
