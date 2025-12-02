<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/includes/db.php';

$mensaje = '';
$errores = [];
$asignaciones_creadas = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['data'])) {
    set_time_limit(300);

    $data = trim($_POST['data']);
    $lineas = explode("\n", $data);

    if (count($lineas) < 3) {
        $errores[] = "Los datos no tienen el formato esperado. Se requieren al menos 3 líneas (2 de cabecera y 1 de proyecto).";
    } else {
        // 1. Parsear cabecera de comunidades
        $cabecera_comunidades = str_getcsv($lineas[1], "\t");
        $comunidades_nombres = array_slice($cabecera_comunidades, 2); // Omitir las primeras 2 columnas
        $comunidades_nombres = array_map('trim', $comunidades_nombres);

        // 2. Parsear filas de proyectos
        $filas_proyectos = array_slice($lineas, 2);
        $proyectos_data = [];
        foreach ($filas_proyectos as $i => $linea) {
            $columnas = str_getcsv($linea, "\t");
            if (count($columnas) > 2 && !empty(trim($columnas[2]))) {
                $proyectos_data[] = [
                    'nombre' => trim($columnas[2]),
                    'cantidades' => array_slice($columnas, 3)
                ];
            }
        }

        // 3. Validar datos y obtener IDs
        $stmt = $pdo->query("SELECT id, nombre FROM proyectos");
        $proyectos_db_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $proyectos_db = [];
        foreach($proyectos_db_raw as $p) {
            $proyectos_db[strtoupper(trim($p['nombre']))] = $p['id'];
        }

        $proyectos_mapeados = [];
        foreach ($proyectos_data as $i => $pd) {
            $nombre_proyecto_upper = strtoupper($pd['nombre']);
            if (isset($proyectos_db[$nombre_proyecto_upper])) {
                $proyectos_mapeados[$i] = [
                    'id' => $proyectos_db[$nombre_proyecto_upper],
                    'nombre' => $pd['nombre'],
                    'cantidades' => $pd['cantidades']
                ];
            } else {
                $errores[] = "El proyecto '{$pd['nombre']}' (fila " . ($i + 1) . ") no fue encontrado en la base de datos. La importación se ha cancelado.";
            }
        }

        // 4. Si no hay errores, proceder con la inserción
        if (empty($errores)) {
            try {
                $pdo->beginTransaction();

                // Setup del Bloque
                $bloque_nombre = "IMPORTACION_MASIVA";
                $stmt = $pdo->prepare("SELECT bloque FROM catalogo_bloques WHERE bloque = ?");
                $stmt->execute([$bloque_nombre]);
                if (!$stmt->fetch()) {
                    $pdo->prepare("INSERT INTO catalogo_bloques (bloque, zona, provincia) VALUES (?, ?, ?)")
                        ->execute([$bloque_nombre, 'ZONA_IMPORT', 'PROVINCIA_IMPORT']);
                }
                $st = $pdo->prepare("SELECT zona, provincia FROM catalogo_bloques WHERE bloque=? LIMIT 1");
                $st->execute([$bloque_nombre]);
                [$zona, $provincia] = $st->fetch(PDO::FETCH_NUM);

                $hoy = date('Y-m-d');
                $idUsuario = $_SESSION['id_usuario'];

                $sqlCab = "INSERT INTO proyecto_asignacion
                    (id_proyecto, bloque, zona, provincia, comunidad_nombre, cantidad_proyectos, beneficiarios, fecha_asignacion, id_tecnico, observaciones, estado_asignacion, fecha_estado)
                    VALUES (?,?,?,?,?,?,?,?,?, 'Importado automáticamente', 'PROCESADO', NOW())";
                $stmtInsert = $pdo->prepare($sqlCab);

                foreach ($proyectos_mapeados as $proyecto) {
                    foreach ($proyecto['cantidades'] as $j => $cantidad) {
                        $cantidad = (int)trim($cantidad);
                        if ($cantidad > 0 && isset($comunidades_nombres[$j])) {
                            $comunidad_nombre = mb_strtoupper(trim($comunidades_nombres[$j]), 'UTF-8');
                            
                            $stmtInsert->execute([
                                $proyecto['id'],
                                $bloque_nombre,
                                $zona,
                                $provincia,
                                $comunidad_nombre,
                                $cantidad,
                                0, // Beneficiarios
                                $hoy,
                                $idUsuario
                            ]);
                            $asignaciones_creadas++;
                        }
                    }
                }

                $pdo->commit();
                $mensaje = "Proceso completado. Se crearon {$asignaciones_creadas} nuevas asignaciones.";

            } catch (Throwable $e) {
                $pdo->rollBack();
                $errores[] = "Error durante la inserción en la base de datos: " . $e->getMessage();
            }
        }
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3>Subir Asignaciones Masivas</h3>
                        <p>Pegue los datos de asignación en formato de texto (separado por tabulaciones).</p>
                    </div>
                    <div class="card-body">
                        <form action="subir_asignaciones.php" method="post">
                            <div class="form-group">
                                <label for="data">Datos de Asignaciones</label>
                                <textarea name="data" id="data" class="form-control" rows="15" placeholder="Pegue aquí los datos de la hoja de cálculo."><?php echo isset($_POST['data']) ? htmlspecialchars($_POST['data']) : ''; ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Procesar Asignaciones</button>
                        </form>

                        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                        <div class="mt-4">
                            <h4>Resultados del Proceso</h4>
                            <?php if (!empty($mensaje)): ?>
                                <div class="alert alert-success">
                                    <?php echo htmlspecialchars($mensaje); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($errores)): ?>
                                <div class="alert alert-danger">
                                    <p><strong>Se encontraron los siguientes errores:</strong></p>
                                    <ul>
                                        <?php foreach ($errores as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
