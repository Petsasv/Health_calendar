<?php
require_once 'includes/header.php';
requireLogin();

$user = getCurrentUser();
if (empty($user['age']) || empty($user['sex'])) {
    header('Location: profile.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $conn = getDBConnection();
        
        switch ($_POST['action']) {
            case 'mark_completed':
                $reminder_id = intval($_POST['reminder_id']);
                $stmt = $conn->prepare("UPDATE reminders SET last_check = CURRENT_DATE, next_due = DATE_ADD(CURRENT_DATE, INTERVAL ? DAY) WHERE id = ? AND user_id = ?");
                
                // Calculate days based on frequency
                $frequency = $_POST['frequency'];
                $days = 365; // Default to yearly
                if (strpos($frequency, 'month') !== false) {
                    $days = 30;
                } elseif (strpos($frequency, '2 year') !== false) {
                    $days = 730;
                } elseif (strpos($frequency, '3 year') !== false) {
                    $days = 1095;
                } elseif (strpos($frequency, '5 year') !== false) {
                    $days = 1825;
                } elseif (strpos($frequency, '6 month') !== false) {
                    $days = 182;
                }
                
                $stmt->bind_param("iii", $days, $reminder_id, $user['id']);
                $stmt->execute();
                break;
                
            case 'add_reminder':
                $test_name = $_POST['test_name'];
                $frequency = $_POST['frequency'];
                $info_link = $_POST['info_link'];
                $next_due = $_POST['next_due'];
                
                $stmt = $conn->prepare("INSERT INTO reminders (user_id, test_name, frequency, info_link, next_due) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $user['id'], $test_name, $frequency, $info_link, $next_due);
                $stmt->execute();
                break;
        }
        
        // Redirect to prevent form resubmission
        header('Location: calendar.php');
        exit();
    }
}

// Load guidelines
$guidelines_file = __DIR__ . '/data/guidelines.json';
$guidelines_data = json_decode(file_get_contents($guidelines_file), true);

// Get personalized recommendations
function getPersonalizedRecommendations($user, $guidelines) {
    $recommendations = [];
    
    foreach ($guidelines['guidelines'] as $guideline) {
        // Check if guideline applies to user's sex and age
        if (
            ($guideline['sex'] === 'any' || $guideline['sex'] === $user['sex']) &&
            $user['age'] >= $guideline['age_min'] &&
            $user['age'] <= $guideline['age_max']
        ) {
            $recommendations[] = $guideline;
        }
    }
    
    return $recommendations;
}

$recommendations = getPersonalizedRecommendations($user, $guidelines_data);

// Get user's reminders
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM reminders WHERE user_id = ? ORDER BY next_due ASC");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$reminders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Current month calendar
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$first_day = mktime(0, 0, 0, $month, 1, $year);
$days_in_month = date('t', $first_day);
$first_day_of_week = date('w', $first_day);

// Previous and next month links
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}
?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn btn-sm btn-outline-primary">&lt; Previous</a>
                    <h3 class="mb-0"><?php echo date('F Y', $first_day); ?></h3>
                    <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn btn-sm btn-outline-primary">Next &gt;</a>
                </div>
            </div>
            <div class="card-body">
                <table class="calendar-table">
                    <thead>
                        <tr>
                            <th>Sun</th>
                            <th>Mon</th>
                            <th>Tue</th>
                            <th>Wed</th>
                            <th>Thu</th>
                            <th>Fri</th>
                            <th>Sat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $day_count = 1;
                        $calendar_html = '<tr>';
                        
                        // Empty cells before first day
                        for ($i = 0; $i < $first_day_of_week; $i++) {
                            $calendar_html .= '<td></td>';
                            $day_count++;
                        }
                        
                        // Days of the month
                        for ($day = 1; $day <= $days_in_month; $day++) {
                            if ($day_count > 7) {
                                $calendar_html .= '</tr><tr>';
                                $day_count = 1;
                            }
                            
                            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $calendar_html .= '<td>';
                            $calendar_html .= $day;
                            
                            // Add reminders for this day
                            foreach ($reminders as $reminder) {
                                if (date('Y-m-d', strtotime($reminder['next_due'])) === $date) {
                                    $calendar_html .= sprintf(
                                        '<div class="calendar-event" data-bs-toggle="tooltip" title="%s" data-info-link="%s">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="mark_completed">
                                                <input type="hidden" name="reminder_id" value="%d">
                                                <input type="hidden" name="frequency" value="%s">
                                                <button type="submit" class="btn btn-sm btn-success">✓</button>
                                            </form>
                                            %s
                                        </div>',
                                        htmlspecialchars($reminder['frequency']),
                                        htmlspecialchars($reminder['info_link']),
                                        $reminder['id'],
                                        htmlspecialchars($reminder['frequency']),
                                        htmlspecialchars($reminder['test_name'])
                                    );
                                }
                            }
                            
                            $calendar_html .= '</td>';
                            $day_count++;
                        }
                        
                        // Empty cells after last day
                        while ($day_count <= 7) {
                            $calendar_html .= '<td></td>';
                            $day_count++;
                        }
                        
                        $calendar_html .= '</tr>';
                        echo $calendar_html;
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Your Health Recommendations</h3>
            </div>
            <div class="card-body">
                <?php foreach ($recommendations as $rec): ?>
                    <div class="health-recommendation">
                        <h4><?php echo htmlspecialchars($rec['test']); ?></h4>
                        <div class="frequency">
                            Frequency: <?php echo htmlspecialchars($rec['frequency']); ?>
                        </div>
                        <div class="info-link">
                            <a href="<?php echo htmlspecialchars($rec['info_link']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                Learn More
                            </a>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="add_reminder">
                                <input type="hidden" name="test_name" value="<?php echo htmlspecialchars($rec['test']); ?>">
                                <input type="hidden" name="frequency" value="<?php echo htmlspecialchars($rec['frequency']); ?>">
                                <input type="hidden" name="info_link" value="<?php echo htmlspecialchars($rec['info_link']); ?>">
                                <input type="hidden" name="next_due" value="<?php echo date('Y-m-d'); ?>">
                                <button type="submit" class="btn btn-sm btn-success">Add to Calendar</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($recommendations)): ?>
                    <div class="alert alert-info">
                        No specific recommendations found for your profile.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3 class="mb-0">Upcoming Checkups</h3>
            </div>
            <div class="card-body">
                <?php if (empty($reminders)): ?>
                    <div class="alert alert-info">
                        No upcoming checkups scheduled.
                    </div>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($reminders as $reminder): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($reminder['test_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            Due: <?php echo date('F j, Y', strtotime($reminder['next_due'])); ?>
                                        </small>
                                    </div>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="mark_completed">
                                        <input type="hidden" name="reminder_id" value="<?php echo $reminder['id']; ?>">
                                        <input type="hidden" name="frequency" value="<?php echo htmlspecialchars($reminder['frequency']); ?>">
                                        <button type="submit" class="btn btn-sm btn-success">Mark Done</button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 