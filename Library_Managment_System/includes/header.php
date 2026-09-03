<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$pageTitle = $pageTitle ?? 'Dashboard';

// Define role colors
$roleColors = [
    'superadmin' => 'bg-red text-white',
    'admin' => 'bg-indigo text-white',
    'staff' => 'bg-green text-white',
    'member' => 'bg-blue text-white',
];
$currentUserRole = $_SESSION['role'] ?? 'member';
$roleBadge = $roleColors[$currentUserRole] ?? 'bg-blue text-white';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= getSiteName($pdo) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</head>
<body class="dark-theme">
    <div class="app-container">
        <!-- Header -->
        <header class="top-navbar">
            <div class="nav-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
                </button>
                <div class="brand">
                    <span class="brand-icon">📚</span>
                    <span class="brand-name"><?= getSiteName($pdo) ?></span>
                </div>
            </div>
            
            <div class="nav-right">
                <button type="button" class="theme-toggle-btn" onclick="toggleTheme()">☀️ Light Mode</button>
                <div class="nav-item user-profile">
                    <div class="user-info" style="display:flex; align-items:center; gap:8px;">
                        <?= renderUserAvatar(32) ?>
                        <span class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></span>
                        <span class="role-badge <?= $roleBadge ?>"><?= ucfirst($currentUserRole) ?></span>
                    </div>
                    <a href="<?= BASE_URL ?>/logout.php" class="btn btn-danger btn-sm logout-btn">
                        Logout
                    </a>
                </div>
            </div>
        </header>
        <div class="main-wrapper">
