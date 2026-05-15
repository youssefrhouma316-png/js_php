<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$message = "";

function ensureMessagesTable(PDO $db): void
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

ensureMessagesTable($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_message'])) {
    $messageId = (int)($_POST['message_id'] ?? 0);
    $status = $_POST['statut'] ?? 'nouveau';
    $allowed = ['nouveau', 'lu', 'traite'];

    if ($messageId > 0 && in_array($status, $allowed, true)) {
        $stmt = $db->prepare("UPDATE contact_messages SET statut = ? WHERE id = ?");
        $stmt->execute([$status, $messageId]);
        $message = "Statut du message mis a jour.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    $messageId = (int)($_POST['message_id'] ?? 0);
    if ($messageId > 0) {
        $stmt = $db->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        $message = "Message supprime.";
    }
}

$messages = $db->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();

include '../includes/header.php';
?>

<main class="container section">
    <div class="flex-between align-center mb-3">
        <div>
            <span class="tag">Administration</span>
            <h1>Messages de contact</h1>
        </div>
        <a href="dashboard.php" class="btn btn-outline">Retour dashboard</a>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <section class="card admin-section">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Contact</th>
                        <th>Sujet</th>
                        <th>Message</th>
                        <th>Rappel</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($messages)): ?>
                        <tr><td colspan="7" class="text-center">Aucun message pour le moment.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($messages as $item): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($item['nom']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($item['email']) ?></small><br>
                                <small class="text-muted"><?= htmlspecialchars($item['telephone'] ?? '') ?></small>
                            </td>
                            <td><?= htmlspecialchars($item['sujet']) ?></td>
                            <td class="message-cell"><?= nl2br(htmlspecialchars($item['message'])) ?></td>
                            <td>
                                <?php if ((int)($item['rappel_souhaite'] ?? 0) === 1): ?>
                                    <span class="pill pill-confirmed">Oui</span><br>
                                    <small class="text-muted"><?= htmlspecialchars($item['creneau_rappel'] ?? 'Creneau non precise') ?></small>
                                <?php else: ?>
                                    <span class="text-muted">Non</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($item['created_at']) ?></td>
                            <td>
                                <form method="POST" class="inline-status-form">
                                    <input type="hidden" name="message_id" value="<?= (int)$item['id'] ?>">
                                    <select name="statut" class="form-control">
                                        <option value="nouveau" <?= $item['statut'] === 'nouveau' ? 'selected' : '' ?>>Nouveau</option>
                                        <option value="lu" <?= $item['statut'] === 'lu' ? 'selected' : '' ?>>Lu</option>
                                        <option value="traite" <?= $item['statut'] === 'traite' ? 'selected' : '' ?>>Traite</option>
                                    </select>
                                    <button type="submit" name="update_message" class="btn btn-primary btn-sm">OK</button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Supprimer ce message ?');">
                                    <input type="hidden" name="message_id" value="<?= (int)$item['id'] ?>">
                                    <button type="submit" name="delete_message" class="btn btn-danger btn-sm">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php include '../includes/footer.php'; ?>
