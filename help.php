<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
checkAuth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Documentation - BeeGuard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        details > summary { list-style: none; }
        details > summary::-webkit-details-marker { display: none; }
        details summary .chevron { transition: transform 0.25s ease; }
        details[open] summary .chevron { transform: rotate(90deg); }
        details[open] > .faq-body { animation: fadeInDown 0.2s ease both; }
        @keyframes fadeInDown {
            from { opacity:0; transform:translateY(-8px); }
            to   { opacity:1; transform:translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'sidebar.php'; ?>
    <?php include 'header.php'; ?>

    <main class="min-h-screen">

        <!-- Page Header -->
        <div class="page-header px-8 py-5 flex items-center justify-between pl-10">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                    <span class="w-9 h-9 rounded-xl flex items-center justify-center"
                          style="background:linear-gradient(135deg,#F59E0B,#F97316);box-shadow:0 4px 14px rgba(245,158,11,0.4);">
                        <i class="fas fa-question-circle text-white text-base"></i>
                    </span>
                    Help & Documentation
                </h1>
                <p class="text-gray-500 text-sm mt-0.5">Get support and learn how to use BeeGuard effectively</p>
            </div>
        </div>

        <div class="p-8 space-y-8">

            <!-- FAQ + System Info row -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- FAQ (2/3 width) -->
                <div class="lg:col-span-2 bg-white rounded-xl p-6">
                    <h3 class="font-bold text-lg text-gray-900 mb-5 flex items-center gap-2">
                        <i class="fas fa-circle-question text-yellow-500"></i>
                        Frequently Asked Questions
                    </h3>
                    <div class="space-y-2">

                        <?php
                        $faqs = [
                            // ── System ────────────────────────────────────────────────
                            ['🖥️ &nbsp;What is BeeGuard and how does it work?',
                             'BeeGuard is an IoT-based smart beehive monitoring system. ESP32 microcontrollers attached to each hive collect real-time sensor data (temperature, humidity, CO₂, and sound) and send it to this dashboard over Wi-Fi every 10 seconds. A machine-learning model then analyzes the audio to detect colony health problems automatically.'],

                            ['📊 &nbsp;How often is data collected and displayed?',
                             'Sensor data is posted by the ESP32 every 10 seconds and stored in the database. The dashboard auto-refreshes every 30 seconds to show the latest readings. The ML audio analysis runs every 60 seconds. All historical data is kept and can be exported at any time.'],

                            ['🔔 &nbsp;What kinds of alerts does the system generate?',
                             'BeeGuard generates 12+ alert types including: High/Low Temperature, High Humidity, High CO₂, Queenless Colony, Swarming Detected, Varroa Mite Stress, Robbing Behavior, Environmental Stress, Camera Insect Intrusion, ESP32 Offline, and Low Battery. Each alert has a severity level (info / warning / critical) and is logged with a timestamp per hive.'],

                            ['📷 &nbsp;How does the camera insect detection work?',
                             'A USB webcam streams live video from the hive entrance. A custom-trained YOLOv8 (nano) AI model — trained on 2,000+ bee and insect photos — detects and classifies bees (green box), wasps, hornets, moths, and flies (red box) in real time. If a non-bee insect is detected, an alert is automatically created in the database. The camera runs 24/7 on a separate Python process.'],

                            ['🤖 &nbsp;How accurate is the machine learning model?',
                             'The audio classification CNN achieves approximately 92% accuracy across 6 colony states. The camera insect detector (YOLOv8n) uses a confidence threshold of 0.25 — meaning it only labels a detection when it is at least 25% confident. You can improve accuracy over time by collecting more labeled hive audio samples and retraining via the ML Training page.'],

                            ['💾 &nbsp;What should I do when I receive a critical alert?',
                             'Critical alerts require immediate attention. Open the Alerts page, read the recommended action for that alert type, then physically inspect the hive. For a Queenless alert: look for queen cells or eggs. For Varroa Mite Stress: check bees for deformed wings and perform a sugar roll. For Swarming: inspect for queen cells and consider splitting the hive. Dismiss the alert once resolved.'],

                            // ── Sensors ───────────────────────────────────────────────
                            ['🌡️ &nbsp;What sensors are used in each hive?',
                             'Each hive uses three sensors connected to an ESP32: <br><br>
                             <b>DHT22</b> (GPIO 4) — measures temperature (°C) and relative humidity (%RH) with ±0.5 °C / ±2–5% accuracy.<br>
                             <b>MQ-135</b> (GPIO 34) — measures air quality and CO₂ concentration (ppm). High CO₂ can indicate poor ventilation or overcrowding.<br>
                             <b>MAX4466</b> (GPIO 35) — a microphone amplifier that captures hive audio for colony-state ML analysis.'],

                            ['🌡️ &nbsp;What are the normal temperature and humidity ranges for a beehive?',
                             'A healthy honeybee colony maintains the brood nest at 34–36 °C (93–97 °F) regardless of outside temperature — this is called thermoregulation. Relative humidity in the nest is typically kept at 40–80 %RH. Readings outside these ranges (e.g., temperature below 32 °C or above 38 °C) will trigger a warning or critical alert.'],

                            ['💨 &nbsp;What does the CO₂ sensor measure and why does it matter?',
                             'The MQ-135 sensor measures the concentration of CO₂ and other gases in the air near the hive entrance (in ppm — parts per million). Normal outdoor CO₂ is ~400 ppm. Inside a well-ventilated hive it stays below 3,000 ppm. Readings above 5,000 ppm suggest poor ventilation, overcrowding, or a closed entrance, which can stress the colony and reduce honey production.'],

                            ['🔊 &nbsp;What does the sound sensor detect?',
                             'The MAX4466 microphone captures continuous audio from inside or near the hive. The system extracts MFCC (Mel-Frequency Cepstral Coefficient) features from the audio and passes them to a CNN model trained to recognize 6 colony states: <b>Normal</b>, <b>Queenless</b>, <b>Swarming</b>, <b>Varroa Mite Stress</b>, <b>Environmental Stress</b>, and <b>Robbing Behavior</b>. Each state produces a distinct sound pattern beekeepers can learn to recognize.'],

                            ['📡 &nbsp;What is the ESP32 and how does it connect?',
                             'The ESP32 is a low-cost Wi-Fi + Bluetooth microcontroller that acts as the "brain" of each hive sensor node. It reads the DHT22, MQ-135, and MAX4466 every 10 seconds and sends the data to this dashboard via an HTTP POST request over your local Wi-Fi network. The firmware version is ESP32 v2.4.1. If an ESP32 goes offline (no data received for 5 minutes), an alert is generated automatically.'],

                            ['📤 &nbsp;Can I export the sensor data for research or records?',
                             'Yes. Go to the <b>Export Data</b> page to download sensor readings, ML predictions, and alert history in CSV, JSON, or PDF format. You can filter by hive, sensor type, and date range. This is useful for thesis research, academic analysis, or keeping beekeeping records.'],
                        ];
                        foreach ($faqs as $i => $faq):
                        ?>
                        <details class="border border-gray-100 rounded-xl overflow-hidden group">
                            <summary class="flex items-center gap-3 px-5 py-4 cursor-pointer hover:bg-gray-50 transition-colors select-none">
                                <span class="w-6 h-6 rounded-lg flex items-center justify-center flex-shrink-0 text-xs font-black text-yellow-700"
                                      style="background:#FEF3C7;"><?php echo $i+1; ?></span>
                                <span class="flex-1 font-semibold text-sm text-gray-900"><?php echo $faq[0]; ?></span>
                                <i class="fas fa-chevron-right chevron text-gray-400 text-xs flex-shrink-0"></i>
                            </summary>
                            <div class="faq-body px-5 pb-4 pt-1 text-sm text-gray-600 leading-relaxed border-t border-gray-50">
                                <?php echo $faq[1]; ?>
                            </div>
                        </details>
                        <?php endforeach; ?>

                    </div>
                </div>

                <!-- System Info (1/3 width) -->
                <div class="flex flex-col gap-5">
                    <div class="bg-white rounded-xl p-5">
                        <h3 class="font-bold text-base text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fas fa-info-circle text-blue-500"></i>
                            System Information
                        </h3>
                        <div class="space-y-3">
                            <?php
                            $info = [
                                ['fa-tag', 'text-yellow-600', '#FEF9C3', 'Version', 'BeeGuard v1.0.0'],
                                ['fa-brain', 'text-purple-600', '#F5F3FF', 'ML Model', 'CNN v2.3.1'],
                                ['fa-microchip', 'text-green-600', '#ECFDF5', 'Firmware', 'ESP32 v2.4.1'],
                                ['fa-calendar', 'text-blue-600', '#EFF6FF', 'Last Update', 'May 2026'],
                            ];
                            foreach ($info as [$icon, $color, $bg, $label, $val]):
                            ?>
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                                     style="background:<?php echo $bg; ?>">
                                    <i class="fas <?php echo $icon; ?> text-sm <?php echo $color; ?>"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400"><?php echo $label; ?></p>
                                    <p class="text-sm font-semibold text-gray-800"><?php echo $val; ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Quick links panel -->
                    <div class="bg-white rounded-xl p-5">
                        <h3 class="font-bold text-base text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fas fa-bolt text-yellow-500"></i>
                            Quick Actions
                        </h3>
                        <div class="space-y-2">
                            <?php
                            $links = [
                                ['hives.php',           'fa-layer-group', 'View Live Hives'],
                                ['analytics.php',       'fa-chart-line',  'Open Analytics'],
                                ['alerts.php',          'fa-bell',        'Check Alerts'],
                                ['export.php',          'fa-download',    'Export Data'],
                                ['inspection_notes.php','fa-clipboard',   'Inspection Notes'],
                            ];
                            foreach ($links as [$href, $icon, $label]):
                            ?>
                            <a href="<?php echo $href; ?>"
                               class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-yellow-50 transition-colors group">
                                <i class="fas <?php echo $icon; ?> w-4 text-center text-yellow-500 group-hover:text-yellow-600 text-sm"></i>
                                <span class="text-sm font-medium text-gray-700 group-hover:text-gray-900"><?php echo $label; ?></span>
                                <i class="fas fa-arrow-right text-gray-300 group-hover:text-yellow-400 text-xs ml-auto transition-all group-hover:translate-x-0.5"></i>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Support Banner -->
            <div class="rounded-2xl overflow-hidden relative"
                 style="background:linear-gradient(135deg,#F59E0B 0%,#F97316 100%);box-shadow:0 8px 32px rgba(245,158,11,0.35);">
                <div class="absolute inset-0 opacity-10"
                     style="background-image:radial-gradient(circle at 80% 50%,#fff 0%,transparent 60%);"></div>
                <div class="relative px-8 py-8 flex flex-col sm:flex-row items-center gap-6 justify-between">
                    <div class="flex items-center gap-5">
                        <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-headset text-white text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-black text-white">Need Additional Support?</h3>
                            <p class="text-yellow-100 text-sm mt-0.5">Our team is ready to help you get the most from BeeGuard.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <button class="bg-white text-yellow-600 px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-yellow-50 transition-all shadow">
                            <i class="fas fa-envelope mr-2"></i>Contact Support
                        </button>
                        <button class="border-2 border-white/60 text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-white/10 transition-all">
                            <i class="fas fa-calendar mr-2"></i>Schedule Demo
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </main>
</body>
</html>
