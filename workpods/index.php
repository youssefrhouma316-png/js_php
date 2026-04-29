<?php 
require_once 'config/db.php'; 
include 'includes/header.php'; 

$db = getDB();
// On récupère tous les pods pour les afficher
$pods = $db->query("SELECT * FROM pods ORDER BY nom ASC")->fetchAll();
?>

<main>
    <section class="hero-section">
        <div class="container">
            <h1 class="hero-title">Trouvez votre <em>Pod</em> de travail idéal</h1>
            <p class="hero-subtitle">Des espaces privés, isolés et équipés pour une productivité maximale.</p>
        </div>
    </section>

    <section class="container section">
        <div class="pods-grid">
            <?php foreach($pods as $pod): ?>
                <div class="card pod-card">
                    <div class="card-image-container">
                        <img src="assets/uploads/<?= htmlspecialchars($pod['image_url'] ?? 'default-pod.jpg') ?>" 
                             alt="<?= htmlspecialchars($pod['nom']) ?>" class="card-img">
                    </div>
                    <div class="card-body">
                        <h3 class="card-title"><?= htmlspecialchars($pod['nom']) ?></h3>
                        <p class="text-muted"><?= htmlspecialchars($pod['description']) ?></p>
                        
                        <div class="divider"></div>
                        
                        <div class="flex-between align-center">
                            <div class="pod-price">
                                <?= number_format($pod['prix_heure'], 2) ?> DT<span>/heure</span>
                            </div>
                            <a href="reserve.php?id=<?= $pod['id'] ?>" class="btn btn-primary">Réserver</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>