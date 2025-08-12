<?php
if (!function_exists('ensure_settlement_tables')) {
    function ensure_settlement_tables(mysqli $conn): void {
        $conn->query("CREATE TABLE IF NOT EXISTS customer_payouts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_by VARCHAR(100) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS customer_payout_details (
            id INT AUTO_INCREMENT PRIMARY KEY,
            payout_id INT NULL,
            customer_id INT NOT NULL,
            parcel_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_parcel (parcel_id),
            INDEX idx_customer (customer_id),
            CONSTRAINT fk_payout_details_parent FOREIGN KEY (payout_id) REFERENCES customer_payouts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('compute_parcel_net_due')) {
    function compute_parcel_net_due(array $parcel): float {
        $cod = (float)($parcel['cod_amount'] ?? $parcel['price'] ?? 0);
        $fees = (float)($parcel['shipping_fees'] ?? 0);
        $payer = strtolower((string)($parcel['shipping_payer'] ?? 'sender'));
        $net = $cod;
        if ($payer === 'sender') {
            $net = max(0.0, $cod - $fees);
        }
        return $net;
    }
}

if (!function_exists('get_parcel_paid_to_customer')) {
    function get_parcel_paid_to_customer(mysqli $conn, int $parcelId): float {
        $stmt = $conn->prepare('SELECT COALESCE(SUM(amount),0) AS s FROM customer_payout_details WHERE parcel_id = ?');
        $stmt->bind_param('i', $parcelId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (float)($row['s'] ?? 0);
    }
}

if (!function_exists('fetch_customer_settlement_data')) {
    function fetch_customer_settlement_data(mysqli $conn, int $customerId): array {
        ensure_settlement_tables($conn);

        // احضر الشحنات المسلمة بنجاح وغير المسددة بالكامل
        $stmt = $conn->prepare("SELECT id, reference_number, tracking_number, recipient_name, cod_amount, shipping_fees, shipping_payer, status, payment_status, date_created FROM parcels WHERE client_id_fk = ? AND status IN (4,6) AND (payment_status IS NULL OR payment_status <> 'paid') ORDER BY date_created DESC");
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $res = $stmt->get_result();
        $delivered = [];
        $partials = [];
        $totalBalance = 0.0;

        while ($p = $res->fetch_assoc()) {
            $parcelId = (int)$p['id'];
            $net = compute_parcel_net_due($p);
            $alreadyPaid = get_parcel_paid_to_customer($conn, $parcelId);
            $remaining = max(0.0, $net - $alreadyPaid);
            $row = [
                'id' => $parcelId,
                'reference_number' => $p['reference_number'] ?? '',
                'tracking_number' => $p['tracking_number'] ?? '',
                'recipient_name' => $p['recipient_name'] ?? '',
                'cod_amount' => (float)$p['cod_amount'],
                'shipping_fees' => (float)($p['shipping_fees'] ?? 0),
                'shipping_payer' => $p['shipping_payer'] ?? 'sender',
                'status' => (int)$p['status'],
                'payment_status' => $p['payment_status'] ?? 'unpaid',
                'net_due' => $net,
                'paid_to_customer' => $alreadyPaid,
                'remaining_due' => $remaining,
                'date_created' => $p['date_created']
            ];
            if ($row['remaining_due'] > 0) {
                $totalBalance += $row['remaining_due'];
            }
            if ((int)$p['status'] === 4 && ($p['payment_status'] ?? '') !== 'partial_paid') {
                $delivered[] = $row;
            } else {
                $partials[] = $row;
            }
        }
        $stmt->close();

        // إحصائيات سريعة
        $stats = [
            'delivered_count' => count($delivered),
            'partial_count' => count($partials),
            'current_balance' => $totalBalance
        ];

        return [
            'delivered' => $delivered,
            'partials' => $partials,
            'stats' => $stats
        ];
    }
}