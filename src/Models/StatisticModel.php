<?php
// src/Models/StatisticModel.php
namespace App\Models;

class StatisticModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getDashboardStatistics($userId, $isAdmin) {
        $stats = [];

        // 1. NÚMERO DE PROYECTOS CREADOS
        if ($isAdmin) {
            $stats['totalProyectos'] = $this->pdo->query("SELECT COUNT(*) FROM proyectos WHERE activo = 1")->fetchColumn() ?? 0;
        } else {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM proyectos WHERE creado_por = ? AND activo = 1");
            $stmt->execute([$userId]);
            $stats['totalProyectos'] = $stmt->fetchColumn() ?? 0;
        }

        // 2. BENEFICIARIOS DE PROYECTOS COMPLETADOS
        $stats['totalBeneficiariosCompletados'] = $this->pdo->query("
            SELECT COALESCE(SUM(pa.beneficiarios), 0) 
            FROM proyecto_asignacion pa
            JOIN asignacion_entregas_resumen aer ON aer.id_asignacion = pa.id_asignacion
            WHERE aer.completado = 1
        ")->fetchColumn() ?? 0;

        // 3. INVERSIÓN TOTAL DE PROYECTOS COMPLETADOS
        $stats['inversionCompletados'] = $this->pdo->query("
            SELECT COALESCE(SUM(pad.cantidad * cp.precio_unit), 0) 
            FROM proyecto_asignacion_detalle pad
            JOIN catalogo_productos cp ON cp.id_producto = pad.id_producto
            JOIN asignacion_entregas_resumen aer ON aer.id_asignacion = pad.id_asignacion
            WHERE aer.completado = 1
        ")->fetchColumn() ?? 0;

        // 4. INVERSIÓN PENDIENTE (proyectos no completados)
        $stats['inversionPendiente'] = $this->pdo->query("
            SELECT COALESCE(SUM(pad.cantidad * cp.precio_unit), 0) 
            FROM proyecto_asignacion_detalle pad
            JOIN catalogo_productos cp ON cp.id_producto = pad.id_producto
            LEFT JOIN asignacion_entregas_resumen aer ON aer.id_asignacion = pad.id_asignacion
            WHERE aer.completado IS NULL OR aer.completado = 0
        ")->fetchColumn() ?? 0;

        // 5. INVERSIÓN ESPERADA TOTAL (completados + pendientes)
        $stats['inversionEsperada'] = $stats['inversionCompletados'] + $stats['inversionPendiente'];

        // 6. PORCENTAJE DE PROYECTOS ENTREGADOS
        $totalAsignaciones = $this->pdo->query("SELECT COUNT(*) FROM proyecto_asignacion")->fetchColumn() ?? 0;
        $proyectosCompletados = $this->pdo->query("
            SELECT COUNT(*) 
            FROM asignacion_entregas_resumen 
            WHERE completado = 1
        ")->fetchColumn() ?? 0;
        
        $stats['totalAsignaciones'] = $totalAsignaciones;
        $stats['proyectosCompletados'] = $proyectosCompletados;
        $stats['porcentajeEntregados'] = $totalAsignaciones > 0 ? ($proyectosCompletados / $totalAsignaciones) * 100 : 0;

        // 7. PROYECTOS EN PROGRESO
        $stats['proyectosEnProgreso'] = $this->pdo->query("
            SELECT COUNT(*) 
            FROM proyecto_asignacion pa
            LEFT JOIN asignacion_entregas_resumen aer ON aer.id_asignacion = pa.id_asignacion
            WHERE aer.completado IS NULL OR aer.completado = 0
        ")->fetchColumn() ?? 0;

        // 8. Proyectos completados recientes (últimos 30 días)
        $stats['proyectosCompletadosRecientes'] = $this->pdo->query("
            SELECT 
                pa.id_asignacion,
                pa.comunidad_nombre,
                pa.bloque,
                p.nombre as proyecto_nombre,
                aer.fecha_completado,
                aer.porcentaje_entrega
            FROM asignacion_entregas_resumen aer
            JOIN proyecto_asignacion pa ON pa.id_asignacion = aer.id_asignacion
            JOIN proyectos p ON p.id = pa.id_proyecto
            WHERE aer.completado = 1 
            AND aer.fecha_completado >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY aer.fecha_completado DESC
            LIMIT 10
        ")->fetchAll() ?? [];

        return $stats;
    }
}
