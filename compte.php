<?php
require_once 'includes/config.php';
startSession();
if (!isLoggedIn()) { setFlash('error', 'Connectez-vous pour accéder à votre compte.'); redirect('login.php'); }

$db     = getDB();
$client = getCurrentClient();
$errors = [];

// Mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Session invalide.';
    } else {
        $nom       = sanitize($_POST['nom'] ?? '');
        $prenom    = sanitize($_POST['prenom'] ?? '');
        $telephone = sanitize($_POST['telephone'] ?? '');
        $adresse   = sanitize($_POST['adresse'] ?? '');
        $ville     = sanitize($_POST['ville'] ?? '');
        $cp        = sanitize($_POST['code_postal'] ?? '');

        if (strlen($nom) < 2) $errors[] = 'Nom trop court.';
        if (empty($errors)) {
            $upd = $db->prepare("UPDATE clients SET nom=?, prenom=?, telephone=?, adresse=?, ville=?, code_postal=? WHERE id=?");
            $upd->execute([$nom, $prenom, $telephone, $adresse, $ville, $cp, $client['id']]);
            setFlash('success', 'Profil mis à jour.');
            redirect('compte.php');
        }
    }
}

// Changement mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { $errors[] = 'Session invalide.'; }
    else {
        $ancien = $_POST['ancien_mdp'] ?? '';
        $nouveau = $_POST['nouveau_mdp'] ?? '';
        $confirm = $_POST['confirmer_mdp'] ?? '';

        if (!password_verify($ancien, $client['mot_de_passe'])) $errors[] = 'Mot de passe actuel incorrect.';
        if (strlen($nouveau) < 8) $errors[] = 'Nouveau mot de passe trop court (8 min).';
        if ($nouveau !== $confirm) $errors[] = 'Les mots de passe ne correspondent pas.';

        if (empty($errors)) {
            $hash = password_hash($nouveau, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare("UPDATE clients SET mot_de_passe=? WHERE id=?")->execute([$hash, $client['id']]);
            setFlash('success', 'Mot de passe changé avec succès.');
            redirect('compte.php');
        }
    }
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Compte – <?= SITE_NAME ?></title>
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
            <a href="commandes.php" class="nav-link">Mes commandes</a>
            <a href="panier.php" class="nav-cart">🛒</a>
            <a href="php/logout.php" class="nav-link">Déconnexion</a>
        </div>
    </div>
</nav>

<?php if ($flash): ?>
<div class="flash flash-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="container" style="padding:48px 24px;max-width:800px">
    <h1 style="font-family:'Playfair Display',serif;font-size:2rem;margin-bottom:32px">
        Mon <em style="color:var(--accent)">Compte</em>
    </h1>

    <?php foreach ($errors as $e): ?>
        <div class="flash flash-error"><?= sanitize($e) ?></div>
    <?php endforeach; ?>

    <!-- Profil -->
    <div style="background:white;border:1px solid #ede9e2;border-radius:16px;padding:32px;margin-bottom:24px">
        <h2 style="font-size:1.1rem;font-weight:700;margin-bottom:24px">Informations personnelles</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Nom</label>
                    <input type="text" name="nom" required value="<?= sanitize($client['nom']) ?>">
                </div>
                <div class="form-group">
                    <label>Prénom</label>
                    <input type="text" name="prenom" value="<?= sanitize($client['prenom'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" value="<?= sanitize($client['email']) ?>" disabled style="background:#f5f5f5;color:#aaa">
                </div>
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="tel" name="telephone" value="<?= sanitize($client['telephone'] ?? '') ?>">
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>Adresse</label>
                    <input type="text" name="adresse" value="<?= sanitize($client['adresse'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Code postal</label>
                    <input type="text" name="code_postal" value="<?= sanitize($client['code_postal'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Ville</label>
                    <input type="text" name="ville" value="<?= sanitize($client['ville'] ?? '') ?>">
                </div>
            </div>
            <div style="margin-top:20px">
                <button type="submit" name="update_profile" class="btn-primary" style="width:auto;padding:12px 32px;border-radius:50px">Enregistrer</button>
            </div>
        </form>
    </div>

    <!-- Mot de passe -->
    <div style="background:white;border:1px solid #ede9e2;border-radius:16px;padding:32px">
        <h2 style="font-size:1.1rem;font-weight:700;margin-bottom:24px">Changer le mot de passe</h2>
        <form method="POST" style="max-width:400px">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <div class="form-group" style="margin-bottom:14px">
                <label>Mot de passe actuel</label>
                <input type="password" name="ancien_mdp" required>
            </div>
            <div class="form-group" style="margin-bottom:14px">
                <label>Nouveau mot de passe</label>
                <input type="password" name="nouveau_mdp" required>
            </div>
            <div class="form-group" style="margin-bottom:20px">
                <label>Confirmer</label>
                <input type="password" name="confirmer_mdp" required>
            </div>
            <button type="submit" name="change_password" class="btn-primary" style="width:auto;padding:12px 32px;border-radius:50px">Changer</button>
        </form>
    </div>
</div>
</body>
</html>
