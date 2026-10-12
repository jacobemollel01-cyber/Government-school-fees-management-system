<?php
require_once "../auth/auth_check.php";
checkRole(['Admin']); // Block Bursars, allow only Admins
require_once "../config/db.php";
require_once "../includes/header.php";

$error = "";
$success = "";

// Ensure configuration table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Helper function to fetch settings
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

// Helper function to save settings
function saveSetting($conn, $key, $value) {
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    if ($stmt) {
        $stmt->bind_param("sss", $key, $value, $value);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $hostel_quota = (float)($_POST['hostel_quota'] ?? 300000.00);
    $food_quota = (float)($_POST['food_quota'] ?? 200000.00);
    $partial_min_percent = (float)($_POST['partial_min_percent'] ?? 10.0);
    $cleared_min_percent = (float)($_POST['cleared_min_percent'] ?? 100.0);
    $late_payment_grace_days = (int)($_POST['late_payment_grace_days'] ?? 14);

    if ($hostel_quota < 0 || $food_quota < 0) {
        $error = "Fee quota amounts cannot be negative.";
    } elseif ($partial_min_percent < 0 || $partial_min_percent > 100) {
        $error = "Partially Paid threshold percentage must be between 0% and 100%.";
    } else {
        saveSetting($conn, 'hostel_quota', (string)$hostel_quota);
        saveSetting($conn, 'food_quota', (string)$food_quota);
        saveSetting($conn, 'partial_min_percent', (string)$partial_min_percent);
        saveSetting($conn, 'cleared_min_percent', (string)$cleared_min_percent);
        saveSetting($conn, 'late_payment_grace_days', (string)$late_payment_grace_days);
        
        $success = "Fee threshold rules updated successfully!";
    }
}

// Load Configuration
$hostel_quota = (float)getSetting($conn, 'hostel_quota', '300000.00');
$food_quota = (float)getSetting($conn, 'food_quota', '200000.00');
$total_baseline = $hostel_quota + $food_quota;

$partial_min_percent = (float)getSetting($conn, 'partial_min_percent', '10.0');
$cleared_min_percent = (float)getSetting($conn, 'cleared_min_percent', '100.0');
$late_payment_grace_days = (int)getSetting($conn, 'late_payment_grace_days', '14');

// Calculate Fee Status Breakdown
$status_counts = ['Paid' => 0, 'Partially Paid' => 0, 'Not Paid' => 0];

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
        // FIXED: Reading $row['total_paid'] matching the SQL query alias
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
?>

<div class="container" style="max-width: 1000px; margin: 30px auto; padding: 20px;">

    <!-- Top Navigation Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2 style="margin: 0; color: #1e293b; font-size: 1.6rem;">Hostel & Food Fee Thresholds</h2>
            <p style="margin: 4px 0 0 0; color: #64748b; font-size: 0.9rem;">Status calculations are based strictly on Hostel, Catering, and Auxiliary payment receipts</p>
        </div>
        <div>
            <a href="admin dashboard.php" style="padding: 10px 16px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.9rem;">← Dashboard</a>
        </div>
    </div>

    <!-- Notification Alerts -->
    <?php if (!empty($error)): ?>
        <div style="background-color: #f8d7da; color: #842029; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9rem;">
            <strong>Error:</strong> <?= htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div style="background-color: #d1e7dd; color: #0f5132; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9rem;">
            <strong>Success:</strong> <?= htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Status Breakdown Cards -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px;">
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; padding: 18px; border-radius: 8px; text-align: center;">
            <span style="font-size: 0.85rem; color: #065f46; font-weight: 600; text-transform: uppercase;">Paid (Cleared)</span>
            <h3 style="margin: 6px 0 0 0; color: #047857; font-size: 1.6rem;"><?= $status_counts['Paid']; ?> Students</h3>
            <small style="color: #059669;">≥ <?= $cleared_min_percent; ?>% of baseline (TZS <?= number_format($total_baseline, 2); ?>)</small>
        </div>

        <div style="background: #fffbeb; border: 1px solid #fde68a; padding: 18px; border-radius: 8px; text-align: center;">
            <span style="font-size: 0.85rem; color: #92400e; font-weight: 600; text-transform: uppercase;">Partially Paid</span>
            <h3 style="margin: 6px 0 0 0; color: #d97706; font-size: 1.6rem;"><?= $status_counts['Partially Paid']; ?> Students</h3>
            <small style="color: #b45309;">Between <?= $partial_min_percent; ?>% and <?= $cleared_min_percent - 0.1; ?>% paid</small>
        </div>

        <div style="background: #fef2f2; border: 1px solid #fecaca; padding: 18px; border-radius: 8px; text-align: center;">
            <span style="font-size: 0.85rem; color: #991b1b; font-weight: 600; text-transform: uppercase;">Not Paid</span>
            <h3 style="margin: 6px 0 0 0; color: #dc2626; font-size: 1.6rem;"><?= $status_counts['Not Paid']; ?> Students</h3>
            <small style="color: #b91c1c;">< <?= $partial_min_percent; ?>% paid</small>
        </div>
    </div>

    <!-- Threshold Configuration Form -->
    <div style="background: #ffffff; padding: 25px; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <form method="POST" action="">
            
            <h3 style="margin-top: 0; color: #0f172a; font-size: 1.15rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                1. Hostel & Food Expected Quotas
            </h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #334155; font-size: 0.9rem;">
                        Hostel / Boarding Quota (TZS) *
                    </label>
                    <input type="number" name="hostel_quota" step="1000" value="<?= $hostel_quota; ?>" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;">
                    <small style="color: #64748b; font-size: 0.8rem;">Target hostel amount per student.</small>
                </div>

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #334155; font-size: 0.9rem;">
                        Food & Catering Quota (TZS) *
                    </label>
                    <input type="number" name="food_quota" step="1000" value="<?= $food_quota; ?>" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;">
                    <small style="color: #64748b; font-size: 0.8rem;">Target food & catering fee per student.</small>
                </div>
            </div>

            <h3 style="color: #0f172a; font-size: 1.15rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; margin-top: 10px;">
                2. Status Percentage Thresholds
            </h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #334155; font-size: 0.9rem;">
                        'Partially Paid' Minimum Deposit (%) *
                    </label>
                    <input type="number" name="partial_min_percent" step="0.1" min="0" max="100" value="<?= $partial_min_percent; ?>" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;">
                    <small style="color: #64748b; font-size: 0.8rem;">Minimum deposit percentage needed to qualify as 'Partially Paid'.</small>
                </div>

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #334155; font-size: 0.9rem;">
                        'Paid / Cleared' Target (%) *
                    </label>
                    <input type="number" name="cleared_min_percent" step="0.1" min="1" max="100" value="<?= $cleared_min_percent; ?>" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;">
                    <small style="color: #64748b; font-size: 0.8rem;">Percentage required for full 'Paid' status.</small>
                </div>
            </div>

            <div style="margin-bottom: 25px;">
                <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #334155; font-size: 0.9rem;">
                    Late Payment Grace Period (Days)
                </label>
                <input type="number" name="late_payment_grace_days" min="0" max="90" value="<?= $late_payment_grace_days; ?>" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;">
                <small style="color: #64748b; font-size: 0.8rem;">Days allowed before 'Partially Paid' accounts trigger warnings.</small>
            </div>

            <!-- Submit Buttons -->
            <div style="display: flex; gap: 12px;">
                <button type="submit" style="padding: 12px 24px; background-color: #059669; color: #ffffff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.95rem;">
                    Save Quotas & Thresholds
                </button>
                <a href="admin dashboard.php" style="padding: 12px 20px; background-color: #f1f5f9; color: #475569; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.95rem;">
                    Cancel
                </a>
            </div>

        </form>
    </div>

</div>

<?php require_once "../includes/footer.php"; ?>