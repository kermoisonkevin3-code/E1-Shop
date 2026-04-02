<?php
require_once 'includes/config.php';
startSession();
if (!isLoggedIn()) { setFlash('error', 'Connectez-vous pour voir vos commandes.'); redirect('login.php'); }

$db = getDB();
$stmt = $db->prepare("
    SELECT c.*, COUNT(cl.id) as nb_articles
    FROM commandes c
    LEFT JOIN commande_lignes cl ON cl.commande_id = c.id
    WHERE c.client_id = ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$stmt->execute([$_SESSION['client_id']]);
$commandes = $stmt->fetchAll();
$flash = getFlash();

$statutLabels = [
    'en_attente' => ['label' => 'En attente',  'class' => 'badge-warning'],
    'confirmee'  => ['label' => 'Confirmée',   'class' => 'badge-success'],
    'expediee'   => ['label' => 'Expédiée',    'class' => 'badge-success'],
    'livree'     => ['label' => 'Livrée',      'class' => 'badge-success'],
    'annulee'    => ['label' => 'Annulée',     'class' => 'badge-danger'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Commandes – <?= SITE_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 – Critère 4 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="logo">E<span>1</span></a>
        <div class="nav-actions">
            <a href="index.php" class="nav-link">Boutique</a>
            <a href="compte.php" class="nav-link">👤 Mon compte</a>
            <a href="panier.php" class="nav-cart">🛒</a>
            <a href="php/logout.php" class="nav-link">Déconnexion</a>
        </div>
    </div>
</nav>

<?php if ($flash): ?>
<div class="flash flash-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="container" style="padding:48px 24px">
    <h1 style="font-family:'Playfair Display',serif; font-size:2rem; margin-bottom:32px">
        Mes <em style="color:var(--accent)">Commandes</em>
    </h1>

    <?php if (empty($commandes)): ?>
        <div style="text-align:center; padding:80px 0">
            <p style="font-size:3rem">📦</p>
            <p style="color:var(--gris); margin:16px 0">Aucune commande pour le moment</p>
            <a href="produits.php" class="btn-hero" style="display:inline-block">Commencer mes achats</a>
        </div>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Articles</th>
                    <th>Total</th>
                    <th>Statut</th>
                    <th>Livraison</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($commandes as $cmd): ?>
                <?php $s = $statutLabels[$cmd['statut']] ?? ['label' => $cmd['statut'], 'class' => 'badge-warning']; ?>
                <tr>
                    <td><strong>#<?= $cmd['id'] ?></strong></td>
                    <td><?= date('d/m/Y', strtotime($cmd['created_at'])) ?></td>
                    <td><?= $cmd['nb_articles'] ?> article<?= $cmd['nb_articles'] > 1 ? 's' : '' ?></td>
                    <td><strong><?= formatPrice($cmd['total']) ?></strong></td>
                    <td><span class="badge <?= $s['class'] ?>"><?= $s['label'] ?></span></td>
                    <td style="font-size:0.85rem; color:var(--gris)">
                        <?= sanitize($cmd['adresse_livraison'] ?? '') ?>
                        <?= $cmd['ville_livraison'] ? ', '.sanitize($cmd['ville_livraison']) : '' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
