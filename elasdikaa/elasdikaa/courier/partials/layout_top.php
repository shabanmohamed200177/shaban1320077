<?php
// Expected variables: $pageTitle, $courierName
if (!isset($pageTitle)) { $pageTitle = 'لوحة المندوب'; }
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars($pageTitle); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    body { font-family: 'Cairo', sans-serif; background: #f5f7fb; }
    .navbar { background: #111827; }
    .navbar .navbar-brand, .navbar .nav-link, .navbar .navbar-text { color: #f9fafb !important; }
    .top-icons .btn { color:#f9fafb; }
    /* Sidebar */
    #sidebarOverlay{ position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:1999; display:none; }
    #sidebar{ position:fixed; top:0; right:-300px; width:280px; height:100%; background:#111827; color:#fff; z-index:2000; transition:right .25s ease; }
    #sidebar.open{ right:0; }
    #sidebar a.list-group-item{ background:transparent; color:#fff; border-color:rgba(255,255,255,.1) }
  </style>
  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
</head>
<body>
  <nav class="navbar navbar-expand-lg">
    <button class="btn btn-link text-light p-0 mr-2" id="btnSidebarToggle"><i class="fas fa-bars"></i></button>
    <a class="navbar-brand" href="dashboard.php">الاصدقاء</a>
    <div class="ml-auto d-flex align-items-center top-icons" style="gap:10px">
      <button class="btn btn-link p-0"><i class="fas fa-qrcode"></i></button>
      <button class="btn btn-link p-0 position-relative" id="btnNotifications">
        <i class="fas fa-bell"></i>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationCount" style="display:none">0</span>
      </button>
      <span class="navbar-text mr-3">مرحبًا، <?php echo htmlspecialchars($courierName, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
  </nav>

  <!-- Sidebar (shared) -->
  <div id="sidebarOverlay"></div>
  <div id="sidebar">
    <div class="sidebar-header">
      <strong>القائمة</strong>
      <button class="btn btn-sm btn-light" id="btnSidebarClose">إغلاق</button>
    </div>
    <div class="list-group list-group-flush">
      <a href="dashboard.php" class="list-group-item d-flex align-items-center"><i class="fa fa-truck ml-2"></i> الشحنات</a>
      <a href="commissions.php" class="list-group-item d-flex align-items-center"><i class="fa fa-receipt ml-2"></i> سجل العمولات</a>
      <a href="history.php" class="list-group-item d-flex align-items-center"><i class="fa fa-clock-rotate-left ml-2"></i> سجل الشحنات</a>
      <a href="settings.php" class="list-group-item d-flex align-items-center"><i class="fa fa-gear ml-2"></i> الإعدادات</a>
      <a href="logout.php" class="list-group-item d-flex align-items-center"><i class="fa fa-right-from-bracket ml-2"></i> تسجيل الخروج</a>
    </div>
    <div class="sidebar-header"><strong>الحالات</strong></div>
    <div class="list-group list-group-flush" id="sidebarCounters"></div>
  </div>

  <!-- مودال الإشعارات -->
  <div class="modal fade" id="notificationsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">الإشعارات</h5>
          <button type="button" class="close" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body" id="notificationsList">
          <!-- سيتم تحميل الإشعارات هنا -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">إغلاق</button>
        </div>
      </div>
    </div>
  </div>


