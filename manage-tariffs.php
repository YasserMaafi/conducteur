<?php
require_once 'includes/config.php';
require_once 'includes/auth_functions.php';

// Verify admin role
if (!isLoggedIn() || $_SESSION['user']['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Get admin info
$admin_id = $_SESSION['user']['id'];
$stmt = $pdo->prepare("SELECT * FROM admins WHERE user_id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $stmt = $pdo->prepare("
                        INSERT INTO tariffs (client_id, base_rate_per_km, created_at)
                        VALUES (?, ?, NOW())
                    ");
                    $stmt->execute([
                        $_POST['client_id'],
                        $_POST['base_rate_per_km']
                    ]);
                    $_SESSION['success'] = "Tarif ajouté avec succès";
                    break;

                case 'edit':
                    $stmt = $pdo->prepare("
                        UPDATE tariffs 
                        SET base_rate_per_km = ?
                        WHERE tariff_id = ?
                    ");
                    $stmt->execute([
                        $_POST['base_rate_per_km'],
                        $_POST['tariff_id']
                    ]);
                    $_SESSION['success'] = "Tarif mis à jour avec succès";
                    break;

                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM tariffs WHERE tariff_id = ?");
                    $stmt->execute([$_POST['tariff_id']]);
                    $_SESSION['success'] = "Tarif supprimé avec succès";
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
    header("Location: manage-tariffs.php");
    exit();
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$min_rate = $_GET['min_rate'] ?? '';
$max_rate = $_GET['max_rate'] ?? '';

// Build WHERE clause based on filters
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.company_name ILIKE ? OR c.account_code ILIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($min_rate)) {
    $where_conditions[] = "t.base_rate_per_km >= ?";
    $params[] = $min_rate;
}

if (!empty($max_rate)) {
    $where_conditions[] = "t.base_rate_per_km <= ?";
    $params[] = $max_rate;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get all tariffs with client info
$stmt = $pdo->prepare("
    SELECT t.*, c.company_name, c.account_code
    FROM tariffs t
    JOIN clients c ON t.client_id = c.client_id
    $where_clause
    ORDER BY c.company_name
");
$stmt->execute($params);
$tariffs = $stmt->fetchAll();

// Get all clients for the add form
$clients = $pdo->query("
    SELECT client_id, company_name, account_code 
    FROM clients 
    ORDER BY company_name
")->fetchAll();

// Get unread notifications for navbar
$notifStmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE (user_id = ? OR (user_id IS NULL AND metadata->>'target_audience' = 'admins'))
    AND is_read = FALSE
    ORDER BY created_at DESC
    LIMIT 5
");
$notifStmt->execute([$admin_id]);
$notifications = $notifStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Tarifs | SNCFT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        <?php require_once 'assets/css/style.css'; ?>
        
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --sidebar-width: 280px;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 56px;
        }

        /* Admin Navigation */
        .admin-navbar {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            height: 56px;
        }

        .avatar-sm {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Sidebar styling */
        .admin-sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            position: fixed;
            height: calc(100vh - 56px);
            top: 56px;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
        }

        .admin-sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.5rem;
            margin: 0.15rem 0;
            border-radius: 0.25rem;
            transition: all 0.2s;
        }

        .admin-sidebar .nav-link:hover, 
        .admin-sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .admin-sidebar .nav-link i {
            width: 24px;
            text-align: center;
            margin-right: 10px;
        }

        .admin-profile {
            text-align: center;
            padding: 1.5rem 0;
        }

        .admin-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            font-size: 2.5rem;
            color: white;
            border: 3px solid rgba(255, 255, 255, 0.2);
        }

        /* Main content area */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all 0.3s;
            min-height: calc(100vh - 56px);
        }

        @media (max-width: 992px) {
            .admin-sidebar {
                margin-left: -100%;
            }
            .admin-sidebar.show {
                margin-left: 0;
            }
            .main-content {
                margin-left: 0;
            }
        }
        
        .tariff-card {
            transition: transform 0.2s;
        }
        
        .tariff-card:hover {
            transform: translateY(-5px);
        }
        
        .rate-badge {
            font-size: 1.2rem;
            padding: 0.5rem 1rem;
        }
    </style>
</head>
<body>
    <!-- Admin Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark admin-navbar fixed-top">
        <div class="container-fluid px-3">
            <!-- Brand with sidebar toggle for mobile -->
            <div class="d-flex align-items-center">
                <button class="btn btn-link me-2 d-lg-none text-white" id="mobileSidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <a class="navbar-brand fw-bold" href="admin-dashboard.php">
                    <i class="fas fa-train me-2"></i>SNCFT Admin
                </a>
            </div>

            <!-- Right side navigation items -->
            <div class="d-flex align-items-center">
                <!-- Notification dropdown -->
                <div class="dropdown me-3">
                    <a class="nav-link dropdown-toggle position-relative p-2" href="#" id="notifDropdown" 
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell fa-lg"></i>
                        <?php if (count($notifications) > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= count($notifications) ?>
                                <span class="visually-hidden">unread notifications</span>
                            </span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notifDropdown">
                        <?php if (empty($notifications)): ?>
                            <li><span class="dropdown-item-text">Aucune notification</span></li>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                                <li>
                                    <a class="dropdown-item" href="#">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-bell text-primary"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-2">
                                                <p class="mb-0"><?= htmlspecialchars($notif['message']) ?></p>
                                                <small class="text-muted">
                                                    <?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar Navigation -->
    <div class="admin-sidebar">
        <div class="admin-profile">
            <div class="admin-avatar">
                <i class="fas fa-user-shield"></i>
            </div>
            <h5 class="mt-3 mb-0"><?= htmlspecialchars($admin['department'] ?? 'Administrateur') ?></h5>
            <small class="text-white-50">Niveau d'accès: <?= $admin['access_level'] ?? 1 ?></small>
        </div>
        
        <ul class="nav flex-column px-3">
            <li class="nav-item">
                <a class="nav-link" href="admin-dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Tableau de bord
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage-clients.php">
                    <i class="fas fa-users"></i> Gestion Clients
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage-stations.php">
                    <i class="fas fa-train"></i> Gestion Gares
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="manage-tariffs.php">
                    <i class="fas fa-money-bill-wave"></i> Tarifs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin-settings.php">
                    <i class="fas fa-cog"></i> Paramètres
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-money-bill-wave me-2"></i>Gestion des Tarifs</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTariffModal">
                <i class="fas fa-plus me-2"></i>Nouveau Tarif
            </button>
        </div>

        <!-- Search and Filter Form -->
        <form method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Rechercher par client..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <input type="number" name="min_rate" class="form-control" 
                           placeholder="Taux minimum" step="0.01"
                           value="<?= htmlspecialchars($min_rate) ?>">
                </div>
                <div class="col-md-3">
                    <input type="number" name="max_rate" class="form-control" 
                           placeholder="Taux maximum" step="0.01"
                           value="<?= htmlspecialchars($max_rate) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i>Filtrer
                    </button>
                </div>
            </div>
        </form>

        <!-- Tariffs Grid -->
        <div class="row g-4">
            <?php foreach ($tariffs as $tariff): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card tariff-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="card-title mb-1"><?= htmlspecialchars($tariff['company_name']) ?></h5>
                                    <small class="text-muted">Code: <?= htmlspecialchars($tariff['account_code']) ?></small>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-link text-dark" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <button class="dropdown-item" data-bs-toggle="modal" 
                                                    data-bs-target="#editTariffModal<?= $tariff['tariff_id'] ?>">
                                                <i class="fas fa-edit me-2"></i>Modifier
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item text-danger" data-bs-toggle="modal" 
                                                    data-bs-target="#deleteTariffModal<?= $tariff['tariff_id'] ?>">
                                                <i class="fas fa-trash me-2"></i>Supprimer
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="text-center">
                                <span class="badge bg-primary rate-badge">
                                    <?= number_format($tariff['base_rate_per_km'], 2) ?> €/km
                                </span>
                            </div>
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    Créé le <?= date('d/m/Y', strtotime($tariff['created_at'])) ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Modal -->
                <div class="modal fade" id="editTariffModal<?= $tariff['tariff_id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="tariff_id" value="<?= $tariff['tariff_id'] ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title">Modifier Tarif</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Client</label>
                                        <input type="text" class="form-control" 
                                               value="<?= htmlspecialchars($tariff['company_name']) ?>" readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Taux de Base (€/km)</label>
                                        <input type="number" name="base_rate_per_km" class="form-control" 
                                               value="<?= $tariff['base_rate_per_km'] ?>" step="0.01" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Delete Modal -->
                <div class="modal fade" id="deleteTariffModal<?= $tariff['tariff_id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="tariff_id" value="<?= $tariff['tariff_id'] ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title">Supprimer Tarif</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Êtes-vous sûr de vouloir supprimer le tarif pour <?= htmlspecialchars($tariff['company_name']) ?> ?</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                    <button type="submit" class="btn btn-danger">Supprimer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add Tariff Modal -->
    <div class="modal fade" id="addTariffModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-header">
                        <h5 class="modal-title">Nouveau Tarif</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Client</label>
                            <select name="client_id" class="form-select" required>
                                <option value="">Sélectionner un client</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= $client['client_id'] ?>">
                                        <?= htmlspecialchars($client['company_name']) ?> 
                                        (<?= htmlspecialchars($client['account_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Taux de Base (€/km)</label>
                            <input type="number" name="base_rate_per_km" class="form-control" 
                                   step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar toggle
        document.getElementById('mobileSidebarToggle').addEventListener('click', function() {
            document.querySelector('.admin-sidebar').classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.admin-sidebar');
            const toggle = document.getElementById('mobileSidebarToggle');
            
            if (window.innerWidth < 992 && 
                !sidebar.contains(event.target) && 
                !toggle.contains(event.target) && 
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
    </script>
</body>
</html> 