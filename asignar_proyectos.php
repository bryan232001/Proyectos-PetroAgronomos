<?php
session_start();


if (!isset($_SESSION['usuario'])) { header("Location: login.php"); exit; }

$pageTitle = 'Asignar Proyectos';
$is_dashboard = true;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Mostrar todos los bloques para todos los usuarios
$stmt = $pdo->query("SELECT bloque, zona, provincia FROM catalogo_bloques ORDER BY bloque");
if (!isset($bloques)) {
    $bloques = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$stmt = $pdo->query("SELECT DISTINCT comunidad_nombre FROM proyecto_asignacion WHERE comunidad_nombre IS NOT NULL AND comunidad_nombre<>'' ORDER BY comunidad_nombre LIMIT 200");
$sugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="dashboard-grid">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <h1 class="dashboard-welcome">Asignar Proyectos a Comunidades</h1>
        </div>

        <div class="card-form">
            <div class="card-form-header">
                <h2 class="card-form-title">Nueva Asignación Múltiple</h2>
                <p class="card-form-subtitle">Selecciona un bloque y un proyecto, luego agrega las comunidades a asignar.</p>
            </div>

            <div class="card-form-body">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="bloque">1. Elegir Bloque / Locación</label>
                        <select id="bloque" class="nice-input" required>
                            <option value="">— Elegir bloque —</option>
                            <?php foreach($bloques as $b): ?>
                                <option value="<?=h($b['bloque'])?>" data-zona="<?=h($b['zona'])?>" data-prov="<?=h($b['provincia'])?>">
                                    <?=h($b['bloque'])?> — <?=h($b['zona'])?> (<?=h($b['provincia'])?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="proyecto">2. Elegir Proyecto</label>
                        <select id="proyecto" class="nice-input" required disabled>
                            <option value="">— Esperando bloque —</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="zona">Zona</label>
                        <input id="zona" class="nice-input" disabled>
                    </div>
                    <div class="form-group">
                        <label for="provincia">Provincia</label>
                        <input id="provincia" class="nice-input" disabled>
                    </div>
                </div>

                <div id="comunidades-section" style="display: none;">
                    <div class="section-divider"></div>
                    <h3 class="subsection-title">3. Comunidades a Asignar</h3>
                    <div id="comunidades-container">
                        <!-- Las filas de comunidad se agregarán aquí -->
                    </div>
                    <div class="form-actions-start" style="margin-top: 1rem;">
                        <button type="button" id="btnAgregarComunidad" class="btn-secondary">Agregar Comunidad</button>
                    </div>
                </div>

                <datalist id="sugs">
                    <?php foreach($sugs as $s){ ?><option value="<?=h($s['comunidad_nombre'])?>"></option><?php } ?>
                </datalist>

                <div id="productos-section" style="display: none;">
                    <div class="section-divider"></div>
                    <h3 class="subsection-title">4. Productos del Proyecto (Cantidades por Asignación)</h3>
                    <div class="table-wrapper">
                        <table class="products-table" id="tablaProductos">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Unidad</th>
                                    <th>Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="3" class="text-muted">Elige un proyecto para ver sus productos.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="msg" class="form-message"></div>

                <div class="form-actions-end">
                    <button id="btnLimpiar" class="btn-secondary">Limpiar Todo</button>
                    <button id="btnGuardar" class="btn-primary" disabled>Guardar Asignaciones</button>
                </div>
            </div>
        </div>
    </main>
</div>

<template id="comunidad-template">
    <div class="comunidad-row">
        <div class="form-group">
            <label>Comunidad</label>
            <input type="text" class="nice-input comunidad-nombre" list="sugs" required placeholder="Nombre de la comunidad">
        </div>
        <div class="form-group">
            <label># Proyectos</label>
            <input type="number" class="nice-input cantidad-proyectos" value="1" min="1" required>
        </div>
        <div class="form-group">
            <label># Beneficiarios</label>
            <input type="number" class="nice-input beneficiarios" value="0" min="0" required>
        </div>
        <div class="form-group-action">
            <button type="button" class="btn-danger-sm btn-remove-comunidad">&times;</button>
        </div>
    </div>
</template>

<style>
.comunidad-row {
    display: grid;
    grid-template-columns: 3fr 1fr 1fr auto;
    gap: 1rem;
    align-items: flex-end;
    margin-bottom: 1rem;
    padding: 1rem;
    border-radius: 8px;
    background-color: #f9f9f9;
    border: 1px solid #eee;
}
.form-group-action {
    padding-bottom: 0.5rem; /* Alinea el botón con los inputs */
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const $bloque = document.getElementById('bloque');
    const $proy = document.getElementById('proyecto');
    const $zona = document.getElementById('zona');
    const $prov = document.getElementById('provincia');
    const $tablaB = document.querySelector('#tablaProductos tbody');
    const $btnG = document.getElementById('btnGuardar');
    const $btnL = document.getElementById('btnLimpiar');
    const $msg = document.getElementById('msg');
    
    const $comunidadesSection = document.getElementById('comunidades-section');
    const $comunidadesContainer = document.getElementById('comunidades-container');
    const $btnAgregarComunidad = document.getElementById('btnAgregarComunidad');
    const $comunidadTemplate = document.getElementById('comunidad-template');
    const $productosSection = document.getElementById('productos-section');

    function setMsg(html, ok = true) {
        const alertClass = ok ? 'alert-success' : 'alert-danger';
        const newAlert = document.createElement('div');
        newAlert.className = `alert ${alertClass}`;
        newAlert.innerHTML = html;
        $msg.appendChild(newAlert);
        setTimeout(() => { newAlert.remove(); }, 6000);
    }

    function limpiarTabla() {
        $tablaB.innerHTML = '<tr><td colspan="3" class="text-muted">Elige un proyecto…</td></tr>';
    }

    function agregarFilaComunidad() {
        const clone = $comunidadTemplate.content.cloneNode(true);
        const inputNombre = clone.querySelector('.comunidad-nombre');
        inputNombre.addEventListener('input', () => {
            const p = inputNombre.selectionStart;
            inputNombre.value = inputNombre.value.toUpperCase();
            inputNombre.setSelectionRange(p, p);
        });
        $comunidadesContainer.appendChild(clone);
        validarEstadoBotonGuardar();
    }

    function validarEstadoBotonGuardar() {
        const hayProyecto = $proy.value !== '';
        const hayComunidades = $comunidadesContainer.children.length > 0;
        const selectedOption = $proy.options[$proy.selectedIndex];
        const isReadonly = selectedOption?.dataset.readonly === 'true';

        $btnG.disabled = !hayProyecto || !hayComunidades || isReadonly;
    }

    async function cargarProyectos() {
        $proy.innerHTML = '<option value="">Cargando proyectos…</option>';
        try {
            const r = await fetch('api_get_proyectos.php');
            const data = await r.json();
            if (!data.ok) throw new Error(data.msg || 'Error');
            
            $proy.innerHTML = '<option value="">— Elegir proyecto —</option>';
            if (data.proyectos.length === 0) {
                $proy.innerHTML = '<option value="">(No hay proyectos)</option>';
                return;
            }

            data.proyectos.forEach(p => {
                const o = document.createElement('option');
                o.value = p.id_proyecto;
                o.textContent = `${p.nombre} (Técnico: ${p.creado_por})`;
                if (!p.puede_asignar) {
                    o.dataset.readonly = 'true';
                    o.textContent += ' - SOLO LECTURA';
                    o.style.color = '#999';
                }
                $proy.appendChild(o);
            });
        } catch (e) {
            $proy.innerHTML = '<option value="">(Error al cargar)</option>';
        } finally {
            $proy.disabled = false;
        }
    }

    $bloque.addEventListener('change', () => {
        const opt = $bloque.options[$bloque.selectedIndex];
        $zona.value = opt?.dataset?.zona || '';
        $prov.value = opt?.dataset?.prov || '';
        $proy.value = '';
        $proy.disabled = $bloque.value === '';
        $comunidadesSection.style.display = 'none';
        $productosSection.style.display = 'none';
        $comunidadesContainer.innerHTML = '';
        limpiarTabla();
        validarEstadoBotonGuardar();
        if ($bloque.value) {
            cargarProyectos();
        }
    });

    $proy.addEventListener('change', async () => {
        limpiarTabla();
        $comunidadesContainer.innerHTML = '';
        
        const selectedOption = $proy.options[$proy.selectedIndex];
        const proyectoId = selectedOption.value;
        const isReadonly = selectedOption?.dataset.readonly === 'true';

        if (!proyectoId) {
            $comunidadesSection.style.display = 'none';
            $productosSection.style.display = 'none';
            validarEstadoBotonGuardar();
            return;
        }

        $comunidadesSection.style.display = 'block';
        $productosSection.style.display = 'block';
        
        if (!isReadonly) {
            agregarFilaComunidad();
        }

        try {
            const r = await fetch(`api_get_productos.php?id_proyecto=${encodeURIComponent(proyectoId)}`);
            const data = await r.json();
            if (!data.ok) throw new Error(data.msg || 'Error');

            if (data.productos.length === 0) {
                $tablaB.innerHTML = '<tr><td colspan="3" class="text-muted">Este proyecto no tiene productos.</td></tr>';
            } else {
                $tablaB.innerHTML = '';
                data.productos.forEach(prod => {
                    const tr = document.createElement('tr');
                    if (isReadonly) {
                        tr.innerHTML = `
                            <td>${prod.nombre}</td>
                            <td>${prod.unidad ?? ''}</td>
                            <td style="color: #666; font-style: italic;">${prod.cantidad_default ?? 0} (Solo lectura)</td>
                        `;
                    } else {
                        tr.innerHTML = `
                            <td>${prod.nombre}</td>
                            <td>${prod.unidad ?? ''}</td>
                            <td><input type="number" class="nice-input-sm" data-id="${prod.id_producto}" step="0.01" min="0" value="${prod.cantidad_default ?? 0}"></td>
                        `;
                    }
                    $tablaB.appendChild(tr);
                });
            }
            
            if (isReadonly) {
                setMsg('Este proyecto es de solo lectura. Puedes ver los detalles pero no guardarlo.', false);
            }

        } catch (e) {
            setMsg('Error al cargar productos: ' + e.message, false);
        }
        validarEstadoBotonGuardar();
    });

    $btnAgregarComunidad.addEventListener('click', agregarFilaComunidad);

    $comunidadesContainer.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-remove-comunidad')) {
            e.target.closest('.comunidad-row').remove();
            validarEstadoBotonGuardar();
        }
    });

    $btnL.addEventListener('click', () => {
        $bloque.value = '';
        $proy.value = '';
        $proy.disabled = true;
        $zona.value = '';
        $prov.value = '';
        $comunidadesContainer.innerHTML = '';
        $comunidadesSection.style.display = 'none';
        $productosSection.style.display = 'none';
        limpiarTabla();
        $msg.innerHTML = '';
        validarEstadoBotonGuardar();
    });

    $btnG.addEventListener('click', async () => {
        const id_proyecto = $proy.value;
        const bloque = $bloque.value;
        const comunidadRows = $comunidadesContainer.querySelectorAll('.comunidad-row');

        if (!id_proyecto || !bloque) {
            setMsg('Debes elegir un bloque y un proyecto.', false);
            return;
        }
        if (comunidadRows.length === 0) {
            setMsg('Debes agregar al menos una comunidad.', false);
            return;
        }

        // Recolectar productos y sus cantidades
        const productos = [];
        document.querySelectorAll('#tablaProductos .nice-input-sm').forEach(inp => {
            const idp = parseInt(inp.dataset.id, 10);
            const cant = parseFloat(inp.value || '0');
            if (!isNaN(idp) && cant >= 0) { // Permitir cantidad 0 si es necesario
                productos.push({ id_producto: idp, cantidad: cant });
            }
        });

        let hayErrores = false;
        const payloads = [];
        comunidadRows.forEach(row => {
            const nombre = row.querySelector('.comunidad-nombre').value.trim().toUpperCase();
            const cantidad = parseInt(row.querySelector('.cantidad-proyectos').value || '1', 10);
            const beneficiarios = parseInt(row.querySelector('.beneficiarios').value || '0', 10);

            if (!nombre) {
                setMsg('El nombre de la comunidad no puede estar vacío.', false);
                hayErrores = true;
            }
            if (cantidad < 1) {
                setMsg('La cantidad de proyectos debe ser al menos 1.', false);
                hayErrores = true;
            }
            if (beneficiarios < 0) {
                setMsg('El número de beneficiarios no puede ser negativo.', false);
                hayErrores = true;
            }

            payloads.push({
                bloque: bloque,
                id_proyecto: id_proyecto,
                comunidad_nombre: nombre,
                cantidad_proyectos: cantidad,
                beneficiarios: beneficiarios,
                observaciones: '',
                productos: productos // Adjuntar la lista de productos a cada payload
            });
        });

        if (hayErrores) return;

        $btnG.disabled = true;
        $btnG.textContent = 'Guardando...';
        let guardados = 0;
        let errores = 0;

        for (const payload of payloads) {
            try {
                const r = await fetch('api_save_asignacion.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await r.json();
                if (!data.ok) throw new Error(data.msg || 'Error desconocido');
                guardados++;
            } catch (e) {
                errores++;
                setMsg(`Error al guardar la comunidad ${payload.comunidad_nombre}: ${e.message}`, false);
            }
        }

        if (guardados > 0) {
            setMsg(`${guardados} asignaci(ón/ones) guardada(s) correctamente.`, true);
        }
        if (errores === 0 && guardados > 0) {
            $btnL.click(); // Limpiar todo si no hubo errores
        }

        $btnG.disabled = false;
        $btnG.textContent = 'Guardar Asignaciones';
        validarEstadoBotonGuardar();
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>