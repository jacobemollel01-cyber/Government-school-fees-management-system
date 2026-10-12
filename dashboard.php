<?php
require_once __DIR__ . "/auth/auth_check.php";
require_once "config/db.php";
require_once "includes/header.php";


/* =========================
   ACTIVE STUDENTS
========================= */

$sql = "
    SELECT COUNT(*) AS total_students
    FROM students
    WHERE status = 'Active'
";

$result = $conn->query($sql);
$row = $result->fetch_assoc();

$total_students = $row['total_students'];


/* =========================
   TOTAL MONEY RECEIVED
   ACTIVE PAYMENTS ONLY
========================= */

$sql = "
    SELECT COALESCE(SUM(amount), 0) AS total_received
    FROM payments
    WHERE status = 'Active'
";

$result = $conn->query($sql);
$row = $result->fetch_assoc();

$total_received = (float) $row['total_received'];


/* =========================
   TOTAL MONEY SPENT
========================= */

$sql = "
    SELECT COALESCE(SUM(amount), 0) AS total_spent
    FROM expenses
";

$result = $conn->query($sql);
$row = $result->fetch_assoc();

$total_spent = (float) $row['total_spent'];


/* =========================
   CURRENT BALANCE
========================= */

$current_balance = $total_received - $total_spent;


/* =========================
   FOOD MONEY RECEIVED
   ACTIVE PAYMENTS ONLY
========================= */

$sql = "
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM payments
    WHERE contribution_type_id = 2
    AND status = 'Active'
";

$result = $conn->query($sql);
$row = $result->fetch_assoc();

$food_received = (float) $row['total'];


/* =========================
   HOSTEL MONEY RECEIVED
   ACTIVE PAYMENTS ONLY
========================= */

$sql = "
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM payments
    WHERE contribution_type_id = 1
    AND status = 'Active'
";

$result = $conn->query($sql);
$row = $result->fetch_assoc();

$hostel_received = (float) $row['total'];


/* =========================
   TOTAL MAIZE
   ACTIVE CONTRIBUTIONS ONLY
========================= */

$sql = "
    SELECT COALESCE(SUM(quantity), 0) AS total
    FROM food_contributions
    WHERE contribution_type_id = 3
    AND status = 'Active'
";

$result = $conn->query($sql);
$row = $result->fetch_assoc();

$total_maize = (float) $row['total'];


/* =========================
   TOTAL BEANS
   ACTIVE CONTRIBUTIONS ONLY
========================= */

$sql = "
    SELECT COALESCE(SUM(quantity), 0) AS total
    FROM food_contributions
    WHERE contribution_type_id = 4
    AND status = 'Active'
";

$result = $conn->query($sql);
$row = $result->fetch_assoc();

$total_beans = (float) $row['total'];


/* =========================
   SPONSOR COMMITMENTS
========================= */

$sql = "
    SELECT COALESCE(SUM(amount_committed), 0) AS total
    FROM sponsorships
";

$result = $conn->query($sql);
$row = $result->fetch_assoc();

$total_sponsor_commitments = (float) $row['total'];


/* =========================
   SPONSOR PAYMENTS
   ACTIVE PAYMENTS ONLY
========================= */

$sql = "
    SELECT COALESCE(SUM(p.amount), 0) AS total
    FROM payments p

    INNER JOIN sponsorships sp
        ON p.student_id = sp.student_id
        AND p.contribution_type_id = sp.contribution_type_id
        AND YEAR(p.payment_date) = sp.academic_year

    INNER JOIN sponsors s
        ON sp.sponsor_id = s.sponsor_id

    WHERE p.payer_name = s.sponsor_name
    AND p.status = 'Active'
";

$result = $conn->query($sql);
$row = $result->fetch_assoc();

$total_sponsor_payments = (float) $row['total'];


/* =========================
   SPONSOR OUTSTANDING
========================= */

$total_sponsor_outstanding =
    $total_sponsor_commitments - $total_sponsor_payments;

?>

<style>
    /* Modern Classic Dashboard Styles */
    .dashboard-wrapper {
        max-width: 1140px;
        margin: 30px auto;
        padding: 0 20px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #1e293b;
    }

    .dashboard-header-block {
        margin-bottom: 30px;
        padding-bottom: 15px;
        border-bottom: 1px solid #e2e8f0;
    }

    .dashboard-header-block h1 {
        margin: 0;
        font-size: 1.8rem;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.025em;
    }

    .dashboard-header-block p {
        margin: 4px 0 0 0;
        color: #64748b;
        font-size: 0.95rem;
    }

    .section-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #334155;
        margin: 0 0 16px 0;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 18px;
        margin-bottom: 35px;
    }

    .kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    }

    .kpi-label {
        font-size: 0.82rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.025em;
        margin-bottom: 8px;
        display: block;
    }

    .kpi-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        line-height: 1.2;
    }

    /* Highlight themes for specific KPIs */
    .kpi-card.balance-primary {
        background: #eff6ff;
        border-color: #bfdbfe;
    }
    .kpi-card.balance-primary .kpi-value {
        color: #1d4ed8;
    }

    .kpi-card.spent-accent {
        background: #fef2f2;
        border-color: #fecaca;
    }
    .kpi-card.spent-accent .kpi-value {
        color: #dc2626;
    }

    .kpi-card.received-accent {
        background: #ecfdf5;
        border-color: #a7f3d0;
    }
    .kpi-card.received-accent .kpi-value {
        color: #047857;
    }

    /* Action & Link Buttons */
    .actions-flex {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 35px;
    }

    .action-btn-primary {
        display: inline-flex;
        align-items: center;
        padding: 11px 20px;
        background-color: #2563eb;
        color: #ffffff !important;
        text-decoration: none;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.9rem;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        transition: background-color 0.2s;
    }

    .action-btn-primary:hover {
        background-color: #1d4ed8;
    }

    .action-btn-secondary {
        display: inline-flex;
        align-items: center;
        padding: 11px 20px;
        background-color: #ffffff;
        color: #334155 !important;
        border: 1px solid #cbd5e1;
        text-decoration: none;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.9rem;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        transition: background-color 0.2s, border-color 0.2s;
    }

    .action-btn-secondary:hover {
        background-color: #f8fafc;
        border-color: #94a3b8;
    }
</style>

<div class="dashboard-wrapper">

    <!-- HEADER SECTION -->
    <div class="dashboard-header-block">
        <h1>Dashboard</h1>
        <p>School contributions and financial overview</p>
    </div>

    <!-- 1. FINANCIAL OVERVIEW SECTION -->
    <h2 class="section-title">Financial Overview</h2>
    <div class="kpi-grid">
        <div class="kpi-card received-accent">
            <span class="kpi-label">Total Received</span>
            <div class="kpi-value">
                TZS <?= number_format($total_received, 2); ?>
            </div>
        </div>

        <div class="kpi-card spent-accent">
            <span class="kpi-label">Total Spent</span>
            <div class="kpi-value">
                TZS <?= number_format($total_spent, 2); ?>
            </div>
        </div>

        <div class="kpi-card balance-primary">
            <span class="kpi-label">Current Balance</span>
            <div class="kpi-value">
                TZS <?= number_format($current_balance, 2); ?>
            </div>
        </div>
    </div>

    <!-- 2. CONTRIBUTIONS SECTION -->
    <h2 class="section-title">Contributions Summary</h2>
    <div class="kpi-grid">
        <div class="kpi-card">
            <span class="kpi-label">Food Received</span>
            <div class="kpi-value">
                TZS <?= number_format($food_received, 2); ?>
            </div>
        </div>

        <div class="kpi-card">
            <span class="kpi-label">Hostel Received</span>
            <div class="kpi-value">
                TZS <?= number_format($hostel_received, 2); ?>
            </div>
        </div>

        <div class="kpi-card">
            <span class="kpi-label">Maize Contributed</span>
            <div class="kpi-value">
                <?= number_format($total_maize, 2); ?> kg
            </div>
        </div>

        <div class="kpi-card">
            <span class="kpi-label">Beans Contributed</span>
            <div class="kpi-value">
                <?= number_format($total_beans, 2); ?> kg
            </div>
        </div>
    </div>

    <!-- 3. STUDENTS & SPONSORS SECTION -->
    <h2 class="section-title">Students & Sponsorships</h2>
    <div class="kpi-grid">
        <div class="kpi-card">
            <span class="kpi-label">Active Students</span>
            <div class="kpi-value">
                <?= number_format($total_students); ?>
            </div>
        </div>

        <div class="kpi-card">
            <span class="kpi-label">Sponsor Commitments</span>
            <div class="kpi-value">
                TZS <?= number_format($total_sponsor_commitments, 2); ?>
            </div>
        </div>

        <div class="kpi-card">
            <span class="kpi-label">Sponsor Payments</span>
            <div class="kpi-value">
                TZS <?= number_format($total_sponsor_payments, 2); ?>
            </div>
        </div>

        <div class="kpi-card">
            <span class="kpi-label">Sponsor Outstanding</span>
            <div class="kpi-value" style="color: <?= $total_sponsor_outstanding > 0 ? '#d97706' : '#059669'; ?>;">
                TZS <?= number_format($total_sponsor_outstanding, 2); ?>
            </div>
        </div>
    </div>

    <!-- 4. QUICK ACTIONS SECTION -->
    <h2 class="section-title">Quick Actions</h2>
    <div class="actions-flex">
        <a href="students/add.php" class="action-btn-primary">+ Add Student</a>
        <a href="payments/add.php" class="action-btn-primary">+ Record Payment</a>
        <a href="contributions/add.php" class="action-btn-primary">+ Record Maize / Beans</a>
        <a href="expenses/add.php" class="action-btn-primary">+ Record Expense</a>
        <a href="sponsors/add.php" class="action-btn-primary">+ Add Sponsor</a>
    </div>

    <!-- 5. REPORTS SECTION -->
    <h2 class="section-title">Reports</h2>
    <div class="actions-flex">
        <a href="reports/student_status.php" class="action-btn-secondary">📄 Student Payment Status</a>
        <a href="reports/sponsorship_report.php" class="action-btn-secondary">📄 Sponsorship Report</a>
    </div>

</div>

<?php require_once "includes/footer.php"; ?>