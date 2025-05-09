<?php
require_once 'includes/header.php';

// Require login for this page
requireLogin();

$user = getCurrentUser();
$message = '';

// Debug information
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));
error_log("User data: " . print_r($user, true));

if (!$user) {
    $message = '<div class="alert alert-danger">Error: Could not retrieve user data. Please try logging out and logging back in.</div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $age = $_POST['age'] ?? null;
    $sex = $_POST['sex'] ?? null;
    $location = $_POST['location'] ?? null;
    $conditions = $_POST['conditions'] ?? null;
    
    $conn = getDBConnection();
    
    // Debug information
    error_log("Updating profile for user ID: " . $_SESSION['user_id']);
    error_log("Values - Age: $age, Sex: $sex, Location: $location, Conditions: $conditions");
    
    try {
        $stmt = $conn->prepare("UPDATE users SET age = ?, sex = ?, location = ?, conditions = ?, last_health_update = CURRENT_TIMESTAMP WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("isssi", $age, $sex, $location, $conditions, $_SESSION['user_id']);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $message = '<div class="alert alert-success">Profile updated successfully!</div>';
        $user = getCurrentUser(); // Refresh user data
    } catch (Exception $e) {
        error_log("Profile update error: " . $e->getMessage());
        $message = '<div class="alert alert-danger">Error updating profile: ' . $e->getMessage() . '</div>';
    }
}

// Get health assessment status
$health_data = json_decode($user['health_data'] ?? '{}', true);
$has_health_assessment = !empty($health_data);
$last_assessment = $user['last_health_update'] ?? null;
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="mb-0">Your Profile</h3>
            </div>
            <div class="card-body">
                <?php echo $message; ?>
                
                <?php if ($user): ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label for="age" class="form-label">Age</label>
                        <input type="number" class="form-control" id="age" name="age" value="<?php echo htmlspecialchars($user['age'] ?? ''); ?>" min="0" max="120">
                    </div>
                    
                    <div class="mb-3">
                        <label for="sex" class="form-label">Sex</label>
                        <select class="form-select" id="sex" name="sex">
                            <option value="">Select...</option>
                            <option value="male" <?php echo ($user['sex'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($user['sex'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo ($user['sex'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>" placeholder="Enter your city and country" required>
                        <input type="hidden" id="location_country" name="location_country" value="<?php echo htmlspecialchars($user['location_country'] ?? ''); ?>">
                        <div class="form-text">Enter your city and country (e.g., "New York, USA" or "London, UK")</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="conditions" class="form-label">Health Conditions</label>
                        <textarea class="form-control" id="conditions" name="conditions" rows="3" placeholder="List any existing health conditions (optional)"><?php echo htmlspecialchars($user['conditions'] ?? ''); ?></textarea>
                        <div class="form-text">Separate multiple conditions with commas.</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </form>
                <?php else: ?>
                <div class="alert alert-warning">
                    Unable to load profile data. Please try <a href="logout.php">logging out</a> and logging back in.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Health Assessment Section -->
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Health Assessment</h3>
            </div>
            <div class="card-body">
                <?php if ($has_health_assessment): ?>
                    <div class="alert alert-success">
                        <h5>Last Health Assessment</h5>
                        <p>Your last health assessment was completed on: <?php echo date('F j, Y', strtotime($last_assessment)); ?></p>
                        <a href="health_assessment.php" class="btn btn-primary">Update Health Assessment</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <h5>Complete Your Health Assessment</h5>
                        <p>To get personalized health insights and recommendations, please complete your health assessment.</p>
                        <p>The assessment includes:</p>
                        <ul>
                            <li>Lifestyle factors (smoking, physical activity, sleep)</li>
                            <li>Current health metrics (height, weight, blood pressure)</li>
                            <li>Family health history</li>
                            <li>Mental health indicators</li>
                        </ul>
                        <a href="health_assessment.php" class="btn btn-primary">Start Health Assessment</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 