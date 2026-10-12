<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";

$message = "";

/*
|--------------------------------------------------------------------------
| Get active students
|--------------------------------------------------------------------------
*/

$students = $conn->query("
    SELECT student_id, admission_no, full_name
    FROM students
    WHERE status = 'Active'
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
    ORDER BY name
");


/*
|--------------------------------------------------------------------------
| Save contribution
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $student_id = $_POST["student_id"];
    $contribution_type_id = $_POST["contribution_type_id"];
    $quantity = $_POST["quantity"];
    $unit = trim($_POST["unit"]);
    $contribution_date = $_POST["contribution_date"];
    $reference_number = trim($_POST["reference_number"]);
    $notes = trim($_POST["notes"]);
    $recorded_by = "Admin";


    $sql = "INSERT INTO food_contributions
            (
                student_id,
                contribution_type_id,
                quantity,
                unit,
                contribution_date,
                reference_number,
                notes,
                recorded_by
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";


    $stmt = $conn->prepare($sql);


    if (!$stmt) {

        $message = "Database error: " . $conn->error;

    } else {

        $stmt->bind_param(
            "iidsssss",
            $student_id,
            $contribution_type_id,
            $quantity,
            $unit,
            $contribution_date,
            $reference_number,
            $notes,
            $recorded_by
        );


        if ($stmt->execute()) {

            $message = "Contribution recorded successfully!";

        } else {

            $message = "Error recording contribution: " . $stmt->error;

        }


        $stmt->close();
    }
}


include "../includes/header.php";

?>

<div class="container">

    <div class="form-card">

        <h2>Record Food Contribution</h2>

        <?php if ($message != ""): ?>

            <p class="form-message">
                <?= htmlspecialchars($message); ?>
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

                            <option value="<?= $student['student_id']; ?>">

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

                            <option value="<?= $type['contribution_type_id']; ?>">

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
                        placeholder="e.g. 50"
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

                        <option value="kg">
                            Kilograms (kg)
                        </option>

                        <option value="bags">
                            Bags
                        </option>

                        <option value="sacks">
                            Sacks
                        </option>

                        <option value="Other">
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
                        value="<?= date('Y-m-d'); ?>"
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
                    ></textarea>

                </div>

            </div>


            <!-- Buttons -->

            <div class="form-actions">

                <button type="submit">
                    Record Contribution
                </button>

                <a href="index.php" class="btn">
                    Back to Contributions
                </a>

            </div>

        </form>

    </div>

</div>