<?php
session_start();
include 'config.php';

if (!isset($_POST['complaint_id']) || !isset($_POST['decision'])) {
    $_SESSION['error_message'] = "Invalid request!";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit();
}

$complaint_id = mysqli_real_escape_string($conn, $_POST['complaint_id']);
$decision = mysqli_real_escape_string($conn, $_POST['decision']);

if ($decision !== 'challenge' && $decision !== 'accept') {
    $_SESSION['error_message'] = "Invalid decision!";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit();
}

if ($decision === 'challenge') {
    $challenge_reason = isset($_POST['challenge_reason']) ? mysqli_real_escape_string($conn, $_POST['challenge_reason']) : '';
    $update_sql = "UPDATE complaints SET final_decision = 'challenged', challenge_reason = '$challenge_reason', team_lead_id = NULL, status = 'pending' WHERE id = '$complaint_id'";
} else {
    $update_sql = "UPDATE complaints SET final_decision = 'accepted' WHERE id = '$complaint_id'";
}

if (mysqli_query($conn, $update_sql)) {
    $_SESSION['success_message'] = "Decision submitted successfully!";
} else {
    $_SESSION['error_message'] = "Error submitting decision: " . mysqli_error($conn);
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit();
?>