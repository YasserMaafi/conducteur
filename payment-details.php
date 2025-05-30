<?php
require_once 'includes/config.php';
require_once 'includes/auth_functions.php';

// Verify client role
if (!isLoggedIn() || $_SESSION['user']['role'] !== 'client') {
    redirect('index.php');
}

// Get contract ID
$contract_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$contract_id) {
    $_SESSION['error'] = "ID de contrat invalide";
    redirect('client-payments.php');
}

// Get client data
$stmt = $pdo->prepare("SELECT c.*, u.email FROM clients c JOIN users u ON c.user_id = u.user_id WHERE c.user_id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$client = $stmt->fetch();

// Get payment and contract details
$stmt = $pdo->prepare("
    SELECT c.*, 
           p.payment_date, p.payment_method, p.reference_number, p.status as payment_status,
           p.notes as payment_notes,
           g1.libelle AS origin, g1.code_gare AS origin_code,
           g2.libelle AS destination, g2.code_gare AS destination_code,
           u.username as agent_name, a.badge_number as agent_badge -- Changed to use username instead of direct name
    FROM contracts c
    JOIN gares g1 ON c.gare_expéditrice = g1.id_gare
    JOIN gares g2 ON c.gare_destinataire = g2.id_gare
    LEFT JOIN payments p ON c.contract_id = p.contract_id
    LEFT JOIN agents a ON c.agent_id = a.agent_id
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE c.contract_id = ? AND c.sender_client = ?
");
$stmt->execute([$contract_id, $client['client_id']]);
$payment = $stmt->fetch();

if (!$payment) {
    $_SESSION['error'] = "Paiement non trouvé";
    redirect('client-payments.php');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du Paiement | SNCFT Client</title>
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
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .info-section {
            border-left: 4px solid #0d6efd;
            padding-left: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .info-section h6 {
            color: #0d6efd;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .info-label {
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .info-value {
            font-weight: 500;
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
                <h4 class="mb-1">Détails du Paiement</h4>
                <p class="text-muted mb-0">Contrat #<?= $contract_id ?></p>
            </div>
            <a href="client-payments.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Retour aux paiements
            </a>
        </div>

        <!-- Payment Details -->
        <div class="row">
            <div class="col-lg-8">
                <!-- Contract Information -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="fas fa-file-contract me-2"></i>Informations du Contrat
                        </h5>
                        
                        <div class="info-section">
                            <h6><i class="fas fa-route me-2"></i>Détails du Trajet</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="info-label">Gare d'Origine</div>
                                    <div class="info-value">
                                        <?= htmlspecialchars($payment['origin']) ?>
                                        <small class="text-muted">(<?= htmlspecialchars($payment['origin_code']) ?>)</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-label">Gare de Destination</div>
                                    <div class="info-value">
                                        <?= htmlspecialchars($payment['destination']) ?>
                                        <small class="text-muted">(<?= htmlspecialchars($payment['destination_code']) ?>)</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="info-section">
                            <h6><i class="fas fa-box me-2"></i>Détails de la Marchandise</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="info-label">Type de Marchandise</div>
                                    <div class="info-value"><?= htmlspecialchars($payment['merchandise_description']) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-label">Poids</div>
                                    <div class="info-value"><?= number_format($payment['shipment_weight'], 2) ?> kg</div>
                                </div>
                            </div>
                        </div>

                        <div class="info-section">
                            <h6><i class="fas fa-money-bill-wave me-2"></i>Détails du Paiement</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="info-label">Montant Total</div>
                                    <div class="info-value h5 text-primary mb-0">
                                        <?= number_format($payment['total_port_due'], 2) ?> €
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-label">Statut du Paiement</div>
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
                                </div>
                            </div>
                            <?php if ($payment['payment_date']): ?>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="info-label">Date de Paiement</div>
                                        <div class="info-value"><?= date('d/m/Y', strtotime($payment['payment_date'])) ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="info-label">Méthode de Paiement</div>
                                        <div class="info-value"><?= htmlspecialchars($payment['payment_method']) ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="info-label">Référence</div>
                                        <div class="info-value"><?= htmlspecialchars($payment['reference_number']) ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($payment['payment_notes']): ?>
                            <div class="info-section">
                                <h6><i class="fas fa-sticky-note me-2"></i>Notes</h6>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($payment['payment_notes'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Agent Information -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="fas fa-user-tie me-2"></i>Agent Responsable
                        </h5>
                        <?php if ($payment['agent_name']): ?>
                            <div class="text-center mb-3">
                                <div class="avatar bg-primary text-white rounded-circle mb-3 mx-auto" style="width: 80px; height: 80px;">
                                    <i class="fas fa-user-tie fa-2x"></i>
                                </div>
                                <h6 class="mb-1"><?= htmlspecialchars($payment['agent_name']) ?></h6>
                                <?php if ($payment['agent_badge']): ?>
                                    <span class="badge bg-light text-dark">
                                        Badge: <?= htmlspecialchars($payment['agent_badge']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-user-slash fa-2x mb-2"></i>
                                <p class="mb-0">Aucun agent assigné</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="fas fa-bolt me-2"></i>Actions Rapides
                        </h5>
                        <div class="d-grid gap-2">
                            <a href="contract_details.php?id=<?= $contract_id ?>" class="btn btn-outline-primary">
                                <i class="fas fa-file-contract me-2"></i>Voir le Contrat
                            </a>
                            <a href="track_shipment.php?id=<?= $contract_id ?>" class="btn btn-outline-info">
                                <i class="fas fa-map-marked-alt me-2"></i>Suivre l'Expédition
                            </a>
                            <button class="btn btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print me-2"></i>Imprimer les Détails
                            </button>
                        </div>
                    </div>
                </div>
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