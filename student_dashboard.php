<?php
session_start();
include 'config.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$sql = "SELECT * FROM students WHERE id = '$student_id'";
$result = mysqli_query($conn, $sql);
$student = mysqli_fetch_assoc($result);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (password_verify($old_password, $student['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE students SET password = '$hashed_password' WHERE id = '$student_id'";
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

    header("Location: student_dashboard.php");
    exit();
}

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$complaint_sql = "SELECT * FROM complaints WHERE student_id = '$student_id'";
if ($status_filter !== 'all') {
    if ($status_filter === 'challenged' || $status_filter === 'accepted') {
        $complaint_sql .= " AND final_decision = '$status_filter'";
    } else {
        $complaint_sql .= " AND status = '$status_filter'";
    }
}
$complaint_sql .= " ORDER BY created_at DESC";
$complaint_result = mysqli_query($conn, $complaint_sql);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_document'])) {
    $complaint_id = $_POST['complaint_id'];
    $files = $_FILES['extra_documents'];

    for ($i = 0; $i < count($files['name']); $i++) {
        $file_name = $files['name'][$i];
        $file_tmp = $files['tmp_name'][$i];
        $file_path = "assets/uploads/" . basename($file_name);

        if (move_uploaded_file($file_tmp, $file_path)) {
            $insert_sql = "INSERT INTO complaint_attachments (complaint_id, file_path, uploaded_by, uploaded_by_student_id) 
                           VALUES ('$complaint_id', '$file_path', 'student', '$student_id')";
            mysqli_query($conn, $insert_sql);
        }
    }
    $_SESSION['success_message'] = "Extra documents added successfully!";
    header("Location: student_dashboard.php");
    exit();
}

if (isset($_GET['complaint_submitted']) && $_GET['complaint_submitted'] == 'true') {
    $_SESSION['success_message'] = "Complaint submitted successfully!";
    header("Location: student_dashboard.php");
    exit();
}

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
    <title>Student Dashboard</title>
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
        h2 {
            color: #333;
        }
        .profile-section, .complaint-section, .change-password-section {
            margin-bottom: 30px;
            display: none;
        }
        .profile-section.active, .complaint-section.active, .change-password-section.active {
            display: block;
        }
        .profile-section p, .complaint-item p {
            margin: 5px 0;
            color: #555;
        }
        .complaint-item {
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-bottom: 10px;
            background-color: #f9f9f9;
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
        form {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        input[type="text"], textarea, select, input[type="password"], input[type="file"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        button {
            background-color: #333;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #555;
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
        .small-file-input {
            width: auto;
            padding: 5px;
            margin-bottom: 5px;
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
        <h1>Student Dashboard</h1>
    </header>
    <nav>
        <a href="#profile" onclick="showSection('profile')">Profile</a>
        <a href="#submit-complaint" onclick="showSection('submit-complaint')">Submit Complaint</a>
        <a href="#track-complaints" onclick="showSection('track-complaints')">Track Complaints</a>
        <a href="logout.php?role=student">Log Out</a>
    </nav>
    <div class="container">
        <section id="profile" class="profile-section active">
            <h2>Profile</h2>
            <p><strong>Name:</strong> <?php echo $student['name']; ?></p>
            <p><strong>Email:</strong> <?php echo $student['email']; ?></p>
            <p><strong>Roll No:</strong> <?php echo $student['roll_no']; ?></p>
            <p><strong>Registration No:</strong> <?php echo $student['registration_no']; ?></p>
            <p><strong>Year:</strong> <?php echo $student['year']; ?></p>
            <p><strong>Branch:</strong> <?php echo $student['branch']; ?></p>
            <button onclick="togglePasswordForm()">Change Password</button>

            <?php if (isset($success_message)): ?>
                <div class="message success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <div class="message error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div id="change-password-form" style="display: none;">
                <h3>Change Password</h3>
                <form action="student_dashboard.php" method="POST" onsubmit="return validatePassword()">
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
                        <input type="password" id="confirm_password" name="confirm_password" required onpaste="return false;" oncopy="return false;" oncut="return false;">
                    </div>

                    <button type="submit" name="change_password">Change Password</button>
                </form>
            </div>
        </section>

        <section id="submit-complaint" class="complaint-section">
            <h2>Submit a Complaint</h2>
            <?php if (isset($success_message)): ?>
                <div class="message success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <form action="php/submit_complaint.php" method="POST" enctype="multipart/form-data">
                <label for="description">Description:</label>
                <textarea id="description" name="description" rows="5" required></textarea>

                <label for="category">Category:</label>
                <select id="category" name="category" required>
                    <option value="academic">Academic</option>
                    <option value="hostel">Hostel</option>
                    <option value="finance">Finance</option>
                    <option value="infrastructure">Infrastructure</option>
                    <option value="other">Other</option>
                </select>

                <label for="files">Upload Files (Optional):</label>
                <input type="file" id="files" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.mp4">

                <button type="submit">Submit Complaint</button>
            </form>
        </section>

        <section id="track-complaints" class="complaint-section">
            <h2>Track Your Complaints</h2>
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
                    <?php if (mysqli_num_rows($complaint_result) > 0): ?>
                        <?php while ($complaint = mysqli_fetch_assoc($complaint_result)): ?>
                            <?php
                            $complaint_id = $complaint['id'];

                            $attachment_sql = "SELECT ca.*, s.name AS student_name 
                                              FROM complaint_attachments ca 
                                              JOIN students s ON ca.uploaded_by = 'student' AND s.id = '$student_id' 
                                              WHERE ca.complaint_id = '$complaint_id'";
                            $attachment_result = mysqli_query($conn, $attachment_sql);

                            $proceeding_attachment_sql = "SELECT ca.*, st.name AS staff_name 
                                                          FROM complaint_attachments ca 
                                                          JOIN staff st ON ca.uploaded_by_staff_id = st.id 
                                                          WHERE ca.complaint_id = '$complaint_id' AND ca.uploaded_by = 'staff'";
                            $proceeding_attachment_result = mysqli_query($conn, $proceeding_attachment_sql);
                            ?>
                            <tr>
                                <td><?php echo $complaint['description']; ?></td>
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
                                    <?php if (mysqli_num_rows($attachment_result) > 0): ?>
                                        <ul>
                                            <?php while ($attachment = mysqli_fetch_assoc($attachment_result)): ?>
                                                <li>
                                                    <a href="<?php echo $attachment['file_path']; ?>" target="_blank"><?php echo basename($attachment['file_path']); ?></a>
                                                    (Uploaded by: <?php echo $attachment['student_name']; ?>)
                                                </li>
                                            <?php endwhile; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p>No attachments found.</p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (mysqli_num_rows($proceeding_attachment_result) > 0): ?>
                                        <ul>
                                            <?php while ($proceeding_attachment = mysqli_fetch_assoc($proceeding_attachment_result)): ?>
                                                <li>
                                                    <a href="<?php echo $proceeding_attachment['file_path']; ?>" target="_blank"><?php echo basename($proceeding_attachment['file_path']); ?></a>
                                                    (Uploaded by: <?php echo $proceeding_attachment['staff_name']; ?>)
                                                </li>
                                            <?php endwhile; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p>No proceeding attachments found.</p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form action="student_dashboard.php" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                                        <input type="file" name="extra_documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.mp4" class="small-file-input">
                                        <button type="submit" name="add_document">Upload</button>
                                    </form>
                                </td>
                                <td>
                                    <?php if ($complaint['status'] === 'resolved' && $complaint['final_decision'] === 'pending'): ?>
                                        <form action="handle_decision.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                                            <button type="submit" name="decision" value="challenge" onclick="return confirm('Are you sure you want to challenge this decision?');">Challenge</button>
                                            <button type="submit" name="decision" value="accept" onclick="return confirm('Are you sure you want to accept this decision?');">Accept</button>
                                        </form>
                                    <?php elseif ($complaint['final_decision'] === 'challenged'): ?>
                                        <p>Decision Challenged</p>
                                        <?php if (!empty($complaint['challenge_reason'])): ?>
                                            <div class="challenge-reason">
                                                <strong>Reason:</strong> <?php echo htmlspecialchars($complaint['challenge_reason']); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($complaint['final_decision'] === 'accepted'): ?>
                                        <p>Decision Accepted</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">No complaints found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>

    <script>
        function showSection(sectionId) {
            document.querySelectorAll('.profile-section, .complaint-section, .change-password-section').forEach(section => {
                section.style.display = 'none';
            });

            document.getElementById(sectionId).style.display = 'block';
        }

        function togglePasswordForm() {
            const form = document.getElementById('change-password-form');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function togglePasswordVisibility(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
            } else {
                passwordInput.type = 'password';
            }
        }

        function validatePassword() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                alert("New passwords do not match!");
                return false;
            }
            return true;
        }

        function filterComplaints() {
            const statusFilter = document.getElementById('status_filter').value;
            window.location.href = `student_dashboard.php?status=${statusFilter}#track-complaints`;
        }

        const urlParams = new URLSearchParams(window.location.search);
        const statusFilter = urlParams.get('status') || 'all';
        document.getElementById('status_filter').value = statusFilter;

        if (window.location.hash === '#track-complaints') {
            showSection('track-complaints');
        }
    </script>
</body>
</html>