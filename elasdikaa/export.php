<?php
include 'db_connect.php';

if (isset($_POST['action']) && $_POST['action'] == 'export_excel') {
    // استلام المعلمات
    $status = $_POST['status'];
    $date_from = $_POST['date_from'];
    $date_to = $_POST['date_to'];
    
    // بناء الاستعلام
    $where = "WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($status != 'all') {
        $where .= " AND pt.status = ?";
        $params[] = $status;
        $types .= "i";
    }
    
    if (!empty($date_from)) {
        $where .= " AND DATE(p.date_created) >= ?";
        $params[] = $date_from;
        $types .= "s";
    }
    
    if (!empty($date_to)) {
        $where .= " AND DATE(p.date_created) <= ?";
        $params[] = $date_to;
        $types .= "s";
    }
    
    // الاستعلام الرئيسي
    $query = "SELECT p.id, p.reference_number, p.sender_name, p.recipient_name, 
                     p.price, p.date_created, pt.status,
                     fb.branch_code as from_branch, tb.branch_code as to_branch,
                     c.name as courier_name
              FROM parcels p
              LEFT JOIN (
                  SELECT parcel_id, MAX(date_created) as max_date
                  FROM parcel_tracks
                  GROUP BY parcel_id
              ) latest ON p.id = latest.parcel_id
              LEFT JOIN parcel_tracks pt ON p.id = pt.parcel_id AND pt.date_created = latest.max_date
              LEFT JOIN branches fb ON p.from_branch_id = fb.id
              LEFT JOIN branches tb ON p.to_branch_id = tb.id
              LEFT JOIN couriers c ON p.courier_id = c.id
              $where
              ORDER BY p.date_created DESC";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // مصفوفة الحالات النصية
    $status_arr = array("Item Accepted by Courier","Collected","Shipped","In-Transit","Arrived At Destination","Out for Delivery","Ready to Pickup","Delivered","Picked-up","Unsuccessfull Delivery Attempt");
    
    // إنشاء ملف Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="parcels_report.xls');
    
    echo "<table border='1'>";
    echo "<tr>";
    echo "<th>#</th>";
    echo "<th>Reference Number</th>";
    echo "<th>Date</th>";
    echo "<th>Sender</th>";
    echo "<th>Recipient</th>";
    echo "<th>From Branch</th>";
    echo "<th>To Branch</th>";
    echo "<th>Courier</th>";
    echo "<th>Amount</th>";
    echo "<th>Status</th>";
    echo "</tr>";
    
    $i = 1;
    while ($row = $result->fetch_assoc()) {
        // تحويل رقم الحالة إلى نص
        $status_text = isset($row['status']) ? $status_arr[$row['status']] : 'Unknown';
        
        echo "<tr>";
        echo "<td>" . $i++ . "</td>";
        echo "<td>" . $row['reference_number'] . "</td>";
        echo "<td>" . $row['date_created'] . "</td>";
        echo "<td>" . $row['sender_name'] . "</td>";
        echo "<td>" . $row['recipient_name'] . "</td>";
        echo "<td>" . $row['from_branch'] . "</td>";
        echo "<td>" . $row['to_branch'] . "</td>";
        echo "<td>" . $row['courier_name'] . "</td>";
        echo "<td>" . $row['price'] . "</td>";
        echo "<td>" . $status_text . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    exit();
}
?>