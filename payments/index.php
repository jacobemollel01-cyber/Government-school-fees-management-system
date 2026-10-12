<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$class_id =
    isset($_GET["class_id"]) && $_GET["class_id"] !== ""
        ? (int) $_GET["class_id"]
        : 0;

$stream_id =
    isset($_GET["stream_id"]) && $_GET["stream_id"] !== ""
        ? (int) $_GET["stream_id"]
        : 0;

$contribution_type_id =
    isset($_GET["contribution_type_id"])
        && $_GET["contribution_type_id"] !== ""
        ? (int) $_GET["contribution_type_id"]
        : 0;

$status =
    trim($_GET["status"] ?? "");

$search =
    trim($_GET["search"] ?? "");


/*
|--------------------------------------------------------------------------
| Load classes
|--------------------------------------------------------------------------
*/

$classes = [];

$classes_sql = "
    SELECT
        class_id,
        class_name
    FROM classes
    WHERE is_active = TRUE
    ORDER BY class_id ASC
";

$classes_result =
    $conn->query($classes_sql);

if ($classes_result) {

    while ($row = $classes_result->fetch_assoc()) {

        $classes[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| Load streams
|--------------------------------------------------------------------------
*/

$streams = [];

$streams_sql = "
    SELECT
        stream_id,
        stream_name
    FROM streams
    WHERE is_active = TRUE
    ORDER BY stream_id ASC
";

$streams_result =
    $conn->query($streams_sql);

if ($streams_result) {

    while ($row = $streams_result->fetch_assoc()) {

        $streams[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| Load contribution types
|--------------------------------------------------------------------------
*/

$contribution_types = [];

$types_sql = "
    SELECT
        contribution_type_id,
        name
    FROM contribution_types
    WHERE is_active = TRUE
      AND name IN ('Food', 'Hostel')
    ORDER BY FIELD(name, 'Food', 'Hostel')
";

$types_result =
    $conn->query($types_sql);

if ($types_result) {

    while ($row = $types_result->fetch_assoc()) {

        $contribution_types[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| Build payment query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        p.payment_id,

        p.student_id,

        p.sponsorship_id,

        p.contribution_type_id,

        p.amount,

        p.payment_date,

        p.payment_period,

        p.payment_method,

        p.payment_reference,

        p.payer_name,

        p.payer_type,

        p.notes,

        p.recorded_by,

        p.created_at,

        p.status,

        p.voided_by,

        p.voided_at,

        p.void_reason,

        s.admission_no,

        s.full_name,

        c.class_id,

        c.class_name,

        st.stream_id,

        st.stream_name,

        ct.name AS contribution_name,

        sp.sponsor_id,

        sponsors.sponsor_name

    FROM payments p

    INNER JOIN students s
        ON p.student_id = s.student_id

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

    INNER JOIN contribution_types ct
        ON p.contribution_type_id =
           ct.contribution_type_id

    LEFT JOIN sponsorships sp
        ON p.sponsorship_id =
           sp.sponsorship_id

    LEFT JOIN sponsors
        ON sp.sponsor_id =
           sponsors.sponsor_id

    WHERE 1 = 1
";


/*
|--------------------------------------------------------------------------
| Dynamic filters
|--------------------------------------------------------------------------
*/

$params = [];

$types = "";


/*
|--------------------------------------------------------------------------
| Class filter
|--------------------------------------------------------------------------
*/

if ($class_id > 0) {

    $sql .= "
        AND c.class_id = ?
    ";

    $params[] = $class_id;
    $types .= "i";
}


/*
|--------------------------------------------------------------------------
| Stream filter
|--------------------------------------------------------------------------
*/

if ($stream_id > 0) {

    $sql .= "
        AND st.stream_id = ?
    ";

    $params[] = $stream_id;
    $types .= "i";
}


/*
|--------------------------------------------------------------------------
| Contribution filter
|--------------------------------------------------------------------------
*/

if ($contribution_type_id > 0) {

    $sql .= "
        AND p.contribution_type_id = ?
    ";

    $params[] =
        $contribution_type_id;

    $types .= "i";
}


/*
|--------------------------------------------------------------------------
| Status filter
|--------------------------------------------------------------------------
*/

if (
    $status === "Active"
    || $status === "Voided"
) {

    $sql .= "
        AND p.status = ?
    ";

    $params[] = $status;
    $types .= "s";
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $sql .= "
        AND (
            s.full_name LIKE ?
            OR s.admission_no LIKE ?
            OR p.payer_name LIKE ?
            OR p.payment_reference LIKE ?
            OR sponsors.sponsor_name LIKE ?
        )
    ";

    $search_value =
        "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "sssss";
}


/*
|--------------------------------------------------------------------------
| Order
|--------------------------------------------------------------------------
|
| Class → Stream → Student → Date → Payment ID
|
*/

$sql .= "
    ORDER BY
        c.class_id ASC,
        st.stream_id ASC,
        s.full_name ASC,
        p.payment_date DESC,
        p.payment_id DESC
";


/*
|--------------------------------------------------------------------------
| Execute payment query
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->prepare($sql);

$payments = [];

$query_error = "";


if (!$stmt) {

    $query_error =
        "Database error: " . $conn->error;

} else {

    if (!empty($params)) {

        $stmt->bind_param(
            $types,
            ...$params
        );
    }

    $stmt->execute();

    $result =
        $stmt->get_result();


    while ($row = $result->fetch_assoc()) {

        $payments[] = $row;
    }


    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Calculate active total
|--------------------------------------------------------------------------
*/

$active_total = 0;

$active_count = 0;

$voided_count = 0;


foreach ($payments as $payment) {

    if ($payment["status"] === "Active") {

        $active_total +=
            (float) $payment["amount"];

        $active_count++;

    } else {

        $voided_count++;
    }
}


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

require_once "../includes/header.php";

?>


<div class="container">


    <!-- =========================================================
         PAGE HEADER
    ========================================================== -->

    <div class="dashboard-header">

        <div>

            <h1>Payments</h1>

            <p class="dashboard-subtitle">
                View and manage Food and Hostel payments.
            </p>

        </div>


        <div>

            <a
                href="add.php"
                class="action-btn"
            >
                + Record Payment
            </a>

        </div>

    </div>


    <!-- =========================================================
         SUMMARY
    ========================================================== -->

    <div class="payment-summary">

        <div class="summary-item">

            <span class="summary-label">
                Active Payments
            </span>

            <strong>
                <?= number_format($active_count); ?>
            </strong>

        </div>


        <div class="summary-item">

            <span class="summary-label">
                Active Amount
            </span>

            <strong>
                <?= number_format(
                    $active_total,
                    2
                ); ?>
                TZS
            </strong>

        </div>


        <div class="summary-item">

            <span class="summary-label">
                Voided Payments
            </span>

            <strong>
                <?= number_format($voided_count); ?>
            </strong>

        </div>

    </div>


    <!-- =========================================================
         FILTERS
    ========================================================== -->

    <div class="filter-card">

        <form method="GET">

            <div class="filter-grid">


                <!-- Class -->

                <div class="filter-group">

                    <label for="class_id">
                        Class
                    </label>

                    <select
                        id="class_id"
                        name="class_id"
                    >

                        <option value="">
                            All Classes
                        </option>

                        <?php foreach ($classes as $class): ?>

                            <option
                                value="<?= $class["class_id"]; ?>"
                                <?= (
                                    $class_id
                                    ==
                                    $class["class_id"]
                                )
                                    ? "selected"
                                    : ""; ?>
                            >

                                <?= htmlspecialchars(
                                    $class["class_name"]
                                ); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Stream -->

                <div class="filter-group">

                    <label for="stream_id">
                        Stream
                    </label>

                    <select
                        id="stream_id"
                        name="stream_id"
                    >

                        <option value="">
                            All Streams
                        </option>

                        <?php foreach ($streams as $stream): ?>

                            <option
                                value="<?= $stream["stream_id"]; ?>"
                                <?= (
                                    $stream_id
                                    ==
                                    $stream["stream_id"]
                                )
                                    ? "selected"
                                    : ""; ?>
                            >

                                <?= htmlspecialchars(
                                    $stream["stream_name"]
                                ); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Contribution -->

                <div class="filter-group">

                    <label for="contribution_type_id">
                        Contribution
                    </label>

                    <select
                        id="contribution_type_id"
                        name="contribution_type_id"
                    >

                        <option value="">
                            All Contributions
                        </option>

                        <?php foreach (
                            $contribution_types
                            as $type
                        ): ?>

                            <option
                                value="<?= $type[
                                    "contribution_type_id"
                                ]; ?>"
                                <?= (
                                    $contribution_type_id
                                    ==
                                    $type[
                                        "contribution_type_id"
                                    ]
                                )
                                    ? "selected"
                                    : ""; ?>
                            >

                                <?= htmlspecialchars(
                                    $type["name"]
                                ); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Status -->

                <div class="filter-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <option value="">
                            All Statuses
                        </option>

                        <option
                            value="Active"
                            <?= $status === "Active"
                                ? "selected"
                                : ""; ?>
                        >
                            Active
                        </option>

                        <option
                            value="Voided"
                            <?= $status === "Voided"
                                ? "selected"
                                : ""; ?>
                        >
                            Voided
                        </option>

                    </select>

                </div>


                <!-- Search -->

                <div class="filter-group search-group">

                    <label for="search">
                        Search
                    </label>

                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="<?= htmlspecialchars(
                            $search
                        ); ?>"
                        placeholder="Student, payer, reference..."
                    >

                </div>


            </div>


            <div class="filter-actions">

                <button type="submit">
                    Apply Filters
                </button>

                <a
                    href="index.php"
                    class="action-btn secondary"
                >
                    Clear
                </a>

            </div>

        </form>

    </div>


    <!-- =========================================================
         ERROR
    ========================================================== -->

    <?php if ($query_error !== ""): ?>

        <div class="form-message error-message">

            <?= htmlspecialchars(
                $query_error
            ); ?>

        </div>

    <?php endif; ?>


    <!-- =========================================================
         PAYMENT TABLE
    ========================================================== -->

    <div class="table-card">

        <div class="table-header">

            <div>

                <h2>Payment Records</h2>

                <p>
                    <?= number_format(
                        count($payments)
                    ); ?>
                    record(s) found
                </p>

            </div>

        </div>


        <div class="table-wrapper">

            <table class="data-table payment-table">

                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Class</th>

                        <th>Stream</th>

                        <th>Student</th>

                        <th>Contribution</th>

                        <th>Amount</th>

                        <th>Date</th>

                        <th>Period</th>

                        <th>Method</th>

                        <th>Reference</th>

                        <th>Payer</th>

                        <th>Payer Type</th>

                        <th>Source</th>

                        <th>Status</th>

                        <th>Actions</th>

                    </tr>

                </thead>


                <tbody>


                <?php if (empty($payments)): ?>

                    <tr>

                        <td
                            colspan="15"
                            class="empty-state"
                        >

                            No payment records found.

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach (
                        $payments as $payment
                    ): ?>


                        <tr
                            class="<?= $payment["status"]
                                === "Voided"
                                ? "voided-row"
                                : ""; ?>"
                        >


                            <!-- ID -->

                            <td>

                                #<?= (int)
                                    $payment["payment_id"]; ?>

                            </td>


                            <!-- Class -->

                            <td>

                                <span class="class-badge">

                                    <?= htmlspecialchars(
                                        $payment["class_name"]
                                        ?? "—"
                                    ); ?>

                                </span>

                            </td>


                            <!-- Stream -->

                            <td>

                                <span class="stream-badge">

                                    <?= htmlspecialchars(
                                        $payment["stream_name"]
                                        ?? "—"
                                    ); ?>

                                </span>

                            </td>


                            <!-- Student -->

                            <td>

                                <div class="student-cell">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $payment["full_name"]
                                        ); ?>
                                    </strong>

                                    <small>
                                        <?= htmlspecialchars(
                                            $payment["admission_no"]
                                        ); ?>
                                    </small>

                                </div>

                            </td>


                            <!-- Contribution -->

                            <td>

                                <?= htmlspecialchars(
                                    $payment[
                                        "contribution_name"
                                    ]
                                ); ?>

                            </td>


                            <!-- Amount -->

                            <td class="amount-cell">

                                <?= number_format(
                                    (float)
                                    $payment["amount"],
                                    2
                                ); ?>

                                <small>TZS</small>

                            </td>


                            <!-- Date -->

                            <td>

                                <?= htmlspecialchars(
                                    $payment["payment_date"]
                                ); ?>

                            </td>


                            <!-- Period -->

                            <td>

                                <?= $payment["payment_period"]
                                    !== ""
                                    ? htmlspecialchars(
                                        $payment[
                                            "payment_period"
                                        ]
                                    )
                                    : "—"; ?>

                            </td>


                            <!-- Method -->

                            <td>

                                <?= htmlspecialchars(
                                    $payment[
                                        "payment_method"
                                    ]
                                ); ?>

                            </td>


                            <!-- Reference -->

                            <td>

                                <?php if (
                                    !empty(
                                        $payment[
                                            "payment_reference"
                                        ]
                                    )
                                ): ?>

                                    <span class="reference-code">

                                        <?= htmlspecialchars(
                                            $payment[
                                                "payment_reference"
                                            ]
                                        ); ?>

                                    </span>

                                <?php else: ?>

                                    <span class="muted">
                                        —
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Payer -->

                            <td>

                                <div class="payer-cell">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $payment[
                                                "payer_name"
                                            ]
                                        ); ?>
                                    </strong>

                                </div>

                            </td>


                            <!-- Payer Type -->

                            <td>

                                <span class="payer-type">

                                    <?= htmlspecialchars(
                                        $payment[
                                            "payer_type"
                                        ]
                                    ); ?>

                                </span>

                            </td>


                            <!-- Source -->

                            <td>

                                <?php if (
                                    !empty(
                                        $payment[
                                            "sponsorship_id"
                                        ]
                                    )
                                ): ?>

                                    <span
                                        class="source-badge sponsor"
                                    >
                                        Sponsor
                                    </span>

                                    <?php if (
                                        !empty(
                                            $payment[
                                                "sponsor_name"
                                            ]
                                        )
                                    ): ?>

                                        <small
                                            class="sponsor-name"
                                        >
                                            <?= htmlspecialchars(
                                                $payment[
                                                    "sponsor_name"
                                                ]
                                            ); ?>
                                        </small>

                                    <?php endif; ?>

                                <?php else: ?>

                                    <span
                                        class="source-badge normal"
                                    >
                                        Direct Payment
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Status -->

                            <td>

                                <?php if (
                                    $payment["status"]
                                    ===
                                    "Active"
                                ): ?>

                                    <span
                                        class="status-badge active"
                                    >
                                        ACTIVE
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="status-badge voided"
                                    >
                                        VOIDED
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Actions -->

                            <td>

                                <div class="table-actions">


                                    <?php if (
                                        $payment["status"]
                                        ===
                                        "Active"
                                    ): ?>


                                        <a
                                            href="edit.php?id=<?= (int)
                                                $payment[
                                                    "payment_id"
                                                ]; ?>"
                                            class="action-btn small"
                                        >
                                            Edit
                                        </a>


                                        <a
                                            href="void.php?id=<?= (int)
                                                $payment[
                                                    "payment_id"
                                                ]; ?>"
                                            class="action-btn small danger"
                                        >
                                            Void
                                        </a>


                                    <?php else: ?>

                                        <span
                                            class="voided-info"
                                        >

                                            Voided

                                            <?php if (
                                                !empty(
                                                    $payment[
                                                        "voided_at"
                                                    ]
                                                )
                                            ): ?>

                                                <small>

                                                    <?= htmlspecialchars(
                                                        $payment[
                                                            "voided_at"
                                                        ]
                                                    ); ?>

                                                </small>

                                            <?php endif; ?>

                                        </span>

                                    <?php endif; ?>


                                </div>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>


</div>


<style>

/* =========================================================
   PAYMENT SUMMARY
========================================================= */

.payment-summary {
    display: grid;
    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    gap: 18px;

    margin-bottom: 22px;
}

.summary-item {
    background: var(--dark-card);
    border: 1px solid rgba(201, 162, 39, 0.25);
    border-radius: 8px;
    padding: 18px 20px;
}

.summary-label {
    display: block;
    color: #9ca3af;
    font-size: 13px;
    margin-bottom: 7px;
}

.summary-item strong {
    color: var(--gold-light);
    font-size: 22px;
}


/* =========================================================
   FILTERS
========================================================= */

.filter-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 18px 20px;
    margin-bottom: 22px;
}

.filter-grid {
    display: grid;
    grid-template-columns:
        1fr 1fr 1fr 1fr;

    gap: 14px;
    align-items: end;
}

.filter-group {
    min-width: 0;
}

.filter-group label {
    display: block;
    margin-bottom: 6px;
    color: #374151;
    font-size: 12px;
    font-weight: 700;
}

.filter-group select,
.filter-group input {
    width: 100%;
    height: 42px;
    padding: 0 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: #fff;
    color: #111827;
    font-size: 13px;
    box-sizing: border-box;
}

.filter-group select:focus,
.filter-group input:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow:
        0 0 0 2px
        rgba(201, 162, 39, 0.12);
}


/* =========================================================
   SEARCH
========================================================= */

.search-group {
    grid-column: span 3;
}

.search-group input {
    height: 44px;
}


/* =========================================================
   FILTER ACTIONS
========================================================= */

.filter-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 14px;
}

.filter-actions button,
.filter-actions .action-btn {
    height: 42px;
    min-width: 120px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}


/* =========================================================
   TABLE
========================================================= */

.table-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
}

.table-header {
    padding: 18px 20px;
    border-bottom: 1px solid #e5e7eb;
}

.table-header h2 {
    margin: 0 0 4px;
    font-size: 18px;
}

.table-header p {
    margin: 0;
    color: #6b7280;
    font-size: 13px;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.payment-table {
    min-width: 1700px;
    border-collapse: collapse;
    width: 100%;
}

.payment-table th {
    background: var(--navy);
    color: #fff;
    padding: 13px 12px;
    text-align: left;
    font-size: 12px;
    white-space: nowrap;
}

.payment-table td {
    padding: 13px 12px;
    border-bottom: 1px solid #e5e7eb;
    white-space: nowrap;
    vertical-align: middle;
}

.payment-table tbody tr:hover {
    background: #fafafa;
}


/* =========================================================
   STUDENT
========================================================= */

.student-cell strong,
.payer-cell strong {
    display: block;
    font-weight: 600;
}

.student-cell small {
    display: block;
    margin-top: 3px;
    color: #6b7280;
    font-size: 11px;
}


/* =========================================================
   CLASS / STREAM
========================================================= */

.class-badge,
.stream-badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 5px;
    font-weight: 600;
    font-size: 12px;
}

.class-badge {
    background: #f4e4a6;
    color: #5f4a00;
}

.stream-badge {
    background: #edf0f3;
    color: #374151;
}


/* =========================================================
   AMOUNT
========================================================= */

.amount-cell {
    font-weight: 700;
}

.amount-cell small {
    color: #6b7280;
    font-size: 10px;
}


/* =========================================================
   REFERENCE
========================================================= */

.reference-code {
    font-family: monospace;
    font-size: 12px;
    background: #f3f4f6;
    padding: 5px 7px;
    border-radius: 4px;
}

.muted {
    color: #9ca3af;
}


/* =========================================================
   PAYER
========================================================= */

.payer-type {
    font-size: 12px;
    color: #4b5563;
}


/* =========================================================
   SOURCE
========================================================= */

.source-badge {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 700;
}

.source-badge.sponsor {
    background: #f4e4a6;
    color: #5f4a00;
}

.source-badge.normal {
    background: #edf0f3;
    color: #374151;
}

.sponsor-name {
    display: block;
    margin-top: 4px;
    color: #6b7280;
    font-size: 11px;
}


/* =========================================================
   STATUS
========================================================= */

.status-badge {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.4px;
}

.status-badge.active {
    background: #e8f5ee;
    color: #276749;
}

.status-badge.voided {
    background: #fcebea;
    color: #9b2c2c;
}


/* =========================================================
   VOIDED ROW
========================================================= */

.voided-row {
    opacity: 0.68;
    background: #fafafa;
}

.voided-info {
    color: #9b2c2c;
    font-weight: 700;
}

.voided-info small {
    display: block;
    font-weight: 400;
    font-size: 10px;
    margin-top: 3px;
}


/* =========================================================
   ACTIONS
========================================================= */

.table-actions {
    display: flex;
    gap: 6px;
    align-items: center;
}

.action-btn.small {
    padding: 6px 9px;
    font-size: 11px;
}

.action-btn.danger {
    background: #b83232;
    color: #fff;
}

.action-btn.danger:hover {
    background: #9b2c2c;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state {
    text-align: center;
    padding: 45px !important;
    color: #6b7280;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .payment-summary {
        grid-template-columns: 1fr;
    }

    .filter-grid {
        grid-template-columns: 1fr 1fr;
    }

    .search-group {
        grid-column: span 2;
    }

}

@media (max-width: 600px) {

    .filter-grid {
        grid-template-columns: 1fr;
    }

    .search-group {
        grid-column: span 1;
    }

    .filter-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .filter-actions button,
    .filter-actions .action-btn {
        width: 100%;
    }

}

</style>


<script>

/*
|--------------------------------------------------------------------------
| Class / Stream filter
|--------------------------------------------------------------------------
|
| The server performs the actual filtering.
| This section is only for future UI improvements.
|--------------------------------------------------------------------------
*/

const classFilter =
    document.getElementById("class_id");

const streamFilter =
    document.getElementById("stream_id");


const originalStreamOptions =
    Array.from(
        streamFilter.options
    );


function filterStreams() {

    /*
    | Keep all active streams available.
    | The selected class is handled server-side.
    */

    if (!classFilter.value) {

        streamFilter.innerHTML = "";

        originalStreamOptions.forEach(
            option => {

                streamFilter.appendChild(
                    option.cloneNode(true)
                );

            }
        );

    }

}


classFilter.addEventListener(
    "change",
    filterStreams
);

</script>


<?php include "../includes/footer.php"; ?>