<?php
include 'config.php'; 

$file = 'students.csv'; 

if (!file_exists($file)) {
    die("Error: File not found!");
}

$handle = fopen($file, "r");

if (!$handle) {
    die("Error: Unable to open the file.");
}

fgetcsv($handle); 

$imported = 0;
$skipped = 0;

while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
    $name = mysqli_real_escape_string($conn, $data[0]);
    $roll_no = mysqli_real_escape_string($conn, $data[1]);
    $email = mysqli_real_escape_string($conn, $data[2]);
    $father_name = mysqli_real_escape_string($conn, $data[3]);
    $registration_no = mysqli_real_escape_string($conn, $data[4]);
    $current_year = mysqli_real_escape_string($conn, $data[5]);
    $branch = mysqli_real_escape_string($conn, $data[6]);
    $hashed_password = password_hash($registration_no, PASSWORD_DEFAULT);

    $check_query = "SELECT id FROM students WHERE email = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 0) {
        $insert_query = "INSERT INTO students (name, roll_no, email, father_name, registration_no, year, branch, password) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("ssssssss", $name, $roll_no, $email, $father_name, $registration_no, $current_year, $branch, $hashed_password);
        
        if ($stmt->execute()) {
            $imported++;
        } else {
            echo "Error inserting record for $email: " . $stmt->error . "\n";
        }
    } else {
        echo "Skipped duplicate email: $email\n";
        $skipped++;
    }
}

fclose($handle);
echo "Import completed! $imported students added, $skipped skipped due to duplicates.\n";
?>
