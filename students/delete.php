
<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";


// Check if student ID exists
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("Invalid student ID.");
}

$student_id = (int) $_GET["id"];


// ---------------------------------------------------------
// Get student information
// ---------------------------------------------------------

$sql = "SELECT admission_no, full_name
        FROM students
        WHERE student_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $student_id);

$stmt->execute();

$result = $stmt->get_result();

$student = $result->fetch_assoc();

$stmt->close();


if (!$student) {
    die("Student not found.");
}


// ---------------------------------------------------------
// Check for related records
// ---------------------------------------------------------

$related_records = [];


// Student requirements
$sql = "SELECT COUNT(*) AS total
        FROM student_requirements
        WHERE student_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['total'] > 0) {
    $related_records[] = "Student requirements";
}

$stmt->close();


// Payments
$sql = "SELECT COUNT(*) AS total
        FROM payments
        WHERE student_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['total'] > 0) {
    $related_records[] = "Payments";
}

$stmt->close();


// Sponsorships
$sql = "SELECT COUNT(*) AS total
        FROM sponsorships
        WHERE student_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['total'] > 0) {
    $related_records[] = "Sponsorships";
}

$stmt->close();


// Maize / Beans contributions
$sql = "SELECT COUNT(*) AS total
        FROM food_contributions
        WHERE student_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['total'] > 0) {
    $related_records[] = "Maize / Beans contributions";
}

$stmt->close();


// ---------------------------------------------------------
// Delete only if no related records exist
// ---------------------------------------------------------

if (count($related_records) === 0) {

    $sql = "DELETE FROM students
            WHERE student_id = ?";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param("i", $student_id);

    if ($stmt->execute()) {

        $stmt->close();

        header("Location: index.php");
        exit;

    } else {

        $error = "Error deleting student: " . $stmt->error;

        $stmt->close();
    }

} else {

    $error = "This student cannot be deleted because related records exist.";

}

require_once "../includes/header.php";

?>


<div class="container">

    <div class="form-card">

        <h1>Student Cannot Be Deleted</h1>

        <p>
            The student
            <strong>
                <?= htmlspecialchars($student['full_name']); ?>
            </strong>
            (Admission No.
            <strong>
                <?= htmlspecialchars($student['admission_no']); ?>
            </strong>)
            has records connected to the financial system.
        </p>


        <?php if (!empty($related_records)): ?>

            <div class="form-message error-message">

                <strong>Related records found:</strong>

                <ul>

                    <?php foreach ($related_records as $record): ?>

                        <li>
                            <?= htmlspecialchars($record); ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

                <p>
                    For financial and historical accuracy,
                    this student should not be permanently deleted.
                </p>

            </div>

        <?php endif; ?>


        <div class="form-actions">

            <a
                href="edit.php?id=<?= $student_id; ?>"
                class="action-btn"
            >
                Edit Student
            </a>

            <a
                href="index.php"
                class="action-btn secondary"
            >
                ← Back to Students
            </a>

        </div>

    </div>

</div>


<style>

.error-message {
    background: #fcebea;
    color: #9b2c2c;
    border-left: 4px solid #b83232;
}

.error-message ul {
    margin: 12px 0;
    padding-left: 22px;
}

.error-message li {
    margin-bottom: 5px;
}

</style>
```
