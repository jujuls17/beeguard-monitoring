<?php
// Smart recommendation system based on sensor data and colony status

function getHiveRecommendations($sensor_data, $audio_analysis) {
    $recommendations = [];
    
    if (!$sensor_data) {
        return [
            ['type' => 'warning', 'icon' => 'fa-plug', 'title' => 'No Sensor Data', 'message' => 'ESP32 not yet connected.', 'actions' => ['Check sensor connectivity', 'Verify ESP32 is powered on', 'Check network connection']],
        ];
    }
    
    // Temperature Recommendations — Paper spec: Optimal 33–36°C
    $temp = $sensor_data['temperature'];
    if ($temp > 38) {
        $recommendations[] = [
            'type' => 'critical',
            'icon' => 'fa-thermometer-full',
            'title' => 'HIGH TEMPERATURE ALERT',
            'message' => "Temperature is {$temp}°C (Critical: >38°C, Optimal: 33–36°C)",
            'actions' => [
                'Immediately check hive ventilation',
                'Ensure adequate shade covering',
                'Add ventilation holes if needed',
                'Check for overcrowding',
                'Monitor for 30 minutes after intervention'
            ]
        ];
    } elseif ($temp > 36) {
        $recommendations[] = [
            'type' => 'warning',
            'icon' => 'fa-thermometer-half',
            'title' => 'Temperature Above Optimal',
            'message' => "Temperature is {$temp}°C (Optimal: 33–36°C)",
            'actions' => [
                'Improve ventilation gradually',
                'Check hive orientation (facing east preferred)',
                'Consider adding shade cloth',
                'Monitor temperature trend over next 2 hours'
            ]
        ];
    } elseif ($temp < 18) {
        $recommendations[] = [
            'type' => 'critical',
            'icon' => 'fa-snowflake',
            'title' => 'CRITICAL: Temperature Dangerously Low',
            'message' => "Temperature is {$temp}°C (Critical: <18°C, Optimal: 33–36°C)",
            'actions' => [
                'Insulate the hive immediately',
                'Reduce entrance to a 1-bee gap to retain heat',
                'Verify cluster size — consider combining weak colonies',
                'Check honey stores for winter survival'
            ]
        ];
    } elseif ($temp < 33) {
        $recommendations[] = [
            'type' => 'warning',
            'icon' => 'fa-snowflake',
            'title' => 'Temperature Below Optimal',
            'message' => "Temperature is {$temp}°C (Optimal: 33–36°C)",
            'actions' => [
                'Check for gaps in hive body',
                'Reduce ventilation openings',
                'Add insulation if weather is cold',
                'Check colony strength (weak colonies cannot maintain heat)',
                'Consider combining with stronger colony if very weak'
            ]
        ];
    }

    // Humidity Recommendations — Paper spec: Optimal 40–95%
    $humidity = $sensor_data['humidity'];
    if ($humidity > 98) {
        $recommendations[] = [
            'type' => 'critical',
            'icon' => 'fa-tint',
            'title' => 'CRITICAL: Humidity Dangerously High',
            'message' => "Humidity is {$humidity}% (Critical: >98%, Optimal: 40–95%)",
            'actions' => [
                'Open all ventilation immediately',
                'Check for water ingress or roof leaks',
                'Inspect brood frames for chalkbrood or nosema',
                'Remove entrance reducer to improve airflow'
            ]
        ];
    } elseif ($humidity > 95) {
        $recommendations[] = [
            'type' => 'warning',
            'icon' => 'fa-tint',
            'title' => 'High Humidity Detected',
            'message' => "Humidity is {$humidity}% (Optimal: 40–95%)",
            'actions' => [
                'Increase ventilation to reduce moisture',
                'Check for water leaks in hive roof',
                'Ensure bottom board drainage is clear',
                'Tilt hive slightly forward for water runoff'
            ]
        ];
    } elseif ($humidity < 30) {
        $recommendations[] = [
            'type' => 'critical',
            'icon' => 'fa-tint-slash',
            'title' => 'CRITICAL: Humidity Dangerously Low',
            'message' => "Humidity is {$humidity}% (Critical: <30%, Optimal: 40–95%)",
            'actions' => [
                'Provide a water source immediately within 50m',
                'Place a boardman feeder with clean water at entrance',
                'Check for severe nectar dearth'
            ]
        ];
    } elseif ($humidity < 40) {
        $recommendations[] = [
            'type' => 'warning',
            'icon' => 'fa-tint-slash',
            'title' => 'Low Humidity',
            'message' => "Humidity is {$humidity}% (Optimal: 40–95%)",
            'actions' => [
                'Ensure a water source is accessible within 50m',
                'Monitor nectar curing — bees need humidity to ripen honey',
                'Reduce excessive ventilation if present'
            ]
        ];
    }

    // CO2 Recommendations — Inside-hive reference: Normal ≤4000ppm
    $co2 = $sensor_data['co2'];
    if ($co2 > 6000) {
        $recommendations[] = [
            'type' => 'critical',
            'icon' => 'fa-wind',
            'title' => 'CRITICAL: Severe Ventilation Failure',
            'message' => "CO₂ level is {$co2}ppm (Critical: >6000ppm, Normal: ≤4000ppm)",
            'actions' => [
                'URGENT: Open all entrances and ventilation slots immediately',
                'Remove entrance reducer completely',
                'Inspect for overcrowding — consider adding a super or splitting',
                'CO₂ this high can suffocate brood'
            ]
        ];
    } elseif ($co2 > 4000) {
        $recommendations[] = [
            'type' => 'warning',
            'icon' => 'fa-wind',
            'title' => 'Elevated CO₂ Levels',
            'message' => "CO₂ level is {$co2}ppm (Elevated: >4000ppm, Normal: ≤4000ppm)",
            'actions' => [
                'Increase ventilation openings',
                'Check entrance reducer — may be too small for colony size',
                'Inspect for overcrowding',
                'Add upper ventilation if needed'
            ]
        ];
    }
    
    // Colony Status Recommendations (from Audio Analysis)
    if ($audio_analysis) {
        $status = $audio_analysis['colony_status'];
        $confidence = $audio_analysis['confidence'];
        
        switch ($status) {
            case 'queenless':
                $recommendations[] = [
                    'type' => 'critical',
                    'icon' => 'fa-crown',
                    'title' => 'QUEENLESS COLONY DETECTED',
                    'message' => "Audio analysis shows queenless behavior ({$confidence}% confidence)",
                    'actions' => [
                        'URGENT: Verify by visual inspection within 24 hours',
                        'Look for eggs and young larvae (confirms queen present)',
                        'If queenless: Install new queen within 3 days',
                        'Option 1: Introduce mated queen in cage',
                        'Option 2: Add frame with eggs from strong colony',
                        'Listen for high-pitched roaring sound (confirms queenless)',
                        'Do NOT delay - colony will fail without queen'
                    ]
                ];
                break;
                
            case 'swarming':
                $recommendations[] = [
                    'type' => 'warning',
                    'icon' => 'fa-exclamation-triangle',
                    'title' => 'Swarming Preparation Detected',
                    'message' => "Colony showing pre-swarm behavior ({$confidence}% confidence)",
                    'actions' => [
                        'Inspect for queen cells within 48 hours',
                        'Add honey super to give bees more space',
                        'Consider split to prevent swarm loss',
                        'Ensure adequate ventilation',
                        'Remove queen cells if swarm not desired',
                        'Check weekly until behavior normalizes',
                        'Peak swarming season: April-June'
                    ]
                ];
                break;
                
            case 'varroa_mite':
                $recommendations[] = [
                    'type' => 'critical',
                    'icon' => 'fa-bug',
                    'title' => 'PEST INFESTATION SUSPECTED',
                    'message' => "Possible Varroa mite infestation ({$confidence}% confidence)",
                    'actions' => [
                        'URGENT: Perform alcohol wash or sugar shake test',
                        'Count mites - over 3 per 100 bees requires treatment',
                        'Treatment options:',
                        '  • Formic acid strips (MAQS)',
                        '  • Oxalic acid vaporization',
                        '  • Apivar strips (amitraz)',
                        'Remove honey supers before treatment',
                        'Monitor mite levels every 2 weeks',
                        'Unchecked mites will kill colony in months'
                    ]
                ];
                break;
                
            case 'environmental_stress':
                $recommendations[] = [
                    'type' => 'warning',
                    'icon' => 'fa-exclamation-circle',
                    'title' => 'Colony Under Stress',
                    'message' => "Environmental stress detected ({$confidence}% confidence)",
                    'actions' => [
                        'Check temperature and humidity levels',
                        'Inspect for pests or diseases',
                        'Verify water source is nearby (within 100m)',
                        'Check for pesticide exposure in area',
                        'Reduce inspections to minimize disturbance',
                        'Monitor improvement over next 7 days'
                    ]
                ];
                break;
                
            case 'robbing':
                $recommendations[] = [
                    'type' => 'warning',
                    'icon' => 'fa-shield-alt',
                    'title' => 'Robbing Behavior Detected',
                    'message' => "Colony may be under attack ({$confidence}% confidence)",
                    'actions' => [
                        'IMMEDIATE: Reduce entrance to smallest opening',
                        'Look for fighting bees at entrance',
                        'Robber bees have shiny, hairless appearance',
                        'Stop all feeding immediately',
                        'Apply wet grass/leaves at entrance (temporary)',
                        'Move hive only if severe (last resort)',
                        'Weak colonies most vulnerable - combine if needed',
                        'Monitor until robbing stops'
                    ]
                ];
                break;
                
            case 'normal':
                if ($confidence > 85) {
                    $recommendations[] = [
                        'type' => 'success',
                        'icon' => 'fa-check-circle',
                        'title' => 'Healthy Colony',
                        'message' => "Colony showing normal behavior ({$confidence}% confidence)",
                        'actions' => [
                            'Colony is healthy - maintain current management',
                            'Continue regular inspections every 7-10 days',
                            'Monitor for seasonal changes',
                            'Keep records of hive conditions'
                        ]
                    ];
                }
                break;
        }
    }
    
    // No issues found
    if (empty($recommendations)) {
        $recommendations[] = [
            'type' => 'success',
            'icon' => 'fa-check-circle',
            'title' => 'All Parameters Normal',
            'message' => 'Hive conditions are within optimal range',
            'actions' => [
                'Continue monitoring',
                'Maintain regular inspection schedule',
                'All systems operating normally'
            ]
        ];
    }
    
    return $recommendations;
}
?>
