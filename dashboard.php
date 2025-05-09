<?php
require_once 'includes/header.php';
require_once 'includes/api/health_api.php';
requireLogin();

$user = getCurrentUser();
if (empty($user['age']) || empty($user['sex'])) {
    header('Location: profile.php');
    exit();
}

// Initialize Health API
$health_api = new HealthAPI();

// Get user's location from profile
$location = $user['location'] ?? 'default';

// Decode health data
$health_data = json_decode($user['health_data'] ?? '{}', true);

// Get health data
$local_stats = $health_api->getLocalHealthStats($location);
$risk_assessment = $health_api->getRiskAssessment([
    'age' => $user['age'],
    'sex' => $user['sex'],
    'conditions' => $user['conditions'] ?? null,
    'location' => $location,
    'health_data' => $health_data
]);
$community_insights = $health_api->getCommunityInsights($location, $user['age']);

// Get WHO data for comparison
$who_stats = $health_api->getWHOHealthStats($user['age'], $user['sex']);

// Format data for comparison
$comparison_data = [
    'bmi' => [
        'user' => $health_data['current_health']['bmi'] ?? 0,
        'local' => $local_stats['population_health']['average_bmi'] ?? 0,
        'global' => $who_stats['global_averages']['bmi'] ?? 0
    ],
    'blood_pressure' => [
        'user' => [
            'systolic' => $health_data['current_health']['blood_pressure']['systolic'] ?? 0,
            'diastolic' => $health_data['current_health']['blood_pressure']['diastolic'] ?? 0
        ],
        'local' => [
            'systolic' => $local_stats['population_health']['hypertension_rate'] ?? 0,
            'diastolic' => $local_stats['population_health']['hypertension_rate'] ?? 0
        ],
        'global' => [
            'systolic' => $who_stats['global_averages']['blood_pressure'] ?? 0,
            'diastolic' => $who_stats['global_averages']['blood_pressure'] ?? 0
        ]
    ],
    'lifestyle' => [
        'user' => [
            'physical_activity' => $health_data['lifestyle']['physical_activity'] ?? 'unknown',
            'smoking' => $health_data['lifestyle']['smoking_status'] ?? 'unknown',
            'sleep' => $health_data['lifestyle']['sleep_hours'] ?? 0
        ],
        'local' => [
            'physical_inactivity' => $local_stats['population_health']['physical_inactivity'] ?? 0,
            'smoking_rate' => $local_stats['population_health']['smoking_rate'] ?? 0
        ],
        'global' => [
            'physical_activity' => $who_stats['global_averages']['physical_activity'] ?? 0,
            'smoking_rate' => $who_stats['global_averages']['tobacco'] ?? 0
        ]
    ]
];

// Format data for charts
$population_health_data = [
    'common_conditions' => [
        'Hypertension' => $local_stats['population_health']['hypertension_rate'] ?? 0,
        'Diabetes' => $local_stats['population_health']['diabetes_rate'] ?? 0,
        'Smoking' => $local_stats['population_health']['smoking_rate'] ?? 0,
        'Physical Inactivity' => $local_stats['population_health']['physical_inactivity'] ?? 0
    ]
];

$risk_assessment_data = [
    'specific_conditions' => array_map(function($risk) {
        return ucfirst($risk);
    }, $risk_assessment['general_health'] ?? [])
];

$community_insights_data = [
    'checkup_compliance' => [
        'by_age' => [
            '18-34' => $community_insights['preventive_care']['checkup_rate'] ?? 0,
            '35-49' => $community_insights['preventive_care']['checkup_rate'] ?? 0,
            '50-64' => $community_insights['preventive_care']['checkup_rate'] ?? 0,
            '65+' => $community_insights['preventive_care']['checkup_rate'] ?? 0
        ]
    ],
    'preventive_success' => [
        'Completed Screenings' => $community_insights['preventive_care']['screening_rate'] ?? 0,
        'Pending Screenings' => 100 - ($community_insights['preventive_care']['screening_rate'] ?? 0)
    ]
];
?>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Your Health Dashboard</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header bg-info text-white">
                                <h4 class="mb-0">Your Risk Assessment</h4>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">This section shows your personal health risk factors based on your health assessment.</p>
                                <div class="risk-levels">
                                    <?php 
                                    if (isset($risk_assessment['general_health'])) {
                                        foreach ($risk_assessment['general_health'] as $factor => $risk): 
                                            if ($factor !== 'overall_risk'):
                                    ?>
                                        <div class="risk-factor mb-2">
                                            <span class="factor-name fw-bold"><?php echo ucfirst(str_replace('_', ' ', $factor)); ?>:</span>
                                            <span class="risk-badge risk-<?php echo $risk; ?> ms-2"><?php echo ucfirst($risk); ?></span>
                                        </div>
                                    <?php 
                                            endif;
                                        endforeach; 
                                    }
                                    ?>
                                    <?php if (isset($risk_assessment['general_health']['overall_risk'])): ?>
                                        <div class="risk-factor overall-risk mt-3 pt-3 border-top">
                                            <span class="factor-name fw-bold">Overall Risk:</span>
                                            <span class="risk-badge risk-<?php echo $risk_assessment['general_health']['overall_risk']; ?> ms-2">
                                                <?php echo ucfirst($risk_assessment['general_health']['overall_risk']); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h4 class="mb-0">Preventive Recommendations</h4>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">Based on your health assessment, here are your recommended preventive actions.</p>
                                <div class="recommendations">
                                    <?php if (isset($risk_assessment['preventive_recommendations'])): ?>
                                        <?php foreach ($risk_assessment['preventive_recommendations'] as $type => $items): ?>
                                            <div class="recommendation-type mb-3">
                                                <h5 class="text-primary"><?php echo ucfirst($type); ?> Actions</h5>
                                                <ul class="list-group">
                                                    <?php foreach ($items as $item => $status): ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                            <?php echo ucfirst(str_replace('_', ' ', $item)); ?>
                                                            <span class="status-badge status-<?php echo $status; ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                                            </span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h4 class="mb-0">Local Health Statistics</h4>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Health statistics in <?php echo htmlspecialchars($location); ?> compared to your personal metrics.</p>
                
                <!-- Local Health Metrics -->
                <div class="local-metrics mb-4">
                    <h5 class="text-primary">Population Health</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="metric-card">
                                <h6>Hypertension Rate</h6>
                                <div class="d-flex justify-content-between">
                                    <span>Local:</span>
                                    <span class="badge bg-info"><?php echo number_format($local_stats['population_health']['hypertension_rate'], 1); ?>%</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Your BP:</span>
                                    <span class="badge <?php echo ($health_data['current_health']['blood_pressure']['systolic'] ?? 0) > 140 ? 'bg-danger' : 'bg-success'; ?>">
                                        <?php echo $health_data['current_health']['blood_pressure']['systolic'] ?? 'N/A'; ?>/<?php echo $health_data['current_health']['blood_pressure']['diastolic'] ?? 'N/A'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="metric-card">
                                <h6>Diabetes Rate</h6>
                                <div class="d-flex justify-content-between">
                                    <span>Local:</span>
                                    <span class="badge bg-info"><?php echo number_format($local_stats['population_health']['diabetes_rate'], 1); ?>%</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Your A1C:</span>
                                    <span class="badge <?php echo ($health_data['current_health']['blood_sugar']['hba1c'] ?? 0) > 5.7 ? 'bg-danger' : 'bg-success'; ?>">
                                        <?php echo number_format($health_data['current_health']['blood_sugar']['hba1c'] ?? 0, 1); ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lifestyle Comparison -->
                <div class="lifestyle-comparison mb-4">
                    <h5 class="text-primary">Lifestyle Comparison</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="metric-card">
                                <h6>Physical Activity</h6>
                                <div class="d-flex justify-content-between">
                                    <span>Local Inactivity:</span>
                                    <span class="badge bg-info"><?php echo number_format($local_stats['population_health']['physical_inactivity'], 1); ?>%</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Your Activity:</span>
                                    <span class="badge bg-success"><?php echo ucfirst($health_data['lifestyle']['physical_activity'] ?? 'Not specified'); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="metric-card">
                                <h6>Smoking Rate</h6>
                                <div class="d-flex justify-content-between">
                                    <span>Local Rate:</span>
                                    <span class="badge bg-info"><?php echo number_format($local_stats['population_health']['smoking_rate'], 1); ?>%</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Your Status:</span>
                                    <span class="badge bg-success"><?php echo ucfirst($health_data['lifestyle']['smoking_status'] ?? 'Not specified'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preventive Care -->
                <div class="preventive-care">
                    <h5 class="text-primary">Preventive Care</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="metric-card">
                                <h6>Vaccination Rate</h6>
                                <div class="progress mb-2">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?php echo $local_stats['preventive_care']['vaccination_rate']; ?>%">
                                        <?php echo number_format($local_stats['preventive_care']['vaccination_rate'], 1); ?>%
                                    </div>
                                </div>
                                <small class="text-muted">Local vaccination coverage</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="metric-card">
                                <h6>Screening Rate</h6>
                                <div class="progress mb-2">
                                    <div class="progress-bar bg-info" role="progressbar" 
                                         style="width: <?php echo $local_stats['preventive_care']['screening_rate']; ?>%">
                                        <?php echo number_format($local_stats['preventive_care']['screening_rate'], 1); ?>%
                                    </div>
                                </div>
                                <small class="text-muted">Local screening compliance</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0">Your Health Risks</h4>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Visual representation of your health risk factors.</p>
                <canvas id="riskAssessmentChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0">Community Health Insights</h4>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Health trends and statistics in your community by age group.</p>
                <canvas id="communityInsightsChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Preventive Care Success</h4>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Your progress on recommended preventive care actions.</p>
                <canvas id="preventiveSuccessChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Your Health vs. Population Data</h4>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Compare your health metrics with local and global averages.</p>
                
                <!-- BMI Comparison -->
                <div class="comparison-section mb-4">
                    <h5 class="text-primary">BMI Comparison</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h6>Your BMI</h6>
                                    <h3><?php echo number_format($comparison_data['bmi']['user'], 1); ?></h3>
                                    <small class="text-muted">Personal</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h6>Local Average</h6>
                                    <h3><?php echo number_format($comparison_data['bmi']['local'], 1); ?></h3>
                                    <small class="text-muted">Your Area</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h6>Global Average</h6>
                                    <h3><?php echo number_format($comparison_data['bmi']['global'], 1); ?></h3>
                                    <small class="text-muted">Worldwide</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Blood Pressure Comparison -->
                <div class="comparison-section mb-4">
                    <h5 class="text-primary">Blood Pressure Comparison</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h6>Your Blood Pressure</h6>
                                    <h3><?php echo $comparison_data['blood_pressure']['user']['systolic']; ?>/<?php echo $comparison_data['blood_pressure']['user']['diastolic']; ?></h3>
                                    <small class="text-muted">Personal</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h6>Local Average</h6>
                                    <h3><?php echo $comparison_data['blood_pressure']['local']['systolic']; ?>/<?php echo $comparison_data['blood_pressure']['local']['diastolic']; ?></h3>
                                    <small class="text-muted">Your Area</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h6>Global Average</h6>
                                    <h3><?php echo $comparison_data['blood_pressure']['global']['systolic']; ?>/<?php echo $comparison_data['blood_pressure']['global']['diastolic']; ?></h3>
                                    <small class="text-muted">Worldwide</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lifestyle Comparison -->
                <div class="comparison-section">
                    <h5 class="text-primary">Lifestyle Comparison</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h6>Your Lifestyle</h6>
                                    <ul class="list-unstyled">
                                        <li>Physical Activity: <?php echo ucfirst($comparison_data['lifestyle']['user']['physical_activity']); ?></li>
                                        <li>Smoking Status: <?php echo ucfirst($comparison_data['lifestyle']['user']['smoking']); ?></li>
                                        <li>Sleep Hours: <?php echo $comparison_data['lifestyle']['user']['sleep']; ?> hours</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h6>Local Statistics</h6>
                                    <ul class="list-unstyled">
                                        <li>Physical Inactivity: <?php echo $comparison_data['lifestyle']['local']['physical_inactivity']; ?>%</li>
                                        <li>Smoking Rate: <?php echo $comparison_data['lifestyle']['local']['smoking_rate']; ?>%</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h6>Global Statistics</h6>
                                    <ul class="list-unstyled">
                                        <li>Physical Activity: <?php echo $comparison_data['lifestyle']['global']['physical_activity']; ?>%</li>
                                        <li>Smoking Rate: <?php echo $comparison_data['lifestyle']['global']['smoking_rate']; ?>%</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.risk-badge {
    padding: 5px 10px;
    border-radius: 15px;
    font-weight: bold;
}
.risk-low {
    background-color: #28a745;
    color: white;
}
.risk-medium {
    background-color: #ffc107;
    color: black;
}
.risk-high {
    background-color: #dc3545;
    color: white;
}
.status-badge {
    padding: 5px 10px;
    border-radius: 15px;
    font-weight: bold;
}
.status-completed {
    background-color: #28a745;
    color: white;
}
.status-pending {
    background-color: #ffc107;
    color: black;
}
.status-recommended {
    background-color: #17a2b8;
    color: white;
}
.comparison-section {
    padding: 15px;
    border-radius: 5px;
    background-color: #f8f9fa;
}
.comparison-section h5 {
    margin-bottom: 20px;
}
.card {
    transition: transform 0.2s;
}
.card:hover {
    transform: translateY(-5px);
}
</style>

<script src="assets/js/charts/health_charts.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const charts = new HealthCharts();
    
    // Initialize charts with formatted data
    charts.createPopulationHealthChart(<?php echo json_encode($population_health_data); ?>, 'populationHealthChart');
    charts.createRiskAssessmentChart(<?php echo json_encode($risk_assessment_data); ?>, 'riskAssessmentChart');
    charts.createCommunityInsightsChart(<?php echo json_encode($community_insights_data); ?>, 'communityInsightsChart');
    charts.createPreventiveSuccessChart(<?php echo json_encode($community_insights_data); ?>, 'preventiveSuccessChart');
});
</script>

<?php require_once 'includes/footer.php'; ?> 