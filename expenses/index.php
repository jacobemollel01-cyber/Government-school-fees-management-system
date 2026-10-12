<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";
require_once "../includes/header.php";


$sql = "
    SELECT
        e.expense_id,
        e.expense_date,
        e.category,
        e.description,
        e.amount,
        e.payment_method,
        e.reference_number,
        e.supplier_name,
        ct.name AS contribution_source,
        e.recorded_by
    FROM expenses e
    LEFT JOIN contribution_types ct
        ON e.contribution_type_id = ct.contribution_type_id
    ORDER BY e.expense_date DESC, e.expense_id DESC
";


$result = $conn->query($sql);

if (!$result) {
    die("Database error: " . $conn->error);
}

?>

<div class="container">

    <h1>Expenses</h1>

    <a href="add.php">
        <button type="button">Record Expense</button>
    </a>

    <br><br>

    <table>

        <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Category</th>
            <th>Description</th>
            <th>Amount</th>
            <th>Payment Method</th>
            <th>Reference</th>
            <th>Supplier</th>
            <th>Source</th>
            <th>Recorded By</th>
        </tr>


        <?php if ($result->num_rows > 0): ?>

            <?php while ($row = $result->fetch_assoc()): ?>

                <tr>

                    <td>
                        <?= $row['expense_id']; ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['expense_date']); ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['category']); ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['description']); ?>
                    </td>

                    <td>
                        <?= number_format((float)$row['amount'], 2); ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['payment_method'] ?? ''); ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['reference_number'] ?? ''); ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['supplier_name'] ?? ''); ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['contribution_source'] ?? 'General'); ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['recorded_by'] ?? ''); ?>
                    </td>

                </tr>

            <?php endwhile; ?>

        <?php else: ?>

            <tr>

                <td colspan="10">
                    No expenses recorded yet.
                </td>

            </tr>

        <?php endif; ?>

    </table>

</div>