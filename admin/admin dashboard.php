<?php
require_once "../auth/auth_check.php";
checkRole(['Admin']); // Block Bursars, allow only Admins
require_once "../config/db.php";
require_once "../includes/header.php";

// Helper function to fetch threshold settings with defaults
function getSetting($conn, $key, $default) {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    if (!$stmt) return $default;
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $stmt->close();
        return $row['setting_value'];
    }
    $stmt->close();
    return $default;
}

// 1. Fetch System Settings & Dynamic Thresholds
$hostel_quota = (float)getSetting($conn, 'hostel_quota', '300000.00');
$food_quota = (float)getSetting($conn, 'food_quota', '200000.00');
$total_baseline = $hostel_quota + $food_quota;

$partial_min_percent = (float)getSetting($conn, 'partial_min_percent', '10.0');
$cleared_min_percent = (float)getSetting($conn, 'cleared_min_percent', '100.0');

// 2. Aggregate Dynamic Fee Payment Statuses (Excluding Tuition)
$status_counts = [
    'Paid' => 0,
    'Partially Paid' => 0,
    'Not Paid' => 0
];

$sql_summary = "
    SELECT 
        s.student_id,
        COALESCE(SUM(p.amount_paid), 0) AS total_paid
    FROM students s
    LEFT JOIN fee_payments p ON s.student_id = p.student_id AND p.payment_category NOT IN ('Tuition Fee')
    WHERE s.status = 'Active'
    GROUP BY s.student_id
";
$summary_res = $conn->query($sql_summary);

if ($summary_res && $summary_res->num_rows > 0) {
    while ($row = $summary_res->fetch_assoc()) {
        $paid = (float)$row['total_paid'];
        $percentage = ($total_baseline > 0) ? ($paid / $total_baseline) * 100 : 0;

        if ($percentage >= $cleared_min_percent) {
            $status_counts['Paid']++;
        } elseif ($percentage >= $partial_min_percent) {
            $status_counts['Partially Paid']++;
        } else {
            $status_counts['Not Paid']++;
        }
    }
}

// 3. Overall Dashboard Financial Statistics
$total_students_res = $conn->query("SELECT COUNT(*) AS total FROM students WHERE status = 'Active'");
$total_students = ($total_students_res) ? $total_students_res->fetch_assoc()['total'] : 0;

$total_collections_res = $conn->query("SELECT SUM(amount_paid) AS total_amount FROM fee_payments");
$total_collections = ($total_collections_res) ? (float)$total_collections_res->fetch_assoc()['total_amount'] : 0.00;

// 4. Fetch 5 Most Recent Payments
$recent_payments = $conn->query("
    SELECT p.*, s.full_name, s.admission_no 
    FROM fee_payments p
    JOIN students s ON p.student_id = s.student_id
    ORDER BY p.payment_date DESC, p.payment_id DESC
    LIMIT 5
");
?>

<div class="container" style="max-width: 1100px; margin: 30px auto; padding: 20px;">

    <!-- Dashboard Title Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="margin: 0; color: #0f172a; font-size: 1.6rem;">Administrative Dashboard</h2>
            <p style="margin: 4px 0 0 0; color: #64748b; font-size: 0.9rem;">Overview of student payment statuses, financial quotas, and active ledger logs</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="fee_thresholds.php" style="padding: 10px 16px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.9rem;">
                ⚙️ Configure Quotas
            </a>
            <a href="../payments/add.php" style="padding: 10px 16px; background-color: #059669; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.9rem;">
                + Record Payment
            </a>
        </div>
    </div>

    <!-- Overall Overview Cards -->
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 25px;">
        <div style="background: #ffffff; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span style="font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Active Students</span>
                <h2 style="margin: 5px 0 0 0; color: #0f172a; font-size: 1.8rem;"><?= number_format($total_students); ?></h2>
            </div>
            <div style="font-size: 2.2rem; color: #3b82f6;">🎓</div>
        </div>

        <div style="background: #ffffff; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span style="font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Total Collections Recorded</span>
                <h2 style="margin: 5px 0 0 0; color: #059669; font-size: 1.8rem;">TZS <?= number_format($total_collections, 2); ?></h2>
            </div>
            <div style="font-size: 2.2rem; color: #10b981;">💳</div>
        </div>
    </div>

    <!-- Dynamic Hostel & Food Status Breakdown Section -->
    <div style="margin-bottom: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h3 style="margin: 0; color: #1e293b; font-size: 1.15rem;">Hostel & Food Quota Payment Statuses</h3>
            <span style="font-size: 0.82rem; color: #64748b; background: #f1f5f9; padding: 4px 10px; border-radius: 4px; border: 1px solid #e2e8f0;">
                Quota Baseline: TZS <?= number_format($total_baseline, 2); ?>
            </span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px;">
            
            <!-- Paid Card -->
            <a href="../payments/other_payments_list.php" style="text-decoration: none;">
                <div style="background: #ecfdf5; border: 1px solid #a7f3d0; padding: 20px; border-radius: 10px; transition: transform 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 0.85rem; color: #065f46; font-weight: 700; text-transform: uppercase;">Paid (Cleared)</span>
                        <span style="background: #059669; color: #ffffff; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 700;">≥ <?= $cleared_min_percent; ?>%</span>
                    </div>
                    <h3 style="margin: 0; color: #047857; font-size: 1.9rem; font-weight: 800;"><?= number_format($status_counts['Paid']); ?></h3>
                    <p style="margin: 6px 0 0 0; color: #059669; font-size: 0.82rem;">Students cleared for boarding & food</p>
                </div>
            </a>

            <!-- Partially Paid Card -->
            <a href="../payments/other_payments_list.php" style="text-decoration: none;">
                <div style="background: #fffbeb; border: 1px solid #fde68a; padding: 20px; border-radius: 10px; transition: transform 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 0.85rem; color: #92400e; font-weight: 700; text-transform: uppercase;">Partially Paid</span>
                        <span style="background: #d97706; color: #ffffff; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 700;">≥ <?= $partial_min_percent; ?>%</span>
                    </div>
                    <h3 style="margin: 0; color: #d97706; font-size: 1.9rem; font-weight: 800;"><?= number_format($status_counts['Partially Paid']); ?></h3>
                    <p style="margin: 6px 0 0 0; color: #b45309; font-size: 0.82rem;">Students with partial quota deposits</p>
                </div>
            </a>

            <!-- Not Paid Card -->
            <a href="../payments/other_payments_list.php" style="text-decoration: none;">
                <div style="background: #fef2f2; border: 1px solid #fecaca; padding: 20px; border-radius: 10px; transition: transform 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 0.85rem; color: #991b1b; font-weight: 700; text-transform: uppercase;">Not Paid</span>
                        <span style="background: #dc2626; color: #ffffff; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 700;">< <?= $partial_min_percent; ?>%</span>
                    </div>
                    <h3 style="margin: 0; color: #dc2626; font-size: 1.9rem; font-weight: 800;"><?= number_format($status_counts['Not Paid']); ?></h3>
                    <p style="margin: 6px 0 0 0; color: #b91c1c; font-size: 0.82rem;">Students with no significant payment</p>
                </div>
            </a>

        </div>
    </div>

    <!-- Recent Payments Table -->
    <div style="background: #ffffff; border-radius: 10px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="padding: 18px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: #1e293b; font-size: 1.05rem;">Recent Fee Transactions</h3>
            <a href="../payments/index.php" style="color: #2563eb; text-decoration: none; font-size: 0.88rem; font-weight: 600;">View All Transactions →</a>
        </div>
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
            <thead>
                <tr style="background-color: #ffffff; border-bottom: 1px solid #e2e8f0; color: #64748b;">
                    <th style="padding: 12px 15px;">Date</th>
                    <th style="padding: 12px 15px;">Student</th>
                    <th style="padding: 12px 15px;">Purpose</th>
                    <th style="padding: 12px 15px;">Method</th>
                    <th style="padding: 12px 15px; text-align: right;">Amount (TZS)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recent_payments && $recent_payments->num_rows > 0): ?>
                    <?php while ($p = $recent_payments->fetch_assoc()): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; color: #334155;">
                            <td style="padding: 12px 15px; white-space: nowrap;"><?= date('d M Y', strtotime($p['payment_date'])); ?></td>
                            <td style="padding: 12px 15px;">
                                <strong><?= htmlspecialchars($p['full_name']); ?></strong><br>
                                <small style="color: #64748b;"><?= htmlspecialchars($p['admission_no']); ?></small>
                            </td>
                            <td style="padding: 12px 15px;">
                                <span style="background: #f1f5f9; color: #0f172a; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 0.82rem;">
                                    <?= htmlspecialchars($p['payment_category']); ?>
                                </span>
                            </td>
                            <td style="padding: 12px 15px; color: #475569;"><?= htmlspecialchars($p['payment_method']); ?></td>
                            <td style="padding: 12px 15px; text-align: right; font-weight: 700; color: #059669;">
                                <?= number_format((float)$p['amount_paid'], 2); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="padding: 25px; text-align: center; color: #94a3b8;">
                            No recent transactions found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php require_once "../includes/footer.php"; ?>