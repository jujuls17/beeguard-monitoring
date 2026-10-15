<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();
header('Content-Type: application/json');

$hives = getAllHives($conn);
echo json_encode([
    'success'        => true,
    'avg_temp'       => getAverageTemperature($conn),
    'avg_co2'        => getAvgCO2($conn),
    'health'         => getHiveHealthScore($conn),
    'healthy'        => countHealthyHives($conn),
    'total_hives'    => count($hives),
    'total_readings' => getTotalReadings($conn),
]);
