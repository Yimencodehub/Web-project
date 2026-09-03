<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';

$search = $_GET['q'] ?? '';
$category_id = $_GET['category'] ?? '';
$availability = $_GET['availability'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Fetch categories for filter
$categories = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Build query
$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(b.title LIKE :search OR b.author LIKE :search OR b.isbn LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($category_id) {
    $where[] = "b.category_id = :category";
    $params[':category'] = $category_id;
}
if ($availability === 'available') {
    $where[] = "b.available_copies > 0";
}

$whereClause = implode(" AND ", $where);

// Count total
$total = 0;
try {
    $countSql = "SELECT COUNT(*) FROM books b WHERE $whereClause";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {}

$totalPages = ceil($total / $limit);

// Fetch books
$books = [];
try {
    $sql = "SELECT b.*, c.name as category_name 
            FROM books b 
            LEFT JOIN categories c ON b.category_id = c.id 
            WHERE $whereClause 
            ORDER BY b.title ASC 
            LIMIT :limit OFFSET :offset";
            
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Catalog</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0f172a;
            --nav-bg: #1e293b;
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --accent: #f59e0b;
            --accent-hover: #d97706;
            --text-main: #f8fafc;
            --text-muted: #cbd5e1;
            --border-color: #334155;
            --card-bg: rgba(30, 41, 59, 0.7);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-color); color: var(--text-main); line-height: 1.6; }
        a { text-decoration: none; color: inherit; }
        
        /* Navbar */
        .navbar { background-color: var(--nav-bg); padding: 1rem 5%; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; border-bottom: 1px solid var(--border-color); }
        .logo { font-size: 1.5rem; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 0.5rem; }
        .logo span { color: var(--accent); }
        .nav-links { display: flex; gap: 2rem; }
        .nav-links a { font-weight: 500; transition: color 0.3s; }
        .nav-links a:hover, .nav-links a.active { color: var(--accent); }
        .nav-actions { display: flex; gap: 1rem; }
        .btn { padding: 0.5rem 1.5rem; border-radius: 0.375rem; font-weight: 600; transition: all 0.3s; cursor: pointer; display: inline-block; text-align: center; border: none; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-hover)); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4); }
        .btn-outline { background: transparent; color: var(--accent); border: 1px solid var(--accent); }
        .btn-outline:hover { background: var(--accent); color: white; transform: translateY(-2px); }
        
        /* Catalog Layout */
        .catalog-container { display: flex; gap: 2rem; padding: 2rem 5%; min-height: calc(100vh - 200px); }
        
        /* Sidebar */
        .sidebar { width: 280px; flex-shrink: 0; background: var(--card-bg); padding: 1.5rem; border-radius: 0.75rem; border: 1px solid var(--border-color); height: fit-content; }
        .sidebar h3 { margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--text-muted); }
        .form-control { width: 100%; padding: 0.75rem; background: var(--bg-color); border: 1px solid var(--border-color); color: white; border-radius: 0.375rem; outline: none; transition: border-color 0.3s; }
        .form-control:focus { border-color: var(--primary); }
        .btn-block { width: 100%; }

        /* Main Content */
        .main-content { flex: 1; }
        .search-bar-top { display: flex; gap: 1rem; margin-bottom: 2rem; }
        .search-bar-top input { flex: 1; }
        
        .results-info { margin-bottom: 1.5rem; color: var(--text-muted); font-size: 0.875rem; }

        /* Book Grid */
        .book-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem; }
        .book-card { background: var(--card-bg); border-radius: 0.75rem; overflow: hidden; border: 1px solid var(--border-color); transition: transform 0.3s, box-shadow 0.3s; display: flex; flex-direction: column; }
        .book-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.4); border-color: var(--primary); }
        .book-cover { height: 220px; background: linear-gradient(45deg, var(--nav-bg), var(--border-color)); display: flex; align-items: center; justify-content: center; font-size: 4rem; color: var(--text-muted); font-weight: 700; }
        .book-info { padding: 1.25rem; flex: 1; display: flex; flex-direction: column; }
        .book-title { font-weight: 600; font-size: 1.1rem; margin-bottom: 0.25rem; }
        .book-author { color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1rem; }
        .book-badge { display: inline-block; padding: 0.25rem 0.5rem; background: rgba(245, 158, 11, 0.1); color: var(--accent); border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; margin-bottom: 1rem; align-self: flex-start; }
        .status-badge { font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 1rem; display: inline-block; margin-bottom: 1rem; align-self: flex-start; }
        .status-avail { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .status-unavail { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        
        .empty-state { text-align: center; padding: 5rem 2rem; background: var(--card-bg); border-radius: 1rem; border: 1px dashed var(--border-color); }
        .empty-state h3 { margin-bottom: 0.5rem; color: var(--text-muted); }

        /* Pagination */
        .pagination { display: flex; justify-content: center; gap: 0.5rem; margin-top: 3rem; }
        .page-btn { padding: 0.5rem 1rem; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.375rem; color: white; cursor: pointer; transition: 0.3s; }
        .page-btn:hover:not(.disabled) { background: var(--primary); border-color: var(--primary); }
        .page-btn.active { background: var(--primary); border-color: var(--primary); }
        .page-btn.disabled { opacity: 0.5; cursor: not-allowed; }

        /* Footer */
        .footer { background-color: var(--nav-bg); padding: 4rem 5% 1.5rem; border-top: 1px solid var(--border-color); margin-top: auto; }
        .footer-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 3rem; margin-bottom: 3rem; }
        .footer-col h3 { color: white; margin-bottom: 1.5rem; font-size: 1.1rem; }
        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 0.75rem; }
        .footer-col ul li a { color: var(--text-muted); transition: color 0.3s; }
        .footer-col ul li a:hover { color: var(--accent); }
        .footer-bottom { text-align: center; padding-top: 1.5rem; border-top: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.875rem; }

        @media (max-width: 768px) {
            .catalog-container { flex-direction: column; }
            .sidebar { width: 100%; }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <a href="index.php" class="logo">City<span>Library</span></a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="catalog.php" class="active">Catalog</a>
            <a href="library_info.php">Library Info</a>
            <a href="contact.php">Contact</a>
        </div>
        <div class="nav-actions">
            <a href="../login.php" class="btn btn-outline">Login</a>
            <a href="../register.php" class="btn btn-primary">Register</a>
        </div>
    </nav>

    <div class="catalog-container">
        <!-- Sidebar Filter -->
        <aside class="sidebar">
            <h3>Filters</h3>
            <form action="catalog.php" method="GET">
                <!-- Keep search if exists -->
                <?php if($search): ?>
                    <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Availability</label>
                    <select name="availability" class="form-control">
                        <option value="">All</option>
                        <option value="available" <?= $availability === 'available' ? 'selected' : '' ?>>Available Only</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Apply Filters</button>
                <a href="catalog.php" class="btn btn-outline btn-block" style="margin-top: 0.5rem; display: block; text-align: center;">Reset</a>
            </form>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <form action="catalog.php" method="GET" class="search-bar-top">
                <?php if($category_id): ?><input type="hidden" name="category" value="<?= htmlspecialchars($category_id) ?>"><?php endif; ?>
                <?php if($availability): ?><input type="hidden" name="availability" value="<?= htmlspecialchars($availability) ?>"><?php endif; ?>
                <input type="text" name="q" class="form-control" placeholder="Search by title, author, or ISBN..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </form>

            <div class="results-info">
                Found <?= $total ?> result(s)
            </div>

            <?php if(empty($books)): ?>
                <?php if(isset($pdo) && !empty($categories)): // If DB works but no results ?>
                    <div class="empty-state">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🔍</div>
                        <h3>No books found matching your criteria.</h3>
                        <p class="text-muted">Try adjusting your filters or search terms.</p>
                    </div>
                <?php else: // Dummy data if db missing ?>
                    <div class="book-grid">
                    <?php for($i=1; $i<=8; $i++): ?>
                        <div class="book-card">
                            <div class="book-cover"><?= chr(64+$i) ?></div>
                            <div class="book-info">
                                <span class="book-badge">Fiction</span>
                                <span class="status-badge status-avail">Available</span>
                                <h4 class="book-title">Sample Book Title <?= $i ?></h4>
                                <p class="book-author">Author Name</p>
                                <a href="book_details.php?id=<?= $i ?>" class="btn btn-outline btn-block" style="margin-top: auto;">View Details</a>
                            </div>
                        </div>
                    <?php endfor; ?>
                    </div>
                    <?php $totalPages = 3; $page = 1; ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="book-grid">
                    <?php foreach($books as $book): ?>
                        <div class="book-card">
                            <div class="book-cover"><?= strtoupper(substr($book['title'], 0, 1)) ?></div>
                            <div class="book-info">
                                <span class="book-badge"><?= htmlspecialchars($book['category_name'] ?? 'General') ?></span>
                                <?php 
                                    $available = ($book['available_copies'] > 0);
                                ?>
                                <span class="status-badge <?= $available ? 'status-avail' : 'status-unavail' ?>">
                                    <?= $available ? 'Available' : 'Unavailable' ?>
                                </span>
                                <h4 class="book-title" title="<?= htmlspecialchars($book['title']) ?>"><?= htmlspecialchars($book['title']) ?></h4>
                                <p class="book-author"><?= htmlspecialchars($book['author']) ?></p>
                                <a href="book_details.php?id=<?= $book['id'] ?>" class="btn btn-outline btn-block" style="margin-top: auto;">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                        $qs = $_GET;
                        $prevPage = $page - 1;
                        $nextPage = $page + 1;
                    ?>
                    
                    <?php $qs['page'] = $prevPage; ?>
                    <a href="?<?= http_build_query($qs) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" <?= $page <= 1 ? 'onclick="event.preventDefault()"' : '' ?>>Prev</a>
                    
                    <?php for($i=1; $i<=$totalPages; $i++): ?>
                        <?php $qs['page'] = $i; ?>
                        <a href="?<?= http_build_query($qs) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <?php $qs['page'] = $nextPage; ?>
                    <a href="?<?= http_build_query($qs) ?>" class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>" <?= $page >= $totalPages ? 'onclick="event.preventDefault()"' : '' ?>>Next</a>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-grid">
            <div class="footer-col">
                <a href="index.php" class="logo mb-4" style="display:inline-flex;">City<span>Library</span></a>
                <p class="text-muted" style="margin-top: 1rem;">Empowering the community through free access to knowledge, information, and technology.</p>
            </div>
            <div class="footer-col">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="catalog.php">Browse Catalog</a></li>
                    <li><a href="library_info.php">Library Info</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Account</h3>
                <ul>
                    <li><a href="../login.php">Member Login</a></li>
                    <li><a href="../register.php">Register</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> <?= getSiteName($pdo) ?>. All rights reserved.
        </div>
    </footer>
</body>
</html>
