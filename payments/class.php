<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';

/*
|--------------------------------------------------------------------------
| Class Financial & Contribution Summary
|--------------------------------------------------------------------------
| Uses the current database structure:
| - students
| - student_enrollments
| - classes
| - streams
| - payments
| - student_requirements
| - food_contributions
| - contribution_types
|
| Voided payments/contributions are excluded.
|--------------------------------------------------------------------------
*/

$current_year = date('Y');

/*
|--------------------------------------------------------------------------
| Get active classes
|--------------------------------------------------------------------------
*/
$class_query = "
    SELECT class_id, class_name
    FROM classes
    WHERE is_active = 1
    ORDER BY class_id ASC
";

$class_result = $conn->query($class_query);
$classes = $class_result ? $class_result->fetch_all(MYSQLI_ASSOC) : [];

$summaries = [];

/*
|--------------------------------------------------------------------------
| Build summary for each class
|--------------------------------------------------------------------------
*/
foreach ($classes as $class) {

    $class_id = (int)$class['class_id'];

    /*
    |--------------------------------------------------------------------------
    | Total active students in this class
    |--------------------------------------------------------------------------
    */
    $student_query = "
        SELECT COUNT(DISTINCT se.student_id) AS total_students
        FROM student_enrollments se
        INNER JOIN students s
            ON s.student_id = se.student_id
        WHERE se.class_id = ?
          AND se.academic_year = ?
          AND s.status = 'Active'
    ";

    $stmt = $conn->prepare($student_query);
    $stmt->bind_param("ii", $class_id, $current_year);
    $stmt->execute();

    $student_result = $stmt->get_result()->fetch_assoc();
    $total_students = (int)($student_result['total_students'] ?? 0);

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Food & Hostel payments collected
    |--------------------------------------------------------------------------
    */
    $payment_query = "
        SELECT
            COALESCE(SUM(
                CASE
                    WHEN ct.name = 'Food' THEN p.amount
                    ELSE 0
                END
            ), 0) AS food_collected,

            COALESCE(SUM(
                CASE
                    WHEN ct.name = 'Hostel' THEN p.amount
                    ELSE 0
                END
            ), 0) AS hostel_collected

        FROM payments p

        INNER JOIN students s
            ON s.student_id = p.student_id

        INNER JOIN student_enrollments se
            ON se.student_id = s.student_id

        INNER JOIN contribution_types ct
            ON ct.contribution_type_id = p.contribution_type_id

        WHERE se.class_id = ?
          AND se.academic_year = ?
          AND p.status = 'Active'
          AND ct.name IN ('Food', 'Hostel')
    ";

    $stmt = $conn->prepare($payment_query);
    $stmt->bind_param("ii", $class_id, $current_year);
    $stmt->execute();

    $payment_result = $stmt->get_result()->fetch_assoc();

    $food_collected = (float)($payment_result['food_collected'] ?? 0);
    $hostel_collected = (float)($payment_result['hostel_collected'] ?? 0);

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Maize & Beans quantities
    |--------------------------------------------------------------------------
    */
    $contribution_query = "
        SELECT
            COALESCE(SUM(
                CASE
                    WHEN ct.name = 'Maize' THEN fc.quantity
                    ELSE 0
                END
            ), 0) AS maize_quantity,

            COALESCE(SUM(
                CASE
                    WHEN ct.name = 'Beans' THEN fc.quantity
                    ELSE 0
                END
            ), 0) AS beans_quantity

        FROM food_contributions fc

        INNER JOIN students s
            ON s.student_id = fc.student_id

        INNER JOIN student_enrollments se
            ON se.student_id = s.student_id

        INNER JOIN contribution_types ct
            ON ct.contribution_type_id = fc.contribution_type_id

        WHERE se.class_id = ?
          AND se.academic_year = ?
          AND fc.status = 'Active'
          AND ct.name IN ('Maize', 'Beans')
    ";

    $stmt = $conn->prepare($contribution_query);
    $stmt->bind_param("ii", $class_id, $current_year);
    $stmt->execute();

    $contribution_result = $stmt->get_result()->fetch_assoc();

    $maize_quantity = (float)($contribution_result['maize_quantity'] ?? 0);
    $beans_quantity = (float)($contribution_result['beans_quantity'] ?? 0);

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Food payment status
    |--------------------------------------------------------------------------
    |
    | For each student:
    |   No payment       = NOT PAID
    |   Payment < due    = PARTIALLY PAID
    |   Payment >= due   = FULLY PAID
    |
    | Sponsor payments count because they are linked to the student_id.
    |--------------------------------------------------------------------------
    */
    $food_status_query = "
        SELECT
            SUM(
                CASE
                    WHEN COALESCE(r.amount_due, 0) <= 0
                         OR COALESCE(p.paid_amount, 0) <= 0
                    THEN 1
                    ELSE 0
                END
            ) AS food_not_paid,

            SUM(
                CASE
                    WHEN COALESCE(p.paid_amount, 0) > 0
                     AND COALESCE(p.paid_amount, 0) < COALESCE(r.amount_due, 0)
                    THEN 1
                    ELSE 0
                END
            ) AS food_partial,

            SUM(
                CASE
                    WHEN COALESCE(r.amount_due, 0) > 0
                     AND COALESCE(p.paid_amount, 0) >= COALESCE(r.amount_due, 0)
                    THEN 1
                    ELSE 0
                END
            ) AS food_paid

        FROM students s

        INNER JOIN student_enrollments se
            ON se.student_id = s.student_id

        LEFT JOIN (
            SELECT
                student_id,
                SUM(amount_due) AS amount_due
            FROM student_requirements sr
            INNER JOIN contribution_types ct
                ON ct.contribution_type_id = sr.contribution_type_id
            WHERE ct.name = 'Food'
              AND sr.academic_year = ?
            GROUP BY student_id
        ) r
            ON r.student_id = s.student_id

        LEFT JOIN (
            SELECT
                student_id,
                SUM(amount) AS paid_amount
            FROM payments p
            INNER JOIN contribution_types ct
                ON ct.contribution_type_id = p.contribution_type_id
            WHERE ct.name = 'Food'
              AND p.status = 'Active'
            GROUP BY student_id
        ) p
            ON p.student_id = s.student_id

        WHERE se.class_id = ?
          AND se.academic_year = ?
          AND s.status = 'Active'
    ";

    $stmt = $conn->prepare($food_status_query);
    $stmt->bind_param("iii", $current_year, $class_id, $current_year);
    $stmt->execute();

    $food_status = $stmt->get_result()->fetch_assoc();

    $food_not_paid = (int)($food_status['food_not_paid'] ?? 0);
    $food_partial = (int)($food_status['food_partial'] ?? 0);
    $food_paid = (int)($food_status['food_paid'] ?? 0);

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Hostel payment status
    |--------------------------------------------------------------------------
    */
    $hostel_status_query = "
        SELECT
            SUM(
                CASE
                    WHEN COALESCE(r.amount_due, 0) <= 0
                         OR COALESCE(p.paid_amount, 0) <= 0
                    THEN 1
                    ELSE 0
                END
            ) AS hostel_not_paid,

            SUM(
                CASE
                    WHEN COALESCE(p.paid_amount, 0) > 0
                     AND COALESCE(p.paid_amount, 0) < COALESCE(r.amount_due, 0)
                    THEN 1
                    ELSE 0
                END
            ) AS hostel_partial,

            SUM(
                CASE
                    WHEN COALESCE(r.amount_due, 0) > 0
                     AND COALESCE(p.paid_amount, 0) >= COALESCE(r.amount_due, 0)
                    THEN 1
                    ELSE 0
                END
            ) AS hostel_paid

        FROM students s

        INNER JOIN student_enrollments se
            ON se.student_id = s.student_id

        LEFT JOIN (
            SELECT
                student_id,
                SUM(amount_due) AS amount_due
            FROM student_requirements sr
            INNER JOIN contribution_types ct
                ON ct.contribution_type_id = sr.contribution_type_id
            WHERE ct.name = 'Hostel'
              AND sr.academic_year = ?
            GROUP BY student_id
        ) r
            ON r.student_id = s.student_id

        LEFT JOIN (
            SELECT
                student_id,
                SUM(amount) AS paid_amount
            FROM payments p
            INNER JOIN contribution_types ct
                ON ct.contribution_type_id = p.contribution_type_id
            WHERE ct.name = 'Hostel'
              AND p.status = 'Active'
            GROUP BY student_id
        ) p
            ON p.student_id = s.student_id

        WHERE se.class_id = ?
          AND se.academic_year = ?
          AND s.status = 'Active'
    ";

    $stmt = $conn->prepare($hostel_status_query);
    $stmt->bind_param("iii", $current_year, $class_id, $current_year);
    $stmt->execute();

    $hostel_status = $stmt->get_result()->fetch_assoc();

    $hostel_not_paid = (int)($hostel_status['hostel_not_paid'] ?? 0);
    $hostel_partial = (int)($hostel_status['hostel_partial'] ?? 0);
    $hostel_paid = (int)($hostel_status['hostel_paid'] ?? 0);

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Combined financial collection
    |--------------------------------------------------------------------------
    */
    $combined_total = $food_collected + $hostel_collected;


    /*
    |--------------------------------------------------------------------------
    | Save class summary
    |--------------------------------------------------------------------------
    */
    $summaries[] = [
        'class_id' => $class_id,
        'class_name' => $class['class_name'],
        'total_students' => $total_students,

        'food_collected' => $food_collected,
        'hostel_collected' => $hostel_collected,
        'combined_total' => $combined_total,

        'food_paid' => $food_paid,
        'food_partial' => $food_partial,
        'food_not_paid' => $food_not_paid,

        'hostel_paid' => $hostel_paid,
        'hostel_partial' => $hostel_partial,
        'hostel_not_paid' => $hostel_not_paid,

        'maize_quantity' => $maize_quantity,
        'beans_quantity' => $beans_quantity
    ];
}
?>

<div class="app-container">

    <header class="page-header">
        <div>
            <h2>Class Financial Summary</h2>
            <p class="subtitle">
                Class-by-class overview of Food, Hostel, Maize and Beans contributions
                for <?= htmlspecialchars($current_year) ?>.
            </p>
        </div>

        <div class="header-actions">
            <a href="index.php" class="btn secondary-btn">
                Back to Payments
            </a>
        </div>
    </header>


    <!-- SUMMARY TABLE -->
    <div class="card table-card">

        <div class="table-responsive">

            <table class="data-table class-summary-table">

                <thead>

                    <tr>
                        <th rowspan="2">Class</th>
                        <th rowspan="2">Students</th>

                        <th colspan="2">Money Collected</th>

                        <th colspan="3">Food Status</th>

                        <th colspan="3">Hostel Status</th>

                        <th colspan="2">Physical Contributions</th>

                        <th rowspan="2">Combined Total</th>
                    </tr>

                    <tr>
                        <th>Food</th>
                        <th>Hostel</th>

                        <th>Paid</th>
                        <th>Partial</th>
                        <th>Not Paid</th>

                        <th>Paid</th>
                        <th>Partial</th>
                        <th>Not Paid</th>

                        <th>Maize (kg)</th>
                        <th>Beans (kg)</th>
                    </tr>

                </thead>


                <tbody>

                    <?php if (empty($summaries)): ?>

                        <tr>
                            <td colspan="13" class="text-center empty-state">
                                No class summary data available.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($summaries as $row): ?>

                            <tr>

                                <!-- CLASS -->
                                <td>
                                    <strong>
                                        <?= htmlspecialchars($row['class_name']) ?>
                                    </strong>
                                </td>


                                <!-- STUDENTS -->
                                <td>
                                    <span class="badge">
                                        <?= number_format($row['total_students']) ?>
                                    </span>
                                </td>


                                <!-- FOOD -->
                                <td>
                                    <?= number_format($row['food_collected'], 2) ?>
                                </td>


                                <!-- HOSTEL -->
                                <td>
                                    <?= number_format($row['hostel_collected'], 2) ?>
                                </td>


                                <!-- FOOD PAID -->
                                <td>
                                    <span class="status-badge paid">
                                        <?= $row['food_paid'] ?>
                                    </span>
                                </td>


                                <!-- FOOD PARTIAL -->
                                <td>
                                    <span class="status-badge partial">
                                        <?= $row['food_partial'] ?>
                                    </span>
                                </td>


                                <!-- FOOD NOT PAID -->
                                <td>
                                    <span class="status-badge not-paid">
                                        <?= $row['food_not_paid'] ?>
                                    </span>
                                </td>


                                <!-- HOSTEL PAID -->
                                <td>
                                    <span class="status-badge paid">
                                        <?= $row['hostel_paid'] ?>
                                    </span>
                                </td>


                                <!-- HOSTEL PARTIAL -->
                                <td>
                                    <span class="status-badge partial">
                                        <?= $row['hostel_partial'] ?>
                                    </span>
                                </td>


                                <!-- HOSTEL NOT PAID -->
                                <td>
                                    <span class="status-badge not-paid">
                                        <?= $row['hostel_not_paid'] ?>
                                    </span>
                                </td>


                                <!-- MAIZE -->
                                <td>
                                    <?= number_format($row['maize_quantity'], 2) ?> kg
                                </td>


                                <!-- BEANS -->
                                <td>
                                    <?= number_format($row['beans_quantity'], 2) ?> kg
                                </td>


                                <!-- COMBINED -->
                                <td>
                                    <strong class="text-highlight">
                                        <?= number_format($row['combined_total'], 2) ?>
                                    </strong>
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
   CLASS SUMMARY TABLE
   ========================================================= */

.class-summary-table {
    min-width: 1450px;
}

.class-summary-table th,
.class-summary-table td {
    white-space: nowrap;
    vertical-align: middle;
}

.class-summary-table thead th {
    text-align: center;
}

.class-summary-table thead th:first-child,
.class-summary-table tbody td:first-child {
    text-align: left;
}

.class-summary-table tbody td {
    padding: 13px 14px;
}


/* =========================================================
   STATUS BADGES
   ========================================================= */

.status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-width: 34px;
    height: 28px;

    padding: 0 9px;

    border-radius: 5px;

    font-size: 12px;
    font-weight: 700;
}

.status-badge.paid {
    background: #e8f5e9;
    color: #2e7d32;
}

.status-badge.partial {
    background: #fff8e1;
    color: #9a6700;
}

.status-badge.not-paid {
    background: #fdecec;
    color: #b42318;
}


/* =========================================================
   HIGHLIGHT
   ========================================================= */

.text-highlight {
    color: var(--gold);
}


/* =========================================================
   MOBILE / SMALL SCREEN
   ========================================================= */

@media (max-width: 900px) {

    .class-summary-table {
        min-width: 1450px;
    }

}

</style>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>