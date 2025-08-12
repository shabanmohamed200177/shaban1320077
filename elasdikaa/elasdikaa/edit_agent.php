<?php
// تضمين ملف الاتصال بقاعدة البيانات
include 'db_connect.php';

// التحقق من صلاحية المستخدم
if (!isset($_SESSION['login_id'])) {
    header("Location: login.php");
    exit;
}

// التحقق من وجود ID الوكيل في الرابط
if (!isset($_GET['id'])) {
    // توجيه المستخدم إلى صفحة قائمة الوكلاء إذا لم يتم تحديد ID
    header("Location: index.php?page=agent_list");
    exit;
}

$agent_id = intval($_GET['id']);

// جلب بيانات الوكيل من قاعدة البيانات
$agent_query = $conn->query("SELECT * FROM agents WHERE id = $agent_id");
if ($agent_query->num_rows === 0) {
    // عرض رسالة خطأ إذا لم يتم العثور على الوكيل
    echo '<div class="container mt-5"><div class="alert alert-danger text-center">الوكيل غير موجود.</div></div>';
    exit;
}

$agent_data = $agent_query->fetch_assoc();

// معالجة طلب تحديث البيانات
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // جلب البيانات من النموذج
    $company_name = $_POST['company_name'];
    $contact_person = $_POST['contact_person'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $geo_area = $_POST['geo_area'];
    $coop_type = $_POST['coop_type'];
    $commission_type = $_POST['commission_type'];
    $commission_value = $_POST['commission_value'];
    $status = $_POST['status'];

    // تحديث البيانات في قاعدة البيانات
    $update_query = $conn->prepare("UPDATE agents SET company_name = ?, contact_person = ?, phone = ?, email = ?, address = ?, geo_area = ?, coop_type = ?, commission_type = ?, commission_value = ?, status = ? WHERE id = ?");
    $update_query->bind_param("sssssssidii", $company_name, $contact_person, $phone, $email, $address, $geo_area, $coop_type, $commission_type, $commission_value, $status, $agent_id);

    if ($update_query->execute()) {
        echo '<div class="container mt-5"><div class="alert alert-success text-center">تم تحديث بيانات الوكيل بنجاح.</div></div>';
        // يمكنك توجيه المستخدم إلى صفحة البروفايل بعد التحديث
        echo '<script>window.location.href = "index.php?page=agent_profile&id=' . $agent_id . '";</script>';
    } else {
        echo '<div class="container mt-5"><div class="alert alert-danger text-center">حدث خطأ أثناء تحديث البيانات.</div></div>';
    }
}
?>

<div class="container py-5" dir="rtl">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-header bg-gradient-info text-white rounded-top-4">
                    <h3 class="mb-0"><i class="fas fa-edit"></i> تعديل بيانات الوكيل: <?php echo htmlspecialchars($agent_data['company_name']); ?></h3>
                </div>
                <div class="card-body bg-white">
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="company_name" class="form-label">اسم الشركة</label>
                                <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo htmlspecialchars($agent_data['company_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="contact_person" class="form-label">اسم المسؤول</label>
                                <input type="text" class="form-control" id="contact_person" name="contact_person" value="<?php echo htmlspecialchars($agent_data['contact_person']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">رقم الجوال</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($agent_data['phone']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($agent_data['email']); ?>">
                            </div>
                            <div class="col-12">
                                <label for="address" class="form-label">العنوان</label>
                                <textarea class="form-control" id="address" name="address"><?php echo htmlspecialchars($agent_data['address']); ?></textarea>
                            </div>
                            <div class="col-12">
                                <label for="geo_area" class="form-label">النطاق الجغرافي</label>
                                <input type="text" class="form-control" id="geo_area" name="geo_area" value="<?php echo htmlspecialchars($agent_data['geo_area']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="coop_type" class="form-label">نوع التعاون</label>
                                <select class="form-control" id="coop_type" name="coop_type">
                                    <option value="to_them" <?php echo $agent_data['coop_type'] == 'to_them' ? 'selected' : ''; ?>>نرسل لهم الطلبيات</option>
                                    <option value="from_them" <?php echo $agent_data['coop_type'] == 'from_them' ? 'selected' : ''; ?>>يطلبون منا الشحن</option>
                                    <option value="exchange" <?php echo $agent_data['coop_type'] == 'exchange' ? 'selected' : ''; ?>>تبادل شحنات</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="commission_type" class="form-label">نوع العمولة</label>
                                <select class="form-control" id="commission_type" name="commission_type">
                                    <option value="percent" <?php echo $agent_data['commission_type'] == 'percent' ? 'selected' : ''; ?>>نسبة مئوية</option>
                                    <option value="fixed" <?php echo $agent_data['commission_type'] == 'fixed' ? 'selected' : ''; ?>>قيمة ثابتة</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="commission_value" class="form-label">قيمة العمولة</label>
                                <input type="number" step="0.01" class="form-control" id="commission_value" name="commission_value" value="<?php echo htmlspecialchars($agent_data['commission_value']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label">الحالة</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="1" <?php echo $agent_data['status'] == 1 ? 'selected' : ''; ?>>نشط</option>
                                    <option value="0" <?php echo $agent_data['status'] == 0 ? 'selected' : ''; ?>>موقوف</option>
                                </select>
                            </div>
                        </div>
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-info mx-2"><i class="fas fa-save"></i> حفظ التعديلات</button>
                            <a href="index.php?page=agent_profile&id=<?php echo $agent_id; ?>" class="btn btn-secondary mx-2"><i class="fas fa-times"></i> إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
body { background: #f5f7fa !important; }
.card { border-radius: 1.5rem !important; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
.card-header { border-radius: 1.5rem 1.5rem 0 0 !important; font-size: 1.1rem; }
.form-control, .btn { border-radius: 0.75rem !important; }
.btn-info { background-color: #17a2b8; border-color: #17a2b8; }
.btn-secondary { background-color: #6c757d; border-color: #6c757d; }
</style>