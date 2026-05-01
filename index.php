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
                <span class="hero-pill">Votre espace, votre zone de productivité</span>
                <h1 class="hero-title">Votre Pod privé, puissant et prêt à l'emploi</h1>
                <p class="hero-subtitle">Réservez un espace calme, connecté et confortable pour travailler, créer ou recevoir des clients rapidement.</p>
                <div class="hero-actions">
                    <a href="#pods" class="btn btn-primary">Explorer les Pods</a>
                    <a href="reserve.php" class="btn btn-outline">Réserver maintenant</a>
                </div>
                <div class="hero-stats">
                    <div class="stat-pill">+24 Pods disponibles</div>
                    <div class="stat-pill">Connexion Fibre</div>
                    <div class="stat-pill">Accessible 24/7</div>
                </div>
                <div class="partner-strip">
                    <span>Partenaires :</span>
                    <div class="partner-logos">
                        <span>Slack</span>
                        <span>Amazon</span>
                        <span>Google</span>
                        <span>SpaceX</span>
                    </div>
                </div>
            </div>
            <div class="hero-visual">
                <div class="hero-image-frame">
                    <img src="assets/pics/pod.jpg" alt="Pod Premium" class="hero-main-img">
                    <div class="hero-card hero-card-floating">
                        <div class="hero-card-label">Sélection recommandée</div>
                        <h3>Pod Premium</h3>
                        <p class="text-muted">Isolation phonique, bureau ergonomique et écran 24" pour un confort pro.</p>
                        <div class="hero-card-meta">
                            <span class="pill pill-confirmed">Disponible</span>
                            <span class="hero-price">35 DT / heure</span>
                        </div>
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
                    <img src="assets/pics/pod%20prive.jpg" alt="Pod privé" class="card-img">
                </div>
                <div class="card-body">
                    <h3 class="card-title">Pod Privé</h3>
                    <p class="text-muted">Espace isolé pour les appels, la concentration et le travail individuel.</p>
                </div>
            </div>
            <div class="card gallery-card">
                <div class="card-image-container">
                    <img src="assets/pics/pod%20equipe.jpg" alt="Pod équipe" class="card-img">
                </div>
                <div class="card-body">
                    <h3 class="card-title">Pod Équipe</h3>
                    <p class="text-muted">Un vrai mini-studio pour les sessions de groupe, workshops et brainstormings.</p>
                </div>
            </div>
            <div class="card gallery-card">
                <div class="card-image-container">
                    <img src="assets/pics/podzen.jpg" alt="Pod zen" class="card-img">
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
                            <img src="assets/pics/<?= htmlspecialchars($pod['image'] ?? 'default-pod.jpg') ?>" 
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