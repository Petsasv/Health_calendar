<?php
require_once 'includes/header.php';
requireLogin();

$user = getCurrentUser();
$message = '';
$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $health_data = [
        'lifestyle' => [
            'smoking_status' => $_POST['smoking_status'] ?? 'never',
            'alcohol_consumption' => $_POST['alcohol_consumption'] ?? 'none',
            'physical_activity' => $_POST['physical_activity'] ?? 'sedentary',
            'sleep_hours' => (int)($_POST['sleep_hours'] ?? 7),
            'diet_quality' => $_POST['diet_quality'] ?? 'average',
            'exercise_frequency' => $_POST['exercise_frequency'] ?? 'never',
            'exercise_duration' => (int)($_POST['exercise_duration'] ?? 0),
            'exercise_type' => $_POST['exercise_type'] ?? [],
            'diet_type' => $_POST['diet_type'] ?? 'regular',
            'water_intake' => (int)($_POST['water_intake'] ?? 0),
            'caffeine_intake' => $_POST['caffeine_intake'] ?? 'none'
        ],
        'family_history' => [
            'diabetes' => isset($_POST['family_diabetes']),
            'heart_disease' => isset($_POST['family_heart']),
            'cancer' => isset($_POST['family_cancer']),
            'high_blood_pressure' => isset($_POST['family_hypertension']),
            'stroke' => isset($_POST['family_stroke']),
            'kidney_disease' => isset($_POST['family_kidney']),
            'thyroid_disorders' => isset($_POST['family_thyroid']),
            'autoimmune_diseases' => isset($_POST['family_autoimmune'])
        ],
        'current_health' => [
            'height' => (float)($_POST['height'] ?? 0),
            'weight' => (float)($_POST['weight'] ?? 0),
            'blood_pressure' => [
                'systolic' => (int)($_POST['bp_systolic'] ?? 0),
                'diastolic' => (int)($_POST['bp_diastolic'] ?? 0),
                'last_measured' => $_POST['bp_last_measured'] ?? null
            ],
            'cholesterol' => [
                'total' => (int)($_POST['cholesterol_total'] ?? 0),
                'hdl' => (int)($_POST['cholesterol_hdl'] ?? 0),
                'ldl' => (int)($_POST['cholesterol_ldl'] ?? 0),
                'triglycerides' => (int)($_POST['triglycerides'] ?? 0),
                'last_measured' => $_POST['cholesterol_last_measured'] ?? null
            ],
            'blood_sugar' => [
                'fasting' => (int)($_POST['blood_sugar_fasting'] ?? 0),
                'hba1c' => (float)($_POST['hba1c'] ?? 0),
                'last_measured' => $_POST['blood_sugar_last_measured'] ?? null
            ],
            'thyroid' => [
                'tsh' => (float)($_POST['tsh'] ?? 0),
                't3' => (float)($_POST['t3'] ?? 0),
                't4' => (float)($_POST['t4'] ?? 0),
                'last_measured' => $_POST['thyroid_last_measured'] ?? null
            ],
            'vitamin_levels' => [
                'vitamin_d' => (float)($_POST['vitamin_d'] ?? 0),
                'vitamin_b12' => (float)($_POST['vitamin_b12'] ?? 0),
                'iron' => (float)($_POST['iron'] ?? 0),
                'last_measured' => $_POST['vitamins_last_measured'] ?? null
            ]
        ],
        'mental_health' => [
            'stress_level' => (int)($_POST['stress_level'] ?? 3),
            'anxiety_level' => (int)($_POST['anxiety_level'] ?? 3),
            'depression_symptoms' => isset($_POST['depression_symptoms']),
            'sleep_quality' => $_POST['sleep_quality'] ?? 'average',
            'mood_patterns' => $_POST['mood_patterns'] ?? [],
            'therapy_status' => $_POST['therapy_status'] ?? 'none',
            'medication_use' => isset($_POST['medication_use']),
            'medication_list' => $_POST['medication_list'] ?? []
        ],
        'preventive_care' => [
            'last_physical' => $_POST['last_physical'] ?? null,
            'last_dental' => $_POST['last_dental'] ?? null,
            'last_eye' => $_POST['last_eye'] ?? null,
            'vaccinations' => [
                'flu' => $_POST['flu_vaccine'] ?? null,
                'covid' => $_POST['covid_vaccine'] ?? null,
                'tetanus' => $_POST['tetanus_vaccine'] ?? null,
                'pneumonia' => $_POST['pneumonia_vaccine'] ?? null
            ],
            'screening_tests' => [
                'mammogram' => $_POST['last_mammogram'] ?? null,
                'colonoscopy' => $_POST['last_colonoscopy'] ?? null,
                'pap_smear' => $_POST['last_pap_smear'] ?? null,
                'prostate' => $_POST['last_prostate'] ?? null
            ]
        ]
    ];

    // Calculate BMI
    if ($health_data['current_health']['height'] > 0 && $health_data['current_health']['weight'] > 0) {
        $height_m = $health_data['current_health']['height'] / 100;
        $health_data['current_health']['bmi'] = round(
            $health_data['current_health']['weight'] / ($height_m * $height_m),
            1
        );
    }

    try {
        // Debug log the health data
        error_log("Saving health data for user ID: " . $user['id']);
        error_log("Health data: " . json_encode($health_data));

        // Update user's health data
        $stmt = $conn->prepare("UPDATE users SET health_data = ?, last_health_update = CURRENT_TIMESTAMP WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $health_data_json = json_encode($health_data);
        if ($health_data_json === false) {
            throw new Exception("JSON encode failed: " . json_last_error_msg());
        }
        
        $stmt->bind_param("si", $health_data_json, $user['id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        // Verify the data was saved
        $verify_stmt = $conn->prepare("SELECT health_data FROM users WHERE id = ?");
        $verify_stmt->bind_param("i", $user['id']);
        $verify_stmt->execute();
        $result = $verify_stmt->get_result();
        $saved_data = $result->fetch_assoc();
        
        if (!$saved_data || !$saved_data['health_data']) {
            throw new Exception("Data verification failed: No data found after save");
        }
        
        error_log("Health data saved successfully for user ID: " . $user['id']);
        $message = "Health assessment completed successfully!";
        header("Location: dashboard.php");
        exit();
    } catch (Exception $e) {
        error_log("Health assessment error: " . $e->getMessage());
        $message = "Error saving health assessment: " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h2>Comprehensive Health Assessment</h2>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-info"><?php echo $message; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <!-- Lifestyle Section -->
                        <h3>Lifestyle</h3>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Smoking Status</label>
                                    <select name="smoking_status" class="form-control">
                                        <option value="never">Never Smoked</option>
                                        <option value="former">Former Smoker</option>
                                        <option value="current">Current Smoker</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Alcohol Consumption</label>
                                    <select name="alcohol_consumption" class="form-control">
                                        <option value="none">None</option>
                                        <option value="occasional">Occasional</option>
                                        <option value="moderate">Moderate</option>
                                        <option value="heavy">Heavy</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Physical Activity Level</label>
                                    <select name="physical_activity" class="form-control">
                                        <option value="sedentary">Sedentary</option>
                                        <option value="light">Light</option>
                                        <option value="moderate">Moderate</option>
                                        <option value="active">Active</option>
                                        <option value="very_active">Very Active</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Exercise Frequency (per week)</label>
                                    <select name="exercise_frequency" class="form-control">
                                        <option value="never">Never</option>
                                        <option value="1-2">1-2 times</option>
                                        <option value="3-4">3-4 times</option>
                                        <option value="5+">5+ times</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Exercise Duration (minutes per session)</label>
                                    <input type="number" name="exercise_duration" class="form-control" min="0" value="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Exercise Types (select all that apply)</label>
                                    <select name="exercise_type[]" class="form-control" multiple>
                                        <option value="cardio">Cardio</option>
                                        <option value="strength">Strength Training</option>
                                        <option value="flexibility">Flexibility</option>
                                        <option value="sports">Sports</option>
                                        <option value="yoga">Yoga/Pilates</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Diet Type</label>
                                    <select name="diet_type" class="form-control">
                                        <option value="regular">Regular</option>
                                        <option value="vegetarian">Vegetarian</option>
                                        <option value="vegan">Vegan</option>
                                        <option value="keto">Keto</option>
                                        <option value="paleo">Paleo</option>
                                        <option value="mediterranean">Mediterranean</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Water Intake (glasses per day)</label>
                                    <input type="number" name="water_intake" class="form-control" min="0" value="8">
                                </div>
                            </div>
                        </div>

                        <!-- Family History Section -->
                        <h3>Family History</h3>
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="checkbox" name="family_diabetes" class="form-check-input" id="family_diabetes">
                                    <label class="form-check-label" for="family_diabetes">Diabetes</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="checkbox" name="family_heart" class="form-check-input" id="family_heart">
                                    <label class="form-check-label" for="family_heart">Heart Disease</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="checkbox" name="family_cancer" class="form-check-input" id="family_cancer">
                                    <label class="form-check-label" for="family_cancer">Cancer</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="checkbox" name="family_hypertension" class="form-check-input" id="family_hypertension">
                                    <label class="form-check-label" for="family_hypertension">High Blood Pressure</label>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="checkbox" name="family_stroke" class="form-check-input" id="family_stroke">
                                    <label class="form-check-label" for="family_stroke">Stroke</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="checkbox" name="family_kidney" class="form-check-input" id="family_kidney">
                                    <label class="form-check-label" for="family_kidney">Kidney Disease</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="checkbox" name="family_thyroid" class="form-check-input" id="family_thyroid">
                                    <label class="form-check-label" for="family_thyroid">Thyroid Disorders</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="checkbox" name="family_autoimmune" class="form-check-input" id="family_autoimmune">
                                    <label class="form-check-label" for="family_autoimmune">Autoimmune Diseases</label>
                                </div>
                            </div>
                        </div>

                        <!-- Current Health Metrics Section -->
                        <h3>Current Health Metrics</h3>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Height (cm)</label>
                                    <input type="number" name="height" class="form-control" step="0.1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Weight (kg)</label>
                                    <input type="number" name="weight" class="form-control" step="0.1" required>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Blood Pressure - Systolic</label>
                                    <input type="number" name="bp_systolic" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Blood Pressure - Diastolic</label>
                                    <input type="number" name="bp_diastolic" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Last Measured</label>
                                    <input type="date" name="bp_last_measured" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Total Cholesterol (mg/dL)</label>
                                    <input type="number" name="cholesterol_total" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>HDL Cholesterol (mg/dL)</label>
                                    <input type="number" name="cholesterol_hdl" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>LDL Cholesterol (mg/dL)</label>
                                    <input type="number" name="cholesterol_ldl" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Last Measured</label>
                                    <input type="date" name="cholesterol_last_measured" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Fasting Blood Sugar (mg/dL)</label>
                                    <input type="number" name="blood_sugar_fasting" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>HbA1c (%)</label>
                                    <input type="number" name="hba1c" class="form-control" step="0.1">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Last Measured</label>
                                    <input type="date" name="blood_sugar_last_measured" class="form-control">
                                </div>
                            </div>
                        </div>

                        <!-- Thyroid Section -->
                        <h4>Thyroid Function</h4>
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>TSH (mIU/L)</label>
                                    <input type="number" name="tsh" class="form-control" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>T3 (pg/mL)</label>
                                    <input type="number" name="t3" class="form-control" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>T4 (ng/dL)</label>
                                    <input type="number" name="t4" class="form-control" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Last Measured</label>
                                    <input type="date" name="thyroid_last_measured" class="form-control">
                                </div>
                            </div>
                        </div>

                        <!-- Vitamin Levels Section -->
                        <h4>Vitamin Levels</h4>
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Vitamin D (ng/mL)</label>
                                    <input type="number" name="vitamin_d" class="form-control" step="0.1">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Vitamin B12 (pg/mL)</label>
                                    <input type="number" name="vitamin_b12" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Iron (µg/dL)</label>
                                    <input type="number" name="iron" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Last Measured</label>
                                    <input type="date" name="vitamins_last_measured" class="form-control">
                                </div>
                            </div>
                        </div>

                        <!-- Mental Health Section -->
                        <h3>Mental Health</h3>
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Stress Level (1-5)</label>
                                    <input type="range" name="stress_level" class="form-range" min="1" max="5" value="3">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Anxiety Level (1-5)</label>
                                    <input type="range" name="anxiety_level" class="form-range" min="1" max="5" value="3">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" name="depression_symptoms" class="form-check-input" id="depression_symptoms">
                                    <label class="form-check-label" for="depression_symptoms">Depression Symptoms</label>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Sleep Quality</label>
                                    <select name="sleep_quality" class="form-control">
                                        <option value="poor">Poor</option>
                                        <option value="fair">Fair</option>
                                        <option value="good">Good</option>
                                        <option value="excellent">Excellent</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Mood Patterns (select all that apply)</label>
                                    <select name="mood_patterns[]" class="form-control" multiple>
                                        <option value="stable">Stable</option>
                                        <option value="fluctuating">Fluctuating</option>
                                        <option value="seasonal">Seasonal Changes</option>
                                        <option value="stress_related">Stress-Related</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Preventive Care Section -->
                        <h3>Preventive Care</h3>
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Last Physical Exam</label>
                                    <input type="date" name="last_physical" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Last Dental Checkup</label>
                                    <input type="date" name="last_dental" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Last Eye Exam</label>
                                    <input type="date" name="last_eye" class="form-control">
                                </div>
                            </div>
                        </div>

                        <h4>Vaccinations</h4>
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Flu Vaccine</label>
                                    <input type="date" name="flu_vaccine" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>COVID-19 Vaccine</label>
                                    <input type="date" name="covid_vaccine" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Tetanus</label>
                                    <input type="date" name="tetanus_vaccine" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Pneumonia</label>
                                    <input type="date" name="pneumonia_vaccine" class="form-control">
                                </div>
                            </div>
                        </div>

                        <h4>Screening Tests</h4>
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Last Mammogram</label>
                                    <input type="date" name="last_mammogram" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Last Colonoscopy</label>
                                    <input type="date" name="last_colonoscopy" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Last Pap Smear</label>
                                    <input type="date" name="last_pap_smear" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Last Prostate Exam</label>
                                    <input type="date" name="last_prostate" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Save Health Assessment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 