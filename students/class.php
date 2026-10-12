<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";
require_once "../includes/header.php";


/*
 * Check that a class ID was provided.
 */
$class_id = isset($_GET["class_id"]) ? intval($_GET["class_id"]) : 0;

$stream_id = isset($_GET["stream_id"]) ? intval($_GET["stream_id"]) : 0;


/*
 * If no class was selected, return to Students page.
 */
if ($class_id <= 0) {

    header("Location: index.php");
    exit;

}


/*
 * Get the selected class.
 */
$class_stmt = $conn->prepare("
    SELECT class_id, class_name
    FROM classes
    WHERE class_id = ?
      AND is_active = 1
");

$class_stmt->bind_param("i", $class_id);
$class_stmt->execute();

$class_result = $class_stmt->get_result();


if ($class_result->num_rows === 0) {

    header("Location: index.php");
    exit;

}


$class = $class_result->fetch_assoc();


/*
 * Get all active streams.
 */
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


/*
 * Get students only when a stream has been selected.
 */
$students = [];

if ($stream_id > 0) {

    $student_stmt = $conn->prepare("
        SELECT
            s.student_id,
            s.admission_no,
            s.full_name,
            s.payee_type,
            s.payee_other,
            s.status,
            se.academic_year,
            c.class_name,
            st.stream_name

        FROM student_enrollments se

        INNER JOIN students s
            ON se.student_id = s.student_id

        INNER JOIN classes c
            ON se.class_id = c.class_id

        LEFT JOIN streams st
            ON se.stream_id = st.stream_id

        WHERE se.class_id = ?
          AND se.stream_id = ?
          AND s.status = 'Active'

        ORDER BY s.full_name ASC
    ");

    $student_stmt->bind_param(
        "ii",
        $class_id,
        $stream_id
    );

    $student_stmt->execute();

    $student_result = $student_stmt->get_result();


    while ($student = $student_result->fetch_assoc()) {

        $students[] = $student;

    }

}


/*
 * Get selected stream name.
 */
$selected_stream_name = "";

if ($stream_id > 0) {

    foreach ($streams as $stream) {

        if ($stream["stream_id"] == $stream_id) {

            $selected_stream_name = $stream["stream_name"];

            break;

        }

    }

}

?>


<div class="container">


    <!-- Page Header -->

    <div class="dashboard-header">

        <h1>
            <?= htmlspecialchars($class["class_name"]); ?>
        </h1>

        <p class="dashboard-subtitle">
            Select a stream to view students in this class.
        </p>

    </div>


    <!-- Stream Selection -->

    <div class="dashboard-card">

        <h2 style="margin-bottom: 10px;">
            Select Stream
        </h2>


        <form method="GET">

            <input
                type="hidden"
                name="class_id"
                value="<?= (int)$class_id; ?>"
            >


            <div
                style="
                    display: flex;
                    gap: 15px;
                    align-items: end;
                    flex-wrap: wrap;
                "
            >

                <div
                    class="form-group"
                    style="min-width: 250px;"
                >

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
                                value="<?= (int)$stream["stream_id"]; ?>"
                                <?= ($stream_id == $stream["stream_id"]) ? "selected" : ""; ?>
                            >

                                <?= htmlspecialchars($stream["stream_name"]); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <button
                        type="submit"
                        class="action-btn"
                    >
                        View Students
                    </button>

                </div>

            </div>

        </form>

    </div>


    <?php if ($stream_id > 0): ?>


        <!-- Selected Class and Stream -->

        <div
            style="
                margin-top: 30px;
                margin-bottom: 15px;
                padding: 15px 20px;
                background: #071a2f;
                color: white;
                border-left: 5px solid #c9a227;
                border-radius: 6px;
            "
        >

            <h2 style="margin: 0;">

                <?= htmlspecialchars($class["class_name"]); ?>

                -

                Stream <?= htmlspecialchars($selected_stream_name); ?>

            </h2>

        </div>


        <?php if (!empty($students)): ?>


            <!-- Student Count -->

            <p style="color: #6b7280; margin-bottom: 15px;">

                <?= count($students); ?>

                student<?= count($students) == 1 ? "" : "s"; ?>

                found.

            </p>


            <!-- Students Table -->

            <div class="dashboard-table-wrapper">

                <table class="student-status-table">

                    <thead>

                        <tr>

                            <th>#</th>

                            <th>Admission No.</th>

                            <th>Full Name</th>

                            <th>Payee</th>

                            <th>Academic Year</th>

                            <th>Status</th>

                            <th>Actions</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php $number = 1; ?>


                        <?php foreach ($students as $student): ?>

                            <tr>


                                <!-- Number -->

                                <td>

                                    <?= $number++; ?>

                                </td>


                                <!-- Admission Number -->

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $student["admission_no"]
                                        ); ?>

                                    </strong>

                                </td>


                                <!-- Name -->

                                <td>

                                    <?= htmlspecialchars(
                                        $student["full_name"]
                                    ); ?>

                                </td>


                                <!-- Payee -->

                                <td>

                                    <?php if (
                                        $student["payee_type"] === "Other"
                                    ): ?>

                                        <?= htmlspecialchars(
                                            $student["payee_other"] ?? "Other"
                                        ); ?>

                                    <?php else: ?>

                                        <?= htmlspecialchars(
                                            $student["payee_type"]
                                        ); ?>

                                    <?php endif; ?>

                                </td>


                                <!-- Academic Year -->

                                <td>

                                    <?= htmlspecialchars(
                                        $student["academic_year"]
                                    ); ?>

                                </td>


                                <!-- Status -->

                                <td>

                                    <strong
                                        style="color: #2f855a;"
                                    >
                                        Active
                                    </strong>

                                </td>


                                <!-- Actions -->

                                <td>

                                    <a
                                        href="edit.php?id=<?= (int)$student["student_id"]; ?>"
                                        class="action-btn secondary"
                                    >
                                        Edit
                                    </a>


                                    <a
                                        href="delete.php?id=<?= (int)$student["student_id"]; ?>"
                                        class="action-btn secondary"
                                        onclick="return confirm('Are you sure you want to delete this student?');"
                                    >
                                        Delete
                                    </a>

                                </td>


                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


        <?php else: ?>


            <!-- No Students -->

            <div class="dashboard-table-wrapper">

                <table class="student-status-table">

                    <tbody>

                        <tr>

                            <td
                                style="
                                    text-align: center;
                                    padding: 40px;
                                "
                            >

                                <strong>
                                    No students found.
                                </strong>

                                <br>

                                <span style="color: #6b7280;">

                                    There are no active students in
                                    <?= htmlspecialchars($class["class_name"]); ?>
                                    -
                                    Stream <?= htmlspecialchars($selected_stream_name); ?>.

                                </span>

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>


        <?php endif; ?>


    <?php endif; ?>


    <!-- Navigation -->

    <div
        class="quick-actions"
        style="margin-top: 25px;"
    >

        <a
            href="index.php"
            class="action-btn secondary"
        >
            ← Back to Classes
        </a>


        <?php if ($stream_id > 0): ?>

            <a
                href="class.php?class_id=<?= (int)$class_id; ?>"
                class="action-btn secondary"
            >
                Change Stream
            </a>

        <?php endif; ?>

    </div>


</div>