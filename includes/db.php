<?php
$DB_HOST = '127.0.0.1';   // mejor que 'localhost'
$DB_PORT = '3306';        // cambia si usas otro puerto (a veces 3307)
$DB_NAME = 'Proyectos_agronomos';  // verifica el nombre exacto de tu BD
$DB_USER = 'root';
$DB_PASS = 'Petroecuador';

$dsn = "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4";
try {
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  die('Error de conexión: ' . $e->getMessage());
}

// al final de includes/db.php
$GLOBALS['pdo'] = $pdo;               // por si acaso
function db(){ return $GLOBALS['pdo']; }  // helper opcional
