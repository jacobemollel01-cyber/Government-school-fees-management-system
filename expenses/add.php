<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";

$message = "";

/*
|--------------------------------------------------------------------------
| Get contribution types
|--------------------------------------------------------------------------
*/

$contribution_types = $conn->query("
    SELECT contribution_type_id, name
    FROM contribution_types
    WHERE is_active = TRUE
    ORDER BY name
");


/*
|--------------------------------------------------------------------------
| Save expense
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $contribution_type_id = $_POST["contribution_type_id"];
    $expense_date = $_POST["expense_date"];
    $category = trim($_POST["category"]);
    $description = trim($_POST["description"]);
    $amount = $_POST["amount"];
    $payment_method = trim($_POST["payment_method"]);
    $reference_number = trim($_POST["reference_number"]);
    $supplier_name = trim($_POST["supplier_name"]);
    $recorded_by = "Admin";
    $notes = trim($_POST["notes"]);


    $sql = "INSERT INTO expenses
            (
                contribution_type_id,
                expense_date,
                category,
                description,
                amount,
                payment_method,
                reference_number,
                supplier_name,
                recorded_by,
                notes
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";


    $stmt = $conn->prepare($sql);


    if (!$stmt) {

        $message = "Database error: " . $conn->error;

    } else {

        $stmt->bind_param(
            "isssdsssss",
            $contribution_type_id,
            $expense_date,
            $category,
            $description,
            $amount,
            $payment_method,
            $reference_number,
            $supplier_name,
            $recorded_by,
            $notes
        );


        if ($stmt->execute()) {

            $message = "Expense recorded successfully!";

        } else {

            $message = "Error recording expense: " . $stmt->error;

        }


        $stmt->close();
    }
}


include "../includes/header.php";

?>

<div class="container">

    <div class="form-card">

        <h2>Record Expense</h2>


        <?php if ($message != ""): ?>

            <p class="form-message">
                <?= htmlspecialchars($message); ?>
            </p>

        <?php endif; ?>


        <form method="POST">

            <div class="form-grid">


                <!-- Contribution Source -->

                <div class="form-group">

                    <label for="contribution_type_id">
                        Contribution Source
                    </label>

                    <select
                        id="contribution_type_id"
                        name="contribution_type_id"
                        required
                    >

                        <option value="">
                            Select source
                        </option>

                        <?php while ($type = $contribution_types->fetch_assoc()): ?>

                            <option value="<?= $type['contribution_type_id']; ?>">

                                <?= htmlspecialchars($type['name']); ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>


                <!-- Expense Date -->

                <div class="form-group">

                    <label for="expense_date">
                        Expense Date
                    </label>

                    <input
                        type="date"
                        id="expense_date"
                        name="expense_date"
                        value="<?= date('Y-m-d'); ?>"
                        required
                    >

                </div>


                <!-- Category -->

                <div class="form-group">

                    <label for="category">
                        Category
                    </label>

                    <input
                        type="text"
                        id="category"
                        name="category"
                        placeholder="e.g. Food Purchase"
                        required
                    >

                </div>


                <!-- Amount -->

                <div class="form-group">

                    <label for="amount">
                        Amount (TZS)
                    </label>

                    <input
                        type="number"
                        id="amount"
                        name="amount"
                        placeholder="e.g. 250000"
                        min="0"
                        step="0.01"
                        required
                    >

                </div>


                <!-- Payment Method -->

                <div class="form-group">

                    <label for="payment_method">
                        Payment Method
                    </label>

                    <select
                        id="payment_method"
                        name="payment_method"
                        required
                    >

                        <option value="">
                            Select method
                        </option>

                        <option value="Cash">
                            Cash
                        </option>

                        <option value="Bank">
                            Bank
                        </option>

                        <option value="Mobile Money">
                            Mobile Money
                        </option>

                        <option value="Other">
                            Other
                        </option>

                    </select>

                </div>


                <!-- Reference -->

                <div class="form-group">

                    <label for="reference_number">
                        Reference Number
                    </label>

                    <input
                        type="text"
                        id="reference_number"
                        name="reference_number"
                        placeholder="Optional reference"
                    >

                </div>


                <!-- Supplier -->

                <div class="form-group">

                    <label for="supplier_name">
                        Supplier / Payee
                    </label>

                    <input
                        type="text"
                        id="supplier_name"
                        name="supplier_name"
                        placeholder="Supplier or person paid"
                    >

                </div>


                <!-- Description -->

                <div class="form-group full-width">

                    <label for="description">
                        Description
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        placeholder="Describe what the money was used for..."
                        required
                    ></textarea>

                </div>


                <!-- Notes -->

                <div class="form-group full-width">

                    <label for="notes">
                        Notes
                    </label>

                    <textarea
                        id="notes"
                        name="notes"
                        placeholder="Additional information about this expense..."
                    ></textarea>

                </div>

            </div>


            <!-- Buttons -->

            <div class="form-actions">

                <button type="submit">
                    Record Expense
                </button>

                <a href="index.php" class="btn">
                    Back to Expenses
                </a>

            </div>

        </form>

    </div>

</div>