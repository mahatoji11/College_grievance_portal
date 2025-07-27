<?php
session_start();
include 'config.php';

// Redirect to login if the student is not logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit();
}

// Handle attachment removal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_attachment'])) {
    $attachment_id = $_POST['attachment_id'];
    $file_path = $_POST['file_path'];

    // Validate file path (ensure it is inside the uploads folder)
    $uploads_dir = realpath(__DIR__ . '/../assets/uploads');
    $file_path = realpath($file_path);

    if (strpos($file_path, $uploads_dir) !== 0) {
        // Invalid file path
        $_SESSION['error_message'] = "Invalid file path!";
        header("Location: student_dashboard.php#track-complaints");
        exit();
    }

    // Verify that the attachment belongs to the logged-in student
    $sql = "SELECT ca.*, c.student_id 
            FROM complaint_attachments ca
            JOIN complaints c ON ca.complaint_id = c.id
            WHERE ca.id = ? AND c.student_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $attachment_id, $_SESSION['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $attachment = $result->fetch_assoc();
    $stmt->close();

    if (!$attachment) {
        // Attachment does not belong to the student
        $_SESSION['error_message'] = "You do not have permission to remove this attachment!";
        header("Location: student_dashboard.php#track-complaints");
        exit();
    }

    // Delete the attachment from the database
    $delete_sql = "DELETE FROM complaint_attachments WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $attachment_id);
    $stmt->execute();
    $stmt->close();

    // Delete the file from the uploads folder
    if (file_exists($file_path)) {
        unlink($file_path); // Delete the file
    }

    // Redirect with success message
    $_SESSION['success_message'] = "Attachment removed successfully!";
    header("Location: student_dashboard.php#track-complaints");
    exit();
}
?>