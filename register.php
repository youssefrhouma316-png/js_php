<?php
require_once 'config/db.php';
require_once 'includes/security.php';

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = "Session expirée. Rechargez la page puis réessayez.";
    } else {
        $db = getDB();
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $passwordRaw = $_POST['password'] ?? '';

        if ($nom === '' || $prenom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($passwordRaw) < 6) {
            $error = "Veuillez remplir tous les champs avec des informations valides.";
        } else {
            $password = password_hash($passwordRaw, PASSWORD_BCRYPT);

            try {
                $stmt = $db->prepare("INSERT INTO users (nom, prenom, email, password) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nom, $prenom, $email, $password]);
                $success = "Compte créé avec succès ! <a href='login.php'>Connectez-vous ici</a>";
            } catch (PDOException $e) {
                $error = "Cet email est déjà utilisé.";
            }
        }
    }
}

include 'includes/header.php';
?>

<main class="container section flex-center">
    <div class="auth-card card">
        <h2 class="text-center mb-2">Créer un <span>compte</span></h2>

        <?php if($error): ?> <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div> <?php endif; ?>
        <?php if($success): ?> <div class="alert alert-success"><?= $success ?></div> <?php endif; ?>

        <form action="register.php" method="POST" class="grid-form">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Nom</label>
                <input type="text" name="nom" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Prénom</label>
                <input type="text" name="prenom" class="form-control" required>
            </div>
            <div class="form-group" style="grid-column: span 2;">
                <label>Email professionnel</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="form-group" style="grid-column: span 2;">
                <label>Mot de passe</label>
                <input type="password" name="password" class="form-control" minlength="6" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block mt-1">S'inscrire</button>
        </form>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
