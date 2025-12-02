<?php
set_time_limit(300);
session_start();

echo "<pre>";
echo "Iniciando script de importación de asignaciones...\n";

require_once __DIR__ . '/includes/db.php';

// --- 1. Suplantación de Identidad (Admin) ---
$adminUser = $pdo->query("SELECT id_usuario, usuario FROM usuarios WHERE id_rol = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$adminUser) {
    die("ERROR: No se encontró un usuario administrador (id_rol = 1) para ejecutar el script.\n");
}
$_SESSION['id_usuario'] = $adminUser['id_usuario'];
$_SESSION['usuario'] = $adminUser['usuario'];
echo "Ejecutando como usuario: {$adminUser['usuario']} (ID: {$adminUser['id_usuario']})\n";


// --- 2. Definición de Datos ---
$comunidades = [
    "Pio Jaramillo", "Tres Palmas", "Aso. Flor de Los Rios", "Aso. El Porvenir", 
    "Sol Nacietne", "Triunfo Uno", "Flor de Mayo", "Aso. Guanta 8", "Guayacanes", 
    "Aso. Rivereños Amazonicos", "SOTE", "Ribereños I", "Ribereños II", 
    "Emprendimientos - Cadena comercialización y Rivereños amazónicos", 
    "Aso. El porvenir", "Aso. Buen Camino", "Cofán Dureno"
];

$proyectos_nombres = [
    "MANTENIMIENTO DE CACAO", "AVICULTURA FAMILIAR", "PISCICULTURA FAMILIAR",
    "PLANTACIÓN NUEVA DE CACAO", "FRUTALES", "PLANTACIÓN PLÁTANO",
    "MARQUESINAS SOLARES", "KIT HERRAMIENTAS DE CAMPO", "MAÍZ HÍBRIDO",
    "HUERTOS FAMILIARES", "EQUIPAMIENTO", "FRUTALES"
];

$matriz = [
    // Pio Jaramillo, Tres Palmas, Aso. Flor de Los Rios, Aso. El Porvenir, Sol Nacietne, Triunfo Uno, Flor de Mayo, Aso. Guanta 8, Guayacanes, Aso. Rivereños Amazonicos, SOTE, Ribereños I, Ribereños II, Emprendimientos..., Aso. El porvenir, Aso. Buen Camino, Cofán Dureno
    [45, 20, 10, 10, 30, 60, 35, 12, 20, 0, 0, 0, 40, 0, 0, 0, 0], // MANTENIMIENTO DE CACAO
    [25, 30, 25, 15, 30, 35, 45, 15, 12, 12, 30, 20, 25, 0, 0, 0, 50], // AVICULTURA FAMILIAR
    [0, 20, 5, 5, 10, 0, 10, 0, 5, 5, 10, 5, 10, 0, 0, 0, 10], // PISCICULTURA FAMILIAR
    [0, 20, 15, 15, 30, 10, 0, 10, 10, 0, 0, 0, 0, 0, 0, 0, 60], // PLANTACIÓN NUEVA DE CACAO
    [0, 0, 0, 0, 0, 0, 0, 0, 5, 0, 0, 0, 0, 25, 15, 0, 0], // FRUTALES (1)
    [1, 0, 0, 0, 0, 15, 5, 5, 5, 0, 0, 0, 0, 0, 0, 0, 0], // PLANTACIÓN PLÁTANO
    [0, 0, 0, 0, 30, 0, 12, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], // MARQUESINAS SOLARES
    [0, 0, 5, 5, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], // KIT HERRAMIENTAS DE CAMPO
    [0, 0, 0, 0, 15, 15, 5, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], // MAÍZ HÍBRIDO
    [0, 8, 0, 0, 0, 0, 10, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], // HUERTOS FAMILIARES
    [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0], // EQUIPAMIENTO
    [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 25, 15, 0, 0], // FRUTALES (2)
];

echo "Datos de la matriz cargados.\n";

// --- 3. Setup del Bloque ---
$bloque_nombre = "IMPORTACION_MASIVA";
$stmt = $pdo->prepare("SELECT bloque FROM catalogo_bloques WHERE bloque = ?");
$stmt->execute([$bloque_nombre]);
if (!$stmt->fetch()) {
    $pdo->prepare("INSERT INTO catalogo_bloques (bloque, zona, provincia) VALUES (?, ?, ?)")
        ->execute([$bloque_nombre, 'ZONA_IMPORT', 'PROVINCIA_IMPORT']);
    echo "Bloque '{$bloque_nombre}' creado.\n";
} else {
    echo "Bloque '{$bloque_nombre}' ya existe.\n";
}
$st = $pdo->prepare("SELECT zona, provincia FROM catalogo_bloques WHERE bloque=? LIMIT 1");
$st->execute([$bloque_nombre]);
[$zona, $provincia] = $st->fetch(PDO::FETCH_NUM);


// --- 4. Obtener IDs de Proyectos ---
$proyectos_ids = [];
$stmt = $pdo->query("SELECT id_proyecto, nombre FROM proyectos ORDER BY id_proyecto");
$proyectos_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

$proyectos_mapeados = [];
foreach($proyectos_nombres as $nombre_buscado) {
    $encontrado = false;
    foreach ($proyectos_db as $key => $proyecto_db) {
        if (strtoupper($proyecto_db['nombre']) === strtoupper($nombre_buscado)) {
            $proyectos_mapeados[] = $proyecto_db['id_proyecto'];
            unset($proyectos_db[$key]); // Eliminar para no volver a encontrarlo (manejo de duplicados)
            $encontrado = true;
            break;
        }
    }
    if (!$encontrado) {
        die("ERROR: No se encontró el proyecto '{$nombre_buscado}' en la base de datos.\n");
    }
}
echo "IDs de proyectos mapeados correctamente.\n";


// --- 5. Proceso de Inserción ---
$hoy = date('Y-m-d');
$idUsuario = $_SESSION['id_usuario'];
$total_asignaciones = 0;

try {
    $pdo->beginTransaction();
    echo "Iniciando transacción de base de datos...\n\n";

    foreach ($matriz as $i => $fila) {
        foreach ($fila as $j => $cantidad_proyectos) {
            if ($cantidad_proyectos > 0) {
                $id_proyecto = $proyectos_mapeados[$i];
                $comunidad_nombre = mb_strtoupper($comunidades[$j], 'UTF-8');
                
                echo "Procesando: [{$comunidad_nombre}] -> [{$proyectos_nombres[$i]}] -> Cantidad: {$cantidad_proyectos}\n";

                // Insertar cabecera de la asignación
                $sqlCab = "INSERT INTO proyecto_asignacion
                    (id_proyecto, bloque, zona, provincia, comunidad_nombre, cantidad_proyectos, beneficiarios, fecha_asignacion, id_tecnico, observaciones, estado_asignacion, fecha_estado)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())";
                $stmt = $pdo->prepare($sqlCab);
                $stmt->execute([$id_proyecto, $bloque_nombre, $zona, $provincia, $comunidad_nombre, $cantidad_proyectos, 0, $hoy, $idUsuario, 'Importado automáticamente']);
                $id_asignacion = (int)$pdo->lastInsertId();

                // Obtener productos del proyecto
                $stmtProd = $pdo->prepare("SELECT id_producto, cantidad_default FROM proyecto_productos WHERE id_proyecto = ?");
                $stmtProd->execute([$id_proyecto]);
                $productos_del_proyecto = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

                if ($productos_del_proyecto) {
                    $sqlDet = "INSERT INTO proyecto_asignacion_detalle (id_asignacion, id_producto, cantidad) VALUES (?,?,?)";
                    $stDet = $pdo->prepare($sqlDet);
                    
                    $sqlEntrega = "INSERT INTO producto_entregas (id_asignacion, id_producto, cantidad_asignada, cantidad_entregada, entregado) VALUES (?,?,?,0,0)";
                    $stEntrega = $pdo->prepare($sqlEntrega);
                    
                    foreach($productos_del_proyecto as $pr){
                        $idp = (int)$pr['id_producto'];
                        $cant = (float)$pr['cantidad_default'];
                        if($idp > 0 && $cant > 0){ 
                            $stDet->execute([$id_asignacion, $idp, $cant]);
                            $stEntrega->execute([$id_asignacion, $idp, $cant]);
                        }
                    }
                    
                    $sqlResumen = "INSERT INTO asignacion_entregas_resumen (id_asignacion, total_productos, productos_entregados, porcentaje_entrega, completado) VALUES (?,?,0,0.00,0)";
                    $stResumen = $pdo->prepare($sqlResumen);
                    $stResumen->execute([$id_asignacion, count($productos_del_proyecto)]);
                }
                $total_asignaciones++;
            }
        }
    }

    $pdo->commit();
    echo "\n--- TRANSACCIÓN COMPLETADA ---\n";
    echo "Se han insertado {$total_asignaciones} asignaciones correctamente.\n";

} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    die("\n--- ERROR DURANTE LA TRANSACCIÓN ---\nSe revirtieron todos los cambios.\nMensaje: " . $e->getMessage() . "\n");
}

echo "\nScript finalizado.\n";
echo "</pre>";
?>
