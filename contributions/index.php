<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";
require_once "../includes/header.php";


$sql = "
    SELECT
        fc.food_contribution_id,
        s.admission_no,
        s.full_name,
        ct.name AS contribution,
        fc.quantity,
        fc.unit,
        fc.contribution_date,
        fc.reference_number,
        fc.notes,
        fc.recorded_by,
        fc.status,
        fc.voided_by,
        fc.voided_at,
        fc.void_reason

    FROM food_contributions fc

    INNER JOIN students s
        ON fc.student_id = s.student_id

    INNER JOIN contribution_types ct
        ON fc.contribution_type_id = ct.contribution_type_id

    ORDER BY
        fc.contribution_date DESC,
        fc.food_contribution_id DESC
";


$result = $conn->query($sql);

?>


<div class="container">


    <!-- PAGE HEADER -->

    <div class="dashboard-header">

        <h1>Maize & Beans Contributions</h1>

        <p class="dashboard-subtitle">
            View recorded maize and beans contributions.
        </p>

    </div>


    <!-- RECORD CONTRIBUTION -->

    <div class="quick-actions">

        <a
            href="add.php"
            class="action-btn primary"
        >
            Record Contribution
        </a>

    </div>


    <br>


    <!-- CONTRIBUTIONS TABLE -->

    <div class="dashboard-table-wrapper">

        <table>

            <thead>

                <tr>

                    <th>ID</th>
                    <th>Student</th>
                    <th>Contribution</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Notes</th>
                    <th>Recorded By</th>
                    <th>Status</th>
                    <th>Actions</th>

                </tr>

            </thead>


            <tbody>


                <?php if ($result && $result->num_rows > 0): ?>


                    <?php while ($row = $result->fetch_assoc()): ?>


                        <?php

                        $is_voided = ($row['status'] === 'Voided');

                        ?>


                        <tr
                            class="<?= $is_voided ? 'payment-voided-row' : ''; ?>"
                        >


                            <!-- ID -->

                            <td>

                                <?= (int)$row['food_contribution_id']; ?>

                            </td>


                            <!-- STUDENT -->

                            <td>

                                <?= htmlspecialchars(
                                    $row['admission_no']
                                ); ?>

                                -

                                <?= htmlspecialchars(
                                    $row['full_name']
                                ); ?>

                            </td>


                            <!-- CONTRIBUTION -->

                            <td>

                                <?= htmlspecialchars(
                                    $row['contribution']
                                ); ?>

                            </td>


                            <!-- QUANTITY -->

                            <td>

                                <?= number_format(
                                    (float)$row['quantity'],
                                    2
                                ); ?>

                            </td>


                            <!-- UNIT -->

                            <td>

                                <?= htmlspecialchars(
                                    $row['unit']
                                ); ?>

                            </td>


                            <!-- DATE -->

                            <td>

                                <?= htmlspecialchars(
                                    $row['contribution_date']
                                ); ?>

                            </td>


                            <!-- REFERENCE -->

                            <td>

                                <?= htmlspecialchars(
                                    $row['reference_number'] ?? ''
                                ); ?>

                            </td>


                            <!-- NOTES -->

                            <td>

                                <?= htmlspecialchars(
                                    $row['notes'] ?? ''
                                ); ?>

                            </td>


                            <!-- RECORDED BY -->

                            <td>

                                <?= htmlspecialchars(
                                    $row['recorded_by'] ?? ''
                                ); ?>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <?php if ($is_voided): ?>

                                    <span class="payment-status voided">
                                        VOIDED
                                    </span>

                                <?php else: ?>

                                    <span class="payment-status active">
                                        ACTIVE
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <?php if (!$is_voided): ?>

                                    <div class="payment-actions">


                                        <a
                                            href="edit.php?id=<?= (int)$row['food_contribution_id']; ?>"
                                            class="table-action edit"
                                        >
                                            Edit
                                        </a>


                                        <a
                                            href="void.php?id=<?= (int)$row['food_contribution_id']; ?>"
                                            class="table-action void"
                                        >
                                            Void
                                        </a>


                                    </div>


                                <?php else: ?>


                                    <div class="void-details">

                                        <strong>
                                            Voided
                                        </strong>

                                        <?php if (!empty($row['voided_by'])): ?>

                                            <small>
                                                By:
                                                <?= htmlspecialchars(
                                                    $row['voided_by']
                                                ); ?>
                                            </small>

                                        <?php endif; ?>


                                        <?php if (!empty($row['voided_at'])): ?>

                                            <small>
                                                At:
                                                <?= htmlspecialchars(
                                                    $row['voided_at']
                                                ); ?>
                                            </small>

                                        <?php endif; ?>


                                        <?php if (!empty($row['void_reason'])): ?>

                                            <small>
                                                Reason:
                                                <?= htmlspecialchars(
                                                    $row['void_reason']
                                                ); ?>
                                            </small>

                                        <?php endif; ?>

                                    </div>


                                <?php endif; ?>

                            </td>


                        </tr>


                    <?php endwhile; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="11"
                            style="text-align: center; padding: 30px;"
                        >

                            No maize or beans contributions
                            recorded yet.

                        </td>

                    </tr>


                <?php endif; ?>


            </tbody>

        </table>

    </div>


</div>