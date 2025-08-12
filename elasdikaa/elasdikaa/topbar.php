<!-- شريط التنقل العلوي -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
  <!-- أزرار القائمة الجانبية -->
  <ul class="navbar-nav">
    <li class="nav-item">
      <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
    </li>
  </ul>

  <!-- شريط البحث -->
  <form class="form-inline ml-3">
    <div class="input-group input-group-sm">
      <input class="form-control form-control-navbar" type="search" placeholder="بحث..." aria-label="Search">
      <div class="input-group-append">
        <button class="btn btn-navbar" type="submit">
          <i class="fas fa-search"></i>
        </button>
      </div>
    </div>
  </form>

  <!-- العناصر اليمنى -->
  <ul class="navbar-nav ml-auto">
    <!-- إشعارات -->
    <li class="nav-item dropdown">
      <a class="nav-link" data-toggle="dropdown" href="#">
        <i class="far fa-bell"></i>
        <span class="badge badge-warning navbar-badge">15</span>
      </a>
      <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
        <span class="dropdown-item dropdown-header">15 إشعار</span>
        <div class="dropdown-divider"></div>
        <a href="#" class="dropdown-item">
          <i class="fas fa-envelope mr-2"></i> 4 رسائل جديدة
          <span class="float-right text-muted text-sm">3 دقائق</span>
        </a>
        <div class="dropdown-divider"></div>
        <a href="#" class="dropdown-item">
          <i class="fas fa-users mr-2"></i> 8 طلبات جديدة
          <span class="float-right text-muted text-sm">12 ساعة</span>
        </a>
        <div class="dropdown-divider"></div>
        <a href="#" class="dropdown-item dropdown-footer">عرض جميع الإشعارات</a>
      </div>
    </li>
    
    <!-- قائمة المستخدم -->
    <li class="nav-item dropdown">
      <a class="nav-link" data-toggle="dropdown" href="#">
        <i class="far fa-user"></i>
        <?php echo $_SESSION['login_firstname'] ?? 'المستخدم' ?>
      </a>
      <div class="dropdown-menu dropdown-menu-right">
        <span class="dropdown-item dropdown-header">
          <?php if($_SESSION['login_type'] == 1): ?>
            مدير النظام
          <?php else: ?>
            موظف
          <?php endif; ?>
        </span>
        <div class="dropdown-divider"></div>
        <a href="#" class="dropdown-item">
          <i class="fas fa-user mr-2"></i> الملف الشخصي
        </a>
        <div class="dropdown-divider"></div>
        <a href="ajax.php?action=logout" class="dropdown-item">
          <i class="fas fa-sign-out-alt mr-2"></i> تسجيل الخروج
        </a>
      </div>
    </li>
    
    <!-- زر ملء الشاشة -->
    <li class="nav-item">
      <a class="nav-link" data-widget="fullscreen" href="#" role="button">
        <i class="fas fa-expand-arrows-alt"></i>
      </a>
    </li>
  </ul>
</nav>