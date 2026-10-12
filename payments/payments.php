/* =========================================================
   SCHOOL CONTRIBUTIONS MANAGEMENT SYSTEM
   DARK NAVY • BLACK • GOLD THEME (UNIFIED & DEDUPLICATED)
========================================================= */

/* =========================
   RESET & VARIABLES
========================= */

:root {
    --navy: #071a2f;
    --navy-light: #0d2744;
    --navy-hover: #12385f;
    --black: #111111;
    --dark: #171717;
    --dark-card: #1d1d1d;
    --gold: #c9a227;
    --gold-light: #e0bd4f;
    --gold-soft: #f4e4a6;
    --white: #ffffff;
    --background: #f3f4f6;
    --text: #1f2937;
    --muted: #6b7280;
    --border: #d9dce1;
    --success: #2f855a;
    --danger: #b83232;
}

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: var(--background);
    color: var(--text);
    line-height: 1.5;
}


/* =========================================================
   HEADER & RESPONSIVE NAVIGATION
========================================================= */

.main-header {
    width: 100%;
    min-height: 70px;
    padding: 0 30px;
    background: linear-gradient(
        90deg,
        var(--black),
        var(--navy),
        var(--navy-light)
    );
    color: var(--white);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    border-bottom: 3px solid var(--gold);
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.22);
    position: relative;
    z-index: 1000;
}

.main-header .logo {
    color: var(--gold-light);
    font-size: 21px;
    font-weight: 700;
    letter-spacing: 0.3px;
    white-space: nowrap;
    flex-shrink: 0;
}

/* Main Navigation */
.main-header .main-nav {
    display: flex;
    align-items: stretch;
    justify-content: flex-end;
    gap: 2px;
    margin-left: auto;
    min-width: 0;
}

/* Direct Nav Links */
.main-header .main-nav > a {
    color: var(--white);
    text-decoration: none;
    padding: 24px 13px;
    font-size: 14px;
    font-weight: 600;
    white-space: nowrap;
    transition: background 0.2s ease, color 0.2s ease;
}

.main-header .main-nav > a:hover {
    background: rgba(201, 162, 39, 0.12);
    color: var(--gold-light);
}

/* Dropdown Container */
.main-header .nav-dropdown {
    position: relative;
    display: flex;
    align-items: stretch;
}

/* Dropdown Button */
.main-header .nav-dropdown-btn {
    background: transparent;
    border: none;
    color: var(--white);
    padding: 0 13px;
    min-height: 67px;
    font-size: 14px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 7px;
    transition: background 0.2s ease, color 0.2s ease;
}

.main-header .nav-dropdown-btn:hover,
.main-header .nav-dropdown:hover .nav-dropdown-btn {
    background: rgba(201, 162, 39, 0.12);
    color: var(--gold-light);
}

.main-header .dropdown-arrow {
    font-size: 10px;
    color: var(--gold);
    transition: transform 0.2s ease;
}

.main-header .nav-dropdown:hover .dropdown-arrow {
    transform: rotate(180deg);
}

/* Dropdown Menu */
.main-header .dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    min-width: 220px;
    background: var(--navy);
    border: 1px solid rgba(201, 162, 39, 0.55);
    border-top: 3px solid var(--gold);
    box-shadow: 0 12px 25px rgba(0, 0, 0, 0.28);
    display: none;
    padding: 7px 0;
    z-index: 1100;
}

@media (min-width: 851px) {
    .main-header .nav-dropdown:hover .dropdown-menu {
        display: block;
    }
}

/* Dropdown Links */
.main-header .dropdown-menu a {
    display: block;
    color: var(--white);
    text-decoration: none;
    padding: 12px 18px;
    font-size: 14px;
    font-weight: 500;
    border-left: 3px solid transparent;
    transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

.main-header .dropdown-menu a:hover {
    background: var(--navy-hover);
    color: var(--gold-light);
    border-left-color: var(--gold);
}

/* Password Link */
.main-header .password-link {
    color: #cbd5e1 !important;
    text-decoration: none;
    font-size: 0.85rem !important;
    font-weight: 600;
    margin-left: 12px;
    padding: 12px 10px !important;
}

.main-header .password-link:hover {
    color: var(--gold-light) !important;
    background: transparent !important;
}

/* =========================================================
   HAMBURGER BUTTON (MOBILE)
========================================================= */

.mobile-menu-btn {
    display: none;
    width: 44px;
    height: 44px;
    padding: 8px;
    margin-left: auto;
    background: transparent;
    border: 1px solid rgba(201, 162, 39, 0.5);
    border-radius: 6px;
    cursor: pointer;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 5px;
    flex-shrink: 0;
}

.mobile-menu-btn span {
    display: block;
    width: 24px;
    height: 2px;
    background: var(--gold-light);
    border-radius: 2px;
    transition: transform 0.25s ease, opacity 0.25s ease;
}

.mobile-menu-btn:hover {
    background: rgba(201, 162, 39, 0.12);
    border-color: var(--gold);
}

/* Hamburger to 'X' state */
.mobile-menu-btn.active span:nth-child(1) {
    transform: translateY(7px) rotate(45deg);
}

.mobile-menu-btn.active span:nth-child(2) {
    opacity: 0;
}

.mobile-menu-btn.active span:nth-child(3) {
    transform: translateY(-7px) rotate(-45deg);
}


/* =========================================================
   LOGOUT BUTTON - HEADER
========================================================= */

.main-header .main-nav .user-menu {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin-left: 8px !important;
    padding: 0 !important;
    width: auto !important;
    flex-shrink: 0;
}

.main-header .main-nav .user-menu .btn-logout {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: auto !important;
    min-width: 75px;
    height: 36px;
    padding: 0 14px !important;
    margin: 0 !important;
    background: #fef2f2 !important;
    color: #dc2626 !important;
    border: 1px solid #dc2626 !important;
    border-radius: 6px;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 13px !important;
    font-weight: 700 !important;
    line-height: 1;
    text-decoration: none !important;
    white-space: nowrap;
    cursor: pointer;
    transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease, transform 0.15s ease;
}

.main-header .main-nav .user-menu .btn-logout:hover {
    background: #dc2626 !important;
    color: #ffffff !important;
    border-color: #dc2626 !important;
    transform: translateY(-1px);
}


/* =========================================================
   PAGE LAYOUT & HEADINGS
========================================================= */

.container {
    width: min(94%, 1400px);
    margin: 32px auto;
}

h1 {
    color: var(--navy);
    font-size: 30px;
    margin: 0 0 8px;
}

h2 {
    color: var(--navy);
    font-size: 22px;
    margin: 0 0 18px;
}

h3 {
    margin-top: 0;
}

a {
    color: var(--navy);
}

a:hover {
    color: #8a6d00;
}


/* =========================================================
   DASHBOARD HEADER & SECTIONS
========================================================= */

.dashboard-header {
    margin-bottom: 28px;
    padding-bottom: 18px;
    border-bottom: 2px solid var(--gold);
}

.dashboard-header h1 {
    margin-bottom: 5px;
}

.dashboard-subtitle {
    margin: 0;
    color: var(--muted);
    font-size: 15px;
}

.dashboard-section {
    margin-bottom: 35px;
}

.dashboard-table-wrapper {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 8px;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.07);
}


/* =========================================================
   TABLES (GENERAL, PAYMENTS & STUDENTS)
========================================================= */

table,
.dashboard-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--white);
}

table th,
.dashboard-table th {
    background: var(--navy);
    color: var(--gold-light);
    padding: 15px 16px;
    text-align: left;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
    border-bottom: 3px solid var(--gold);
}

table td,
.dashboard-table td {
    padding: 15px 16px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

table tbody tr:nth-child(even),
.dashboard-table tbody tr:nth-child(even) {
    background: #fafafa;
}

table tbody tr:hover,
.dashboard-table tbody tr:hover {
    background: #fffdf5;
}

/* Payments Table Specifics */
.payments-table {
    width: 100%;
    min-width: 1250px;
    border-collapse: collapse;
    background: var(--white);
}

.payments-table th {
    background: var(--navy);
    color: var(--gold-light);
    padding: 17px 18px;
    text-align: left;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
    border-right: 1px solid rgba(255, 255, 255, 0.1);
    border-bottom: 3px solid var(--gold);
}

.payments-table td {
    padding: 17px 18px;
    border-right: 1px solid #e3e5e8;
    border-bottom: 2px solid #d4d7dc;
    white-space: nowrap;
    vertical-align: middle;
}

.payments-table tbody tr {
    height: 62px;
}

.payments-table tbody tr:nth-child(even) {
    background: #fafafa;
}

.payments-table tbody tr:hover td {
    background: #fffdf5;
}

.payment-amount {
    color: var(--navy);
    font-size: 14px;
}

.payment-reference {
    font-family: Consolas, "Courier New", monospace;
    font-size: 13px;
    background: #f1f3f5;
    padding: 5px 8px;
    border-radius: 4px;
}

.table-muted {
    color: #9ca3af;
    font-style: italic;
}

/* Student Status Table Specifics */
.student-status-table {
    width: 100%;
    min-width: 1450px;
    border-collapse: collapse;
    background: var(--white);
}

.student-status-table th {
    background: var(--navy);
    color: var(--gold-light);
    padding: 17px 18px;
    text-align: left;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
    border-right: 1px solid rgba(255, 255, 255, 0.1);
    border-bottom: 3px solid var(--gold);
}

.student-status-table td {
    padding: 18px;
    border-right: 1px solid #e3e5e8;
    border-bottom: 2px solid #d4d7dc;
    white-space: nowrap;
    vertical-align: middle;
}

.student-status-table tbody tr {
    height: 65px;
}

.student-status-table tbody tr:nth-child(even) {
    background: #fafafa;
}

.student-status-table tbody tr:hover td {
    background: #fffdf5;
}

.student-status-table td small {
    display: block;
    margin-top: 4px;
    color: var(--muted);
}

.student-status-table th:last-child,
.student-status-table td:last-child {
    min-width: 190px;
}

.student-status-table td:last-child .action-btn {
    display: inline-flex;
    width: auto;
    padding: 8px 13px;
    margin-right: 5px;
    font-size: 13px;
}


/* =========================================================
   PAYMENT VOID SYSTEM & STATUS BADGES
========================================================= */

.payment-voided-row {
    background: #f8f8f8 !important;
    opacity: 0.78;
}

.payment-voided-row td {
    color: #6b7280;
}

.payment-status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.payment-status.active {
    background: #e6f4ea;
    color: #276749;
    border: 1px solid #9ae6b4;
}

.payment-status.voided {
    background: #f3f3f3;
    color: #7a7a7a;
    border: 1px solid #c7c7c7;
}

.payment-actions {
    display: flex;
    align-items: center;
    gap: 7px;
    white-space: nowrap;
}

.table-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 7px 11px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    transition: all 0.2s ease;
}

.table-action.edit {
    background: var(--navy);
    color: var(--gold-light);
    border: 1px solid var(--navy);
}

.table-action.edit:hover {
    background: var(--black);
    color: var(--gold-light);
    border-color: var(--gold);
}

.table-action.void {
    background: #ffffff;
    color: #9b2c2c;
    border: 1px solid #c53030;
}

.table-action.void:hover {
    background: #9b2c2c;
    color: #ffffff;
}

.void-details {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 150px;
}

.void-details strong {
    color: #777777;
    font-size: 12px;
}

.void-details small {
    color: #777777;
    font-size: 11px;
    white-space: normal;
    line-height: 1.35;
}

.void-confirm-btn {
    background: #9b2c2c;
    color: #ffffff;
    border: 1px solid #9b2c2c;
}

.void-confirm-btn:hover {
    background: #7f1d1d;
    border-color: #7f1d1d;
    color: #ffffff;
}


/* =========================================================
   FORMS & INPUTS
========================================================= */

.form-card {
    background: var(--white);
    padding: 32px;
    max-width: 950px;
    margin: 25px auto;
    border-radius: 10px;
    border-top: 4px solid var(--gold);
    box-shadow: 0 5px 18px rgba(0, 0, 0, 0.09);
}

.form-card h2 {
    margin-top: 0;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 22px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    margin-bottom: 8px;
    font-weight: 700;
    color: var(--navy);
    font-size: 14px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid #cbd0d6;
    border-radius: 6px;
    font-size: 15px;
    font-family: inherit;
    color: var(--text);
    background: var(--white);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(201, 162, 39, 0.16);
}

.form-group textarea {
    min-height: 110px;
    resize: vertical;
}

.form-message {
    padding: 12px 15px;
    margin-bottom: 20px;
    background: #f7f1d7;
    color: #705900;
    border-left: 4px solid var(--gold);
    border-radius: 4px;
}


/* =========================================================
   BUTTONS & ACTIONS
========================================================= */

.form-actions,
.quick-actions {
    margin-top: 28px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

button,
.btn,
.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 20px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 700;
    font-family: inherit;
    text-decoration: none;
    cursor: pointer;
    background: var(--navy);
    color: var(--gold-light);
    border: 1px solid var(--navy);
    transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease, transform 0.15s ease;
}

button:hover,
.btn:hover,
.action-btn:hover {
    background: var(--black);
    color: var(--gold-light);
    border-color: var(--gold);
    transform: translateY(-1px);
}

.action-btn.secondary {
    background: var(--white);
    color: var(--navy) !important;
    border: 1px solid var(--navy);
}

.action-btn.secondary:hover {
    background: var(--navy);
    color: var(--gold-light) !important;
}


/* =========================================================
   CARDS & SUMMARY BLOCKS
========================================================= */

.dashboard-cards {
    display: grid;
    gap: 18px;
}

.financial-cards {
    grid-template-columns: repeat(3, 1fr);
}

.contribution-cards,
.sponsor-cards {
    grid-template-columns: repeat(4, 1fr);
}

.card {
    background: var(--white);
    padding: 23px;
    border-radius: 8px;
    border: 1px solid var(--border);
    border-top: 3px solid var(--gold);
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.07);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 7px 18px rgba(0, 0, 0, 0.11);
}

.card h3 {
    color: var(--navy);
    margin-bottom: 12px;
}

.card-label {
    color: var(--muted);
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 10px;
}

.card .amount {
    font-size: 22px;
    font-weight: 700;
    color: var(--black);
}

.balance-card {
    border-left: 4px solid var(--gold);
}


/* =========================================================
   RESPONSIVE MEDIA QUERIES
========================================================= */

@media (max-width: 1100px) {
    .main-header {
        padding: 0 18px;
        gap: 12px;
    }

    .main-header .logo {
        font-size: 18px;
    }

    .main-header .main-nav > a,
    .main-header .nav-dropdown-btn {
        font-size: 13px;
        padding-left: 8px;
        padding-right: 8px;
    }

    .main-header .password-link {
        margin-left: 4px;
    }

    .financial-cards,
    .contribution-cards,
    .sponsor-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 850px) {
    .main-header {
        min-height: 70px;
        padding: 12px 16px;
        flex-wrap: wrap;
        gap: 0;
    }

    .main-header .logo {
        text-align: left;
        font-size: 18px;
        line-height: 1.2;
    }

    .mobile-menu-btn {
        display: flex;
    }

    .main-header .main-nav {
        display: none;
        width: 100%;
        flex-direction: column;
        align-items: stretch;
        justify-content: flex-start;
        margin: 12px 0 0;
        padding: 8px 0;
        border-top: 1px solid rgba(201, 162, 39, 0.35);
    }

    .main-header .main-nav.mobile-open {
        display: flex;
    }

    .main-header .main-nav > a {
        width: 100%;
        padding: 14px 15px;
        text-align: left;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .main-header .nav-dropdown {
        display: block;
        width: 100%;
    }

    .main-header .nav-dropdown-btn {
        width: 100%;
        min-height: auto;
        padding: 14px 15px;
        justify-content: space-between;
        text-align: left;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .main-header .dropdown-menu {
        position: static;
        width: 100%;
        min-width: 0;
        display: none;
        box-shadow: none;
        border-top: 2px solid var(--gold);
        border-left: none;
        border-right: none;
        padding: 0;
    }

    .main-header .nav-dropdown.dropdown-open .dropdown-menu {
        display: block;
    }

    .main-header .nav-dropdown.dropdown-open .dropdown-arrow {
        transform: rotate(180deg);
    }

    .main-header .dropdown-menu a {
        padding: 13px 30px;
        background: rgba(0, 0, 0, 0.15);
        border-left: 3px solid transparent;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .main-header .password-link {
        width: 100%;
        margin: 0 !important;
        padding: 14px 15px !important;
        font-size: 14px !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .main-header .main-nav .user-menu {
        display: flex !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 12px 15px !important;
        box-sizing: border-box;
    }

    .main-header .main-nav .user-menu .btn-logout {
        width: 100% !important;
        min-width: 0;
        height: 42px;
        padding: 0 15px !important;
        font-size: 14px !important;
    }
}

@media (max-width: 650px) {
    h1 {
        font-size: 25px;
    }

    h2 {
        font-size: 20px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full-width {
        grid-column: auto;
    }

    .form-card {
        padding: 22px;
    }

    .form-actions {
        flex-direction: column;
    }

    button,
    .btn,
    .action-btn {
        width: 100%;
    }

    .financial-cards,
    .contribution-cards,
    .sponsor-cards {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 500px) {
    .main-header {
        padding: 11px 12px;
    }

    .main-header .logo {
        font-size: 17px;
    }

    .mobile-menu-btn {
        width: 42px;
        height: 42px;
    }

    .main-header .main-nav {
        margin-top: 10px;
    }

    .main-header .nav-dropdown-btn,
    .main-header .main-nav > a {
        font-size: 14px;
    }

    table th,
    table td {
        padding: 11px 12px;
        font-size: 13px;
    }
}


/* =========================================================
   PRINT STYLES
========================================================= */

@media print {
    .main-header,
    .site-footer,
    .no-print,
    .quick-actions {
        display: none !important;
    }

    body {
        background: #ffffff;
        color: #000000;
        font-size: 12pt;
    }

    .container {
        width: 100%;
        margin: 0;
        padding: 0;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th, td {
        border: 1px solid #000000;
        padding: 6px;
    }
}