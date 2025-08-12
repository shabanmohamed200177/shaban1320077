<aside class="main-sidebar sidebar-dark-primary elevation-4 modern-ar-sidebar">
  <div class="dropdown brand-box">
    <a href="./" class="brand-link text-end">
      <?php if($_SESSION['login_type'] == 1): ?>
      <h3 class="text-end p-0 m-0"><b>المدير</b></h3>
      <?php else: ?>
      <h3 class="text-end p-0 m-0"><b>الموظف</b></h3>
      <?php endif; ?>
    </a>
  </div>
  <div class="sidebar pb-4 mb-4" style="direction: rtl;">
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column nav-flat" data-widget="treeview" role="menu" data-accordion="false">
        <li class="nav-item dropdown">
          <a href="./" class="nav-link nav-home text-end">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <p>الرئيسية</p>
          </a>
        </li>
        <?php if($_SESSION['login_type'] == 1): ?>
        <li class="nav-item">
          <a href="#" class="nav-link nav-edit_branch text-end">
            <i class="nav-icon fas fa-building"></i>
            <p>
              الفروع
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="./index.php?page=new_branch" class="nav-link nav-new_branch tree-item text-end">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>إضافة فرع جديد</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="./index.php?page=branch_list" class="nav-link nav-branch_list tree-item text-end">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>قائمة الفروع</p>
              </a>
            </li>
          </ul>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link nav-edit_staff text-end">
            <i class="nav-icon fas fa-users"></i>
            <p>
              موظفي الفروع
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="./index.php?page=new_staff" class="nav-link nav-new_staff tree-item text-end">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>إضافة موظف جديد</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="./index.php?page=staff_list" class="nav-link nav-staff_list tree-item text-end">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>قائمة الموظفين</p>
              </a>
            </li>
          </ul>
        </li>
        <?php endif; ?>
        <li class="nav-item">
          <a href="#" class="nav-link nav-edit_courier text-end">
            <i class="nav-icon fas fa-motorcycle"></i>
            <p>
              المناديب
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="./index.php?page=new_courier" class="nav-link nav-new_courier tree-item text-end">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>إضافة مندوب جديد</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="./index.php?page=courier_list" class="nav-link nav-courier_list tree-item text-end">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>عرض جميع المناديب</p>
              </a>
            </li>
          </ul>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link nav-edit_customer text-end">
            <i class="nav-icon fas fa-user-friends"></i>
            <p>
              العملاء
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="./index.php?page=customer_list" class="nav-link nav-customer_list tree-item text-end">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>قائمة العملاء</p>
              </a>
            </li>
          </ul>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link nav-edit_agent text-end">
            <i class="nav-icon fas fa-user-tie"></i>
            <p>
              شبكة الوكلاء
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="./index.php?page=new_agent" class="nav-link nav-new_agent tree-item text-end">
                  <i class="fas fa-angle-right nav-icon"></i>
                  <p>إضافة وكيل جديد</p>
                </a>
              </li>
            <li class="nav-item">
              <a href="./index.php?page=agent_list" class="nav-link nav-agent_list tree-item text-end">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>قائمة الوكلاء</p>
              </a>
            </li>
          </ul>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link nav-edit_parcel text-end">
            <i class="nav-icon fas fa-boxes"></i>
            <p>
              الشحنات
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="./index.php?page=new_parcel" class="nav-link nav-new_parcel tree-item text-end">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>إضافة شحنة جديدة</p>
              </a>
            </li>
            <li class="nav-item">
              <!-- Removed &status=-1 to ensure all shipments are shown -->
              <a href="./index.php?page=parcel_list" class="nav-link nav-parcel_list nav-sall tree-item text-end">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>عرض كل الشحنات</p>
              </a>
            </li>
            <?php 
            // تعريف مصفوفة حالات الشحنات الجديدة بناءً على parcel_status (1).sql
            $status_arr = array(
                1  => 'قيد التنفيذ',
                2  => 'تم تسليمها للمندوب',
                3  => 'جاري التوصيل',
                4  => 'تم التسليم بنجاح',
                5  => 'تم الدفع بنجاح',
                6  => 'تم الدفع جزئي',
                7  => 'تم تأجيل الطلب',
                8  => 'تم الرفض',
                9  => 'المرتجعات',
                10 => 'مرتجع للمخزن',
                11 => 'مرتجع للفرع',
                12 => 'مرتجع للعميل'
            );
            foreach($status_arr as $k =>$v):
            ?>
            <li class="nav-item">
              <!-- Changed &s= to &status= to match agent_shipments.php -->
              <a href="./index.php?page=parcel_list&status=<?php echo $k ?>" class="nav-link nav-parcel_list_<?php echo $k ?> tree-item text-end">
                <i class="fas fa-angle-right nav-icon"></i>
                <p><?php echo $v ?></p>
              </a>
            </li>
            <?php endforeach; ?>
          </ul>
        </li>
        <!-- قسم إشعارات WhatsApp -->
        <li class="nav-item">
          <a href="./enhanced_notifications.php" class="nav-link nav-whatsapp_notifications text-end" target="_blank">
            <i class="nav-icon fab fa-whatsapp"></i>
            <p>إرسال واتساب</p>
          </a>
        </li>
        <!-- إضافة قسم توزيع الشحنات على المناطق -->
        <li class="nav-item">
          <a href="#" class="nav-link nav-distribution_area text-end">
            <i class="nav-icon fas fa-map"></i>
            <p>
              توزيع الشحنات على المناطق
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="./index.php?page=area_distribution" class="nav-link nav-area_distribution tree-item text-end">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>لوحة توزيع المناطق</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="./index.php?page=manage_courier_area" class="nav-link nav-manage_courier_area tree-item text-end">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>إدارة مناديب المناطق</p>
              </a>
            </li>
          </ul>
        </li>
        <!-- نهاية قسم توزيع الشحنات -->
        <li class="nav-item has-treeview">
          <a href="#" class="nav-link text-end">
            <i class="nav-icon fas fa-map-marked-alt"></i>
            <p>
              المحافظات والمناطق
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="index.php?page=governorate_list" class="nav-link nav-governorate_list tree-item text-end">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>قائمة المحافظات</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="index.php?page=area_list" class="nav-link nav-area_list tree-item text-end">
                <i class="fas fa-angle-right nav-icon"></i>
                <p>قائمة المناطق</p>
              </a>
            </li>
          </ul>
        </li>
        <!-- تصنيف النظام المالي -->
        <li class="nav-header text-end" style="font-size: 1.1em; margin-top: 10px;">النظام المالي</li>
        <li class="nav-item has-treeview">
          <a href="#" class="nav-link text-end">
            <i class="nav-icon fas fa-wallet"></i>
            <p>
              إدارة النظام المالي
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="./index.php?page=main_vault" class="nav-link nav-main_vault tree-item text-end">
                <i class="nav-icon fas fa-piggy-bank"></i>
                <p>الخزنة الرئيسية</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="./index.php?page=transactions" class="nav-link nav-transactions tree-item text-end">
                <i class="nav-icon fas fa-exchange-alt"></i>
                <p>سجل الحركات المالية</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="./index.php?page=customer_balance" class="nav-link nav-customer_balance tree-item text-end">
                <i class="nav-icon fas fa-user-circle"></i>
                <p>أرصدة العملاء</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="./index.php?page=agent_balance" class="nav-link nav-agent_balance tree-item text-end">
                <i class="nav-icon fas fa-user-tie"></i>
                <p>أرصدة الوكلاء</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="./index.php?page=payouts" class="nav-link nav-payouts tree-item text-end">
                <i class="nav-icon fas fa-money-bill-wave"></i>
                <p>صرف المستحقات</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="./index.php?page=financial_reports" class="nav-link nav-financial_reports tree-item text-end">
                <i class="nav-icon fas fa-chart-line"></i>
                <p>التقارير المالية</p>
              </a>
            </li>
          </ul>
        </li>
        <!-- نهاية النظام المالي -->
        <li class="nav-item dropdown">
          <a href="./index.php?page=track" class="nav-link nav-track text-end">
            <i class="nav-icon fas fa-search"></i>
            <p>تتبع الشحنة</p>
          </a>
        </li>  
        <li class="nav-item dropdown">
          <a href="./index.php?page=reports" class="nav-link nav-reports text-end">
            <i class="nav-icon fas fa-file"></i>
            <p>التقارير</p>
          </a>
        </li>  
      </ul>
    </nav>
  </div>
</aside>
<script>
  $(document).ready(function(){
    var page = '<?php echo isset($_GET['page']) ? $_GET['page'] : 'home' ?>';
    var s = '<?php echo isset($_GET['s']) ? $_GET['s'] : '' ?>'; // Keep 's' for backward compatibility if needed elsewhere
    var statusParam = '<?php echo isset($_GET['status']) ? $_GET['status'] : '' ?>'; // Get the status parameter

    if(statusParam !== '') { // Prioritize status parameter for parcel_list
        page = 'parcel_list_' + statusParam;
    } else if (s !== '') { // Fallback to 's' if status is not set
        page = page + '_' + s;
    } else if (page === 'parcel_list' && statusParam === '') { // Handle "عرض كل الشحنات" specifically
        page = 'parcel_list_all'; // A unique identifier for "all" shipments
    }

    if($('.nav-link.nav-'+page).length > 0){
        $('.nav-link.nav-'+page).addClass('active')
      if($('.nav-link.nav-'+page).hasClass('tree-item') == true){
        $('.nav-link.nav-'+page).closest('.nav-treeview').siblings('a').addClass('active')
        $('.nav-link.nav-'+page).closest('.nav-treeview').parent().addClass('menu-open')
      }
      if($('.nav-link.nav-'+page).hasClass('nav-is-tree') == true){
        $('.nav-link.nav-'+page).parent().addClass('menu-open')
      }
    }
  })
</script>
