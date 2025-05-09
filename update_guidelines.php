<?php
// This script would typically be run via a cron job to update guidelines periodically

// In a real application, this would be an actual API endpoint
$remote_guidelines_url = "https://raw.githubusercontent.com/yourusername/health-calendar/main/data/guidelines.json";

// Local file path
$local_file = __DIR__ . '/data/guidelines.json';

// Function to validate guidelines JSON
function validateGuidelines($data) {
    if (!isset($data['last_updated']) || !isset($data['source']) || !isset($data['guidelines'])) {
        return false;
    }
    
    foreach ($data['guidelines'] as $guideline) {
        $required_fields = ['sex', 'age_min', 'age_max', 'test', 'frequency', 'info_link'];
        foreach ($required_fields as $field) {
            if (!isset($guideline[$field])) {
                return false;
            }
        }
    }
    
    return true;
}

try {
    // In a real application, you might want to add authentication headers
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'Health Calendar Guidelines Updater'
        ]
    ]);
    
    // Fetch remote guidelines
    $remote_data = file_get_contents($remote_guidelines_url, false, $context);
    if ($remote_data === false) {
        throw new Exception("Failed to fetch remote guidelines");
    }
    
    // Decode JSON
    $guidelines = json_decode($remote_data, true);
    if ($guidelines === null) {
        throw new Exception("Invalid JSON format in remote guidelines");
    }
    
    // Validate guidelines structure
    if (!validateGuidelines($guidelines)) {
        throw new Exception("Invalid guidelines structure");
    }
    
    // Update last_updated timestamp
    $guidelines['last_updated'] = date('Y-m-d');
    
    // Save to local file
    if (file_put_contents($local_file, json_encode($guidelines, JSON_PRETTY_PRINT)) === false) {
        throw new Exception("Failed to save guidelines to local file");
    }
    
    echo "Guidelines updated successfully.\n";
    exit(0);
    
} catch (Exception $e) {
    echo "Error updating guidelines: " . $e->getMessage() . "\n";
    
    // If update fails, ensure we have a valid local file
    if (!file_exists($local_file)) {
        // Create default guidelines if local file doesn't exist
        $default_guidelines = [
            "last_updated" => date('Y-m-d'),
            "source" => "Default guidelines (update failed)",
            "guidelines" => [
                [
                    "sex" => "any",
                    "age_min" => 18,
                    "age_max" => 120,
                    "test" => "General Health Checkup",
                    "frequency" => "yearly",
                    "info_link" => "https://www.who.int/health-topics/"
                ]
            ]
        ];
        
        file_put_contents($local_file, json_encode($default_guidelines, JSON_PRETTY_PRINT));
    }
    
    exit(1);
}
?> 