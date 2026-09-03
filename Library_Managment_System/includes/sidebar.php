<?php
$currentPath = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'member';

$menu = [];

if ($role === 'superadmin') {
    $menu = [
        ['Dashboard', 'index.php', '📊'],
        ['Admin Accounts', 'admins.php', '👥'],
        ['System Settings', 'settings.php', '⚙️'],
        ['Database Backup', 'backup.php', '💾'],
        ['Audit Logs', 'logs.php', '📝'],
        ['Reports', 'reports.php', '📈'],
        ['Fine Config', 'fine_config.php', '💰']
    ];
} elseif ($role === 'admin') {
    $menu = [
        ['Dashboard', 'index.php', '📊'],
        ['Books', 'books.php', '📚'],
        ['Categories', 'categories.php', '🏷️'],
        ['Members', 'members.php', '👥'],
        ['Issue Books', 'issue.php', '📤'],
        ['Returns', 'returns.php', '📥'],
        ['Fines', 'fines.php', '💰'],
        ['Inventory', 'inventory.php', '📦'],
        ['Reports', 'reports.php', '📈'],
        ['User Accounts', 'users.php', '👤']
    ];
} elseif ($role === 'staff') {
    $menu = [
        ['Dashboard', 'index.php', '📊'],
        ['Check-in/out', 'checkInOut.php', '🔄'],
        ['Reservations', 'reservations.php', '📅'],
        ['Reports', 'reports.php', '📈'],
        ['Fine Collection', 'fines.php', '💰']
    ];
} else { // member
    $menu = [
        ['Dashboard', 'index.php', '📊'],
        ['Search Books', 'search.php', '🔍'],
        ['My Borrows', 'my_borrows.php', '📚'],
        ['Due Dates', 'due_dates.php', '⏰'],
        ['My Fines', 'my_fines.php', '💰'],
        ['Reservations', 'my_reservations.php', '📅'],
        ['Profile', 'profile.php', '👤']
    ];
}
?>
<aside class="sidebar" id="sidebar">
    <nav class="sidebar-nav">
        <ul>
            <?php foreach ($menu as $item): 
                $isActive = ($currentPath == $item[1]) ? 'active' : '';
            ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/<?= $item[1] ?>" class="nav-link <?= $isActive ?>">
                    <span class="nav-icon"><?= $item[2] ?></span>
                    <span class="nav-text"><?= $item[0] ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</aside>
<main class="main-content">
