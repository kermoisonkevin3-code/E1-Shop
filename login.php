<?php
require_once 'includes/config.php';
startSession();

if (isLoggedIn()) redirect('index.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Session expirée, veuillez réessayer.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        $mdp   = $_POST['password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
        if (strlen($mdp) < 6) $errors[] = 'Mot de passe trop court.';

        if (empty($errors)) {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM clients WHERE email = ? AND actif = 1 LIMIT 1");
            $stmt->execute([$email]);
            $client = $stmt->fetch();

            if ($client && password_verify($mdp, $client['mot_de_passe'])) {
                session_regenerate_id(true);
                $_SESSION['client_id'] = $client['id'];
                $_SESSION['role']      = $client['role'];
                $_SESSION['nom']       = $client['nom'];
                setFlash('success', 'Bienvenue, ' . $client['prenom'] . ' !');
                redirect('index.php');
            } else {
                $errors[] = 'Email ou mot de passe incorrect.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion – <?= SITE_NAME ?></title>
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
    </div>
</nav>

<div class="form-container">
    <h1 class="form-title">Connexion</h1>
    <?php foreach ($errors as $e): ?>
        <div class="flash flash-error"><?= sanitize($e) ?></div>
    <?php endforeach; ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required placeholder="votre@email.com"
                   value="<?= sanitize($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Mot de passe</label>
            <input type="password" name="password" required placeholder="••••••••">
        </div>
        <button type="submit" class="btn-primary">Se connecter</button>
    </form>
    <p style="text-align:center; margin-top:20px; font-size:0.9rem; color:#888">
        Pas encore de compte ? <a href="register.php" style="color:#c8a96e; font-weight:600">S'inscrire</a>
    </p>
</div>
</body>
</html>
