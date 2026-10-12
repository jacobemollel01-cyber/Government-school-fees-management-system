<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";

$message = "";
$error = "";

/* Load active students */
$students = $conn->query("
    SELECT 
        s.student_id,
        s.admission_no,
        s.full_name,
        se.academic_year,
        c.class_name,
        st.stream_name
    FROM students s
    JOIN student_enrollments se 
        ON s.student_id = se.student_id
    JOIN classes c 
        ON se.class_id = c.class_id
    LEFT JOIN streams st 
        ON se.stream_id = st.stream_id
    WHERE s.status = 'Active'
    ORDER BY c.class_name, st.stream_name, s.full_name
");

/* Load active classes */
$classes = $conn->query("
    SELECT class_id, class_name
    FROM classes
    WHERE is_active = TRUE
    ORDER BY class_id
");

/* Load active streams */
$streams = $conn->query("
    SELECT stream_id, stream_name
    FROM streams
    WHERE is_active = TRUE
    ORDER BY stream_id
");

/* Handle promotion */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $student_id = intval($_POST["student_id"] ?? 0);
    $academic_year = intval($_POST["academic_year"] ?? 0);
    $class_id = intval($_POST["class_id"] ?? 0);
    $stream_id = !empty($_POST["stream_id"]) ? intval($_POST["stream_id"]) : null;

    if ($student_id <= 0 || $academic_year <= 0 || $class_id <= 0) {
        $error = "Please fill in all required fields.";
    } else {

        /* Check whether this student already has an enrollment for this year */
        $check = $conn->prepare("
            SELECT enrollment_id
            FROM student_enrollments
            WHERE student_id = ?
              AND academic_year = ?
        ");

        $check->bind_param("ii", $student_id, $academic_year);
        $check->execute();
        $existing = $check->get_result();

        if ($existing->num_rows > 0) {

            $error = "This student already has an enrollment for academic year $academic_year.";

        } else {

            /* Insert new academic-year enrollment */
            $stmt = $conn->prepare("
                INSERT INTO student_enrollments
                (student_id, academic_year, class_id, stream_id)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "iiii",
                $student_id,
                $academic_year,
                $class_id,
                $stream_id
            );

            if ($stmt->execute()) {
                $message = "Student successfully enrolled for academic year $academic_year.";
            } else {
                $error = "Failed to create the new enrollment.";
            }

            $stmt->close();
        }

        $check->close();
    }
}
?>

<?php include "../includes/header.php"; ?>

<div class="page-container">

    <div class="page-header">
        <div>
            <h1>Promote Student</h1>
            <p>Create a new academic-year enrollment without changing previous history.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert success">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="form-card">

        <form method="POST">

            <div class="form-group">
                <label for="student_id">Student *</label>

                <select name="student_id" id="student_id" required>
                    <option value="">-- Select Student --</option>

                    <?php while ($student = $students->fetch_assoc()): ?>

                        <option value="<?php echo $student["student_id"]; ?>">
                            <?php
                            echo htmlspecialchars(
                                $student["admission_no"] . " - " .
                                $student["full_name"] . " (" .
                                $student["class_name"] .
                                ($student["stream_name"]
                                    ? " - " . $student["stream_name"]
                                    : "") .
                                ", " .
                                $student["academic_year"] .
                                ")"
                            );
                            ?>
                        </option>

                    <?php endwhile; ?>
                </select>
            </div>


            <div class="form-group">
                <label for="academic_year">New Academic Year *</label>

                <input
                    type="number"
                    name="academic_year"
                    id="academic_year"
                    value="<?php echo date("Y") + 1; ?>"
                    min="<?php echo date("Y"); ?>"
                    required
                >
            </div>


            <div class="form-group">
                <label for="class_id">New Class *</label>

                <select name="class_id" id="class_id" required>
                    <option value="">-- Select Class --</option>

                    <?php while ($class = $classes->fetch_assoc()): ?>

                        <option value="<?php echo $class["class_id"]; ?>">
                            <?php echo htmlspecialchars($class["class_name"]); ?>
                        </option>

                    <?php endwhile; ?>

                </select>
            </div>


            <div class="form-group">
                <label for="stream_id">New Stream</label>

                <select name="stream_id" id="stream_id">

                    <option value="">-- No Stream --</option>

                    <?php while ($stream = $streams->fetch_assoc()): ?>

                        <option value="<?php echo $stream["stream_id"]; ?>">
                            <?php echo htmlspecialchars($stream["stream_name"]); ?>
                        </option>

                    <?php endwhile; ?>

                </select>
            </div>


            <div class="form-actions">

                <button type="submit" class="btn btn-primary">
                    Promote Student
                </button>

                <a href="index.php" class="btn btn-secondary">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>