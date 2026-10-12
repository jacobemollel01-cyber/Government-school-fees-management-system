<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";

// Handle Excel Export Request BEFORE outputting HTML header
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $search = trim($_GET['search'] ?? '');
    $category_filter = trim($_GET['category'] ?? '');

    $sql = "
        SELECT p.payment_date, s.admission_no, s.full_name, p.payment_category, p.payer_name, p.payment_method, p.amount_paid, p.remarks
        FROM fee_payments p
        JOIN students s ON p.student_id = s.student_id
        WHERE p.payment_category NOT IN ('Tuition Fee')
    ";

    $params = [];
    $types = "";

    if (!empty($category_filter)) {
        $sql .= " AND p.payment_category = ?";
        $params[] = $category_filter;
        $types .= "s";
    }

    if (!empty($search)) {
        $sql .= " AND (s.full_name LIKE ? OR s.admission_no LIKE ? OR p.payer_name LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "sss";
    }

    $sql .= " ORDER BY p.payment_date DESC, p.payment_id DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Set Excel Download Headers
    $filename = "Auxiliary_Payments_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');
    // Header Row
    fputcsv($output, ['Payment Date', 'Admission No', 'Student Name', 'Payment Category', 'Payer Name', 'Payment Method', 'Amount Paid (TZS)', 'Remarks']);

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['payment_date'],
            $row['admission_no'],
            $row['full_name'],
            $row['payment_category'],
            $row['payer_name'],
            $row['payment_method'],
            $row['amount_paid'],
            $row['remarks']
        ]);
    }
    fclose($output);
    exit();
}

require_once "../includes/header.php";

// Filter Parameters for Page Display
$search = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['category'] ?? '');

// Base SQL excluding routine tuition
$sql = "
    SELECT p.*, s.full_name, s.admission_no 
    FROM fee_payments p
    JOIN students s ON p.student_id = s.student_id
    WHERE p.payment_category NOT IN ('Tuition Fee')
";

$params = [];
$types = "";

if (!empty($category_filter)) {
    $sql .= " AND p.payment_category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (s.full_name LIKE ? OR s.admission_no LIKE ? OR p.payer_name LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$sql .= " ORDER BY p.payment_date DESC, p.payment_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$total_auxiliary = 0;
$records = [];
while ($row = $result->fetch_assoc()) {
    $total_auxiliary += (float)$row['amount_paid'];
    $records[] = $row;
}

// Fetch categories for filter dropdown
$cat_query = $conn->query("SELECT DISTINCT payment_category FROM fee_payments WHERE payment_category NOT IN ('Tuition Fee') ORDER BY payment_category ASC");
?>

<div class="container" style="max-width: 1100px; margin: 30px auto; padding: 20px;">
    
    <!-- Header Section -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <div>
            <h2 style="margin: 0; color: #1e293b; font-size: 1.6rem;">Auxiliary & Special Payments</h2>
            <p style="margin: 5px 0 0 0; color: #64748b; font-size: 0.9rem;">View records for Examinations, Damage Charges, ID Replacements & Extras</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <!-- Export to Excel Link -->
            <a href="other_payments_list.php?export=excel&search=<?= urlencode($search); ?>&category=<?= urlencode($category_filter); ?>" style="padding: 10px 16px; background-color: #10b981; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 5px;">
                📊 Export to Excel
            </a>
            <a href="add.php" style="padding: 10px 16px; background-color: #059669; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.9rem;">+ Record Payment</a>
            <a href="index.php" style="padding: 10px 16px; background-color: #f1f5f9; color: #475569; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.9rem;">All Payments</a>
        </div>
    </div>

    <!-- Summary Box -->
    <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 15px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <span style="color: #1e40af; font-weight: 600;">Total Special Collections Filtered:</span>
        <span style="font-size: 1.3rem; font-weight: 700; color: #1e3a8a;">TZS <?= number_format($total_auxiliary, 2); ?></span>
    </div>

    <!-- Filters & Search Form -->
    <div style="background: #ffffff; padding: 18px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <form method="GET" action="other_payments_list.php" style="display: flex; gap: 12px; flex-wrap: wrap;">
            
            <div style="flex: 1; min-width: 200px;">
                <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Search student name or admission no..." style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;">
            </div>

            <div style="width: 240px;">
                <select name="category" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;">
                    <option value="">All Special Categories</option>
                    <?php if ($cat_query && $cat_query->num_rows > 0): ?>
                        <?php while ($cat = $cat_query->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($cat['payment_category']); ?>" <?= $category_filter === $cat['payment_category'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($cat['payment_category']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <button type="submit" style="padding: 9px 18px; background-color: #2563eb; color: #ffffff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.9rem;">
                Filter Records
            </button>
            
            <?php if (!empty($search) || !empty($category_filter)): ?>
                <a href="other_payments_list.php" style="padding: 9px 15px; background-color: #f1f5f9; color: #64748b; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.9rem;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Payments Table -->
    <div style="background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
            <thead>
                <tr style="background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569;">
                    <th style="padding: 12px 15px;">Date</th>
                    <th style="padding: 12px 15px;">Student</th>
                    <th style="padding: 12px 15px;">Payment Purpose</th>
                    <th style="padding: 12px 15px;">Payer Name</th>
                    <th style="padding: 12px 15px;">Method</th>
                    <th style="padding: 12px 15px; text-align: right;">Amount (TZS)</th>
                    <th style="padding: 12px 15px;">Remarks</th>
                    <th style="padding: 12px 15px; text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($records) > 0): ?>
                    <?php foreach ($records as $row): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; color: #334155;">
                            <td style="padding: 12px 15px; white-space: nowrap;"><?= htmlspecialchars(date('d M Y', strtotime($row['payment_date']))); ?></td>
                            <td style="padding: 12px 15px;">
                                <strong><?= htmlspecialchars($row['full_name']); ?></strong><br>
                                <small style="color: #64748b;"><?= htmlspecialchars($row['admission_no']); ?></small>
                            </td>
                            <td style="padding: 12px 15px;">
                                <span style="background: #f1f5f9; color: #0f172a; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 0.82rem;">
                                    <?= htmlspecialchars($row['payment_category']); ?>
                                </span>
                            </td>
                            <td style="padding: 12px 15px; color: #64748b;">
                                <?= !empty($row['payer_name']) ? htmlspecialchars($row['payer_name']) : '—'; ?>
                            </td>
                            <td style="padding: 12px 15px; color: #475569;"><?= htmlspecialchars($row['payment_method']); ?></td>
                            <td style="padding: 12px 15px; text-align: right; font-weight: 700; color: #059669;">
                                <?= number_format((float)$row['amount_paid'], 2); ?>
                            </td>
                            <td style="padding: 12px 15px; color: #64748b; max-width: 180px;">
                                <?= !empty($row['remarks']) ? htmlspecialchars($row['remarks']) : '—'; ?>
                            </td>
                            <td style="padding: 12px 15px; text-align: center; white-space: nowrap;">
                                <a href="receipt.php?id=<?= (int)$row['payment_id']; ?>" target="_blank" style="padding: 6px 10px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 0.8rem;">
                                    🖨️ Receipt
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="padding: 30px; text-align: center; color: #94a3b8;">
                            No auxiliary payment records found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php require_once "../includes/footer.php"; ?>