<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if(isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if($role === 'superadmin') header('Location: superadmin/dashboard.php');
    elseif($role === 'admin') header('Location: admin/dashboard.php');
    elseif($role === 'staff') header('Location: staff/dashboard.php');
    elseif($role === 'member') header('Location: member/dashboard.php');
} else {
    header('Location: guest/index.php');
}
exit;
