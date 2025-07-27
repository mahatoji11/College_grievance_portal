<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grievance Redressal Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Basic styling for the homepage */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        header {
            background-color: #333;
            color: #fff;
            padding: 20px;
            text-align: center;
        }
        header h1 {
            margin: 0;
            font-size: 2.5rem;
        }
        nav ul {
            list-style: none;
            padding: 0;
            margin: 20px 0 0;
            display: flex;
            justify-content: center;
        }
        nav ul li {
            margin: 0 15px;
        }
        nav ul li a {
            color: #fff;
            text-decoration: none;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }
        nav ul li a:hover {
            color: #ff6347; 
        }
        main {
            padding: 20px;
            text-align: center;
        }
        .hero {
            background-color: #fff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: 20px auto;
        }
        .hero h2 {
            font-size: 2rem;
            margin-bottom: 20px;
        }
        .hero p {
            font-size: 1.2rem;
            color: #555;
        }
        footer {
            background-color: #333;
            color: #fff;
            text-align: center;
            padding: 10px;
            position: fixed;
            bottom: 0;
            width: 100%;
        }
    </style>
</head>
<body>
    <header>
        <h1>Grievance Redressal Portal</h1>
        <nav>
            <ul>
                <li><a href="student_login.php">Student Login</a></li>
                <li><a href="admin_login.php">Admin Login</a></li>
                <li><a href="staff_login.php">Staff Login</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <section class="hero">
            <h2>Welcome to the Grievance Redressal Portal</h2>
            <p>Submit your complaints and track their status easily.</p>
            <!-- Example PHP Code: Display Current Date -->
            <p>Today's date is: <?php echo date('Y-m-d'); ?></p>
        </section>
    </main>
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Grievance Redressal Portal. All rights reserved.</p>
    </footer>
</body>
</html>