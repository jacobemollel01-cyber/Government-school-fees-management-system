<?php

$current_page = basename($_SERVER['PHP_SELF']);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>School Contributions</title>

    <link rel="stylesheet" href="/school-fees/css/style.css">

</head>

<body>


<header class="main-header">


    <!-- LOGO -->

    <div class="logo">
        School Contributions
    </div>

<button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Open navigation menu">
    ☰
</button>
    <!-- NAVIGATION -->

    <nav class="main-nav">


        <!-- DASHBOARD -->

        <a href="/school-fees/dashboard.php">
            Dashboard
        </a>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
        <!-- ADMIN PLACE (VISIBLE TO ADMINS ONLY) -->
        <div class="nav-dropdown">

            <button class="nav-dropdown-btn">

                Admin place

                <span class="dropdown-arrow">▼</span>

            </button>


            <div class="dropdown-menu">

                <a href="/school-fees/admin/admin dashboard.php">
                    Admin Dashboard
                </a>

                <a href="/school-fees/admin/manage_fee_thresholds.php">
                    <span class="icon">⚙️</span> Fee & Quota Settings
                </a>

            </div>

        </div>
        <?php endif; ?>


        <!-- STUDENTS -->

        <div class="nav-dropdown">

            <button class="nav-dropdown-btn">

                Students

                <span class="dropdown-arrow">▼</span>

            </button>


            <div class="dropdown-menu">

                <a href="/school-fees/students/index.php">
                    View Students
                </a>

                <a href="/school-fees/students/add.php">
                    Add Student
                </a>

            </div>

        </div>



        <!-- PAYMENTS -->

        <div class="nav-dropdown">

            <button class="nav-dropdown-btn">

                Payments

                <span class="dropdown-arrow">▼</span>

            </button>


            <div class="dropdown-menu">

                <a href="/school-fees/payments/index.php">
                    View Payments
                </a>

                <a href="/school-fees/payments/add.php">
                    Record Payment
                </a>

            </div>

        </div>



        <!-- SPONSORS -->

        <div class="nav-dropdown">

            <button class="nav-dropdown-btn">

                Sponsors

                <span class="dropdown-arrow">▼</span>

            </button>


            <div class="dropdown-menu">

                <a href="/school-fees/sponsors/index.php">
                    View Sponsors
                </a>

                <a href="/school-fees/sponsors/add.php">
                    Add Sponsor
                </a>

                <a href="/school-fees/sponsors/sponsorships.php">
                    Add Sponsorship
                </a>

            </div>

        </div>



        <!-- CONTRIBUTIONS -->

        <div class="nav-dropdown">

            <button class="nav-dropdown-btn">

                Contributions

                <span class="dropdown-arrow">▼</span>

            </button>


            <div class="dropdown-menu">

                <a href="/school-fees/contributions/index.php">
                    View Contributions
                </a>

                <a href="/school-fees/contributions/add.php">
                    Record Maize / Beans
                </a>

            </div>

        </div>



        <!-- EXPENSES -->

        <div class="nav-dropdown">

            <button class="nav-dropdown-btn">

                Expenses

                <span class="dropdown-arrow">▼</span>

            </button>


            <div class="dropdown-menu">

                <a href="/school-fees/expenses/index.php">
                    View Expenses
                </a>

                <a href="/school-fees/expenses/add.php">
                    Record Expense
                </a>

            </div>

        </div>



        <!-- REPORTS -->

        <div class="nav-dropdown">

            <button class="nav-dropdown-btn">

                Reports

                <span class="dropdown-arrow">▼</span>

            </button>


            <div class="dropdown-menu">

                <a href="../reports/class_report.php">
                    <span class="icon">📋</span> Class Financial Report
                </a>

                <a href="../reports/student_report.php">
                    <span class="icon">👤</span> Individual Student Statement
                </a>

            </div>

        </div>

<!-- CHANGE PASSWORD LINK -->
        <a href="/school-fees/auth/change_password.php" style="color: #cbd5e1; text-decoration: none; font-size: 0.85rem; font-weight: 600; margin-left: 12px;">
            🔑 Password
        </a>

        <!-- LOGOUT BUTTON -->

        <!-- HTML content above -->

<style>
  /* This is inside the style block */
  .user-menu {
    display: inline-flex;
    align-items: center;
    gap: 12px;
  }
  .btn-logout {
      display: inline-flex;
      align-items: center;
      padding: 6px 14px;
      font-size: 0.82rem;
      background-color: #fef2f2;
      color: #dc2626;
      border-radius: 6px;
      text-decoration: none;
  }
  
  .btn-logout:hover {
      background-color: #dc2626;
      color: #ffffff;
  }
</style>

<!-- HTML content below -->
<div class="user-menu">
    <a href="/school-fees/auth/logout.php" class="btn-logout">Logout</a>
</div>

    </nav>

</header>