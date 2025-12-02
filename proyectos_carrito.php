<?php
// proyectos_carrito.php (PDO, sin restricción por tipo; permite mezclar grupos)
session_start();
if (!isset($_SESSION['usuario'])) { header('Location: login.php'); exit; }

require_once __DIR__ . '/includes/db.php';   // Debe definir $pdo (PDO)
$pageTitle = 'Crear Proyectos - EP Petroecuador';
$is_dashboard = true;
require_once __DIR__ . '/includes/header.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ===== A) Asegurar tipo "MIXTO" para clasificar proyectos (internamente) ===== */
$mixtoId = $pdo->query("SELECT id FROM tipos_proyecto WHERE nombre='MIXTO' LIMIT 1")->fetchColumn();
if (!$mixtoId) {
  $pdo->prepare("INSERT INTO tipos_proyecto(nombre) VALUES('MIXTO')")->execute();
  $mixtoId = (int)$pdo->lastInsertId();
}

/* ===== B) Proyectos existentes ===== */
// Primero, obtenemos los proyectos ordenados por nombre y luego por ID para agrupar duplicados.
$stmt = $pdo->query("
    SELECT p.id, p.nombre, tp.nombre AS tipo, p.creado_por
    FROM proyectos p
    JOIN tipos_proyecto tp ON tp.id = p.tipo_id
    ORDER BY p.nombre ASC, p.id ASC
");
$proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar ocurrencias de cada nombre
$name_counts = array_count_values(array_column($proyectos, 'nombre'));

// Aplicar contador a nombres duplicados
$name_counters = [];
foreach ($proyectos as &$p) {
    $nombre = $p['nombre'];
    if (isset($name_counts[$nombre]) && $name_counts[$nombre] > 1) {
        if (!isset($name_counters[$nombre])) {
            $name_counters[$nombre] = 1;
        }
        $p['nombre'] = $nombre . ' (' . $name_counters[$nombre] . ')';
        $name_counters[$nombre]++;
    }
}
unset($p); // Romper referencia

/* ===== C) Proyecto abierto ===== */
$proyecto_id = isset($_GET['proyecto_id']) ? (int)$_GET['proyecto_id'] : 0;
$proyecto = null;

if ($proyecto_id > 0) {
  $stmt = $pdo->prepare("
    SELECT p.id, p.nombre, p.creado_por
    FROM proyectos p
    WHERE p.id = ?
    LIMIT 1
  ");
  $stmt->execute([$proyecto_id]);
  $proyecto = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* ===== D) Filtro opcional por grupo para el selector (no obligatorio) ===== */
$grupo_sel = isset($_GET['g']) ? trim($_GET['g']) : '';
$grupos = $pdo->query("SELECT DISTINCT grupo FROM catalogo_productos ORDER BY grupo")->fetchAll(PDO::FETCH_COLUMN);

if ($grupo_sel !== '') {
  $stmt = $pdo->prepare("
    SELECT id_producto AS id, descripcion, unidad, precio_unit, grupo
    FROM catalogo_productos
    WHERE grupo = ?
    ORDER BY descripcion
  ");
  $stmt->execute([$grupo_sel]);
  $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
  $productos = $pdo->query("
    SELECT id_producto AS id, descripcion, unidad, precio_unit, grupo
    FROM catalogo_productos
    ORDER BY grupo, descripcion
  ")->fetchAll(PDO::FETCH_ASSOC);
}

/* ===== E) Carrito en sesión por proyecto ===== */
$cartKey = $proyecto_id ? "cart_$proyecto_id" : null;
if ($cartKey && !isset($_SESSION[$cartKey])) {
  // El carrito para este proyecto no está en la sesión, así que lo inicializamos
  // cargando los items previamente guardados en la base de datos.
  $_SESSION[$cartKey] = []; // Inicializar como array vacío

  $stmt = $pdo->prepare("
    SELECT pi.producto_id, pi.descripcion, pi.unidad, pi.cantidad, pi.precio_unit, cp.grupo
    FROM proyecto_items pi
    LEFT JOIN catalogo_productos cp ON cp.id_producto = pi.producto_id
    WHERE pi.proyecto_id = ?
  ");
  $stmt->execute([$proyecto_id]);
  while($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $nombre = $item['grupo'] ? '['.$item['grupo'].'] '.$item['descripcion'] : $item['descripcion'];
    $_SESSION[$cartKey][] = [
      'id'       => (int)$item['producto_id'],
      'nombre'   => $nombre,
      'unidad'   => $item['unidad'],
      'precio'   => (float)$item['precio_unit'],
      'cantidad' => (float)$item['cantidad']
    ];
  }
}

// Sincronizar precios del carrito en sesión con el catálogo para reflejar cambios.
if ($cartKey && isset($_SESSION[$cartKey]) && !empty($_SESSION[$cartKey])) {
    $product_ids = array_column($_SESSION[$cartKey], 'id');
    
    if (!empty($product_ids)) {
        $in_clause = implode(',', array_fill(0, count($product_ids), '?'));
        
        $stmt = $pdo->prepare("SELECT id_producto, precio_unit FROM catalogo_productos WHERE id_producto IN ($in_clause)");
        $stmt->execute($product_ids);
        $latest_prices = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($_SESSION[$cartKey] as &$item) {
            $product_id = $item['id'];
            if (isset($latest_prices[$product_id])) {
                $item['precio'] = (float)$latest_prices[$product_id];
            }
        }
        unset($item); // Romper la referencia
    }
}

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<div class="dashboard-grid">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="dashboard-main">
      <div class="container">
        <div class="dashboard-header">
          <h1 class="dashboard-welcome">
            Crear Proyectos
          </h1>
        </div>

        <?php if ($flash_success): ?>
          <div class="alert alert-success">
            <?= h($flash_success) ?>
          </div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
          <div class="alert alert-danger">
            <?= h($flash_error) ?>
          </div>
        <?php endif; ?>

        <!-- Paso 1: Crear o seleccionar proyecto -->
        <section class="card-form mb-4">
          <div class="card-form-header">
            <h2 class="card-form-title">1) Crear o seleccionar proyecto</h2>
          </div>
          <div class="card-form-body">
            <div class="form-grid-2">
              <!-- Crear nuevo (sin tipo; se asigna MIXTO) -->
              <div class="card-form">
                <div class="card-form-body">
                  <h3 class="subsection-title"><i class="fas fa-plus-circle me-2"></i> Crear nuevo</h3>
                  <form action="proyectos_nuevo.php" method="post" autocomplete="off">
                    <div class="form-group">
                      <label for="nombre_proyecto">Nombre del proyecto</label>
                      <input type="text" id="nombre_proyecto" name="nombre" required class="nice-input">
                    </div>

                    <!-- oculto: tipo_id = MIXTO -->
                    <input type="hidden" name="tipo_id" value="<?= (int)$mixtoId ?>">

                    <button class="btn-primary mt-3" type="submit">Crear y continuar</button>
                  </form>
                  <small class="text-muted mt-2">
                    *El proyecto se clasificará internamente como <b>MIXTO</b>, pero puedes añadir productos de cualquier grupo.
                  </small>
                </div>
              </div>

              <!-- Abrir existente -->
              <div class="card-form">
                <div class="card-form-body">
                  <h3 class="subsection-title"><i class="fas fa-folder-open me-2"></i> Usar proyecto existente</h3>
                  <?php if(empty($proyectos)): ?>
                    <div class="alert alert-info">
                      Aún no hay proyectos. Crea uno nuevo para empezar.
                    </div>
                  <?php else: ?>
                    <form method="get" action="proyectos_carrito.php" class="mb-3">
                      <div class="form-group">
                        <label for="proyecto_existente">Proyecto</label>
                        <select id="proyecto_existente" name="proyecto_id" class="nice-input" onchange="this.form.submit()">
                          <option value="">-- Seleccione un proyecto para abrirlo --</option>
                          <?php foreach($proyectos as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= $proyecto_id==$p['id']?'selected':'' ?>>
                              <?= h($p['nombre']) ?> (<?= h($p['tipo']) ?>) - Creado por: <?= h($p['creado_por']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                    </form>
                    <!-- Formulario para eliminar proyecto seleccionado -->
                    <?php 
                    $rol_actual = $_SESSION['id_rol'] ?? 2;
                    $es_admin = ($rol_actual == 1);
                    $puede_eliminar = $proyecto && ($es_admin || $proyecto['creado_por'] === $_SESSION['usuario']);
                    
                    if ($puede_eliminar): ?>
                    <form method="post" action="proyectos_eliminar.php" onsubmit="return confirm('¿Seguro que deseas eliminar este proyecto? Esta acción no se puede deshacer.');">
                      <input type="hidden" name="proyecto_id" value="<?= (int)$proyecto_id ?>">
                      <button type="submit" class="btn-danger mt-2"><i class="fas fa-trash-alt me-2"></i>
                        Eliminar proyecto seleccionado
                      </button>
                    </form>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </section>



        <!-- Paso 2: Agregar productos (si hay proyecto abierto) -->
        <?php if($proyecto): ?>
        <section class="card-form mb-4">
          <div class="card-form-header">
            <h2 class="card-form-title">
              2) Agregar productos a: <strong><?= h($proyecto['nombre']) ?></strong>
              <small class="text-muted d-block" style="font-size: 0.8rem; margin-top: 4px;">Creado por: <?= h($proyecto['creado_por'] ?? 'N/A') ?></small>
            </h2>
          </div>
          <div class="card-form-body">
            <!-- Filtro opcional por grupo (solo para comodidad visual) -->
            <div class="form-inline mb-3" id="formFiltroProductos">
              <input type="hidden" name="proyecto_id" value="<?= (int)$proyecto_id ?>">
              <div class="form-group me-2">
                <label for="filtro_grupo">Filtrar por grupo</label>
                <select id="filtro_grupo" name="g" class="nice-input">
                  <option value="">(Todos)</option>
                  <?php foreach($grupos as $g): ?>
                    <option value="<?= h($g) ?>" <?= $grupo_sel===$g?'selected':'' ?>><?= h($g) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <?php if(empty($productos)): ?>
              <div class="alert alert-info">
                No hay productos en el catálogo.
              </div>
            <?php else: ?>
              <form action="proyectos_cart_add.php" method="post" class="form-grid-3-auto" id="formAddProduct">
                <input type="hidden" name="proyecto_id" value="<?= (int)$proyecto_id ?>">

                <div class="form-group">
                  <label for="producto_add">Producto</label>
                  <select id="producto_add" name="producto_id" class="nice-input" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach($productos as $pr): ?>
                      <option value="<?= (int)$pr['id'] ?>">
                        [<?= h($pr['grupo']) ?>] <?= h($pr['descripcion']) ?> — $<?= number_format((float)$pr['precio_unit'],2) ?>/<?= h($pr['unidad']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="form-group">
                  <label for="cantidad_add">Cantidad</label>
                  <input type="number" id="cantidad_add" step="1" min="1" name="cantidad" class="nice-input" required>
                </div>

                <div class="form-group d-flex align-items-end">
                  <button class="btn-primary w-100" type="submit"><i class="fas fa-cart-plus me-2"></i> Agregar</button>
                </div>
              </form>
            <?php endif; ?>
          </div>
        </section>

        <!-- Paso 3: Carrito actual -->
        <section class="card-form mb-4">
          <div class="card-form-body" id="carrito-container">
            <?php
              $items = $_SESSION[$cartKey] ?? [];
              if (empty($items)) {
                echo '<div class="alert alert-info">Sin productos aún.</div>';
              } else {
                echo '<div class="table-wrapper">';
                echo '<table class="data-table">';
                echo '<thead><tr>
                        <th>Producto</th>
                        <th class="text-center">Unidad</th>
                        <th class="text-end">Precio</th>
                        <th class="text-end">Cantidad</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-center"></th>
                      </tr></thead><tbody>';
                $total = 0;
                foreach($items as $idx => $it){
                  $precio   = (float)$it['precio'];
                  $cantidad = (float)$it['cantidad'];
                  $subtotal = $precio * $cantidad;
                  $total   += $subtotal;

                  echo '<tr data-product-id="'.(int)$it['id'].'">';
                  echo '<td>'.h($it['nombre']).'</td>';
                  echo '<td class="text-center">'.h($it['unidad']).'</td>';
                  echo '<td class="text-end">'.number_format($precio,2).'</td>';
                  echo '<td class="text-end">'.number_format($cantidad,2).'</td>';
                  echo '<td class="text-end font-bold">' . number_format($subtotal,2) . '</td>';
                  echo '<td class="text-center">
                          <a href="proyectos_cart_remove.php?proyecto_id='.(int)$proyecto_id.'&idx='.(int)$idx.'" 
                             class="btn-icon btn-icon-danger">✕</a>
                        </td>';
                  echo '</tr>';
                }
                echo '</tbody></table></div>';
                echo '<div class="d-flex justify-content-end align-items-center mt-3">';
                echo '<strong class="me-2">Total:</strong> <span class="text-lg font-bold">' . number_format($total,2) . '</span>';
                echo '</div>';
                echo '<div class="form-actions-end mt-3">';
                echo '<a class="btn-secondary" href="proyectos_cart_clear.php?proyecto_id='.(int)$proyecto_id.'"><i class="fas fa-trash-alt me-2"></i> Vaciar</a>';
                echo '<form action="proyectos_cart_save.php" method="post">';
                echo '<input type="hidden" name="proyecto_id" value="'.(int)$proyecto_id.'">';
                echo '<button class="btn-primary" type="submit"><i class="fas fa-save me-2"></i> Guardar Proyecto</button>';
                echo '</form>';
                echo '</div>';
              }
            ?>
          </div>
        </section>
        <?php endif; ?>
      </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- FILTRO DE PRODUCTOS ---
    const filterSelect = document.getElementById('filtro_grupo');
    const productSelect = document.getElementById('producto_add');

    if (filterSelect && productSelect) {
        filterSelect.addEventListener('change', async () => {
            const selectedGroup = filterSelect.value;
            const url = `api_get_productos_por_grupo.php?g=${encodeURIComponent(selectedGroup)}`;
            try {
                productSelect.innerHTML = '<option value="">Cargando...</option>';
                const response = await fetch(url);
                if (!response.ok) throw new Error('La respuesta de la red no fue correcta.');
                const data = await response.json();
                productSelect.innerHTML = '<option value="">-- Selecciona --</option>';
                if (data.productos && data.productos.length > 0) {
                    data.productos.forEach(product => {
                        const option = document.createElement('option');
                        option.value = product.id;
                        option.textContent = `[${product.grupo}] ${product.descripcion} — ${parseFloat(product.precio_unit).toFixed(2)}/${product.unidad}`;
                        productSelect.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error al obtener productos:', error);
                productSelect.innerHTML = '<option value="">Error al cargar</option>';
            }
        });
    }

    // --- AGREGAR PRODUCTO AL CARRITO (AJAX) ---
    const formAddProduct = document.getElementById('formAddProduct');
    console.log('formAddProduct element:', formAddProduct); // DEBUG
    if (formAddProduct) {
        console.log('Attaching submit event listener to formAddProduct.'); // DEBUG
        formAddProduct.addEventListener('submit', async (e) => {
            console.log('Submit event triggered!'); // DEBUG
            e.preventDefault();

            const formData = new FormData(formAddProduct);
            const button = formAddProduct.querySelector('button[type="submit"]');
            const originalButtonText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';

            try {
                const response = await fetch('proyectos_cart_add.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) throw new Error('Error en la respuesta del servidor.');
                
                const result = await response.json();

                if (result.ok) {
                    updateCartUI(result.item);
                    showFlashMessage('Producto agregado con éxito.', 'success');
                    formAddProduct.reset();
                } else {
                    showFlashMessage(result.message || 'No se pudo agregar el producto.', 'danger');
                }

            } catch (error) {
                console.error('Error al agregar producto:', error);
                showFlashMessage('Error de conexión al agregar el producto.', 'danger');
            } finally {
                button.disabled = false;
                button.innerHTML = originalButtonText;
            }
        });
    }

    function updateCartUI(item) {
        const cartContainer = document.getElementById('carrito-container');
        if (!cartContainer) return;

        let cartTable = cartContainer.querySelector('.data-table');
        
        // Si el carrito estaba vacío, creamos la tabla
        if (!cartTable) {
            cartContainer.innerHTML = `
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Unidad</th>
                                <th class="text-end">Precio</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Subtotal</th>
                                <th class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end align-items-center mt-3" id="cart-total-container">
                    <strong class="me-2">Total:</strong> <span class="text-lg font-bold">0.00</span>
                </div>
                <div class="form-actions-end mt-3">
                    <a class="btn-secondary" href="proyectos_cart_clear.php?proyecto_id=${escapeHTML(formAddProduct.proyecto_id.value)}"><i class="fas fa-trash-alt me-2"></i> Vaciar</a>
                    <form action="proyectos_cart_save.php" method="post" style="display:inline;">
                        <input type="hidden" name="proyecto_id" value="${escapeHTML(formAddProduct.proyecto_id.value)}">
                        <button class="btn-primary" type="submit"><i class="fas fa-save me-2"></i> Guardar Proyecto</button>
                    </form>
                </div>
            `;
            cartTable = cartContainer.querySelector('.data-table');
        }

        const cartTableBody = cartTable.querySelector('tbody');
        let existingRow = cartTableBody.querySelector(`tr[data-product-id="${item.id}"]`);

        if (existingRow) {
            // Actualizar fila existente
            const newQuantity = parseFloat(item.cantidad);
            const price = parseFloat(item.precio);
            const subtotal = price * newQuantity;
            existingRow.cells[3].textContent = newQuantity.toFixed(2);
            existingRow.cells[4].innerHTML = `<span class="font-bold">${subtotal.toFixed(2)}</span>`;
        } else {
            // Agregar nueva fila
            const newRow = document.createElement('tr');
            newRow.dataset.productId = item.id;
            const subtotal = item.precio * item.cantidad;
            newRow.innerHTML = `
                <td>${escapeHTML(item.nombre)}</td>
                <td class="text-center">${escapeHTML(item.unidad)}</td>
                <td class="text-end">${item.precio.toFixed(2)}</td>
                <td class="text-end">${item.cantidad.toFixed(2)}</td>
                <td class="text-end font-bold">${subtotal.toFixed(2)}</td>
                <td class="text-center">
                    <a href="#" onclick="alert('Para eliminar un producto recién agregado, primero guarde el proyecto.'); return false;" 
                       class="btn-icon btn-icon-danger" title="Guarde el proyecto para poder eliminar">✕</a>
                </td>
            `;
            cartTableBody.appendChild(newRow);
        }

        updateCartTotal();
    }

    function updateCartTotal() {
        const cartTableBody = document.querySelector('.data-table tbody');
        if (!cartTableBody) return;

        let total = 0;
        cartTableBody.querySelectorAll('tr').forEach(row => {
            const subtotalText = row.cells[4].textContent.replace(/[^0-9.-]+/g,"");
            const subtotal = parseFloat(subtotalText);
            if (!isNaN(subtotal)) {
                total += subtotal;
            }
        });

        const totalElement = document.querySelector('#cart-total-container .font-bold');
        if (totalElement) {
            totalElement.textContent = total.toFixed(2);
        }
    }

    function showFlashMessage(message, type = 'success') {
        const container = document.querySelector('.dashboard-header');
        if (!container) return;
        
        // Remove any existing flash messages
        const existingAlert = document.querySelector('.alert.alert-dismissible');
        if (existingAlert) existingAlert.remove();

        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.role = 'alert';
        alert.innerHTML = `
            ${escapeHTML(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        container.insertAdjacentElement('afterend', alert);

        setTimeout(() => {
            if(alert) alert.remove();
        }, 5000);
    }
    
    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        const p = document.createElement('p');
        p.appendChild(document.createTextNode(str));
        return p.innerHTML;
    }
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>