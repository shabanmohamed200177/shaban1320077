<?php
// 🔍 فحص العلاقات والمفاتيح الخارجية في قاعدة البيانات
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'db_connect.php';

echo "<!DOCTYPE html>";
echo "<html lang='ar' dir='rtl'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>فحص العلاقات في قاعدة البيانات</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "</head>";
echo "<body class='container py-4'>";

echo "<h1 class='text-center mb-4'>🔍 فحص العلاقات والمفاتيح الخارجية</h1>";

try {
    // فحص جميع المفاتيح الخارجية
    echo "<div class='card mb-4'>";
    echo "<div class='card-header'><h5>🔗 المفاتيح الخارجية الموجودة</h5></div>";
    echo "<div class='card-body'>";
    
    $foreign_keys = $conn->query("
        SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE REFERENCED_TABLE_SCHEMA = 'shipping' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY TABLE_NAME, COLUMN_NAME
    ");
    
    if ($foreign_keys && $foreign_keys->num_rows > 0) {
        echo "<table class='table table-striped'>";
        echo "<tr><th>الجدول</th><th>العمود</th><th>اسم القيد</th><th>الجدول المرجعي</th><th>العمود المرجعي</th></tr>";
        
        while ($fk = $foreign_keys->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$fk['TABLE_NAME']}</td>";
            echo "<td>{$fk['COLUMN_NAME']}</td>";
            echo "<td>{$fk['CONSTRAINT_NAME']}</td>";
            echo "<td>{$fk['REFERENCED_TABLE_NAME']}</td>";
            echo "<td>{$fk['REFERENCED_COLUMN_NAME']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='text-info'>لا توجد مفاتيح خارجية موجودة</p>";
    }
    
    echo "</div>";
    echo "</div>";
    
    // فحص الشحنة المشكلة
    echo "<div class='card mb-4'>";
    echo "<div class='card-header'><h5>🎯 فحص الشحنة المشكلة</h5></div>";
    echo "<div class='card-body'>";
    
    $problem_parcel = $conn->query("
        SELECT id, tracking_number FROM parcels 
        WHERE tracking_number LIKE '%6896AF1102F86%' 
        LIMIT 1
    ");
    
    if ($problem_parcel && $problem_parcel->num_rows > 0) {
        $parcel = $problem_parcel->fetch_assoc();
        $parcel_id = $parcel['id'];
        $tracking = $parcel['tracking_number'];
        
        echo "<div class='alert alert-info'>";
        echo "<strong>الشحنة:</strong> $tracking (ID: $parcel_id)";
        echo "</div>";
        
        // فحص البيانات المرتبطة
        $related_tables = [
            'parcels_notes' => 'parcel_id',
            'parcel_tracks' => 'parcel_id',
            'parcel_collections' => 'parcel_id',
            'customer_payments' => 'parcel_id',
            'free_notification_logs' => 'parcel_id'
        ];
        
        echo "<table class='table table-bordered'>";
        echo "<tr><th>الجدول</th><th>العدد</th><th>الإجراء</th></tr>";
        
        foreach ($related_tables as $table => $column) {
            $count_query = $conn->prepare("SELECT COUNT(*) as count FROM $table WHERE $column = ?");
            $count_query->bind_param("i", $parcel_id);
            $count_query->execute();
            $count_result = $count_query->get_result();
            $count = $count_result->fetch_assoc()['count'];
            
            echo "<tr>";
            echo "<td>$table</td>";
            echo "<td><span class='badge bg-" . ($count > 0 ? 'warning' : 'success') . "'>$count</span></td>";
            echo "<td>";
            if ($count > 0) {
                echo "<button class='btn btn-sm btn-danger' onclick=\"deleteFromTable('$table', '$column', $parcel_id)\">حذف $count سجل</button>";
            } else {
                echo "<span class='text-success'>لا يوجد</span>";
            }
            echo "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        echo "<div class='mt-3'>";
        echo "<button class='btn btn-danger btn-lg' onclick=\"deleteParcelCompletely($parcel_id)\">🗑️ حذف الشحنة كاملة مع البيانات المرتبطة</button>";
        echo "</div>";
        
    } else {
        echo "<p class='text-warning'>لم يتم العثور على الشحنة المشكلة</p>";
    }
    
    echo "</div>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>خطأ: " . $e->getMessage() . "</div>";
}

echo "<div id='result'></div>";

echo "<script src='https://code.jquery.com/jquery-3.6.0.min.js'></script>";
echo "<script>";
echo "
function deleteFromTable(table, column, parcelId) {
    if (!confirm('هل تريد حذف السجلات من جدول ' + table + '؟')) return;
    
    $('#result').html('<div class=\"alert alert-info\">🔄 جاري الحذف...</div>');
    
    $.post('check_foreign_keys.php', {
        action: 'delete_from_table',
        table: table,
        column: column,
        parcel_id: parcelId
    }, function(response) {
        $('#result').html('<div class=\"alert alert-success\">✅ ' + response + '</div>');
        location.reload();
    }).fail(function() {
        $('#result').html('<div class=\"alert alert-danger\">❌ فشل في الحذف</div>');
    });
}

function deleteParcelCompletely(parcelId) {
    if (!confirm('هل أنت متأكد من حذف الشحنة وجميع البيانات المرتبطة بها؟\\nهذا الإجراء لا يمكن التراجع عنه!')) return;
    
    $('#result').html('<div class=\"alert alert-info\">🔄 جاري حذف الشحنة كاملة...</div>');
    
    $.post('fix_delete_parcel.php', {
        action: 'safe_delete_parcel',
        id: parcelId
    }, function(response) {
        if (response.status === 'success') {
            $('#result').html('<div class=\"alert alert-success\">✅ ' + response.message + '</div>');
            setTimeout(function() {
                location.reload();
            }, 2000);
        } else {
            $('#result').html('<div class=\"alert alert-danger\">❌ ' + response.message + '</div>');
        }
    }, 'json').fail(function() {
        $('#result').html('<div class=\"alert alert-danger\">❌ فشل في الحذف</div>');
    });
}
";
echo "</script>";

// معالجة طلبات AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_from_table') {
        $table = $_POST['table'];
        $column = $_POST['column'];
        $parcel_id = intval($_POST['parcel_id']);
        
        // التأكد من أن اسم الجدول آمن
        $allowed_tables = ['parcels_notes', 'parcel_tracks', 'parcel_collections', 'customer_payments', 'free_notification_logs'];
        if (!in_array($table, $allowed_tables)) {
            echo "جدول غير مسموح";
            exit;
        }
        
        $stmt = $conn->prepare("DELETE FROM $table WHERE $column = ?");
        $stmt->bind_param("i", $parcel_id);
        $stmt->execute();
        
        echo "تم حذف " . $stmt->affected_rows . " سجل من جدول $table";
        exit;
    }
}

echo "</body>";
echo "</html>";

$conn->close();
?>
