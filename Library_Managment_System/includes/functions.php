<?php
// Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireRole($roles) {
    if (!isLoggedIn()) {
        redirect('/login.php');
    }
    if (!in_array($_SESSION['role'], (array)$roles)) {
        redirect('/unauthorized.php');
    }
}

function calculateFine($due_date, $return_date, $pdo) {
    $stmt = $pdo->query("SELECT fine_per_day, max_fine, grace_period_days FROM fine_settings LIMIT 1");
    $settings = $stmt->fetch();
    
    if (!$settings) return 0;
    
    $due = new DateTime($due_date);
    $return = new DateTime($return_date);
    
    if ($return <= $due) {
        return 0;
    }
    
    $interval = $due->diff($return);
    $days_late = $interval->days;
    
    if ($days_late <= $settings['grace_period_days']) {
        return 0;
    }
    
    $fine = ($days_late - $settings['grace_period_days']) * $settings['fine_per_day'];
    if ($settings['max_fine'] > 0 && $fine > $settings['max_fine']) {
        $fine = $settings['max_fine'];
    }
    
    return $fine;
}

function generateMemberID($pdo) {
    $stmt = $pdo->query("SELECT member_id FROM members ORDER BY id DESC LIMIT 1");
    $last_member = $stmt->fetch();
    
    if (!$last_member) {
        return 'LIB001';
    }
    
    $num = (int) substr($last_member['member_id'], 3);
    $num++;
    return 'LIB' . str_pad($num, 3, '0', STR_PAD_LEFT);
}

function logAudit($pdo, $user_id, $action, $details = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $details, $ip]);
}

function logAction($pdo, $user_id, $action, $details = '') {
    return logAudit($pdo, $user_id, $action, $details);
}

function getSystemSetting($pdo, $key) {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : null;
}

function getStats($pdo) {
    $stats = [];
    $stats['total_books'] = $pdo->query("SELECT SUM(total_copies) FROM books")->fetchColumn() ?: 0;
    $stats['total_members'] = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn() ?: 0;
    $stats['issued_books'] = $pdo->query("SELECT COUNT(*) FROM book_issues WHERE status = 'issued'")->fetchColumn() ?: 0;
    $stats['overdue_books'] = $pdo->query("SELECT COUNT(*) FROM book_issues WHERE status = 'issued' AND due_date < CURRENT_DATE")->fetchColumn() ?: 0;
    return $stats;
}

function formatDate($date) {
    if (!$date) return '-';
    return date('M d, Y', strtotime($date));
}

function getDaysOverdue($due_date) {
    $due = new DateTime($due_date);
    $now = new DateTime();
    if ($now <= $due) return 0;
    return $due->diff($now)->days;
}

function renderUserAvatar($size = 36, $customPdo = null) {
    global $pdo;
    $db = $customPdo ?: $pdo;
    
    $userId = $_SESSION['user_id'] ?? 0;
    $pic = $_SESSION['profile_pic'] ?? '';
    $name = $_SESSION['full_name'] ?? 'User';
    
    // Fetch fresh profile_pic from DB if logged in
    if ($userId && $db) {
        try {
            $stmt = $db->prepare("SELECT profile_pic, full_name FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $u = $stmt->fetch();
            if ($u) {
                if (!empty($u['profile_pic'])) {
                    $pic = $u['profile_pic'];
                    $_SESSION['profile_pic'] = $pic;
                }
                if (!empty($u['full_name'])) {
                    $name = $u['full_name'];
                }
            }
        } catch (Exception $e) {}
    }

    $projectRoot = dirname(__DIR__); // c:\xampp\htdocs\Library_Mgt_System
    $fullPath = $projectRoot . '/' . ltrim($pic, '/\\');

    // Check if pic exists on filesystem
    if (!empty($pic) && file_exists($fullPath)) {
        // Build URL dynamically
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        $imgUrl = rtrim($baseUrl, '/') . '/' . ltrim($pic, '/\\');
        return "<img src='" . htmlspecialchars($imgUrl) . "' alt='Avatar' style='width:{$size}px; height:{$size}px; border-radius:50%; object-fit:cover; border:2px solid #4f46e5; vertical-align:middle; display:inline-block;'>";
    }
    
    // Fallback to stylized initials badge
    $initials = strtoupper(substr($name, 0, 1));
    if (strpos($name, ' ') !== false) {
        $parts = explode(' ', $name);
        $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    $fontSize = floor($size * 0.4);
    return "<span style='display:inline-flex; align-items:center; justify-content:center; width:{$size}px; height:{$size}px; border-radius:50%; background:linear-gradient(135deg,#4f46e5,#7c3aed); color:white; font-weight:700; font-size:{$fontSize}px; vertical-align:middle;'>{$initials}</span>";
}
