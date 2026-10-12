<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";
require_once "../includes/header.php";

$class_id  = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$stream_id = isset($_GET['stream_id']) ? (int)$_GET['stream_id'] : 0;

// Fetch classes and streams for dropdown filters
$classes = $conn->query("SELECT class_id, class_name FROM classes WHERE is_active = 1 ORDER BY class_id ASC");
$streams = $conn->query("SELECT stream_id, stream_name FROM streams WHERE is_active = 1 ORDER BY stream_id ASC");

$class_summary = null;

if ($class_id > 0 && $stream_id > 0) {
    // Assuming contribution_type_id 1 = Food Fee and contribution_type_id 2 = Hostel Fee
    $sql = "
        SELECT 
            s.student_id,
            s.admission_no,
            s.full_name,
            s.payee_type,
            s.payee_other,
            se.academic_year,
            COALESCE(SUM(CASE WHEN p.contribution_type_id = 1 THEN p.amount ELSE 0 END), 0) AS total_food_paid,
            COALESCE(SUM(CASE WHEN p.contribution_type_id = 2 THEN p.amount ELSE 0 END), 0) AS total_hostel_paid,
            COALESCE(SUM(CASE WHEN fc.contribution_type_id = 3 THEN fc.quantity ELSE 0 END), 0) AS total_maize,
            COALESCE(SUM(CASE WHEN fc.contribution_type_id = 4 THEN fc.quantity ELSE 0 END), 0) AS total_beans,
            MAX(ft.food_fee_annual) AS food_fee_annual,
            MAX(ft.hostel_fee_monthly) AS hostel_fee_monthly,
            MAX(ft.maize_quota_kg) AS maize_quota_kg,
            MAX(ft.beans_quota_kg) AS beans_quota_kg
        FROM student_enrollments se
        JOIN students s ON se.student_id = s.student_id
        LEFT JOIN fee_thresholds ft ON se.class_id = ft.class_id AND se.academic_year = ft.academic_year
        LEFT JOIN payments p ON s.student_id = p.student_id AND p.status = 'Active'
        LEFT JOIN food_contributions fc ON s.student_id = fc.student_id AND fc.status = 'Active'
        WHERE se.class_id = ? AND se.stream_id = ? AND s.status = 'Active'
        GROUP BY 
            s.student_id,
            s.admission_no,
            s.full_name,
            s.payee_type,
            s.payee_other,
            se.academic_year
        ORDER BY s.full_name ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $class_id, $stream_id);
    $stmt->execute();
    $class_summary = $stmt->get_result();
}
?>

<div class="container">
    <div class="no-print" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
        <?php if ($class_summary && $class_summary->num_rows > 0): ?>
            <button onclick="window.print()" class="action-btn">🖨️ Print Class Summary</button>
            <a href="export.php?type=class&class_id=<?= $class_id; ?>&stream_id=<?= $stream_id; ?>" class="action-btn secondary">📊 Export to Excel</a>
        <?php endif; ?>
        <a href="index.php" class="action-btn secondary">Back to Reports</a>
    </div>

    <!-- Selection Form -->
    <div class="dashboard-card no-print" style="margin-bottom: 20px;">
        <h2>Select Class and Stream</h2>
        <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="min-width: 200px;">
                <label for="class_id">Class</label>
                <select name="class_id" id="class_id" required>
                    <option value="">-- Select Class --</option>
                    <?php while ($c = $classes->fetch_assoc()): ?>
                        <option value="<?= $c['class_id']; ?>" <?= $class_id == $c['class_id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($c['class_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group" style="min-width: 200px;">
                <label for="stream_id">Stream</label>
                <select name="stream_id" id="stream_id" required>
                    <option value="">-- Select Stream --</option>
                    <?php while ($st = $streams->fetch_assoc()): ?>
                        <option value="<?= $st['stream_id']; ?>" <?= $stream_id == $st['stream_id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($st['stream_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <button type="submit" class="action-btn">Generate Report</button>
            </div>
        </form>
    </div>

    <h2>Class Summary Financial Report</h2>

    <div class="dashboard-table-wrapper">
        <table class="dashboard-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Admission No</th>
                    <th>Student Name</th>
                    <th>Payee</th>
                    <th>Food Fee Paid (TZS)</th>
                    <th>Hostel Fee Paid (TZS)</th>
                    <th>Maize (kg)</th>
                    <th>Beans (kg)</th>
                    <th>Food Fee Status</th>
                    <th>Hostel Fee Status</th>
                    <th>Maize & Beans Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($class_summary && $class_summary->num_rows > 0): ?>
                    <?php 
                    $count = 1; 
                    while ($row = $class_summary->fetch_assoc()): 
                        // 1. Food Fee Status Logic
                        $food_paid  = $row['total_food_paid'];
                        $food_target = $row['food_fee_annual'] ?? 0;

                        if ($food_target > 0) {
                            if ($food_paid >= $food_target) {
                                $food_status = '<span style="color: green; font-weight: bold;">Paid</span>';
                            } elseif ($food_paid > 0) {
                                $food_status = '<span style="color: orange; font-weight: bold;">Partially Paid</span>';
                            } else {
                                $food_status = '<span style="color: red; font-weight: bold;">Not Paid</span>';
                            }
                        } else {
                            $food_status = '<span style="color: gray;">Not Configured</span>';
                        }

                        // 2. Hostel Fee Status Logic
                        $hostel_paid  = $row['total_hostel_paid'];
                        $hostel_target = $row['hostel_fee_monthly'] ?? 0;

                        if ($hostel_target > 0) {
                            if ($hostel_paid >= $hostel_target) {
                                $hostel_status = '<span style="color: green; font-weight: bold;">Paid</span>';
                            } elseif ($hostel_paid > 0) {
                                $hostel_status = '<span style="color: orange; font-weight: bold;">Partially Paid</span>';
                            } else {
                                $hostel_status = '<span style="color: red; font-weight: bold;">Not Paid</span>';
                            }
                        } else {
                            $hostel_status = '<span style="color: gray;">Not Configured</span>';
                        }

                        // 3. Maize & Beans Status Logic
                        $total_maize = $row['total_maize'];
                        $total_beans = $row['total_beans'];
                        $maize_quota = $row['maize_quota_kg'] ?? 0;
                        $beans_quota = $row['beans_quota_kg'] ?? 0;

                        $total_produce_paid = $total_maize + $total_beans;
                        $total_produce_quota = $maize_quota + $beans_quota;

                        if ($total_produce_quota > 0) {
                            if ($total_maize >= $maize_quota && $total_beans >= $beans_quota) {
                                $maize_beans_status = '<span style="color: green; font-weight: bold;">Paid</span>';
                            } elseif ($total_produce_paid > 0) {
                                $maize_beans_status = '<span style="color: orange; font-weight: bold;">Partially Paid</span>';
                            } else {
                                $maize_beans_status = '<span style="color: red; font-weight: bold;">Not Paid</span>';
                            }
                        } else {
                            $maize_beans_status = '<span style="color: gray;">Not Configured</span>';
                        }
                    ?>
                        <tr>
                            <td><?= $count++; ?></td>
                            <td><?= htmlspecialchars($row['admission_no']); ?></td>
                            <td><?= htmlspecialchars($row['full_name']); ?></td>
                            <td><?= htmlspecialchars($row['payee_type'] === 'Other' ? $row['payee_other'] : $row['payee_type']); ?></td>
                            <td>TZS <?= number_format($row['total_food_paid'], 2); ?></td>
                            <td>TZS <?= number_format($row['total_hostel_paid'], 2); ?></td>
                            <td><?= number_format($row['total_maize'], 2); ?></td>
                            <td><?= number_format($row['total_beans'], 2); ?></td>
                            <td><?= $food_status; ?></td>
                            <td><?= $hostel_status; ?></td>
                            <td><?= $maize_beans_status; ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php elseif ($class_id > 0 && $stream_id > 0): ?>
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 20px;">
                            No active students found in this class and stream.
                        </td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 20px;">
                            Please select a class and stream above to display the report.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include "../includes/footer.php"; ?>