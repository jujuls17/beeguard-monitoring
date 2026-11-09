<?php
ob_start();
ini_set('display_errors', 0);

session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();
session_write_close();

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
ob_clean();

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
