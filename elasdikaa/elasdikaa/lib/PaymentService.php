<?php
/**
 * Unified Payment Service for partial/full payments
 */

class PaymentService {
    /**
     * Whether to also mirror inserts into legacy customer_payments for compatibility
     */
    private const ALSO_INSERT_CUSTOMER_PAYMENTS = true;

    /**
     * Compute total amount to collect from recipient at door
     */
    public static function computeTotalToCollect(float $codAmount, float $shippingFees, string $shippingPayer): float {
        if (strtolower($shippingPayer) === 'recipient') {
            return max(0.0, $codAmount + $shippingFees);
        }
        return max(0.0, $codAmount);
    }

    /**
     * Fetch parcel financial snapshot
     */
    public static function getPaymentInfo(mysqli $conn, int $parcelId): array {
        $sql = 'SELECT id, cod_amount, shipping_fees, shipping_payer, shipment_direction, delivery_agent_fee, paid_amount, total_to_collect, status, payment_status, client_id_fk FROM parcels WHERE id = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $parcelId);
        $stmt->execute();
        $parcel = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$parcel) {
            throw new RuntimeException('Parcel not found');
        }

        $cod = (float)($parcel['cod_amount'] ?? 0);
        $ship = (float)($parcel['shipping_fees'] ?? 0);
        $payer = (string)($parcel['shipping_payer'] ?? 'sender');
        $paid = (float)($parcel['paid_amount'] ?? 0);
        $totalToCollect = (float)($parcel['total_to_collect'] ?? 0);
        if ($totalToCollect <= 0) {
            $totalToCollect = self::computeTotalToCollect($cod, $ship, $payer);
        }
        $remaining = max(0.0, $totalToCollect - $paid);
        $pstatus = $paid >= $totalToCollect ? 'paid' : ($paid > 0 ? 'partial_paid' : 'unpaid');

        // حساب الخصم الأساسي والمتبقي لتعرضه الواجهة بدقة قبل الحفظ
        $shipmentDirection = (string)($parcel['shipment_direction'] ?? 'to_agent');
        $shippingPayer = (string)($parcel['shipping_payer'] ?? 'sender');
        $deliveryAgentFee = (float)($parcel['delivery_agent_fee'] ?? 0);
        $baseDeduction = 0.0;
        if ($shipmentDirection === 'from_agent') {
            $baseDeduction = $deliveryAgentFee;
        } elseif ($shipmentDirection === 'to_agent' && $payer === 'sender') {
            $baseDeduction = $ship;
        }

        $rawCollectedByCourier = 0.0;
        $hasSourceCol = false;
        $colRes = $conn->query("SHOW COLUMNS FROM parcel_collections LIKE 'source'");
        if ($colRes && $colRes->num_rows > 0) { $hasSourceCol = true; }
        if ($colRes) { $colRes->close(); }
        if ($hasSourceCol) {
            $sumStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS s FROM parcel_collections WHERE parcel_id = ? AND source = 'courier'");
        } else {
            $sumStmt = $conn->prepare('SELECT COALESCE(SUM(amount),0) AS s FROM parcel_collections WHERE parcel_id = ? AND collected_by_courier_id IS NOT NULL');
        }
        $sumStmt->bind_param('i', $parcelId);
        $sumStmt->execute();
        $rawCollectedByCourier = (float)($sumStmt->get_result()->fetch_assoc()['s'] ?? 0);
        $sumStmt->close();
        $alreadyCovered = min($baseDeduction, $rawCollectedByCourier);
        $deductionRemaining = max(0.0, $baseDeduction - $alreadyCovered);

        return [
            'parcel' => $parcel,
            'cod_amount' => $cod,
            'shipping_fees' => $ship,
            'shipping_payer' => $payer,
            'shipment_direction' => $shipmentDirection,
            'delivery_agent_fee' => $deliveryAgentFee,
            'paid_amount' => $paid,
            'total_to_collect' => $totalToCollect,
            'remaining_balance' => $remaining,
            'payment_status' => $pstatus,
            'base_deduction' => $baseDeduction,
            'raw_collected_by_courier' => $rawCollectedByCourier,
            'deduction_remaining' => $deductionRemaining,
        ];
    }

    /**
     * Apply a partial payment safely and update parcel/payment records
     * @param string $source e.g., 'courier' | 'admin'
     * @param string|null $note optional note
     * @param int|null $collectedByCourierId for parcel_collections compatibility
     * @param string|null $collectedByName for customer_payments compatibility
     */
    public static function applyPartialPayment(
        mysqli $conn,
        int $parcelId,
        float $amount,
        string $source,
        ?string $note = null,
        ?int $collectedByCourierId = null,
        ?string $collectedByName = null,
        bool $amountIsNet = false
    ): array {
        if ($parcelId <= 0 || $amount <= 0) {
            throw new InvalidArgumentException('Invalid parcel or amount');
        }

        $conn->begin_transaction();
        try {
            $info = self::getPaymentInfo($conn, $parcelId);
            $totalToCollect = (float)$info['total_to_collect'];
            $paidSoFar = (float)$info['paid_amount'];
            $remaining = max(0.0, $totalToCollect - $paidSoFar);

            if ($amount > $remaining) {
                throw new RuntimeException('المبلغ المدفوع يتجاوز المتبقي. المتبقي: ' . number_format($remaining, 2));
            }

            // احسب صافي ما يُعتبر مدفوعًا
            $shipmentDirection = (string)($info['shipment_direction'] ?? 'to_agent');
            $shippingPayer = (string)($info['shipping_payer'] ?? 'sender');
            $deliveryAgentFee = (float)($info['delivery_agent_fee'] ?? 0);
            $shippingFees = (float)($info['shipping_fees'] ?? 0);

            $baseDeduction = 0.0;
            if ($shipmentDirection === 'from_agent') {
                $baseDeduction = $deliveryAgentFee; // خصم عمولة المندوب
            } elseif ($shipmentDirection === 'to_agent' && $shippingPayer === 'sender') {
                $baseDeduction = $shippingFees; // خصم رسوم الشحن على الراسل
            }

            $deductionAppliedNow = 0.0;
            if ($amountIsNet === true) {
                // المبلغ المدخل صافي للعميل، لا نطبّق أي خصم هنا
                $effectivePaid = $amount;
            } else {
                // الخصم المتبقي الذي لم يُغطّ بعد عبر الدفعات السابقة (تحصيل المندوب فقط)
                $rawCollectedSoFar = 0.0;
                $hasSourceCol = false;
                $colRes = $conn->query("SHOW COLUMNS FROM parcel_collections LIKE 'source'");
                if ($colRes && $colRes->num_rows > 0) { $hasSourceCol = true; }
                if ($colRes) { $colRes->close(); }

                if ($hasSourceCol) {
                    $sumStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS s FROM parcel_collections WHERE parcel_id = ? AND source = 'courier'");
                    $sumStmt->bind_param('i', $parcelId);
                } else {
                    $sumStmt = $conn->prepare('SELECT COALESCE(SUM(amount),0) AS s FROM parcel_collections WHERE parcel_id = ? AND collected_by_courier_id IS NOT NULL');
                    $sumStmt->bind_param('i', $parcelId);
                }
                $sumStmt->execute();
                $sumRes = $sumStmt->get_result()->fetch_assoc();
                $sumStmt->close();
                if ($sumRes && isset($sumRes['s'])) {
                    $rawCollectedSoFar = (float)$sumRes['s'];
                }

                $alreadyCovered = min($baseDeduction, $rawCollectedSoFar);
                $deductionRemaining = max(0.0, $baseDeduction - $alreadyCovered);

                // الجزء المخصوم من هذه الدفعة هو الحد الأدنى بين الدفعة والخصم المتبقي
                $deductionAppliedNow = min($amount, $deductionRemaining);
                $effectivePaid = max(0.0, $amount - $deductionAppliedNow);
            }
            $displayPaid = $effectivePaid;

            $newPaid = $paidSoFar + $effectivePaid;
            $newRemaining = $totalToCollect - $newPaid;
            $newPaymentStatus = $newRemaining <= 0 ? 'paid' : 'partial_paid';
            $newStatus = $newRemaining <= 0 ? 5 : 6; // 5 paid, 6 partial

            // Update parcel
            $upd = $conn->prepare('UPDATE parcels SET paid_amount = ?, payment_status = ?, status = ?, status_updated_at = NOW() WHERE id = ?');
            $upd->bind_param('dsii', $newPaid, $newPaymentStatus, $newStatus, $parcelId);
            $upd->execute();
            $upd->close();

            // Insert into parcel_collections (الدفعة الخام كما أدخلها المندوب)
            $nullNote = $note; // can be null
            $method = 'cash';
            $src = $source ?: 'courier';
            // افحص أعمدة الجدول لدعم الحقول الاختيارية
            $pcColsRes = $conn->query("SHOW COLUMNS FROM parcel_collections");
            $pcCols = [];
            while ($pcColsRes && ($c = $pcColsRes->fetch_assoc())) { $pcCols[strtolower($c['Field'])] = true; }
            if ($pcColsRes) { $pcColsRes->close(); }
            $hasPCSource = isset($pcCols['source']);
            $hasPCEff = isset($pcCols['effective_amount']);
            $hasPCDed = isset($pcCols['deduction_applied']);

            if ($collectedByCourierId !== null && $collectedByCourierId > 0) {
                if ($hasPCSource && $hasPCEff && $hasPCDed) {
                    $ins = $conn->prepare("INSERT INTO parcel_collections (parcel_id, collected_by_courier_id, amount, effective_amount, deduction_applied, method, source, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $ins->bind_param('iidddsss', $parcelId, $collectedByCourierId, $amount, $effectivePaid, $deductionAppliedNow, $method, $src, $nullNote);
                } elseif ($hasPCSource) {
                    $ins = $conn->prepare("INSERT INTO parcel_collections (parcel_id, collected_by_courier_id, amount, method, source, note) VALUES (?, ?, ?, ?, ?, ?)");
                    $ins->bind_param('iidsss', $parcelId, $collectedByCourierId, $amount, $method, $src, $nullNote);
                } else {
                    $ins = $conn->prepare("INSERT INTO parcel_collections (parcel_id, collected_by_courier_id, amount, method, note) VALUES (?, ?, ?, ?, ?)");
                    $ins->bind_param('iidss', $parcelId, $collectedByCourierId, $amount, $method, $nullNote);
                }
            } else {
                if ($hasPCSource && $hasPCEff && $hasPCDed) {
                    $ins = $conn->prepare("INSERT INTO parcel_collections (parcel_id, collected_by_courier_id, amount, effective_amount, deduction_applied, method, source, note) VALUES (?, NULL, ?, ?, ?, ?, ?, ?)");
                    $ins->bind_param('dddsss', $amount, $effectivePaid, $deductionAppliedNow, $method, $src, $nullNote);
                    // Note: parcel_id already embedded as literal ? is needed; adjust bind
                    // Rebuild with explicit bind including parcel_id
                    $ins->close();
                    $ins = $conn->prepare("INSERT INTO parcel_collections (parcel_id, collected_by_courier_id, amount, effective_amount, deduction_applied, method, source, note) VALUES (?, NULL, ?, ?, ?, ?, ?, ?)");
                    $ins->bind_param('idddsss', $parcelId, $amount, $effectivePaid, $deductionAppliedNow, $method, $src, $nullNote);
                } elseif ($hasPCSource) {
                    $ins = $conn->prepare("INSERT INTO parcel_collections (parcel_id, collected_by_courier_id, amount, method, source, note) VALUES (?, NULL, ?, ?, ?, ?)");
                    $ins->bind_param('idsss', $parcelId, $amount, $method, $src, $nullNote);
                } else {
                    $ins = $conn->prepare("INSERT INTO parcel_collections (parcel_id, collected_by_courier_id, amount, method, note) VALUES (?, NULL, ?, ?, ?)");
                    $ins->bind_param('idss', $parcelId, $amount, $method, $nullNote);
                }
            }
            $ins->execute();
            $ins->close();

            // Mirror into customer_payments if needed (legacy)
            if (self::ALSO_INSERT_CUSTOMER_PAYMENTS) {
                // تحقق سريع من وجود الجدول
                $tblCheck = $conn->query("SHOW TABLES LIKE 'customer_payments'");
                if ($tblCheck && $tblCheck->num_rows > 0) {
                    // فحص الأعمدة لتحديد المخطط المستخدم
                    $colsRes = $conn->query("SHOW COLUMNS FROM customer_payments");
                    $cols = [];
                    while ($c = $colsRes->fetch_assoc()) { $cols[strtolower($c['Field'])] = true; }
                    $colsRes->close();

                    $method = 'cash';
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $collector = $collectedByName ?? ($source === 'courier' ? 'courier' : 'admin');
                    $customerId = null;
                    try { $customerId = (int)($info['parcel']['client_id_fk'] ?? 0) ?: null; } catch (Throwable $e) { $customerId = null; }

                    if (isset($cols['amount']) && isset($cols['payment_type']) && isset($cols['parcel_id']) && isset($cols['customer_id'])) {
                        // مخطط B: customer_id, parcel_id, amount, payment_type, notes, payment_date
                        $custId = $customerId ?? 0;
                        $pstmt = $conn->prepare('INSERT INTO customer_payments (customer_id, parcel_id, amount, payment_date, payment_type, notes, created_at) VALUES (?, ?, ?, NOW(), \"cod_payment\", ?, NOW())');
                        $pstmt->bind_param('iids', $custId, $parcelId, $amount, $note);
                        $pstmt->execute();
                        $pstmt->close();
                    } elseif (isset($cols['payment_amount'])) {
                        // مخطط A: يحتوي على payment_amount وحقول meta
                        $pstmt = $conn->prepare('INSERT INTO customer_payments (parcel_id, payment_amount, payment_date, payment_method, payment_note, collected_by, ip_address) VALUES (?, ?, NOW(), ?, ?, ?, ?)');
                        $pstmt->bind_param('idssss', $parcelId, $amount, $method, $note, $collector, $ip);
                        $pstmt->execute();
                        $pstmt->close();
                    } elseif (isset($cols['amount']) && isset($cols['payment_method']) && isset($cols['customer_id'])) {
                        // مخطط C: جدول مبسط (admin_class) + تفاصيل في customer_payment_details إن وجدت
                        $custId = $customerId ?? 0;
                        $pstmt = $conn->prepare('INSERT INTO customer_payments (customer_id, amount, payment_method, notes, created_at) VALUES (?, ?, ?, ?, NOW())');
                        $pstmt->bind_param('idss', $custId, $amount, $method, $note);
                        $pstmt->execute();
                        $paymentId = $conn->insert_id;
                        $pstmt->close();

                        // customer_payment_details (اختياري)
                        $detailTbl = $conn->query("SHOW TABLES LIKE 'customer_payment_details'");
                        if ($detailTbl && $detailTbl->num_rows > 0) {
                            $dst = $conn->prepare('INSERT INTO customer_payment_details (payment_id, shipment_id, amount, created_at) VALUES (?, ?, ?, NOW())');
                            $dst->bind_param('iid', $paymentId, $parcelId, $amount);
                            $dst->execute();
                            $dst->close();
                        }
                    } else {
                        // محاولة أخيرة آمنة: لا تُدرج إذا لم نتعرف على المخطط
                        // يمكن لاحقاً إضافة mapping إضافي عند اكتشاف مخطط جديد
                    }
                }
            }

            // Track
            $remarks = 'تحصيل: ' . number_format($amount, 2)
                . ' ج.م | الصافي المضاف: ' . number_format($displayPaid, 2)
                . ' ج.م | خصم مطبق: ' . number_format($deductionAppliedNow, 2)
                . ' ج.م | إجمالي الصافي: ' . number_format($newPaid, 2)
                . ' ج.م | المتبقي: ' . number_format($newRemaining, 2) . ' ج.م';
            $trk = $conn->prepare('INSERT INTO parcel_tracks (parcel_id, status, date_created, remarks) VALUES (?, ?, NOW(), ?)');
            $trk->bind_param('iis', $parcelId, $newStatus, $remarks);
            $trk->execute();
            $trk->close();

            $conn->commit();

            return [
                'payment_amount' => $displayPaid,
                'total_paid' => $newPaid,
                'remaining_balance' => $newRemaining,
                'payment_status' => $newPaymentStatus,
                'new_status' => $newStatus,
                'raw_amount_received' => $amount,
                'deduction_applied_now' => $deductionAppliedNow,
                'applied_deductions' => [
                    'context' => $shipmentDirection,
                    'shipping_payer' => $shippingPayer,
                    'delivery_agent_fee' => $deliveryAgentFee,
                    'shipping_fees' => $shippingFees
                ]
            ];
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }

    /**
     * Force finalize full payment based on total_to_collect
     */
    public static function finalizeFullPayment(mysqli $conn, int $parcelId): array {
        $conn->begin_transaction();
        try {
            $info = self::getPaymentInfo($conn, $parcelId);
            $total = (float)$info['total_to_collect'];
            $paid = (float)$info['paid_amount'];
            $delta = max(0.0, $total - $paid);

            if ($delta > 0) {
                // update paid_amount to total and set statuses
                $upd = $conn->prepare('UPDATE parcels SET paid_amount = ?, payment_status = \'paid\', status = 5, paid_at = NOW() WHERE id = ?');
                $upd->bind_param('di', $total, $parcelId);
                $upd->execute();
                $upd->close();
            } else {
                $upd = $conn->prepare('UPDATE parcels SET payment_status = \'paid\', status = 5, paid_at = NOW() WHERE id = ?');
                $upd->bind_param('i', $parcelId);
                $upd->execute();
                $upd->close();
            }

            // track
            $trk = $conn->prepare('INSERT INTO parcel_tracks (parcel_id, status, date_created) VALUES (?, 5, NOW())');
            $trk->bind_param('i', $parcelId);
            $trk->execute();
            $trk->close();

            $conn->commit();
            return [ 'final_paid' => $total ];
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}
?>


