<?php

require_once "../config/db.php";
require_once "../includes/header.php";


/* =========================
   STUDENT PAYMENT STATUS
========================= */

$sql = "

SELECT

    sr.requirement_id,

    s.admission_no,
    s.full_name,
    s.class_name,
    s.stream,

    ct.name AS contribution,

    sr.academic_year,
    sr.period_type,
    sr.period_name,

    sr.amount_due,

    COALESCE(
        (
            SELECT SUM(p.amount)

            FROM payments p

            WHERE p.student_id = sr.student_id

            AND p.contribution_type_id = sr.contribution_type_id

            AND p.payment_period = sr.period_name

            AND p.status = 'Active'

        ),
        0
    ) AS amount_paid

FROM student_requirements sr

JOIN students s
    ON sr.student_id = s.student_id

JOIN contribution_types ct
    ON sr.contribution_type_id = ct.contribution_type_id

ORDER BY
    s.full_name,
    sr.academic_year,
    ct.name

";


$result = $conn->query($sql);

?>


<div class="container">


    <!-- =========================
         PAGE HEADER
    ========================= -->

    <div class="dashboard-header">

        <h1>Student Contribution Status</h1>

        <p class="dashboard-subtitle">
            View student contribution requirements, payments
            and outstanding balances.
        </p>

    </div>


    <!-- =========================
         STATUS TABLE
    ========================= -->

    <div class="dashboard-table-wrapper">

        <table class="student-status-table">

            <thead>

                <tr>

                    <th>Admission No.</th>
                    <th>Student</th>
                    <th>Class</th>
                    <th>Stream</th>
                    <th>Contribution</th>
                    <th>Year</th>
                    <th>Period</th>
                    <th>Amount Due</th>
                    <th>Amount Paid</th>
                    <th>Balance</th>
                    <th>Status</th>

                </tr>

            </thead>


            <tbody>

                <?php if ($result && $result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>


                        <?php

                        $amount_due = (float) $row['amount_due'];

                        $amount_paid = (float) $row['amount_paid'];

                        $balance = $amount_due - $amount_paid;


                        /* =========================
                           PAYMENT STATUS
                        ========================= */

                        if ($amount_paid <= 0) {

                            $status = "NOT PAID";

                        } elseif ($amount_paid < $amount_due) {

                            $status = "PARTIALLY PAID";

                        } elseif ($amount_paid == $amount_due) {

                            $status = "FULLY PAID";

                        } else {

                            $status = "OVERPAID";

                        }

                        ?>


                        <tr>


                            <!-- Admission Number -->

                            <td>

                                <?= htmlspecialchars(
                                    $row['admission_no']
                                ); ?>

                            </td>


                            <!-- Student -->

                            <td>

                                <?= htmlspecialchars(
                                    $row['full_name']
                                ); ?>

                            </td>


                            <!-- Class -->

                            <td>

                                <?= htmlspecialchars(
                                    $row['class_name']
                                ); ?>

                            </td>


                            <!-- Stream -->

                            <td>

                                <?= htmlspecialchars(
                                    $row['stream'] ?? ''
                                ); ?>

                            </td>


                            <!-- Contribution -->

                            <td>

                                <?= htmlspecialchars(
                                    $row['contribution']
                                ); ?>

                            </td>


                            <!-- Academic Year -->

                            <td>

                                <?= htmlspecialchars(
                                    $row['academic_year']
                                ); ?>

                            </td>


                            <!-- Period -->

                            <td>

                                <?= htmlspecialchars(
                                    $row['period_type']
                                ); ?>


                                <?php if (!empty($row['period_name'])): ?>

                                    <br>

                                    <small>

                                        <?= htmlspecialchars(
                                            $row['period_name']
                                        ); ?>

                                    </small>

                                <?php endif; ?>

                            </td>


                            <!-- Amount Due -->

                            <td>

                                TZS
                                <?= number_format(
                                    $amount_due,
                                    2
                                ); ?>

                            </td>


                            <!-- Amount Paid -->

                            <td>

                                TZS
                                <?= number_format(
                                    $amount_paid,
                                    2
                                ); ?>

                            </td>


                            <!-- Balance -->

                            <td>

                                <?php if ($balance > 0): ?>

                                    <strong>

                                        TZS
                                        <?= number_format(
                                            $balance,
                                            2
                                        ); ?>

                                    </strong>


                                <?php elseif ($balance < 0): ?>

                                    <strong>

                                        TZS
                                        <?= number_format(
                                            abs($balance),
                                            2
                                        ); ?>

                                    </strong>


                                <?php else: ?>

                                    TZS 0.00

                                <?php endif; ?>

                            </td>


                            <!-- Status -->

                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $status
                                    ); ?>

                                </strong>

                            </td>


                        </tr>


                    <?php endwhile; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="11"
                            style="text-align: center; padding: 30px;"
                        >

                            No student contribution
                            requirements found.

                        </td>

                    </tr>


                <?php endif; ?>

            </tbody>

        </table>

    </div>


    <!-- =========================
         BOTTOM ACTIONS
    ========================= -->

    <div
        class="quick-actions"
        style="margin-top: 25px;"
    >

        <a
            href="../students/index.php"
            class="action-btn secondary"
        >
            Students
        </a>


        <a
            href="../payments/index.php"
            class="action-btn secondary"
        >
            Payments
        </a>

    </div>


</div>