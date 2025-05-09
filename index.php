<?php
require_once 'includes/header.php';

// If user is logged in, redirect to calendar
if (isLoggedIn()) {
    header('Location: calendar.php');
    exit();
}
?>

<div class="row align-items-center">
    <div class="col-md-6">
        <h1 class="display-4 mb-4">Take Control of Your Preventive Health</h1>
        <p class="lead">
            Get personalized health screening recommendations based on your age, sex, and health conditions.
            Never miss an important checkup with our smart calendar system.
        </p>
        <div class="mt-4">
            <a href="register.php" class="btn btn-primary btn-lg me-3">Get Started</a>
            <a href="login.php" class="btn btn-outline-primary btn-lg">Login</a>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h3>Why Use Health Calendar?</h3>
                <ul class="list-unstyled">
                    <li class="mb-3">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        Personalized recommendations based on WHO and CDC guidelines
                    </li>
                    <li class="mb-3">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        Smart calendar with automatic reminders
                    </li>
                    <li class="mb-3">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        Up-to-date health screening information
                    </li>
                    <li class="mb-3">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        Easy-to-use interface for managing your health schedule
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-body">
                <h3>How It Works</h3>
                <ol class="mb-0">
                    <li class="mb-2">Create your account</li>
                    <li class="mb-2">Fill in your health profile</li>
                    <li class="mb-2">Get personalized recommendations</li>
                    <li>Stay on top of your preventive health</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="row mt-5">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h4>Evidence-Based</h4>
                <p>Our recommendations are based on the latest guidelines from trusted health organizations.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h4>Privacy First</h4>
                <p>Your health information is secure and never shared with third parties.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h4>Always Updated</h4>
                <p>Our guidelines are regularly updated to reflect the latest health recommendations.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 