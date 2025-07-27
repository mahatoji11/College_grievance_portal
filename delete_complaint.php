<?php
session_start();
include __DIR__ . '/config.php'; // Include the config file

// Redirect to login if the student is not logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit();
}

// Handle complaint deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_complaint'])) {
    $complaint_id = $_POST['complaint_id'];

    // Verify that the complaint belongs to the logged-in student
    $sql = "SELECT * FROM complaints WHERE id = ? AND student_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $complaint_id, $_SESSION['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $complaint = $result->fetch_assoc();
    $stmt->close();

    if (!$complaint) {
        // Complaint does not belong to the student
        $_SESSION['error_message'] = "You do not have permission to delete this complaint!";
        header("Location: student_dashboard.php#track-complaints");
        exit();
    }

    // Delete all attachments associated with the complaint
    $attachment_sql = "SELECT file_path FROM complaint_attachments WHERE complaint_id = ?";
    $stmt = $conn->prepare($attachment_sql);
    $stmt->bind_param("i", $complaint_id);
    $stmt->execute();
    $attachment_result = $stmt->get_result();

    while ($attachment = $attachment_result->fetch_assoc()) {
        $file_path = $attachment['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path); // Delete the file from the uploads folder
        }
    }
    $stmt->close();

    // Delete all attachments from the database
    $delete_attachments_sql = "DELETE FROM complaint_attachments WHERE complaint_id = ?";
    $stmt = $conn->prepare($delete_attachments_sql);
    $stmt->bind_param("i", $complaint_id);
    $stmt->execute();
    $stmt->close();

    // Delete the complaint from the database
    $delete_complaint_sql = "DELETE FROM complaints WHERE id = ?";
    $stmt = $conn->prepare($delete_complaint_sql);
    $stmt->bind_param("i", $complaint_id);
    $stmt->execute();
    $stmt->close();

    // Redirect with success message
    $_SESSION['success_message'] = "Complaint deleted successfully!";
    header("Location: student_dashboard.php#track-complaints"); // Redirect to the Track Complaints section
    exit();
}
?>