<?php
require_once 'includes/config.php';
startSession();
$db = getDB();

$q    = sanitize($_GET['q'] ?? '');
$catId = (int)($_GET['categorie'] ?? 0);
$sort = in_array($_GET['sort'] ?? '', ['prix_asc','prix_desc','nom','recent']) ? $_GET['sort'] : 'recent';

$where = ["p.actif = 1", "p.stock > 0"];
$params = [];

if ($q) { $where[] = "p.nom LIKE ?"; $params[] = "%$q%"; }
if ($catId) { $where[] = "p.categorie_id = ?"; $params[] = $catId; }

$orderBy = match($sort) {
    'prix_asc'  => 'p.prix ASC',
    'prix_desc' => 'p.prix DESC',
    'nom'       => 'p.nom ASC',
    default     => 'p.created_at DESC',
};

$sql = "SELECT p.*, c.nom as categorie FROM produits p
        LEFT JOIN categories c ON p.categorie_id = c.id
        WHERE " . implode(' AND ', $where) . " ORDER BY $orderBy";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$produits = $stmt->fetchAll();

$cats  = $db->query("SELECT * FROM categories")->fetchAll();
$flash = getFlash();

// Panier count
$panierCount = 0;
if (isLoggedIn()) {
    $s = $db->prepare("SELECT COALESCE(SUM(quantite),0) FROM panier WHERE client_id = ?");
    $s->execute([$_SESSION['client_id']]);
    $panierCount = (int)$s->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Produits – <?= SITE_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 – Critère 4 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .page-header { background: var(--gris-clair); padding: 40px 0; margin-bottom: 40px; }
        .page-header h1 { font-family: 'Playfair Display', serif; font-size: 2.2rem; }
        .filters { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; margin-bottom: 32px; }
        .filter-cats { display: flex; gap: 8px; flex-wrap: wrap; flex: 1; }
        .filter-cat {
            padding: 6px 16px; border-radius: 30px; border: 1.5px solid #ddd;
            font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
            background: white; color: var(--gris);
        }
        .filter-cat.active, .filter-cat:hover { background: var(--noir); color: white; border-color: var(--noir); }
        .sort-select {
            padding: 8px 16px; border: 1.5px solid #ddd; border-radius: 30px;
            font-family: inherit; font-size: 0.85rem; cursor: pointer; background: white;
        }
        .results-count { color: var(--gris); font-size: 0.9rem; }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="logo">E<span>1</span></a>
        <div class="nav-search">
            <form action="produits.php" method="GET">
                <?php if ($catId): ?><input type="hidden" name="categorie" value="<?= $catId ?>"><?php endif; ?>
                <input type="text" name="q" placeholder="Rechercher…" value="<?= sanitize($q) ?>">
                <button type="submit">🔍</button>
            </form>
        </div>
        <div class="nav-actions">
            <?php if (isLoggedIn()): ?>
                <a href="compte.php" class="nav-link">👤 Mon compte</a>
                <a href="panier.php" class="nav-cart">🛒 <span class="cart-count"><?= $panierCount ?></span></a>
                <a href="php/logout.php" class="nav-link">Déconnexion</a>
            <?php else: ?>
                <a href="login.php" class="nav-link">Connexion</a>
                <a href="panier.php" class="nav-cart">🛒 <span class="cart-count"><?= $panierCount ?></span></a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<?php if ($flash): ?>
<div class="flash flash-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="page-header">
    <div class="container">
        <h1><?= $q ? "Résultats pour « $q »" : "Tous les produits" ?></h1>
    </div>
</div>

<div class="container">
    <div class="filters">
        <div class="filter-cats">
            <a href="produits.php<?= $q ? '?q='.urlencode($q) : '' ?>" class="filter-cat <?= !$catId ? 'active' : '' ?>">Tous</a>
            <?php foreach ($cats as $cat): ?>
            <a href="produits.php?categorie=<?= $cat['id'] ?><?= $q ? '&q='.urlencode($q) : '' ?>"
               class="filter-cat <?= $catId == $cat['id'] ? 'active' : '' ?>">
                <?= sanitize($cat['nom']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <select class="sort-select" onchange="location.href=this.value">
            <option value="produits.php?sort=recent<?= $catId ? '&categorie='.$catId : '' ?><?= $q ? '&q='.urlencode($q) : '' ?>" <?= $sort=='recent'?'selected':'' ?>>Plus récents</option>
            <option value="produits.php?sort=prix_asc<?= $catId ? '&categorie='.$catId : '' ?><?= $q ? '&q='.urlencode($q) : '' ?>" <?= $sort=='prix_asc'?'selected':'' ?>>Prix ↑</option>
            <option value="produits.php?sort=prix_desc<?= $catId ? '&categorie='.$catId : '' ?><?= $q ? '&q='.urlencode($q) : '' ?>" <?= $sort=='prix_desc'?'selected':'' ?>>Prix ↓</option>
            <option value="produits.php?sort=nom<?= $catId ? '&categorie='.$catId : '' ?><?= $q ? '&q='.urlencode($q) : '' ?>" <?= $sort=='nom'?'selected':'' ?>>Nom A-Z</option>
        </select>
    </div>

    <p class="results-count"><?= count($produits) ?> produit<?= count($produits) > 1 ? 's' : '' ?> trouvé<?= count($produits) > 1 ? 's' : '' ?></p>

    <?php if (empty($produits)): ?>
        <div style="text-align:center; padding:80px 0; color:var(--gris)">
            <p style="font-size:3rem">🔍</p>
            <p style="font-size:1.2rem; margin-top:16px">Aucun produit trouvé</p>
            <a href="produits.php" style="color:var(--accent); font-weight:600; margin-top:8px; display:inline-block">Voir tous les produits</a>
        </div>
    <?php else: ?>
        <div class="products-grid" style="margin-top:24px">
            <?php foreach ($produits as $p): ?>
            <article class="product-card">
                <div class="product-img">
                    <?php if ($p['image_url']): ?>
                        <img src="<?= sanitize($p['image_url']) ?>" alt="<?= sanitize($p['nom']) ?>">
                    <?php else: ?>
                        <div class="img-placeholder">📦</div>
                    <?php endif; ?>
                    <?php if ($p['stock'] <= $p['stock_min']): ?>
                        <span class="badge-stock">Stock limité</span>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <span class="product-cat"><?= sanitize($p['categorie'] ?? '') ?></span>
                    <h3><a href="produit.php?id=<?= $p['id'] ?>"><?= sanitize($p['nom']) ?></a></h3>
                    <div class="product-footer">
                        <span class="price"><?= formatPrice($p['prix']) ?></span>
                        <button class="btn-add" onclick="addToCart(<?= $p['id'] ?>)">+ Panier</button>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<footer class="footer" style="margin-top:80px">
    <div class="footer-bottom">© 2025 E1 Shop</div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
