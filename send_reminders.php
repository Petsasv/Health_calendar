<?php
require_once 'includes/db.php';

function sendReminderEmail($user, $reminder) {
    $to = $user['email'];
    $subject = "Upcoming Health Checkup Reminder";
    
    // Calculate days until due
    $due_date = new DateTime($reminder['due_date']);
    $today = new DateTime();
    $days_until = $today->diff($due_date)->days;
    
    // Prepare email content
    $message = "Dear " . htmlspecialchars($user['username']) . ",\n\n";
    $message .= "This is a reminder that you have an upcoming health checkup:\n\n";
    $message .= "Checkup: " . htmlspecialchars($reminder['title']) . "\n";
    $message .= "Due Date: " . $reminder['due_date'] . "\n";
    $message .= "Days until due: " . $days_until . "\n\n";
    
    if ($reminder['description']) {
        $message .= "Additional Information:\n" . htmlspecialchars($reminder['description']) . "\n\n";
    }
    
    $message .= "Please schedule your appointment soon to maintain your health routine.\n\n";
    $message .= "Best regards,\nHealth Calendar Team";
    
    $headers = "From: noreply@healthcalendar.com\r\n";
    $headers .= "Reply-To: support@healthcalendar.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($to, $subject, $message, $headers);
}

// Get reminders due in the next 7 days
$sql = "SELECT r.*, u.email, u.username 
        FROM reminders r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND (r.last_reminder IS NULL OR r.last_reminder < DATE_SUB(CURDATE(), INTERVAL 7 DAY))
        AND r.last_completed IS NULL";

$result = $conn->query($sql);

if ($result) {
    while ($reminder = $result->fetch_assoc()) {
        $user = [
            'email' => $reminder['email'],
            'username' => $reminder['username']
        ];
        
        if (sendReminderEmail($user, $reminder)) {
            // Update last_reminder timestamp
            $update_sql = "UPDATE reminders SET last_reminder = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("i", $reminder['id']);
            $stmt->execute();
            
            echo "Reminder sent to " . $user['email'] . " for " . $reminder['title'] . "\n";
        } else {
            echo "Failed to send reminder to " . $user['email'] . "\n";
        }
    }
} else {
    echo "Error fetching reminders: " . $conn->error . "\n";
}

$conn->close();
?> 