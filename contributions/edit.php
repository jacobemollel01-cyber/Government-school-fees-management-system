
<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";

$message = "";


/*
|--------------------------------------------------------------------------
| Get contribution ID
|--------------------------------------------------------------------------
*/

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {

    die("Invalid contribution ID.");

}

$food_contribution_id = (int) $_GET["id"];


/*
|--------------------------------------------------------------------------
| Get existing contribution
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        food_contribution_id,
        student_id,
        contribution_type_id,
        quantity,
        unit,
        contribution_date,
        reference_number,
        notes,
        recorded_by,
        status
    FROM food_contributions
    WHERE food_contribution_id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $food_contribution_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {

    die("Contribution not found.");

}

$contribution = $result->fetch_assoc();

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
       OR student_id = {$contribution['student_id']}
    ORDER BY full_name
");


/*
|--------------------------------------------------------------------------
| Get Maize and Beans
|--------------------------------------------------------------------------
*/

$contribution_types = $conn->query("
    SELECT contribution_type_id, name
    FROM contribution_types
    WHERE is_active = TRUE
      AND name IN ('Maize', 'Beans')
       OR contribution_type_id = {$contribution['contribution_type_id']}
    ORDER BY name
");


/*
|--------------------------------------------------------------------------
| Update contribution
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $student_id = (int) $_POST["student_id"];
    $contribution_type_id = (int) $_POST["contribution_type_id"];
    $quantity = (float) $_POST["quantity"];
    $unit = trim($_POST["unit"]);
    $contribution_date = $_POST["contribution_date"];
    $reference_number = trim($_POST["reference_number"]);
    $notes = trim($_POST["notes"]);


    /*
    |----------------------------------------------------------------------
    | Basic validation
    |----------------------------------------------------------------------
    */

    if ($quantity <= 0) {

        $message = "Quantity must be greater than 0.";

    } elseif ($unit == "") {

        $message = "Please select a unit.";

    } elseif ($contribution_date == "") {

        $message = "Please select a contribution date.";

    } else {


        /*
        |------------------------------------------------------------------
        | Update only the contribution information.
        |
        | IMPORTANT:
        | status, voided_by, voided_at and void_reason are NOT changed.
        | Therefore, editing a voided contribution will NOT reactivate it.
        |------------------------------------------------------------------
        */

        $sql = "
            UPDATE food_contributions
            SET
                student_id = ?,
                contribution_type_id = ?,
                quantity = ?,
                unit = ?,
                contribution_date = ?,
                reference_number = ?,
                notes = ?
            WHERE food_contribution_id = ?
        ";


        $stmt = $conn->prepare($sql);


        if (!$stmt) {

            $message = "Database error: " . $conn->error;

        } else {

            $stmt->bind_param(
                "iidssssi",
                $student_id,
                $contribution_type_id,
                $quantity,
                $unit,
                $contribution_date,
                $reference_number,
                $notes,
                $food_contribution_id
            );


            if ($stmt->execute()) {

                header("Location: index.php?updated=1");
                exit;

            } else {

                $message = "Error updating contribution: " . $stmt->error;

            }


            $stmt->close();

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Keep submitted values in the form if validation fails
    |--------------------------------------------------------------------------
    */

    $contribution["student_id"] = $student_id;
    $contribution["contribution_type_id"] = $contribution_type_id;
    $contribution["quantity"] = $quantity;
    $contribution["unit"] = $unit;
    $contribution["contribution_date"] = $contribution_date;
    $contribution["reference_number"] = $reference_number;
    $contribution["notes"] = $notes;

}


include "../includes/header.php";

?>

<div class="container">

    <div class="form-card">

        <h2>Edit Food Contribution</h2>


        <?php if ($message != ""): ?>

            <p class="form-message">
                <?= htmlspecialchars($message); ?>
            </p>

        <?php endif; ?>


        <?php if ($contribution["status"] == "Voided"): ?>

            <p class="form-message">
                This contribution is currently VOIDED.
                Editing it will not reactivate the contribution.
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
                                <?= ($student['student_id'] == $contribution['student_id']) ? 'selected' : ''; ?>
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
                                <?= ($type['contribution_type_id'] == $contribution['contribution_type_id']) ? 'selected' : ''; ?>
                            >

                                <?= htmlspecialchars($type['name']); ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>


                <!-- Quantity -->

                <div class="form-group">

                    <label for="quantity">
                        Quantity
                    </label>

                    <input
                        type="number"
                        id="quantity"
                        name="quantity"
                        value="<?= htmlspecialchars($contribution['quantity']); ?>"
                        min="0"
                        step="0.01"
                        required
                    >

                </div>


                <!-- Unit -->

                <div class="form-group">

                    <label for="unit">
                        Unit
                    </label>

                    <select
                        id="unit"
                        name="unit"
                        required
                    >

                        <option value="">
                            Select unit
                        </option>

                        <option
                            value="kg"
                            <?= ($contribution['unit'] == 'kg') ? 'selected' : ''; ?>
                        >
                            Kilograms (kg)
                        </option>

                        <option
                            value="bags"
                            <?= ($contribution['unit'] == 'bags') ? 'selected' : ''; ?>
                        >
                            Bags
                        </option>

                        <option
                            value="sacks"
                            <?= ($contribution['unit'] == 'sacks') ? 'selected' : ''; ?>
                        >
                            Sacks
                        </option>

                        <option
                            value="Other"
                            <?= ($contribution['unit'] == 'Other') ? 'selected' : ''; ?>
                        >
                            Other
                        </option>

                    </select>

                </div>


                <!-- Contribution Date -->

                <div class="form-group">

                    <label for="contribution_date">
                        Contribution Date
                    </label>

                    <input
                        type="date"
                        id="contribution_date"
                        name="contribution_date"
                        value="<?= htmlspecialchars($contribution['contribution_date']); ?>"
                        required
                    >

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
                        value="<?= htmlspecialchars($contribution['reference_number'] ?? ''); ?>"
                        placeholder="Optional reference"
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
                        placeholder="Additional information about this contribution..."
                    ><?= htmlspecialchars($contribution['notes'] ?? ''); ?></textarea>

                </div>

            </div>


            <!-- Buttons -->

            <div class="form-actions">

                <button type="submit">
                    Update Contribution
```
