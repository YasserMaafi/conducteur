<?php
require_once 'includes/config.php';
require_once 'includes/auth_functions.php';

// Verify client role
if (!isLoggedIn() || $_SESSION['user']['role'] !== 'client') {
    redirect('index.php');
}

// Get client data
$stmt = $pdo->prepare("SELECT c.*, u.email FROM clients c JOIN users u ON c.user_id = u.user_id WHERE c.user_id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$client = $stmt->fetch();

// Get filter parameters
$payment_status = $_GET['payment_status'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$query = "
    SELECT c.*, 
           p.payment_date, p.payment_method, p.reference_number, p.status as payment_status,
           g1.libelle AS origin, g1.code_gare AS origin_code,
           g2.libelle AS destination, g2.code_gare AS destination_code
    FROM contracts c
    JOIN gares g1 ON c.gare_expéditrice = g1.id_gare
    JOIN gares g2 ON c.gare_destinataire = g2.id_gare
    LEFT JOIN payments p ON c.contract_id = p.contract_id
    WHERE c.sender_client = ?
";

$params = [$client['client_id']];

if ($payment_status !== 'all') {
    $query .= " AND p.status = ?";
    $params[] = $payment_status;
}

if ($date_from) {
    $query .= " AND p.payment_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND p.payment_date <= ?";
    $params[] = $date_to . ' 23:59:59';
}

$query .= " ORDER BY p.payment_date DESC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Paiements | SNCFT Client</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-brand {
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        
        .payment-status {
            font-size: 0.75rem;
            padding: 0.5em 0.75em;
            border-radius: 50rem;
        }
        
        .payment-status.completed {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
        }
        
        .payment-status.pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .payment-status.failed {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .filters {
            background-color: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .table th {
            font-weight: 600;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="client-dashboard.php">
                <i class="fas fa-train me-2"></i>SNCFT Client
            </a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">
                    <i class="fas fa-building me-1"></i><?= htmlspecialchars($client['company_name']) ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">Mes Paiements</h4>
                <p class="text-muted mb-0">Historique et détails de vos paiements</p>
            </div>
            <a href="client-dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Retour au tableau de bord
            </a>
        </div>

        <!-- Filters -->
        <div class="filters mb-4">
            <form class="row g-3" method="GET">
                <div class="col-md-4">
                    <label class="form-label">Statut de paiement</label>
                    <select name="payment_status" class="form-select">
                        <option value="all" <?= $payment_status === 'all' ? 'selected' : '' ?>>Tous les statuts</option>
                        <option value="completed" <?= $payment_status === 'completed' ? 'selected' : '' ?>>Complété</option>
                        <option value="pending" <?= $payment_status === 'pending' ? 'selected' : '' ?>>En attente</option>
                        <option value="failed" <?= $payment_status === 'failed' ? 'selected' : '' ?>>Échoué</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date de début</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date de fin</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-2"></i>Filtrer
                    </button>
                    <a href="client-payments.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Réinitialiser
                    </a>
                </div>
            </form>
        </div>

        <!-- Payments Table -->
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <?php if (empty($payments)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                        <h5>Aucun paiement trouvé</h5>
                        <p class="text-muted">Aucun paiement ne correspond à vos critères de recherche</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID Contrat</th>
                                    <th>Trajet</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th>Date de paiement</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark">CT-<?= $payment['contract_id'] ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold">
                                                <?= htmlspecialchars($payment['origin']) ?> → <?= htmlspecialchars($payment['destination']) ?>
                                            </div>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($payment['origin_code']) ?> → <?= htmlspecialchars($payment['destination_code']) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?= number_format($payment['total_port_due'], 2) ?> €</div>
                                            <small class="text-muted">Port dû</small>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            $statusText = '';
                                            switch ($payment['payment_status']) {
                                                case 'completed':
                                                    $statusClass = 'completed';
                                                    $statusText = 'Complété';
                                                    break;
                                                case 'pending':
                                                    $statusClass = 'pending';
                                                    $statusText = 'En attente';
                                                    break;
                                                case 'failed':
                                                    $statusClass = 'failed';
                                                    $statusText = 'Échoué';
                                                    break;
                                                default:
                                                    $statusClass = 'pending';
                                                    $statusText = 'En attente';
                                            }
                                            ?>
                                            <span class="payment-status <?= $statusClass ?>">
                                                <i class="fas fa-circle me-1"></i><?= $statusText ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($payment['payment_date']): ?>
                                                <?= date('d/m/Y', strtotime($payment['payment_date'])) ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($payment['payment_method']) ?>
                                                    <?php if ($payment['reference_number']): ?>
                                                        <br>Ref: <?= htmlspecialchars($payment['reference_number']) ?>
                                                    <?php endif; ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="payment-details.php?id=<?= $payment['contract_id'] ?>" 
                                               class="btn btn-outline-primary btn-sm">
                                               <i class="fas fa-eye me-1"></i> Détails
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white py-4 mt-5 border-top">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0 text-muted">&copy; <?= date('Y') ?> SNCFT - Société Nationale des Chemins de Fer Tunisiens</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0 text-muted">Système de gestion des contrats de fret</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 