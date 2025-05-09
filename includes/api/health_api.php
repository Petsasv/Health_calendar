<?php
class HealthAPI {
    private $conn;
    private $api_keys = [];
    private $cache_dir;
    private $healthdata_api_base = 'https://healthdata.gov/api/3/action/';
    private $who_api_base = 'https://ghoapi.azureedge.net/api/';
    private $cdc_api_base = 'https://data.cdc.gov/resource/';
    private $greek_data_api_base = 'https://data.gov.gr/api/v1/query/';
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->cache_dir = __DIR__ . '/../../data/health_data/cache/';
        if (!file_exists($this->cache_dir)) {
            mkdir($this->cache_dir, 0777, true);
        }
        
        // Load API keys from config
        $config_file = __DIR__ . '/../../config/api_keys.php';
        if (file_exists($config_file)) {
            $this->api_keys = include($config_file);
        }
    }
    
    // Get local health statistics
    public function getLocalHealthStats($location, $country_code = null) {
        // Extract country code from location if not provided
        if (!$country_code) {
            $parts = explode(',', $location);
            if (count($parts) > 1) {
                $country_code = trim(end($parts));
            }
        }

        // If we have a country code, use WHO data for non-US locations
        if ($country_code && strtoupper($country_code) !== 'US' && strtoupper($country_code) !== 'USA') {
            return $this->getWHOHealthStatsByCountry($country_code);
        }

        // For US locations, use HealthData.gov
        $encoded_location = urlencode($location);
        $url = $this->healthdata_api_base . "datastore_search?resource_id=health_indicators&q=" . $encoded_location;
        
        $response = @file_get_contents($url);
        if ($response === false) {
            error_log("Failed to fetch local health stats for location: " . $location);
            return $this->getDefaultLocalStats();
        }
        
        try {
            $cache_file = $this->cache_dir . 'local_stats_' . md5($location) . '.json';
            
            if (file_exists($cache_file) && (time() - filemtime($cache_file) < 86400)) {
                return json_decode(file_get_contents($cache_file), true);
            }

            // Fetch health indicators from HealthData.gov
            $data = json_decode($response, true);

            if (!$data) {
                throw new Exception("Failed to fetch health data");
            }

            $stats = [
                'population_health' => [
                    'hypertension_rate' => $this->calculateDiseaseRate($data, 'hypertension'),
                    'diabetes_rate' => $this->calculateDiseaseRate($data, 'diabetes'),
                    'smoking_rate' => $this->calculateLifestyleRate($data, 'smoking'),
                    'physical_inactivity' => $this->calculateLifestyleRate($data, 'physical_activity')
                ],
                'common_conditions' => $this->getCommonConditions($data),
                'preventive_care' => [
                    'vaccination_rate' => $this->calculateVaccinationRate($data),
                    'screening_rate' => $this->calculateScreeningRate($data)
                ]
            ];

            file_put_contents($cache_file, json_encode($stats));
            return $stats;
        } catch (Exception $e) {
            error_log("HealthData.gov API error: " . $e->getMessage());
            return $this->getDefaultLocalStats();
        }
    }
    
    // Get personalized risk assessment
    public function getRiskAssessment($user_data) {
        $risks = [];
        $recommendations = [];
        $preventive_care = [];
        
        // Calculate BMI if height and weight are provided
        if (!empty($user_data['health_data']['current_health']['height']) && 
            !empty($user_data['health_data']['current_health']['weight'])) {
            $height_m = $user_data['health_data']['current_health']['height'] / 100;
            $bmi = $user_data['health_data']['current_health']['weight'] / ($height_m * $height_m);
            
            // BMI Risk Assessment
            if ($bmi < 18.5) {
                $risks['bmi'] = 'high';
                $recommendations['nutrition'][] = 'underweight';
                $preventive_care[] = [
                    'type' => 'nutrition_consultation',
                    'priority' => 'high',
                    'description' => 'Consult with a nutritionist to develop a healthy weight gain plan'
                ];
            } elseif ($bmi >= 25 && $bmi < 30) {
                $risks['bmi'] = 'medium';
                $recommendations['nutrition'][] = 'overweight';
                $preventive_care[] = [
                    'type' => 'weight_management',
                    'priority' => 'medium',
                    'description' => 'Regular weight monitoring and lifestyle modification'
                ];
            } elseif ($bmi >= 30) {
                $risks['bmi'] = 'high';
                $recommendations['nutrition'][] = 'obese';
                $preventive_care[] = [
                    'type' => 'obesity_screening',
                    'priority' => 'high',
                    'description' => 'Comprehensive health screening for obesity-related conditions'
                ];
            } else {
                $risks['bmi'] = 'low';
            }
        }
        
        // Blood Pressure Risk Assessment
        if (!empty($user_data['health_data']['current_health']['blood_pressure'])) {
            $bp = $user_data['health_data']['current_health']['blood_pressure'];
            if ($bp['systolic'] >= 140 || $bp['diastolic'] >= 90) {
                $risks['blood_pressure'] = 'high';
                $recommendations['screening'][] = 'hypertension';
                $preventive_care[] = [
                    'type' => 'blood_pressure_monitoring',
                    'priority' => 'high',
                    'description' => 'Daily blood pressure monitoring and medical consultation'
                ];
            } elseif ($bp['systolic'] >= 120 || $bp['diastolic'] >= 80) {
                $risks['blood_pressure'] = 'medium';
                $recommendations['lifestyle'][] = 'blood_pressure';
                $preventive_care[] = [
                    'type' => 'blood_pressure_check',
                    'priority' => 'medium',
                    'description' => 'Regular blood pressure monitoring'
                ];
            } else {
                $risks['blood_pressure'] = 'low';
            }
        }
        
        // Cholesterol Risk Assessment
        if (!empty($user_data['health_data']['current_health']['cholesterol'])) {
            $chol = $user_data['health_data']['current_health']['cholesterol'];
            if ($chol['total'] >= 240 || $chol['ldl'] >= 160) {
                $risks['cholesterol'] = 'high';
                $recommendations['screening'][] = 'cholesterol';
                $preventive_care[] = [
                    'type' => 'lipid_panel',
                    'priority' => 'high',
                    'description' => 'Comprehensive lipid panel and medical consultation'
                ];
            } elseif ($chol['total'] >= 200 || $chol['ldl'] >= 130) {
                $risks['cholesterol'] = 'medium';
                $recommendations['lifestyle'][] = 'cholesterol';
                $preventive_care[] = [
                    'type' => 'cholesterol_screening',
                    'priority' => 'medium',
                    'description' => 'Regular cholesterol monitoring'
                ];
            } else {
                $risks['cholesterol'] = 'low';
            }
        }
        
        // Blood Sugar Risk Assessment
        if (!empty($user_data['health_data']['current_health']['blood_sugar'])) {
            $bs = $user_data['health_data']['current_health']['blood_sugar'];
            if ($bs >= 126) {
                $risks['blood_sugar'] = 'high';
                $recommendations['screening'][] = 'diabetes';
                $preventive_care[] = [
                    'type' => 'diabetes_screening',
                    'priority' => 'high',
                    'description' => 'Comprehensive diabetes screening and medical consultation'
                ];
            } elseif ($bs >= 100) {
                $risks['blood_sugar'] = 'medium';
                $recommendations['lifestyle'][] = 'blood_sugar';
                $preventive_care[] = [
                    'type' => 'glucose_monitoring',
                    'priority' => 'medium',
                    'description' => 'Regular blood glucose monitoring'
                ];
            } else {
                $risks['blood_sugar'] = 'low';
            }
        }
        
        // Lifestyle Risk Assessment
        if (!empty($user_data['health_data']['lifestyle'])) {
            $lifestyle = $user_data['health_data']['lifestyle'];
            
            // Smoking
            if ($lifestyle['smoking_status'] === 'current') {
                $risks['smoking'] = 'high';
                $recommendations['lifestyle'][] = 'quit_smoking';
                $preventive_care[] = [
                    'type' => 'smoking_cessation',
                    'priority' => 'high',
                    'description' => 'Smoking cessation program and lung health screening'
                ];
            }
            
            // Physical Activity
            if ($lifestyle['physical_activity'] === 'sedentary') {
                $risks['physical_activity'] = 'high';
                $recommendations['lifestyle'][] = 'increase_activity';
                $preventive_care[] = [
                    'type' => 'physical_activity_assessment',
                    'priority' => 'high',
                    'description' => 'Physical activity assessment and personalized exercise plan'
                ];
            } elseif ($lifestyle['physical_activity'] === 'light') {
                $risks['physical_activity'] = 'medium';
                $recommendations['lifestyle'][] = 'moderate_activity';
                $preventive_care[] = [
                    'type' => 'exercise_consultation',
                    'priority' => 'medium',
                    'description' => 'Exercise consultation for improved activity levels'
                ];
            }
            
            // Sleep
            if ($lifestyle['sleep_hours'] < 6) {
                $risks['sleep'] = 'high';
                $recommendations['lifestyle'][] = 'improve_sleep';
                $preventive_care[] = [
                    'type' => 'sleep_study',
                    'priority' => 'high',
                    'description' => 'Sleep study and consultation for sleep improvement'
                ];
            } elseif ($lifestyle['sleep_hours'] < 7) {
                $risks['sleep'] = 'medium';
                $recommendations['lifestyle'][] = 'adequate_sleep';
                $preventive_care[] = [
                    'type' => 'sleep_consultation',
                    'priority' => 'medium',
                    'description' => 'Sleep consultation for better sleep habits'
                ];
            }
        }
        
        // Mental Health Risk Assessment
        if (!empty($user_data['health_data']['mental_health'])) {
            $mental = $user_data['health_data']['mental_health'];
            
            if ($mental['stress_level'] >= 4 || $mental['anxiety_level'] >= 4) {
                $risks['mental_health'] = 'high';
                $recommendations['mental_health'][] = 'stress_management';
                $preventive_care[] = [
                    'type' => 'mental_health_screening',
                    'priority' => 'high',
                    'description' => 'Comprehensive mental health screening and consultation'
                ];
            } elseif ($mental['stress_level'] >= 3 || $mental['anxiety_level'] >= 3) {
                $risks['mental_health'] = 'medium';
                $recommendations['mental_health'][] = 'mental_wellness';
                $preventive_care[] = [
                    'type' => 'stress_management',
                    'priority' => 'medium',
                    'description' => 'Stress management consultation'
                ];
            }
            
            if ($mental['depression_symptoms']) {
                $risks['depression'] = 'high';
                $recommendations['mental_health'][] = 'depression_screening';
                $preventive_care[] = [
                    'type' => 'depression_screening',
                    'priority' => 'high',
                    'description' => 'Depression screening and mental health consultation'
                ];
            }
        }
        
        // Family History Risk Assessment
        if (!empty($user_data['health_data']['family_history'])) {
            $family = $user_data['health_data']['family_history'];
            
            if ($family['diabetes']) {
                $risks['diabetes'] = 'medium';
                $recommendations['screening'][] = 'diabetes';
                $preventive_care[] = [
                    'type' => 'diabetes_screening',
                    'priority' => 'medium',
                    'description' => 'Regular diabetes screening due to family history'
                ];
            }
            if ($family['heart_disease']) {
                $risks['heart_disease'] = 'medium';
                $recommendations['screening'][] = 'heart_disease';
                $preventive_care[] = [
                    'type' => 'cardiac_screening',
                    'priority' => 'medium',
                    'description' => 'Regular cardiac screening due to family history'
                ];
            }
            if ($family['cancer']) {
                $risks['cancer'] = 'medium';
                $recommendations['screening'][] = 'cancer';
                $preventive_care[] = [
                    'type' => 'cancer_screening',
                    'priority' => 'medium',
                    'description' => 'Regular cancer screening due to family history'
                ];
            }
            if ($family['high_blood_pressure']) {
                $risks['hypertension'] = 'medium';
                $recommendations['screening'][] = 'hypertension';
                $preventive_care[] = [
                    'type' => 'blood_pressure_monitoring',
                    'priority' => 'medium',
                    'description' => 'Regular blood pressure monitoring due to family history'
                ];
            }
        }
        
        // Calculate overall risk
        $high_risks = count(array_filter($risks, function($risk) { return $risk === 'high'; }));
        $medium_risks = count(array_filter($risks, function($risk) { return $risk === 'medium'; }));
        
        if ($high_risks > 0) {
            $overall_risk = 'high';
        } elseif ($medium_risks > 0) {
            $overall_risk = 'medium';
        } else {
            $overall_risk = 'low';
        }
        
        return [
            'general_health' => array_merge($risks, ['overall_risk' => $overall_risk]),
            'preventive_recommendations' => $recommendations,
            'preventive_care' => $preventive_care
        ];
    }
    
    // Get community health insights
    public function getCommunityInsights($location, $age) {
        // Get local stats first
        $local_stats = $this->getLocalHealthStats($location);
        
        // Get WHO stats with both required parameters
        $who_stats = $this->getWHOHealthStats($age, 'male'); // Default to male if not specified
        
        // Ensure we have valid data
        $common_concerns = isset($local_stats['common_conditions']) ? array_keys($local_stats['common_conditions']) : [];
        
        $insights = [
            'preventive_care' => [
                'checkup_rate' => $local_stats['preventive_care']['screening_rate'] ?? 0,
                'screening_rate' => $local_stats['preventive_care']['screening_rate'] ?? 0,
                'vaccination_rate' => $local_stats['preventive_care']['vaccination_rate'] ?? 0
            ],
            'health_comparison' => [
                'local_vs_global' => [
                    'hypertension' => [
                        'local' => $local_stats['population_health']['hypertension_rate'] ?? 0,
                        'global' => $who_stats['global_averages']['mean_blood_pressure'] ?? 0
                    ],
                    'diabetes' => [
                        'local' => $local_stats['population_health']['diabetes_rate'] ?? 0,
                        'global' => $who_stats['global_averages']['mean_blood_glucose'] ?? 0
                    ]
                ]
            ],
            'common_concerns' => $common_concerns
        ];

        return $insights;
    }

    // Get health statistics from HealthData.gov
    public function getHealthDataStats($location, $age_group) {
        $cache_file = $this->cache_dir . 'healthdata_' . md5($location . $age_group) . '.json';
        
        // Check cache first
        if (file_exists($cache_file) && (time() - filemtime($cache_file) < 86400)) {
            return json_decode(file_get_contents($cache_file), true);
        }

        try {
            // Fetch health indicators dataset
            $url = $this->healthdata_api_base . 'datastore_search?resource_id=health_indicators&q=' . urlencode($location);
            $response = file_get_contents($url);
            $data = json_decode($response, true);

            if (!$data || !isset($data['result']['records'])) {
                throw new Exception("Failed to fetch health data");
            }

            // Process and filter data
            $health_stats = [
                'population_health' => [
                    'average_bmi' => $this->getAverageBMI($data['result']['records'], $age_group),
                    'hypertension_rate' => $this->getDiseaseRate($data['result']['records'], 'hypertension', $age_group),
                    'diabetes_rate' => $this->getDiseaseRate($data['result']['records'], 'diabetes', $age_group),
                    'smoking_rate' => $this->getLifestyleRate($data['result']['records'], 'smoking', $age_group),
                    'physical_inactivity' => $this->getLifestyleRate($data['result']['records'], 'physical_inactivity', $age_group)
                ],
                'preventive_care' => [
                    'vaccination_rate' => $this->getVaccinationRate($data['result']['records'], $age_group),
                    'screening_rate' => $this->getScreeningRate($data['result']['records'], $age_group),
                    'checkup_rate' => $this->getCheckupRate($data['result']['records'], $age_group)
                ],
                'lifestyle' => [
                    'active_lifestyle' => $this->getLifestyleRate($data['result']['records'], 'physical_activity', $age_group),
                    'healthy_diet' => $this->getLifestyleRate($data['result']['records'], 'healthy_diet', $age_group),
                    'stress_management' => $this->getLifestyleRate($data['result']['records'], 'stress', $age_group)
                ]
            ];

            // Cache the processed data
            file_put_contents($cache_file, json_encode($health_stats));
            
            return $health_stats;
        } catch (Exception $e) {
            error_log("HealthData.gov API error: " . $e->getMessage());
            return null;
        }
    }

    // Get disease prevalence from HealthData.gov
    public function getDiseasePrevalence($location, $disease_type) {
        $cache_file = $this->cache_dir . 'disease_' . md5($location . $disease_type) . '.json';
        
        if (file_exists($cache_file) && (time() - filemtime($cache_file) < 86400)) {
            return json_decode(file_get_contents($cache_file), true);
        }

        try {
            $url = $this->healthdata_api_base . 'datastore_search?resource_id=disease_prevalence&q=' . 
                   urlencode($location . ' ' . $disease_type);
            $response = file_get_contents($url);
            $data = json_decode($response, true);

            if (!$data || !isset($data['result']['records'])) {
                throw new Exception("Failed to fetch disease data");
            }

            $prevalence_data = [
                'rate' => $this->calculatePrevalenceRate($data['result']['records']),
                'trend' => $this->calculateTrend($data['result']['records']),
                'risk_factors' => $this->getRiskFactors($data['result']['records'])
            ];

            file_put_contents($cache_file, json_encode($prevalence_data));
            
            return $prevalence_data;
        } catch (Exception $e) {
            error_log("Disease prevalence API error: " . $e->getMessage());
            return null;
        }
    }

    // Get preventive care recommendations from HealthData.gov
    public function getPreventiveCareRecommendations($age, $sex, $location) {
        $cache_file = $this->cache_dir . 'preventive_' . md5($age . $sex . $location) . '.json';
        
        if (file_exists($cache_file) && (time() - filemtime($cache_file) < 86400)) {
            return json_decode(file_get_contents($cache_file), true);
        }

        try {
            $url = $this->healthdata_api_base . 'datastore_search?resource_id=preventive_care&q=' . 
                   urlencode("age:$age sex:$sex location:$location");
            $response = file_get_contents($url);
            $data = json_decode($response, true);

            if (!$data || !isset($data['result']['records'])) {
                throw new Exception("Failed to fetch preventive care data");
            }

            $recommendations = [
                'screenings' => $this->getRecommendedScreenings($data['result']['records'], $age, $sex),
                'vaccinations' => $this->getRecommendedVaccinations($data['result']['records'], $age),
                'lifestyle' => $this->getLifestyleRecommendations($data['result']['records'], $age, $sex)
            ];

            file_put_contents($cache_file, json_encode($recommendations));
            
            return $recommendations;
        } catch (Exception $e) {
            error_log("Preventive care API error: " . $e->getMessage());
            return null;
        }
    }

    // Get comprehensive health data from all sources
    public function getComprehensiveHealthData($user_data) {
        $location = $user_data['location'] ?? 'US';
        $age = $user_data['age'] ?? 0;
        $sex = $user_data['sex'] ?? 'unknown';
        
        return [
            'local_stats' => $this->getHealthDataStats($location, $age),
            'global_stats' => $this->getWHOHealthStats($age, $sex),
            'cdc_recommendations' => $this->getCDCRecommendations($age, $sex),
            'comparison' => $this->compareHealthData($user_data)
        ];
    }

    // Get WHO Global Health Statistics
    public function getWHOHealthStats($age, $sex = 'male') {
        // Add default value for sex parameter
        if (empty($sex)) {
            $sex = 'male';
        }
        
        $cache_file = $this->cache_dir . '/who_stats_' . $age . '_' . $sex . '.json';
        
        // Check cache first
        if (file_exists($cache_file) && (time() - filemtime($cache_file) < 86400)) {
            return json_decode(file_get_contents($cache_file), true);
        }
        
        try {
            // Define health indicators to fetch
            $indicators = [
                'life_expectancy' => 'WHOSIS_000001',
                'mean_bmi' => 'NCD_BMI_MEAN',
                'mean_blood_pressure' => 'NCD_BP_MEAN',
                'mean_blood_glucose' => 'NCD_GLUC_MEAN',
                'mean_alcohol' => 'SA_0000001400',
                'mean_tobacco' => 'TOBACCO_USE'
            ];
            
            $who_data = [];
            $global_averages = [];
            
            // Process indicators in chunks to reduce memory usage
            foreach (array_chunk($indicators, 2, true) as $chunk) {
                foreach ($chunk as $indicator_name => $indicator_code) {
                    $url = "https://ghoapi.azureedge.net/api/{$indicator_code}";
                    $response = $this->makeAPICall($url);
                    
                    if ($response) {
                        $data = json_decode($response, true);
                        if (isset($data['value'])) {
                            // Process data in chunks
                            foreach (array_chunk($data['value'], 100) as $data_chunk) {
                                $processed_data = $this->processWHOData($data_chunk, $age, $sex);
                                if ($processed_data) {
                                    $who_data[$indicator_name] = $processed_data;
                                    $global_averages[$indicator_name] = $this->getGlobalAverage($processed_data);
                                }
                            }
                        }
                    }
                    
                    // Clear memory after processing each indicator
                    unset($data);
                    unset($response);
                    gc_collect_cycles();
                }
            }
            
            $result = [
                'who_data' => $who_data,
                'global_averages' => $global_averages
            ];
            
            // Save to cache
            file_put_contents($cache_file, json_encode($result));
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Error fetching WHO data: " . $e->getMessage());
            return [
                'who_data' => [],
                'global_averages' => [
                    'bmi' => 0,
                    'blood_pressure' => 0,
                    'physical_activity' => 0,
                    'tobacco' => 0
                ]
            ];
        }
    }

    // Get CDC Health Recommendations
    public function getCDCRecommendations($age, $sex) {
        $cache_file = $this->cache_dir . 'cdc_' . md5($age . $sex) . '.json';
        
        if (file_exists($cache_file) && (time() - filemtime($cache_file) < 86400)) {
            return json_decode(file_get_contents($cache_file), true);
        }

        try {
            // Fetch CDC preventive care guidelines
            $url = $this->cdc_api_base . 'preventive-care-guidelines.json';
            $response = file_get_contents($url);
            $data = json_decode($response, true);

            if (!$data) {
                throw new Exception("Failed to fetch CDC data");
            }

            $recommendations = [
                'screenings' => $this->filterCDCScreenings($data, $age, $sex),
                'vaccinations' => $this->filterCDCVaccinations($data, $age),
                'lifestyle' => $this->filterCDCLifestyle($data, $age, $sex),
                'preventive_services' => $this->getPreventiveServices($data, $age, $sex)
            ];

            // Add age-specific recommendations
            $recommendations['age_specific'] = [
                'screenings' => $this->getAgeSpecificScreenings($age, $sex),
                'vaccinations' => $this->getAgeSpecificVaccinations($age),
                'lifestyle' => $this->getAgeSpecificLifestyle($age, $sex)
            ];

            file_put_contents($cache_file, json_encode($recommendations));
            return $recommendations;
        } catch (Exception $e) {
            error_log("CDC API error: " . $e->getMessage());
            return null;
        }
    }

    // Compare user health data with population data
    private function compareHealthData($user_data) {
        $comparison = [];
        
        // BMI Comparison
        if (isset($user_data['health_data']['current_health']['bmi'])) {
            $user_bmi = $user_data['health_data']['current_health']['bmi'];
            $comparison['bmi'] = [
                'user_value' => $user_bmi,
                'local_average' => $this->getHealthDataStats($user_data['location'], $user_data['age'])['population_health']['average_bmi'],
                'global_average' => $this->getWHOHealthStats($user_data['age'], $user_data['sex'])['NCD_BMI_MEAN'],
                'status' => $this->getBMICategory($user_bmi)
            ];
        }

        // Blood Pressure Comparison
        if (isset($user_data['health_data']['current_health']['blood_pressure'])) {
            $bp = $user_data['health_data']['current_health']['blood_pressure'];
            $comparison['blood_pressure'] = [
                'user_value' => $bp,
                'local_average' => $this->getHealthDataStats($user_data['location'], $user_data['age'])['population_health']['hypertension_rate'],
                'global_average' => $this->getWHOHealthStats($user_data['age'], $user_data['sex'])['NCD_BP_MEAN'],
                'status' => $this->getBPCategory($bp)
            ];
        }

        // Lifestyle Comparison
        if (isset($user_data['health_data']['lifestyle'])) {
            $lifestyle = $user_data['health_data']['lifestyle'];
            $comparison['lifestyle'] = [
                'physical_activity' => $this->comparePhysicalActivity($lifestyle),
                'diet' => $this->compareDiet($lifestyle),
                'sleep' => $this->compareSleep($lifestyle)
            ];
        }

        return $comparison;
    }

    // Helper methods for data processing
    private function getAverageBMI($records, $age_group) {
        // Implementation to calculate average BMI from records
        return 26.5; // Placeholder
    }

    private function getDiseaseRate($records, $disease, $age_group) {
        // Implementation to calculate disease rate from records
        return 15; // Placeholder
    }

    private function getLifestyleRate($records, $factor, $age_group) {
        // Implementation to calculate lifestyle factor rate from records
        return 25; // Placeholder
    }

    private function getVaccinationRate($records, $age_group) {
        // Implementation to calculate vaccination rate from records
        return 85; // Placeholder
    }

    private function getScreeningRate($records, $age_group) {
        // Implementation to calculate screening rate from records
        return 72; // Placeholder
    }

    private function getCheckupRate($records, $age_group) {
        // Implementation to calculate checkup rate from records
        return 68; // Placeholder
    }

    private function calculatePrevalenceRate($records) {
        // Implementation to calculate disease prevalence rate
        return 10; // Placeholder
    }

    private function calculateTrend($records) {
        // Implementation to calculate disease trend
        return 'stable'; // Placeholder
    }

    private function getRiskFactors($records) {
        // Implementation to get risk factors
        return ['age', 'lifestyle', 'genetics']; // Placeholder
    }

    private function getRecommendedScreenings($records, $age, $sex) {
        // Implementation to get recommended screenings
        return [
            ['type' => 'blood_pressure', 'frequency' => 'annual'],
            ['type' => 'cholesterol', 'frequency' => 'every_5_years']
        ]; // Placeholder
    }

    private function getRecommendedVaccinations($records, $age) {
        // Implementation to get recommended vaccinations
        return [
            ['type' => 'flu', 'frequency' => 'annual'],
            ['type' => 'tetanus', 'frequency' => 'every_10_years']
        ]; // Placeholder
    }

    private function getLifestyleRecommendations($records, $age, $sex) {
        // Implementation to get lifestyle recommendations
        return [
            ['type' => 'exercise', 'recommendation' => '30 minutes daily'],
            ['type' => 'diet', 'recommendation' => 'balanced diet']
        ]; // Placeholder
    }

    private function processWHOData($data, $age, $sex) {
        if (empty($data)) return null;
        
        $processed = [];
        $age_group = $this->getAgeGroup($age);
        
        foreach ($data as $record) {
            if (isset($record['SpatialDim']) && isset($record['TimeDim']) && isset($record['NumericValue'])) {
                $key = $record['SpatialDim'] . '_' . $record['TimeDim'];
                $processed[$key] = [
                    'value' => floatval($record['NumericValue']),
                    'year' => $record['TimeDim'],
                    'country' => $record['SpatialDim'],
                    'age_group' => $age_group,
                    'sex' => $sex
                ];
            }
        }
        
        return $processed;
    }

    private function getGlobalAverage($data) {
        if (empty($data)) return 0;
        
        $sum = 0;
        $count = 0;
        
        foreach ($data as $record) {
            if (isset($record['value'])) {
                $sum += $record['value'];
                $count++;
            }
        }
        
        return $count > 0 ? $sum / $count : 0;
    }

    private function getAgeGroup($age) {
        if ($age < 18) return '0-17';
        if ($age < 35) return '18-34';
        if ($age < 50) return '35-49';
        if ($age < 65) return '50-64';
        return '65+';
    }

    private function filterCDCScreenings($data, $age, $sex) {
        // Filter CDC screening recommendations based on age and sex
        return array_filter($data, function($item) use ($age, $sex) {
            return $this->isInAgeRange($age, $item['age_range']) && 
                   $this->isApplicableToSex($sex, $item['sex']);
        });
    }

    private function filterCDCVaccinations($data, $age) {
        // Filter CDC vaccination recommendations based on age
        return array_filter($data, function($item) use ($age) {
            return $this->isInAgeRange($age, $item['age_range']);
        });
    }

    private function filterCDCLifestyle($data, $age, $sex) {
        // Filter CDC lifestyle recommendations based on age and sex
        return array_filter($data, function($item) use ($age, $sex) {
            return $this->isInAgeRange($age, $item['age_range']) && 
                   $this->isApplicableToSex($sex, $item['sex']);
        });
    }

    private function getBMICategory($bmi) {
        if ($bmi < 18.5) return 'underweight';
        if ($bmi < 25) return 'normal';
        if ($bmi < 30) return 'overweight';
        return 'obese';
    }

    private function getBPCategory($bp) {
        if ($bp['systolic'] < 120 && $bp['diastolic'] < 80) return 'normal';
        if ($bp['systolic'] < 130 && $bp['diastolic'] < 80) return 'elevated';
        if ($bp['systolic'] < 140 && $bp['diastolic'] < 90) return 'stage1';
        return 'stage2';
    }

    private function comparePhysicalActivity($lifestyle) {
        $activity_levels = [
            'sedentary' => 0,
            'light' => 1,
            'moderate' => 2,
            'active' => 3,
            'very_active' => 4
        ];
        
        return [
            'user_level' => $lifestyle['physical_activity'],
            'recommended' => 'moderate',
            'comparison' => $activity_levels[$lifestyle['physical_activity']] >= $activity_levels['moderate'] ? 'good' : 'needs_improvement'
        ];
    }

    private function compareDiet($lifestyle) {
        return [
            'user_type' => $lifestyle['diet_type'],
            'recommended' => 'mediterranean',
            'comparison' => $lifestyle['diet_type'] === 'mediterranean' ? 'good' : 'consider_change'
        ];
    }

    private function compareSleep($lifestyle) {
        $sleep_hours = $lifestyle['sleep_hours'];
        return [
            'user_hours' => $sleep_hours,
            'recommended' => '7-9',
            'comparison' => ($sleep_hours >= 7 && $sleep_hours <= 9) ? 'good' : 'needs_improvement'
        ];
    }

    private function isInAgeRange($age, $range) {
        list($min, $max) = explode('-', $range);
        return $age >= $min && $age <= $max;
    }

    private function isApplicableToSex($sex, $applicable_sex) {
        return $applicable_sex === 'all' || $applicable_sex === $sex;
    }

    private function getPreventiveServices($data, $age, $sex) {
        $services = [];
        foreach ($data as $service) {
            if ($this->isInAgeRange($age, $service['age_range']) && 
                $this->isApplicableToSex($sex, $service['sex'])) {
                $services[] = [
                    'name' => $service['name'],
                    'frequency' => $service['frequency'],
                    'description' => $service['description'],
                    'priority' => $service['priority'] ?? 'medium'
                ];
            }
        }
        return $services;
    }

    private function getAgeSpecificScreenings($age, $sex) {
        $screenings = [];
        
        // Add age and sex-specific screenings
        if ($age >= 50) {
            $screenings[] = [
                'name' => 'Colonoscopy',
                'frequency' => 'every 10 years',
                'priority' => 'high'
            ];
        }
        
        if ($sex === 'female' && $age >= 40) {
            $screenings[] = [
                'name' => 'Mammogram',
                'frequency' => 'every 1-2 years',
                'priority' => 'high'
            ];
        }
        
        if ($age >= 65) {
            $screenings[] = [
                'name' => 'Bone density test',
                'frequency' => 'every 2 years',
                'priority' => 'medium'
            ];
        }
        
        return $screenings;
    }

    private function getAgeSpecificVaccinations($age) {
        $vaccinations = [];
        
        // Add age-specific vaccinations
        if ($age >= 65) {
            $vaccinations[] = [
                'name' => 'Pneumococcal vaccine',
                'frequency' => 'one-time',
                'priority' => 'high'
            ];
        }
        
        if ($age >= 50) {
            $vaccinations[] = [
                'name' => 'Shingles vaccine',
                'frequency' => 'one-time',
                'priority' => 'high'
            ];
        }
        
        return $vaccinations;
    }

    private function getAgeSpecificLifestyle($age, $sex) {
        $lifestyle = [];
        
        // Add age and sex-specific lifestyle recommendations
        if ($age >= 50) {
            $lifestyle[] = [
                'name' => 'Regular physical activity',
                'recommendation' => '150 minutes of moderate activity per week',
                'priority' => 'high'
            ];
        }
        
        if ($sex === 'female' && $age >= 40) {
            $lifestyle[] = [
                'name' => 'Calcium and vitamin D',
                'recommendation' => 'Daily supplements as recommended by healthcare provider',
                'priority' => 'medium'
            ];
        }
        
        return $lifestyle;
    }

    private function makeAPICall($url, $timeout = 30) {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => $timeout,
                    'header' => [
                        'User-Agent: HealthCalendar/1.0',
                        'Accept: application/json'
                    ]
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                error_log("API call failed for URL: " . $url);
                return null;
            }
            
            return $response;
        } catch (Exception $e) {
            error_log("API call error: " . $e->getMessage());
            return null;
        }
    }

    private function getDefaultLocalStats() {
        return [
            'population_health' => [
                'hypertension_rate' => 30.0,
                'diabetes_rate' => 10.0,
                'smoking_rate' => 15.0,
                'physical_inactivity' => 25.0,
                'average_bmi' => 26.5
            ],
            'preventive_care' => [
                'vaccination_rate' => 75.0,
                'screening_rate' => 65.0
            ],
            'common_conditions' => [
                'Hypertension' => 30.0,
                'Diabetes' => 10.0,
                'Heart Disease' => 8.0,
                'Asthma' => 7.0
            ]
        ];
    }

    private function getWHOHealthStatsByCountry($country_code) {
        $cache_file = $this->cache_dir . 'who_country_' . $country_code . '.json';
        
        if (file_exists($cache_file) && (time() - filemtime($cache_file) < 86400)) {
            return json_decode(file_get_contents($cache_file), true);
        }

        try {
            $indicators = [
                'life_expectancy' => 'WHOSIS_000001',
                'hypertension' => 'NCD_BP_MEAN',
                'diabetes' => 'NCD_GLUC_MEAN',
                'obesity' => 'NCD_BMI_MEAN',
                'smoking' => 'TOBACCO_USE'
            ];

            $stats = [];
            foreach ($indicators as $key => $indicator) {
                $url = $this->who_api_base . $indicator . "?$filter=SpatialDim eq '" . $country_code . "'";
                $response = $this->makeAPICall($url);
                
                if ($response) {
                    $data = json_decode($response, true);
                    if (isset($data['value'][0]['NumericValue'])) {
                        $stats[$key] = $data['value'][0]['NumericValue'];
                    }
                }
            }

            $result = [
                'population_health' => [
                    'hypertension_rate' => $stats['hypertension'] ?? 0,
                    'diabetes_rate' => $stats['diabetes'] ?? 0,
                    'smoking_rate' => $stats['smoking'] ?? 0,
                    'physical_inactivity' => 0, // WHO doesn't provide this directly
                    'average_bmi' => $stats['obesity'] ?? 0
                ],
                'preventive_care' => [
                    'vaccination_rate' => 0, // WHO doesn't provide this directly
                    'screening_rate' => 0
                ],
                'common_conditions' => [
                    'Hypertension' => $stats['hypertension'] ?? 0,
                    'Diabetes' => $stats['diabetes'] ?? 0,
                    'Obesity' => $stats['obesity'] ?? 0
                ]
            ];

            file_put_contents($cache_file, json_encode($result));
            return $result;

        } catch (Exception $e) {
            error_log("Error fetching WHO country data: " . $e->getMessage());
            return $this->getDefaultLocalStats();
        }
    }

    // Get Greek health statistics
    public function getGreekHealthStats($date_from = null, $date_to = null) {
        if (!$date_from) {
            $date_from = date('Y-m-d', strtotime('-1 year'));
        }
        if (!$date_to) {
            $date_to = date('Y-m-d');
        }

        $cache_file = $this->cache_dir . 'greek_stats_' . md5($date_from . $date_to) . '.json';
        
        if (file_exists($cache_file) && (time() - filemtime($cache_file) < 86400)) {
            return json_decode(file_get_contents($cache_file), true);
        }

        try {
            // Example for vaccination data
            $vaccination_data = $this->getGreekData('mdg_emvolio', [
                'date_from' => $date_from,
                'date_to' => $date_to
            ]);

            // Process the data
            $stats = [
                'vaccination' => $this->processGreekVaccinationData($vaccination_data),
                // Add more data types as they become available
            ];

            file_put_contents($cache_file, json_encode($stats));
            return $stats;

        } catch (Exception $e) {
            error_log("Greek health data API error: " . $e->getMessage());
            return $this->getDefaultGreekStats();
        }
    }

    private function getGreekData($endpoint, $params) {
        $url = $this->greek_data_api_base . $endpoint;
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Accept: application/json',
                    'User-Agent: HealthCalendar/1.0'
                ]
            ]
        ]);

        $response = @file_get_contents($url . '?' . http_build_query($params), false, $context);
        
        if ($response === false) {
            throw new Exception("Failed to fetch data from Greek API");
        }

        return json_decode($response, true);
    }

    private function processGreekVaccinationData($data) {
        if (empty($data)) {
            return [];
        }

        $processed = [
            'total_vaccinations' => 0,
            'daily_vaccinations' => [],
            'by_region' => []
        ];

        foreach ($data as $record) {
            // Process based on actual data structure
            // This will need to be adjusted based on the actual API response
            if (isset($record['total_doses'])) {
                $processed['total_vaccinations'] += $record['total_doses'];
            }
            
            if (isset($record['date']) && isset($record['doses'])) {
                $processed['daily_vaccinations'][$record['date']] = $record['doses'];
            }
            
            if (isset($record['region']) && isset($record['doses'])) {
                if (!isset($processed['by_region'][$record['region']])) {
                    $processed['by_region'][$record['region']] = 0;
                }
                $processed['by_region'][$record['region']] += $record['doses'];
            }
        }

        return $processed;
    }

    private function getDefaultGreekStats() {
        return [
            'vaccination' => [
                'total_vaccinations' => 0,
                'daily_vaccinations' => [],
                'by_region' => []
            ]
        ];
    }

    private function processGreekCSVData($csv_file) {
        if (!file_exists($csv_file)) {
            throw new Exception("CSV file not found: " . $csv_file);
        }

        $data = [];
        $headers = [];
        $row = 1;

        if (($handle = fopen($csv_file, "r")) !== FALSE) {
            while (($csv_data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if ($row === 1) {
                    // First row contains headers
                    $headers = $csv_data;
                } else {
                    // Process data rows
                    $row_data = [];
                    foreach ($headers as $index => $header) {
                        $row_data[$header] = $csv_data[$index] ?? null;
                    }
                    $data[] = $row_data;
                }
                $row++;
            }
            fclose($handle);
        }

        return $data;
    }

    public function importGreekCSVData($csv_file, $data_type) {
        try {
            $data = $this->processGreekCSVData($csv_file);
            
            // Cache the processed data
            $cache_file = $this->cache_dir . 'greek_csv_' . md5($csv_file) . '.json';
            file_put_contents($cache_file, json_encode($data));
            
            return [
                'status' => 'success',
                'records_processed' => count($data),
                'data_type' => $data_type,
                'data' => $data
            ];
        } catch (Exception $e) {
            error_log("Error processing Greek CSV data: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}
?> 