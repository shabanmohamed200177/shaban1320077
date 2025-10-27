<?php
// إظهار الأخطاء أثناء التجربة فقط
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = "localhost";
$user = "u239043057_admin1";
$pass = "V~t:k|3t";
$dbname = "u239043057_shipping";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

// جلب كل الجداول
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

$sqlScript = "";
foreach ($tables as $table) {
    // إنشاء الجدول
    $result = $conn->query("SHOW CREATE TABLE `$table`");
    $row = $result->fetch_row();
    $sqlScript .= "\n\n" . $row[1] . ";\n\n";

    // نسخ البيانات
    $result = $conn->query("SELECT * FROM `$table`");
    $columnCount = $result->field_count;

    while ($row = $result->fetch_row()) {
        $sqlScript .= "INSERT INTO `$table` VALUES(";
        for ($j = 0; $j < $columnCount; $j++) {
            $row[$j] = isset($row[$j]) ? addslashes($row[$j]) : "";
            $row[$j] = str_replace("\n", "\\n", $row[$j]);
            $sqlScript .= '"' . $row[$j] . '"';
            if ($j < ($columnCount - 1)) {
                $sqlScript .= ',';
            }
        }
        $sqlScript .= ");\n";
    }
    $sqlScript .= "\n";
}

if (!empty($sqlScript)) {
    $backup_file_name = $dbname . '_backup_' . date('Y-m-d_H-i-s') . '.sql';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . $backup_file_name);
    echo $sqlScript;
    exit;
} else {
    echo "لم يتم العثور على بيانات لعمل نسخة احتياطية.";
}
?>