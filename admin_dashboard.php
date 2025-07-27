<?php
session_start();
include 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

function canRemoveTeamHead($conn, $staff_id) {
    $sql = "SELECT COUNT(*) as count FROM complaints WHERE team_lead_id = '$staff_id' AND status = 'pending'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] == 0;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_student'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $roll_no = mysqli_real_escape_string($conn, $_POST['roll_no']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $father_name = mysqli_real_escape_string($conn, $_POST['father_name']);
    $registration_no = mysqli_real_escape_string($conn, $_POST['registration_no']);
    $year = mysqli_real_escape_string($conn, $_POST['year']);
    $branch = mysqli_real_escape_string($conn, $_POST['branch']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $sql = "INSERT INTO students (name, roll_no, email, father_name, registration_no, year, branch, password) VALUES ('$name', '$roll_no', '$email', '$father_name', '$registration_no', '$year', '$branch', '$password')";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success_message'] = "Student added successfully!";
    } else {
        $_SESSION['error_message'] = "Error adding student: " . mysqli_error($conn);
    }
    header("Location: admin_dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_staff'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $is_team_lead = isset($_POST['is_team_lead']) ? 1 : 0;

    $sql = "INSERT INTO staff (name, email, password, is_team_lead) VALUES ('$name', '$email', '$password', '$is_team_lead')";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success_message'] = "Staff added successfully!";
    } else {
        $_SESSION['error_message'] = "Error adding staff: " . mysqli_error($conn);
    }
    header("Location: admin_dashboard.php#manage-staff");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_team_head'])) {
    $complaint_id = mysqli_real_escape_string($conn, $_POST['complaint_id']);
    $team_head_id = mysqli_real_escape_string($conn, $_POST['team_head_id']);

    $check_sql = "SELECT team_lead_id FROM complaints WHERE id = '$complaint_id'";
    $check_result = mysqli_query($conn, $check_sql);
    $complaint = mysqli_fetch_assoc($check_result);

    if ($complaint['team_lead_id']) {
        $_SESSION['error_message'] = "A team head is already assigned. Remove the current team head first.";
    } else {
        $update_sql = "UPDATE complaints SET team_lead_id = '$team_head_id', status = 'pending', final_decision = 'pending' WHERE id = '$complaint_id'";
        if (mysqli_query($conn, $update_sql)) {
            $_SESSION['success_message'] = "Team head assigned successfully!";
        } else {
            $_SESSION['error_message'] = "Error assigning team head: " . mysqli_error($conn);
        }
    }
    header("Location: admin_dashboard.php#manage-complaints");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_team_head'])) {
    $complaint_id = mysqli_real_escape_string($conn, $_POST['complaint_id']);

    $update_sql = "UPDATE complaints SET team_lead_id = NULL WHERE id = '$complaint_id'";
    if (mysqli_query($conn, $update_sql)) {
        $_SESSION['success_message'] = "Team head removed successfully!";
    } else {
        $_SESSION['error_message'] = "Error removing team head: " . mysqli_error($conn);
    }
    header("Location: admin_dashboard.php#manage-complaints");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_student'])) {
    $student_id = mysqli_real_escape_string($conn, $_POST['student_id']);
    
    $delete_sql = "DELETE FROM students WHERE id = '$student_id'";
    if (mysqli_query($conn, $delete_sql)) {
        $_SESSION['success_message'] = "Student deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting student: " . mysqli_error($conn);
    }
    header("Location: admin_dashboard.php#manage-students");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_staff'])) {
    $staff_id = mysqli_real_escape_string($conn, $_POST['staff_id']);
    
    $check_sql = "SELECT is_team_lead FROM staff WHERE id = '$staff_id'";
    $check_result = mysqli_query($conn, $check_sql);
    $staff = mysqli_fetch_assoc($check_result);
    
    if ($staff['is_team_lead'] && !canRemoveTeamHead($conn, $staff_id)) {
        $_SESSION['error_message'] = "Cannot delete team head with pending complaints. Reassign complaints first.";
    } else {
        $delete_sql = "DELETE FROM staff WHERE id = '$staff_id'";
        if (mysqli_query($conn, $delete_sql)) {
            $_SESSION['success_message'] = "Staff deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Error deleting staff: " . mysqli_error($conn);
        }
    }
    header("Location: admin_dashboard.php#manage-staff");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_staff_role'])) {
    $staff_id = mysqli_real_escape_string($conn, $_POST['staff_id']);
    $is_team_lead = isset($_POST['is_team_lead']) ? 1 : 0;

    $current_role_sql = "SELECT is_team_lead FROM staff WHERE id = '$staff_id'";
    $current_role_result = mysqli_query($conn, $current_role_sql);
    $current_role = mysqli_fetch_assoc($current_role_result);

    if ($current_role['is_team_lead'] && !$is_team_lead && !canRemoveTeamHead($conn, $staff_id)) {
        $_SESSION['error_message'] = "Cannot remove team head status - pending complaints exist";
    } else {
        $update_sql = "UPDATE staff SET is_team_lead = '$is_team_lead' WHERE id = '$staff_id'";
        if (mysqli_query($conn, $update_sql)) {
            $_SESSION['success_message'] = "Staff role updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating staff role: " . mysqli_error($conn);
        }
    }
    header("Location: admin_dashboard.php#manage-staff");
    exit();
}

$students_sql = "SELECT * FROM students ORDER BY name ASC";
$students_result = mysqli_query($conn, $students_sql);

$staff_sql = "SELECT * FROM staff ORDER BY name ASC";
$staff_result = mysqli_query($conn, $staff_sql);

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$complaint_sql = "SELECT complaints.*, 
                 CASE 
                     WHEN complaints.submitted_by = 'student' THEN students.name
                     WHEN complaints.submitted_by = 'staff' THEN staff_submitter.name
                     ELSE 'Unknown'
                 END AS submitter_name,
                 staff_lead.name AS team_head_name,
                 complaints.submitted_by
                 FROM complaints 
                 LEFT JOIN students ON complaints.student_id = students.id AND complaints.submitted_by = 'student'
                 LEFT JOIN staff AS staff_submitter ON complaints.student_id = staff_submitter.id AND complaints.submitted_by = 'staff'
                 LEFT JOIN staff AS staff_lead ON complaints.team_lead_id = staff_lead.id";

if ($status_filter !== 'all') {
    if ($status_filter === 'challenged' || $status_filter === 'accepted') {
        $complaint_sql .= " WHERE complaints.final_decision = '$status_filter'";
    } else {
        $complaint_sql .= " WHERE complaints.status = '$status_filter'";
    }
}
$complaint_sql .= " ORDER BY complaints.created_at DESC";
$complaint_result = mysqli_query($conn, $complaint_sql);

$challenged_sql = "SELECT * FROM complaints WHERE final_decision = 'challenged'";
$challenged_result = mysqli_query($conn, $challenged_sql);

$teams_sql = "SELECT * FROM teams";
$teams_result = mysqli_query($conn, $teams_sql);

$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;

unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        header {
            background-color: #333;
            color: #fff;
            padding: 10px 20px;
            text-align: center;
        }
        nav {
            background-color: #444;
            padding: 10px;
            text-align: center;
        }
        nav a {
            color: #fff;
            text-decoration: none;
            margin: 0 15px;
            font-size: 18px;
            cursor: pointer;
        }
        nav a:hover {
            color: #ff6347;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .form-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        .btn-primary {
            background-color: #007bff;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .hidden {
            display: none;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .table th, .table td {
            padding: 12px;
            border: 1px solid #ccc;
            text-align: left;
        }
        .table th {
            background-color: #f4f4f4;
            font-weight: bold;
        }
        .table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .table tr:hover {
            background-color: #f1f1f1;
        }
        .status {
            font-weight: bold;
        }
        .status.pending {
            color: #ff6347;
        }
        .status.resolved {
            color: #32cd32;
        }
        .status.challenged {
            color: #ff8c00;
        }
        .status.accepted {
            color: #008000;
        }
        .password-container {
            position: relative;
            width: 100%;
        }
        .password-container input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .password-container .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
        }
        .team-head {
            cursor: pointer;
            color: blue;
            text-decoration: underline;
        }
        .team-members {
            display: none;
            margin-left: 20px;
        }
        .remove-team-head {
            color: red;
            cursor: pointer;
            margin-left: 10px;
        }
        .submitter-type {
            font-weight: bold;
            color: #555;
        }
        .submitter-type.student {
            color: #0066cc;
        }
        .submitter-type.staff {
            color: #cc6600;
        }
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .team-lead-badge {
            background-color: #ffc107;
            color: #000;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 12px;
            margin-left: 5px;
        }
        .complaint-details {
            display: none;
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            border-left: 4px solid #007bff;
        }
        .complaint-details p {
            margin: 5px 0;
        }
        .complaint-details strong {
            display: inline-block;
            width: 150px;
        }
        .complaint-id {
            color: #007bff;
            cursor: pointer;
            text-decoration: underline;
        }
        .checkbox-container {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .checkbox-container input {
            margin-right: 10px;
        }
        .disabled-action {
            color: #999;
            cursor: not-allowed;
        }
        .btn-action {
            color: #007bff;
            text-decoration: none;
            margin-right: 10px;
        }
        .btn-action.delete {
            color: #dc3545;
        }
        .role-toggle {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .role-toggle input[type="checkbox"] {
            width: auto;
        }
        .role-form {
            display: inline;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #2196F3;
        }
        input:focus + .slider {
            box-shadow: 0 0 1px #2196F3;
        }
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        .toggle-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .toggle-label {
            font-weight: bold;
        }
        .challenge-reason {
            margin-top: 10px;
            padding: 10px;
            background-color: #fff3cd;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <header>
        <h1>Admin Dashboard</h1>
    </header>
    <nav>
        <a href="#manage-students" onclick="showSection('manage-students')">Manage Students</a>
        <a href="#manage-staff" onclick="showSection('manage-staff')">Manage Staff</a>
        <a href="#manage-complaints" onclick="showSection('manage-complaints')">Manage Complaints</a>
        <a href="logout.php?role=admin">Log Out</a>
    </nav>
    <div class="container">
        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <section id="manage-students" class="hidden">
            <h2>Manage Students</h2>
            <button class="btn-primary" onclick="toggleForm('add-student-form')">Add Student</button>
                
            <div id="add-student-form" class="form-card hidden">
                <h3>Add Student</h3>
                <form action="admin_dashboard.php" method="POST">
                    <div class="form-group">
                        <label for="name">Name:</label>
                        <input type="text" name="name" placeholder="Enter student's name" required>
                    </div>
                    <div class="form-group">
                        <label for="roll_no">Roll No:</label>
                        <input type="text" name="roll_no" placeholder="Enter roll number" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" name="email" placeholder="Enter email" required>
                    </div>
                    <div class="form-group">
                        <label for="father_name">Father's Name:</label>
                        <input type="text" name="father_name" placeholder="Enter father's name" required>
                    </div>
                    <div class="form-group">
                        <label for="registration_no">Registration No:</label>
                        <input type="text" name="registration_no" placeholder="Enter registration number" required>
                    </div>
                    <div class="form-group">
                        <label for="year">Year:</label>
                        <input type="number" name="year" placeholder="Enter year" required>
                    </div>
                    <div class="form-group">
                        <label for="branch">Branch:</label>
                        <input type="text" name="branch" placeholder="Enter branch" required>
                    </div>
                    <div class="form-group password-container">
                        <label for="password">Password:</label>
                        <input type="password" name="password" id="password" placeholder="Enter password" required>
                        <span class="toggle-password" onclick="togglePasswordVisibility('password')">👁</span>
                    </div>
                    <button type="submit" name="add_student" class="btn-primary">Add Student</button>
                </form>
            </div>
                
            <h3>Student List</h3>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Roll No</th>
                            <th>Email</th>
                            <th>Father's Name</th>
                            <th>Registration No</th>
                            <th>Year</th>
                            <th>Branch</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($students_result) > 0): ?>
                            <?php while ($student = mysqli_fetch_assoc($students_result)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['roll_no']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td><?php echo htmlspecialchars($student['father_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['registration_no']); ?></td>
                                    <td><?php echo htmlspecialchars($student['year']); ?></td>
                                    <td><?php echo htmlspecialchars($student['branch']); ?></td>
                                    <td>
                                        <a href="edit_user.php?type=student&id=<?php echo $student['id']; ?>" class="btn-action">Edit</a>
                                        <a href="#" onclick="confirmDeleteStudent(<?php echo $student['id']; ?>)" class="btn-action delete">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">No students found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        
        <section id="manage-staff" class="hidden">
            <h2>Manage Staff</h2>
            <button class="btn-primary" onclick="toggleForm('add-staff-form')">Add Staff</button>

            <div id="add-staff-form" class="form-card hidden">
                <h3>Add Staff</h3>
                <form action="admin_dashboard.php" method="POST">
                    <div class="form-group">
                        <label for="name">Name:</label>
                        <input type="text" name="name" placeholder="Enter staff's name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" name="email" placeholder="Enter email" required>
                    </div>
                    <div class="form-group password-container">
                        <label for="password">Password:</label>
                        <input type="password" name="password" id="staff_password" placeholder="Enter password" required>
                        <span class="toggle-password" onclick="togglePasswordVisibility('staff_password')">👁</span>
                    </div>
                    <div class="form-group">
                        <label for="is_team_lead">Team Head:</label>
                        <select name="is_team_lead" id="is_team_lead" required>
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>
                    <button type="submit" name="add_staff" class="btn-primary">Add Staff</button>
                </form>
            </div>

            <h3>Staff List</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    mysqli_data_seek($staff_result, 0);
                    while ($staff = mysqli_fetch_assoc($staff_result)): ?>
                        <tr>
                            <td><?php echo $staff['name']; ?></td>
                            <td><?php echo $staff['email']; ?></td>
                            <td>
                                <form class="role-form" action="admin_dashboard.php" method="POST">
                                    <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                    <input type="hidden" name="update_staff_role" value="1">
                                    <div class="toggle-container">
                                        <label class="switch">
                                            <input type="checkbox" name="is_team_lead" 
                                                <?php echo $staff['is_team_lead'] ? 'checked' : ''; ?>
                                                <?php echo ($staff['is_team_lead'] && !canRemoveTeamHead($conn, $staff['id'])) ? 'disabled' : ''; ?>
                                                onchange="this.form.submit()">
                                            <span class="slider"></span>
                                        </label>
                                        <span class="toggle-label"><?php echo $staff['is_team_lead'] ? 'Team Head' : 'Staff Member'; ?></span>
                                    </div>
                                </form>
                                <?php if ($staff['is_team_lead'] && !canRemoveTeamHead($conn, $staff['id'])): ?>
                                    <small>(Cannot remove - pending complaints)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="edit_user.php?type=staff&id=<?php echo $staff['id']; ?>" class="btn-action">Edit</a>
                                <?php if (!$staff['is_team_lead'] || canRemoveTeamHead($conn, $staff['id'])): ?>
                                    <a href="#" onclick="confirmDeleteStaff(<?php echo $staff['id']; ?>)" class="btn-action delete">Delete</a>
                                <?php else: ?>
                                    <span class="disabled-action" title="Cannot delete - assigned to pending complaints">Delete</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </section>

        <section id="manage-complaints" class="hidden">
            <h2>Manage Complaints</h2>
            <div>
                <label for="status_filter">Filter by Status:</label>
                <select id="status_filter" onchange="filterComplaints()">
                    <option value="all" <?php echo ($status_filter === 'all') ? 'selected' : ''; ?>>All</option>
                    <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="resolved" <?php echo ($status_filter === 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                    <option value="challenged" <?php echo ($status_filter === 'challenged') ? 'selected' : ''; ?>>Challenged</option>
                    <option value="accepted" <?php echo ($status_filter === 'accepted') ? 'selected' : ''; ?>>Accepted</option>
                </select>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Complaint ID</th>
                        <th>Submitted By</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Team Head</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($complaint_result) > 0): ?>
                        <?php while ($complaint = mysqli_fetch_assoc($complaint_result)): ?>
                            <tr>
                                <td>
                                    <span class="complaint-id" onclick="toggleComplaintDetails(<?php echo $complaint['id']; ?>)">
                                        <?php echo $complaint['id']; ?>
                                    </span>
                                </td>
                                <td><?php echo !empty($complaint['submitter_name']) ? $complaint['submitter_name'] : 'Unknown'; ?></td>
                                <td>
                                    <?php if (!empty($complaint['submitted_by'])): ?>
                                        <span class="submitter-type <?php echo $complaint['submitted_by']; ?>">
                                            <?php echo ucfirst($complaint['submitted_by']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span>Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo substr($complaint['description'], 0, 50); ?>...</td>
                                <td><?php echo $complaint['category']; ?></td>
                                <td>
                                    <?php if ($complaint['final_decision'] === 'challenged'): ?>
                                        <span class="status challenged">Challenged</span>
                                    <?php elseif ($complaint['final_decision'] === 'accepted'): ?>
                                        <span class="status accepted">Accepted</span>
                                    <?php else: ?>
                                        <span class="status <?php echo $complaint['status']; ?>"><?php echo ucfirst($complaint['status']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $complaint['created_at']; ?></td>
                                <td>
                                    <?php if ($complaint['team_lead_id']): ?>
                                        <div>
                                            <?php echo $complaint['team_head_name']; ?>
                                            <span class="remove-team-head" onclick="removeTeamHead(<?php echo $complaint['id']; ?>)">❌</span>
                                        </div>
                                    <?php else: ?>
                                        <form action="admin_dashboard.php" method="POST">
                                            <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                                            <select name="team_head_id" class="staff-select" required>
                                                <option value="">Select Team Head</option>
                                                <?php
                                                $staff_sql = "SELECT * FROM staff WHERE is_team_lead = 1";
                                                $staff_result = mysqli_query($conn, $staff_sql);
                                                while ($staff = mysqli_fetch_assoc($staff_result)): ?>
                                                    <option value="<?php echo $staff['id']; ?>"><?php echo $staff['name']; ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                            <button type="submit" name="assign_team_head" class="btn-primary">Assign</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($complaint['final_decision'] === 'challenged'): ?>
                                        <p>Decision Challenged</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr id="complaint-details-<?php echo $complaint['id']; ?>" class="complaint-details">
                                <td colspan="9">
                                    <p><strong>Complaint ID:</strong> <?php echo $complaint['id']; ?></p>
                                    <p><strong>Submitted By:</strong> <?php echo !empty($complaint['submitter_name']) ? $complaint['submitter_name'] : 'Unknown'; ?></p>
                                    <p><strong>Type:</strong> 
                                        <?php if (!empty($complaint['submitted_by'])): ?>
                                            <span class="submitter-type <?php echo $complaint['submitted_by']; ?>">
                                                <?php echo ucfirst($complaint['submitted_by']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span>Unknown</span>
                                        <?php endif; ?>
                                    </p>
                                    <p><strong>Description:</strong> <?php echo $complaint['description']; ?></p>
                                    <p><strong>Category:</strong> <?php echo $complaint['category']; ?></p>
                                    <p><strong>Status:</strong> 
                                        <?php if ($complaint['final_decision'] === 'challenged'): ?>
                                            <span class="status challenged">Challenged</span>
                                        <?php elseif ($complaint['final_decision'] === 'accepted'): ?>
                                            <span class="status accepted">Accepted</span>
                                        <?php else: ?>
                                            <span class="status <?php echo $complaint['status']; ?>"><?php echo ucfirst($complaint['status']); ?></span>
                                        <?php endif; ?>
                                    </p>
                                    <p><strong>Created At:</strong> <?php echo $complaint['created_at']; ?></p>
                                    <?php if ($complaint['final_decision'] === 'challenged' && !empty($complaint['challenge_reason'])): ?>
                                        <div class="challenge-reason">
                                            <p><strong>Challenge Reason:</strong> <?php echo $complaint['challenge_reason']; ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <p><strong>Attachments:</strong>
                                        <?php
                                        $complaint_id = $complaint['id'];
                                        $attachment_sql = "SELECT * FROM complaint_attachments WHERE complaint_id = '$complaint_id' AND uploaded_by = 'student'";
                                        $attachment_result = mysqli_query($conn, $attachment_sql);
                                        if (mysqli_num_rows($attachment_result) > 0): ?>
                                            <ul>
                                                <?php while ($attachment = mysqli_fetch_assoc($attachment_result)): ?>
                                                    <li>
                                                        <a href="<?php echo $attachment['file_path']; ?>" target="_blank"><?php echo basename($attachment['file_path']); ?></a>
                                                    </li>
                                                <?php endwhile; ?>
                                            </ul>
                                        <?php else: ?>
                                            No attachments found.
                                        <?php endif; ?>
                                    </p>
                                    <p><strong>Proceeding Attachments:</strong>
                                        <?php
                                        $proceeding_attachment_sql = "SELECT ca.*, st.name AS staff_name 
                                                                      FROM complaint_attachments ca 
                                                                      JOIN staff st ON ca.uploaded_by_staff_id = st.id 
                                                                      WHERE ca.complaint_id = '$complaint_id' AND ca.uploaded_by = 'staff'";
                                        $proceeding_attachment_result = mysqli_query($conn, $proceeding_attachment_sql);
                                        if (mysqli_num_rows($proceeding_attachment_result) > 0): ?>
                                            <ul>
                                                <?php while ($proceeding_attachment = mysqli_fetch_assoc($proceeding_attachment_result)): ?>
                                                    <li>
                                                        <a href="<?php echo $proceeding_attachment['file_path']; ?>" target="_blank"><?php echo basename($proceeding_attachment['file_path']); ?></a>
                                                        (Uploaded by: <?php echo $proceeding_attachment['staff_name']; ?>)
                                                    </li>
                                                <?php endwhile; ?>
                                            </ul>
                                        <?php else: ?>
                                            No proceeding attachments found.
                                        <?php endif; ?>
                                    </p>
                                    <p><strong>Team Head:</strong>
                                        <?php if ($complaint['team_lead_id']): ?>
                                            <?php echo $complaint['team_head_name']; ?>
                                        <?php else: ?>
                                            Not assigned
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($complaint['status'] === 'resolved' && !empty($complaint['resolution_notes'])): ?>
                                        <p><strong>Resolution Notes:</strong> <?php echo $complaint['resolution_notes']; ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">No complaints found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        function showSection(sectionId) {
            document.querySelectorAll('section').forEach(section => {
                section.classList.add('hidden');
            });
            document.getElementById(sectionId).classList.remove('hidden');
        }

        function toggleForm(formId) {
            const form = document.getElementById(formId);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function filterComplaints() {
            const statusFilter = document.getElementById('status_filter').value;
            window.location.href = `admin_dashboard.php?status=${statusFilter}#manage-complaints`;
        }

        function removeTeamHead(complaintId) {
            if (confirm("Are you sure you want to remove the team head?")) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin_dashboard.php';

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'complaint_id';
                input.value = complaintId;

                const input2 = document.createElement('input');
                input2.type = 'hidden';
                input2.name = 'remove_team_head';
                input2.value = '1';

                form.appendChild(input);
                form.appendChild(input2);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function togglePasswordVisibility(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
            } else {
                passwordInput.type = 'password';
            }
        }

        function toggleComplaintDetails(complaintId) {
            const detailsRow = document.getElementById(`complaint-details-${complaintId}`);
            if (detailsRow.style.display === 'none' || !detailsRow.style.display) {
                detailsRow.style.display = 'table-row';
            } else {
                detailsRow.style.display = 'none';
            }
        }

        function confirmDeleteStudent(studentId) {
            if (confirm("Are you sure you want to delete this student?")) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin_dashboard.php';

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'student_id';
                input.value = studentId;

                const input2 = document.createElement('input');
                input2.type = 'hidden';
                input2.name = 'delete_student';
                input2.value = '1';

                form.appendChild(input);
                form.appendChild(input2);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function confirmDeleteStaff(staffId) {
            if (confirm("Are you sure you want to delete this staff member?")) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin_dashboard.php';

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'staff_id';
                input.value = staffId;

                const input2 = document.createElement('input');
                input2.type = 'hidden';
                input2.name = 'delete_staff';
                input2.value = '1';

                form.appendChild(input);
                form.appendChild(input2);
                document.body.appendChild(form);
                form.submit();
            }
        }

        $(document).ready(function() {
            $('.staff-select').select2({
                placeholder: "Select a staff member",
                allowClear: true
            });

            if (window.location.hash === '#manage-complaints') {
                showSection('manage-complaints');
            } else if (window.location.hash === '#manage-staff') {
                showSection('manage-staff');
            } else {
                showSection('manage-students');
            }
        });
    </script>
</body>
</html>