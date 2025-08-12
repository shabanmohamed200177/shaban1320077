<!DOCTYPE html>
<html lang="ar" dir="rtl">
<?php 
// تضمين نظام الأمان
require_once 'middleware.php';
require_once 'error_handler.php';

// بدء الجلسة مع الحماية
SecurityMiddleware::checkSession();

// التحقق من تسجيل الدخول
SecurityMiddleware::requireLogin();

// تضمين الاتصال بقاعدة البيانات
include 'db_connect.php';

// جلب إعدادات النظام
if(!isset($_SESSION['system'])){
    try {
        $system = $conn->query("SELECT * FROM system_settings")->fetch_array();
        if ($system) {
            foreach($system as $k => $v){
                $_SESSION['system'][$k] = $v;
            }
        }
    } catch (Exception $e) {
        handleError("فشل في جلب إعدادات النظام: " . $e->getMessage());
        $_SESSION['system'] = ['name' => 'نظام إدارة الشحنات'];
    }
}

include 'header.php' 
?>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed text-right">
<div class="wrapper">
  <?php include 'topbar.php' ?>
  <?php include 'sidebar.php' ?>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
  	 <div class="toast" id="alert_toast" role="alert" aria-live="assertive" aria-atomic="true">
	    <div class="toast-body text-white">
	    </div>
	  </div>
    <div id="toastsContainerTopRight" class="toasts-top-right fixed"></div>
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0"><?php echo $title ?></h1>
          </div><!-- /.col -->

        </div><!-- /.row -->
            <hr class="border-primary">
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
         <?php 
            // تنظيف اسم الصفحة لمنع Directory Traversal
            $page = isset($_GET['page']) ? SecurityMiddleware::sanitizeInput($_GET['page']) : 'home';
            
            // التحقق من صحة اسم الصفحة
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $page)) {
                $page = 'home';
            }
            
            $page_file = $page . '.php';
            
            if(!file_exists($page_file)){
                include '404.html';
            } else {
                try {
                    include $page_file;
                } catch (Exception $e) {
                    handleError("فشل في تحميل الصفحة: " . $e->getMessage());
                    echo '<div class="alert alert-danger">حدث خطأ في تحميل الصفحة</div>';
                }
            }
          ?>
      </div><!--/. container-fluid -->
    </section>
    <!-- /.content -->
    <div class="modal fade" id="confirm_modal" role='dialog'>
    <div class="modal-dialog modal-md" role="document">
      <div class="modal-content">
        <div class="modal-header">
        <h5 class="modal-title">Confirmation</h5>
      </div>
      <div class="modal-body">
        <div id="delete_content"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id='confirm' onclick="">Continue</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
      </div>
    </div>
  </div>
  <div class="modal fade" id="uni_modal" role='dialog'>
    <div class="modal-dialog modal-md" role="document">
      <div class="modal-content">
        <div class="modal-header">
        <h5 class="modal-title"></h5>
      </div>
      <div class="modal-body">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id='submit' onclick="$('#uni_modal form').submit()">Save</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
      </div>
      </div>
    </div>
  </div>
  <div class="modal fade" id="uni_modal_right" role='dialog'>
    <div class="modal-dialog modal-full-height  modal-md" role="document">
      <div class="modal-content">
        <div class="modal-header">
        <h5 class="modal-title"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span class="fa fa-arrow-right"></span>
        </button>
      </div>
      <div class="modal-body">
      </div>
      </div>
    </div>
  </div>
  <div class="modal fade" id="viewer_modal" role='dialog'>
    <div class="modal-dialog modal-md" role="document">
      <div class="modal-content">
              <button type="button" class="btn-close" data-dismiss="modal"><span class="fa fa-times"></span></button>
              <img src="" alt="">
      </div>
    </div>
  </div>
  </div>
  <!-- /.content-wrapper -->

  <!-- Control Sidebar -->
  <aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
  </aside>
  <!-- /.control-sidebar -->

  <!-- Main Footer -->
  <footer class="main-footer">
    <strong>Copyright &copy; 2020 <a href="https://www.sourcecodester.com/">sourcecodester.com</a>.</strong>
    All rights reserved.
    <div class="float-right d-none d-sm-inline-block">
      <b><?php echo $_SESSION['system']['name'] ?></b>
    </div>
  </footer>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<!-- Bootstrap -->
<?php include 'footer.php' ?>
</body>
</html>