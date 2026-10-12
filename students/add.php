<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";

$message = "";
$message_type = "";

$admission_no = "";
$full_name = "";
$class_id = "";
$stream_id = "";
$academic_year = date("Y");
$payment_responsibility = "Parent/Guardian";
$payment_responsibility_other = "";

/* Get classes */
$classes = [];
$class_result = $conn->query("
    SELECT class_id, class_name
    FROM classes
    WHERE is_active = 1
    ORDER BY class_id ASC
");

if ($class_result) {
    while ($row = $class_result->fetch_assoc()) {
        $classes[] = $row;
    }
}

/* Get streams */
$streams = [];
$stream_result = $conn->query("
    SELECT stream_id, stream_name
    FROM streams
    WHERE is_active = 1
    ORDER BY stream_id ASC
");

if ($stream_result) {
    while ($row = $stream_result->fetch_assoc()) {
        $streams[] = $row;
    }
}

/* Handle form submission */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $admission_no = trim($_POST["admission_no"] ?? "");
    $full_name = trim($_POST["full_name"] ?? "");
    $class_id = intval($_POST["class_id"] ?? 0);
    $stream_id = intval($_POST["stream_id"] ?? 0);
    $academic_year = intval($_POST["academic_year"] ?? date("Y"));

    $payment_responsibility = $_POST["payment_responsibility"] ?? "Parent/Guardian";
    $payment_responsibility_other = trim($_POST["payment_responsibility_other"] ?? "");

    /* Basic validation */
    if ($admission_no === "" || $full_name === "") {
        $message = "Admission number and full name are required.";
        $message_type = "error";
    } elseif ($class_id <= 0) {
        $message = "Please select a class.";
        $message_type = "error";
    } elseif ($stream_id <= 0) {
        $message = "Please select a stream.";
        $message_type = "error";
    } elseif ($academic_year < 2000 || $academic_year > 2100) {
        $message = "Please enter a valid academic year.";
        $message_type = "error";
    } elseif (!in_array($payment_responsibility, ["Sponsor", "Parent/Guardian", "Other"])) {
        $message = "Invalid payment responsibility.";
        $message_type = "error";
    } elseif ($payment_responsibility === "Other" && $payment_responsibility_other === "") {
        $message = "Please specify the other payment responsibility.";
        $message_type = "error";
    } else {

        /* Get selected class */
        $class_stmt = $conn->prepare("
            SELECT class_name
            FROM classes
            WHERE class_id = ? AND is_active = 1
        ");
        $class_stmt->bind_param("i", $class_id);
        $class_stmt->execute();
        $class_result = $class_stmt->get_result();

        /* Get selected stream */
        $stream_stmt = $conn->prepare("
            SELECT stream_name
            FROM streams
            WHERE stream_id = ? AND is_active = 1
        ");
        $stream_stmt->bind_param("i", $stream_id);
        $stream_stmt->execute();
        $stream_result = $stream_stmt->get_result();

        if ($class_result->num_rows === 0) {
            $message = "Selected class is invalid.";
            $message_type = "error";
        } elseif ($stream_result->num_rows === 0) {
            $message = "Selected stream is invalid.";
            $message_type = "error";
        } else {
            $class_row = $class_result->fetch_assoc();
            $stream_row = $stream_result->fetch_assoc();

            $class_name = $class_row["class_name"];
            $stream_name = $stream_row["stream_name"];

            if ($payment_responsibility !== "Other") {
                $payment_responsibility_other = null;
            }

            /* Check duplicate admission number */
            $check_stmt = $conn->prepare("
                SELECT student_id
                FROM students
                WHERE admission_no = ?
            ");
            $check_stmt->bind_param("s", $admission_no);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $message = "A student with admission number " . htmlspecialchars($admission_no) . " already exists.";
                $message_type = "error";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO students
                    (
                        admission_no,
                        full_name,
                        class_name,
                        stream,
                        academic_year,
                        payee_type,
                        payee_other
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "ssssiss",
                    $admission_no,
                    $full_name,
                    $class_name,
                    $stream_name,
                    $academic_year,
                    $payment_responsibility,
                    $payment_responsibility_other
                );

                if ($stmt->execute()) {
                    $student_id = $stmt->insert_id;

                    /* Create current academic-year enrollment */
                    $enrollment_stmt = $conn->prepare("
                        INSERT INTO student_enrollments
                        (
                            student_id,
                            academic_year,
                            class_id,
                            stream_id
                        )
                        VALUES (?, ?, ?, ?)
                    ");
                    $enrollment_stmt->bind_param(
                        "iiii",
                        $student_id,
                        $academic_year,
                        $class_id,
                        $stream_id
                    );

                    if ($enrollment_stmt->execute()) {
                        $message = "Student added successfully.";
                        $message_type = "success";

                        /* Clear form */
                        $admission_no = "";
                        $full_name = "";
                        $class_id = "";
                        $stream_id = "";
                        $academic_year = date("Y");
                        $payment_responsibility = "Parent/Guardian";
                        $payment_responsibility_other = "";
                    } else {
                        $conn->query("
                            DELETE FROM students
                            WHERE student_id = " . intval($student_id) . "
                        ");
                        $message = "Student could not be added because enrollment creation failed.";
                        $message_type = "error";
                    }
                } else {
                    $message = "Error adding student: " . $stmt->error;
                    $message_type = "error";
                }
            }
        }
    }
}

include "../includes/header.php";
?>

<div class="page-container">
    <div class="page-header">
        <h1>Add New Student</h1>
        <p>Register a student profile and assign them to an active class and stream.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8'); ?>">
            <span class="alert-icon"><?php echo $message_type === 'success' ? '✓' : '⚠'; ?></span>
            <span><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" class="student-form" novalidate>
            
            <div class="form-section-title">Personal & Academic Info</div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="admission_no">Admission Number <span class="required">*</span></label>
                    <input
                        type="text"
                        id="admission_no"
                        name="admission_no"
                        value="<?php echo htmlspecialchars($admission_no, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="e.g. ADM/2026/001"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="full_name">Full Name <span class="required">*</span></label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?php echo htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="e.g. Juma Ally Hassan"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="academic_year">Academic Year <span class="required">*</span></label>
                    <input
                        type="number"
                        id="academic_year"
                        name="academic_year"
                        value="<?php echo htmlspecialchars($academic_year, ENT_QUOTES, 'UTF-8'); ?>"
                        min="2000"
                        max="2100"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="class_id">Class <span class="required">*</span></label>
                    <select id="class_id" name="class_id" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ($classes as $class): ?>
                            <option
                                value="<?php echo $class['class_id']; ?>"
                                <?php echo ($class_id == $class['class_id']) ? 'selected' : ''; ?>
                            >
                                <?php echo htmlspecialchars($class['class_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="stream_id">Stream <span class="required">*</span></label>
                    <select id="stream_id" name="stream_id" required>
                        <option value="">-- Select Stream --</option>
                        <?php foreach ($streams as $stream): ?>
                            <option
                                value="<?php echo $stream['stream_id']; ?>"
                                <?php echo ($stream_id == $stream['stream_id']) ? 'selected' : ''; ?>
                            >
                                <?php echo htmlspecialchars($stream['stream_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="payment_responsibility">Payment Responsibility <span class="required">*</span></label>
                    <select id="payment_responsibility" name="payment_responsibility" required>
                        <option value="Parent/Guardian" <?php echo $payment_responsibility === 'Parent/Guardian' ? 'selected' : ''; ?>>Parent/Guardian</option>
                        <option value="Sponsor" <?php echo $payment_responsibility === 'Sponsor' ? 'selected' : ''; ?>>Sponsor</option>
                        <option value="Other" <?php echo $payment_responsibility === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>

            <div class="form-group full-width" id="otherResponsibilityGroup" style="<?php echo $payment_responsibility === 'Other' ? '' : 'display:none;'; ?>">
                <label for="payment_responsibility_other">Specify Other Responsible Party <span class="required">*</span></label>
                <input
                    type="text"
                    id="payment_responsibility_other"
                    name="payment_responsibility_other"
                    value="<?php echo htmlspecialchars($payment_responsibility_other ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="Enter organization or individual name"
                    <?php echo $payment_responsibility === 'Other' ? 'required' : ''; ?>
                >
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <span class="btn-icon">+</span> Save Student
                </button>
                <a href="index.php" class="btn btn-secondary">
                    Cancel
                </a>
            </div>

        </form>
    </div>
</div>

<style>
/* Modern Form UI Enhancements */
.page-container {
    max-width: 900px;
    margin: 2rem auto;
    padding: 0 1.5rem;
}

.page-header {
    margin-bottom: 1.5rem;
}

.page-header h1 {
    font-size: 1.75rem;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.page-header p {
    color: #64748b;
    font-size: 0.95rem;
}

.alert {
    padding: 1rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.95rem;
    font-weight: 500;
}

.alert.success {
    background-color: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.alert.error {
    background-color: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.form-card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
    padding: 2rem;
}

.form-section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 1.25rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #f1f5f9;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.25rem;
    margin-bottom: 1.25rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full-width {
    grid-column: 1 / -1;
    margin-bottom: 1.25rem;
}

.form-group label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 0.5rem;
}

.form-group label .required {
    color: #ef4444;
}

.form-group input,
.form-group select {
    padding: 0.75rem 1rem;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.95rem;
    color: #1e293b;
    background-color: #fff;
    transition: all 0.2s ease;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.form-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.25rem;
    border-top: 1px solid #f1f5f9;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    font-size: 0.95rem;
    font-weight: 600;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: background-color 0.2s ease;
}

.btn-primary {
    background-color: #2563eb;
    color: #ffffff;
}

.btn-primary:hover {
    background-color: #1d4ed8;
}

.btn-secondary {
    background-color: #f1f5f9;
    color: #475569;
}

.btn-secondary:hover {
    background-color: #e2e8f0;
}

@media (max-width: 640px) {
    .form-card {
        padding: 1.25rem;
    }
    .form-actions {
        flex-direction: column-reverse;
        width: 100%;
    }
    .btn {
        width: 100%;
    }
}
</style>

<script>
const paymentResponsibility = document.getElementById("payment_responsibility");
const otherResponsibilityGroup = document.getElementById("otherResponsibilityGroup");
const paymentResponsibilityOther = document.getElementById("payment_responsibility_other");

function toggleOtherResponsibility() {
    if (paymentResponsibility.value === "Other") {
        otherResponsibilityGroup.style.display = "flex";
        paymentResponsibilityOther.required = true;
    } else {
        otherResponsibilityGroup.style.display = "none";
        paymentResponsibilityOther.required = false;
        paymentResponsibilityOther.value = "";
    }
}

paymentResponsibility.addEventListener("change", toggleOtherResponsibility);
toggleOtherResponsibility();
</script>

<?php include "../includes/footer.php"; ?>