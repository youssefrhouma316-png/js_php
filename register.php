<?php
require_once 'config/db.php';

$error = "";
$success = "";

function ensureRegistrationColumns(PDO $db): void
{
    $columns = [
        'profession' => "ALTER TABLE users ADD COLUMN profession VARCHAR(120) DEFAULT NULL AFTER telephone",
        'entreprise' => "ALTER TABLE users ADD COLUMN entreprise VARCHAR(120) DEFAULT NULL AFTER profession",
        'objectif_usage' => "ALTER TABLE users ADD COLUMN objectif_usage VARCHAR(160) DEFAULT NULL AFTER entreprise",
        'adresse' => "ALTER TABLE users ADD COLUMN adresse VARCHAR(255) DEFAULT NULL AFTER objectif_usage",
        'photo' => "ALTER TABLE users ADD COLUMN photo VARCHAR(255) DEFAULT NULL AFTER adresse",
    ];

    $stmt = $db->query("SHOW COLUMNS FROM users");
    $existing = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    foreach ($columns as $column => $sql) {
        if (!in_array($column, $existing, true)) {
            $db->exec($sql);
        }
    }
}

function uploadProfilePhoto(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("La photo n'a pas pu etre envoyee.");
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException("La photo doit faire moins de 2 Mo.");
    }

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!isset($allowedMimeTypes[$mimeType])) {
        throw new RuntimeException("Formats acceptes : JPG, PNG ou WebP.");
    }

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException("Impossible de preparer le dossier des photos.");
    }

    $filename = 'profile-' . bin2hex(random_bytes(8)) . '.' . $allowedMimeTypes[$mimeType];
    $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException("Impossible d'enregistrer la photo.");
    }

    return 'assets/uploads/profiles/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();

    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $profession = trim($_POST['profession'] ?? '');
    $entreprise = trim($_POST['entreprise'] ?? '');
    $objectifUsage = trim($_POST['objectif_usage'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $passwordRaw = $_POST['password'] ?? '';

    try {
        if ($nom === '' || $prenom === '' || $email === '' || $passwordRaw === '') {
            throw new RuntimeException("Veuillez remplir les champs obligatoires.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Veuillez saisir une adresse email valide.");
        }

        if (strlen($passwordRaw) < 8) {
            throw new RuntimeException("Le mot de passe doit contenir au moins 8 caracteres.");
        }

        ensureRegistrationColumns($db);

        $photoPath = uploadProfilePhoto($_FILES['photo'] ?? []);
        $password = password_hash($passwordRaw, PASSWORD_BCRYPT);

        $stmt = $db->prepare(
            "INSERT INTO users
                (nom, prenom, email, password, telephone, profession, entreprise, objectif_usage, adresse, photo)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $nom,
            $prenom,
            $email,
            $password,
            $telephone ?: null,
            $profession ?: null,
            $entreprise ?: null,
            $objectifUsage ?: null,
            $adresse ?: null,
            $photoPath,
        ]);

        $success = "Compte cree avec succes ! <a href='login.php'>Connectez-vous ici</a>";
    } catch (PDOException $e) {
        $error = "Cet email est deja utilise.";
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

include 'includes/header.php';
?>

<main class="container section flex-center">
    <div class="register-layout">
        <section class="auth-card card register-card">
            <span class="tag">Profil membre</span>
            <h2 class="mb-1">Creer un <span>compte</span></h2>
            <p class="text-muted mb-2">Ajoutez vos informations utiles pour accelerer vos reservations et personnaliser votre experience WorkPods.</p>

            <?php if($error): ?> <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div> <?php endif; ?>
            <?php if($success): ?> <div class="alert alert-success"><?= $success ?></div> <?php endif; ?>

            <form action="register.php" method="POST" enctype="multipart/form-data" class="grid-form" data-validate-form>
                <div class="form-group">
                    <label for="nom">Nom *</label>
                    <input type="text" name="nom" id="nom" class="form-control" value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" data-validate="required" required>
                    <div class="form-error" id="err-nom"></div>
                </div>

                <div class="form-group">
                    <label for="prenom">Prenom *</label>
                    <input type="text" name="prenom" id="prenom" class="form-control" value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>" data-validate="required" required>
                    <div class="form-error" id="err-prenom"></div>
                </div>

                <div class="form-group">
                    <label for="email">Email professionnel *</label>
                    <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" data-validate="required|email" required>
                    <div class="form-error" id="err-email"></div>
                </div>

                <div class="form-group">
                    <label for="telephone">Telephone</label>
                    <input type="tel" name="telephone" id="telephone" class="form-control" value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>" placeholder="+216 00 000 000">
                </div>

                <div class="form-group">
                    <label for="profession">Profession</label>
                    <input type="text" name="profession" id="profession" class="form-control" value="<?= htmlspecialchars($_POST['profession'] ?? '') ?>" placeholder="Designer, etudiant, fondateur...">
                </div>

                <div class="form-group">
                    <label for="entreprise">Entreprise / organisation</label>
                    <input type="text" name="entreprise" id="entreprise" class="form-control" value="<?= htmlspecialchars($_POST['entreprise'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="objectif_usage">Besoin principal</label>
                    <select name="objectif_usage" id="objectif_usage" class="form-control">
                        <?php $selectedUsage = $_POST['objectif_usage'] ?? ''; ?>
                        <option value="">Choisir une option</option>
                        <option value="Travail individuel" <?= $selectedUsage === 'Travail individuel' ? 'selected' : '' ?>>Travail individuel</option>
                        <option value="Reunion client" <?= $selectedUsage === 'Reunion client' ? 'selected' : '' ?>>Reunion client</option>
                        <option value="Appels et visioconferences" <?= $selectedUsage === 'Appels et visioconferences' ? 'selected' : '' ?>>Appels et visioconferences</option>
                        <option value="Session equipe" <?= $selectedUsage === 'Session equipe' ? 'selected' : '' ?>>Session equipe</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe *</label>
                    <input type="password" name="password" id="password" class="form-control" data-validate="required|minLen:8" required>
                    <div class="form-error" id="err-password"></div>
                </div>

                <div class="form-group form-wide">
                    <label for="adresse">Adresse</label>
                    <input type="text" name="adresse" id="adresse" class="form-control" value="<?= htmlspecialchars($_POST['adresse'] ?? '') ?>" placeholder="Ville, quartier ou adresse professionnelle">
                </div>

                <div class="form-group form-wide">
                    <label for="profile-photo">Photo de profil</label>
                    <label class="file-upload-area" for="profile-photo">
                        <span class="file-upload-icon">+</span>
                        <strong>Ajouter une photo</strong>
                        <p>JPG, PNG ou WebP - 2 Mo maximum</p>
                        <input type="file" name="photo" id="profile-photo" accept="image/jpeg,image/png,image/webp">
                        <img id="img-preview" alt="Apercu de la photo">
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block form-wide mt-1">S'inscrire</button>
            </form>
        </section>

        <aside class="register-benefits">
            <div class="feature-card">
                <div class="feature-icon">1</div>
                <h3>Reservations plus rapides</h3>
                <p>Vos informations sont pre-remplies pour reduire les etapes lors de la prochaine reservation.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">2</div>
                <h3>Accueil personnalise</h3>
                <p>L'equipe sait mieux vous orienter vers le pod adapte a vos usages.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">3</div>
                <h3>Profil fiable</h3>
                <p>La photo aide a identifier les membres et simplifie l'arrivee sur place.</p>
            </div>
        </aside>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
