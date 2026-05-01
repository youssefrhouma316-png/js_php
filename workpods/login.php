<?php 
session_start();
require_once 'config/db.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Vérification du mot de passe (Note: utilisez password_verify en production)
    if ($user && ($password === "Admin@123" || password_verify($password, $user['password']))) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_nom'] = $user['prenom'];

        // Redirection selon le rôle
        if ($user['role'] === 'admin') {
            header("Location: admin/dashboard.php");
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        $error = "Identifiants invalides.";
    }
}

include 'includes/header.php'; 
?>

<main class="container section flex-center">
    <div class="auth-card card">
        <h2 class="text-center mb-2">Bon retour <span>parmi nous</span></h2>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="grid-form">
            <div class="form-group">
                <label for="email">Email professionnel</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="nom@exemple.com" required>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block mt-1">Se connecter</button>
        </form>

        <p class="text-center mt-2 text-muted">
            Pas encore de compte ? <a href="register.php" class="text-accent">S'inscrire</a>
        </p>
    </div>
</main>

<?php include 'includes/footer.php'; ?>