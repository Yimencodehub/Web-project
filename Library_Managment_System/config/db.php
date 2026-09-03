<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'library_db');

// Dynamic BASE_URL detection for WAMP / XAMPP / Live Servers
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
    $appDir  = str_replace('\\', '/', dirname(__DIR__));
    if ($docRoot && strpos($appDir, $docRoot) === 0) {
        $relative = substr($appDir, strlen($docRoot));
        $baseUrl = $protocol . $host . rtrim($relative, '/');
    } else {
        $baseUrl = $protocol . $host . '/' . basename($appDir);
    }
    define('BASE_URL', $baseUrl);
}

try {
    $pdo = new PDO(
        'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
        DB_USER, DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('DB Connection failed: ' . $e->getMessage());
    die('
    <html><head><title>Database Connection Error</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>body{font-family:Inter,sans-serif;background:#0f172a;color:#f1f5f9;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
    .box{background:#1e293b;border:1px solid rgba(239,68,68,.3);border-radius:16px;padding:40px;text-align:center;max-width:460px;box-shadow:0 10px 25px rgba(0,0,0,0.3);}
    h2{color:#f87171;margin-bottom:12px}p{color:#94a3b8;font-size:.95rem;line-height:1.6;}</style></head>
    <body><div class="box"><h2>⚠️ Database Connection Error</h2>
    <p>Cannot connect to the MySQL database. Please ensure WampServer / MySQL is running and the database <strong>library_db</strong> is created.</p></div></body></html>
    ');
}

// Load site name from DB (cached in a global so we only query once)
function getSiteName(PDO $pdo): string {
    static $name = null;
    if ($name === null) {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key='library_name' LIMIT 1");
            $stmt->execute();
            $name = $stmt->fetchColumn() ?: 'City Public Library';
        } catch (Exception $e) {
            $name = 'City Public Library';
        }
    }
    return $name;
}
