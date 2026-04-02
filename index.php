<?php
require_once 'includes/config.php';
startSession();

$db = getDB();

// Produits vedettes
$stmt = $db->query("SELECT p.*, c.nom as categorie FROM produits p LEFT JOIN categories c ON p.categorie_id = c.id WHERE p.actif = 1 AND p.stock > 0 ORDER BY p.created_at DESC LIMIT 8");
$produits = $stmt->fetchAll();

// Catégories
$cats = $db->query("SELECT * FROM categories")->fetchAll();

$flash = getFlash();
$client = getCurrentClient();

// Panier count
$panierCount = 0;
if (isLoggedIn()) {
    $s = $db->prepare("SELECT SUM(quantite) FROM panier WHERE client_id = ?");
    $s->execute([$_SESSION['client_id']]);
    $panierCount = (int)$s->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> – Boutique en ligne</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 – Critère 4 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="logo">E<span>1</span></a>
        <div class="nav-search">
            <form action="produits.php" method="GET">
                <input type="text" name="q" placeholder="Rechercher un produit…" value="<?= sanitize($_GET['q'] ?? '') ?>">
                <button type="submit">🔍</button>
            </form>
        </div>
        <div class="nav-actions">
            <?php if (isLoggedIn()): ?>
                <a href="compte.php" class="nav-link">👤 <?= sanitize($client['prenom'] ?? $client['nom']) ?></a>
                <?php if (isAdmin()): ?>
                    <a href="admin.php" class="nav-link nav-admin">⚙ Admin</a>
                <?php endif; ?>
                <a href="panier.php" class="nav-cart">🛒 <span class="cart-count"><?= $panierCount ?></span></a>
                <a href="php/logout.php" class="nav-link">Déconnexion</a>
            <?php else: ?>
                <a href="login.php" class="nav-link">Connexion</a>
                <a href="register.php" class="btn-nav">S'inscrire</a>
                <a href="panier.php" class="nav-cart">🛒 <span class="cart-count"><?= $panierCount ?></span></a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- FLASH -->
<?php if ($flash): ?>
<div class="flash flash-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<!-- HERO -->
<section class="hero">
    <div class="hero-content">
        <p class="hero-sub">Nouvelle collection 2025</p>
        <h1>Découvrez<br><em>l'excellence</em></h1>
        <p class="hero-desc">Des produits sélectionnés pour leur qualité, livrés rapidement.</p>
        <a href="produits.php" class="btn-hero">Explorer la boutique</a>
    </div>
    <div class="hero-visual">
        <div class="hero-blob"></div>
        <div class="hero-badge">⭐ 4.9 / 5<br><small>+2 000 avis</small></div>
    </div>
</section>

<!-- CATÉGORIES -->
<section class="section-categories">
    <div class="container">
        <h2 class="section-title">Nos <em>catégories</em></h2>
        <div class="cats-grid">
            <?php foreach ($cats as $cat): ?>
            <a href="produits.php?categorie=<?= $cat['id'] ?>" class="cat-card">
                <div class="cat-icon">📦</div>
                <span><?= sanitize($cat['nom']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- PRODUITS VEDETTES -->
<section class="section-products">
    <div class="container">
        <h2 class="section-title">Nouveautés <em>du moment</em></h2>
        <div class="products-grid">
            <?php foreach ($produits as $p): ?>
            <article class="product-card" data-id="<?= $p['id'] ?>">
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
    </div>
</section>

<!-- FOOTER -->
<footer class="footer">
    <div class="container footer-grid">
        <div>
            <h4 class="logo">E<span>1</span></h4>
            <p>Votre boutique en ligne de confiance.</p>
        </div>
        <div>
            <h5>Boutique</h5>
            <a href="produits.php">Tous les produits</a>
            <a href="produits.php">Promotions</a>
        </div>
        <div>
            <h5>Compte</h5>
            <a href="commandes.php">Mes commandes</a>
            <a href="compte.php">Mon compte</a>
        </div>
        <div>
            <h5>Aide</h5>
            <a href="#">Contact</a>
            <a href="#">CGV</a>
        </div>
    </div>
    <div class="footer-bottom">© 2025 E1 Shop – Tous droits réservés</div>
</footer>

<!-- Bootstrap JS – Critère 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
