<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";
require_once "../includes/header.php";


/*
 * Get all active classes from the database.
 */
$sql = "
    SELECT class_id, class_name
    FROM classes
    WHERE is_active = 1
    ORDER BY class_id ASC
";

$result = $conn->query($sql);

?>

<div class="container">

    <!-- Page Header -->
    <div class="dashboard-header">

        <h1>Students</h1>

        <p class="dashboard-subtitle">
            Select a class to view its students.
        </p>

    </div>


    <!-- Add Student -->
    <div
        class="quick-actions"
        style="margin-bottom: 30px;"
    >

        <a
            href="add.php"
            class="action-btn"
        >
            + Add Student
        </a>

    </div>


    <!-- Class Selection -->

    <div class="dashboard-card">

        <h2 style="margin-bottom: 10px;">
            Select Class
        </h2>

        <p style="color: #6b7280; margin-bottom: 25px;">
            Choose a class to continue to the student list.
        </p>


        <div
            style="
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 20px;
            "
        >

            <?php if ($result && $result->num_rows > 0): ?>

                <?php while ($class = $result->fetch_assoc()): ?>

                    <a
                        href="class.php?class_id=<?= (int)$class['class_id']; ?>"
                        style="
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            min-height: 100px;
                            padding: 20px;
                            background: #071a2f;
                            color: #ffffff;
                            text-decoration: none;
                            border-radius: 8px;
                            border: 2px solid #c9a227;
                            font-size: 20px;
                            font-weight: 700;
                            transition: 0.2s ease;
                        "
                        onmouseover="this.style.background='#12385f';"
                        onmouseout="this.style.background='#071a2f';"
                    >

                        <?= htmlspecialchars($class['class_name']); ?>

                    </a>

                <?php endwhile; ?>

            <?php else: ?>

                <div
                    style="
                        grid-column: 1 / -1;
                        text-align: center;
                        padding: 40px;
                        color: #6b7280;
                    "
                >

                    <strong>
                        No classes found.
                    </strong>

                    <br>

                    Please add classes to the database first.

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- Back to Dashboard -->

    <div
        class="quick-actions"
        style="margin-top: 25px;"
    >

        <a
            href="../dashboard.php"
            class="action-btn secondary"
        >
            ← Back to Dashboard
        </a>

    </div>

</div>