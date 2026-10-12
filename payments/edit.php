```php
<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";

$message = "";


/*
|--------------------------------------------------------------------------
| Get payment ID
|--------------------------------------------------------------------------
*/

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {

    die("Invalid payment ID.");

}

$payment_id = (int) $_GET["id"];


/*
|--------------------------------------------------------------------------
| Get existing payment
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        payment_id,
        student_id,
        contribution_type_id,
        amount,
        payment_date,
        payment_period,
        payment_method,
        payment_reference,
        payer_name,
        notes,
        recorded_by,
        status
    FROM payments
    WHERE payment_id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $payment_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {

    die("Payment not found.");

}

$payment = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Get active students
|--------------------------------------------------------------------------
*/

$students = $conn->query("
    SELECT student_id, admission_no, full_name
    FROM students
    WHERE status = 'Active'
       OR student_id = {$payment['student_id']}
    ORDER BY full_name
");


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
| Update payment
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $student_id = (int) $_POST["student_id"];
    $contribution_type_id = (int) $_POST["contribution_type_id"];
    $amount = (float) $_POST["amount"];
    $payment_date = $_POST["payment_date"];
    $payment_period = trim($_POST["payment_period"]);
    $payment_method = trim($_POST["payment_method"]);
    $payment_reference = trim($_POST["payment_reference"]);
    $payer_name = trim($_POST["payer_name"]);
    $notes = trim($_POST["notes"]);


    /*
    |--------------------------------------------------------------------------
    | Basic validation
    |--------------------------------------------------------------------------
    */

    if ($amount <= 0) {

        $message = "Payment amount must be greater than 0.";

    } elseif ($payment_date == "") {

        $message = "Please select a payment date.";

    } elseif ($payment_period == "") {

        $message = "Please enter the payment period.";

    } elseif ($payment_method == "") {

        $message = "Please select a payment method.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | Update payment information
        |
        | IMPORTANT:
        | status and void information are NOT changed here.
        | A voided payment remains voided after editing.
        |--------------------------------------------------------------------------
        */

        $sql = "
            UPDATE payments
            SET
                student_id = ?,
                contribution_type_id = ?,
                amount = ?,
                payment_date = ?,
                payment_period = ?,
                payment_method = ?,
                payment_reference = ?,
                payer_name = ?,
                notes = ?
            WHERE payment_id = ?
        ";


        $stmt = $conn->prepare($sql);


        if (!$stmt) {

            $message = "Database error: " . $conn->error;

        } else {

            $stmt->bind_param(
                "iidssssssi",
                $student_id,
                $contribution_type_id,
                $amount,
                $payment_date,
                $payment_period,
                $payment_method,
                $payment_reference,
                $payer_name,
                $notes,
                $payment_id
            );


            if ($stmt->execute()) {

                header("Location: index.php?updated=1");
                exit;

            } else {

                $message = "Error updating payment: " . $stmt->error;

            }


            $stmt->close();

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Keep submitted values if validation fails
    |--------------------------------------------------------------------------
    */

    $payment["student_id"] = $student_id;
    $payment["contribution_type_id"] = $contribution_type_id;
    $payment["amount"] = $amount;
    $payment["payment_date"] = $payment_date;
    $payment["payment_period"] = $payment_period;
    $payment["payment_method"] = $payment_method;
    $payment["payment_reference"] = $payment_reference;
    $payment["payer_name"] = $payer_name;
    $payment["notes"] = $notes;

}


include "../includes/header.php";

?>

<div class="container">

    <div class="form-card">

        <h2>Edit Payment</h2>


        <?php if ($message != ""): ?>

            <p class="form-message">
                <?= htmlspecialchars($message); ?>
            </p>

        <?php endif; ?>


        <?php if ($payment["status"] == "Voided"): ?>

            <p class="form-message">
                This payment is currently VOIDED.
                Editing it will not reactivate the payment.
            </p>

        <?php endif; ?>


        <form method="POST">

            <div class="form-grid">


                <!-- Student -->

                <div class="form-group">

                    <label for="student_id">
                        Student
                    </label>

                    <select
                        id="student_id"
                        name="student_id"
                        required
                    >

                        <option value="">
                            Select student
                        </option>


                        <?php while ($student = $students->fetch_assoc()): ?>

                            <option
                                value="<?= $student['student_id']; ?>"
                                <?= ($student['student_id'] == $payment['student_id']) ? 'selected' : ''; ?>
                            >

                                <?= htmlspecialchars(
                                    $student['admission_no'] . " - " . $student['full_name']
                                ); ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>


                <!-- Contribution Type -->

                <div class="form-group">

                    <label for="contribution_type_id">
                        Contribution Type
                    </label>

                    <select
                        id="contribution_type_id"
                        name="contribution_type_id"
                        required
                    >

                        <option value="">
                            Select contribution
                        </option>


                        <?php while ($type = $contribution_types->fetch_assoc()): ?>

                            <option
                                value="<?= $type['contribution_type_id']; ?>"
                                <?= ($type['contribution_type_id'] == $payment['contribution_type_id']) ? 'selected' : ''; ?>
                            >

                                <?= htmlspecialchars($type['name']); ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>


                <!-- Amount -->

                <div class="form-group">

                    <label for="amount">
                        Amount
                    </label>

                    <input
                        type="number"
                        id="amount"
                        name="amount"
                        value="<?= htmlspecialchars($payment['amount']); ?>"
                        min="0"
                        step="0.01"
                        required
                    >

                </div>


                <!-- Payment Date -->

                <div class="form-group">

                    <label for="payment_date">
                        Payment Date
                    </label>

                    <input
                        type="date"
                        id="payment_date"
                        name="payment_date"
                        value="<?= htmlspecialchars($payment['payment_date']); ?>"
                        required
                    >

                </div>


                <!-- Payment Period -->

                <div class="form-group">

                    <label for="payment_period">
                        Payment Period
                    </label>

                    <input
                        type="text"
                        id="payment_period"
                        name="payment_period"
                        value="<?= htmlspecialchars($payment['payment_period'] ?? ''); ?>"
                        placeholder="e.g. Annual, Term 1, January"
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
                            Select payment method
                        </option>

                        <option
                            value="Cash"
                            <?= ($payment['payment_method'] == 'Cash') ? 'selected' : ''; ?>
                        >
                            Cash
                        </option>

                        <option
                            value="Bank"
                            <?= ($payment['payment_method'] == 'Bank') ? 'selected' : ''; ?>
                        >
                            Bank
                        </option>

                        <option
                            value="Mobile Money"
                            <?= ($payment['payment_method'] == 'Mobile Money') ? 'selected' : ''; ?>
                        >
                            Mobile Money
                        </option>

                        <option
                            value="Other"
                            <?= ($payment['payment_method'] == 'Other') ? 'selected' : ''; ?>
                        >
                            Other
                        </option>

                    </select>

                </div>


                <!-- Payment Reference -->

                <div class="form-group">

                    <label for="payment_reference">
                        Payment Reference
                    </label>

                    <input
                        type="text"
                        id="payment_reference"
                        name="payment_reference"
                        value="<?= htmlspecialchars($payment['payment_reference'] ?? ''); ?>"
                        placeholder="Optional bank/mobile money reference"
                    >

                </div>


                <!-- Payer Name -->

                <div class="form-group">

                    <label for="payer_name">
                        Payer Name
                    </label>

                    <input
                        type="text"
                        id="payer_name"
                        name="payer_name"
                        value="<?= htmlspecialchars($payment['payer_name'] ?? ''); ?>"
                        placeholder="Name of person or sponsor"
                    >

                </div>


                <!-- Notes -->

                <div class="form-group full-width">

                    <label for="notes">
                        Notes
                    </label>

                    <textarea
                        id="notes"
                        name="notes"
                        placeholder="Additional information about this payment..."
                    ><?= htmlspecialchars($payment['notes'] ?? ''); ?></textarea>

                </div>

            </div>


            <!-- Buttons -->

            <div class="form-actions">

                <button type="submit">
                    Update Payment
                </button>

                <a href="index.php" class="btn">
                    Back to Payments
                </a>

            </div>

        </form>

    </div>

</div>
```
