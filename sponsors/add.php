<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $sponsor_name = trim($_POST["sponsor_name"]);
    $sponsor_type = $_POST["sponsor_type"];
    $contact_person = trim($_POST["contact_person"]);
    $phone = trim($_POST["phone"]);
    $email = trim($_POST["email"]);
    $address = trim($_POST["address"]);
    $notes = trim($_POST["notes"]);

    $sql = "INSERT INTO sponsors
            (
                sponsor_name,
                sponsor_type,
                contact_person,
                phone,
                email,
                address,
                notes
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        $message = "Database error: " . $conn->error;

    } else {

        $stmt->bind_param(
            "sssssss",
            $sponsor_name,
            $sponsor_type,
            $contact_person,
            $phone,
            $email,
            $address,
            $notes
        );

        if ($stmt->execute()) {

            $message = "Sponsor added successfully!";

        } else {

            $message = "Error adding sponsor: " . $stmt->error;

        }

        $stmt->close();
    }
}

include "../includes/header.php";

?>

<div class="container">

    <div class="form-card">

        <h2>Add Sponsor</h2>

        <?php if ($message != ""): ?>

            <p class="form-message">
                <?= htmlspecialchars($message); ?>
            </p>

        <?php endif; ?>

        <form method="POST">

            <div class="form-grid">

                <!-- Sponsor Name -->
                <div class="form-group">

                    <label for="sponsor_name">
                        Sponsor Name
                    </label>

                    <input
                        type="text"
                        id="sponsor_name"
                        name="sponsor_name"
                        placeholder="e.g. ABC Foundation"
                        required
                    >

                </div>


                <!-- Sponsor Type -->
                <div class="form-group">

                    <label for="sponsor_type">
                        Sponsor Type
                    </label>

                    <select
                        id="sponsor_type"
                        name="sponsor_type"
                        required
                    >

                        <option value="">
                            Select sponsor type
                        </option>

                        <option value="Company">
                            Company
                        </option>

                        <option value="Organization">
                            Organization
                        </option>

                        <option value="Individual">
                            Individual
                        </option>

                        <option value="Other">
                            Other
                        </option>

                    </select>

                </div>


                <!-- Contact Person -->
                <div class="form-group">

                    <label for="contact_person">
                        Contact Person
                    </label>

                    <input
                        type="text"
                        id="contact_person"
                        name="contact_person"
                        placeholder="Full name of contact person"
                    >

                </div>


                <!-- Phone -->
                <div class="form-group">

                    <label for="phone">
                        Phone Number
                    </label>

                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        placeholder="e.g. +255 700 000 000"
                    >

                </div>


                <!-- Email -->
                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="e.g. sponsor@example.com"
                    >

                </div>


                <!-- Address -->
                <div class="form-group">

                    <label for="address">
                        Address
                    </label>

                    <input
                        type="text"
                        id="address"
                        name="address"
                        placeholder="Sponsor address"
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
                        placeholder="Additional information about the sponsor..."
                    ></textarea>

                </div>

            </div>


            <!-- Buttons -->
            <div class="form-actions">

                <button type="submit">
                    Add Sponsor
                </button>

                <a href="index.php" class="btn">
                    Back to Sponsors
                </a>

            </div>

        </form>

    </div>

</div>