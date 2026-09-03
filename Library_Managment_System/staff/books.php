<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['staff','admin','superadmin']);

$msg = '';
$msgType = 'success';

// Update shelf location (ST-05)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_shelf'])) {
    $book_id = (int)$_POST['book_id'];
    $shelf   = trim($_POST['shelf_location'] ?? '');

    try {
        $stmt = $pdo->prepare("UPDATE books SET shelf_location = ? WHERE id = ?");
        $stmt->execute([$shelf, $book_id]);
        $msg = "✅ Shelf location updated successfully for book #$book_id.";
        $msgType = 'success';
    } catch (Exception $e) {
        $msg = "Error updating location: " . htmlspecialchars($e->getMessage());
        $msgType = 'danger';
    }
}

// Search & filter
$search = trim($_GET['q'] ?? '');
$cat_id = trim($_GET['category'] ?? '');

$query = "
    SELECT b.*, c.name as category_name 
    FROM books b
    LEFT JOIN categories c ON b.category_id = c.id
    WHERE 1=1
";
$params = [];
if ($search !== '') {
    $query .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR b.shelf_location LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}
if ($cat_id !== '') {
    $query .= " AND b.category_id = ?";
    $params[] = $cat_id;
}
$query .= " ORDER BY b.title ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$books = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Search & Shelf Management — Staff</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #0f172a; --sidebar: #111827; --card-bg: rgba(30,41,59,0.85); --primary: #4f46e5; --accent: #f59e0b; --text: #f8fafc; --text-muted: #94a3b8; --border: #334155; --green: #10b981; --red: #ef4444; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: var(--sidebar); border-right: 1px solid var(--border); padding: 1.5rem; display: flex; flex-direction: column; position: fixed; left: 0; top: 0; bottom: 0; }
        .sidebar h2 { color: var(--primary); margin-bottom: 2rem; font-size: 1.3rem; font-weight: 700; }
        .sidebar a { display: block; color: var(--text-muted); text-decoration: none; padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s; font-size: 0.9rem; font-weight: 500; }
        .sidebar a:hover, .sidebar a.active { background: var(--primary); color: white; }
        
        .main-content { margin-left: 260px; flex: 1; padding: 2rem; overflow-y: auto; }
        .navbar { height: 64px; background: var(--sidebar); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: flex-end; padding: 0 2rem; position: sticky; top: 0; z-index: 10; margin: -2rem -2rem 2rem -2rem; }
        .card { background: var(--card-bg); backdrop-filter: blur(10px); border: 1px solid var(--border); border-radius: 1rem; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--border); font-size: 0.88rem; }
        th { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: 600; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        
        .badge { padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-green { background: rgba(16,185,129,0.15); color: var(--green); }
        .badge-red { background: rgba(239,68,68,0.15); color: var(--red); }
        .badge-info { background: rgba(59,130,246,0.15); color: #60a5fa; }
        
        .form-control { padding: 0.6rem 0.8rem; background: #0f172a; border: 1px solid var(--border); border-radius: 0.5rem; color: white; font-size: 0.85rem; }
        .btn { padding: 0.6rem 1.2rem; background: var(--primary); color: white; border: none; border-radius: 0.5rem; cursor: pointer; font-weight: 500; font-size: 0.85rem; text-decoration: none; }
        .btn-sm { padding: 0.35rem 0.75rem; font-size: 0.78rem; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .alert-success { background: rgba(16,185,129,0.1); color: var(--green); border: 1px solid rgba(16,185,129,0.3); }
        .alert-danger { background: rgba(239,68,68,0.1); color: var(--red); border: 1px solid rgba(239,68,68,0.3); }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>📚 Staff Panel</h2>
        <a href="dashboard.php">Dashboard</a>
        <a href="books.php" class="active">Search & Shelves (ST-04)</a>
        <a href="checkout.php">Check Out</a>
        <a href="checkin.php">Check In</a>
        <a href="reservations.php">Reservations</a>
        <a href="reports.php">Reports</a>
    </div>
    
    <div class="main-content">
        <div class="navbar">
            <span>Staff Portal (<?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>) | <a href="../logout.php" style="color:var(--red); text-decoration:none;">Logout</a></span>
        </div>
        
        <h1 style="font-size:1.6rem; font-weight:700; margin-bottom:0.5rem;">📖 Book Search & Shelf Management (ST-04 & ST-05)</h1>
        <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1.5rem;">Locate books on shelves, check available stock, and update physical shelf placements.</p>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?>"><?= $msg ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap;">
                <input type="text" name="q" class="form-control" style="flex:2; min-width:200px;" placeholder="Search title, author, ISBN, or shelf location..." value="<?= htmlspecialchars($search) ?>">
                <select name="category" class="form-control" style="flex:1; min-width:160px;">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $cat_id == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn">🔍 Search Books</button>
            </form>
        </div>

        <div class="card">
            <h3 style="font-size:1.1rem; margin-bottom:0.5rem;">Library Catalog Inventory</h3>
            <table>
                <thead>
                    <tr>
                        <th>Title & Author</th>
                        <th>ISBN</th>
                        <th>Category</th>
                        <th>Availability</th>
                        <th>Shelf Location (ST-05)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $b): 
                        $isAvail = $b['available_copies'] > 0;
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($b['title']) ?></strong><br>
                            <span style="color:var(--text-muted); font-size:0.8rem;">by <?= htmlspecialchars($b['author']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($b['isbn'] ?: 'N/A') ?></td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($b['category_name'] ?: 'General') ?></span></td>
                        <td>
                            <?php if ($isAvail): ?>
                                <span class="badge badge-green"><?= $b['available_copies'] ?> / <?= $b['total_copies'] ?> Available</span>
                            <?php else: ?>
                                <span class="badge badge-red">Out of Stock</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:flex; gap:6px; align-items:center;">
                                <input type="hidden" name="book_id" value="<?= $b['id'] ?>">
                                <input type="text" name="shelf_location" class="form-control" style="padding:4px 8px; font-size:0.8rem; width:140px;" value="<?= htmlspecialchars($b['shelf_location'] ?: 'General Stacks') ?>">
                                <button type="submit" name="update_shelf" class="btn btn-sm" title="Save Shelf Location">💾 Save</button>
                            </form>
                        </td>
                        <td>
                            <a href="checkout.php?book_id=<?= $b['id'] ?>" class="btn btn-sm" style="background:#10b981; <?= !$isAvail ? 'opacity:0.5; pointer-events:none;' : '' ?>">
                                Issue
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
