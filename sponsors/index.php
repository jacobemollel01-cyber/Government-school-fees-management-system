<?php
require_once "../auth/auth_check.php";
require_once "../config/db.php";

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// 1. Detect primary key column name (id or sponsor_id)
$pk_check = $conn->query("SHOW COLUMNS FROM sponsors LIKE 'sponsor_id'");
$primary_key = ($pk_check && $pk_check->num_rows > 0) ? 'sponsor_id' : 'id';

// Handle Delete Request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $sponsor_id = (int)$_GET['id'];

    // Check for linked sponsorships
    $check_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM sponsorships WHERE sponsor_id = ?");
    if ($check_stmt) {
        $check_stmt->bind_param("i", $sponsor_id);
        $check_stmt->execute();
        $linked_count = $check_stmt->get_result()->fetch_assoc()['total'];
        $check_stmt->close();
    } else {
        $linked_count = 0;
    }

    if ($linked_count > 0) {
        $error = "Cannot delete sponsor: This sponsor is currently linked to active sponsorships.";
    } else {
        $del_stmt = $conn->prepare("DELETE FROM sponsors WHERE {$primary_key} = ?");
        if ($del_stmt) {
            $del_stmt->bind_param("i", $sponsor_id);
            if ($del_stmt->execute()) {
                $message = "Sponsor deleted successfully.";
            } else {
                $error = "Failed to delete sponsor.";
            }
            $del_stmt->close();
        }
    }
}

// 2. Fetch all sponsors using the correct primary key for sorting
$query = "SELECT * FROM sponsors ORDER BY {$primary_key} DESC";
$sponsors = $conn->query($query);

require_once "../includes/header.php";
?>

<div style="max-width: 1100px; margin: 30px auto; padding: 0 20px;">

    <!-- Header Block -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h2 style="margin: 0; color: #0f172a; font-size: 1.6rem;">Sponsors Management</h2>
            <p style="margin: 4px 0 0 0; color: #64748b; font-size: 0.9rem;">Manage school sponsors and organizational partners</p>
        </div>
        <a href="add.php" style="background-color: #2563eb; color: #ffffff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem;">
            + Add New Sponsor
        </a>
    </div>

    <!-- Feedback Alerts -->
    <?php if (!empty($message)): ?>
        <div style="background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; padding: 12px 16px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 20px;">
            ✨ <?= htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div style="background-color: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; padding: 12px 16px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 20px;">
            ⚠️ <?= htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Table Card -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
            <thead>
                <tr style="background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-weight: 600;">
                    <th style="padding: 14px 18px;">#</th>
                    <th style="padding: 14px 18px;">Sponsor Name</th>
                    <th style="padding: 14px 18px;">Contact Person</th>
                    <th style="padding: 14px 18px;">Phone</th>
                    <th style="padding: 14px 18px;">Email</th>
                    <th style="padding: 14px 18px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($sponsors && $sponsors->num_rows > 0): ?>
                    <?php $count = 1; while ($row = $sponsors->fetch_assoc()): ?>
                        <?php 
                            $row_id = $row[$primary_key];
                            $sponsor_name = $row['name'] ?? $row['sponsor_name'] ?? 'Unnamed';
                            $contact_person = $row['contact_person'] ?? '—';
                            $phone = $row['phone'] ?? $row['phone_number'] ?? '—';
                            $email = $row['email'] ?? '—';
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; color: #1e293b;">
                            <td style="padding: 14px 18px; color: #64748b;"><?= $count++; ?></td>
                            <td style="padding: 14px 18px; font-weight: 600; color: #0f172a;">
                                <?= htmlspecialchars($sponsor_name); ?>
                            </td>
                            <td style="padding: 14px 18px; color: #475569;">
                                <?= htmlspecialchars($contact_person); ?>
                            </td>
                            <td style="padding: 14px 18px; color: #475569;">
                                <?= htmlspecialchars($phone); ?>
                            </td>
                            <td style="padding: 14px 18px; color: #475569;">
                                <?= htmlspecialchars($email); ?>
                            </td>
                            <td style="padding: 14px 18px; text-align: right; white-space: nowrap;">
                                <a href="edit.php?id=<?= $row_id; ?>" style="display: inline-flex; align-items: center; gap: 4px; background-color: #f1f5f9; color: #2563eb; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.82rem; margin-right: 6px;">
                                    ✏️ Edit
                                </a>
                                <a href="index.php?action=delete&id=<?= $row_id; ?>" onclick="return confirm('Are you sure you want to delete <?= htmlspecialchars(addslashes($sponsor_name)); ?>?');" style="display: inline-flex; align-items: center; gap: 4px; background-color: #fef2f2; color: #dc2626; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.82rem;">
                                    🗑️ Delete
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="padding: 30px; text-align: center; color: #64748b;">
                            No sponsors registered yet. Click <strong>+ Add New Sponsor</strong> above to create one.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php require_once "../includes/footer.php"; ?>