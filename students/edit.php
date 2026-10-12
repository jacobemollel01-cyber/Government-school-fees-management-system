<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";


// Check if student ID exists
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("Invalid student ID.");
}

$student_id = (int) $_GET["id"];


// Get student information
$sql = "SELECT * FROM students WHERE student_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();
$student = $result->fetch_assoc();

$stmt->close();


// Check if student exists
if (!$student) {
    die("Student not found.");
}


$message = "";


// Payment responsibility values
$payment_responsibility =
    $student["payee_type"] ?? "Parent/Guardian";

$payment_responsibility_other =
    $student["payee_other"] ?? "";


// Current academic year enrollment
$selected_class_id = "";
$selected_stream_id = "";

$enrollment_sql = "SELECT class_id, stream_id
                   FROM student_enrollments
                   WHERE student_id = ?
                   AND academic_year = ?
                   LIMIT 1";

$enrollment_stmt = $conn->prepare($enrollment_sql);

$enrollment_stmt->bind_param(
    "ii",
    $student_id,
    $student["academic_year"]
);

$enrollment_stmt->execute();

$enrollment_result = $enrollment_stmt->get_result();

if ($enrollment_result->num_rows > 0) {

    $enrollment = $enrollment_result->fetch_assoc();

    $selected_class_id = $enrollment["class_id"];
    $selected_stream_id = $enrollment["stream_id"];
}

$enrollment_stmt->close();


// If no enrollment exists yet, find class/stream from old student fields
if ($selected_class_id === "") {

    $fallback_class = $conn->prepare(
        "SELECT class_id
         FROM classes
         WHERE LOWER(class_name) = LOWER(?)
         LIMIT 1"
    );

    $fallback_class->bind_param(
        "s",
        $student["class_name"]
    );

    $fallback_class->execute();

    $fallback_result = $fallback_class->get_result();

    if ($fallback_result->num_rows > 0) {

        $fallback_row = $fallback_result->fetch_assoc();

        $selected_class_id = $fallback_row["class_id"];
    }

    $fallback_class->close();
}


if ($selected_stream_id === "") {

    $fallback_stream = $conn->prepare(
        "SELECT stream_id
         FROM streams
         WHERE LOWER(stream_name) = LOWER(?)
         LIMIT 1"
    );

    $fallback_stream->bind_param(
        "s",
        $student["stream"]
    );

    $fallback_stream->execute();

    $fallback_result = $fallback_stream->get_result();

    if ($fallback_result->num_rows > 0) {

        $fallback_row = $fallback_result->fetch_assoc();

        $selected_stream_id = $fallback_row["stream_id"];
    }

    $fallback_stream->close();
}


/*
|--------------------------------------------------------------------------
| Load Classes
|--------------------------------------------------------------------------
*/

$classes = [];

$class_query = "SELECT class_id, class_name
                FROM classes
                WHERE is_active = TRUE
                ORDER BY class_id ASC";

$class_result = $conn->query($class_query);

if ($class_result) {

    while ($row = $class_result->fetch_assoc()) {
        $classes[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| Load Streams
|--------------------------------------------------------------------------
*/

$streams = [];

$stream_query = "SELECT stream_id, stream_name
                 FROM streams
                 WHERE is_active = TRUE
                 ORDER BY stream_id ASC";

$stream_result = $conn->query($stream_query);

if ($stream_result) {

    while ($row = $stream_result->fetch_assoc()) {
        $streams[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| Handle Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $admission_no = trim($_POST["admission_no"] ?? "");
    $full_name = trim($_POST["full_name"] ?? "");

    $class_id = intval($_POST["class_id"] ?? 0);
    $stream_id = intval($_POST["stream_id"] ?? 0);

    $payment_responsibility =
        trim($_POST["payment_responsibility"] ?? "");

    $payment_responsibility_other =
        trim($_POST["payment_responsibility_other"] ?? "");

    /*
     * Academic year is intentionally taken from the existing
     * student record. Promotion is handled separately.
     */
    $academic_year = (int) $student["academic_year"];

    $status = $_POST["status"] ?? "Active";


    // Validate status
    if ($status !== "Active" && $status !== "Inactive") {
        $status = "Active";
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Payment Responsibility
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $payment_responsibility,
            ["Sponsor", "Parent/Guardian", "Other"],
            true
        )
    ) {

        $message = "Please select a valid payment responsibility.";

    } elseif (
        $payment_responsibility === "Other" &&
        $payment_responsibility_other === ""
    ) {

        $message = "Please specify the other payment responsibility.";

    } else {

        /*
         * Other responsibility is only stored when
         * Payment Responsibility is Other.
         */
        if ($payment_responsibility !== "Other") {
            $payment_responsibility_other = null;
        }


        /*
        |--------------------------------------------------------------------------
        | Basic Validation
        |--------------------------------------------------------------------------
        */

        if ($admission_no === "" || $full_name === "") {

            $message = "Please fill in all required fields.";

        } elseif ($class_id <= 0) {

            $message = "Please select a class.";

        } elseif ($stream_id <= 0) {

            $message = "Please select a stream.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Get Selected Class Name
            |--------------------------------------------------------------------------
            */

            $class_stmt = $conn->prepare(
                "SELECT class_name
                 FROM classes
                 WHERE class_id = ?
                 AND is_active = TRUE"
            );

            $class_stmt->bind_param("i", $class_id);
            $class_stmt->execute();

            $class_result = $class_stmt->get_result();

            if ($class_result->num_rows === 0) {

                $message = "Selected class is invalid.";

                $class_stmt->close();

            } else {

                $class_row = $class_result->fetch_assoc();
                $class_name = $class_row["class_name"];

                $class_stmt->close();


                /*
                |--------------------------------------------------------------------------
                | Get Selected Stream Name
                |--------------------------------------------------------------------------
                */

                $stream_stmt = $conn->prepare(
                    "SELECT stream_name
                     FROM streams
                     WHERE stream_id = ?
                     AND is_active = TRUE"
                );

                $stream_stmt->bind_param("i", $stream_id);
                $stream_stmt->execute();

                $stream_result = $stream_stmt->get_result();

                if ($stream_result->num_rows === 0) {

                    $message = "Selected stream is invalid.";

                    $stream_stmt->close();

                } else {

                    $stream_row = $stream_result->fetch_assoc();
                    $stream = $stream_row["stream_name"];

                    $stream_stmt->close();


                    /*
                    |--------------------------------------------------------------------------
                    | Start Transaction
                    |--------------------------------------------------------------------------
                    */

                    $conn->begin_transaction();

                    try {

                        /*
                        |--------------------------------------------------------------------------
                        | Update Student
                        |--------------------------------------------------------------------------
                        */

                        $update_sql = "UPDATE students
                                       SET admission_no = ?,
                                           full_name = ?,
                                           class_name = ?,
                                           stream = ?,
                                           payee_type = ?,
                                           payee_other = ?,
                                           status = ?
                                       WHERE student_id = ?";

                        $update_stmt = $conn->prepare($update_sql);

                        if (!$update_stmt) {
                            throw new Exception(
                                "Database error: " . $conn->error
                            );
                        }

                        $update_stmt->bind_param(
                            "sssssssi",
                            $admission_no,
                            $full_name,
                            $class_name,
                            $stream,
                            $payment_responsibility,
                            $payment_responsibility_other,
                            $status,
                            $student_id
                        );

                        if (!$update_stmt->execute()) {

                            if ($update_stmt->errno == 1062) {

                                throw new Exception(
                                    "Admission number already exists. Please use a different admission number."
                                );
                            }

                            throw new Exception(
                                "Error updating student: " .
                                $update_stmt->error
                            );
                        }

                        $update_stmt->close();


                        /*
                        |--------------------------------------------------------------------------
                        | Check Existing Enrollment
                        |--------------------------------------------------------------------------
                        */

                        $check_sql = "SELECT enrollment_id
                                      FROM student_enrollments
                                      WHERE student_id = ?
                                      AND academic_year = ?
                                      LIMIT 1";

                        $check_stmt = $conn->prepare($check_sql);

                        $check_stmt->bind_param(
                            "ii",
                            $student_id,
                            $academic_year
                        );

                        $check_stmt->execute();

                        $check_result = $check_stmt->get_result();

                        $existing_enrollment =
                            $check_result->fetch_assoc();

                        $check_stmt->close();


                        /*
                        |--------------------------------------------------------------------------
                        | Update Existing Enrollment
                        |--------------------------------------------------------------------------
                        */

                        if ($existing_enrollment) {

                            $enrollment_id =
                                (int) $existing_enrollment["enrollment_id"];

                            $enrollment_update = $conn->prepare(
                                "UPDATE student_enrollments
                                 SET class_id = ?,
                                     stream_id = ?
                                 WHERE enrollment_id = ?"
                            );

                            $enrollment_update->bind_param(
                                "iii",
                                $class_id,
                                $stream_id,
                                $enrollment_id
                            );

                            if (!$enrollment_update->execute()) {

                                throw new Exception(
                                    "Could not update academic enrollment."
                                );
                            }

                            $enrollment_update->close();

                        } else {

                            /*
                            |--------------------------------------------------------------------------
                            | Create Enrollment If It Does Not Exist
                            |--------------------------------------------------------------------------
                            */

                            $enrollment_insert = $conn->prepare(
                                "INSERT INTO student_enrollments
                                 (
                                     student_id,
                                     academic_year,
                                     class_id,
                                     stream_id
                                 )
                                 VALUES (?, ?, ?, ?)"
                            );

                            $enrollment_insert->bind_param(
                                "iiii",
                                $student_id,
                                $academic_year,
                                $class_id,
                                $stream_id
                            );

                            if (!$enrollment_insert->execute()) {

                                throw new Exception(
                                    "Could not create academic enrollment."
                                );
                            }

                            $enrollment_insert->close();
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Commit
                        |--------------------------------------------------------------------------
                        */

                        $conn->commit();

                        header("Location: index.php");
                        exit;

                    } catch (Exception $e) {

                        $conn->rollback();

                        $message = $e->getMessage();
                    }
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Refresh Values After Error
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] == "POST" && $message !== "") {

    $student["admission_no"] = $admission_no;
    $student["full_name"] = $full_name;
    $student["status"] = $status;

    $selected_class_id = $class_id;
    $selected_stream_id = $stream_id;

    $student["payee_type"] = $payment_responsibility;
    $student["payee_other"] = $payment_responsibility_other;
}


require_once "../includes/header.php";

?>

<div class="container">

    <!-- Page Header -->
    <div class="dashboard-header">

        <h1>Edit Student</h1>

        <p class="dashboard-subtitle">
            Update the student's academic and enrollment information.
        </p>

    </div>


    <!-- Form Card -->
    <div class="form-card">

        <?php if ($message != ""): ?>

            <div class="form-message error-message">

                <?= htmlspecialchars($message); ?>

            </div>

        <?php endif; ?>


        <form method="POST">

            <div class="form-grid">


                <!-- Admission Number -->
                <div class="form-group">

                    <label for="admission_no">
                        Admission Number
                    </label>

                    <input
                        type="text"
                        id="admission_no"
                        name="admission_no"
                        value="<?= htmlspecialchars($student['admission_no']); ?>"
                        required
                    >

                </div>


                <!-- Full Name -->
                <div class="form-group">

                    <label for="full_name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?= htmlspecialchars($student['full_name']); ?>"
                        required
                    >

                </div>


                <!-- Class -->
                <div class="form-group">

                    <label for="class_id">
                        Class
                    </label>

                    <select
                        id="class_id"
                        name="class_id"
                        required
                    >

                        <option value="">
                            -- Select Class --
                        </option>

                        <?php foreach ($classes as $class): ?>

                            <option
                                value="<?= $class['class_id']; ?>"
                                <?= ($selected_class_id == $class['class_id'])
                                    ? 'selected'
                                    : ''; ?>
                            >
                                <?= htmlspecialchars($class['class_name']); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Stream -->
                <div class="form-group">

                    <label for="stream_id">
                        Stream
                    </label>

                    <select
                        id="stream_id"
                        name="stream_id"
                        required
                    >

                        <option value="">
                            -- Select Stream --
                        </option>

                        <?php foreach ($streams as $stream): ?>

                            <option
                                value="<?= $stream['stream_id']; ?>"
                                <?= ($selected_stream_id == $stream['stream_id'])
                                    ? 'selected'
                                    : ''; ?>
                            >
                                <?= htmlspecialchars($stream['stream_name']); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Academic Year -->
                <div class="form-group">

                    <label for="academic_year">
                        Academic Year
                    </label>

                    <input
                        type="text"
                        id="academic_year"
                        value="<?= htmlspecialchars($student['academic_year']); ?>"
                        readonly
                    >

                    <small style="color: #6b7280;">
                        Academic year is linked to this student's current enrollment.
                    </small>

                </div>


                <!-- Payment Responsibility -->
                <div class="form-group">

                    <label for="payment_responsibility">
                        Payment Responsibility
                    </label>

                    <select
                        id="payment_responsibility"
                        name="payment_responsibility"
                        required
                    >

                        <option
                            value="Parent/Guardian"
                            <?= $student['payee_type'] === 'Parent/Guardian'
                                ? 'selected'
                                : ''; ?>
                        >
                            Parent/Guardian
                        </option>

                        <option
                            value="Sponsor"
                            <?= $student['payee_type'] === 'Sponsor'
                                ? 'selected'
                                : ''; ?>
                        >
                            Sponsor
                        </option>

                        <option
                            value="Other"
                            <?= $student['payee_type'] === 'Other'
                                ? 'selected'
                                : ''; ?>
                        >
                            Other
                        </option>

                    </select>

                </div>


                <!-- Other Payment Responsibility -->
                <div
                    class="form-group"
                    id="other-responsibility-group"
                    style="<?= $student['payee_type'] === 'Other'
                        ? ''
                        : 'display:none;'; ?>"
                >

                    <label for="payment_responsibility_other">
                        Specify Other Payment Responsibility
                    </label>

                    <input
                        type="text"
                        id="payment_responsibility_other"
                        name="payment_responsibility_other"
                        value="<?= htmlspecialchars(
                            $student['payee_other'] ?? ''
                        ); ?>"
                        placeholder="e.g. Uncle, Church, Relative"
                    >

                </div>


                <!-- Status -->
                <div class="form-group">

                    <label for="status">
                        Student Status
                    </label>

                    <select
                        id="status"
                        name="status"
                        required
                    >

                        <option
                            value="Active"
                            <?= $student['status'] === 'Active'
                                ? 'selected'
                                : ''; ?>
                        >
                            Active
                        </option>

                        <option
                            value="Inactive"
                            <?= $student['status'] === 'Inactive'
                                ? 'selected'
                                : ''; ?>
                        >
                            Inactive
                        </option>

                    </select>

                </div>


            </div>


            <!-- Form Actions -->
            <div class="form-actions">

                <button type="submit">
                    Save Changes
                </button>

                <a
                    href="index.php"
                    class="action-btn secondary"
                >
                    ← Cancel
                </a>

            </div>


        </form>

    </div>

</div>


<style>

/* Error message */
.error-message {
    background: #fcebea;
    color: #9b2c2c;
    border-left: 4px solid #b83232;
}

</style>


<script>

const paymentResponsibility =
    document.getElementById("payment_responsibility");

const otherResponsibilityGroup =
    document.getElementById("other-responsibility-group");

const otherResponsibilityInput =
    document.getElementById("payment_responsibility_other");


function updateOtherResponsibilityField() {

    if (paymentResponsibility.value === "Other") {

        otherResponsibilityGroup.style.display = "";
        otherResponsibilityInput.required = true;

    } else {

        otherResponsibilityGroup.style.display = "none";
        otherResponsibilityInput.required = false;
        otherResponsibilityInput.value = "";

    }
}


paymentResponsibility.addEventListener(
    "change",
    updateOtherResponsibilityField
);

updateOtherResponsibilityField();

</script>