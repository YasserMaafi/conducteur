<?php
require_once 'includes/config.php';
require_once 'includes/auth_functions.php';

if (!isLoggedIn() || $_SESSION['user']['role'] !== 'client') {
    header("Location: login.php");
    exit;
}

$user_id   = $_SESSION['user']['id'];
$client_id = getClientIdByUserId($pdo, $user_id);

// Fetch notifications for this client
// Only get personal notifications for clients
$stmt = $pdo->prepare("
    SELECT * FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch client info for sidebar
$stmt = $pdo->prepare("
    SELECT c.*, u.email
      FROM clients c
      JOIN users   u ON c.user_id = u.user_id
     WHERE c.client_id = ?
");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

// Count unread notifications
$unread_count = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) {
        $unread_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notifications | SNCFT Client</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root { --primary-color: #4e73df; }
    body { font-family: 'Segoe UI', sans-serif; background: #f8f9fc; }
    .notification-time { font-size: .85rem; color: #6c757d; }
    .notification-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.1); }
    .border-start { border-left-width: .5rem !important; }
  </style>
</head>
<body>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
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

  <div class="container-fluid mt-4">
    <div class="row">

      <!-- Sidebar -->
      <div class="col-lg-3 mb-4">
        <div class="card shadow-sm mb-4">
          <div class="card-body text-center">
            <div class="avatar bg-primary text-white rounded-circle p-3 mx-auto" style="width:80px;height:80px">
              <i class="fas fa-building fa-2x"></i>
            </div>
            <h5 class="mt-3"><?= htmlspecialchars($client['company_name']) ?></h5>
            <small class="text-muted">Client</small>
          </div>
        </div>

        <div class="list-group mb-4">
          <a href="client-dashboard.php" class="list-group-item list-group-item-action">
            <i class="fas fa-tachometer-alt me-2"></i>Tableau de bord
          </a>
          <a href="new_request.php" class="list-group-item list-group-item-action">
            <i class="fas fa-plus-circle me-2"></i>Nouvelle demande
          </a>
          <a href="interface.php" class="list-group-item list-group-item-action">
            <i class="fas fa-truck me-2"></i>Mes expéditions
          </a>
          <a href="payments.php" class="list-group-item list-group-item-action">
            <i class="fas fa-money-bill-wave me-2"></i>Paiements
          </a>
          <a href="notifications.php" class="list-group-item list-group-item-action active d-flex justify-content-between align-items-center">
            <span><i class="fas fa-bell me-2"></i>Notifications</span>
            <?php if($unread_count): ?>
              <span class="badge bg-danger"><?= $unread_count ?></span>
            <?php endif; ?>
          </a>
          <a href="profile.php" class="list-group-item list-group-item-action">
            <i class="fas fa-user-cog me-2"></i>Profil
          </a>
        </div>

        <div class="card shadow-sm">
          <div class="card-body">
            <h6 class="text-center">Actions Rapides</h6>
            <div class="d-grid gap-2">
              <a href="new_request.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Nouvelle Demande
              </a>
              <a href="interface.php" class="btn btn-success">
                <i class="fas fa-truck me-2"></i>Suivi Expédition
              </a>
              <a href="payments.php" class="btn btn-info">
                <i class="fas fa-money-bill-wave me-2"></i>Paiements
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="col-lg-9">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2 class="h3 mb-0">Notifications</h2>
          <div class="btn-group">
            <button id="markAllRead" class="btn btn-primary">
              <i class="fas fa-check-double me-1"></i>Marquer tout comme lu
            </button>
            <button id="refreshBtn" class="btn btn-outline-secondary" onclick="location.reload()">
              <i class="fas fa-redo"></i>
            </button>
          </div>
        </div>

        <?php if (empty($notifications)): ?>
          <div class="card shadow-sm">
            <div class="card-body text-center py-5">
              <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
              <h5>Aucune notification</h5>
              <p class="text-muted">Vous n'avez aucune notification à afficher</p>
            </div>
          </div>
        <?php else: ?>
          <div class="row g-3">
            <?php foreach ($notifications as $notif):
              $md          = json_decode($notif['metadata'] ?? '{}', true);
              $link        = $md['link'] ?? '#';
              $created_ts  = strtotime($notif['created_at']);
              $time_diff   = time() - $created_ts;
              $time_label  = $time_diff < 86400
                             ? "Aujourd'hui à " . date("H:i", $created_ts)
                             : date("d/m/Y", $created_ts);
              $is_unread   = !$notif['is_read'];
            ?>
              <div class="col-md-6">
                <div
                  class="card notification-card shadow-sm h-100 <?= $is_unread ? 'unread border-start border-primary' : '' ?>"
                  data-id="<?= $notif['id'] ?>">
                  <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                      <h6 class="card-subtitle text-primary mb-0">
                        <i class="fas fa-bell me-2"></i><?= htmlspecialchars($notif['title']) ?>
                      </h6>
                      <small class="notification-time"><?= $time_label ?></small>
                    </div>
                    <p class="card-text"><?= htmlspecialchars($notif['message']) ?></p>
                    <?php if ($is_unread): ?>
                      <span class="badge bg-warning text-dark">Nouveau</span>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($link) ?>" class="stretched-link"></a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    // Mark single notification read
    document.querySelectorAll('.notification-card').forEach(card => {
      card.addEventListener('click', e => {
        // Don't override clicks on real links
        if (e.target.closest('a') && e.target.closest('a').href !== '#') return;

        if (!card.classList.contains('unread')) return;
        const id = card.dataset.id;
        card.classList.remove('unread', 'border-start');

        const badge = card.querySelector('.badge');
        if (badge) badge.remove();

        fetch('mark_notification_read.php?id=' + id, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).catch(err => {
          console.error(err);
          // revert UI on error
          card.classList.add('unread', 'border-start');
          if (!badge) {
            card.querySelector('.card-body').insertAdjacentHTML(
              'beforeend', '<span class="badge bg-warning text-dark">Nouveau</span>'
            );
          }
        });
      });
    });

    // Mark all read
    document.getElementById('markAllRead').addEventListener('click', btn => {
      btn.target.disabled = true;
      btn.target.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Traitement...';

      fetch('mark_all_notifications_read.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          document.querySelectorAll('.notification-card').forEach(c => {
            c.classList.remove('unread', 'border-start');
            const b = c.querySelector('.badge');
            if (b) b.remove();
          });
        }
      })
      .catch(console.error)
      .finally(() => {
        btn.target.disabled = false;
        btn.target.innerHTML = '<i class="fas fa-check-double me-1"></i>Marquer tout comme lu';
      });
    });
  });
  </script>
</body>
</html>
