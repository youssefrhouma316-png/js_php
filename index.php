<?php 
require_once 'config/db.php'; 
include 'includes/header.php'; 

$db = getDB();
// On récupère tous les pods pour les afficher
$pods = $db->query("SELECT * FROM pods ORDER BY nom ASC")->fetchAll();
?>

<main>
    <section class="hero-section">
        <div class="container hero-grid">
            <div class="hero-copy">
                <span class="hero-pill">Espaces privés prêts à réserver</span>
                <h1 class="hero-title">Trouvez votre <em>Pod</em> de travail idéal</h1>
                <p class="hero-subtitle">Des espaces privés, isolés et équipés pour une productivité maximale, 
                    avec réservation en ligne simple et un confort professionnel.</p>
                <div class="hero-actions">
                    <a href="#pods" class="btn btn-primary">Voir les Pods</a>
                    <a href="reserve.php" class="btn btn-outline">Réserver maintenant</a>
                </div>
                <div class="hero-stats">
                    <div class="stat-pill">+24 Pods disponibles</div>
                    <div class="stat-pill">Wifi haut débit</div>
                    <div class="stat-pill">Accessible 24/7</div>
                </div>
            </div>
            <div class="hero-visual">
                <img src="assets/uploads/hero-pod.svg" alt="Illustration de Pod" class="hero-card-img">
                <div class="hero-card">
                    <div class="hero-card-label">Sélection recommandée</div>
                    <h3>Pod Premium</h3>
                    <p class="text-muted">Isolation phonique, bureau ergonomique, alimentation et écran 24".</p>
                    <div class="hero-card-meta">
                        <span class="pill pill-confirmed">Disponible</span>
                        <span class="hero-price">35 DT / heure</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="container section gallery-section">
        <div class="section-header">
            <span class="tag">Nos Pods</span>
            <h2>Choisissez le type d'espace qui vous convient</h2>
            <p>Trois ambiances prêtes à accueillir votre concentration, vos réunions ou vos sessions créatives.</p>
        </div>
        <div class="gallery-grid">
            <div class="card gallery-card">
                <div class="card-image-container">
                    <img src="assets/uploads/pod-private.svg" alt="Pod privé" class="card-img">
                </div>
                <div class="card-body">
                    <h3 class="card-title">Pod Privé</h3>
                    <p class="text-muted">Espace isolé pour les appels, la concentration et le travail individuel.</p>
                </div>
            </div>
            <div class="card gallery-card">
                <div class="card-image-container">
                    <img src="assets/uploads/pod-team.svg" alt="Pod équipe" class="card-img">
                </div>
                <div class="card-body">
                    <h3 class="card-title">Pod Équipe</h3>
                    <p class="text-muted">Un vrai mini-studio pour les sessions de groupe, workshops et brainstormings.</p>
                </div>
            </div>
            <div class="card gallery-card">
                <div class="card-image-container">
                    <img src="assets/uploads/pod-zen.svg" alt="Pod zen" class="card-img">
                </div>
                <div class="card-body">
                    <h3 class="card-title">Pod Zen</h3>
                    <p class="text-muted">Ambiance apaisante pour les pauses créatives, le coaching ou la lecture.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="pods" class="container section">
        <?php if (empty($pods)): ?>
            <div class="empty-state">
                <h2>Aucun Pod trouvé pour le moment</h2>
                <p>Nous travaillons actuellement sur l’ajout de nouveaux espaces. Revenez bientôt ou contactez-nous pour une assistance rapide.</p>
            </div>
        <?php else: ?>
            <div class="pods-grid">
                <?php foreach($pods as $pod): ?>
                    <div class="card pod-card">
                        <div class="card-image-container">
                            <img src="assets/uploads/<?= htmlspecialchars($pod['image_url'] ?? 'default-pod.svg') ?>" 
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
        <?php endif; ?>
    </section>
</main>

<?php include 'includes/footer.php'; ?>