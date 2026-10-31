<?php
/**
 * =========================================================
 * PROYECTO: TEEN SHOES - DASHBOARD DE ADMINISTRACIÓN
 * VERSIÓN CORREGIDA: Editar funcional + Buscador + Fixes
 * =========================================================
 */

// 1. CONFIGURACIÓN DE LA BASE DE DATOS
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'zapateria_online';

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Error crítico de conexión: " . $conn->connect_error);
}
$conn->set_charset("utf8");

$mensaje = '';
$error = '';

// 2. LÓGICA DEL BACKEND (CRUD)

// -- AGREGAR PRODUCTO --
if (isset($_POST['accion']) && $_POST['accion'] === 'agregar') {
    $marca      = trim($_POST['marca']);
    $modelo     = trim($_POST['modelo']);
    $talla      = intval($_POST['talla']);
    $color      = trim($_POST['color']);
    $precio     = floatval($_POST['precio']);
    $existencia = intval($_POST['existencia']);

    if ($marca !== '' && $modelo !== '' && $talla > 0 && $color !== '' && $precio >= 0 && $existencia >= 0) {
        $stmt = $conn->prepare("INSERT INTO productos (marca, modelo, talla, color, precio, existencia) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssisdi", $marca, $modelo, $talla, $color, $precio, $existencia);
        if ($stmt->execute()) {
            $mensaje = " Producto agregado correctamente al catálogo.";
        } else {
            $error = " Error al guardar: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error = " Por favor, llena todos los campos del formulario.";
    }
}

// -- ELIMINAR PRODUCTO --
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $stmt = $conn->prepare("DELETE FROM productos WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $mensaje = " Producto eliminado del inventario.";
    } else {
        $error = " Error al eliminar el producto.";
    }
    $stmt->close();
}

// -- EDITAR PRODUCTO -- (¡CORREGIDO!)
if (isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    $id         = intval($_POST['id']);
    $marca      = trim($_POST['marca']);
    $modelo     = trim($_POST['modelo']);
    $talla      = intval($_POST['talla']);
    $color      = trim($_POST['color']);
    $precio     = floatval($_POST['precio']);
    $existencia = intval($_POST['existencia']);

    //  FIX: era "ssisdi i" (con espacio) → ahora "ssisdii" (7 caracteres para 7 params)
    $stmt = $conn->prepare("UPDATE productos SET marca=?, modelo=?, talla=?, color=?, precio=?, existencia=? WHERE id=?");
    $stmt->bind_param("ssisdii", $marca, $modelo, $talla, $color, $precio, $existencia, $id);
    if ($stmt->execute()) {
        $mensaje = " Producto actualizado correctamente.";
    } else {
        $error = " Error al actualizar: " . $conn->error;
    }
    $stmt->close();
}

// 3. BUSCADOR 
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

if ($busqueda !== '') {
    // Busca por marca, modelo, color, talla o precio (LIKE)
    $like = '%' . $busqueda . '%';
    $stmt = $conn->prepare(
        "SELECT * FROM productos 
         WHERE marca LIKE ? OR modelo LIKE ? OR color LIKE ? 
            OR CAST(talla AS CHAR) LIKE ? OR CAST(precio AS CHAR) LIKE ?
         ORDER BY id DESC"
    );
    $stmt->bind_param("sssss", $like, $like, $like, $like, $like);
    $stmt->execute();
    $resultado = $stmt->get_result();
} else {
    $resultado = $conn->query("SELECT * FROM productos ORDER BY id DESC");
}

// 4. ESTADÍSTICAS
$total_productos  = $resultado ? $resultado->num_rows : 0;
$total_stock      = 0;
$valor_inventario = 0;
$productos_array  = [];

if ($resultado) {
    while ($row = $resultado->fetch_assoc()) {
        $productos_array[] = $row;
        $total_stock      += $row['existencia'];
        $valor_inventario += ($row['precio'] * $row['existencia']);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teen Shoes - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #38bdf8;
            --dark-text: #1e293b;
            --light-bg: #f8fafc;
            --sidebar-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-muted: #64748b;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-text);
            overflow-x: hidden;
        }
        .sidebar {
            width: 260px; height: 100vh; background: var(--sidebar-bg);
            position: fixed; top: 0; left: 0;
            border-right: 1px solid var(--border-color);
            padding: 20px 0; z-index: 100;
        }
        .brand {
            font-size: 1.4rem; font-weight: 700; color: var(--dark-text);
            display: flex; align-items: center;
            padding: 0 25px 25px 25px;
            border-bottom: 1px solid var(--border-color);
        }
        .brand i { color: var(--primary-blue); font-size: 1.6rem; margin-right: 10px; }
        .nav-menu { padding: 20px 10px; list-style: none; margin: 0; }
        .nav-item { margin-bottom: 5px; }
        .nav-link {
            display: flex; align-items: center;
            padding: 12px 20px; color: var(--text-muted);
            text-decoration: none; font-weight: 500;
            border-radius: 10px; transition: all 0.3s ease;
        }
        .nav-link i { width: 25px; font-size: 1.1rem; margin-right: 10px; }
        .nav-link:hover, .nav-link.active { background-color: var(--primary-blue); color: white; }
        .main-content { margin-left: 260px; padding: 20px 40px; }
        .topbar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 15px 0; margin-bottom: 30px;
            border-bottom: 1px solid var(--border-color);
        }
        .search-bar { position: relative; width: 400px; }
        .search-bar input {
            width: 100%; padding: 10px 20px 10px 45px;
            border-radius: 20px; border: 1px solid var(--border-color);
            background: white; font-size: 0.9rem; outline: none; transition: 0.3s;
        }
        .search-bar input:focus {
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
            border-color: var(--primary-blue);
        }
        .search-bar i {
            position: absolute; left: 15px; top: 50%;
            transform: translateY(-50%); color: var(--text-muted);
        }
        .user-profile { display: flex; align-items: center; gap: 15px; }
        .user-profile img {
            width: 40px; height: 40px; border-radius: 50%; object-fit: cover;
        }
        .user-info h6 { margin: 0; font-weight: 600; font-size: 0.9rem; }
        .user-info p { margin: 0; font-size: 0.75rem; color: var(--text-muted); }
        .stat-card {
            background: white; border-radius: 15px; padding: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            transition: transform 0.3s; height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }
        .stat-icon {
            width: 50px; height: 50px; border-radius: 12px;
            background: rgba(56, 189, 248, 0.1); color: var(--primary-blue);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; margin-bottom: 15px;
        }
        .stat-title {
            font-size: 0.85rem; color: var(--text-muted);
            font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .stat-value {
            font-size: 1.8rem; font-weight: 700;
            color: var(--dark-text); margin: 5px 0;
        }
        .table-container {
            background: white; border-radius: 15px;
            border: 1px solid var(--border-color);
            padding: 20px; margin-top: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }
        .table-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 20px;
        }
        .table-title { font-size: 1.2rem; font-weight: 700; margin: 0; }
        .table { margin-bottom: 0; }
        .table thead th {
            border-bottom: 2px solid var(--border-color);
            color: var(--text-muted); font-size: 0.85rem;
            font-weight: 600; text-transform: uppercase;
            border-top: none; padding: 15px;
        }
        .table tbody td {
            vertical-align: middle; padding: 15px;
            font-size: 0.9rem; border-bottom: 1px solid var(--border-color);
        }
        .badge-stock {
            background: #dcfce7; color: #166534;
            padding: 5px 10px; border-radius: 20px;
            font-size: 0.75rem; font-weight: 600;
        }
        .badge-stock.low { background: #fee2e2; color: #991b1b; }
        .btn-action {
            padding: 5px 10px; border-radius: 8px;
            border: none; background: transparent; transition: 0.2s;
        }
        .btn-edit { color: var(--primary-blue); }
        .btn-edit:hover { background: rgba(56, 189, 248, 0.1); }
        .btn-delete { color: #ef4444; }
        .btn-delete:hover { background: rgba(239, 68, 68, 0.1); }
        .modal-content {
            border: none; border-radius: 15px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .modal-header {
            border-bottom: 1px solid var(--border-color);
            background: var(--light-bg);
            border-radius: 15px 15px 0 0;
        }
        .modal-body .form-label {
            font-size: 0.85rem; font-weight: 600; color: var(--text-muted);
        }
        .modal-body .form-control {
            border-radius: 8px; border: 1px solid var(--border-color); padding: 10px;
        }
        .btn-primary-custom {
            background-color: var(--primary-blue); border: none;
            border-radius: 8px; padding: 10px 20px;
            font-weight: 600; color: white;
        }
        .btn-primary-custom:hover { background-color: #0284c7; color: white; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="brand">
            <i class="fas fa-shoe-prints"></i> zapateria de zapatos
        </div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="#" class="nav-link active"><i class="fas fa-th-large"></i> Panel</a></li>
            <li class="nav-item"><a href="#" class="nav-link"><i class="fas fa-shopping-bag"></i> Ordenes</a></li>
            <li class="nav-item"><a href="#" class="nav-link"><i class="fas fa-box"></i> Productos</a></li>
            <li class="nav-item"><a href="#" class="nav-link"><i class="fas fa-wallet"></i> Billetera</a></li>
            <li class="nav-item mt-4"><a href="#" class="nav-link"><i class="fas fa-cog"></i> Ajustes</a></li>
            <li class="nav-item"><a href="#" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
        </ul>
    </div>

    <!-- CONTENIDO PRINCIPAL -->
    <div class="main-content">

        <!-- Topbar con BUSCADOR FUNCIONAL -->
        <div class="topbar">
            <form class="search-bar" method="GET" action="">
                <i class="fas fa-search"></i>
                <input type="text" name="buscar"
                       placeholder="Buscar por marca, modelo, color, talla o precio..."
                       value="<?php echo htmlspecialchars($busqueda); ?>">
            </form>
            <div class="user-profile">
                <i class="far fa-bell me-3" style="font-size: 1.2rem; cursor: pointer;"></i>
                <img src="https://ui-avatars.com/api/?name=Dolly+Dolly&background=38bdf8&color=fff" alt="User">
                <div class="user-info">
                    <h6>Usuario</h6>
                    <p>Bienvenido a la Zapateria de Zapatos</p>
                </div>
            </div>
        </div>

        <!-- Alertas -->
        <?php if ($mensaje): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- KPIs -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-shoe-prints"></i></div>
                    <div class="stat-title">Total Modelos</div>
                    <div class="stat-value"><?php echo $total_productos; ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                        <i class="fas fa-cubes"></i>
                    </div>
                    <div class="stat-title">Zapatos en Stock</div>
                    <div class="stat-value"><?php echo $total_stock; ?> pares</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(234, 179, 8, 0.1); color: #eab308;">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-title">Valor del Inventario</div>
                    <div class="stat-value">$<?php echo number_format($valor_inventario, 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="table-container">
            <div class="table-header">
                <h4 class="table-title">
                    Productos
                    <?php if ($busqueda !== ''): ?>
                        <small class="text-muted">— Resultados para: "<?php echo htmlspecialchars($busqueda); ?>"</small>
                    <?php endif; ?>
                </h4>
                <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalAgregar">
                    <i class="fas fa-plus me-2"></i> Add Product
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Marca</th>
                            <th>Modelo</th>
                            <th>Talla</th>
                            <th>Color</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($productos_array) > 0): ?>
                            <?php foreach ($productos_array as $row): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['marca']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['modelo']); ?></td>
                                <td><?php echo $row['talla']; ?></td>
                                <td><?php echo htmlspecialchars($row['color']); ?></td>
                                <td>$<?php echo number_format($row['precio'], 2); ?></td>
                                <td>
                                    <span class="badge-stock <?php echo ($row['existencia'] < 10) ? 'low' : ''; ?>">
                                        <?php echo $row['existencia']; ?> pzas
                                    </span>
                                </td>
                                <td>
                                    <!-- ⚠️ FIX: usamos htmlspecialchars + comillas simples en el atributo
                                         y pasamos el ID para no incrustar el JSON en el onclick -->
                                    <button class="btn-action btn-edit"
                                            onclick="abrirModalEditar(<?php echo (int)$row['id']; ?>)"
                                            title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?eliminar=<?php echo (int)$row['id']; ?>"
                                       class="btn-action btn-delete"
                                       onclick="return confirm('¿Seguro que deseas eliminar este zapato?')"
                                       title="Eliminar">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <?php echo ($busqueda !== '') ? 'No se encontraron resultados para tu búsqueda.' : 'No hay productos registrados aún.'; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL AGREGAR -->
    <div class="modal fade" id="modalAgregar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Añadir Nuevo Zapato</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="accion" value="agregar">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Marca</label>
                                <input type="text" name="marca" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Modelo</label>
                                <input type="text" name="modelo" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Talla</label>
                                <input type="number" name="talla" class="form-control" min="1" max="50" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Color</label>
                                <input type="text" name="color" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Precio ($)</label>
                                <input type="number" step="0.01" name="precio" class="form-control" min="0" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Existencia</label>
                                <input type="number" name="existencia" class="form-control" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary-custom">Guardar Zapato</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL EDITAR -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Editar Zapato</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Marca</label>
                                <input type="text" name="marca" id="edit_marca" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Modelo</label>
                                <input type="text" name="modelo" id="edit_modelo" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Talla</label>
                                <input type="number" name="talla" id="edit_talla" class="form-control" min="1" max="50" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Color</label>
                                <input type="text" name="color" id="edit_color" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Precio ($)</label>
                                <input type="number" step="0.01" name="precio" id="edit_precio" class="form-control" min="0" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Existencia</label>
                                <input type="number" name="existencia" id="edit_existencia" class="form-control" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary-custom">Actualizar Datos</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ⚠️ FIX CLAVE: en vez de incrustar todo el JSON (frágil con comillas/apóstrofes),
        // guardamos los productos en un array JS y el botón solo pasa el ID.
        const PRODUCTOS = <?php echo json_encode($productos_array, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        function abrirModalEditar(id) {
            const p = PRODUCTOS.find(prod => parseInt(prod.id) === parseInt(id));
            if (!p) { alert('Producto no encontrado'); return; }

            document.getElementById('edit_id').value         = p.id;
            document.getElementById('edit_marca').value      = p.marca;
            document.getElementById('edit_modelo').value     = p.modelo;
            document.getElementById('edit_talla').value      = p.talla;
            document.getElementById('edit_color').value      = p.color;
            document.getElementById('edit_precio').value     = p.precio;
            document.getElementById('edit_existencia').value = p.existencia;

            new bootstrap.Modal(document.getElementById('modalEditar')).show();
        }
    </script>
</body>
</html>
