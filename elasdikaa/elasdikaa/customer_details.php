<?php
if(!isset($conn)){ include 'db_connect.php'; }
include_once('admin_class.php');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$c = $conn->query("SELECT c.*, g.name as gov_name, a.name as area_name 
    FROM customers c
    LEFT JOIN governorates g ON c.governorate_id = g.id
    LEFT JOIN areas a ON c.area_id = a.id
    WHERE c.id = $id")->fetch_assoc();

// جلب الملخص المالي للعميل
$admin = new Action();
$financial_summary = $admin->get_customer_financial_summary($id);
$balance_transactions = $admin->get_customer_balance_transactions($id, 20);
?>
<div class="col-lg-10 mx-auto">
  <div class="card card-outline card-primary">
    <div class="card-header">
      <h3 class="card-title">تفاصيل العميل</h3>
    </div>
    <div class="card-body">
      <table class="table table-bordered">
        <tr>
          <th>اسم العميل</th>
          <td><?php echo htmlspecialchars($c['name']) ?></td>
        </tr>
        <tr>
          <th>رقم الجوال</th>
          <td><?php echo htmlspecialchars($c['phone']) ?></td>
        </tr>
        <tr>
          <th>البريد الإلكتروني</th>
          <td><?php echo htmlspecialchars($c['email']) ?></td>
        </tr>
        <tr>
          <th>العنوان</th>
          <td><?php echo htmlspecialchars($c['address']) ?></td>
        </tr>
        <tr>
          <th>المحافظة</th>
          <td><?php echo htmlspecialchars($c['gov_name']) ?></td>
        </tr>
        <tr>
          <th>المنطقة</th>
          <td><?php echo htmlspecialchars($c['area_name']) ?></td>
        </tr>
        <tr>
          <th>الحالة</th>
          <td><?php echo $c['status'] ? "نشط" : "موقوف" ?></td>
        </tr>
        <tr>
          <th>تاريخ الإضافة</th>
          <td><?php echo htmlspecialchars($c['date_created']) ?></td>
        </tr>
      </table>
      
      <!-- الملخص المالي للعميل -->
      <?php if ($financial_summary && $financial_summary['total_transactions'] > 0): ?>
      <hr>
      <h5 class="text-primary"><i class="fas fa-wallet"></i> الملخص المالي</h5>
      <div class="row">
        <div class="col-md-3">
          <div class="card bg-info text-white">
            <div class="card-body text-center">
              <h6>الرصيد الحالي</h6>
              <h4><?php echo number_format($financial_summary['current_balance'], 2); ?> جنيه</h4>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card bg-success text-white">
            <div class="card-body text-center">
              <h6>إجمالي الدائن</h6>
              <h4><?php echo number_format($financial_summary['total_credit'], 2); ?> جنيه</h4>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card bg-danger text-white">
            <div class="card-body text-center">
              <h6>إجمالي المدين</h6>
              <h4><?php echo number_format($financial_summary['total_debit'], 2); ?> جنيه</h4>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card bg-warning text-white">
            <div class="card-body text-center">
              <h6>عدد المعاملات</h6>
              <h4><?php echo $financial_summary['total_transactions']; ?></h4>
            </div>
          </div>
        </div>
      </div>
      
      <div class="row mt-3">
        <div class="col-md-6">
          <div class="card border-primary">
            <div class="card-header bg-primary text-white">
              <h6 class="mb-0"><i class="fas fa-shipping-fast"></i> تكاليف الشحن</h6>
            </div>
            <div class="card-body">
              <p><strong>عدد مرات تحمل تكلفة الشحن:</strong> <?php echo $financial_summary['shipping_charges_count']; ?></p>
              <p><strong>إجمالي تكاليف الشحن المدفوعة:</strong> <?php echo number_format($financial_summary['total_shipping_charges'], 2); ?> جنيه</p>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card border-success">
            <div class="card-header bg-success text-white">
              <h6 class="mb-0"><i class="fas fa-money-bill-wave"></i> مدفوعات COD</h6>
            </div>
            <div class="card-body">
              <p><strong>عدد مدفوعات COD:</strong> <?php echo $financial_summary['cod_payments_count']; ?></p>
              <p><strong>إجمالي مدفوعات COD:</strong> <?php echo number_format($financial_summary['total_cod_payments'], 2); ?> جنيه</p>
            </div>
          </div>
        </div>
      </div>
      
                  <!-- الشحنات المرتبطة بالعميل -->
            <hr>
            <h5 class="text-primary"><i class="fas fa-shipping-fast"></i> الشحنات المرتبطة بالعميل</h5>
            <?php
            // جلب الشحنات المرتبطة بالعميل
            $customer_parcels_query = "
                SELECT p.*, ps.name_ar as status_name, ps.color as status_color,
                       c.name as courier_name, a.company_name as agent_name
                FROM parcels p
                LEFT JOIN parcel_status ps ON p.status = ps.id
                LEFT JOIN couriers c ON p.courier_id = c.id
                LEFT JOIN agents a ON p.agent_id = a.id
                WHERE p.client_id_fk = ? OR p.sender_name = ? OR p.sender_phone = ?
                ORDER BY p.date_created DESC
                LIMIT 20
            ";
            
            $stmt_parcels = $conn->prepare($customer_parcels_query);
            $stmt_parcels->bind_param("iss", $customer_id, $customer['name'], $customer['phone']);
            $stmt_parcels->execute();
            $customer_parcels = $stmt_parcels->get_result();
            ?>
            
            <?php if ($customer_parcels && $customer_parcels->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="bg-light">
                        <tr>
                            <th>رقم التتبع</th>
                            <th>المستلم</th>
                            <th>مبلغ COD</th>
                            <th>المبلغ المدفوع</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($parcel = $customer_parcels->fetch_assoc()): ?>
                        <?php
                        $total_to_collect = ($parcel['cod_amount'] ?? 0);
                        if (($parcel['shipping_payer'] ?? 'sender') == 'recipient') {
                            $total_to_collect += ($parcel['shipping_fees'] ?? 0);
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($parcel['tracking_number']); ?></strong>
                                <?php if (!empty($parcel['courier_name'])): ?>
                                <br><small class="text-info"><i class="fas fa-motorcycle"></i> <?php echo htmlspecialchars($parcel['courier_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($parcel['recipient_name']); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($parcel['recipient_phone']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo number_format($parcel['cod_amount'] ?? 0, 2); ?> جنيه</span>
                                <?php if ($total_to_collect != ($parcel['cod_amount'] ?? 0)): ?>
                                <br><small class="text-muted">الإجمالي: <?php echo number_format($total_to_collect, 2); ?> جنيه</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo ($parcel['paid_amount'] ?? 0) > 0 ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo number_format($parcel['paid_amount'] ?? 0, 2); ?> جنيه
                                </span>
                                <?php if (($parcel['payment_status'] ?? '') == 'partial_paid'): ?>
                                <br><small class="text-warning">دفع جزئي</small>
                                <?php elseif (($parcel['payment_status'] ?? '') == 'paid'): ?>
                                <br><small class="text-success">مدفوع بالكامل</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background-color: <?php echo $parcel['status_color'] ?? '#6c757d'; ?>; color: white;">
                                    <?php echo htmlspecialchars($parcel['status_name'] ?? 'غير محدد'); ?>
                                </span>
                            </td>
                            <td>
                                <small><?php echo date('d/m/Y H:i', strtotime($parcel['date_created'])); ?></small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="print_waybill.php?id=<?php echo $parcel['id']; ?>" target="_blank" 
                                       class="btn btn-info btn-sm" title="طباعة البوليصة">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <a href="index.php?page=new_parcel&id=<?php echo $parcel['id']; ?>" 
                                       class="btn btn-primary btn-sm" title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if (($parcel['paid_amount'] ?? 0) < $total_to_collect): ?>
                                    <button type="button" class="btn btn-success btn-sm pay-parcel-btn" 
                                            data-id="<?php echo $parcel['id']; ?>" 
                                            data-cod="<?php echo $parcel['cod_amount'] ?? 0; ?>"
                                            data-paid="<?php echo $parcel['paid_amount'] ?? 0; ?>"
                                            data-total="<?php echo $total_to_collect; ?>"
                                            title="تسجيل دفعة">
                                        <i class="fas fa-dollar-sign"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> لا توجد شحنات مرتبطة بهذا العميل.
            </div>
            <?php endif; ?>
            
            <!-- معاملات الرصيد -->
            <hr>
            <h5 class="text-primary"><i class="fas fa-list-alt"></i> آخر معاملات الرصيد</h5>
      <?php if (!empty($balance_transactions)): ?>
      <div class="table-responsive">
        <table class="table table-striped table-bordered">
          <thead class="bg-light">
            <tr>
              <th>التاريخ</th>
              <th>نوع المعاملة</th>
              <th>الوصف</th>
              <th>المبلغ</th>
              <th>رقم الشحنة</th>
              <th>بواسطة</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($balance_transactions as $transaction): ?>
            <tr>
              <td><?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?></td>
              <td>
                <?php 
                switch ($transaction['transaction_type']) {
                  case 'shipping_charge':
                    echo '<span class="badge bg-danger">خصم شحن</span>';
                    break;
                  case 'cod_payment':
                    echo '<span class="badge bg-success">دفع COD</span>';
                    break;
                  case 'balance_adjustment':
                    echo '<span class="badge bg-warning">تعديل رصيد</span>';
                    break;
                  case 'refund':
                    echo '<span class="badge bg-info">استرداد</span>';
                    break;
                  default:
                    echo '<span class="badge bg-secondary">أخرى</span>';
                }
                ?>
              </td>
              <td><?php echo htmlspecialchars($transaction['description']); ?></td>
              <td class="<?php echo $transaction['amount'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                <?php echo ($transaction['amount'] >= 0 ? '+' : '') . number_format($transaction['amount'], 2); ?> جنيه
              </td>
              <td><?php echo $transaction['tracking_number'] ? htmlspecialchars($transaction['tracking_number']) : '-'; ?></td>
              <td><?php echo $transaction['firstname'] ? htmlspecialchars($transaction['firstname'] . ' ' . $transaction['lastname']) : '-'; ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="alert alert-info">لا توجد معاملات مالية لهذا العميل.</div>
      <?php endif; ?>
      <?php endif; ?>
      <hr>
      <h5>سجل الشحنات</h5>
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>#</th>
            <th>رقم الشحنة</th>
            <th>المندوب المسؤول</th>
            <th>الحالة</th>
            <th>تاريخ</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $shipments = $conn->query("SELECT s.*, cr.name as courier_name 
            FROM shipments s
            LEFT JOIN couriers cr ON s.courier_id = cr.id
            WHERE s.customer_id = $id ORDER BY s.id DESC");
          $i = 1;
          while($sh = $shipments->fetch_assoc()):
          ?>
          <tr>
            <td><?php echo $i++ ?></td>
            <td><?php echo $sh['id'] ?></td>
            <td><?php echo htmlspecialchars($sh['courier_name']) ?></td>
            <td><?php echo htmlspecialchars($sh['status']) ?></td>
            <td><?php echo htmlspecialchars($sh['created_at']) ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
      <hr>
      <h5>سجل العمليات</h5>
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>العملية</th>
            <th>المستخدم</th>
            <th>تاريخ ووقت</th>
          </tr>
        </thead>
        <tbody>
        <?php
          $logs = $conn->query("SELECT * FROM customer_logs WHERE customer_id = $id ORDER BY created_at DESC");
          while($log = $logs->fetch_assoc()):
        ?>
          <tr>
            <td><?php echo htmlspecialchars($log['action']) ?></td>
            <td><?php echo htmlspecialchars($log['username']) ?></td>
            <td><?php echo htmlspecialchars($log['created_at']) ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- نافذة تسجيل الدفعات -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentModalLabel">تسجيل دفعة للشحنة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" id="parcel_id" name="parcel_id">
                    <div class="mb-3">
                        <label for="payment_amount" class="form-label">المبلغ المدفوع</label>
                        <input type="number" class="form-control" id="payment_amount" name="payment_amount" step="0.01" min="0" required>
                        <div class="form-text">
                            <span id="payment_info"></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="payment_notes" class="form-label">ملاحظات (اختياري)</label>
                        <textarea class="form-control" id="payment_notes" name="payment_notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-success" id="savePaymentBtn">حفظ الدفعة</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // تسجيل دفعة للشحنة
    $('.pay-parcel-btn').click(function() {
        var parcelId = $(this).data('id');
        var codAmount = parseFloat($(this).data('cod'));
        var paidAmount = parseFloat($(this).data('paid'));
        var totalAmount = parseFloat($(this).data('total'));
        var remainingAmount = totalAmount - paidAmount;
        
        $('#parcel_id').val(parcelId);
        $('#payment_amount').attr('max', remainingAmount);
        $('#payment_amount').val(remainingAmount);
        $('#payment_info').html(
            '<strong>مبلغ COD:</strong> ' + codAmount.toFixed(2) + ' جنيه<br>' +
            '<strong>الإجمالي المطلوب:</strong> ' + totalAmount.toFixed(2) + ' جنيه<br>' +
            '<strong>المدفوع سابقاً:</strong> ' + paidAmount.toFixed(2) + ' جنيه<br>' +
            '<strong>المتبقي:</strong> ' + remainingAmount.toFixed(2) + ' جنيه'
        );
        
        $('#paymentModal').modal('show');
    });
    
    // حفظ الدفعة
    $('#savePaymentBtn').click(function() {
        var formData = {
            action: 'partial_pay_parcel',
            id: $('#parcel_id').val(),
            amount: $('#payment_amount').val(),
            notes: $('#payment_notes').val()
        };
        
        $.ajax({
            url: 'parcel_list.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert('تم تسجيل الدفعة بنجاح!');
                    $('#paymentModal').modal('hide');
                    location.reload(); // إعادة تحميل الصفحة لعرض التحديث
                } else {
                    alert('خطأ: ' + response.message);
                }
            },
            error: function() {
                alert('حدث خطأ أثناء تسجيل الدفعة');
            }
        });
    });
});
</script>