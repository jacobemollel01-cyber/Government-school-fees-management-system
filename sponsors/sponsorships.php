<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";
require_once "../includes/header.php";

$message = "";

// Fetch active sponsors
$sponsors = $conn->query("
    SELECT sponsor_id, sponsor_name 
    FROM sponsors 
    WHERE is_active = TRUE 
    ORDER BY sponsor_name
");

// Fetch active students
$students = $conn->query("
    SELECT student_id, admission_no, full_name 
    FROM students 
    WHERE status = 'Active' 
    ORDER BY full_name
");

// Fetch contribution types
$contribution_types = $conn->query("
    SELECT contribution_type_id, name 
    FROM contribution_types 
    WHERE is_active = TRUE 
    AND name IN ('Food', 'Hostel', 'Maize', 'Beans') 
    ORDER BY FIELD(name, 'Food', 'Hostel', 'Maize', 'Beans')
");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $sponsor_id = (int) ($_POST["sponsor_id"] ?? 0);
    $student_id = (int) ($_POST["student_id"] ?? 0);
    $contribution_type_id = (int) ($_POST["contribution_type_id"] ?? 0);
    $academic_year = (int) ($_POST["academic_year"] ?? date('Y'));

    $amount_committed = !empty($_POST["amount_committed"]) ? (float) $_POST["amount_committed"] : null;
    $quantity_committed = !empty($_POST["quantity_committed"]) ? (float) $_POST["quantity_committed"] : null;
    $quantity_unit = !empty($_POST["quantity_unit"]) ? trim($_POST["quantity_unit"]) : null;
    $start_date = !empty($_POST["start_date"]) ? $_POST["start_date"] : null;
    $end_date = !empty($_POST["end_date"]) ? $_POST["end_date"] : null;
    $notes = isset($_POST["notes"]) ? trim($_POST["notes"]) : "";

    // Validate contribution type
    $type_stmt = $conn->prepare("
        SELECT name 
        FROM contribution_types 
        WHERE contribution_type_id = ? 
        AND is_active = TRUE 
        LIMIT 1
    ");
    $type_stmt->bind_param("i", $contribution_type_id);
    $type_stmt->execute();
    $type_result = $type_stmt->get_result();

    if ($type_result->num_rows === 0) {
        $message = "Invalid contribution type selected.";
    } else {
        $type_row = $type_result->fetch_assoc();
        $contribution_name = $type_row["name"];

        if (in_array($contribution_name, ["Food", "Hostel"])) {
            $quantity_committed = null;
            $quantity_unit = null;
            if ($amount_committed === null || $amount_committed <= 0) {
                $message = "Please enter a valid amount committed.";
            }
        } elseif (in_array($contribution_name, ["Maize", "Beans"])) {
            $amount_committed = null;
            if ($quantity_committed === null || $quantity_committed <= 0) {
                $message = "Please enter a valid quantity committed.";
            } elseif (empty($quantity_unit)) {
                $message = "Please select or enter the unit (e.g. kg, bags).";
            }
        }

        if ($message === "") {
            $sql = "INSERT INTO sponsorships (
                        sponsor_id, student_id, contribution_type_id, academic_year, 
                        amount_committed, quantity_committed, quantity_unit, 
                        start_date, end_date, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $message = "Database error: " . $conn->error;
            } else {
                $stmt->bind_param(
                    "iiiiddssss",
                    $sponsor_id,
                    $student_id,
                    $contribution_type_id,
                    $academic_year,
                    $amount_committed,
                    $quantity_committed,
                    $quantity_unit,
                    $start_date,
                    $end_date,
                    $notes
                );

                if ($stmt->execute()) {
                    $message = "Sponsorship registered successfully!";
                } else {
                    $message = "Error adding sponsorship: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
    $type_stmt->close();
}
?>

<div class="container">
    <div class="form-card">
        <h2>Add Sponsorship</h2>

        <?php if (!empty($message)): ?>
            <p class="form-message"><?= htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <form method="POST" action="sponsorship.php">
            <div class="form-grid">
                <!-- Sponsor Select -->
                <div class="form-group">
                    <label for="sponsor_id">Sponsor</label>
                    <select id="sponsor_id" name="sponsor_id" required>
                        <option value="">Select sponsor</option>
                        <?php while ($s = $sponsors->fetch_assoc()): ?>
                            <option value="<?= (int)$s['sponsor_id']; ?>">
                                <?= htmlspecialchars($s['sponsor_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Student Select -->
                <div class="form-group">
                    <label for="student_id">Student</label>
                    <select id="student_id" name="student_id" required>
                        <option value="">Select student</option>
                        <?php while ($st = $students->fetch_assoc()): ?>
                            <option value="<?= (int)$st['student_id']; ?>">
                                <?= htmlspecialchars($st['admission_no'] . " - " . $st['full_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Contribution Type -->
                <div class="form-group">
                    <label for="contribution_type_id">Contribution Type</label>
                    <select id="contribution_type_id" name="contribution_type_id" required>
                        <option value="">Select contribution</option>
                        <?php while ($ct = $contribution_types->fetch_assoc()): ?>
                            <option value="<?= (int)$ct['contribution_type_id']; ?>" data-type="<?= htmlspecialchars($ct['name']); ?>">
                                <?= htmlspecialchars($ct['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Academic Year -->
                <div class="form-group">
                    <label for="academic_year">Academic Year</label>
                    <input type="number" id="academic_year" name="academic_year" value="<?= date('Y'); ?>" min="2000" max="2100" required>
                </div>

                <!-- Financial Amount -->
                <div class="form-group" id="amount_group">
                    <label for="amount_committed">Amount Committed (TZS)</label>
                    <input type="number" id="amount_committed" name="amount_committed" placeholder="e.g. 900000" min="0" step="0.01">
                </div>

                <!-- Physical Quantity -->
                <div class="form-group" id="quantity_group" style="display: none;">
                    <label for="quantity_committed">Quantity Committed</label>
                    <input type="number" id="quantity_committed" name="quantity_committed" placeholder="e.g. 100" min="0" step="0.01">
                </div>

                <!-- Unit selection -->
                <div class="form-group" id="unit_group" style="display: none;">
                    <label for="quantity_unit">Unit</label>
                    <select id="quantity_unit" name="quantity_unit">
                        <option value="">Select unit</option>
                        <option value="kg">Kilograms (kg)</option>
                        <option value="bags">Bags</option>
                        <option value="litres">Litres</option>
                        <option value="pieces">Pieces</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <!-- Dates -->
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date">
                </div>

                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date">
                </div>

                <div class="form-group full-width">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" placeholder="Additional details..."></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit">Add Sponsorship</button>
                <a href="index.php" class="btn">Back to Sponsors</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const contributionSelect = document.getElementById("contribution_type_id");
    const amountGroup = document.getElementById("amount_group");
    const quantityGroup = document.getElementById("quantity_group");
    const unitGroup = document.getElementById("unit_group");
    const amountInput = document.getElementById("amount_committed");
    const quantityInput = document.getElementById("quantity_committed");
    const unitInput = document.getElementById("quantity_unit");

    function updateContributionFields() {
        const selectedOption = contributionSelect.options[contributionSelect.selectedIndex];
        if (!selectedOption || !selectedOption.value) {
            amountGroup.style.display = "block";
            quantityGroup.style.display = "none";
            unitGroup.style.display = "none";
            amountInput.required = false;
            quantityInput.required = false;
            unitInput.required = false;
            return;
        }

        const type = selectedOption.getAttribute("data-type");

        if (type === "Food" || type === "Hostel") {
            amountGroup.style.display = "block";
            quantityGroup.style.display = "none";
            unitGroup.style.display = "none";

            amountInput.required = true;
            quantityInput.required = false;
            unitInput.required = false;
            
            quantityInput.value = "";
            unitInput.value = "";
        } else if (type === "Maize" || type === "Beans") {
            amountGroup.style.display = "none";
            quantityGroup.style.display = "block";
            unitGroup.style.display = "block";

            amountInput.required = false;
            quantityInput.required = true;
            unitInput.required = true;
            
            amountInput.value = "";
        }
    }

    contributionSelect.addEventListener("change", updateContributionFields);
    updateContributionFields();
});
</script>

<?php require_once "../includes/footer.php"; ?>