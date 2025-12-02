<?php
// Limpiar cualquier salida anterior y configurar headers lo antes posible
if (ob_get_level()) {
    ob_end_clean();
}

session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario'])) {
    http_response_code(403);
    die("Acceso denegado");
}

// Obtener datos del POST
$report_name = $_POST['report'] ?? 'Reporte';
$filename = $_POST['filename'] ?? 'reporte.xls';
$data_json = $_POST['data'] ?? '[]';

// Decodificar JSON
$data = json_decode($data_json, true);

// Verificar errores en JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die("Error en los datos JSON: " . json_last_error_msg());
}

// Verificar que hay datos
if (empty($data) || !is_array($data) || count($data) === 0 || !is_array($data[0])) {
    http_response_code(400);
    die("No hay datos válidos para exportar.");
}

// Limpiar nombre de archivo
$filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
if (!str_ends_with(strtolower($filename), '.xls')) {
    $filename .= '.xls';
}

// Headers para descarga de Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// BOM UTF-8 para caracteres especiales
echo "\xEF\xBB\xBF";

// Inicio del HTML con estilos mejorados
$html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    body { 
        font-family: Calibri, Arial, sans-serif; 
        font-size: 12px;
    }
    .report-title {
        font-size: 18px;
        font-weight: bold;
        text-align: center;
        margin-bottom: 20px;
    }
    table { 
        border-collapse: collapse; 
        width: 100%; 
        border: 2px solid #356bc7ff;
    }
    th, td { 
        border: 1px solid #A0AEC0; 
        padding: 8px; 
        vertical-align: top;
    }
    th { 
        background-color: #2C5282; 
        color: white; 
        font-weight: bold; 
        text-transform: uppercase; 
        text-align: center;
        font-size: 13px;
    }
    tr:nth-child(even) { 
        background-color: #EDF2F7; 
    }
    .number {
        text-align: right;
    }
    .main-cell {
        font-weight: bold;
        color: #2D3748;
    }
</style>
</head>
<body>';

// Título del reporte
$html .= '<div class="report-title">' . htmlspecialchars($report_name, ENT_QUOTES, 'UTF-8') . '</div>';

$html .= '<table>';

// Encabezados
$html .= '<thead><tr>';
$headers = array_keys($data[0]);
foreach ($headers as $header) {
    $html .= '<th>' . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . '</th>';
}
$html .= '</tr></thead>';

// Cuerpo de la tabla
$html .= '<tbody>';

// Calcular totales para columnas numéricas
$totals = [];
$has_numeric_columns = false;

// Identificar columnas numéricas y calcular totales
// Excluir columnas que no deben sumarse (precios unitarios, códigos, etc.)
$columns_to_exclude = ['PRECIO_UNITARIO', 'PRECIO UNITARIO', 'CPC', 'PROCESO', 'UNIDAD', 'BLOQUE', 'PROYECTO', 'DESCRIPCION', 'CREADO_POR'];

foreach ($headers as $header) {
    $total = 0;
    $is_numeric_column = false;
    $should_sum = true;
    
    // Verificar si esta columna debe excluirse de la suma
    foreach ($columns_to_exclude as $exclude) {
        if (stripos($header, $exclude) !== false) {
            $should_sum = false;
            break;
        }
    }
    
    if ($should_sum) {
        foreach ($data as $row) {
            $cell_value = $row[$header] ?? '';
            if (is_numeric($cell_value) && !empty($cell_value)) {
                $total += (float)$cell_value;
                $is_numeric_column = true;
            }
        }
    }
    
    if ($is_numeric_column && $should_sum) {
        $totals[$header] = $total;
        $has_numeric_columns = true;
    } else {
        $totals[$header] = '';
    }
}

foreach ($data as $row) {
    $html .= '<tr>';
    $is_first_cell = true;
    foreach ($headers as $header) {
        $cell_value = $row[$header] ?? '';
        $is_numeric = is_numeric($cell_value) && !empty($cell_value);
        
        $classes = [];
        if ($is_numeric) $classes[] = 'number';
        if ($is_first_cell) $classes[] = 'main-cell';
        
        $class_attr = empty($classes) ? '' : ' class="' . implode(' ', $classes) . '"';
        $cell_content = htmlspecialchars($cell_value, ENT_QUOTES, 'UTF-8');

        if (in_array(substr($cell_content, 0, 1), ["=", "+", "-", "@"])) {
            $cell_content = "'" . $cell_content;
        }

        $html .= '<td' . $class_attr . '>' . $cell_content . '</td>';
        $is_first_cell = false;
    }
    $html .= '</tr>';
}

// Agregar fila de totales si hay columnas numéricas
if ($has_numeric_columns) {
    $html .= '<tr style="background-color: #2C5282; color: white; font-weight: bold; border-top: 3px solid #1A365D;">';
    $is_first_cell = true;
    foreach ($headers as $header) {
        if ($is_first_cell) {
            $html .= '<td style="text-align: center; font-weight: bold; font-size: 14px;">TOTAL</td>';
            $is_first_cell = false;
        } else {
            $total_value = $totals[$header];
            if ($total_value !== '' && $total_value != 0) {
                $formatted_total = number_format($total_value, 2);
                $html .= '<td class="number" style="font-weight: bold; font-size: 14px;">' . $formatted_total . '</td>';
            } else {
                $html .= '<td></td>';
            }
        }
    }
    $html .= '</tr>';
}

$html .= '</tbody></table></body></html>';

echo $html;
exit;
?>
