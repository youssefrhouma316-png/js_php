<?php
/* ============================================
   WorkPods - config/db.php
   Connexion PDO à MySQL via Laragon
   ============================================ */

define('DB_HOST', 'localhost');
define('DB_NAME', 'coworking_db');
define('DB_USER', 'root');
define('DB_PASS', '');          // Laragon : mot de passe vide par défaut
define('DB_CHARSET', 'utf8mb4');

define('BASE_URL', 'http://workpods.test'); // adapter selon Laragon
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', BASE_URL . '/assets/uploads/');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // En production : logger l'erreur, ne pas afficher le message
            die(json_encode(['error' => 'Connexion à la base de données impossible.']));
        }
    }
    return $pdo;
}
