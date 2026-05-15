<?php
require_once 'config/db.php';

$error = "";
$success = "";

function ensureContactTable(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS contact_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(120) NOT NULL,
            email VARCHAR(180) NOT NULL,
            telephone VARCHAR(30) DEFAULT NULL,
            sujet VARCHAR(160) NOT NULL,
            message TEXT NOT NULL,
            statut ENUM('nouveau','lu','traite') NOT NULL DEFAULT 'nouveau',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $stmt = $db->query("SHOW COLUMNS FROM contact_messages");
    $existing = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('rappel_souhaite', $existing, true)) {
        $db->exec("ALTER TABLE contact_messages ADD COLUMN rappel_souhaite TINYINT(1) NOT NULL DEFAULT 0 AFTER message");
    }
    if (!in_array('creneau_rappel', $existing, true)) {
        $db->exec("ALTER TABLE contact_messages ADD COLUMN creneau_rappel VARCHAR(80) DEFAULT NULL AFTER rappel_souhaite");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();

    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $sujet = trim($_POST['sujet'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $rappelSouhaite = isset($_POST['rappel_souhaite']) ? 1 : 0;
    $creneauRappel = trim($_POST['creneau_rappel'] ?? '');

    try {
        if ($nom === '' || $email === '' || $sujet === '' || $message === '') {
            throw new RuntimeException("Veuillez remplir tous les champs obligatoires.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Veuillez saisir une adresse email valide.");
        }

        ensureContactTable($db);

        $stmt = $db->prepare(
            "INSERT INTO contact_messages (nom, email, telephone, sujet, message, rappel_souhaite, creneau_rappel)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$nom, $email, $telephone ?: null, $sujet, $message, $rappelSouhaite, $creneauRappel ?: null]);

        $success = "Message envoye avec succes. Notre equipe vous repondra rapidement.";
        $_POST = [];
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

include 'includes/header.php';
?>

<main>
    <section class="container section contact-hero">
        <div class="contact-intro">
            <span class="tag">Contact</span>
            <h1>Besoin d'un pod, d'une visite ou d'un conseil ?</h1>
            <p class="text-muted">Envoyez-nous votre demande, trouvez notre localisation et reservez l'espace le plus adapte a votre journee de travail.</p>
        </div>
    </section>

    <section class="container section contact-layout">
        <div class="card contact-card">
            <h2>Envoyer un message</h2>
            <p class="text-muted mb-2">Pour une visite, une reservation de groupe ou une question avant de venir.</p>

            <?php if($error): ?> <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div> <?php endif; ?>
            <?php if($success): ?> <div class="alert alert-success"><?= htmlspecialchars($success) ?></div> <?php endif; ?>

            <form action="contact.php" method="POST" class="grid-form" data-validate-form>
                <div class="form-group">
                    <label for="nom">Nom complet *</label>
                    <input type="text" name="nom" id="nom" class="form-control" value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" data-validate="required" required>
                    <div class="form-error" id="err-nom"></div>
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" data-validate="required|email" required>
                    <div class="form-error" id="err-email"></div>
                </div>

                <div class="form-group">
                    <label for="telephone">Telephone</label>
                    <input type="tel" name="telephone" id="telephone" class="form-control" value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="sujet">Sujet *</label>
                    <select name="sujet" id="sujet" class="form-control" data-validate="required" required>
                        <?php $selectedSubject = $_POST['sujet'] ?? ''; ?>
                        <option value="">Choisir un sujet</option>
                        <option value="Visite des espaces" <?= $selectedSubject === 'Visite des espaces' ? 'selected' : '' ?>>Visite des espaces</option>
                        <option value="Reservation de groupe" <?= $selectedSubject === 'Reservation de groupe' ? 'selected' : '' ?>>Reservation de groupe</option>
                        <option value="Support reservation" <?= $selectedSubject === 'Support reservation' ? 'selected' : '' ?>>Support reservation</option>
                        <option value="Partenariat" <?= $selectedSubject === 'Partenariat' ? 'selected' : '' ?>>Partenariat</option>
                    </select>
                    <div class="form-error" id="err-sujet"></div>
                </div>

                <div class="form-group form-wide">
                    <label for="message">Message *</label>
                    <textarea name="message" id="message" class="form-control" rows="6" data-validate="required|minLen:10" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    <div class="form-error" id="err-message"></div>
                </div>

                <div class="form-group form-wide callback-box">
                    <label class="checkbox-line">
                        <input type="checkbox" name="rappel_souhaite" value="1" <?= isset($_POST['rappel_souhaite']) ? 'checked' : '' ?>>
                        Je veux etre rappele par l'equipe WorkPods
                    </label>
                    <select name="creneau_rappel" class="form-control mt-1">
                        <?php $selectedCallback = $_POST['creneau_rappel'] ?? ''; ?>
                        <option value="">Creneau prefere</option>
                        <option value="Matin 09:00 - 12:00" <?= $selectedCallback === 'Matin 09:00 - 12:00' ? 'selected' : '' ?>>Matin 09:00 - 12:00</option>
                        <option value="Apres-midi 14:00 - 17:00" <?= $selectedCallback === 'Apres-midi 14:00 - 17:00' ? 'selected' : '' ?>>Apres-midi 14:00 - 17:00</option>
                        <option value="Soir 17:00 - 20:00" <?= $selectedCallback === 'Soir 17:00 - 20:00' ? 'selected' : '' ?>>Soir 17:00 - 20:00</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary form-wide">Envoyer la demande</button>
            </form>
        </div>

        <aside class="contact-side">
            <div class="card contact-info">
                <h3>WorkPods Tunis</h3>
                <p class="text-muted">Centre Urbain Nord, Tunis, Tunisie</p>
                <div class="contact-info-list">
                    <span>Ouvert tous les jours : 08:00 - 22:00</span>
                    <span>Telephone : +216 70 000 000</span>
                    <span>Email : contact@workpods.local</span>
                </div>
                <a class="btn btn-outline" href="https://www.google.com/maps/search/?api=1&query=Centre%20Urbain%20Nord%20Tunis%20Tunisie" target="_blank" rel="noopener">Ouvrir dans Google Maps</a>
            </div>

            <div class="map-frame">
                <iframe
                    title="Localisation WorkPods Tunis"
                    src="https://www.google.com/maps?q=Centre%20Urbain%20Nord%20Tunis%20Tunisie&output=embed"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>

            <div class="feature-card">
                <h3>Rappel rapide</h3>
                <p>Vous pouvez demander a etre rappele avec un creneau prefere. L'equipe retrouve cette demande dans l'administration.</p>
            </div>
        </aside>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
