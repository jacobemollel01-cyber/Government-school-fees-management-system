<?php
// Clear output buffers to ensure clean exports/AJAX responses
if ((isset($_GET['ajax_search']) || isset($_GET['export'])) && ob_get_length()) {
    ob_clean();
}

require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";

// ---------------------------------------------------------------------
// 1. EXCEL EXPORT HANDLER (CSV / Spreadsheet Stream)
// ---------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $exp_student_id = (int)($_GET['student_id'] ?? 0);
    if ($exp_student_id > 0) {
        // Fetch Student Info for Filename
        $s_stmt = $conn->prepare("SELECT full_name, admission_no FROM students WHERE student_id = ?");
        $s_stmt->bind_param("i", $exp_student_id);
        $s_stmt->execute();
        $s_res = $s_stmt->get_result();
        $s_data = $s_res->fetch_assoc();
        $s_stmt->close();

        if ($s_data) {
            $filename = "Financial_Statement_" . preg_replace('/[^a-zA-Z0-9]/', '_', $s_data['admission_no']) . ".xls";
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            // Fetch Payments for Excel
            $p_stmt = $conn->prepare("SELECT * FROM payments WHERE student_id = ? AND status = 'Active' ORDER BY payment_date DESC");
            $p_stmt->bind_param("i", $exp_student_id);
            $p_stmt->execute();
            $p_res = $p_stmt->get_result();

            echo "Student Financial Statement\t\t\t\t\n";
            echo "Name:\t" . $s_data['full_name'] . "\tAdmission No:\t" . $s_data['admission_no'] . "\t\n\n";
            echo "Date\tPurpose / Category\tPayer Name\tMethod\tAmount (TZS)\n";

            while ($row = $p_res->fetch_assoc()) {
                $category = $row['payment_category'] ?? ($row['contribution_type_id'] == 1 ? 'Food Fee' : ($row['contribution_type_id'] == 2 ? 'Hostel Fee' : 'General Fee'));
                $date = $row['payment_date'] ?? $row['created_at'] ?? '';
                $payer = $row['payer_name'] ?? '—';
                $method = $row['payment_method'] ?? 'Cash';
                $amount = $row['amount'] ?? $row['amount_paid'] ?? 0;
                
                echo "$date\t$category\t$payer\t$method\t$amount\n";
            }
            $p_stmt->close();
            exit;
        }
    }
}

// ---------------------------------------------------------------------
// 2. AJAX ENDPOINT FOR SMART LIVE SEARCH (STRICT PREFIX MATCHING)
// ---------------------------------------------------------------------
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json; charset=utf-8');
    $query = trim($_GET['ajax_search'] ?? '');
    $class_filter = trim($_GET['class'] ?? '');

    $results = [];
    try {
        if (!isset($conn) || !($conn instanceof mysqli)) {
            echo json_encode(['error' => 'Database connection unavailable.']);
            exit;
        }

        if (strlen($query) >= 1 || !empty($class_filter)) {
            $sql = "SELECT s.student_id, s.full_name, s.admission_no, s.class_name FROM students s WHERE 1=1";
            $params = [];
            $types = "";

            if (!empty($query)) {
                $sql .= " AND (s.full_name LIKE ? OR s.admission_no LIKE ?)";
                $name_param = "{$query}%"; 
                $adm_param = "%{$query}%";
                $params[] = $name_param;
                $params[] = $adm_param;
                $types .= "ss";
            }

            if (!empty($class_filter)) {
                $sql .= " AND s.class_name = ?";
                $params[] = $class_filter;
                $types .= "s";
            }

            $sql .= " ORDER BY s.full_name ASC LIMIT 15";

            $stmt = $conn->prepare($sql);
            if ($stmt) {
                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $res = $stmt->get_result();

                while ($row = $res->fetch_assoc()) {
                    $results[] = $row;
                }
                $stmt->close();
            }
        }
        echo json_encode($results);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Database search error occurred.']);
    }
    exit;
}

require_once "../includes/header.php";

// ---------------------------------------------------------------------
// 3. MAIN PAGE REPORT RENDERING & STRICT THRESHOLD EVALUATION LOGIC
// ---------------------------------------------------------------------
$student_id = (int)($_GET['student_id'] ?? 0);
$search_query = trim($_GET['search_query'] ?? '');
$class_filter_main = trim($_GET['class'] ?? '');

if ($student_id === 0 && !empty($search_query)) {
    $stmt = $conn->prepare("SELECT student_id FROM students WHERE full_name LIKE ? OR admission_no LIKE ? LIMIT 1");
    $param = "{$search_query}%";
    $stmt->bind_param("ss", $param, $param);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $student_id = (int)$row['student_id'];
    }
    $stmt->close();
}

$student = null;
$payments = [];
$total_food_paid = 0.00;
$total_hostel_paid = 0.00;
$total_maize = 0.00;
$total_beans = 0.00;

// Strict Threshold Defaults
$food_fee_target = 0.00;
$hostel_fee_target = 0.00;
$maize_quota = 0.00;
$beans_quota = 0.00;
$academic_year = date('Y');
$threshold_configured = false;

if ($student_id > 0) {
    $stmt = $conn->prepare("SELECT s.*, se.academic_year, se.class_id FROM students s LEFT JOIN student_enrollments se ON s.student_id = se.student_id WHERE s.student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student_res = $stmt->get_result();
    if ($student_res->num_rows > 0) {
        $student = $student_res->fetch_assoc();
        $academic_year = $student['academic_year'] ?? date('Y');
    }
    $stmt->close();

    if ($student) {
        if (!empty($student['class_id'])) {
            $t_stmt = $conn->prepare("
                SELECT food_fee_annual, hostel_fee_monthly, maize_quota_kg, beans_quota_kg 
                FROM fee_thresholds 
                WHERE class_id = ? AND academic_year = ? 
                LIMIT 1
            ");
            $t_stmt->bind_param("is", $student['class_id'], $academic_year);
            $t_stmt->execute();
            $t_res = $t_stmt->get_result();
            if ($t_res->num_rows > 0) {
                $t_row = $t_res->fetch_assoc();
                $food_fee_target   = (float)($t_row['food_fee_annual'] ?? 0);
                $hostel_fee_target = (float)($t_row['hostel_fee_monthly'] ?? 0);
                $maize_quota       = (float)($t_row['maize_quota_kg'] ?? 0);
                $beans_quota       = (float)($t_row['beans_quota_kg'] ?? 0);
                $threshold_configured = true;
            }
            $t_stmt->close();
        }

        $p_stmt = $conn->prepare("SELECT * FROM payments WHERE student_id = ? AND status = 'Active' ORDER BY payment_date DESC, payment_id DESC");
        $p_stmt->bind_param("i", $student_id);
        $p_stmt->execute();
        $p_res = $p_stmt->get_result();

        while ($row = $p_res->fetch_assoc()) {
            if ((int)$row['contribution_type_id'] === 1) {
                $total_food_paid += (float)$row['amount'];
            } elseif ((int)$row['contribution_type_id'] === 2) {
                $total_hostel_paid += (float)$row['amount'];
            }
            $payments[] = $row;
        }
        $p_stmt->close();

        $fc_stmt = $conn->prepare("SELECT * FROM food_contributions WHERE student_id = ? AND status = 'Active' ORDER BY contribution_date DESC");
        $fc_stmt->bind_param("i", $student_id);
        $fc_stmt->execute();
        $fc_res = $fc_stmt->get_result();
        while ($fc_row = $fc_res->fetch_assoc()) {
            if ((int)$fc_row['contribution_type_id'] === 3) {
                $total_maize += (float)$fc_row['quantity'];
            } elseif ((int)$fc_row['contribution_type_id'] === 4) {
                $total_beans += (float)$fc_row['quantity'];
            }
        }
        $fc_stmt->close();
    }
}

$class_query = $conn->query("SELECT DISTINCT class_name FROM students WHERE class_name IS NOT NULL AND class_name != '' ORDER BY class_name ASC");
?>

<style>
@media print {
    .no-print { display: none !important; }
    body { background: #ffffff; color: #000; }
    .page-wrapper { max-width: 100% !important; margin: 0 !important; padding: 0 !important; }
    .panel-card { border: none !important; box-shadow: none !important; }
}

:root {
    --primary: #4f46e5;
    --primary-hover: #4338ca;
    --success: #059669;
    --warning: #d97706;
    --danger: #dc2626;
    --bg-app: #f1f5f9;
    --bg-card: #ffffff;
    --border-subtle: #e2e8f0;
    --border-main: #cbd5e1;
    --text-dark: #0f172a;
    --text-muted: #64748b;
}

body {
    background-color: var(--bg-app);
    color: var(--text-dark);
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    -webkit-font-smoothing: antialiased;
}

/* Polished Page Layout Shell */
.page-wrapper {
    max-width: 1200px;
    margin: 32px auto;
    padding: 0 24px;
}

.app-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bg-card);
    border: 1px solid var(--border-subtle);
    padding: 20px 28px;
    border-radius: 14px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
    margin-bottom: 24px;
}

.panel-card {
    background: var(--bg-card);
    border: 1px solid var(--border-subtle);
    border-radius: 16px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.04), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
    padding: 32px;
    margin-bottom: 24px;
}

/* Clean Modern Search Bar Container */
.search-box-group {
    position: relative;
    background: #ffffff;
    border: 2px solid var(--border-main);
    border-radius: 12px;
    padding: 8px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    transition: all 0.2s ease;
}

.search-box-group:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
}

.search-input-field {
    flex: 2;
    border: none;
    outline: none;
    font-size: 0.98rem;
    background: transparent;
    color: var(--text-dark);
    padding: 6px 0;
}

.class-select-dropdown {
    flex: 1;
    min-width: 150px;
    border: none;
    border-left: 1px solid var(--border-subtle);
    outline: none;
    font-size: 0.92rem;
    background: transparent;
    color: #475569;
    padding-left: 14px;
    cursor: pointer;
}

.suggestions-dropdown {
    position: absolute;
    top: calc(100% + 10px);
    left: 0;
    right: 0;
    background: #ffffff;
    border: 1px solid var(--border-main);
    border-radius: 10px;
    box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.1);
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
}

.suggestion-row {
    padding: 12px 16px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.92rem;
    transition: background 0.15s ease, color 0.15s ease;
}

.suggestion-row:hover {
    background: #eef2ff;
    color: var(--primary);
}

.suggestion-row:last-child {
    border-bottom: none;
}

.btn-primary-action {
    padding: 10px 20px;
    background-color: var(--primary);
    color: #ffffff;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.92rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: background 0.2s ease;
    box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);
}

.btn-primary-action:hover {
    background-color: var(--primary-hover);
}

.btn-subtle-action {
    background-color: #f8fafc;
    color: #475569;
    border: 1px solid var(--border-subtle);
    box-shadow: none;
}

.btn-subtle-action:hover {
    background-color: #f1f5f9;
    color: var(--text-dark);
}

.metrics-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}

.metric-card-item {
    background: #f8fafc;
    border: 1px solid var(--border-subtle);
    padding: 20px;
    border-radius: 12px;
    text-align: center;
}

.data-table-clean {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 0.92rem;
}

.data-table-clean th {
    background-color: #f8fafc;
    color: #475569;
    padding: 14px 16px;
    border-bottom: 2px solid var(--border-subtle);
    font-weight: 600;
}

.data-table-clean td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
}
</style>

<div class="page-wrapper">

    <!-- Polished App Header Bar -->
    <div class="app-header-bar no-print">
        <div>
            <h2 style="margin: 0; color: var(--text-dark); font-size: 1.7rem; font-weight: 700; letter-spacing: -0.02em;">Student Financial Portal</h2>
            <p style="margin: 4px 0 0 0; color: var(--text-muted); font-size: 0.9rem;">Strict threshold evaluations & live prefix filtering</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="index.php" class="btn-primary-action btn-subtle-action">← Records List</a>
            <a href="../admin/admin dashboard.php" class="btn-primary-action">Dashboard</a>
        </div>
    </div>

    <!-- Clean Smart Search Component Card -->
    <div class="panel-card no-print" style="padding: 22px;">
        <form method="GET" action="student_report.php" id="searchForm">
            <div class="search-box-group">
                <span style="color: var(--text-muted); font-size: 1.1rem;">🔍</span>
                <input type="text" id="smartSearchInput" name="search_query" autocomplete="off" placeholder="Type name starting letters (e.g., 'J' for John)..." value="<?= $student ? htmlspecialchars($student['full_name']) : htmlspecialchars($search_query); ?>" class="search-input-field">
                
                <select id="classFilterSelect" name="class" class="class-select-dropdown">
                    <option value="">All Classes</option>
                    <?php if ($class_query && $class_query->num_rows > 0): ?>
                        <?php while ($c = $class_query->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($c['class_name']); ?>" <?= $class_filter_main === $c['class_name'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($c['class_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>

                <input type="hidden" name="student_id" id="selectedStudentId" value="<?= $student_id; ?>">
                
                <button type="submit" id="triggerSearchBtn" class="btn-primary-action" style="padding: 10px 18px; border-radius: 8px;">
                    Search
                </button>

                <?php if ($student_id > 0 || !empty($search_query)): ?>
                    <a href="student_report.php" class="btn-primary-action btn-subtle-action" style="padding: 10px 14px; border-radius: 8px;">Reset</a>
                <?php endif; ?>

                <div id="smartSuggestionsBox" class="suggestions-dropdown"></div>
            </div>
        </form>
    </div>

    <!-- Report Display Panel -->
    <?php if ($student): ?>
        <?php 
            if ($threshold_configured) {
                if ($food_fee_target > 0) {
                    if ($total_food_paid >= $food_fee_target) {
                        $food_status_text = 'Paid'; $food_status_color = '#059669';
                    } elseif ($total_food_paid > 0) {
                        $food_status_text = 'Partially Paid'; $food_status_color = '#d97706';
                    } else {
                        $food_status_text = 'Not Paid'; $food_status_color = '#dc2626';
                    }
                } else {
                    $food_status_text = 'No Target Set'; $food_status_color = '#64748b';
                }

                if ($hostel_fee_target > 0) {
                    if ($total_hostel_paid >= $hostel_fee_target) {
                        $hostel_status_text = 'Paid'; $hostel_status_color = '#059669';
                    } elseif ($total_hostel_paid > 0) {
                        $hostel_status_text = 'Partially Paid'; $hostel_status_color = '#d97706';
                    } else {
                        $hostel_status_text = 'Not Paid'; $hostel_status_color = '#dc2626';
                    }
                } else {
                    $hostel_status_text = 'No Target Set'; $hostel_status_color = '#64748b';
                }

                $total_produce_paid = $total_maize + $total_beans;
                $total_produce_quota = $maize_quota + $beans_quota;
                if ($total_produce_quota > 0) {
                    if ($total_maize >= $maize_quota && $total_beans >= $beans_quota) {
                        $produce_status_text = 'Paid'; $produce_status_color = '#059669';
                    } elseif ($total_produce_paid > 0) {
                        $produce_status_text = 'Partially Paid'; $produce_status_color = '#d97706';
                    } else {
                        $produce_status_text = 'Not Paid'; $produce_status_color = '#dc2626';
                    }
                } else {
                    $produce_status_text = 'No Quota Set'; $produce_status_color = '#64748b';
                }
            } else {
                $food_status_text = $hostel_status_text = $produce_status_text = 'Threshold Not Configured';
                $food_status_color = $hostel_status_color = $produce_status_color = '#d97706';
            }
        ?>

        <div class="panel-card" style="padding: 40px;">
            
            <!-- Action Toolbar (Export & Print) -->
            <div class="no-print" style="display: flex; justify-content: flex-end; gap: 10px; margin-bottom: 24px;">
                <a href="student_report.php?student_id=<?= $student_id; ?>&export=excel" class="btn-primary-action" style="background-color: #10b981;">
                    📊 Export to Excel
                </a>
                <button onclick="window.print()" class="btn-primary-action" style="background-color: #0ea5e9;">
                    🖨️ Print Statement
                </button>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 28px;">
                <div>
                    <h3 style="margin: 0; color: var(--text-dark); font-size: 1.5rem; font-weight: 700;">Official Student Financial Statement</h3>
                    <p style="margin: 4px 0 0 0; color: var(--text-muted); font-size: 0.9rem;">Strictly Evaluated Against Class Fee Thresholds</p>
                </div>
                <div style="text-align: right; font-size: 0.88rem; color: var(--text-muted);">
                    <strong>Issued Date:</strong> <?= date('d M Y'); ?><br>
                    <strong>Academic Year:</strong> <?= htmlspecialchars($academic_year); ?>
                </div>
            </div>

            <!-- Profile Summary Box -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 18px; background: #f8fafc; padding: 22px; border-radius: 12px; margin-bottom: 30px; border: 1px solid #f1f5f9;">
                <div>
                    <span style="font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Student Name</span>
                    <p style="margin: 6px 0 0 0; font-weight: 700; color: var(--text-dark); font-size: 1.1rem;"><?= htmlspecialchars($student['full_name']); ?></p>
                </div>
                <div>
                    <span style="font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Admission Number</span>
                    <p style="margin: 6px 0 0 0; font-weight: 600; color: #334155; font-size: 1rem;"><?= htmlspecialchars($student['admission_no']); ?></p>
                </div>
                <?php if (!empty($student['class_name'])): ?>
                <div>
                    <span style="font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Class / Stream</span>
                    <p style="margin: 6px 0 0 0; font-weight: 600; color: #334155; font-size: 1rem;"><?= htmlspecialchars($student['class_name']); ?></p>
                </div>
                <?php endif; ?>
                <div>
                    <span style="font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Account Status</span>
                    <p style="margin: 6px 0 0 0; font-weight: 700; font-size: 1rem; color: <?= ($student['status'] ?? 'Active') === 'Active' ? '#059669' : '#dc2626'; ?>;"><?= htmlspecialchars($student['status'] ?? 'Active'); ?></p>
                </div>
            </div>

            <!-- Threshold Evaluation Metrics Grid -->
            <div class="metrics-row">
                <div class="metric-card-item">
                    <span style="font-size: 0.85rem; color: #475569; font-weight: 600;">Food Fee Evaluation</span>
                    <h4 style="margin: 10px 0 4px 0; color: #0f172a; font-size: 1.2rem;">TZS <?= number_format($total_food_paid, 2); ?></h4>
                    <small style="color: var(--text-muted);">Threshold Target: TZS <?= number_format($food_fee_target, 2); ?></small>
                    <div style="margin-top: 10px;">
                        <span style="display: inline-block; padding: 4px 12px; font-size: 0.78rem; font-weight: 700; color: #fff; background: <?= $food_status_color; ?>; border-radius: 6px;"><?= $food_status_text; ?></span>
                    </div>
                </div>

                <div class="metric-card-item">
                    <span style="font-size: 0.85rem; color: #475569; font-weight: 600;">Hostel Fee Evaluation</span>
                    <h4 style="margin: 10px 0 4px 0; color: #0f172a; font-size: 1.2rem;">TZS <?= number_format($total_hostel_paid, 2); ?></h4>
                    <small style="color: var(--text-muted);">Threshold Target: TZS <?= number_format($hostel_fee_target, 2); ?></small>
                    <div style="margin-top: 10px;">
                        <span style="display: inline-block; padding: 4px 12px; font-size: 0.78rem; font-weight: 700; color: #fff; background: <?= $hostel_status_color; ?>; border-radius: 6px;"><?= $hostel_status_text; ?></span>
                    </div>
                </div>

                <div class="metric-card-item">
                    <span style="font-size: 0.85rem; color: #475569; font-weight: 600;">Maize & Beans Evaluation</span>
                    <h4 style="margin: 10px 0 4px 0; color: #0f172a; font-size: 1.05rem;"><?= number_format($total_maize, 1); ?>kg / <?= number_format($total_beans, 1); ?>kg</h4>
                    <small style="color: var(--text-muted);">Quota Requirement: <?= number_format($maize_quota, 1); ?>kg / <?= number_format($beans_quota, 1); ?>kg</small>
                    <div style="margin-top: 10px;">
                        <span style="display: inline-block; padding: 4px 12px; font-size: 0.78rem; font-weight: 700; color: #fff; background: <?= $produce_status_color; ?>; border-radius: 6px;"><?= $produce_status_text; ?></span>
                    </div>
                </div>
            </div>

            <!-- Transaction History Log -->
            <h4 style="color: var(--text-dark); font-size: 1.15rem; margin-bottom: 14px;">Payment Transactions History</h4>
            <div style="border: 1px solid var(--border-subtle); border-radius: 12px; overflow: hidden; margin-bottom: 35px;">
                <table class="data-table-clean">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Purpose / Category</th>
                            <th>Payer / Depositor</th>
                            <th>Method</th>
                            <th style="text-align: right;">Amount Paid (TZS)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($payments) > 0): ?>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td style="white-space: nowrap;"><?= date('d M Y', strtotime($p['payment_date'] ?? $p['created_at'] ?? 'now')); ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($p['payment_category'] ?? ($p['contribution_type_id'] == 1 ? 'Food Fee' : ($p['contribution_type_id'] == 2 ? 'Hostel Fee' : 'General Fee'))); ?></strong>
                                        <?php if (!empty($p['remarks'])): ?>
                                            <br><small style="color: var(--text-muted);"><?= htmlspecialchars($p['remarks']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color: var(--text-muted);"><?= !empty($p['payer_name']) ? htmlspecialchars($p['payer_name']) : '—'; ?></td>
                                    <td><?= htmlspecialchars($p['payment_method'] ?? 'Cash'); ?></td>
                                    <td style="text-align: right; font-weight: 700; color: #059669;">
                                        <?= number_format((float)($p['amount'] ?? $p['amount_paid'] ?? 0), 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="padding: 35px; text-align: center; color: var(--text-muted);">
                                    No financial transaction records found for this student.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Signatures Section -->
            <div style="display: flex; justify-content: space-between; margin-top: 50px; padding-top: 20px; border-top: 1px dashed var(--border-main); font-size: 0.88rem; color: var(--text-muted);">
                <div>
                    <p style="margin: 0;">Accounts / Bursar Signature: _______________________</p>
                </div>
                <div>
                    <p style="margin: 0;">Official School Stamp: _______________________</p>
                </div>
            </div>

        </div>
    <?php else: ?>
        <div class="panel-card" style="padding: 60px; text-align: center; color: var(--text-muted);">
            <p style="margin: 0; font-size: 1.1rem; color: #475569;">🔍 Type starting letters above (e.g., <strong>"J"</strong>) to filter names strictly, select a student from the suggestion popup, or click <strong>Search</strong>.</p>
        </div>
    <?php endif; ?>

</div>

<!-- JavaScript Engine for Prefix Matching and Error Handling -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('smartSearchInput');
    const hiddenIdInput = document.getElementById('selectedStudentId');
    const box = document.getElementById('smartSuggestionsBox');
    const classSelect = document.getElementById('classFilterSelect');
    const searchForm = document.getElementById('searchForm');
    let debounceTimer = null;

    function fetchSuggestions(callback) {
        const queryVal = input.value.trim();
        const selectedClass = classSelect.value;

        if (queryVal.length < 1 && selectedClass === '') {
            box.style.display = 'none';
            if (callback) callback([]);
            return;
        }

        box.innerHTML = '<div class="suggestion-row" style="color: #64748b; cursor: default; text-align: center;">Searching names starting with "' + escapeHtml(queryVal) + '"...</div>';
        box.style.display = 'block';

        fetch(`student_report.php?ajax_search=${encodeURIComponent(queryVal)}&class=${encodeURIComponent(selectedClass)}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response failure');
                return response.json();
            })
            .then(data => {
                box.innerHTML = '';
                
                if (data.error) {
                    box.innerHTML = `<div class="suggestion-row" style="color: #dc2626; cursor: default;">⚠️ ${escapeHtml(data.error)}</div>`;
                    return;
                }

                if (!Array.isArray(data) || data.length === 0) {
                    box.innerHTML = '<div class="suggestion-row" style="color: #94a3b8; cursor: default;">No students found starting with that prefix</div>';
                } else {
                    data.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'suggestion-row';
                        const classText = item.class_name ? ` (${item.class_name})` : '';
                        div.innerHTML = `<strong style="color: #0f172a;">${escapeHtml(item.full_name)}</strong> <span style="font-size: 0.85rem; color: #64748b;">— Adm: ${escapeHtml(item.admission_no)}${escapeHtml(classText)}</span>`;
                        
                        div.addEventListener('click', function() {
                            hiddenIdInput.value = item.student_id;
                            input.value = item.full_name;
                            box.style.display = 'none';
                            window.location.href = `student_report.php?student_id=${item.student_id}`;
                        });
                        box.appendChild(div);
                    });
                }
                box.style.display = 'block';
                if (callback) callback(data);
            })
            .catch(err => {
                console.error('Search Error:', err);
                box.innerHTML = '<div class="suggestion-row" style="color: #dc2626; cursor: default;">⚠️ Error fetching suggestions. Please try again.</div>';
                box.style.display = 'block';
                if (callback) callback([]);
            });
    }

    input.addEventListener('input', function() {
        hiddenIdInput.value = ''; 
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchSuggestions(), 250);
    });

    input.addEventListener('focus', function() {
        if (input.value.trim().length >= 1) {
            fetchSuggestions();
        }
    });

    classSelect.addEventListener('change', function() {
        fetchSuggestions();
    });

    searchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const queryVal = input.value.trim();

        if (hiddenIdInput.value && parseInt(hiddenIdInput.value) > 0) {
            window.location.href = `student_report.php?student_id=${hiddenIdInput.value}`;
            return;
        }

        fetchSuggestions(function(data) {
            if (data && Array.isArray(data) && data.length > 0) {
                window.location.href = `student_report.php?student_id=${data[0].student_id}`;
            } else if (queryVal !== '') {
                window.location.href = `student_report.php?search_query=${encodeURIComponent(queryVal)}`;
            }
        });
    });

    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !box.contains(e.target)) {
            box.style.display = 'none';
        }
    });

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
});
</script>

<?php require_once "../includes/footer.php"; ?>