<?php
// Cargar el autoloader de Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Cargar las variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Ahora, las variables están disponibles en $_ENV
$DB_HOST = $_ENV['DB_HOST'];
$DB_PORT = '3306'; // Opcional, podrías añadirlo a .env si cambia
$DB_NAME = $_ENV['DB_NAME'];
$DB_USER = $_ENV['DB_USER'];
$DB_PASS = $_ENV['DB_PASS'];

$dsn = "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4";
try {
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  // En producción, es mejor no mostrar el mensaje de error detallado.
  // Podríamos manejar esto con la configuración de errores que discutimos.
  error_log('Error de conexión a la BD: ' . $e->getMessage());
  die('Error de conexión a la base de datos. Por favor, intente más tarde.');
}

// al final de includes/db.php
$GLOBALS['pdo'] = $pdo;               // por si acaso
function db(){ return $GLOBALS['pdo']; }  // helper opcional
