<?php include('db_connect.php') ?>
<?php
$twhere = "";
if ($_SESSION['login_type'] != 1) $twhere = "";
?>

<link href="https://fonts.googleapis.com/css2?family=Alexandria:wght@400;500;700&display=swap" rel="stylesheet">

<style>
body, .dashboard-card, .welcome-box, .dashboard-modern .label, .dashboard-modern .number {
  font-family: 'Alexandria', Arial, Tahoma, sans-serif !important;
}

/* بوكس الترحيب */
.welcome-box {
  width: 100%;
  background: #fff;
  border-radius: 1.2rem;
  box-shadow: 0 8px 32px rgba(25,119,204,0.09), 0 2px 8px #eee;
  padding: 30px 38px 28px 24px;
  margin-bottom: 38px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 92px;
}
.welcome-text {
  font-size: 2em;
  font-weight: bold;
  color: #1977cc;
  text-align: right;
  margin-bottom: 0;
  letter-spacing: 0.5px;
}
.welcome-desc {
  font-size: 1.16em;
  color: #444;
  margin-top: 8px;
  font-weight: 400;
  text-align: right;
}
.welcome-icon {
  background: linear-gradient(135deg,#1977cc 60%,#5bc0de 100%);
  color: #fff;
  border-radius: 50%;
  width: 74px;
  height: 74px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2.6em;
  margin-left: 18px;
  box-shadow: 0 2px 18px #1977cc29;
}

/* لوحات البيانات */
.dashboard-modern {
  display: flex;
  flex-wrap: wrap;
  gap: 36px;
  margin: 0 0 30px 0;
  justify-content: center;
}
.dashboard-card {
  background: #fff;
  border-radius: 1.2rem;
  box-shadow: 0 8px 32px rgba(25,119,204,0.09), 0 2px 8px #eee;
  padding: 44px 36px;
  min-width: 270px;
  max-width: 360px;
  flex: 1 1 270px;
  position: relative;
  overflow: hidden;
  transition: box-shadow 0.2s, transform 0.2s;
  cursor: pointer;
  height: 148px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
}
.dashboard-card:hover {
  box-shadow: 0 14px 36px rgba(25,119,204,0.18), 0 2px 10px #d1e6f9;
  transform: scale(1.03);
}
.dashboard-card .icon {
  font-size: 3em;
  margin-right: 0;
  margin-left: 28px;
  min-width: 55px;
  text-align: center;
}
.dashboard-card .inner {
  flex: 1;
  text-align: right;
}
.dashboard-card .number {
  font-size: 2.2em;
  font-weight: 700;
  margin-bottom: 8px;
  letter-spacing: 1px;
  color: #1977cc;
  line-height: 1.25;
}
.dashboard-card .label {
  font-size: 1.24em;
  font-weight: 500;
  letter-spacing: 1px;
  color: #444;
  margin-bottom: 3px;
}
.dashboard-card .desc {
  font-size: 1.08em;
  color: #999;
  margin-top: 5px;
  font-weight: 400;
}
.dashboard-card[data-type="delivered"] .icon { color: #20b85a; }
.dashboard-card[data-type="branches"] .icon { color: #1977cc; }
.dashboard-card[data-type="customers"] .icon { color: #ff9800; }
.dashboard-card[data-type="couriers"] .icon { color: #00bcd4; }
.dashboard-card[data-type="agents"] .icon { color: #005fa3; }
.dashboard-card[data-type="parcels"] .icon { color: #8d27bc; }
.dashboard-card[data-type="staff"] .icon { color: #ffc107; }
.dashboard-card[data-type="intransit"] .icon { color: #ecb300; }
.dashboard-card[data-type="accepted"] .icon { color: #7e57c2; }

@media (max-width: 900px) {
  .dashboard-modern { flex-direction: column; gap: 16px; }
  .dashboard-card { min-width: 100%; max-width: 100%; padding: 28px 16px; height:98px;}
  .welcome-box { padding: 18px 14px; min-height:unset; max-height:unset; flex-direction: column; gap: 10px;}
  .welcome-icon { margin: 12px 0 0 0; }
}
</style>

<?php if($_SESSION['login_type'] == 1): ?>
<!-- بوكس ترحيب بعرض كامل -->
<div class="welcome-box">
  <div>
    <div class="welcome-text">مرحباً بك في لوحة التحكم!</div>
    <div class="welcome-desc">نظام الشحن الخاص بك لإدارة الشحنات والعملاء والمناديب والوكلاء بكل احترافية.</div>
  </div>
  <div class="welcome-icon"><i class="fas fa-shipping-fast"></i></div>
</div>

<div class="dashboard-modern">
  <!-- إجمالي الفروع -->
  <div class="dashboard-card" data-type="branches" onclick="location.href='index.php?page=branch_list'">
    <div class="inner">
      <div class="number"><?php echo $conn->query("SELECT * FROM branches")->num_rows; ?></div>
      <div class="label">إجمالي الفروع</div>
      <div class="desc">عدد الفروع المسجلة</div>
    </div>
    <div class="icon"><i class="fa fa-building"></i></div>
  </div>
  <!-- إجمالي العملاء -->
  <div class="dashboard-card" data-type="customers" onclick="location.href='index.php?page=customer_list'">
    <div class="inner">
      <div class="number"><?php echo $conn->query("SELECT * FROM customers")->num_rows; ?></div>
      <div class="label">إجمالي العملاء</div>
      <div class="desc">عدد العملاء المسجلين</div>
    </div>
    <div class="icon"><i class="fa fa-user-friends"></i></div>
  </div>
  <!-- إجمالي المناديب -->
  <div class="dashboard-card" data-type="couriers" onclick="location.href='index.php?page=courier_list'">
    <div class="inner">
      <div class="number"><?php echo $conn->query("SELECT * FROM couriers")->num_rows; ?></div>
      <div class="label">إجمالي المناديب</div>
      <div class="desc">عدد المناديب العاملين بالشركة</div>
    </div>
    <div class="icon"><i class="fa fa-motorcycle"></i></div>
  </div>
  <!-- إجمالي الوكلاء -->
  <div class="dashboard-card" data-type="agents" onclick="location.href='index.php?page=agent_list'">
    <div class="inner">
      <div class="number"><?php echo $conn->query("SELECT * FROM agents")->num_rows; ?></div>
      <div class="label">إجمالي الوكلاء</div>
      <div class="desc">عدد وكلاء شركات الشحن الشريكة</div>
    </div>
    <div class="icon"><i class="fas fa-user-tie"></i></div>
  </div>
  <!-- إجمالي الشحنات -->
  <div class="dashboard-card" data-type="parcels" onclick="location.href='index.php?page=parcel_list'">
    <div class="inner">
      <div class="number"><?php echo $conn->query("SELECT * FROM parcels")->num_rows; ?></div>
      <div class="label">إجمالي الشحنات</div>
      <div class="desc">عدد الطرود والشحنات المسجلة</div>
    </div>
    <div class="icon"><i class="fa fa-boxes"></i></div>
  </div>
  <!-- إجمالي موظفي الفروع -->
  <div class="dashboard-card" data-type="staff" onclick="location.href='index.php?page=staff_list'">
    <div class="inner">
      <div class="number"><?php echo $conn->query("SELECT * FROM users where type != 1")->num_rows; ?></div>
      <div class="label">إجمالي الموظفين</div>
      <div class="desc">عدد موظفي الفروع</div>
    </div>
    <div class="icon"><i class="fa fa-users"></i></div>
  </div>
  <!-- حالات شحنات مختارة -->
  <?php 
    $status_arr = [
      0 => ["تم قبولها من المندوب", "accepted", "fa-check-circle"],
      3 => ["في الطريق", "intransit", "fa-truck-moving"],
      7 => ["تم التوصيل", "delivered", "fa-check-double"]
    ];
    foreach($status_arr as $k => $arr):
      list($label, $type, $fa) = $arr;
  ?>
  <div class="dashboard-card" data-type="<?php echo $type ?>" onclick="location.href='index.php?page=parcel_list&s=<?php echo $k ?>'">
    <div class="inner">
      <div class="number"><?php echo $conn->query("SELECT * FROM parcels where status = {$k} ")->num_rows; ?></div>
      <div class="label"><?php echo $label ?></div>
      <div class="desc">عدد الشحنات في هذه الحالة</div>
    </div>
    <div class="icon"><i class="fa <?php echo $fa ?>"></i></div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
  <!-- بوكس ترحيب بعرض كامل للموظف -->
  <div class="welcome-box">
    <div>
      <div class="welcome-text">مرحباً <?php echo $_SESSION['login_name'] ?>!</div>
      <div class="welcome-desc">يمكنك إدارة الشحنات الخاصة بفرعك وتتبع الطرود وإنشاء تقارير تفصيلية.</div>
    </div>
    <div class="welcome-icon"><i class="fas fa-shipping-fast"></i></div>
  </div>
<?php endif; ?>