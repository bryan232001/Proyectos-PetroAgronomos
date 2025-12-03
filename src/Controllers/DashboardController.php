<?php
// src/Controllers/DashboardController.php
namespace App\Controllers;

use App\Models\StatisticModel;

class DashboardController {
    private $pdo;
    private $statisticModel;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->statisticModel = new StatisticModel($pdo);
    }

    public function index() {
        if (!isset($_SESSION['usuario'])) {
            header('Location: login.php');
            exit;
        }
        
        $usuario_actual = $_SESSION['usuario'];
        $rol_usuario = $_SESSION['id_rol'] ?? 2;
        $es_admin = ($rol_usuario == 1);
        
        try {
            $stats = $this->statisticModel->getDashboardStatistics($usuario_actual, $es_admin);
        } catch (\PDOException $e) {
            error_log("Error en DashboardController: " . $e->getMessage());
            $stats = [
                'totalProyectos' => 0,
                'totalBeneficiariosCompletados' => 0,
                'inversionCompletados' => 0,
                'inversionPendiente' => 0,
                'inversionEsperada' => 0,
                'porcentajeEntregados' => 0,
                'proyectosCompletados' => 0,
                'totalAsignaciones' => 0,
                'proyectosEnProgreso' => 0,
                'proyectosCompletadosRecientes' => [],
            ];
        }
        
        $pageTitle = 'Dashboard';
        $is_dashboard = true;

        $data = array_merge($stats, [
            'pageTitle' => $pageTitle,
            'is_dashboard' => $is_dashboard,
            'es_admin' => $es_admin
        ]);

        $this->render('dashboard', $data);
    }

    private function render($view, $data = []) {
        extract($data);
        
        require_once __DIR__ . '/../../includes/header.php';
        require_once __DIR__ . '/../../templates/' . $view . '.php';
        require_once __DIR__ . '/../../includes/footer.php';
    }
}
