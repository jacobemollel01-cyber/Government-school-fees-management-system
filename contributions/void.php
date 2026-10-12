<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";


/* =========================
   CHECK CONTRIBUTION ID
========================= */

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

    header("Location: index.php");
    exit;

}


$food_contribution_id = (int) $_GET['id'];


/* =========================
   GET CONTRIBUTION DETAILS
========================= */

$sql = "
    SELECT
        fc.food_contribution_id,
        fc.quantity,
        fc.unit,
        fc.contribution_date,
        fc.reference_number,
        fc.notes,
        fc.status,

        s.admission_no,
        s.full_name,

        ct.name AS contribution

    FROM food_contributions fc

    INNER JOIN students s
        ON fc.student_id = s.student_id

    INNER JOIN contribution_types ct
        ON fc.contribution_type_id = ct.contribution_type_id

    WHERE fc.food_contribution_id = ?
";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $food_contribution_id
);

$stmt->execute();

$result = $stmt->get_result();

$record = $result->fetch_assoc();


/* =========================
   RECORD NOT FOUND
========================= */

if (!$record) {

    header("Location: index.php");
    exit;

}


/* =========================
   ALREADY VOIDED
========================= */

if ($record['status'] === 'Voided') {

    header("Location: index.php");
    exit;

}


/* =========================
   PROCESS VOID
========================= */

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    $voided_by = trim($_POST['voided_by'] ?? '');

    $void_reason = trim($_POST['void_reason'] ?? '');


    /* VALIDATION */

    if ($voided_by === '') {

        $error = "Please enter the name of the person voiding this contribution.";

    } elseif ($void_reason === '') {

        $error = "Please provide a reason for voiding this contribution.";

    } else {


        /* UPDATE CONTRIBUTION */

        $update_sql = "
            UPDATE food_contributions

            SET
                status = 'Voided',
                voided_by = ?,
                voided_at = NOW(),
                void_reason = ?

            WHERE food_contribution_id = ?

            AND status = 'Active'
        ";


        $update_stmt = $conn->prepare($update_sql);

        $update_stmt->bind_param(
            "ssi",
            $voided_by,
            $void_reason,
            $food_contribution_id
        );


        if ($update_stmt->execute()) {

            header("Location: index.php?voided=1");
            exit;

        } else {

            $error = "Unable to void the contribution. Please try again.";

        }

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

    <title>Void Contribution</title>

    <link
        rel="stylesheet"
        href="/school-fees/css/style.css"
    >

</head>


<body>


<div class="container">


    <div class="dashboard-header">

        <h1>Void Contribution</h1>

        <p class="dashboard-subtitle">
            Mark an incorrect maize or beans contribution as voided.
        </p>

    </div>


    <?php if ($error !== ''): ?>

        <div class="form-error">

            <?= htmlspecialchars($error); ?>

        </div>

    <?php endif; ?>


    <!-- CONTRIBUTION DETAILS -->

    <div class="dashboard-table-wrapper">

        <table>

            <tr>

                <th>ID</th>

                <td>
                    <?= (int)$record['food_contribution_id']; ?>
                </td>

            </tr>


            <tr>

                <th>Student</th>

                <td>

                    <?= htmlspecialchars(
                        $record['admission_no']
                    ); ?>

                    -

                    <?= htmlspecialchars(
                        $record['full_name']
                    ); ?>

                </td>

            </tr>


            <tr>

                <th>Contribution</th>

                <td>

                    <?= htmlspecialchars(
                        $record['contribution']
                    ); ?>

                </td>

            </tr>


            <tr>

                <th>Quantity</th>

                <td>

                    <?= number_format(
                        (float)$record['quantity'],
                        2
                    ); ?>

                    <?= htmlspecialchars(
                        $record['unit']
                    ); ?>

                </td>

            </tr>


            <tr>

                <th>Date</th>

                <td>

                    <?= htmlspecialchars(
                        $record['contribution_date']
                    ); ?>

                </td>

            </tr>


            <tr>

                <th>Reference</th>

                <td>

                    <?= htmlspecialchars(
                        $record['reference_number'] ?? ''
                    ); ?>

                </td>

            </tr>


            <tr>

                <th>Notes</th>

                <td>

                    <?= htmlspecialchars(
                        $record['notes'] ?? ''
                    ); ?>

                </td>

            </tr>

        </table>

    </div>


    <br>


    <!-- VOID FORM -->

    <div class="form-container">


        <h2>Void This Contribution</h2>


        <p>

            This will keep the original contribution in the system
            but mark it as <strong>VOIDED</strong>.

            It will no longer be included in contribution totals.

        </p>


        <form
            method="POST"
            onsubmit="return confirm(
                'Are you sure you want to void this contribution?'
            );"
        >


            <!-- VOIDED BY -->

            <div class="form-group">

                <label for="voided_by">
                    Voided By
                </label>

                <input
                    type="text"
                    id="voided_by"
                    name="voided_by"
                    placeholder="Enter your name"
                    required
                >

            </div>


            <!-- REASON -->

            <div class="form-group">

                <label for="void_reason">
                    Reason for Voiding
                </label>

                <textarea
                    id="void_reason"
                    name="void_reason"
                    rows="4"
                    placeholder="Explain why this contribution is being voided"
                    required
                ></textarea>

            </div>


            <!-- BUTTONS -->

            <div class="quick-actions">


                <button
                    type="submit"
                    class="action-btn danger"
                >
                    Void Contribution
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