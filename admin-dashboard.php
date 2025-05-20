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

// Process approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'];
    $notes = $_POST['notes'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // Get full request details with sender client info
        $stmt = $pdo->prepare("
            SELECT fr.*, 
                   c.client_id AS client_id, 
                   c.user_id AS client_user_id, 
                   c.company_name, 
                   u.email, 
                   g1.libelle AS origin, 
                   g2.libelle AS destination,
                   m.description AS merchandise
            FROM freight_requests fr
            JOIN clients c ON fr.sender_client_id = c.client_id
            JOIN users u ON c.user_id = u.user_id
            JOIN gares g1 ON fr.gare_depart = g1.id_gare
            JOIN gares g2 ON fr.gare_arrivee = g2.id_gare
            LEFT JOIN merchandise m ON fr.merchandise_id = m.merchandise_id
            WHERE fr.id = ?
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();

        if (!$request) {
            throw new Exception("Demande non trouvée.");
        }

        if ($action === 'approve') {
            $new_status = 'accepted';
            $stmt = $pdo->prepare("UPDATE freight_requests SET status = ?, admin_notes = ? WHERE id = ?");
            $stmt->execute([$new_status, $notes, $request_id]);
            
            // Prefill contract draft
            $contract_data = [
                'gare_expéditrice' => $request['gare_depart'],
                'gare_destinataire' => $request['gare_arrivee'],
                'sender_client' => $request['sender_client_id'],
                'merchandise_description' => $request['merchandise'] ?? $request['description'],
                'quantity' => $request['quantity'],
                'wagon_count' => $_POST['wagon_count'],
                'payment_mode' => $request['mode_paiement'],
                'price_quoted' => $_POST['price'],
                'estimated_arrival' => $_POST['eta']
            ];
            $_SESSION['contract_draft_' . $request_id] = $contract_data;

            $metadata = [
                'price' => $_POST['price'],
                'wagon_count' => $_POST['wagon_count'],
                'eta' => $_POST['eta'],
                'origin' => $request['origin'],
                'destination' => $request['destination']
            ];
            $message = "Votre demande #$request_id a été approuvée. Prix: {$_POST['price']}€, Wagons: {$_POST['wagon_count']}, ETA: {$_POST['eta']}";
            $notification_type = 'request_approved';
            $notification_title = 'Demande Approuvée';
        } else {
            $new_status = 'rejected';
            $stmt = $pdo->prepare("UPDATE freight_requests SET status = ?, admin_notes = ? WHERE id = ?");
            $stmt->execute([$new_status, $notes, $request_id]);
            
            $metadata = ['reason' => $notes];
            $message = "Votre demande #$request_id a été refusée. Raison: $notes";
            $notification_type = 'demande_refusée';
            $notification_title = 'Demande Rejetée';
        }

        // Insert notification for sender
        $stmt = $pdo->prepare("
            INSERT INTO notifications 
            (user_id, type, title, message, related_request_id, metadata) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $request['client_user_id'],
            $notification_type,
            $notification_title,
            $message,
            $request_id,
            json_encode($metadata)
        ]);

        $pdo->commit();
        $_SESSION['success'] = "Demande #$request_id " . ($action === 'approve' ? 'approuvée' : 'rejetée') . " avec succès";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
    
    header("Location: admin-dashboard.php");
    exit();
}

// Get all pending requests with sender client info
$pending_requests = $pdo->query("
    SELECT fr.id AS request_id, fr.*, 
           c.company_name, c.account_code,
           g1.libelle AS origin, g2.libelle AS destination,
           m.description AS merchandise, m.code AS merchandise_code,
           u.email AS client_email
    FROM freight_requests fr
    JOIN clients c ON fr.sender_client_id = c.client_id
    JOIN users u ON c.user_id = u.user_id
    JOIN gares g1 ON fr.gare_depart = g1.id_gare
    JOIN gares g2 ON fr.gare_arrivee = g2.id_gare
    LEFT JOIN merchandise m ON fr.merchandise_id = m.merchandise_id
    WHERE fr.status = 'pending'
    ORDER BY fr.created_at DESC
")->fetchAll();

// Get unread notifications
$notifications = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? AND is_read = FALSE
    ORDER BY created_at DESC
    LIMIT 5
");
$notifications->execute([$admin_id]);
$notifications = $notifications->fetchAll();

// Get recent activity on requests
$recent_activity = $pdo->query("
    SELECT fr.id AS request_id, fr.status, fr.updated_at, 
           c.company_name, n.metadata
    FROM freight_requests fr
    JOIN clients c ON fr.sender_client_id = c.client_id
    JOIN notifications n ON fr.id = n.related_request_id
    WHERE fr.status IN ('accepted', 'rejected')
    ORDER BY fr.updated_at DESC
    LIMIT 5
")->fetchAll();

// Replace the recent activity query with this (around line 120 in your code):
$confirmed_requests = $pdo->query("
    SELECT fr.id AS request_id, fr.*, 
           c.company_name, c.account_code,
           g1.libelle AS origin, g2.libelle AS destination,
           m.description AS merchandise, m.code AS merchandise_code,
           u.email AS client_email,
           n.metadata
    FROM freight_requests fr
    JOIN clients c ON fr.sender_client_id = c.client_id
    JOIN users u ON c.user_id = u.user_id
    JOIN gares g1 ON fr.gare_depart = g1.id_gare
    JOIN gares g2 ON fr.gare_arrivee = g2.id_gare
    LEFT JOIN merchandise m ON fr.merchandise_id = m.merchandise_id
    JOIN notifications n ON fr.id = n.related_request_id
    WHERE fr.status = 'client_confirmed'
    ORDER BY fr.updated_at DESC
    LIMIT 5
")->fetchAll();
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Admin | SNCFT</title>
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
            padding-top: 56px; /* Space for fixed navbar */
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

        .admin-sidebar .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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

        /* Card styling */
        .dashboard-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            padding: 1rem 1.5rem;
        }

        /* Stats cards */
        .stat-card {
            border-radius: 10px;
            color: white;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: "";
            position: absolute;
            top: -20px;
            right: -20px;
            width: 100px;
            height: 100px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .stat-card i {
            font-size: 2.5rem;
            opacity: 0.8;
            margin-bottom: 1rem;
        }

        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-card .stat-label {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        /* Table styling */
        .data-table {
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table thead th {
            background-color: #f8f9fa;
            border: none;
            padding: 12px 15px;
            font-weight: 600;
            color: var(--secondary-color);
        }

        .data-table tbody tr {
            transition: all 0.2s;
        }

        .data-table tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }

        .data-table tbody td {
            padding: 12px 15px;
            vertical-align: middle;
            border-top: 1px solid #f1f1f1;
        }

        /* Badges */
        .badge {
            padding: 0.35em 0.65em;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        /* Buttons */
        .btn-action {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-radius: 50%;
        }

        /* Price calculator */
        .price-calculator {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 15px;
            }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-fade {
            animation: fadeIn 0.3s ease-out forwards;
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
    <ul class="dropdown-menu dropdown-menu-end border-0 shadow" aria-labelledby="notifDropdown" style="width: 350px;">
        <li class="dropdown-header bg-light py-2 px-3 d-flex justify-content-between align-items-center border-bottom">
            <strong class="text-primary">Notifications</strong>
            <a href="admin-notifications.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
        </li>
        <?php if (empty($notifications)): ?>
            <li class="py-4 px-3 text-center text-muted">
                <i class="fas fa-bell-slash fa-2x mb-2 opacity-50"></i>
                <div>Aucune nouvelle notification</div>
            </li>
        <?php else: ?>
            <div style="max-height: 400px; overflow-y: auto;">
                <?php foreach ($notifications as $notif): ?>
                    <?php
                        // Default link for notifications with no specific action
                        $link = 'javascript:void(0);';
                        $request_id = $notif['related_request_id'] ?? null;
                        
                        // Set links based on notification type
                        switch ($notif['type']) {
                            case 'client_confirmed':
                            case 'client_rejected':
                            case 'request_rejected':
                            case 'request_approved':
                            case 'request_accepted':
                            case 'nouvelle_demande':
                                $link = "admin-request-details.php?id=" . (int)$request_id;
                                break;
                            case 'contract_draft':
                            case 'new_contract_draft':
                                $link = "create_contract.php?request_id=" . (int)$request_id;
                                break;
                            case 'contract_completed':
                                $link = "contract_details.php?id=" . (int)$request_id;
                                break;
                        }
                        
                        $timeAgo = time_elapsed_string($notif['created_at']);
                        $metadata = isset($notif['metadata']) ? json_decode($notif['metadata'], true) : [];
                    ?>
                    <li>
                        <a class="dropdown-item p-3 border-bottom <?= $notif['is_read'] ? '' : 'bg-light' ?>" 
                           href="<?= htmlspecialchars($link) ?>"
                           <?= ($request_id && $request_id > 0) ? '' : 'onclick="return false;"' ?>>
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-3">
                                    <?php switch($notif['type']) {
                                        case 'nouvelle_demande': ?>
                                            <i class="fas fa-file-alt text-primary"></i>
                                            <?php break;
                                        case 'client_confirmed':
                                        case 'request_approved':
                                        case 'request_accepted': ?>
                                            <i class="fas fa-check-circle text-success"></i>
                                            <?php break;
                                        case 'client_rejected':
                                        case 'request_rejected': ?>
                                            <i class="fas fa-times-circle text-danger"></i>
                                            <?php break;
                                        case 'contract_draft':
                                        case 'new_contract_draft': ?>
                                            <i class="fas fa-file-contract text-info"></i>
                                            <?php break;
                                        case 'contract_completed': ?>
                                            <i class="fas fa-file-signature text-success"></i>
                                            <?php break;
                                        default: ?>
                                            <i class="fas fa-bell text-warning"></i>
                                    <?php } ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <strong><?= htmlspecialchars($notif['title']) ?></strong>
                                        <small class="text-muted"><?= htmlspecialchars($timeAgo) ?></small>
                                    </div>
                                    <div class="text-muted small"><?= htmlspecialchars($notif['message']) ?></div>
                                    <?php if (!empty($metadata)): ?>
                                        <div class="mt-1">
                                            <?php if (isset($metadata['price'])): ?>
                                                <small class="text-success"><?= htmlspecialchars($metadata['price']) ?> €</small>
                                            <?php endif; ?>
                                            <?php if (isset($metadata['wagon_count'])): ?>
                                                <small class="text-primary ms-2"><?= htmlspecialchars($metadata['wagon_count']) ?> wagons</small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <li class="dropdown-footer bg-light py-2 px-3 text-center border-top">
            <small class="text-muted"><?= count($notifications) ?> notification(s) non lue(s)</small>
        </li>
    </ul>
</div>

                <!-- User dropdown -->
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" 
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="me-2 d-none d-sm-block text-end">
                            <div class="fw-semibold text-white"><?= htmlspecialchars($admin['full_name'] ?? 'Administrateur') ?></div>
                            <small class="text-white-50"><?= htmlspecialchars($admin['department'] ?? 'Admin') ?></small>
                        </div>
                        <div class="avatar-sm bg-white text-primary rounded-circle d-flex align-items-center justify-content-center">
                            <i class="fas fa-user-shield"></i>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                        <li><h6 class="dropdown-header">Compte Administrateur</h6></li>
                        <li><a class="dropdown-item" href="admin-profile.php"><i class="fas fa-user-cog me-2"></i>Profil</a></li>
                        <li><a class="dropdown-item" href="admin-settings.php"><i class="fas fa-cog me-2"></i>Paramètres</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Déconnexion</a></li>
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
                <a class="nav-link active" href="admin-dashboard.php">
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
                <a class="nav-link" href="manage-tariffs.php">
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
        <div class="row">
            <div class="col-12">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show animate-fade">
                        <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show animate-fade">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card bg-primary">
                    <i class="fas fa-clock"></i>
                    <div class="stat-value"><?= count($pending_requests) ?></div>
                    <div class="stat-label">Demandes en attente</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card bg-success">
                    <i class="fas fa-check-circle"></i>
                    <div class="stat-value">12</div>
                    <div class="stat-label">Demandes approuvées</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card bg-danger">
                    <i class="fas fa-times-circle"></i>
                    <div class="stat-value">3</div>
                    <div class="stat-label">Demandes rejetées</div>
                </div>
            </div>
        </div>

        <!-- Pending Requests Card -->
        <div class="card dashboard-card mb-4 animate-fade">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-clock me-2 text-warning"></i>Demandes en Attente</h5>
                <span class="badge bg-primary rounded-pill"><?= count($pending_requests) ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($pending_requests)): ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>Aucune demande en attente
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Trajet</th>
                                    <th>Marchandise</th>
                                    <th>Quantité</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_requests as $request): ?>
                                    <tr>
                                        <td><span class="badge bg-light text-dark">FR-<?= $request['request_id'] ?></span></td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($request['company_name']) ?></div>
                                            <small class="text-muted"><?= $request['client_email'] ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="text-danger me-2"><i class="fas fa-map-marker-alt"></i></span>
                                                <div>
                                                    <div><?= htmlspecialchars($request['origin']) ?></div>
                                                    <div class="text-muted small">à</div>
                                                    <div><?= htmlspecialchars($request['destination']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($request['merchandise'] ?? $request['description']) ?>
                                            <?php if (!empty($request['merchandise_code'])): ?>
                                                <br><small class="text-muted">Code: <?= $request['merchandise_code'] ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= number_format($request['quantity'], 0, ',', ' ') ?> kg</td>
                                        <td><?= date('d/m/Y', strtotime($request['date_start'])) ?></td>
                                        <td>
                                            <div class="d-flex">
                                                <button class="btn-action btn-outline-primary me-1" data-bs-toggle="modal"
                                                    data-bs-target="#detailsModal<?= $request['request_id'] ?>"
                                                    title="Voir détails">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-action btn-success me-1" data-bs-toggle="modal"
                                                    data-bs-target="#approveModal<?= $request['request_id'] ?>"
                                                    title="Approuver">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn-action btn-danger" data-bs-toggle="modal"
                                                    data-bs-target="#rejectModal<?= $request['request_id'] ?>"
                                                    title="Rejeter">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Confirmed Requests Card -->
        <div class="card dashboard-card mb-4 animate-fade">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-check-circle me-2 text-success"></i>Demandes Confirmées</h5>
                <span class="badge bg-success rounded-pill"><?= count($confirmed_requests) ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($confirmed_requests)): ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>Aucune demande confirmée récemment
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Trajet</th>
                                    <th>Marchandise</th>
                                    <th>Prix</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($confirmed_requests as $request): 
                                    $meta = json_decode($request['metadata'] ?? '{}', true);
                                ?>
                                    <tr>
                                        <td><span class="badge bg-light text-dark">FR-<?= $request['request_id'] ?></span></td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($request['company_name']) ?></div>
                                            <small class="text-muted"><?= $request['client_email'] ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="text-danger me-2"><i class="fas fa-map-marker-alt"></i></span>
                                                <div>
                                                    <div><?= htmlspecialchars($request['origin']) ?></div>
                                                    <div class="text-muted small">à</div>
                                                    <div><?= htmlspecialchars($request['destination']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($request['merchandise'] ?? $request['description']) ?></td>
                                        <td>
                                            <?php if (isset($meta['price'])): ?>
                                                <span class="fw-bold"><?= htmlspecialchars($meta['price']) ?> €</span>
                                                <br>
                                                <small class="text-muted">
                                                    <?= isset($meta['wagon_count']) ? htmlspecialchars($meta['wagon_count']) . ' wagons' : '' ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex">
                                                <a href="request_details.php?id=<?= $request['request_id'] ?>" 
                                                   class="btn-action btn-outline-primary me-1"
                                                   title="Voir détails">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="create_contract.php?request_id=<?= $request['request_id'] ?>" 
                                                   class="btn-action btn-success"
                                                   title="Créer contrat">
                                                    <i class="fas fa-file-contract"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity Card -->
        <div class="card dashboard-card animate-fade">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-history me-2 text-info"></i>Activité Récente</h5>
                <a href="#" class="btn btn-sm btn-outline-secondary">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recent_activity)): ?>
                    <div class="alert alert-info m-3">
                        <i class="fas fa-info-circle me-2"></i>Aucune activité récente
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="list-group-item border-0 py-3 px-4">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="me-3">
                                        <div class="d-flex align-items-center mb-1">
                                            <span class="badge <?= $activity['status'] === 'accepted' ? 'bg-success' : 'bg-danger' ?> me-2">
                                                <?= $activity['status'] === 'accepted' ? 'Approuvée' : 'Rejetée' ?>
                                            </span>
                                            <strong>Demande #<?= $activity['request_id'] ?></strong>
                                        </div>
                                        <div class="text-muted small">
                                            <?= htmlspecialchars($activity['company_name']) ?>
                                        </div>
                                    </div>
                                    <small class="text-muted text-nowrap">
                                        <?= date('d/m/Y H:i', strtotime($activity['updated_at'])) ?>
                                    </small>
                                </div>
                                <?php if ($activity['status'] === 'accepted' && $activity['metadata']): ?>
                                    <?php $meta = json_decode($activity['metadata'], true); ?>
                                    <div class="mt-2 d-flex flex-wrap gap-2">
                                        <?php if (isset($meta['price'])): ?>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-euro-sign text-success me-1"></i>
                                                <?= htmlspecialchars($meta['price']) ?> €
                                            </span>
                                        <?php endif; ?>
                                        <?php if (isset($meta['wagon_count'])): ?>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-train text-primary me-1"></i>
                                                <?= htmlspecialchars($meta['wagon_count']) ?> wagons
                                            </span>
                                        <?php endif; ?>
                                        <?php if (isset($meta['eta'])): ?>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-calendar-check text-info me-1"></i>
                                                <?= htmlspecialchars($meta['eta']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modals for each request -->
    <?php foreach ($pending_requests as $request): ?>
        <!-- Details Modal -->
        <div class="modal fade" id="detailsModal<?= $request['request_id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-info-circle me-2"></i>Détails Demande #<?= $request['request_id'] ?>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-4">
                            <!-- Client Information Card -->
                            <div class="col-md-6">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="fas fa-building me-2"></i>Informations Client</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label text-muted"><strong>Entreprise</strong></label>
                                            <p class="border-bottom pb-2"><?= !empty($request['company_name']) ? htmlspecialchars($request['company_name']) : '<span class="text-secondary fst-italic">Aucun</span>' ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted"><strong>Contact du client Destinataire</strong></label>
                                            <p class="border-bottom pb-2"><?= !empty($request['recipient_contact']) ? htmlspecialchars($request['recipient_contact']) : '<span class="text-secondary fst-italic">Aucun</span>' ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted"><strong>Email</strong></label>
                                            <p class="border-bottom pb-2">
                                                <?php if (!empty($request['client_email'])): ?>
                                                    <a href="mailto:<?= htmlspecialchars($request['client_email']) ?>" class="text-decoration-none">
                                                        <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($request['client_email']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-secondary fst-italic">Aucun</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted"><strong>Code Compte</strong></label>
                                            <p class="border-bottom pb-2">
                                                <?php if (!empty($request['account_code'])): ?>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars($request['account_code']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-secondary fst-italic">Aucun</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Shipping Details Card -->
                            <div class="col-md-6">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="fas fa-truck me-2"></i>Détails Expédition</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label text-muted"><strong>Trajet</strong></label>
                                            <p class="border-bottom pb-2">
                                                <?php if (!empty($request['origin']) && !empty($request['destination'])): ?>
                                                    <i class="fas fa-map-marker-alt text-danger me-1"></i><?= htmlspecialchars($request['origin']) ?>
                                                    <i class="fas fa-arrow-right mx-2"></i>
                                                    <i class="fas fa-map-marker-alt text-success me-1"></i><?= htmlspecialchars($request['destination']) ?>
                                                <?php else: ?>
                                                    <span class="text-secondary fst-italic">Aucun</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted"><strong>Marchandise</strong></label>
                                            <p class="border-bottom pb-2">
                                                <?php 
                                                $merchandise = !empty($request['merchandise']) ? $request['merchandise'] : (!empty($request['description']) ? $request['description'] : '');
                                                if (!empty($merchandise)): 
                                                ?>
                                                    <i class="fas fa-box me-1"></i><?= htmlspecialchars($merchandise) ?>
                                                    <?php if (!empty($request['merchandise_code'])): ?>
                                                        <br><small class="text-muted">Code: <?= htmlspecialchars($request['merchandise_code']) ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-secondary fst-italic">Aucun</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted"><strong>Quantité</strong></label>
                                            <p class="border-bottom pb-2">
                                                <?php if (!empty($request['quantity'])): ?>
                                                    <i class="fas fa-weight me-1"></i><?= number_format($request['quantity'], 0, ',', ' ') ?> kg
                                                <?php else: ?>
                                                    <span class="text-secondary fst-italic">Aucun</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted"><strong>Date souhaitée</strong></label>
                                            <p class="border-bottom pb-2">
                                                <?php if (!empty($request['date_start'])): ?>
                                                    <i class="far fa-calendar-alt me-1"></i><?= date('d/m/Y', strtotime($request['date_start'])) ?>
                                                <?php else: ?>
                                                    <span class="text-secondary fst-italic">Aucun</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted"><strong>Mode de paiement</strong></label>
                                            <p class="border-bottom pb-2">
                                                <?php if (!empty($request['mode_paiement'])): ?>
                                                    <i class="fas fa-credit-card me-1"></i><?= htmlspecialchars($request['mode_paiement']) ?>
                                                <?php else: ?>
                                                    <span class="text-secondary fst-italic">Aucun</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Fermer
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approve Modal -->
        <div class="modal fade" id="approveModal<?= $request['request_id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="request_id" value="<?= $request['request_id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-check-circle me-2"></i>Approuver Demande #<?= $request['request_id'] ?>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="price-calculator">
                                <h6><i class="fas fa-calculator me-2"></i>Calculateur de Prix</h6>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label">Distance (km)</label>
                                        <input type="number" class="form-control distance-input" 
                                            data-origin="<?= $request['gare_depart'] ?>" 
                                            data-destination="<?= $request['gare_arrivee'] ?>"
                                            placeholder="Calcul automatique">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Tarif (€/km)</label>
                                        <input type="number" class="form-control tariff-input" step="0.01" value="0.25">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Prix Calculé</label>
                                        <input type="number" class="form-control calculated-price" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Prix proposé (€)</label>
                                <input type="number" name="price" class="form-control price-input" step="0.01" min="0" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nombre de wagons</label>
                                <input type="number" name="wagon_count" class="form-control" min="1" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Date estimée d'arrivée</label>
                                <input type="date" name="eta" class="form-control" min="<?= date('Y-m-d', strtotime($request['date_start'])) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notes (optionnel)</label>
                                <textarea class="form-control" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Annuler
                            </button>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check me-1"></i>Confirmer l'approbation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <div class="modal fade" id="rejectModal<?= $request['request_id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="request_id" value="<?= $request['request_id'] ?>">
                        <input type="hidden" name="action" value="deny">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-times-circle me-2"></i>Refuser Demande #<?= $request['request_id'] ?>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Cette action ne peut pas être annulée. Le client sera notifié du refus.
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Raison du refus <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="notes" rows="4" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Annuler
                            </button>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-ban me-1"></i>Confirmer le refus
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('mobileSidebarToggle').addEventListener('click', function() {
            document.querySelector('.admin-sidebar').classList.toggle('show');
        });

        // Price calculator functionality
        document.querySelectorAll('.distance-input').forEach(input => {
            input.addEventListener('change', function() {
                const distance = parseFloat(this.value) || 0;
                const tariff = parseFloat(this.closest('.price-calculator').querySelector('.tariff-input').value) || 0;
                const price = distance * tariff;
                this.closest('.price-calculator').querySelector('.calculated-price').value = price.toFixed(2);
                
                // Auto-fill the price field if empty
                const priceInput = this.closest('.modal-body').querySelector('.price-input');
                if (!priceInput.value) {
                    priceInput.value = price.toFixed(2);
                }
            });
        });

        // Auto-calculate distance (placeholder for actual implementation)
        document.querySelectorAll('.distance-input[data-origin][data-destination]').forEach(input => {
            // In a real implementation, you would fetch the distance between stations here
            // This is just a placeholder that sets a random distance for demonstration
            if (!input.value) {
                const randomDistance = Math.floor(Math.random() * 500) + 50;
                input.value = randomDistance;
                input.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>

<?php
// Helper function to display time elapsed
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $string = array(
        'y' => 'an',
        'm' => 'mois',
        'd' => 'jour',
        'h' => 'heure',
        'i' => 'minute',
        's' => 'seconde',
    );
    
    foreach ($string as $k => $v) {
        if ($diff->$k) {
            $parts[] = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        }
    }

    if (!$full) $parts = array_slice($parts, 0, 1);
    return $parts ? implode(', ', $parts) . ' il y a' : 'à l\'instant';
}
?>