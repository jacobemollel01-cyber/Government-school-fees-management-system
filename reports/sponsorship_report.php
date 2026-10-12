
<?php

require_once "../config/db.php";
require_once "../includes/header.php";


$sql = "
    SELECT
        sp.sponsorship_id,
        s.sponsor_name,
        st.admission_no,
        st.full_name,
        ct.name AS contribution,
        sp.academic_year,
        sp.amount_committed,

        COALESCE(
            (
                SELECT SUM(p.amount)
                FROM payments p
                WHERE p.student_id = sp.student_id
                  AND p.contribution_type_id = sp.contribution_type_id
                  AND p.payer_name = s.sponsor_name
                  AND YEAR(p.payment_date) = sp.academic_year
                  AND p.status = 'Active'
            ),
            0
        ) AS amount_paid

    FROM sponsorships sp

    INNER JOIN sponsors s
        ON sp.sponsor_id = s.sponsor_id

    INNER JOIN students st
        ON sp.student_id = st.student_id

    INNER JOIN contribution_types ct
        ON sp.contribution_type_id = ct.contribution_type_id

    ORDER BY s.sponsor_name, st.full_name
";


$result = $conn->query($sql);

if (!$result) {
    die("Database error: " . $conn->error);
}

?>

<div class="container">

    <h1>Sponsorship Report</h1>

    <br>

    <table>

        <tr>
            <th>Sponsor</th>
            <th>Student</th>
            <th>Contribution</th>
            <th>Academic Year</th>
            <th>Committed</th>
            <th>Paid</th>
            <th>Balance</th>
            <th>Status</th>
        </tr>


        <?php if ($result->num_rows > 0): ?>

            <?php while ($row = $result->fetch_assoc()): ?>

                <?php

                $committed = (float) $row['amount_committed'];
                $paid = (float) $row['amount_paid'];

                $balance = $committed - $paid;


                if ($paid <= 0) {

                    $status = "NOT PAID";

                } elseif ($paid < $committed) {

                    $status = "PARTIALLY PAID";

                } elseif ($paid == $committed) {

                    $status = "FULLY PAID";

                } else {

                    $status = "OVERPAID";

                }

                ?>

                <tr>

                    <td>
                        <?= htmlspecialchars($row['sponsor_name']); ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['admission_no']); ?>
                        -
                        <?= htmlspecialchars($row['full_name']); ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['contribution']); ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['academic_year']); ?>
                    </td>

                    <td>
                        <?= number_format($committed, 2); ?>
                    </td>

                    <td>
                        <?= number_format($paid, 2); ?>
                    </td>

                    <td>
                        <?= number_format($balance, 2); ?>
                    </td>

                    <td>
                        <?= $status; ?>
                    </td>

                </tr>

            <?php endwhile; ?>

        <?php else: ?>

            <tr>

                <td colspan="8">
                    No sponsorship records found.
                </td>

            </tr>

        <?php endif; ?>

    </table>

</div>
```
