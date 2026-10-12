<?php
require_once __DIR__ . "/../auth/auth_check.php";
require_once "../config/db.php";

$type = $_GET['type'] ?? '';

if ($type === 'student') {
    $student_id = (int)($_GET['student_id'] ?? 0);
    if ($student_id <= 0) die("Invalid Student ID.");

    // Fetch Student Info
    $stmt = $conn->prepare("
        SELECT s.full_name, s.admission_no, c.class_name, st.stream_name 
        FROM students s
        LEFT JOIN student_enrollments se ON s.student_id = se.student_id
        LEFT JOIN classes c ON se.class_id = c.class_id
        LEFT JOIN streams st ON se.stream_id = st.stream_id
        WHERE s.student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();

    // Set CSV Headers for Excel
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Student_Report_' . $student['admission_no'] . '.csv');

    $output = fopen('php://output', 'w');

    // Header info
    fputcsv($output, ['Student Name', $student['full_name']]);
    fputcsv($output, ['Admission No', $student['admission_no']]);
    fputcsv($output, ['Class', $student['class_name'] . ' ' . ($student['stream_name'] ?? '')]);
    fputcsv($output, []);

    // Payment Section
    fputcsv($output, ['CASH PAYMENTS']);
    fputcsv($output, ['Date & Time', 'Contribution', 'Period', 'Method', 'Payer', 'Amount (TZS)']);

    $payments = $conn->prepare("
        SELECT p.payment_date, ct.name AS contribution_name, p.payment_period, p.payment_method, p.payer_name, p.amount
        FROM payments p
        JOIN contribution_types ct ON p.contribution_type_id = ct.contribution_type_id
        WHERE p.student_id = ? AND p.status = 'Active'
        ORDER BY p.payment_date DESC
    ");
    $payments->bind_param("i", $student_id);
    $payments->execute();
    $res = $payments->get_result();

    $total = 0;
    while ($row = $res->fetch_assoc()) {
        $total += $row['amount'];
        fputcsv($output, [
            $row['payment_date'], // Formatted as YYYY-MM-DD HH:MM:SS
            $row['contribution_name'],
            $row['payment_period'],
            $row['payment_method'],
            $row['payer_name'] ?? '—',
            $row['amount']
        ]);
    }
    fputcsv($output, ['', '', '', '', 'Total Paid', $total]);
    fputcsv($output, []);

    // Food Contributions Section
    fputcsv($output, ['FOOD CONTRIBUTIONS']);
    fputcsv($output, ['Date & Time', 'Item', 'Quantity', 'Unit', 'Reference']);

    $food = $conn->prepare("
        SELECT fc.contribution_date, ct.name AS contribution_name, fc.quantity, fc.unit, fc.reference_number
        FROM food_contributions fc
        JOIN contribution_types ct ON fc.contribution_type_id = ct.contribution_type_id
        WHERE fc.student_id = ? AND fc.status = 'Active'
        ORDER BY fc.contribution_date DESC
    ");
    $food->bind_param("i", $student_id);
    $food->execute();
    $res_food = $food->get_result();

    while ($row = $res_food->fetch_assoc()) {
        fputcsv($output, [
            $row['contribution_date'],
            $row['contribution_name'],
            $row['quantity'],
            $row['unit'],
            $row['reference_number'] ?? '—'
        ]);
    }

    fclose($output);
    exit();
}

if ($type === 'class') {
    $class_id = (int)($_GET['class_id'] ?? 0);
    $stream_id = (int)($_GET['stream_id'] ?? 0);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Class_Summary_Report.csv');

    $output = fopen('php://output', 'w');

    fputcsv($output, ['CLASS SUMMARY FINANCIAL REPORT']);
    fputcsv($output, ['#', 'Admission No', 'Student Name', 'Payee', 'Total Cash Paid (TZS)', 'Maize (kg)', 'Beans (kg)']);

    $sql = "
        SELECT 
            s.admission_no,
            s.full_name,
            s.payee_type,
            s.payee_other,
            COALESCE(SUM(p.amount), 0) AS total_payments,
            COALESCE(SUM(CASE WHEN fc.contribution_type_id = 3 THEN fc.quantity ELSE 0 END), 0) AS total_maize,
            COALESCE(SUM(CASE WHEN fc.contribution_type_id = 4 THEN fc.quantity ELSE 0 END), 0) AS total_beans
        FROM student_enrollments se
        JOIN students s ON se.student_id = s.student_id
        LEFT JOIN payments p ON s.student_id = p.student_id AND p.status = 'Active'
        LEFT JOIN food_contributions fc ON s.student_id = fc.student_id AND fc.status = 'Active'
        WHERE se.class_id = ? AND se.stream_id = ? AND s.status = 'Active'
        GROUP BY s.student_id
        ORDER BY s.full_name ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $class_id, $stream_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $count = 1;
    while ($row = $res->fetch_assoc()) {
        fputcsv($output, [
            $count++,
            $row['admission_no'],
            $row['full_name'],
            $row['payee_type'] === 'Other' ? $row['payee_other'] : $row['payee_type'],
            $row['total_payments'],
            $row['total_maize'],
            $row['total_beans']
        ]);
    }

    fclose($output);
    exit();
}