<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";

/*
|--------------------------------------------------------------------------
| AJAX Endpoint: Fetch Student Fee Requirements & Balance
|--------------------------------------------------------------------------
*/
if (isset($_GET['action']) && $_GET['action'] === 'get_student_balance') {
    header('Content-Type: application/json; charset=utf-8');
    $sid = (int)($_GET['student_id'] ?? 0);
    $ctid = (int)($_GET['contribution_type_id'] ?? 0);

    if ($sid <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid student ID.']);
        exit;
    }

    // Total required
    $due_sql = "SELECT COALESCE(SUM(amount_due), 0) AS total_due FROM student_requirements WHERE student_id = ?";
    if ($ctid > 0) {
        $due_sql .= " AND contribution_type_id = ?";
    }
    $due_stmt = $conn->prepare($due_sql);
    if ($ctid > 0) {
        $due_stmt->bind_param("ii", $sid, $ctid);
    } else {
        $due_stmt->bind_param("i", $sid);
    }
    $due_stmt->execute();
    $total_due = (float)($due_stmt->get_result()->fetch_assoc()['total_due'] ?? 0);
    $due_stmt->close();

    // Total paid
    $paid_sql = "SELECT COALESCE(SUM(amount), 0) AS total_paid FROM payments WHERE student_id = ? AND status = 'Active'";
    if ($ctid > 0) {
        $paid_sql .= " AND contribution_type_id = ?";
    }
    $paid_stmt = $conn->prepare($paid_sql);
    if ($ctid > 0) {
        $paid_stmt->bind_param("ii", $sid, $ctid);
    } else {
        $paid_stmt->bind_param("i", $sid);
    }
    $paid_stmt->execute();
    $total_paid = (float)($paid_stmt->get_result()->fetch_assoc()['total_paid'] ?? 0);
    $paid_stmt->close();

    $balance = max(0, $total_due - $total_paid);

    echo json_encode([
        'success'    => true,
        'total_due'  => $total_due,
        'total_paid' => $total_paid,
        'balance'    => $balance
    ]);
    exit;
}

$message = "";
$message_type = "";
$last_payment = null;

/*
|--------------------------------------------------------------------------
| Load Active Classes
|--------------------------------------------------------------------------
*/
$classes = [];
$classes_sql = "
    SELECT class_id, class_name
    FROM classes
    WHERE is_active = TRUE
    ORDER BY class_id ASC
";
$classes_result = $conn->query($classes_sql);
if ($classes_result) {
    while ($row = $classes_result->fetch_assoc()) {
        $classes[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Load Active Streams
|--------------------------------------------------------------------------
*/
$streams = [];
$streams_sql = "
    SELECT stream_id, stream_name
    FROM streams
    WHERE is_active = TRUE
    ORDER BY stream_id ASC
";
$streams_result = $conn->query($streams_sql);
if ($streams_result) {
    while ($row = $streams_result->fetch_assoc()) {
        $streams[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Load Active Students (Joined with latest enrollment to avoid duplicates)
|--------------------------------------------------------------------------
*/
$students = [];
$students_sql = "
    SELECT
        s.student_id,
        s.admission_no,
        s.full_name,
        COALESCE(c.class_id, 0) AS class_id,
        COALESCE(c.class_name, s.class_name, '') AS class_name,
        COALESCE(st.stream_id, 0) AS stream_id,
        COALESCE(st.stream_name, s.stream, '') AS stream_name
    FROM students s
    LEFT JOIN student_enrollments se
        ON s.student_id = se.student_id
        AND se.academic_year = (
            SELECT MAX(se2.academic_year)
            FROM student_enrollments se2
            WHERE se2.student_id = s.student_id
        )
    LEFT JOIN classes c
        ON se.class_id = c.class_id
    LEFT JOIN streams st
        ON se.stream_id = st.stream_id
    WHERE s.status = 'Active'
    ORDER BY
        c.class_id ASC,
        st.stream_id ASC,
        s.full_name ASC
";
$students_result = $conn->query($students_sql);
if ($students_result) {
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Load Food and Hostel Contribution Types
|--------------------------------------------------------------------------
*/
$contribution_types = [];
$types_sql = "
    SELECT contribution_type_id, name
    FROM contribution_types
    WHERE is_active = TRUE
      AND name IN ('Food', 'Hostel')
    ORDER BY FIELD(name, 'Food', 'Hostel')
";
$types_result = $conn->query($types_sql);
if ($types_result) {
    while ($row = $types_result->fetch_assoc()) {
        $contribution_types[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Load Active Sponsorships with Calculated Balances
|--------------------------------------------------------------------------
*/
$sponsorships = [];
$sponsorships_sql = "
    SELECT
        sp.sponsorship_id,
        sp.sponsor_id,
        sp.student_id,
        sp.contribution_type_id,
        sp.academic_year,
        sp.amount_committed,
        s.sponsor_name,
        st.admission_no,
        st.full_name,
        ct.name AS contribution_name,
        COALESCE(SUM(p.amount), 0) AS total_paid,
        (sp.amount_committed - COALESCE(SUM(p.amount), 0)) AS remaining_balance
    FROM sponsorships sp
    INNER JOIN sponsors s
        ON sp.sponsor_id = s.sponsor_id
    INNER JOIN students st
        ON sp.student_id = st.student_id
    INNER JOIN contribution_types ct
        ON sp.contribution_type_id = ct.contribution_type_id
    LEFT JOIN payments p
        ON p.sponsorship_id = sp.sponsorship_id
        AND p.status = 'Active'
    WHERE s.is_active = TRUE
      AND st.status = 'Active'
      AND ct.name IN ('Food', 'Hostel')
    GROUP BY
        sp.sponsorship_id,
        sp.sponsor_id,
        sp.student_id,
        sp.contribution_type_id,
        sp.academic_year,
        sp.amount_committed,
        s.sponsor_name,
        st.admission_no,
        st.full_name,
        ct.name
    ORDER BY
        s.sponsor_name ASC,
        st.full_name ASC,
        ct.name ASC
";
$sponsorships_result = $conn->query($sponsorships_sql);
if ($sponsorships_result) {
    while ($row = $sponsorships_result->fetch_assoc()) {
        $sponsorships[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Handle Form Submission
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $payment_source = trim($_POST["payment_source"] ?? "Parent / Student");
    $student_id = (int)($_POST["student_id"] ?? 0);
    $contribution_type_id = (int)($_POST["contribution_type_id"] ?? 0);
    $sponsorship_id = !empty($_POST["sponsorship_id"]) ? (int)$_POST["sponsorship_id"] : null;
    $amount = (float)($_POST["amount"] ?? 0);
    $payment_date = trim($_POST["payment_date"] ?? "");
    $payment_period = trim($_POST["payment_period"] ?? "");
    $payment_method = trim($_POST["payment_method"] ?? "");
    $payment_reference = trim($_POST["payment_reference"] ?? "");
    $payer_name = trim($_POST["payer_name"] ?? "");
    $payer_type = trim($_POST["payer_type"] ?? "");
    $notes = trim($_POST["notes"] ?? "");

    // User recording this transaction
    $recorded_by = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin';
    $sponsor_id = null;

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */
    if ($payment_source !== "Parent / Student" && $payment_source !== "Sponsor") {
        $message = "Invalid payment source.";
        $message_type = "error";
    } elseif ($amount <= 0) {
        $message = "Payment amount must be greater than zero.";
        $message_type = "error";
    } elseif ($payment_date === "") {
        $message = "Please select the payment date.";
        $message_type = "error";
    } elseif ($payment_method === "") {
        $message = "Please select a payment method.";
        $message_type = "error";
    } elseif (in_array($payment_method, ["Bank", "Mobile Money"], true) && $payment_reference === "") {
        $message = "Payment reference number is required for " . htmlspecialchars($payment_method) . " payments.";
        $message_type = "error";
    } elseif ($payment_source === "Sponsor") {
        // SPONSOR PAYMENT SPECIFIC VALIDATION
        if (empty($sponsorship_id) || $sponsorship_id <= 0) {
            $message = "Please select an active sponsorship.";
            $message_type = "error";
        } else {
            $sponsor_sql = "
                SELECT
                    sp.sponsorship_id,
                    sp.sponsor_id,
                    sp.student_id,
                    sp.contribution_type_id,
                    sp.academic_year,
                    sp.amount_committed,
                    s.sponsor_name,
                    st.full_name,
                    ct.name AS contribution_name
                FROM sponsorships sp
                INNER JOIN sponsors s ON sp.sponsor_id = s.sponsor_id
                INNER JOIN students st ON sp.student_id = st.student_id
                INNER JOIN contribution_types ct ON sp.contribution_type_id = ct.contribution_type_id
                WHERE sp.sponsorship_id = ?
                  AND s.is_active = TRUE
                  AND st.status = 'Active'
                  AND ct.name IN ('Food', 'Hostel')
                LIMIT 1
            ";
            $sponsor_stmt = $conn->prepare($sponsor_sql);
            if (!$sponsor_stmt) {
                $message = "Database error: " . $conn->error;
                $message_type = "error";
            } else {
                $sponsor_stmt->bind_param("i", $sponsorship_id);
                $sponsor_stmt->execute();
                $sponsor_res = $sponsor_stmt->get_result();

                if ($sponsor_res->num_rows === 0) {
                    $message = "Selected sponsorship was not found or is no longer active.";
                    $message_type = "error";
                } else {
                    $sponsorship_row = $sponsor_res->fetch_assoc();
                    $student_id = (int)$sponsorship_row["student_id"];
                    $contribution_type_id = (int)$sponsorship_row["contribution_type_id"];
                    $sponsor_id = (int)$sponsorship_row["sponsor_id"];
                    $payer_name = $sponsorship_row["sponsor_name"];
                    $payer_type = "Sponsor";

                    // Check balance
                    $paid_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total_paid FROM payments WHERE sponsorship_id = ? AND status = 'Active'");
                    $paid_stmt->bind_param("i", $sponsorship_id);
                    $paid_stmt->execute();
                    $already_paid = (float)$paid_stmt->get_result()->fetch_assoc()["total_paid"];
                    $paid_stmt->close();

                    $committed = (float)$sponsorship_row["amount_committed"];
                    $remaining = $committed - $already_paid;

                    if ($remaining <= 0) {
                        $message = "This sponsorship has already been fully paid.";
                        $message_type = "error";
                    } elseif ($amount > $remaining) {
                        $message = "Payment exceeds the remaining sponsorship balance of " . number_format($remaining, 2) . " TZS.";
                        $message_type = "error";
                    }
                }
                $sponsor_stmt->close();
            }
        }
    } else {
        // PARENT / STUDENT SPECIFIC VALIDATION
        if ($student_id <= 0) {
            $message = "Please select a student.";
            $message_type = "error";
        } elseif ($contribution_type_id <= 0) {
            $message = "Please select a contribution type.";
            $message_type = "error";
        } elseif (!in_array($payer_type, ["Student", "Parent/Guardian", "Other"], true)) {
            $message = "Please select a valid payer type (Student, Parent/Guardian, or Other).";
            $message_type = "error";
        } elseif ($payer_name === "") {
            $message = "Please enter the payer name.";
            $message_type = "error";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Check Duplicate Payment Reference
    |--------------------------------------------------------------------------
    */
    if ($message === "" && $payment_reference !== "") {
        $reference_stmt = $conn->prepare("SELECT payment_id FROM payments WHERE payment_reference = ? AND status = 'Active' LIMIT 1");
        if ($reference_stmt) {
            $reference_stmt->bind_param("s", $payment_reference);
            $reference_stmt->execute();
            if ($reference_stmt->get_result()->num_rows > 0) {
                $message = "Payment reference '" . htmlspecialchars($payment_reference) . "' has already been recorded for an active payment.";
                $message_type = "error";
            }
            $reference_stmt->close();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Insert Payment within Database Transaction
    |--------------------------------------------------------------------------
    */
    if ($message === "") {
        $conn->begin_transaction();

        try {
            $sql = "
                INSERT INTO payments (
                    student_id,
                    sponsorship_id,
                    sponsor_id,
                    contribution_type_id,
                    amount,
                    payment_date,
                    payment_period,
                    payment_method,
                    payment_reference,
                    payer_name,
                    payer_type,
                    notes,
                    recorded_by,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')
            ";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Database prepare error: " . $conn->error);
            }

            $stmt->bind_param(
                "iiiidssssssss",
                $student_id,
                $sponsorship_id,
                $sponsor_id,
                $contribution_type_id,
                $amount,
                $payment_date,
                $payment_period,
                $payment_method,
                $payment_reference,
                $payer_name,
                $payer_type,
                $notes,
                $recorded_by
            );

            if (!$stmt->execute()) {
                if ($stmt->errno === 1062) {
                    throw new Exception("This payment reference has already been recorded.");
                }
                throw new Exception("Database execute error: " . $stmt->error);
            }

            $new_payment_id = $stmt->insert_id;
            $stmt->close();

            $conn->commit();

            $message = "Payment #" . $new_payment_id . " recorded successfully!";
            $message_type = "success";
            $last_payment = [
                'id'         => $new_payment_id,
                'amount'     => $amount,
                'payer'      => $payer_name,
                'reference'  => $payment_reference,
                'student_id' => $student_id
            ];

            // Reset form input values
            $_POST = [];

        } catch (Exception $e) {
            $conn->rollback();
            $message = $e->getMessage();
            $message_type = "error";
        }
    }
}

require_once "../includes/header.php";
?>

<div class="container">

    <div class="dashboard-header">
        <h1>Record Payment</h1>
        <p class="dashboard-subtitle">
            Record a Food or Hostel contribution received from a student, parent, guardian or sponsor.
        </p>
    </div>

    <div class="form-card">

        <?php if ($message !== ""): ?>
            <div class="form-message <?= $message_type === 'success' ? 'success-message' : 'error-message'; ?>">
                <strong><?= htmlspecialchars($message); ?></strong>

                <?php if ($last_payment): ?>
                    <div class="success-actions">
                        <a href="index.php" class="action-btn-small">← View Payments List</a>
                        <a href="index.php?search=<?= urlencode((string)$last_payment['reference'] ?: (string)$last_payment['id']); ?>" class="action-btn-small highlight">View This Record</a>
                        <a href="add.php" class="action-btn-small secondary">+ Record Another Payment</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="paymentForm" autocomplete="off">

            <div class="form-grid">

                <!-- Payment Source -->
                <div class="form-group full-width">
                    <label for="payment_source">Payment Source</label>
                    <select id="payment_source" name="payment_source" required>
                        <option value="Parent / Student" <?= ($_POST["payment_source"] ?? "") === "Parent / Student" ? "selected" : ""; ?>>
                            Parent / Student Payment
                        </option>
                        <option value="Sponsor" <?= ($_POST["payment_source"] ?? "") === "Sponsor" ? "selected" : ""; ?>>
                            Sponsor Payment
                        </option>
                    </select>
                    <small class="field-help">
                        Choose <strong>Sponsor Payment</strong> when the contribution is covered under a sponsor's allocation commitment.
                    </small>
                </div>

                <!-- =========================================================
                     SPONSORSHIP SELECTION (Sponsor Mode)
                ========================================================== -->
                <div class="form-group full-width" id="sponsorship_group" style="display:none;">
                    <label for="sponsorship_id">Select Sponsorship Commitment</label>
                    <select id="sponsorship_id" name="sponsorship_id">
                        <option value="">-- Choose Active Sponsorship --</option>
                        <?php foreach ($sponsorships as $sp): ?>
                            <?php
                            $sp_rem = (float)$sp["remaining_balance"];
                            $sp_is_full = ($sp_rem <= 0);
                            $selected = (isset($_POST["sponsorship_id"]) && (int)$_POST["sponsorship_id"] === (int)$sp["sponsorship_id"]) ? "selected" : "";
                            ?>
                            <option
                                value="<?= (int)$sp["sponsorship_id"]; ?>"
                                data-sponsor="<?= htmlspecialchars($sp["sponsor_name"], ENT_QUOTES); ?>"
                                data-student="<?= htmlspecialchars($sp["full_name"], ENT_QUOTES); ?>"
                                data-admission="<?= htmlspecialchars($sp["admission_no"], ENT_QUOTES); ?>"
                                data-type="<?= htmlspecialchars($sp["contribution_name"], ENT_QUOTES); ?>"
                                data-committed="<?= (float)$sp["amount_committed"]; ?>"
                                data-paid="<?= (float)$sp["total_paid"]; ?>"
                                data-remaining="<?= $sp_rem; ?>"
                                <?= $sp_is_full ? "disabled" : ""; ?>
                                <?= $selected; ?>
                            >
                                <?= htmlspecialchars(
                                    $sp["sponsor_name"] . " → " . $sp["admission_no"] . " (" . $sp["full_name"] . ") → " . $sp["contribution_name"] . " → Rem: " . number_format($sp_rem, 0) . " TZS" . ($sp_is_full ? " [FULLY PAID]" : "")
                                ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-help">
                        Displays active sponsorships and the current remaining balance.
                    </small>

                    <!-- Sponsorship Real-time Summary Card -->
                    <div id="sponsorship_summary_card" class="summary-badge-card" style="display:none;">
                        <div class="badge-title" id="sp_card_title">Sponsorship Summary</div>
                        <div class="badge-stats">
                            <div><span class="lbl">Committed:</span> <strong id="sp_card_committed">0 TZS</strong></div>
                            <div><span class="lbl">Already Paid:</span> <strong id="sp_card_paid">0 TZS</strong></div>
                            <div><span class="lbl">Remaining Balance:</span> <strong id="sp_card_remaining" class="text-gold">0 TZS</strong></div>
                        </div>
                        <button type="button" class="btn-quick-fill" id="btn_fill_sponsor_remaining">Pay Full Remaining Balance</button>
                    </div>
                </div>

                <!-- =========================================================
                     STUDENT SELECTION (Parent / Student Mode)
                ========================================================== -->

                <!-- Quick Search -->
                <div class="form-group full-width" id="student_search_group">
                    <label for="student_search">Quick Student Search</label>
                    <input
                        type="text"
                        id="student_search"
                        placeholder="Search by student name or admission number (e.g. 009, John, Mollel)..."
                    >
                    <small class="field-help">
                        Type here to quickly find and select a student across all classes without manually selecting filters.
                    </small>
                </div>

                <!-- Class Filter -->
                <div class="form-group" id="class_group">
                    <label for="class_id">Class Filter</label>
                    <select id="class_id" name="class_id">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option
                                value="<?= (int)$class["class_id"]; ?>"
                                <?= (isset($_POST["class_id"]) && (int)$_POST["class_id"] === (int)$class["class_id"]) ? "selected" : ""; ?>
                            >
                                <?= htmlspecialchars($class["class_name"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Stream Filter -->
                <div class="form-group" id="stream_group">
                    <label for="stream_id">Stream Filter (Optional)</label>
                    <select id="stream_id" name="stream_id">
                        <option value="">All Streams</option>
                        <?php foreach ($streams as $stream): ?>
                            <option
                                value="<?= (int)$stream["stream_id"]; ?>"
                                <?= (isset($_POST["stream_id"]) && (int)$_POST["stream_id"] === (int)$stream["stream_id"]) ? "selected" : ""; ?>
                            >
                                <?= htmlspecialchars($stream["stream_name"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Student Dropdown -->
                <div class="form-group full-width" id="student_group">
                    <label for="student_id">Select Student <span class="required-star">*</span></label>
                    <select id="student_id" name="student_id">
                        <option value="">-- Choose Student --</option>
                        <?php foreach ($students as $stu): ?>
                            <?php
                            $selected = (isset($_POST["student_id"]) && (int)$_POST["student_id"] === (int)$stu["student_id"]) ? "selected" : "";
                            ?>
                            <option
                                value="<?= (int)$stu["student_id"]; ?>"
                                data-class-id="<?= (int)$stu["class_id"]; ?>"
                                data-class-name="<?= htmlspecialchars($stu["class_name"], ENT_QUOTES); ?>"
                                data-stream-id="<?= (int)$stu["stream_id"]; ?>"
                                data-stream-name="<?= htmlspecialchars($stu["stream_name"], ENT_QUOTES); ?>"
                                data-admission="<?= htmlspecialchars($stu["admission_no"], ENT_QUOTES); ?>"
                                data-name="<?= htmlspecialchars($stu["full_name"], ENT_QUOTES); ?>"
                                <?= $selected; ?>
                            >
                                <?= htmlspecialchars($stu["admission_no"] . " — " . $stu["full_name"] . " (" . $stu["class_name"] . ($stu["stream_name"] ? " " . $stu["stream_name"] : "") . ")"); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Contribution Type -->
                <div class="form-group" id="contribution_group">
                    <label for="contribution_type_id">Contribution Type <span class="required-star">*</span></label>
                    <select id="contribution_type_id" name="contribution_type_id">
                        <option value="">-- Select Contribution --</option>
                        <?php foreach ($contribution_types as $type): ?>
                            <?php
                            $selected = (isset($_POST["contribution_type_id"]) && (int)$_POST["contribution_type_id"] === (int)$type["contribution_type_id"]) ? "selected" : "";
                            ?>
                            <option value="<?= (int)$type["contribution_type_id"]; ?>" <?= $selected; ?>>
                                <?= htmlspecialchars($type["name"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Student Live Balance Card -->
                <div class="form-group" id="student_balance_group">
                    <label>Current Student Balance</label>
                    <div id="student_balance_card" class="student-fee-card">
                        <div id="balance_loading" style="display:none; color:#6b7280; font-size:13px;">Calculating balance...</div>
                        <div id="balance_content">
                            <span class="bal-label">Select student & contribution type to view fee requirement & balance.</span>
                        </div>
                    </div>
                </div>

                <!-- =========================================================
                     PAYMENT DETAILS
                ========================================================== -->

                <!-- Amount -->
                <div class="form-group">
                    <label for="amount">Amount (TZS) <span class="required-star">*</span></label>
                    <input
                        type="number"
                        id="amount"
                        name="amount"
                        step="0.01"
                        min="0.01"
                        value="<?= htmlspecialchars($_POST["amount"] ?? ""); ?>"
                        placeholder="e.g. 300000"
                        required
                    >
                    <small class="field-help" id="amount_preview_text">Enter amount in Tanzanian Shillings.</small>
                </div>

                <!-- Payment Date -->
                <div class="form-group">
                    <label for="payment_date">Payment Date <span class="required-star">*</span></label>
                    <input
                        type="date"
                        id="payment_date"
                        name="payment_date"
                        value="<?= htmlspecialchars($_POST["payment_date"] ?? date("Y-m-d")); ?>"
                        required
                    >
                </div>

                <!-- Payment Period -->
                <div class="form-group">
                    <label for="payment_period">Payment Period</label>
                    <input
                        type="text"
                        id="payment_period"
                        name="payment_period"
                        value="<?= htmlspecialchars($_POST["payment_period"] ?? date("Y")); ?>"
                        placeholder="e.g. <?= date("Y"); ?>, Term 1, Term 2"
                    >
                    <small class="field-help">
                        Academic year or term this payment covers (defaults to <?= date("Y"); ?>).
                    </small>
                </div>

                <!-- Payment Method -->
                <div class="form-group">
                    <label for="payment_method">Payment Method <span class="required-star">*</span></label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="">-- Select Method --</option>
                        <?php
                        $methods = ["Cash", "Bank", "Mobile Money", "Other"];
                        foreach ($methods as $method):
                            $selected = (($_POST["payment_method"] ?? "") === $method) ? "selected" : "";
                        ?>
                            <option value="<?= $method; ?>" <?= $selected; ?>><?= $method; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Payment Reference -->
                <div class="form-group">
                    <label for="payment_reference">
                        Payment Reference <span id="ref_required_indicator" style="display:none; color:var(--gold); font-size:12px;">(Required)</span>
                    </label>
                    <input
                        type="text"
                        id="payment_reference"
                        name="payment_reference"
                        value="<?= htmlspecialchars($_POST["payment_reference"] ?? ""); ?>"
                        placeholder="e.g. BANK-987654 or MPESA-XYZ"
                    >
                    <small class="field-help" id="ref_help">
                        Enter bank deposit slip number, transaction ID, or mobile money code.
                    </small>
                </div>

                <!-- Payer Type -->
                <div class="form-group">
                    <label for="payer_type">Payer Type <span class="required-star">*</span></label>
                    <select id="payer_type" name="payer_type" required>
                        <option value="">-- Select Payer Type --</option>
                        <option value="Parent/Guardian" <?= (($_POST["payer_type"] ?? "Parent/Guardian") === "Parent/Guardian") ? "selected" : ""; ?>>Parent/Guardian</option>
                        <option value="Student" <?= (($_POST["payer_type"] ?? "") === "Student") ? "selected" : ""; ?>>Student</option>
                        <option value="Sponsor" <?= (($_POST["payer_type"] ?? "") === "Sponsor") ? "selected" : ""; ?>>Sponsor</option>
                        <option value="Other" <?= (($_POST["payer_type"] ?? "") === "Other") ? "selected" : ""; ?>>Other</option>
                    </select>
                </div>

                <!-- Payer Name -->
                <div class="form-group">
                    <label for="payer_name">Payer Name <span class="required-star">*</span></label>
                    <input
                        type="text"
                        id="payer_name"
                        name="payer_name"
                        value="<?= htmlspecialchars($_POST["payer_name"] ?? ""); ?>"
                        placeholder="e.g. Parent Name or Organization"
                        required
                    >
                    <small class="field-help" id="payer_help">
                        Name of the person or entity who made the deposit/payment.
                    </small>
                </div>

                <!-- Notes -->
                <div class="form-group full-width">
                    <label for="notes">Notes / Remarks</label>
                    <textarea
                        id="notes"
                        name="notes"
                        placeholder="Optional remarks, receipt references, or additional transaction details..."
                    ><?= htmlspecialchars($_POST["notes"] ?? ""); ?></textarea>
                </div>

            </div>

            <div class="form-actions">
                <button type="submit" class="submit-btn" id="submitBtn">
                    💾 Record Payment
                </button>
                <a href="index.php" class="action-btn secondary">
                    ← Back to Payments
                </a>
            </div>

        </form>

    </div>

</div>

<style>
/* Scoped enhancements for payment entry */
.required-star {
    color: var(--danger);
    font-weight: bold;
}

.success-message {
    background: #e8f5ee;
    color: #1e593a;
    border-left: 4px solid var(--success);
    padding: 14px 18px;
    border-radius: 6px;
    margin-bottom: 20px;
    line-height: 1.5;
}

.error-message {
    background: #fcebea;
    color: #8c2323;
    border-left: 4px solid var(--danger);
    padding: 14px 18px;
    border-radius: 6px;
    margin-bottom: 20px;
    line-height: 1.5;
}

.success-actions {
    margin-top: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.action-btn-small {
    display: inline-block;
    padding: 6px 12px;
    background: #0d2744;
    color: #ffffff !important;
    text-decoration: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 600;
    transition: background 0.2s;
}

.action-btn-small:hover {
    background: #12385f;
}

.action-btn-small.highlight {
    background: #c9a227;
    color: #071a2f !important;
}

.action-btn-small.highlight:hover {
    background: #e0bd4f;
}

.action-btn-small.secondary {
    background: #ffffff;
    color: #071a2f !important;
    border: 1px solid #cbd0d6;
}

.field-help {
    display: block;
    margin-top: 6px;
    color: #6b7280;
    font-size: 12px;
    line-height: 1.4;
}

/* Summary Badge Card */
.summary-badge-card {
    background: #071a2f;
    color: #ffffff;
    padding: 16px;
    border-radius: 8px;
    margin-top: 12px;
    border-left: 4px solid var(--gold);
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
}

.summary-badge-card .badge-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--gold-light);
    margin-bottom: 8px;
}

.summary-badge-card .badge-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    font-size: 13px;
    margin-bottom: 10px;
}

.summary-badge-card .badge-stats .lbl {
    color: #94a3b8;
}

.text-gold {
    color: var(--gold-light);
    font-size: 14px;
}

.btn-quick-fill {
    background: var(--gold);
    color: #071a2f;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-quick-fill:hover {
    background: var(--gold-light);
}

/* Student Fee Balance Card */
.student-fee-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 12px 14px;
    min-height: 48px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.bal-label {
    color: #64748b;
    font-size: 12px;
}

.bal-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.bal-amount {
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
}

.bal-amount.due {
    color: #b83232;
}

.bal-amount.cleared {
    color: #2f855a;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const paymentSource = document.getElementById("payment_source");
    const sponsorshipGroup = document.getElementById("sponsorship_group");
    const sponsorshipSelect = document.getElementById("sponsorship_id");
    const spCard = document.getElementById("sponsorship_summary_card");
    const spCardCommitted = document.getElementById("sp_card_committed");
    const spCardPaid = document.getElementById("sp_card_paid");
    const spCardRemaining = document.getElementById("sp_card_remaining");
    const btnFillSponsorRemaining = document.getElementById("btn_fill_sponsor_remaining");

    const studentSearchGroup = document.getElementById("student_search_group");
    const studentSearch = document.getElementById("student_search");
    const classGroup = document.getElementById("class_group");
    const streamGroup = document.getElementById("stream_group");
    const studentGroup = document.getElementById("student_group");
    const contributionGroup = document.getElementById("contribution_group");
    const balanceGroup = document.getElementById("student_balance_group");

    const classSelect = document.getElementById("class_id");
    const streamSelect = document.getElementById("stream_id");
    const studentSelect = document.getElementById("student_id");
    const contributionSelect = document.getElementById("contribution_type_id");

    const amountInput = document.getElementById("amount");
    const amountPreview = document.getElementById("amount_preview_text");
    const paymentMethod = document.getElementById("payment_method");
    const paymentReference = document.getElementById("payment_reference");
    const refIndicator = document.getElementById("ref_required_indicator");
    const payerType = document.getElementById("payer_type");
    const payerName = document.getElementById("payer_name");
    const payerHelp = document.getElementById("payer_help");

    const balanceCard = document.getElementById("student_balance_card");
    const balanceLoading = document.getElementById("balance_loading");
    const balanceContent = document.getElementById("balance_content");

    // Cache original student options
    const allStudentOptions = Array.from(studentSelect.options).filter(opt => opt.value !== "");

    /*
    |--------------------------------------------------------------------------
    | Currency Formatter
    |--------------------------------------------------------------------------
    */
    function formatCurrency(val) {
        return Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + " TZS";
    }

    /*
    |--------------------------------------------------------------------------
    | Filter Students List based on Search, Class, and Stream
    |--------------------------------------------------------------------------
    */
    function filterStudents() {
        const query = (studentSearch.value || "").trim().toLowerCase();
        const selectedClassId = classSelect.value;
        const selectedStreamId = streamSelect.value;
        const currentStudentVal = studentSelect.value;

        // Reset dropdown
        studentSelect.innerHTML = "";
        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = "-- Choose Student --";
        studentSelect.appendChild(placeholder);

        let matchCount = 0;
        let foundCurrent = false;

        allStudentOptions.forEach(opt => {
            const stuClassId = opt.getAttribute("data-class-id") || "";
            const stuStreamId = opt.getAttribute("data-stream-id") || "";
            const stuAdm = (opt.getAttribute("data-admission") || "").toLowerCase();
            const stuName = (opt.getAttribute("data-name") || "").toLowerCase();

            // Match query
            const matchSearch = query === "" || stuAdm.includes(query) || stuName.includes(query);

            // Match class
            const matchClass = selectedClassId === "" || stuClassId === selectedClassId;

            // Match stream (only if a specific stream is chosen)
            const matchStream = selectedStreamId === "" || stuStreamId === selectedStreamId;

            if (matchSearch && matchClass && matchStream) {
                const clone = opt.cloneNode(true);
                if (clone.value === currentStudentVal) {
                    clone.selected = true;
                    foundCurrent = true;
                }
                studentSelect.appendChild(clone);
                matchCount++;
            }
        });

        if (matchCount === 0) {
            placeholder.textContent = "-- No students match your search/filter --";
        }

        if (!foundCurrent && currentStudentVal !== "") {
            fetchStudentBalance();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Fetch Live Student Fee Balance
    |--------------------------------------------------------------------------
    */
    let balanceDebounce = null;
    function fetchStudentBalance() {
        if (paymentSource.value === "Sponsor") return;

        const stuId = studentSelect.value;
        const ctid = contributionSelect.value;

        if (!stuId) {
            balanceContent.innerHTML = '<span class="bal-label">Select student & contribution type to view fee requirement & balance.</span>';
            return;
        }

        balanceLoading.style.display = "block";
        balanceContent.style.display = "none";

        clearTimeout(balanceDebounce);
        balanceDebounce = setTimeout(() => {
            fetch(`add.php?action=get_student_balance&student_id=${stuId}&contribution_type_id=${ctid}`)
                .then(res => res.json())
                .then(data => {
                    balanceLoading.style.display = "none";
                    balanceContent.style.display = "block";

                    if (data.success) {
                        const due = data.total_due;
                        const paid = data.total_paid;
                        const bal = data.balance;

                        let statusHtml = "";
                        if (due <= 0 && ctid !== "") {
                            statusHtml = `<span class="bal-label">No fee quota set for this contribution type.</span>`;
                        } else {
                            statusHtml = `
                                <div class="bal-row">
                                    <div>
                                        <span class="bal-label">Total Required: <strong>${formatCurrency(due)}</strong> | Paid: <strong>${formatCurrency(paid)}</strong></span><br>
                                        <span class="bal-label">Outstanding Balance:</span>
                                        <span class="bal-amount ${bal > 0 ? 'due' : 'cleared'}">${formatCurrency(bal)}</span>
                                    </div>
                                    ${bal > 0 ? `<button type="button" class="btn-quick-fill" id="btn_fill_student_bal" data-bal="${bal}">Pay Full Balance</button>` : ''}
                                </div>
                            `;
                        }

                        balanceContent.innerHTML = statusHtml;

                        const btnFill = document.getElementById("btn_fill_student_bal");
                        if (btnFill) {
                            btnFill.addEventListener("click", function () {
                                amountInput.value = this.getAttribute("data-bal");
                                updateAmountPreview();
                            });
                        }
                    } else {
                        balanceContent.innerHTML = `<span class="bal-label text-danger">Error loading balance.</span>`;
                    }
                })
                .catch(() => {
                    balanceLoading.style.display = "none";
                    balanceContent.style.display = "block";
                    balanceContent.innerHTML = `<span class="bal-label text-danger">Unable to fetch balance.</span>`;
                });
        }, 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Source Toggle (Parent/Student vs Sponsor)
    |--------------------------------------------------------------------------
    */
    function updatePaymentSource() {
        const isSponsor = (paymentSource.value === "Sponsor");

        if (isSponsor) {
            sponsorshipGroup.style.display = "block";
            studentSearchGroup.style.display = "none";
            classGroup.style.display = "none";
            streamGroup.style.display = "none";
            studentGroup.style.display = "none";
            contributionGroup.style.display = "none";
            balanceGroup.style.display = "none";

            sponsorshipSelect.required = true;
            studentSelect.required = false;
            contributionSelect.required = false;

            payerType.value = "Sponsor";
            payerType.style.pointerEvents = "none";
            payerType.tabIndex = -1;
            payerName.readOnly = true;

            payerHelp.textContent = "The sponsor name will be populated automatically from the selected sponsorship commitment.";
            updateSponsorDetails();
        } else {
            sponsorshipGroup.style.display = "none";
            studentSearchGroup.style.display = "block";
            classGroup.style.display = "block";
            streamGroup.style.display = "block";
            studentGroup.style.display = "block";
            contributionGroup.style.display = "block";
            balanceGroup.style.display = "block";

            sponsorshipSelect.required = false;
            studentSelect.required = true;
            contributionSelect.required = true;

            payerType.style.pointerEvents = "auto";
            payerType.tabIndex = 0;
            payerName.readOnly = false;

            if (payerType.value === "Sponsor") {
                payerType.value = "Parent/Guardian";
                payerName.value = "";
            }

            payerHelp.textContent = "Enter the person or organization who actually made the payment.";
            fetchStudentBalance();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Sponsorship Selection Details & Quick-Fill
    |--------------------------------------------------------------------------
    */
    function updateSponsorDetails() {
        const opt = sponsorshipSelect.options[sponsorshipSelect.selectedIndex];

        if (!opt || !opt.value) {
            spCard.style.display = "none";
            payerName.value = "";
            return;
        }

        const sponsor = opt.getAttribute("data-sponsor") || "";
        const committed = parseFloat(opt.getAttribute("data-committed") || 0);
        const paid = parseFloat(opt.getAttribute("data-paid") || 0);
        const remaining = parseFloat(opt.getAttribute("data-remaining") || 0);

        payerName.value = sponsor;
        payerType.value = "Sponsor";

        spCardCommitted.textContent = formatCurrency(committed);
        spCardPaid.textContent = formatCurrency(paid);
        spCardRemaining.textContent = formatCurrency(remaining);
        spCard.style.display = "block";

        btnFillSponsorRemaining.onclick = function () {
            if (remaining > 0) {
                amountInput.value = remaining;
                updateAmountPreview();
            }
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Amount Preview Formatter
    |--------------------------------------------------------------------------
    */
    function updateAmountPreview() {
        const val = parseFloat(amountInput.value);
        if (!isNaN(val) && val > 0) {
            amountPreview.innerHTML = `Formatted Amount: <strong>${formatCurrency(val)}</strong>`;
            amountPreview.style.color = "var(--navy)";
        } else {
            amountPreview.textContent = "Enter amount in Tanzanian Shillings.";
            amountPreview.style.color = "#6b7280";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Method Helper & Reference Requirement
    |--------------------------------------------------------------------------
    */
    function updatePaymentMethod() {
        const method = paymentMethod.value;
        if (method === "Bank" || method === "Mobile Money") {
            refIndicator.style.display = "inline";
            paymentReference.required = true;
            paymentReference.placeholder = method === "Bank" ? "e.g. BANK-REF-123456" : "e.g. MPESA-ABCDEF";
        } else {
            refIndicator.style.display = "none";
            paymentReference.required = false;
            paymentReference.placeholder = "e.g. Receipt / Slip No (optional for cash)";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Synchronize Payer Name when Payer Type is Student
    |--------------------------------------------------------------------------
    */
    function onStudentOrPayerTypeChange() {
        if (payerType.value === "Student" && studentSelect.value !== "") {
            const opt = studentSelect.options[studentSelect.selectedIndex];
            if (opt) {
                const stuName = opt.getAttribute("data-name");
                if (stuName) {
                    payerName.value = stuName;
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Event Listeners
    |--------------------------------------------------------------------------
    */
    paymentSource.addEventListener("change", updatePaymentSource);
    sponsorshipSelect.addEventListener("change", updateSponsorDetails);

    studentSearch.addEventListener("input", filterStudents);
    classSelect.addEventListener("change", filterStudents);
    streamSelect.addEventListener("change", filterStudents);

    studentSelect.addEventListener("change", function () {
        // If a student is picked directly from search, auto-sync their class & stream
        const opt = studentSelect.options[studentSelect.selectedIndex];
        if (opt && opt.value) {
            const cId = opt.getAttribute("data-class-id");
            const sId = opt.getAttribute("data-stream-id");
            if (cId && classSelect.value !== cId) classSelect.value = cId;
            if (sId && streamSelect.value !== sId) streamSelect.value = sId;
        }
        onStudentOrPayerTypeChange();
        fetchStudentBalance();
    });

    contributionSelect.addEventListener("change", fetchStudentBalance);
    payerType.addEventListener("change", onStudentOrPayerTypeChange);
    paymentMethod.addEventListener("change", updatePaymentMethod);
    amountInput.addEventListener("input", updateAmountPreview);

    // Initial state trigger
    updatePaymentSource();
    updatePaymentMethod();
    updateAmountPreview();
    if (studentSelect.value) {
        fetchStudentBalance();
    }
});
</script>

<?php include "../includes/footer.php"; ?>