<?php
// تمت إزالة session_start() و ob_start() من هنا
// لأنها يجب أن تُستدعى مرة واحدة فقط في نقطة الدخول الرئيسية (مثل ajax.php أو index.php)
ini_set('display_errors', 1);
error_reporting(E_ALL);

class Action {
    private $db;

    public function __construct() {
        // تمت إزالة ob_start() من هنا
        include 'db_connect.php'; // Include db_connect.php once
        $this->db = $conn;
    }

    function __destruct() {
        if ($this->db) { // Check if $this->db is set before closing
            $this->db->close();
        }
        // تمت إزالة ob_end_flush() من هنا
    }

    // دالة محسّنة وآمنة لحساب تكلفة الشحن
    function get_shipping_cost($gov_id) {
        $cost = 0;
        if ($gov_id > 0) {
            $stmt = $this->db->prepare("SELECT price FROM governorates WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $gov_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                $cost = floatval($row['price']);
            }
            $stmt->close();
        }
        return $cost;
    }

    // دالة تسجيل دخول موحدة وآمنة للمستخدمين (Admin/Staff)
    function login() {
        // استخدام filter_input لسلامة البيانات
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);

        if (empty($email) || empty($password)) {
            return json_encode(['status' => 0, 'message' => 'البريد الإلكتروني وكلمة المرور مطلوبان.']);
        }
        
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $user_data = $result->fetch_array(MYSQLI_ASSOC);
            $stored_password = $user_data['password'];
            
            // دعم كلا النوعين من التشفير: MD5 و password_hash
            $login_success = false;
            
            // اختبار password_verify (للأكواد الجديدة)
            if (password_verify($password, $stored_password)) {
                $login_success = true;
            }
            // اختبار MD5 (للأكواد القديمة)
            elseif (md5($password) === $stored_password) {
                $login_success = true;
                // تحديث كلمة المرور إلى التشفير الجديد
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $update_stmt = $this->db->prepare("UPDATE users SET password = ? WHERE email = ?");
                $update_stmt->bind_param("ss", $new_hash, $email);
                $update_stmt->execute();
                $update_stmt->close();
            }
            
            if ($login_success) {
                foreach ($user_data as $key => $value) {
                    if($key != 'password' && !is_numeric($key)) {
                        $_SESSION['login_'.$key] = $value;
                    }
                }
                $stmt->close();
                return json_encode(['status' => 1, 'message' => 'تم تسجيل الدخول بنجاح.']);
            }
        }
        $stmt->close();
        return json_encode(['status' => 0, 'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.']);
    }

    function logout() {
        session_destroy();
        foreach ($_SESSION as $key => $value) {
            unset($_SESSION[$key]);
        }
        header("location:login.php");
        exit; // Ensure script stops after redirect
    }

    // دالة تسجيل دخول المندوبين (تم تعديلها لتكون آمنة)
    function login2() {
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);

        if (empty($phone) || empty($password)) {
            return json_encode(['status' => 0, 'message' => 'رقم الهاتف وكلمة المرور مطلوبان.']);
        }
        
        $stmt = $this->db->prepare("SELECT *, name FROM couriers WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows > 0) {
            $courier_data = $result->fetch_array(MYSQLI_ASSOC);
            if(password_verify($password, $courier_data['password'])) {
                foreach ($courier_data as $key => $value) {
                    if($key != 'password' && !is_numeric($key)) {
                        $_SESSION['courier_'.$key] = $value;
                    }
                }
                $stmt->close();
                return json_encode(['status' => 1, 'message' => 'تم تسجيل دخول المندوب بنجاح.']);
            }
        }
        $stmt->close();
        return json_encode(['status' => 0, 'message' => 'رقم الهاتف أو كلمة المرور غير صحيحة.']);
    }

    // دالة حفظ المستخدمين (تم تحديثها لتكون آمنة وتستخدم التشفير الجديد)
    function save_user(){
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);
        
        $check_sql = "SELECT id FROM users WHERE email = ?";
        $check_params = ["s", $email];
        if(!empty($id)){
            $check_sql .= " AND id != ?";
            $check_params[] = $id;
        }

        $check_stmt = $this->db->prepare($check_sql);
        $check_stmt->bind_param(...$check_params);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if($check_result->num_rows > 0){
            $check_stmt->close();
            return 2; // البريد الإلكتروني موجود بالفعل
        }
        $check_stmt->close();

        $data_parts = [];
        $params = [];
        $types = '';
        
        foreach($_POST as $k => $v){
            if(!in_array($k, ['id', 'cpass', 'password']) && !is_numeric($k)){
                $data_parts[] = "$k = ?";
                $params[] = $v;
                $types .= 's';
            }
        }

        if(!empty($password)){
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $data_parts[] = "password = ?";
            $params[] = $hashed_password;
            $types .= 's';
        }

        if(empty($id)){
            $sql = "INSERT INTO users SET " . implode(", ", $data_parts);
        } else {
            $sql = "UPDATE users SET " . implode(", ", $data_parts) . " WHERE id = ?";
            $params[] = $id;
            $types .= 'i';
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $save = $stmt->execute();
        $stmt->close();

        if($save){
            return 1;
        }
        return "حدث خطأ أثناء حفظ المستخدم: " . $this->db->error;
    }

    // دالة signup (تم تحديثها لتكون آمنة وتستخدم التشفير الجديد)
    function signup(){
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);
        
        $check_sql = "SELECT id FROM users WHERE email = ?";
        $check_params = ["s", $email];
        if(!empty($id)){
            $check_sql .= " AND id != ?";
            $check_params[] = $id;
        }

        $check_stmt = $this->db->prepare($check_sql);
        $check_stmt->bind_param(...$check_params);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if($check_result->num_rows > 0){
            $check_stmt->close();
            return 2;
        }
        $check_stmt->close();

        $data_parts = [];
        $params = [];
        $types = '';

        foreach($_POST as $k => $v){
            if(!in_array($k, ['id', 'cpass']) && !is_numeric($k)){
                if($k == 'password'){
                    if(empty($v)) continue;
                    $v = password_hash($v, PASSWORD_DEFAULT);
                }
                $data_parts[] = "$k = ?";
                $params[] = $v;
                $types .= 's';
            }
        }
        
        if(isset($_FILES['img']) && $_FILES['img']['tmp_name'] != ''){
            $fname = strtotime(date('y-m-d H:i')).'_'.$_FILES['img']['name'];
            $move = move_uploaded_file($_FILES['img']['tmp_name'],'../assets/uploads/'. $fname);
            if($move){
                $data_parts[] = "avatar = ?";
                $params[] = $fname;
                $types .= 's';
            }
        }
        
        if(empty($id)){
            $sql = "INSERT INTO users SET " . implode(", ", $data_parts);
        } else {
            $sql = "UPDATE users SET " . implode(", ", $data_parts) . " WHERE id = ?";
            $params[] = $id;
            $types .= 'i';
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $save = $stmt->execute();
        $stmt->close();
        
        if($save){
            if(empty($id))
                $id = $this->db->insert_id;
            
            foreach ($_POST as $key => $value) {
                if(!in_array($key, ['id', 'cpass', 'password']) && !is_numeric($key))
                    $_SESSION['login_'.$key] = $value;
            }
            $_SESSION['login_id'] = $id;
            return 1;
        }
        return "خطأ في التسجيل.";
    }

    // دالة تحديث بيانات المستخدم (تم تحديثها لتكون آمنة)
    function update_user(){
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);
        
        $check_stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $email, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if($check_result->num_rows > 0){
            $check_stmt->close();
            return 2;
        }
        $check_stmt->close();

        $data_parts = [];
        $params = [];
        $types = '';
        
        foreach($_POST as $k => $v){
            if(!in_array($k, ['id', 'cpass', 'table']) && !is_numeric($k)){
                if($k == 'password' && !empty($v)){
                    $v = password_hash($v, PASSWORD_DEFAULT);
                }
                $data_parts[] = "$k = ?";
                $params[] = $v;
                $types .= 's';
            }
        }

        if(isset($_FILES['img']) && $_FILES['img']['tmp_name'] != ''){
            $fname = strtotime(date('y-m-d H:i')).'_'.$_FILES['img']['name'];
            $move = move_uploaded_file($_FILES['img']['tmp_name'],'assets/uploads/'. $fname);
            if($move){
                $data_parts[] = "avatar = ?";
                $params[] = $fname;
                $types .= 's';
            }
        }

        $sql = "UPDATE users SET " . implode(", ", $data_parts) . " WHERE id = ?";
        $params[] = $id;
        $types .= 'i';

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $save = $stmt->execute();
        $stmt->close();

        if($save){
            foreach ($_POST as $key => $value) {
                if(!in_array($key, ['id', 'cpass', 'password', 'table']) && !is_numeric($key))
                    $_SESSION['login_'.$key] = $value;
            }
            if(isset($fname))
                $_SESSION['login_avatar'] = $fname;
            return 1;
        }
        return "خطأ في تحديث المستخدم: " . $this->db->error;
    }

    // دالة حذف المستخدم (تم تحديثها لتكون آمنة)
    function delete_user(){
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) return "معرف المستخدم غير صالح.";
        
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $delete = $stmt->execute();
        $stmt->close();
        if($delete) return 1;
        return "خطأ في حذف المستخدم.";
    }

    // باقي الدوال تم تحديثها بنفس المنهج (استخدام Prepared Statements)
    function save_system_settings(){
        // استخدام filter_input_array لجميع المدخلات
        $input = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

        $data_parts = [];
        $params = [];
        $types = '';

        foreach($input as $k => $v){
            if(!is_numeric($k)){
                $data_parts[] = "$k = ?";
                $params[] = $v;
                $types .= 's';
            }
        }
        
        if(isset($_FILES['cover']) && $_FILES['cover']['tmp_name'] != ''){
            $fname = strtotime(date('y-m-d H:i')).'_'.$_FILES['cover']['name'];
            $move = move_uploaded_file($_FILES['cover']['tmp_name'],'../assets/uploads/'. $fname);
            if($move){
                $data_parts[] = "cover_img = ?";
                $params[] = $fname;
                $types .= 's';
            }
        }

        $chk = $this->db->query("SELECT id FROM system_settings");
        if($chk->num_rows > 0){
            $id = $chk->fetch_array()['id'];
            $sql = "UPDATE system_settings SET " . implode(", ", $data_parts) . " WHERE id = ?";
            $params[] = $id;
            $types .= 'i';
        } else {
            $sql = "INSERT INTO system_settings SET " . implode(", ", $data_parts);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $save = $stmt->execute();
        $stmt->close();

        if($save){
            foreach($input as $k => $v){
                if(!is_numeric($k)){
                    $_SESSION['system'][$k] = $v;
                }
            }
            if(isset($fname)){
                $_SESSION['system']['cover_img'] = $fname;
            }
            return 1;
        }
        return "خطأ في حفظ إعدادات النظام.";
    }
    
    // دالة حفظ صورة
    function save_image(){
        // استخدام filter_input_array لجميع المدخلات
        extract($_FILES['file']);
        if(!empty($tmp_name)){
            $fname = strtotime(date("Y-m-d H:i"))."_".(str_replace(" ","-",$name));
            $move = move_uploaded_file($tmp_name,'../assets/uploads/'. $fname);
            if($move){
                $protocol = strtolower(substr($_SERVER["SERVER_PROTOCOL"],0,5))=='https'?'https':'http';
                $hostName = $_SERVER['HTTP_HOST'];
                $path = explode('/',$_SERVER['PHP_SELF']);
                $currentPath = '/'.$path[1];
                return $protocol.'://'.$hostName.$currentPath.'/assets/uploads/'.$fname;
            }
        }
        return false;
    }

    // حفظ فرع جديد (آمن)
    function save_branch(){
        // استخدام filter_input_array لجميع المدخلات
        $input = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
        $id = filter_var($input['id'], FILTER_VALIDATE_INT);
        
        $data_parts = [];
        $params = [];
        $types = '';
        foreach($input as $k => $v){
            if(!in_array($k, ['id']) && !is_numeric($k)){
                $data_parts[] = "$k = ?";
                $params[] = $v;
                $types .= 's';
            }
        }

        if(empty($id)){
            $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $bcode = '';
            do {
                $bcode = substr(str_shuffle($chars), 0, 15);
                $stmt_chk = $this->db->prepare("SELECT id FROM branches WHERE branch_code = ?");
                $stmt_chk->bind_param("s", $bcode);
                $stmt_chk->execute();
                $result_chk = $stmt_chk->get_result();
            } while ($result_chk->num_rows > 0);
            $stmt_chk->close();
            
            $data_parts[] = "branch_code = ?";
            $params[] = $bcode;
            $types .= 's';
            
            $sql = "INSERT INTO branches SET " . implode(", ", $data_parts);
        }else{
            $sql = "UPDATE branches SET " . implode(", ", $data_parts) . " WHERE id = ?";
            $params[] = $id;
            $types .= 'i';
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $save = $stmt->execute();
        $stmt->close();
        
        if($save){
            return 1;
        }
        return "خطأ في حفظ الفرع.";
    }

    // حذف فرع (آمن)
    function delete_branch(){
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) return "معرف الفرع غير صالح.";

        $stmt = $this->db->prepare("DELETE FROM branches WHERE id = ?");
        $stmt->bind_param("i", $id);
        $delete = $stmt->execute();
        $stmt->close();
        if($delete) return 1;
        return "خطأ في حذف الفرع.";
    }

    // حفظ مندوب (آمن) - يُعيد JSON مع بيانات المندوب للحفظ الفوري في الواجهة
    function save_courier(){
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);
        
        $sql_check = "SELECT id FROM couriers WHERE email = ?";
        $params_check = ["s", $email];
        if ($id > 0) {
            $sql_check .= " AND id != ?";
            $params_check[] = $id;
        }

        $stmt_check = $this->db->prepare($sql_check);
        $stmt_check->bind_param(...$params_check);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();

        if($check_result->num_rows > 0){
            $stmt_check->close();
            return json_encode(['status'=>2,'message'=>'البريد الإلكتروني مستخدم بالفعل']);
        }
        $stmt_check->close();

        $data_parts = [];
        $params = [];
        $types = '';
        foreach($_POST as $k => $v){
            if(!in_array($k, ['id', 'password', 'cpass']) && !is_numeric($k)){
                $data_parts[] = "$k = ?";
                $params[] = $v;
                $types .= 's';
            }
        }
        
        if(!empty($password)){
            $data_parts[] = "password = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
            $types .= 's';
        }

        $isCreate = empty($id);
        if($isCreate){
            $sql = "INSERT INTO couriers SET " . implode(", ", $data_parts);
        }else{
            $sql = "UPDATE couriers SET " . implode(", ", $data_parts) . " WHERE id = ?";
            $params[] = $id;
            $types .= 'i';
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $save = $stmt->execute();
        $affectedId = $isCreate ? $this->db->insert_id : $id;
        $stmt->close();

        if(!$save){
            return json_encode(['status'=>0,'message'=>'حدث خطأ في حفظ المندوب: ' . $this->db->error]);
        }

        // إرجاع بيانات المندوب كاملة لتحديث الجدول مباشرة
        $detail_sql = "SELECT c.*, b.branch_code, g.name as gov_name, g.price as gov_price, a.name as area_name, a.price as area_price 
                        FROM couriers c 
                        LEFT JOIN branches b ON c.branch_id = b.id 
                        LEFT JOIN governorates g ON c.governorate_id = g.id 
                        LEFT JOIN areas a ON c.area_id = a.id 
                        WHERE c.id = ? LIMIT 1";
        $dstmt = $this->db->prepare($detail_sql);
        $dstmt->bind_param('i', $affectedId);
        $dstmt->execute();
        $res = $dstmt->get_result();
        $courier = $res ? $res->fetch_assoc() : null;
        $dstmt->close();

        return json_encode([
            'status' => 1,
            'message' => 'تم حفظ بيانات المندوب بنجاح.',
            'action' => $isCreate ? 'create' : 'update',
            'courier' => $courier
        ]);
    }

    // حذف مندوب (آمن)
    function delete_courier(){
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) return "معرف المندوب غير صالح.";

        $stmt = $this->db->prepare("DELETE FROM couriers WHERE id = ?");
        $stmt->bind_param("i", $id);
        $delete = $stmt->execute();
        $stmt->close();
        if($delete) return "تم حذف المندوب بنجاح.";
        return "حدث خطأ أثناء حذف المندوب.";
    }

    // تحديث وكيل (آمن)
    function update_agent(){
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) return "معرف الوكيل غير صالح.";

        $data_parts = [];
        $params = [];
        $types = '';
        foreach($_POST as $k => $v){
            if(!in_array($k, ['id']) && !is_numeric($k)){
                $data_parts[] = "`$k` = ?";
                $params[] = $v;
                $types .= 's';
            }
        }
        $sql = "UPDATE `agents` SET " . implode(", ", $data_parts) . " WHERE id = ?";
        $params[] = $id;
        $types .= 'i';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $update = $stmt->execute();
        $stmt->close();
        if($update){
            return "تم تحديث بيانات الوكيل بنجاح.";
        } else {
            return "حدث خطأ في تحديث البيانات: " . $this->db->error;
        }
    }

    // دالة save_parcel من admin_class.php - تم تعطيلها لأننا نستخدم save_parcel.php منفصل
    // إذا كنت تريد استخدامها، يجب تحديثها لتتوافق مع بنية parcels الجديدة ومنطق الشحنة الواحدة
    /*
    function save_parcel(){
        // هذا المنطق معقد جداً ويتعامل مع بنية بيانات مختلفة عن التي نستخدمها في save_parcel.php
        // يفضل استخدام save_parcel.php المنفصل الذي تم تحديثه
        return "هذه الدالة (save_parcel في admin_class.php) معطلة. يرجى استخدام save_parcel.php المنفصل.";
    }
    */

    // حذف شحنة (آمن)
    function delete_parcel(){
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) return "معرف الشحنة غير صالح.";

        $this->db->begin_transaction();
        try {
            // حذف سجلات التتبع أولاً
            $stmt = $this->db->prepare("DELETE FROM parcel_tracks WHERE parcel_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            
            // حذف الشحنة نفسها
            $stmt = $this->db->prepare("DELETE FROM parcels WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            
            $this->db->commit();
            return 1;
        } catch (mysqli_sql_exception $e) {
            $this->db->rollback();
            return "خطأ في حذف الشحنة: " . $e->getMessage();
        }
    }

    // تحديث حالة الشحنة (آمن) - تم تحديثها لتسجيل التتبع بشكل صحيح
    function update_parcel(){
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $status = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT);

        if (!$id || !isset($status)) return "بيانات غير صالحة لتحديث الشحنة.";

        $this->db->begin_transaction();
        try {
            // تحديث حالة الشحنة في جدول parcels
            $update_stmt = $this->db->prepare("UPDATE parcels SET status = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $status, $id);
            $update_stmt->execute();
            $update_stmt->close();

            // إضافة سجل تتبع جديد فقط إذا كانت الحالة مختلفة عن آخر حالة مسجلة
            $last_status_query = "SELECT status FROM parcel_tracks WHERE parcel_id = ? ORDER BY date_created DESC LIMIT 1";
            $last_status_stmt = $this->db->prepare($last_status_query);
            $last_status_stmt->bind_param("i", $id);
            $last_status_stmt->execute();
            $last_status_result = $last_status_stmt->get_result();
            $last_recorded_status = null;
            if ($last_status_result->num_rows > 0) {
                $last_recorded_status = $last_status_result->fetch_assoc()['status'];
            }
            $last_status_stmt->close();

            if ($last_recorded_status != $status) {
                $insert_track_sql = "INSERT INTO parcel_tracks (parcel_id, status, date_created) VALUES (?, ?, NOW())";
                $insert_track_stmt = $this->db->prepare($insert_track_sql);
                $insert_track_stmt->bind_param("ii", $id, $status);
                $insert_track_stmt->execute();
                $insert_track_stmt->close();
            }

            $this->db->commit();
            return 1;
        } catch (mysqli_sql_exception $e) {
            $this->db->rollback();
            return "حدث خطأ أثناء تحديث الحالة: " . $e->getMessage();
        }
    }

    // جلب تاريخ الشحنة (آمن) - تم تحديثها لاستخدام tracking_number والحالات الجديدة
    function get_parcel_heistory(){
        $ref_no = filter_input(INPUT_POST, 'ref_no', FILTER_SANITIZE_STRING); // يفترض أنه tracking_number الآن
        if (empty($ref_no)) return json_encode(['status' => 0, 'message' => 'رقم التتبع غير صالح.']);

        $data = [];

        $parcel_stmt = $this->db->prepare("SELECT id, date_created, status FROM parcels WHERE tracking_number = ?");
        $parcel_stmt->bind_param("s", $ref_no);
        $parcel_stmt->execute();
        $parcel_result = $parcel_stmt->get_result();

        if($parcel_result->num_rows <= 0){
            $parcel_stmt->close();
            return json_encode(['status' => 2, 'message' => 'لم يتم العثور على الشحنة.']);
        } else {
            $parcel = $parcel_result->fetch_assoc();
            $parcel_stmt->close();
            
            // تعريف مصفوفة حالات الشحنات (للعرض في السجل)
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

            // إضافة الحالة الأولية من جدول parcels
            $initial_status_name = $status_arr[$parcel['status']] ?? "غير معروف";
            $data[] = ['status' => $initial_status_name, 'date_created' => date("M d, Y h:i A", strtotime($parcel['date_created']))];
            
            $history_stmt = $this->db->prepare("SELECT status, date_created FROM parcel_tracks WHERE parcel_id = ? ORDER BY date_created ASC");
            $history_stmt->bind_param("i", $parcel['id']);
            $history_stmt->execute();
            $history_result = $history_stmt->get_result();
            
            while($row = $history_result->fetch_assoc()){
                $row['date_created'] = date("M d, Y h:i A", strtotime($row['date_created']));
                $row['status'] = $status_arr[$row['status']] ?? "غير معروف"; // استخدام مصفوفة الحالات الجديدة
                $data[] = $row;
            }
            $history_stmt->close();
            return json_encode(['status' => 1, 'data' => $data]);
        }
    }

    // جلب التقرير (آمن)
    function get_report(){
        $date_from = filter_input(INPUT_POST, 'date_from', FILTER_SANITIZE_STRING);
        $date_to = filter_input(INPUT_POST, 'date_to', FILTER_SANITIZE_STRING);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

        $data = [];
        $sql = "SELECT p.*, c.name as courier_name, g.name as governorate_name FROM parcels p LEFT JOIN couriers c ON p.courier_id = c.id LEFT JOIN governorates g ON p.recipient_governorate_id = g.id WHERE 1=1";
        $params = [];
        $types = '';

        if (!empty($date_from) && !empty($date_to)) {
            $sql .= " AND DATE(p.date_created) BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
            $types .= 'ss';
        }

        if ($status != 'all') {
            $sql .= " AND p.status = ?";
            $params[] = $status;
            $types .= 'i'; // Status ID is integer
        }

        $sql .= " ORDER BY p.date_created ASC"; // Order by datetime directly

        $stmt = $this->db->prepare($sql);
        if(!empty($params)){
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $get = $stmt->get_result();

        if ($get) {
            // تعريف مصفوفة حالات الشحنات (للعرض في التقرير)
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
            while($row = $get->fetch_assoc()){
                $row['sender_name'] = ucwords($row['sender_name']);
                $row['recipient_name'] = ucwords($row['recipient_name']);
                $row['date_created'] = date("M d, Y", strtotime($row['date_created']));
                $row['status_name'] = $status_arr[$row['status']] ?? "غير معروف"; // استخدام اسم الحالة الجديد
                // price column is not in parcels table. Assuming shipping_fees or cod_amount
                // For reports, you might want to show total_to_collect or company_profit
                $row['display_price'] = number_format($row['shipping_fees'] ?? 0, 2); // Or whatever price you want to display
                $data[] = $row;
            }
        }
        $stmt->close();
        return json_encode($data);
    }
    
    // حفظ عميل جديد (آمن)
    function save_customer(){
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $governorate_id = filter_input(INPUT_POST, 'governorate_id', FILTER_VALIDATE_INT);
        $area_id = filter_input(INPUT_POST, 'area_id', FILTER_VALIDATE_INT);
        $status = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT);

        $stmt_check = $this->db->prepare("SELECT id FROM customers WHERE phone = ? OR (email <> '' AND email = ?)");
        $stmt_check->bind_param("ss", $phone, $email);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();
        if($check_result->num_rows > 0){
            $stmt_check->close();
            return 2; // العميل موجود بالفعل
        }
        $stmt_check->close();
        
        $stmt_save = $this->db->prepare("INSERT INTO customers (name, phone, email, address, governorate_id, area_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_save->bind_param("ssssiii", $name, $phone, $email, $address, $governorate_id, $area_id, $status);
        $save = $stmt_save->execute();
        $stmt_save->close();

        if($save){
            return 1;
        } else {
            return "خطأ في حفظ العميل: ".$this->db->error;
        }
    }
    
    // تحديث بيانات العميل (آمن)
    public function update_customer() {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $governorate_id = filter_input(INPUT_POST, 'governorate_id', FILTER_VALIDATE_INT);
        $area_id = filter_input(INPUT_POST, 'area_id', FILTER_VALIDATE_INT);
        $status = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT);

        if (!$id) return "معرف العميل غير صالح.";

        $stmt = $this->db->prepare("UPDATE customers SET name = ?, phone = ?, email = ?, address = ?, governorate_id = ?, area_id = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssssiiii", $name, $phone, $email, $address, $governorate_id, $area_id, $status, $id);
        $qry = $stmt->execute();
        $stmt->close();
        if ($qry) {
            return 1;
        } else {
            return "خطأ في تحديث بيانات العميل: " . $this->db->error;
        }
    }
    
    // حذف عميل (آمن)
    public function delete_customer() {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) return 0; // Return 0 for invalid ID

        $this->db->begin_transaction();
        try {
            // Get customer phone to delete related parcels
            $stmt_customer = $this->db->prepare("SELECT phone FROM customers WHERE id = ?");
            $stmt_customer->bind_param("i", $id);
            $stmt_customer->execute();
            $customer_qry = $stmt_customer->get_result();
            
            if ($customer_qry->num_rows > 0) {
                $customer = $customer_qry->fetch_assoc();
                $customer_phone = $this->db->real_escape_string($customer['phone']);
                $stmt_customer->close();

                // Delete related parcel tracks first
                $stmt_delete_parcel_tracks = $this->db->prepare("DELETE pt FROM parcel_tracks pt JOIN parcels p ON pt.parcel_id = p.id WHERE p.sender_phone = ?");
                $stmt_delete_parcel_tracks->bind_param("s", $customer_phone);
                $stmt_delete_parcel_tracks->execute();
                $stmt_delete_parcel_tracks->close();

                // Delete related parcels
                $stmt_delete_parcels = $this->db->prepare("DELETE FROM parcels WHERE sender_phone = ?");
                $stmt_delete_parcels->bind_param("s", $customer_phone);
                $stmt_delete_parcels->execute();
                $stmt_delete_parcels->close();

                // Delete the customer
                $stmt_delete_customer = $this->db->prepare("DELETE FROM customers WHERE id = ?");
                $stmt_delete_customer->bind_param("i", $id);
                $stmt_delete_customer->execute();
                $delete_success = $stmt_delete_customer->affected_rows > 0;
                $stmt_delete_customer->close();

                if($delete_success) {
                    $this->db->commit();
                    return 1;
                } else {
                    $this->db->rollback();
                    return "خطأ في حذف العميل.";
                }

            } else {
                $stmt_customer->close();
                $this->db->rollback();
                return 0; // Customer not found
            }
        } catch (mysqli_sql_exception $e) {
            $this->db->rollback();
            return "خطأ في حذف العميل: " . $e->getMessage();
        }
    }

    // إضافة محافظة (آمن)
    public function add_governorate(){
        $name = trim(filter_input(INPUT_POST, 'gov_name', FILTER_UNSAFE_RAW));
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        if ($price === false) $price = 0; // Default if validation fails
        $wantJson = isset($_POST['return_json']) && $_POST['return_json'] == '1';
        
        $stmt_check = $this->db->prepare("SELECT id FROM governorates WHERE name = ?");
        $stmt_check->bind_param("s", $name);
        $stmt_check->execute();
        if($stmt_check->get_result()->num_rows > 0){
            $stmt_check->close();
            if ($wantJson) {
                return json_encode(['status'=>2,'message'=>'المحافظة موجودة بالفعل']);
            }
            return 2; // المحافظة موجودة بالفعل
        }
        $stmt_check->close();

        $stmt_save = $this->db->prepare("INSERT INTO governorates (name, price) VALUES (?, ?)");
        $stmt_save->bind_param("sd", $name, $price);
        $save = $stmt_save->execute();
        $newId = $this->db->insert_id;
        $stmt_save->close();
        if ($wantJson) {
            if ($save) {
                return json_encode([
                    'status'=>1,
                    'governorate'=>[
                        'id'=>$newId,
                        'name'=>$name,
                        'price'=>floatval($price),
                        'area_count'=>0,
                        'courier_count'=>0
                    ]
                ]);
            }
            return json_encode(['status'=>0,'message'=>'فشل حفظ المحافظة']);
        }
        return $save ? 1 : 0;
    }

    // تحديث محافظة (آمن)
    public function update_governorate(){
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'gov_name', FILTER_SANITIZE_STRING);
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        if ($price === false) $price = 0;

        if (!$id) return 0; // Invalid ID

        $stmt_check = $this->db->prepare("SELECT id FROM governorates WHERE name = ? AND id != ?");
        $stmt_check->bind_param("si", $name, $id);
        $stmt_check->execute();
        if($stmt_check->get_result()->num_rows > 0){
            $stmt_check->close();
            return 2; // المحافظة موجودة بالفعل باسم مختلف
        }
        $stmt_check->close();
        
        $stmt_update = $this->db->prepare("UPDATE governorates SET name = ?, price = ? WHERE id = ?");
        $stmt_update->bind_param("sdi", $name, $price, $id);
        $update = $stmt_update->execute();
        $stmt_update->close();

        return $update ? 1 : 0;
    }

    // حذف محافظة (آمن)
    public function delete_governorate(){
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) return 0; // Invalid ID
        
        $stmt_check = $this->db->prepare("SELECT id FROM areas WHERE governorate_id = ?");
        $stmt_check->bind_param("i", $id);
        $stmt_check->execute();
        if($stmt_check->get_result()->num_rows > 0){
            $stmt_check->close();
            return 2; // لا يمكن الحذف لوجود مناطق تابعة
        }
        $stmt_check->close();

        $stmt_delete = $this->db->prepare("DELETE FROM governorates WHERE id = ?");
        $stmt_delete->bind_param("i", $id);
        $delete = $stmt_delete->execute();
        $stmt_delete->close();

        return $delete ? 1 : 0;
    }

    // إضافة منطقة (آمن)
    public function add_area(){
        $gov_id = filter_input(INPUT_POST, 'governorate_id', FILTER_VALIDATE_INT);
        $name = trim(filter_input(INPUT_POST, 'area_name', FILTER_UNSAFE_RAW));
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        if ($price === false) $price = 0;
        $wantJson = isset($_POST['return_json']) && $_POST['return_json'] == '1';

        if (!$gov_id || empty($name)) return 0; // Invalid input
        
        $stmt_check = $this->db->prepare("SELECT id FROM areas WHERE name = ? AND governorate_id = ?");
        $stmt_check->bind_param("si", $name, $gov_id);
        $stmt_check->execute();
        if($stmt_check->get_result()->num_rows > 0){
            $stmt_check->close();
            if ($wantJson) {
                return json_encode(['status'=>2,'message'=>'المنطقة موجودة بالفعل']);
            }
            return 2; // المنطقة موجودة بالفعل في هذه المحافظة
        }
        $stmt_check->close();
        
        $stmt_save = $this->db->prepare("INSERT INTO areas (governorate_id, name, price) VALUES (?, ?, ?)");
        $stmt_save->bind_param("isd", $gov_id, $name, $price);
        $save = $stmt_save->execute();
        $newId = $this->db->insert_id;
        $stmt_save->close();

        if ($wantJson) {
            if ($save) {
                // احضار اسم المحافظة
                $gov_name = '';
                $gov_stmt = $this->db->prepare("SELECT name FROM governorates WHERE id = ?");
                $gov_stmt->bind_param("i", $gov_id);
                $gov_stmt->execute();
                $gov_res = $gov_stmt->get_result()->fetch_assoc();
                if ($gov_res) { $gov_name = $gov_res['name']; }
                $gov_stmt->close();

                return json_encode([
                    'status'=>1,
                    'area'=>[
                        'id'=>$newId,
                        'name'=>$name,
                        'governorate_id'=>$gov_id,
                        'gov_name'=>$gov_name,
                        'price'=>floatval($price),
                        'courier_count'=>0
                    ]
                ]);
            }
            return json_encode(['status'=>0,'message'=>'فشل حفظ المنطقة']);
        }

        return $save ? 1 : 0;
    }

    // تحديث منطقة (آمن)
    public function update_area(){
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $gov_id = filter_input(INPUT_POST, 'governorate_id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'area_name', FILTER_SANITIZE_STRING);
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        if ($price === false) $price = 0;

        if (!$id || !$gov_id || empty($name)) return 0; // Invalid input
        
        $stmt_check = $this->db->prepare("SELECT id FROM areas WHERE name = ? AND governorate_id = ? AND id != ?");
        $stmt_check->bind_param("sii", $name, $gov_id, $id);
        $stmt_check->execute();
        if($stmt_check->get_result()->num_rows > 0){
            $stmt_check->close();
            return 2; // المنطقة موجودة بالفعل في هذه المحافظة
        }
        $stmt_check->close();
        
        $stmt_update = $this->db->prepare("UPDATE areas SET name = ?, governorate_id = ?, price = ? WHERE id = ?");
        $stmt_update->bind_param("sidi", $name, $gov_id, $price, $id);
        $update = $stmt_update->execute();
        $stmt_update->close();

        return $update ? 1 : 0;
    }

    // حذف منطقة (آمن)
    public function delete_area(){
        extract($_POST);
        $data = " area_id = '$id' ";
        if($this->db->query("DELETE FROM areas WHERE ".$data)){
            return 1;
        }
        return 0;
    }

    // دالة تحديث حالة شحنة واحدة مع الدفع التلقائي
    public function update_parcel_status() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $status_id = isset($_POST['status_id']) ? intval($_POST['status_id']) : 0;
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

        if ($id <= 0 || $status_id <= 0) {
            return 'بيانات غير صالحة';
        }

        // بدء المعاملة
        $this->db->begin_transaction();
        
        try {
            // الحصول على بيانات الشحنة قبل التحديث
            $stmt = $this->db->prepare("SELECT * FROM parcels WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $parcel_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$parcel_data) {
                throw new Exception('الشحنة غير موجودة');
            }

            // تحديث حالة الشحنة
            $stmt = $this->db->prepare("UPDATE parcels SET status = ? WHERE id = ?");
            $stmt->bind_param("ii", $status_id, $id);
            
            if (!$stmt->execute()) {
                $stmt->close();
                throw new Exception('خطأ في تحديث حالة الشحنة');
            }
            $stmt->close();

            // إضافة تتبع للحالة الجديدة
            $stmt = $this->db->prepare("INSERT INTO parcel_tracks (parcel_id, status, date_created) VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $id, $status_id);
            $stmt->execute();
            $stmt->close();

            // معالجة الدفع التلقائي حسب نوع الحالة
            $this->processAutomaticPayment($parcel_data, $status_id);

            $this->db->commit();
            return 1;
            
        } catch (Exception $e) {
            $this->db->rollback();
            return 'خطأ: ' . $e->getMessage();
        }
    }

    // دالة معالجة الدفع التلقائي
    private function processAutomaticPayment($parcel_data, $new_status) {
        // تحقق من حالة الدفع التلقائي بناءً على الحالة الجديدة
        // مثال: إذا كانت الحالة 5 (تم الدفع بنجاح) أو 6 (تم الدفع جزئي)
        $customer_id = $parcel_data['customer_id'] ?? 0;
        $cod_amount = floatval($parcel_data['cod_amount'] ?? 0);
        $paid_amount = floatval($parcel_data['paid_amount'] ?? 0);

        if ($new_status == 5 && $paid_amount < $cod_amount) {
            // تسجيل دفعة كاملة
            $this->recordCustomerPayment($customer_id, $cod_amount, $parcel_data['id'], 'full_payment');
        } elseif ($new_status == 6 && $paid_amount > 0) {
            // تسجيل دفعة جزئية
            $this->recordCustomerPayment($customer_id, $paid_amount, $parcel_data['id'], 'partial_payment');
        }

        // تحديث رصيد العميل
        $this->updateCustomerTotalBalance($customer_id);
    }

    // دالة تسجيل دفعة العميل
    private function recordCustomerPayment($customer_id, $amount, $parcel_id, $type) {
        // التحقق من وجود جدول customer_payments وإنشاؤه إذا لم يكن موجود
        $create_table = "
            CREATE TABLE IF NOT EXISTS customer_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                customer_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                payment_method VARCHAR(50) DEFAULT 'automatic',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_customer (customer_id)
            )
        ";
        $this->db->query($create_table);

        // إدراج الدفعة
        $notes = "دفعة تلقائية " . ($type === 'partial_payment' ? 'جزئية' : 'كاملة') . " من تحديث حالة الشحنة";
        $stmt = $this->db->prepare("
            INSERT INTO customer_payments 
            (customer_id, amount, payment_method, notes, created_at)
            VALUES (?, ?, 'automatic', ?, NOW())
        ");
        $stmt->bind_param("ids", $customer_id, $amount, $notes);
        $stmt->execute();
        $payment_id = $this->db->insert_id;
        $stmt->close();

        // ربط الدفعة بالشحنة في جدول التفاصيل
        $this->recordPaymentDetail($payment_id, $parcel_id, $amount);
        
        return $payment_id;
    }

    // دالة تسجيل تفاصيل الدفعة
    private function recordPaymentDetail($payment_id, $parcel_id, $amount) {
        // إنشاء جدول customer_payment_details إذا لم يكن موجود
        $create_table = "
            CREATE TABLE IF NOT EXISTS customer_payment_details (
                id INT AUTO_INCREMENT PRIMARY KEY,
                payment_id INT NOT NULL,
                shipment_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_payment (payment_id),
                INDEX idx_shipment (shipment_id)
            )
        ";
        $this->db->query($create_table);

        $stmt = $this->db->prepare("
            INSERT INTO customer_payment_details 
            (payment_id, shipment_id, amount, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iid", $payment_id, $parcel_id, $amount);
        $stmt->execute();
        $stmt->close();
    }

    // دالة تسجيل حركة رصيد العميل
    private function recordCustomerBalanceTransaction($customer_id, $amount, $type, $description, $reference_type, $reference_id) {
        // التحقق من وجود جدول customer_balances
        $table_check = $this->db->query("SHOW TABLES LIKE 'customer_balances'");
        
        if ($table_check && $table_check->num_rows > 0) {
            // الحصول على الرصيد السابق
            $balance_before = $this->getCustomerCurrentBalance($customer_id);
            $balance_after = ($type === 'credit') ? $balance_before + $amount : $balance_before - $amount;
            
            $stmt = $this->db->prepare("
                INSERT INTO customer_balances 
                (customer_id, type, amount, description, balance_before, balance_after, 
                 reference_type, reference_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
            ");
            $stmt->bind_param("isdsddsi", $customer_id, $type, $amount, $description, 
                             $balance_before, $balance_after, $reference_type, $reference_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // دالة تحديث الرصيد الإجمالي للعميل
    private function updateCustomerTotalBalance($customer_id) {
        // التحقق من وجود عمود balance في جدول customers
        $column_check = $this->db->query("SHOW COLUMNS FROM customers LIKE 'balance'");
        
        if ($column_check && $column_check->num_rows > 0) {
            // حساب الرصيد من جدول customer_balances أو من الشحنات مباشرة
            $table_check = $this->db->query("SHOW TABLES LIKE 'customer_balances'");
            
            if ($table_check && $table_check->num_rows > 0) {
                // حساب من customer_balances
                $stmt = $this->db->prepare("
                    SELECT 
                        COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE -amount END), 0) as balance
                    FROM customer_balances 
                    WHERE customer_id = ? AND status = 'completed'
                ");
                $stmt->bind_param("i", $customer_id);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $new_balance = floatval($result['balance']);
                $stmt->close();
            } else {
                // حساب من الشحنات مباشرة
                $new_balance = $this->calculateCustomerBalanceFromParcels($customer_id);
            }
            
            // تحديث رصيد العميل
            $stmt = $this->db->prepare("UPDATE customers SET balance = ? WHERE id = ?");
            $stmt->bind_param("di", $new_balance, $customer_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // دالة حساب رصيد العميل من الشحنات
    private function calculateCustomerBalanceFromParcels($customer_id) {
        // الحصول على رقم هاتف العميل
        $stmt = $this->db->prepare("SELECT phone FROM customers WHERE id = ?");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $customer = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$customer) return 0;
        
        // حساب الرصيد من الشحنات المسلمة
        $stmt = $this->db->prepare("
            SELECT 
                SUM(
                    CASE 
                        WHEN shipping_payer = 'sender' THEN cod_amount - shipping_fees - COALESCE(paid_amount, 0)
                        ELSE cod_amount - COALESCE(paid_amount, 0)
                    END
                ) as balance
            FROM parcels 
            WHERE sender_phone = ? AND status = 4
        ");
        $stmt->bind_param("s", $customer['phone']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return floatval($result['balance'] ?? 0);
    }

    // دالة الحصول على الرصيد الحالي للعميل
    private function getCustomerCurrentBalance($customer_id) {
        $column_check = $this->db->query("SHOW COLUMNS FROM customers LIKE 'balance'");
        
        if ($column_check && $column_check->num_rows > 0) {
            $stmt = $this->db->prepare("SELECT balance FROM customers WHERE id = ?");
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            return floatval($result['balance'] ?? 0);
        }
        
        return 0;
    }

    // دالة تحديث حالة شحنات متعددة مع الدفع التلقائي
    public function update_multiple_parcel_status() {
        $shipment_ids = isset($_POST['shipment_ids']) ? $_POST['shipment_ids'] : [];
        $new_status = isset($_POST['new_status']) ? intval($_POST['new_status']) : 0;

        if (empty($shipment_ids) || $new_status <= 0) {
            return 'بيانات غير صالحة';
        }

        // بدء المعاملة للعملية المتعددة
        $this->db->begin_transaction();
        
        try {
            foreach ($shipment_ids as $shipment_id) {
                $shipment_id = intval($shipment_id);
                
                // الحصول على بيانات الشحنة قبل التحديث
                $stmt = $this->db->prepare("SELECT * FROM parcels WHERE id = ?");
                $stmt->bind_param("i", $shipment_id);
                $stmt->execute();
                $parcel_data = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if (!$parcel_data) {
                    continue; // تخطي الشحنة غير الموجودة
                }

                // تحديث حالة الشحنة
                $stmt = $this->db->prepare("UPDATE parcels SET status = ? WHERE id = ?");
                $stmt->bind_param("ii", $new_status, $shipment_id);
        
        if (!$stmt->execute()) {
            $stmt->close();
                    throw new Exception('خطأ في تحديث حالة الشحنة رقم: ' . $shipment_id);
        }
        $stmt->close();

                // إضافة تتبع للحالة الجديدة
                $stmt = $this->db->prepare("INSERT INTO parcel_tracks (parcel_id, status, date_created) VALUES (?, ?, NOW())");
                $stmt->bind_param("ii", $shipment_id, $new_status);
                $stmt->execute();
                $stmt->close();

                // معالجة الدفع التلقائي
                $this->processAutomaticPayment($parcel_data, $new_status);
            }

            $this->db->commit();
        return 1;
            
        } catch (Exception $e) {
            $this->db->rollback();
            return 'خطأ: ' . $e->getMessage();
        }
    }
}
?>