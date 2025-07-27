<?php
session_start();
include 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$type = $_GET['type'];
$id = $_GET['id'];

$sql = "SELECT * FROM " . ($type === 'student' ? 'students' : 'staff') . " WHERE id = '$id'";
$result = mysqli_query($conn, $sql);
$user = mysqli_fetch_assoc($result);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $updates = ["name = '$name'", "email = '$email'"];

    if ($type === 'student') {
        $year = mysqli_real_escape_string($conn, $_POST['year']);
        $branch = mysqli_real_escape_string($conn, $_POST['branch']);
        $updates[] = "year = '$year'";
        $updates[] = "branch = '$branch'";
    }

    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $updates[] = "password = '$password'";
    }

    $table_name = ($type === 'student') ? 'students' : 'staff';
    $update_sql = "UPDATE $table_name SET " . implode(", ", $updates) . " WHERE id = '$id'";
    
    if (mysqli_query($conn, $update_sql)) {
        $_SESSION['success_message'] = ucfirst($type) . " updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating " . $type . ": " . mysqli_error($conn);
    }
    
    header("Location: admin_dashboard.php#manage-" . ($type === 'staff' ? 'staff' : 'students'));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit <?php echo ucfirst($type); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            color: #333;
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
        .btn-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            text-align: center;
        }
        .btn-primary {
            background-color: #007bff;
            color: #fff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: #fff;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
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
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Edit <?php echo ucfirst($type); ?></h1>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="message error"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>
        
        <form action="edit_user.php?type=<?php echo $type; ?>&id=<?php echo $id; ?>" method="POST">
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            
            <?php if ($type === 'student'): ?>
                <div class="form-group">
                    <label for="year">Year:</label>
                    <input type="number" name="year" value="<?php echo htmlspecialchars($user['year']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="branch">Branch:</label>
                    <input type="text" name="branch" value="<?php echo htmlspecialchars($user['branch']); ?>" required>
                </div>
            <?php endif; ?>
            
            <div class="form-group password-container">
                <label for="password">New Password (leave blank to keep current):</label>
                <input type="password" name="password" id="password">
                <span class="toggle-password" onclick="togglePasswordVisibility('password')">👁</span>
            </div>
            
            <div class="btn-container">
                <button type="submit" class="btn btn-primary">Update</button>
                <a href="admin_dashboard.php#manage-<?php echo ($type === 'staff' ? 'staff' : 'students'); ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        function togglePasswordVisibility(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>