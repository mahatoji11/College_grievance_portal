<?php
session_start();
include 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if (!isset($_GET['type']) || !isset($_GET['id'])) {
    header("Location: admin_dashboard.php");
    exit();
}

$type = $_GET['type'];
$id = $_GET['id'];

function canRemoveTeamHead($conn, $staff_id) {
    $sql = "SELECT COUNT(*) as count FROM complaints WHERE team_lead_id = '$staff_id' AND status = 'pending'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] == 0;
}

if ($type === 'student') {
    $sql = "DELETE FROM students WHERE id = '$id'";
    $redirect = 'admin_dashboard.php#manage-students';
    $success_msg = "Student deleted successfully!";
    $error_msg = "Error deleting student: ";
} elseif ($type === 'staff') {
    // Check if staff is a team head with pending complaints
    $check_sql = "SELECT is_team_lead FROM staff WHERE id = '$id'";
    $check_result = mysqli_query($conn, $check_sql);
    $staff = mysqli_fetch_assoc($check_result);
    
    if ($staff['is_team_lead'] && !canRemoveTeamHead($conn, $id)) {
        $_SESSION['error_message'] = "Cannot delete team head with pending complaints. Reassign complaints first.";
        header("Location: admin_dashboard.php#manage-staff");
        exit();
    }
    
    $sql = "DELETE FROM staff WHERE id = '$id'";
    $redirect = 'admin_dashboard.php#manage-staff';
    $success_msg = "Staff deleted successfully!";
    $error_msg = "Error deleting staff: ";
} else {
    die("Invalid user type.");
}

if (mysqli_query($conn, $sql)) {
    $_SESSION['success_message'] = $success_msg;
} else {
    $_SESSION['error_message'] = $error_msg . mysqli_error($conn);
}

header("Location: $redirect");
exit();
?>