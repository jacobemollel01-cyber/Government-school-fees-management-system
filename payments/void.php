<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";


// ---------------------------------------------------------
// Get payment ID
// ---------------------------------------------------------

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

    header("Location: index.php");
    exit;

}

$payment_id = (int) $_GET['id'];


// ---------------------------------------------------------
// Get payment information
// ---------------------------------------------------------

$sql = "SELECT
            p.payment_id,
            p.amount,
            p.payment_date,
            p.payment_period,
            p.payment_method,
            p.payment_reference,
            p.payer_name,
            p.status,

            s.admission_no,
            s.full_name,

            ct.name AS contribution

        FROM payments p

        JOIN students s
            ON p.student_id = s.student_id

        JOIN contribution_types ct
            ON p.contribution_type_id = ct.contribution_type_id

        WHERE p.payment_id = ?";


$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $payment_id);

$stmt->execute();

$result = $stmt->get_result();

$payment = $result->fetch_assoc();

$stmt->close();


// ---------------------------------------------------------
// Payment not found
// ---------------------------------------------------------

if (!$payment) {

    die("Payment not found.");

}


// ---------------------------------------------------------
// Prevent already voided payment from being voided again
// ---------------------------------------------------------

if ($payment['status'] === 'Voided') {

    header("Location: index.php");
    exit;

}


// ---------------------------------------------------------
// Process VOID
// ---------------------------------------------------------

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $voided_by = trim($_POST['voided_by'] ?? '');
    $void_reason = trim($_POST['void_reason'] ?? '');


    // Validate name

    if ($voided_by === '') {

        $error = "Please enter your name.";

    }


    // Validate reason

    elseif ($void_reason === '') {

        $error = "Please enter a reason for voiding this payment.";

    }


    else {

        $update_sql = "UPDATE payments

                       SET
                           status = 'Voided',
                           voided_by = ?,
                           voided_at = NOW(),
                           void_reason = ?

                       WHERE payment_id = ?
                       AND status = 'Active'";


        $update_stmt = $conn->prepare($update_sql);

        $update_stmt->bind_param(
            "ssi",
            $voided_by,
            $void_reason,
            $payment_id
        );


        if ($update_stmt->execute()) {

            $update_stmt->close();

            header("Location: index.php?voided=1");
            exit;

        } else {

            $error = "Unable to void this payment. Please try again.";

        }

        $update_stmt->close();

    }

}


?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Void Payment</title>

    <link
        rel="stylesheet"
        href="/school-fees/css/style.css"
    >

</head>


<body>


<div class="container">


    <div class="form-card">

        <div class="dashboard-header">

            <h1>Void Payment</h1>

            <p class="dashboard-subtitle">

                You are about to void the following payment.

            </p>

        </div>


        <?php if ($error): ?>

            <div class="form-message">

                <?= htmlspecialchars($error); ?>

            </div>

        <?php endif; ?>


        <!-- Payment Information -->

        <div class="dashboard-table-wrapper">

            <table>

                <tbody>

                    <tr>

                        <th>Payment ID</th>

                        <td>
                            <?= htmlspecialchars(
                                $payment['payment_id']
                            ); ?>
                        </td>

                    </tr>


                    <tr>

                        <th>Admission No.</th>

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $payment['admission_no']
                                ); ?>
                            </strong>

                        </td>

                    </tr>


                    <tr>

                        <th>Student</th>

                        <td>
                            <?= htmlspecialchars(
                                $payment['full_name']
                            ); ?>
                        </td>

                    </tr>


                    <tr>

                        <th>Contribution</th>

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $payment['contribution']
                                ); ?>
                            </strong>

                        </td>

                    </tr>


                    <tr>

                        <th>Amount</th>

                        <td>

                            <strong class="payment-amount">

                                TZS
                                <?= number_format(
                                    (float) $payment['amount'],
                                    2
                                ); ?>

                            </strong>

                        </td>

                    </tr>


                    <tr>

                        <th>Payment Date</th>

                        <td>
                            <?= htmlspecialchars(
                                $payment['payment_date']
                            ); ?>
                        </td>

                    </tr>


                    <tr>

                        <th>Payment Method</th>

                        <td>

                            <?= !empty($payment['payment_method'])
                                ? htmlspecialchars(
                                    $payment['payment_method']
                                )
                                : '—';
                            ?>

                        </td>

                    </tr>


                    <tr>

                        <th>Reference</th>

                        <td>

                            <?= !empty($payment['payment_reference'])
                                ? htmlspecialchars(
                                    $payment['payment_reference']
                                )
                                : '—';
                            ?>

                        </td>

                    </tr>


                    <tr>

                        <th>Payer</th>

                        <td>

                            <?= !empty($payment['payer_name'])
                                ? htmlspecialchars(
                                    $payment['payer_name']
                                )
                                : '—';
                            ?>

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>


        <br>


        <!-- Warning -->

        <div class="form-message">

            <strong>Important:</strong>

            This payment will NOT be deleted.

            It will be marked as

            <strong>VOIDED</strong>

            and will no longer be included in payment totals
            and reports.

        </div>


        <!-- Void Form -->

        <form method="POST">


            <div class="form-grid">


                <!-- Person voiding -->

                <div class="form-group">

                    <label for="voided_by">
                        Your Name
                    </label>

                    <input
                        type="text"
                        id="voided_by"
                        name="voided_by"
                        placeholder="Enter your name"
                        required
                    >

                </div>


                <!-- Reason -->

                <div class="form-group">

                    <label for="void_reason">
                        Reason for Voiding
                    </label>

                    <input
                        type="text"
                        id="void_reason"
                        name="void_reason"
                        placeholder="e.g. Wrong student selected"
                        maxlength="255"
                        required
                    >

                </div>


            </div>


            <!-- Buttons -->

            <div class="form-actions">


                <button
                    type="submit"
                    class="void-confirm-btn"
                    onclick="return confirm(
                        'Are you sure you want to void this payment?'
                    );"
                >

                    Confirm Void

                </button>


                <a
                    href="index.php"
                    class="action-btn secondary"
                >

                    Cancel

                </a>


            </div>


        </form>


    </div>


</div>


</body>

</html>