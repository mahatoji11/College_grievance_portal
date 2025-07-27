<?php
session_start();
include 'config.php';

if (!isset($_SESSION['staff_id'])) {
    header("Location: staff_login.php");
    exit();
}

$staff_id = $_SESSION['staff_id'];
$sql = "SELECT * FROM staff WHERE id = '$staff_id'";
$result = mysqli_query($conn, $sql);
$staff = mysqli_fetch_assoc($result);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (password_verify($old_password, $staff['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE staff SET password = '$hashed_password' WHERE id = '$staff_id'";
            if (mysqli_query($conn, $update_sql)) {
                $_SESSION['success_message'] = "Password changed successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating password: " . mysqli_error($conn);
            }
        } else {
            $_SESSION['error_message'] = "New passwords do not match!";
        }
    } else {
        $_SESSION['error_message'] = "Incorrect old password!";
    }
    header("Location: staff_dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_proceeding_document'])) {
    $complaint_id = $_POST['complaint_id'];
    $files = $_FILES['proceeding_documents'];

    $check_sql = "SELECT 1 FROM complaints c 
                 LEFT JOIN team_members tm ON c.team_id = tm.team_id
                 WHERE c.id = '$complaint_id' 
                 AND (c.team_lead_id = '$staff_id' OR tm.staff_id = '$staff_id')";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        for ($i = 0; $i < count($files['name']); $i++) {
            $file_name = $files['name'][$i];
            $file_tmp = $files['tmp_name'][$i];
            $file_path = "assets/uploads/" . basename($file_name);

            if (move_uploaded_file($file_tmp, $file_path)) {
                $insert_sql = "INSERT INTO complaint_attachments 
                              (complaint_id, file_path, uploaded_by, uploaded_by_staff_id) 
                              VALUES ('$complaint_id', '$file_path', 'staff', '$staff_id')";
                mysqli_query($conn, $insert_sql);
            }
        }
        $_SESSION['success_message'] = "Documents added successfully!";
    } else {
        $_SESSION['error_message'] = "You are not authorized to add documents to this complaint!";
    }
    header("Location: staff_dashboard.php#manage-complaints");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_proceeding_document'])) {
    $attachment_id = $_POST['attachment_id'];
    $complaint_id = $_POST['complaint_id'];
    
    $check_sql = "SELECT 1 FROM complaint_attachments ca
                 JOIN complaints c ON ca.complaint_id = c.id
                 LEFT JOIN team_members tm ON c.team_id = tm.team_id
                 WHERE ca.id = '$attachment_id' 
                 AND ca.complaint_id = '$complaint_id'
                 AND ca.uploaded_by_staff_id = '$staff_id'
                 AND (c.team_lead_id = '$staff_id' OR tm.staff_id = '$staff_id')";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $sql = "SELECT file_path FROM complaint_attachments WHERE id = '$attachment_id'";
        $result = mysqli_query($conn, $sql);
        $attachment = mysqli_fetch_assoc($result);
        
        if ($attachment) {
            if (file_exists($attachment['file_path'])) {
                unlink($attachment['file_path']);
            }
            
            $delete_sql = "DELETE FROM complaint_attachments WHERE id = '$attachment_id'";
            if (mysqli_query($conn, $delete_sql)) {
                $_SESSION['success_message'] = "Document removed successfully!";
            } else {
                $_SESSION['error_message'] = "Error removing document: " . mysqli_error($conn);
            }
        }
    } else {
        $_SESSION['error_message'] = "You can only remove documents you uploaded!";
    }
    header("Location: staff_dashboard.php#manage-complaints");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_team_member'])) {
    if ($staff['is_team_lead']) {
        $team_member_id = $_POST['team_member_id'];
        $complaint_id = $_POST['complaint_id'];
        
        // Check if complaint has a team, if not create one
        $team_sql = "SELECT team_id FROM complaints WHERE id = '$complaint_id'";
        $team_result = mysqli_query($conn, $team_sql);
        $complaint = mysqli_fetch_assoc($team_result);
        
        if (empty($complaint['team_id'])) {
            // Create new team with current staff as lead
            $team_name = "Team for Complaint #$complaint_id";
            $insert_team_sql = "INSERT INTO teams (team_name, team_lead_id) VALUES ('$team_name', '$staff_id')";
            if (mysqli_query($conn, $insert_team_sql)) {
                $team_id = mysqli_insert_id($conn);
                // Update complaint with new team
                $update_complaint_sql = "UPDATE complaints SET team_id = '$team_id', team_lead_id = '$staff_id' WHERE id = '$complaint_id'";
                mysqli_query($conn, $update_complaint_sql);
            } else {
                $_SESSION['error_message'] = "Error creating team: " . mysqli_error($conn);
                header("Location: staff_dashboard.php#manage-complaints");
                exit();
            }
        } else {
            $team_id = $complaint['team_id'];
        }
        
        // Now add team member
        $check_sql = "SELECT * FROM team_members WHERE team_id = '$team_id' AND staff_id = '$team_member_id'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) == 0) {
            $insert_sql = "INSERT INTO team_members (team_id, staff_id, added_by) 
                           VALUES ('$team_id', '$team_member_id', '$staff_id')";
            if (mysqli_query($conn, $insert_sql)) {
                $_SESSION['success_message'] = "Team member added successfully!";
            } else {
                $_SESSION['error_message'] = "Error adding team member: " . mysqli_error($conn);
            }
        } else {
            $_SESSION['error_message'] = "This staff member is already part of the team.";
        }
    } else {
        $_SESSION['error_message'] = "Only team heads can add team members.";
    }
    header("Location: staff_dashboard.php#manage-complaints");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_team_member'])) {
    if ($staff['is_team_lead']) {
        $team_member_id = $_POST['team_member_id'];
        $complaint_id = $_POST['complaint_id'];
        
        $team_sql = "SELECT team_id FROM complaints WHERE id = '$complaint_id'";
        $team_result = mysqli_query($conn, $team_sql);
        $complaint = mysqli_fetch_assoc($team_result);
        $team_id = $complaint['team_id'];
        
        $delete_sql = "DELETE FROM team_members WHERE team_id = '$team_id' AND staff_id = '$team_member_id'";
        if (mysqli_query($conn, $delete_sql)) {
            $_SESSION['success_message'] = "Team member removed successfully!";
        } else {
            $_SESSION['error_message'] = "Error removing team member: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error_message'] = "Only team heads can remove team members.";
    }
    header("Location: staff_dashboard.php#manage-complaints");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['resolve_complaint'])) {
    if ($staff['is_team_lead']) {
        $complaint_id = $_POST['complaint_id'];
        $resolution_notes = mysqli_real_escape_string($conn, $_POST['resolution_notes']);
        
        $update_sql = "UPDATE complaints SET status = 'resolved', 
                      resolution_notes = '$resolution_notes', 
                      resolved_at = NOW() 
                      WHERE id = '$complaint_id' AND team_lead_id = '$staff_id'";
        
        if (mysqli_query($conn, $update_sql)) {
            $_SESSION['success_message'] = "Complaint marked as resolved!";
        } else {
            $_SESSION['error_message'] = "Error resolving complaint: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error_message'] = "Only team heads can resolve complaints.";
    }
    header("Location: staff_dashboard.php#manage-complaints");
    exit();
}

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$complaint_sql = "SELECT c.*, 
                 CASE 
                     WHEN c.submitted_by = 'student' THEN s.name
                     WHEN c.submitted_by = 'staff' THEN st.name
                     ELSE 'Unknown'
                 END AS submitter_name,
                 c.submitted_by,
                 tl.name AS team_head_name,
                 t.team_name,
                 (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = c.team_id) AS team_member_count
                 FROM complaints c
                 LEFT JOIN students s ON c.student_id = s.id AND c.submitted_by = 'student'
                 LEFT JOIN staff st ON c.student_id = st.id AND c.submitted_by = 'staff'
                 LEFT JOIN staff tl ON c.team_lead_id = tl.id
                 LEFT JOIN teams t ON c.team_id = t.id
                 WHERE (c.team_lead_id = '$staff_id' 
                        OR EXISTS (
                            SELECT 1 FROM team_members tm 
                            WHERE tm.team_id = c.team_id 
                            AND tm.staff_id = '$staff_id'
                        )
                        OR (c.submitted_by = 'staff' AND c.student_id = '$staff_id'))";

if ($status_filter !== 'all') {
    if ($status_filter === 'challenged' || $status_filter === 'accepted') {
        $complaint_sql .= " AND c.final_decision = '$status_filter'";
    } else {
        $complaint_sql .= " AND c.status = '$status_filter'";
    }
}
$complaint_sql .= " ORDER BY c.created_at DESC";
$complaint_result = mysqli_query($conn, $complaint_sql);

$all_staff_sql = "SELECT * FROM staff WHERE id != '$staff_id' ORDER BY name ASC";
$all_staff_result = mysqli_query($conn, $all_staff_sql);

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
    <title>Staff Dashboard</title>
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
        .section {
            margin-bottom: 30px;
            display: none;
        }
        .section.active {
            display: block;
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
        .team-lead-badge {
            background-color: #ffc107;
            color: #000;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 12px;
            margin-left: 5px;
        }
        .uploader-info {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        .remove-btn {
            color: red;
            cursor: pointer;
            margin-left: 10px;
        }
        .submitter-type {
            font-weight: bold;
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
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        .btn-primary {
            background-color: #333;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-primary:hover {
            background-color: #555;
        }
        .resolution-form {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .resolution-form textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .team-member-actions {
            margin-top: 10px;
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
        <h1>Staff Dashboard <?php echo $staff['is_team_lead'] ? '<span class="team-lead-badge">Team Head</span>' : ''; ?></h1>
    </header>
    <nav>
        <a href="#profile" onclick="showSection('profile')">Profile</a>
        <a href="#submit-complaint" onclick="showSection('submit-complaint')">Submit Complaint</a>
        <a href="#track-complaints" onclick="showSection('track-complaints')">Track Complaints</a>
        <a href="#manage-complaints" onclick="showSection('manage-complaints')">Manage Complaints</a>
        <a href="logout.php?role=staff">Log Out</a>
    </nav>
    <div class="container">
        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <section id="profile" class="section active">
            <h2>Profile</h2>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($staff['name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($staff['email']); ?></p>
            <p><strong>Role:</strong> <?php echo $staff['is_team_lead'] ? 'Team Head' : 'Staff Member'; ?></p>
            <button onclick="togglePasswordForm()">Change Password</button>

            <div id="change-password-form" style="display: none;">
                <h3>Change Password</h3>
                <form action="staff_dashboard.php" method="POST" onsubmit="return validatePassword()">
                    <label for="old_password">Old Password:</label>
                    <div class="password-container">
                        <input type="password" id="old_password" name="old_password" required>
                        <span class="toggle-password" onclick="togglePasswordVisibility('old_password')">👁</span>
                    </div>

                    <label for="new_password">New Password:</label>
                    <div class="password-container">
                        <input type="password" id="new_password" name="new_password" required>
                    </div>

                    <label for="confirm_password">Confirm New Password:</label>
                    <div class="password-container">
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" name="change_password">Change Password</button>
                </form>
            </div>
        </section>

        <section id="submit-complaint" class="section">
            <h2>Submit a Complaint</h2>
            <div class="form-card">
                <form action="php/submit_complaint.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="submitted_by" value="staff">
                    <input type="hidden" name="student_id" value="<?php echo $staff_id; ?>">
                    
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" rows="5" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="category">Category:</label>
                        <select id="category" name="category" required>
                            <option value="academic">Academic</option>
                            <option value="hostel">Hostel</option>
                            <option value="finance">Finance</option>
                            <option value="infrastructure">Infrastructure</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="files">Upload Files (Optional):</label>
                        <input type="file" id="files" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.mp4">
                    </div>

                    <button type="submit" class="btn-primary">Submit Complaint</button>
                </form>
            </div>
        </section>

        <section id="track-complaints" class="section">
            <h2>Your Complaints</h2>
            <div>
                <label for="status_filter">Filter by Status:</label>
                <select id="status_filter" onchange="filterComplaints()">
                    <option value="all">All</option>
                    <option value="pending">Pending</option>
                    <option value="resolved">Resolved</option>
                    <option value="challenged">Challenged</option>
                    <option value="accepted">Accepted</option>
                </select>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Submitted On</th>
                        <th>Attachments</th>
                        <th>Proceeding Attachments</th>
                        <th>Add Documents</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    mysqli_data_seek($complaint_result, 0);
                    while ($complaint = mysqli_fetch_assoc($complaint_result)): 
                        if ($complaint['submitted_by'] == 'staff' && $complaint['student_id'] == $staff_id): ?>
                            <?php
                            $complaint_id = $complaint['id'];

                            $attachment_sql = "SELECT ca.*, 
                                            CASE 
                                                WHEN ca.uploaded_by = 'student' THEN s.name
                                                WHEN ca.uploaded_by = 'staff' THEN st.name
                                                ELSE 'Unknown'
                                            END AS uploader_name
                                            FROM complaint_attachments ca
                                            LEFT JOIN students s ON ca.uploaded_by_student_id = s.id
                                            LEFT JOIN staff st ON ca.uploaded_by_staff_id = st.id
                                            WHERE ca.complaint_id = '$complaint_id'";
                            $attachment_result = mysqli_query($conn, $attachment_sql);

                            $proceeding_sql = "SELECT ca.*, st.name AS staff_name
                                            FROM complaint_attachments ca
                                            JOIN staff st ON ca.uploaded_by_staff_id = st.id
                                            WHERE ca.complaint_id = '$complaint_id' 
                                            AND ca.uploaded_by = 'staff'";
                            $proceeding_result = mysqli_query($conn, $proceeding_sql);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($complaint['description']); ?></td>
                                <td><?php echo htmlspecialchars($complaint['category']); ?></td>
                                <td>
                                    <?php if ($complaint['final_decision'] === 'challenged'): ?>
                                        <span class="status challenged">Challenged</span>
                                    <?php elseif ($complaint['final_decision'] === 'accepted'): ?>
                                        <span class="status accepted">Accepted</span>
                                    <?php else: ?>
                                        <span class="status <?php echo $complaint['status']; ?>"><?php echo ucfirst($complaint['status']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($complaint['created_at']); ?></td>
                                <td>
                                    <?php if (mysqli_num_rows($attachment_result) > 0): ?>
                                        <ul style="padding-left: 20px; margin: 0;">
                                            <?php while ($attachment = mysqli_fetch_assoc($attachment_result)): ?>
                                                <li style="margin-bottom: 5px;">
                                                    <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" target="_blank">
                                                        <?php echo htmlspecialchars(basename($attachment['file_path'])); ?>
                                                    </a>
                                                    <div class="uploader-info">
                                                        Uploaded by: <?php echo htmlspecialchars($attachment['uploader_name']); ?>
                                                    </div>
                                                </li>
                                            <?php endwhile; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p>No attachments found.</p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (mysqli_num_rows($proceeding_result) > 0): ?>
                                        <ul style="padding-left: 20px; margin: 0;">
                                            <?php while ($doc = mysqli_fetch_assoc($proceeding_result)): ?>
                                                <li style="margin-bottom: 5px;">
                                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank">
                                                        <?php echo htmlspecialchars(basename($doc['file_path'])); ?>
                                                    </a>
                                                    <div class="uploader-info">
                                                        Uploaded by: <?php echo htmlspecialchars($doc['staff_name']); ?>
                                                    </div>
                                                </li>
                                            <?php endwhile; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p>No proceeding docs</p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form action="staff_dashboard.php" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="complaint_id" value="<?php echo $complaint_id; ?>">
                                        <input type="file" name="proceeding_documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.mp4">
                                        <button type="submit" name="add_proceeding_document" class="btn-primary">Upload</button>
                                    </form>
                                </td>
                                <td>
                                    <?php if ($complaint['status'] === 'resolved' && $complaint['final_decision'] === 'pending'): ?>
                                        <form action="handle_decision.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                                            <button type="submit" name="decision" value="challenge" class="btn-primary" style="padding: 5px 10px; margin-right: 5px;">Challenge</button>
                                            <button type="submit" name="decision" value="accept" class="btn-primary" style="padding: 5px 10px;">Accept</button>
                                        </form>
                                    <?php elseif ($complaint['final_decision'] === 'challenged'): ?>
                                        <span style="color: orange;">Decision Challenged</span>
                                        <?php if (!empty($complaint['challenge_reason'])): ?>
                                            <div class="challenge-reason">
                                                <strong>Reason:</strong> <?php echo htmlspecialchars($complaint['challenge_reason']); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($complaint['final_decision'] === 'accepted'): ?>
                                        <span style="color: green;">Decision Accepted</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </section>

        <section id="manage-complaints" class="section">
            <h2>Manage Complaints</h2>
            <div>
                <label for="manage_status_filter">Filter by Status:</label>
                <select id="manage_status_filter" onchange="filterManageComplaints()">
                    <option value="all">All</option>
                    <option value="pending">Pending</option>
                    <option value="resolved">Resolved</option>
                    <option value="challenged">Challenged</option>
                    <option value="accepted">Accepted</option>
                </select>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Submitted By</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Team</th>
                        <th>Original Attachments</th>
                        <th>Proceeding Docs</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    mysqli_data_seek($complaint_result, 0);
                    while ($complaint = mysqli_fetch_assoc($complaint_result)): 
                        if ($complaint['team_lead_id'] == $staff_id || 
                            ($complaint['team_member_count'] > 0)): ?>
                            <?php
                            $complaint_id = $complaint['id'];
                            $is_team_head = ($complaint['team_lead_id'] == $staff_id);
                            
                            $team_sql = "SELECT t.team_name, s.id, s.name, s.id = c.team_lead_id AS is_team_head
                                        FROM complaints c
                                        JOIN teams t ON c.team_id = t.id
                                        LEFT JOIN team_members tm ON t.id = tm.team_id
                                        LEFT JOIN staff s ON (tm.staff_id = s.id OR s.id = c.team_lead_id)
                                        WHERE c.id = '$complaint_id'
                                        AND (s.id IS NOT NULL)";
                            $team_result = mysqli_query($conn, $team_sql);
                            
                            $original_attachments_sql = "SELECT ca.*, 
                                                        CASE 
                                                            WHEN ca.uploaded_by = 'student' THEN s.name
                                                            WHEN ca.uploaded_by = 'staff' THEN st.name
                                                            ELSE 'Unknown'
                                                        END AS uploader_name
                                                        FROM complaint_attachments ca
                                                        LEFT JOIN students s ON ca.uploaded_by_student_id = s.id
                                                        LEFT JOIN staff st ON ca.uploaded_by_staff_id = st.id
                                                        WHERE ca.complaint_id = '$complaint_id' 
                                                        AND ca.uploaded_by = '{$complaint['submitted_by']}'";
                            $original_attachments_result = mysqli_query($conn, $original_attachments_sql);
                            
                            $proceeding_docs_sql = "SELECT ca.*, s.name AS staff_name
                                                  FROM complaint_attachments ca
                                                  JOIN staff s ON ca.uploaded_by_staff_id = s.id
                                                  WHERE ca.complaint_id = '$complaint_id' 
                                                  AND ca.uploaded_by = 'staff'
                                                  AND ca.uploaded_by_staff_id != '{$complaint['student_id']}'";
                            $proceeding_docs_result = mysqli_query($conn, $proceeding_docs_sql);
                            ?>
                            <tr>
                                <td><?php echo $complaint['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($complaint['submitter_name']); ?>
                                    <span class="submitter-type <?php echo $complaint['submitted_by']; ?>">
                                        (<?php echo ucfirst($complaint['submitted_by']); ?>)
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars(substr($complaint['description'], 0, 50)); ?>...</td>
                                <td><?php echo htmlspecialchars($complaint['category']); ?></td>
                                <td>
                                    <?php if ($complaint['final_decision'] === 'challenged'): ?>
                                        <span class="status challenged">Challenged</span>
                                    <?php elseif ($complaint['final_decision'] === 'accepted'): ?>
                                        <span class="status accepted">Accepted</span>
                                    <?php else: ?>
                                        <span class="status <?php echo $complaint['status']; ?>"><?php echo ucfirst($complaint['status']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
    <?php 
    $team_sql = "SELECT t.team_name, s.id, s.name, s.id = c.team_lead_id AS is_team_head
                FROM complaints c
                LEFT JOIN teams t ON c.team_id = t.id
                LEFT JOIN team_members tm ON t.id = tm.team_id
                LEFT JOIN staff s ON (tm.staff_id = s.id OR s.id = c.team_lead_id)
                WHERE c.id = '$complaint_id'
                AND s.id IS NOT NULL";
    $team_result = mysqli_query($conn, $team_sql);
    
    if (mysqli_num_rows($team_result) > 0): ?>
        <?php 
        $first_row = true;
        while ($member = mysqli_fetch_assoc($team_result)): 
            if ($first_row): ?>
                <strong>Team: <?php echo htmlspecialchars($member['team_name']); ?></strong>
                <ul style="padding-left: 20px; margin: 5px 0;">
                <?php $first_row = false;
            endif; ?>
            <li>
                <?php echo htmlspecialchars($member['name']); ?>
                <?php if ($member['is_team_head']): ?>
                    <span class="team-lead-badge">Head</span>
                <?php endif; ?>
            </li>
        <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <p>No team assigned</p>
    <?php endif; ?>
</td>
                                <td>
                                    <?php if (mysqli_num_rows($original_attachments_result) > 0): ?>
                                        <ul style="padding-left: 20px; margin: 0;">
                                            <?php while ($attachment = mysqli_fetch_assoc($original_attachments_result)): ?>
                                                <li style="margin-bottom: 5px;">
                                                    <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" target="_blank">
                                                        <?php echo htmlspecialchars(basename($attachment['file_path'])); ?>
                                                    </a>
                                                    <div class="uploader-info">
                                                        <?php echo htmlspecialchars($attachment['uploader_name']); ?>
                                                    </div>
                                                </li>
                                            <?php endwhile; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p>No attachments</p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form action="staff_dashboard.php" method="POST" enctype="multipart/form-data" style="margin-bottom: 10px;">
                                        <input type="hidden" name="complaint_id" value="<?php echo $complaint_id; ?>">
                                        <input type="file" name="proceeding_documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.mp4" style="margin-bottom: 5px;">
                                        <button type="submit" name="add_proceeding_document" class="btn-primary">Upload</button>
                                    </form>
                                    
                                    <?php if (mysqli_num_rows($proceeding_docs_result) > 0): ?>
                                        <ul style="padding-left: 20px; margin: 0;">
                                            <?php while ($doc = mysqli_fetch_assoc($proceeding_docs_result)): ?>
                                                <li style="margin-bottom: 5px;">
                                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank">
                                                        <?php echo htmlspecialchars(basename($doc['file_path'])); ?>
                                                    </a>
                                                    <div class="uploader-info">
                                                        Uploaded by: <?php echo htmlspecialchars($doc['staff_name']); ?>
                                                    </div>
                                                    <?php if ($doc['staff_name'] == $staff['name']): ?>
                                                        <form action="staff_dashboard.php" method="POST" style="display: inline;">
                                                            <input type="hidden" name="complaint_id" value="<?php echo $complaint_id; ?>">
                                                            <input type="hidden" name="attachment_id" value="<?php echo $doc['id']; ?>">
                                                            <button type="submit" name="remove_proceeding_document" class="remove-btn">Remove</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endwhile; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p>No proceeding docs</p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_team_head): ?>
                                        <?php if ($complaint['status'] === 'pending'): ?>
                                            <button onclick="toggleResolutionForm(<?php echo $complaint_id; ?>)" class="btn-primary">Resolve</button>
                                            <div id="resolution-form-<?php echo $complaint_id; ?>" class="resolution-form" style="display: none;">
                                                <form action="staff_dashboard.php" method="POST">
                                                    <input type="hidden" name="complaint_id" value="<?php echo $complaint_id; ?>">
                                                    <textarea name="resolution_notes" placeholder="Resolution notes..." required></textarea>
                                                    <button type="submit" name="resolve_complaint" class="btn-primary">Submit Resolution</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="team-member-actions">
                                            <form action="staff_dashboard.php" method="POST">
                                                <input type="hidden" name="complaint_id" value="<?php echo $complaint_id; ?>">
                                                <select name="team_member_id" style="margin-bottom: 5px;">
                                                    <option value="">Add Team Member</option>
                                                    <?php while ($staff_member = mysqli_fetch_assoc($all_staff_result)): ?>
                                                        <option value="<?php echo $staff_member['id']; ?>"><?php echo htmlspecialchars($staff_member['name']); ?></option>
                                                    <?php endwhile; ?>
                                                    <?php mysqli_data_seek($all_staff_result, 0); ?>
                                                </select>
                                                <button type="submit" name="add_team_member" class="btn-primary">Add</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </section>
    </div>

    <script>
        function showSection(sectionId) {
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(sectionId).classList.add('active');
        }

        function togglePasswordForm() {
            const form = document.getElementById('change-password-form');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function togglePasswordVisibility(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
        }

        function validatePassword() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                alert("New passwords don't match!");
                return false;
            }
            return true;
        }

        function filterComplaints() {
            const statusFilter = document.getElementById('status_filter').value;
            window.location.href = `staff_dashboard.php?status=${statusFilter}#track-complaints`;
        }

        function filterManageComplaints() {
            const statusFilter = document.getElementById('manage_status_filter').value;
            window.location.href = `staff_dashboard.php?status=${statusFilter}#manage-complaints`;
        }

        function toggleResolutionForm(complaintId) {
            const form = document.getElementById(`resolution-form-${complaintId}`);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        const urlParams = new URLSearchParams(window.location.search);
        const statusFilter = urlParams.get('status') || 'all';
        document.getElementById('status_filter').value = statusFilter;
        document.getElementById('manage_status_filter').value = statusFilter;

        const hash = window.location.hash || '#profile';
        showSection(hash.substring(1));
    </script>
</body>
</html>