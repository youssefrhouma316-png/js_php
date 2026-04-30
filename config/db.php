<?php
// config/db.php
function getDB() {
    $host = "localhost";
    $db   = "coworking_db"; // Doit correspondre au nom dans HeidiSQL
    $user = "root";         // Utilisateur par défaut Laragon
    $pass = "";             // MOT DE PASSE VIDE par défaut sur Laragon

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        // C'est ici que l'erreur JSON est générée
        die(json_encode(["error" => "Connexion à la base de données impossible."]));
    }
}
?>