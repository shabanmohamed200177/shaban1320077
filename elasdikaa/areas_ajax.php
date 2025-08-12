<?php
session_start();
include 'db_connect.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['login_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'غير مصرح بالدخول']);
    exit;
}

$action = $_POST['action'] ?? '';

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'get_areas':
            echo json_encode(getAreas());
            break;
        case 'get_area':
            echo json_encode(getArea($_POST['id']));
            break;
        case 'save_area':
            echo json_encode(saveArea($_POST));
            break;
        case 'update_area':
            echo json_encode(updateArea($_POST));
            break;
        case 'delete_area':
            echo json_encode(deleteArea($_POST['id']));
            break;
        case 'get_governorates':
            echo json_encode(getGovernorates());
            break;
        case 'get_governorate':
            echo json_encode(getGovernorate($_POST['id']));
            break;
        case 'save_governorate':
            echo json_encode(saveGovernorate($_POST));
            break;
        case 'update_governorate':
            echo json_encode(updateGovernorate($_POST));
            break;
        case 'delete_governorate':
            echo json_encode(deleteGovernorate($_POST['id']));
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'عملية غير صحيحة']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// وظائف إدارة المناطق
function getAreas() {
    global $conn;
    try {
        // إنشاء الجدول إذا لم يكن موجوداً
        $create_table_sql = "CREATE TABLE IF NOT EXISTS areas (
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            governorate_id INT(11) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_governorate (governorate_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $conn->query($create_table_sql);
        
        $sql = "SELECT a.*, g.name as governorate_name 
                FROM areas a 
                LEFT JOIN governorates g ON a.governorate_id = g.id 
                ORDER BY a.name ASC";
        $result = $conn->query($sql);
        
        $areas = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $areas[] = $row;
            }
        }
        
        return ['status' => 'success', 'areas' => $areas];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

function getArea($id) {
    global $conn;
    try {
        $sql = "SELECT * FROM areas WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return ['status' => 'success', 'area' => $row];
        } else {
            return ['status' => 'error', 'message' => 'المنطقة غير موجودة'];
        }
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

function saveArea($data) {
    global $conn;
    try {
        // التحقق من عدم وجود منطقة بنفس الاسم
        $check_sql = "SELECT id FROM areas WHERE name = ? AND governorate_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $data['name'], $data['governorate_id']);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            return ['status' => 'error', 'message' => 'يوجد منطقة بنفس الاسم في هذه المحافظة'];
        }
        
        $sql = "INSERT INTO areas (name, governorate_id, description, date_created) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sis", $data['name'], $data['governorate_id'], $data['description']);
        
        if ($stmt->execute()) {
            return ['status' => 'success', 'message' => 'تم إضافة المنطقة بنجاح'];
        } else {
            return ['status' => 'error', 'message' => 'فشل في إضافة المنطقة'];
        }
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

function updateArea($data) {
    global $conn;
    try {
        // التحقق من عدم وجود منطقة أخرى بنفس الاسم
        $check_sql = "SELECT id FROM areas WHERE name = ? AND governorate_id = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("sii", $data['name'], $data['governorate_id'], $data['id']);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            return ['status' => 'error', 'message' => 'يوجد منطقة بنفس الاسم في هذه المحافظة'];
        }
        
        $sql = "UPDATE areas SET name = ?, governorate_id = ?, description = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisi", $data['name'], $data['governorate_id'], $data['description'], $data['id']);
        
        if ($stmt->execute()) {
            return ['status' => 'success', 'message' => 'تم تحديث المنطقة بنجاح'];
        } else {
            return ['status' => 'error', 'message' => 'فشل في تحديث المنطقة'];
        }
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

function deleteArea($id) {
    global $conn;
    try {
        $sql = "DELETE FROM areas WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            return ['status' => 'success', 'message' => 'تم حذف المنطقة بنجاح'];
        } else {
            return ['status' => 'error', 'message' => 'فشل في حذف المنطقة'];
        }
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

// وظائف إدارة المحافظات
function getGovernorates() {
    global $conn;
    try {
        // إنشاء الجدول إذا لم يكن موجوداً
        $create_table_sql = "CREATE TABLE IF NOT EXISTS governorates (
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $conn->query($create_table_sql);
        
        $sql = "SELECT * FROM governorates ORDER BY name ASC";
        $result = $conn->query($sql);
        
        $governorates = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                // عدد المناطق في كل محافظة
                $areas_count_sql = "SELECT COUNT(*) as count FROM areas WHERE governorate_id = " . $row['id'];
                $areas_result = $conn->query($areas_count_sql);
                $areas_count = $areas_result ? $areas_result->fetch_assoc()['count'] : 0;
                $row['areas_count'] = $areas_count;
                $governorates[] = $row;
            }
        }
        
        return ['status' => 'success', 'governorates' => $governorates];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

function getGovernorate($id) {
    global $conn;
    try {
        $sql = "SELECT * FROM governorates WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return ['status' => 'success', 'governorate' => $row];
        } else {
            return ['status' => 'error', 'message' => 'المحافظة غير موجودة'];
        }
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

function saveGovernorate($data) {
    global $conn;
    try {
        // التحقق من عدم وجود محافظة بنفس الاسم
        $check_sql = "SELECT id FROM governorates WHERE name = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $data['name']);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            return ['status' => 'error', 'message' => 'يوجد محافظة بنفس الاسم'];
        }
        
        $sql = "INSERT INTO governorates (name, description, date_created) VALUES (?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $data['name'], $data['description']);
        
        if ($stmt->execute()) {
            return ['status' => 'success', 'message' => 'تم إضافة المحافظة بنجاح'];
        } else {
            return ['status' => 'error', 'message' => 'فشل في إضافة المحافظة'];
        }
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

function updateGovernorate($data) {
    global $conn;
    try {
        // التحقق من عدم وجود محافظة أخرى بنفس الاسم
        $check_sql = "SELECT id FROM governorates WHERE name = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $data['name'], $data['id']);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            return ['status' => 'error', 'message' => 'يوجد محافظة بنفس الاسم'];
        }
        
        $sql = "UPDATE governorates SET name = ?, description = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $data['name'], $data['description'], $data['id']);
        
        if ($stmt->execute()) {
            return ['status' => 'success', 'message' => 'تم تحديث المحافظة بنجاح'];
        } else {
            return ['status' => 'error', 'message' => 'فشل في تحديث المحافظة'];
        }
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

function deleteGovernorate($id) {
    global $conn;
    try {
        // التحقق من عدم وجود مناطق في هذه المحافظة
        $check_sql = "SELECT id FROM areas WHERE governorate_id = ? LIMIT 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            return ['status' => 'error', 'message' => 'لا يمكن حذف المحافظة لأنها تحتوي على مناطق'];
        }
        
        $sql = "DELETE FROM governorates WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            return ['status' => 'success', 'message' => 'تم حذف المحافظة بنجاح'];
        } else {
            return ['status' => 'error', 'message' => 'فشل في حذف المحافظة'];
        }
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}
?>