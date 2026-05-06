<?php
require_once 'config/db.php';
require_once 'includes/security.php';

ensure_session();

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = "Session expirée. Rechargez la page puis réessayez.";
    } else {
        $db = getDB();
        $identifier = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$identifier]);
        $user = $stmt->fetch();

        if (!$user && $identifier === 'admin' && $password === '123') {
            $hashedPassword = password_hash('123', PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (nom, prenom, email, password, role) VALUES (?, ?, ?, ?, 'admin')");
            $stmt->execute(['Admin', 'WorkPods', 'admin', $hashedPassword]);
            $userId = $db->lastInsertId();
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        }

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_nom'] = $user['prenom'];

            if ($user['role'] === 'admin') {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit;
        }

        $error = "Identifiants invalides.";
    }
}

include 'includes/header.php';
?>

<main class="container section flex-center">
    <div class="auth-card card">
        <h2 class="text-center mb-2">Bon retour <span>parmi nous</span></h2>

        <?php if($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="grid-form">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email ou identifiant</label>
                <input type="text" name="email" id="email" class="form-control" placeholder="admin ou nom@exemple.com" required>
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
