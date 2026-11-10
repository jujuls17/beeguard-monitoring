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
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
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
        .range-ok   { background:#ECFDF5; color:#065F46; }
        .range-warn { background:#FFFBEB; color:#92400E; }
        .range-crit { background:#FEF2F2; color:#991B1B; }
        .range-cell { padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600; white-space:nowrap; }
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
                <p class="text-gray-500 text-sm mt-0.5">System reference, sensor thresholds, and frequently asked questions</p>
            </div>
        </div>

        <div class="p-8 space-y-8">

            <!-- ── Sensor Reference Table ──────────────────────────────── -->
            <div class="bg-white rounded-xl p-6">
                <h3 class="font-bold text-lg text-gray-900 mb-1 flex items-center gap-2">
                    <i class="fas fa-sliders text-amber-500"></i>
                    Sensor Optimal Ranges &amp; Alert Thresholds
                </h3>
                <p class="text-sm text-gray-500 mb-5">These are the exact thresholds BeeGuard uses to generate warnings and critical alerts. Based on standard honeybee colony biology research.</p>

                <!-- Temperature -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center bg-orange-50">
                            <i class="fas fa-thermometer-half text-orange-500 text-sm"></i>
                        </span>
                        <h4 class="font-semibold text-gray-800">Temperature (DHT22 · GPIO 4)</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                                    <th class="text-left px-4 py-2.5 font-semibold rounded-tl-lg">Range</th>
                                    <th class="text-left px-4 py-2.5 font-semibold">Status</th>
                                    <th class="text-left px-4 py-2.5 font-semibold rounded-tr-lg">What It Means</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <td class="px-4 py-3 font-mono font-semibold text-green-700">33 – 36 °C</td>
                                    <td class="px-4 py-3"><span class="range-cell range-ok">✓ Optimal</span></td>
                                    <td class="px-4 py-3 text-gray-600">Ideal brood nest temperature. Bees are healthy and thermoregulating normally.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono font-semibold text-yellow-700">36 – 38 °C</td>
                                    <td class="px-4 py-3"><span class="range-cell range-warn">⚠ Warning (High)</span></td>
                                    <td class="px-4 py-3 text-gray-600">Above optimal. Increase ventilation, reduce direct sun. Monitor for 2 hours.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono font-semibold text-yellow-700">18 – 33 °C</td>
                                    <td class="px-4 py-3"><span class="range-cell range-warn">⚠ Warning (Low)</span></td>
                                    <td class="px-4 py-3 text-gray-600">Below optimal. Reduce entrance size to retain heat; add insulation.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono font-semibold text-red-700">&gt; 38 °C</td>
                                    <td class="px-4 py-3"><span class="range-cell range-crit">✕ Critical (High)</span></td>
                                    <td class="px-4 py-3 text-gray-600">Heat stress risk. Open entrance immediately. Shade hive; check queen within 24 h.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono font-semibold text-red-700">&lt; 18 °C</td>
                                    <td class="px-4 py-3"><span class="range-cell range-crit">✕ Critical (Low)</span></td>
                                    <td class="px-4 py-3 text-gray-600">Hypothermia risk. Insulate immediately. Reduce entrance to 1-bee gap.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Humidity -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center bg-blue-50">
                            <i class="fas fa-droplet text-blue-500 text-sm"></i>
                        </span>
                        <h4 class="font-semibold text-gray-800">Humidity (DHT22 · GPIO 4)</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                                    <th class="text-left px-4 py-2.5 font-semibold rounded-tl-lg">Range</th>
                                    <th class="text-left px-4 py-2.5 font-semibold">Status</th>
                                    <th class="text-left px-4 py-2.5 font-semibold rounded-tr-lg">What It Means</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <td class="px-4 py-3 font-mono font-semibold text-green-700">40 – 95 %RH</td>
                                    <td class="px-4 py-3"><span class="range-cell range-ok">✓ Optimal</span></td>
                                    <td class="px-4 py-3 text-gray-600">Bees are managing hive humidity normally. Honey curing is proceeding well.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono font-semibold text-yellow-700">95 – 98 %RH</td>
                                    <td class="px-4 py-3"><span class="range-cell range-warn">⚠ Warning (High)</span></td>
                                    <td class="px-4 py-3 text-gray-600">High humidity. Improve ventilation; prop lid or open screened bottom board.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono font-semibold text-yellow-700">30 – 40 %RH</td>
                                    <td class="px-4 py-3"><span class="range-cell range-warn">⚠ Warning (Low)</span></td>
                                    <td class="px-4 py-3 text-gray-600">Low humidity. Provide a water source within 50 m of the hive.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono font-semibold text-red-700">&gt; 98 %RH</td>
                                    <td class="px-4 py-3"><span class="range-cell range-crit">✕ Critical (High)</span></td>
                                    <td class="px-4 py-3 text-gray-600">Fungal disease risk (chalkbrood). Open all ventilation; inspect for water ingress.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono font-semibold text-red-700">&lt; 30 %RH</td>
                                    <td class="px-4 py-3"><span class="range-cell range-crit">✕ Critical (Low)</span></td>
                                    <td class="px-4 py-3 text-gray-600">Dehydration risk. Provide water immediately; check for severe nectar dearth.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- CO2 -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center bg-purple-50">
                            <i class="fas fa-wind text-purple-500 text-sm"></i>
                        </span>
                        <h4 class="font-semibold text-gray-800">CO₂ Level (MQ-135 · GPIO 34)</h4>
                    </div>
                    <p class="text-xs text-gray-400 mb-3">Sensor is mounted <strong>inside</strong> the hive — expected values are much higher than outdoor ambient (~400 ppm).</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                                    <th class="text-left px-4 py-2.5 font-semibold rounded-tl-lg">Range</th>
                                    <th class="text-left px-4 py-2.5 font-semibold">Status</th>
                                    <th class="text-left px-4 py-2.5 font-semibold rounded-tr-lg">What It Means</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <td class="px-4 py-3 font-mono font-semibold text-green-700">≤ 4,000 ppm</td>
                                    <td class="px-4 py-3"><span class="range-cell range-ok">✓ Normal</span></td>
                                    <td class="px-4 py-3 text-gray-600">Healthy colony. Bees are actively fanning and ventilating the hive.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono font-semibold text-yellow-700">4,000 – 6,000 ppm</td>
                                    <td class="px-4 py-3"><span class="range-cell range-warn">⚠ Warning</span></td>
                                    <td class="px-4 py-3 text-gray-600">Poor airflow. Open the screened bottom board or entrance reducer.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono font-semibold text-red-700">&gt; 6,000 ppm</td>
                                    <td class="px-4 py-3"><span class="range-cell range-crit">✕ Critical</span></td>
                                    <td class="px-4 py-3 text-gray-600">Severe ventilation failure. Open all entrances immediately; brood suffocation risk.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sound / Colony Status -->
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center bg-green-50">
                            <i class="fas fa-microphone text-green-600 text-sm"></i>
                        </span>
                        <h4 class="font-semibold text-gray-800">Sound / Colony Status (MAX4466 · GPIO 35 + CNN)</h4>
                    </div>
                    <p class="text-xs text-gray-400 mb-3">The ESP32 reports raw <strong>sound level (0–100%)</strong> and <strong>dominant frequency (Hz)</strong>. Colony status (normal/queenless/swarming) is classified by uploading a WAV recording to the CNN model — it is <em>not</em> inferred automatically from the live level.</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                                    <th class="text-left px-4 py-2.5 font-semibold rounded-tl-lg">Colony State</th>
                                    <th class="text-left px-4 py-2.5 font-semibold">Alert Level</th>
                                    <th class="text-left px-4 py-2.5 font-semibold rounded-tr-lg">What to Do</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-green-700">Normal</td>
                                    <td class="px-4 py-3"><span class="range-cell range-ok">✓ Healthy</span></td>
                                    <td class="px-4 py-3 text-gray-600">No action needed. Colony is working normally.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-red-700">Queenless</td>
                                    <td class="px-4 py-3"><span class="range-cell range-crit">✕ Critical</span></td>
                                    <td class="px-4 py-3 text-gray-600">Inspect for queen cells or eggs. Re-queen if absent for more than 5 days.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-orange-700">Swarming</td>
                                    <td class="px-4 py-3"><span class="range-cell range-warn">⚠ Warning</span></td>
                                    <td class="px-4 py-3 text-gray-600">Look for queen cells. Consider splitting the colony to prevent swarm loss.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ── Glossary: plain-language meanings for beekeepers ───── -->
            <div class="bg-white rounded-xl p-6" id="glossary">
                <h3 class="font-bold text-lg text-gray-900 mb-1 flex items-center gap-2">
                    <i class="fas fa-book-open text-amber-500"></i>
                    What the Words Mean
                </h3>
                <p class="text-sm text-gray-500 mb-5">A plain-language guide to the words, units and part names you will see on the BeeGuard screens.</p>

                <?php
                $glossary = [
                    ['Hive readings', 'fa-temperature-half', 'text-orange-500', [
                        ['°C (degrees Celsius)', 'How warm it is inside the hive. A healthy brood area stays around <b>33–36 °C</b>.'],
                        ['Humidity (%)', 'How much moisture is in the hive air. 100% is fully wet air. BeeGuard treats <b>40–95%</b> as normal.'],
                        ['CO₂', 'Carbon dioxide, the gas bees breathe out. If it builds up, the hive may not be getting enough fresh air.'],
                        ['ppm (parts per million)', 'The unit for CO₂. 1,000 ppm means 1,000 parts of CO₂ in every million parts of air. Outdoor air is about 400 ppm. BeeGuard warns above <b>4,000 ppm</b> and marks it critical above <b>6,000 ppm</b>. These CO₂ numbers are estimates from a low-cost sensor, so use them to spot trends rather than as exact values.'],
                        ['Hive sound level (%)', 'How loud the hive is right now, from 0% (silent) to 100% (very loud), measured by the microphone inside the hive.'],
                        ['Hz (hertz)', 'How many times something vibrates each second. Bees buzz by beating their wings, and that hum has a pitch measured in Hz. A higher number means a higher-pitched hum.'],
                        ['Buzz pitch', 'The main pitch of the hive hum, in Hz, heard by the hive microphone. It is shown as a live reading only. It does not decide the colony status. If it shows "— Hz", the hive was too quiet to measure a pitch.'],
                    ]],
                    ['The hardware in the hive', 'fa-microchip', 'text-blue-500', [
                        ['ESP32', 'The small computer board in each hive box. It reads all the sensors and sends the readings to this website over Wi-Fi every <b>15 seconds</b>.'],
                        ['DHT22', 'The sensor that measures <b>temperature</b> and <b>humidity</b>.'],
                        ['MQ-135', 'The gas sensor that estimates the <b>CO₂</b> level in ppm. It needs a few minutes to warm up after the box is switched on, so early readings can be off.'],
                        ['MAX4466', 'The <b>microphone</b> inside the hive. It gives the hive sound level and the buzz pitch.'],
                        ['OLED screen', 'The small screen on the hive box. It shows the same readings on the spot and updates every 2 seconds.'],
                        ['Camera', 'The camera pointed at the hive entrance. It watches for insects that are not bees.'],
                    ]],
                    ['Colony check and AI', 'fa-brain', 'text-purple-500', [
                        ['AI model', 'A computer program that learned to recognise patterns from many examples. BeeGuard uses two: one listens to hive sound recordings, and one looks at the camera picture.'],
                        ['CNN (sound AI)', 'The AI that listens to a <b>sound recording</b> of a hive and decides if the colony sounds normal, queenless or about to swarm. It learned from labelled hive recordings.'],
                        ['Sound recording', 'A 5-second audio clip recorded by the hive mic about every 2 minutes and sent to BeeGuard. The colony check listens to these clips. The clip is checked a little after it is recorded, so the colony check can be a minute or two older than the live readings.'],
                        ['Colony check', 'The CNN result for a hive: <b>Colony sounds normal</b>, <b>Signs of swarming</b>, or <b>Signs of a missing queen</b>. "Waiting for the first recording from the hive mic" means no clip from that hive has been checked yet. If it stays that way, make sure the hive node is on and the BeeGuard computer is running the colony check.'],
                        ['Queenless', 'The colony has lost its queen. Without her no new eggs are laid, so look for eggs, young larvae or queen cells.'],
                        ['Swarming', 'Part of the colony is getting ready to leave with the old queen to start a new nest. Look for capped queen cells.'],
                        ['YOLO (camera AI)', 'The AI that looks at the camera picture and draws a box around each insect it finds: green for bees, red for other insects such as wasps or hornets.'],
                        ['Confidence', 'How sure the AI is about what it found, from 0% to 100%. The camera only raises an alert when it is at least <b>35%</b> sure an insect is not a bee.'],
                        ['Training / retrain', 'Teaching the sound AI again using the recordings saved in the system. Retraining replaces the current model, so only do it on purpose.'],
                    ]],
                    ['Screen and alert words', 'fa-display', 'text-green-600', [
                        ['Online / Offline', '<b>Online</b>: the hive box sent readings within the last 2 minutes. <b>Offline</b>: nothing has arrived for over 2 minutes; check the power and Wi-Fi at that hive.'],
                        ['"Updated 12 sec ago"', 'How long ago the newest reading arrived. The number counts up every second. It turns red when the hive has gone quiet for 2 minutes.'],
                        ['(old reading)', 'The value shown is the last one received before the hive went offline, not a live value.'],
                        ['Warning', 'A reading is outside the normal range. Check the hive when you can.'],
                        ['Critical', 'A reading is far outside the normal range, or the colony check found a problem. Check the hive as soon as possible.'],
                        ['Info', 'A notice for your information only. No action needed.'],
                        ['Health score', 'A number from 0 to 100 that sums up the latest readings for a hive. 80 or above is healthy.'],
                    ]],
                ];
                ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php foreach ($glossary as $group): ?>
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fas <?php echo $group[1]; ?> <?php echo $group[2]; ?> text-sm"></i>
                            <h4 class="font-semibold text-gray-800"><?php echo $group[0]; ?></h4>
                        </div>
                        <dl class="divide-y divide-gray-100 border border-gray-100 rounded-xl">
                            <?php foreach ($group[3] as $term): ?>
                            <div class="px-4 py-3 grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-4">
                                <dt class="text-sm font-semibold text-gray-900"><?php echo $term[0]; ?></dt>
                                <dd class="sm:col-span-2 text-sm text-gray-600 leading-relaxed"><?php echo $term[1]; ?></dd>
                            </div>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── FAQ + System Info row ──────────────────────────────── -->
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
                             'BeeGuard is an IoT-based smart beehive monitoring system designed for precision apiculture. ESP32 microcontrollers attached to each hive collect real-time sensor data — temperature, humidity, CO₂, and sound — and send it to this web dashboard over Wi-Fi every <strong>15 seconds</strong>. A CNN machine-learning model then analyzes uploaded audio recordings to detect colony health problems (normal, queenless, or swarming). A separate YOLOv8 AI model monitors the hive entrance via USB camera in real time for non-bee insect intrusions.'],

                            ['📊 &nbsp;How often is data collected and displayed?',
                             'The ESP32 sends sensor data every <strong>15 seconds</strong> and stores it in the database. The Live Hives page checks for new readings every <strong>5 seconds</strong> and shows how long ago each hive last sent data. Analytics charts refresh every <strong>60 seconds</strong>. A device is marked <em>offline</em> if no data is received for more than <strong>2 minutes</strong>. All historical data is kept permanently and can be exported at any time from the Export Data page.'],

                            ['🔔 &nbsp;What kinds of alerts does the system generate?',
                             'BeeGuard generates these alert types automatically:<br><br>
                             <b>Temperature</b> — too high (&gt;36°C warning, &gt;38°C critical) or too low (&lt;33°C warning, &lt;18°C critical)<br>
                             <b>Humidity</b> — too high (&gt;95% warning, &gt;98% critical) or too low (&lt;40% warning, &lt;30% critical)<br>
                             <b>CO₂</b> — elevated (4,000–6,000 ppm warning, &gt;6,000 ppm critical)<br>
                             <b>Colony Status</b> — queenless or swarming detected by CNN audio analysis<br>
                             <b>Camera</b> — non-bee insect detected at hive entrance by YOLOv8<br><br>
                             Each alert has a severity level (info / warning / critical) and is shown on the Alerts page with a recommended beekeeper action.'],

                            ['📷 &nbsp;How does the camera insect detection work?',
                             'A USB webcam streams live video from the hive entrance at up to 10 FPS. A custom-trained YOLOv8 model — trained on bee and insect photos — detects and classifies objects in real time: bees get a <span style="color:#16A34A;font-weight:bold;">green box</span>, while wasps, hornets, moths, and flies get a <span style="color:#DC2626;font-weight:bold;">red box</span>. If a non-bee insect is detected above the confidence threshold (0.35), an alert is automatically created. The camera runs on a separate Python process (port 5050) and does not affect the main PHP server performance.'],

                            ['🤖 &nbsp;How does the CNN audio analysis work?',
                             'The CNN (Convolutional Neural Network) model analyzes 5-second WAV recordings of hive audio. It extracts 40 MFCC (Mel-Frequency Cepstral Coefficient) features from each recording, producing a 40×216 spectrogram input. Three Conv2D layers with Global Average Pooling then classify the audio into one of 3 colony states: <b>Normal</b>, <b>Queenless</b>, or <b>Swarming</b>. The recordings come from the hive mic itself: about every 2 minutes, each hive node records a 5-second clip and uploads it, and the BeeGuard computer checks it with the model. The model works on these recorded clips, not on a continuous live stream, so a new hive shows "Waiting for the first recording from the hive mic" until its first clip has been checked. After that, the result and the time of the recording appear on the Live Hives page and in the hive window.'],

                            ['💾 &nbsp;What should I do when I receive a critical alert?',
                             'Critical alerts require immediate attention. Open the <b>Alerts</b> page, read the recommended action for that specific alert type, then physically inspect the hive. For a <b>Queenless</b> alert: look for queen cells or eggs in the brood frames. For <b>Swarming</b>: inspect for capped queen cells and consider splitting the colony. For <b>High Temperature</b>: open the entrance and provide shade. Dismiss the alert once the issue is resolved.'],

                            // ── Sensors ───────────────────────────────────────────────
                            ['🌡️ &nbsp;What sensors are used in each hive node?',
                             'Each hive sensor node uses three sensors connected to a 30-pin ESP32:<br><br>
                             <b>DHT22</b> (GPIO 4) — measures temperature (°C) and relative humidity (%RH). Accuracy: ±0.5°C and ±2–5%RH. Samples every 2 seconds; ESP32 sends the latest reading every 15 seconds.<br><br>
                             <b>MQ-135</b> (GPIO 34, ADC 12-bit) — measures CO₂ concentration in ppm via analog voltage. Calibration baseline (CO2_R0) is set to 400.0 in fresh air. CO₂ scale factor (CO2_SCALE) is 1.8 for inside-hive placement.<br><br>
                             <b>MAX4466</b> (GPIO 35) — electret microphone amplifier. Captures 512-sample RMS for sound level (0–100%) and the dominant frequency (Hz) of the hive hum, measured from 2,048 samples around the microphone&#39;s own resting level. The microphone is placed <em>inside</em> the hive directly on the brood frames.'],

                            ['📡 &nbsp;How does the ESP32 connect to the system?',
                             'The ESP32 connects to the same Wi-Fi network as the server PC. On boot it calls <code>GET /beemonitoring/api/get_config.php</code> to fetch its sync interval, then POSTs a JSON payload to <code>/beemonitoring/api/receive_data.php</code> every 15 seconds. The payload includes: <code>api_key</code>, <code>device_id</code>, <code>hive_id</code>, <code>temperature</code>, <code>humidity</code>, <code>co2</code>, <code>sound_level</code>, and <code>dominant_freq</code>. The API key must match in both <code>config.h</code> and the server (it is not shown here for security). If the server IP changes (DHCP), update <code>SERVER_HOST</code> in <code>config.h</code> and reflash.'],

                            ['🔧 &nbsp;How do I register a new ESP32 device?',
                             'Flash the ESP32 firmware with the correct <code>WIFI_SSID</code>, <code>WIFI_PASSWORD</code>, <code>SERVER_HOST</code>, <code>DEVICE_ID</code>, and <code>HIVE_ID</code> values in <code>config.h</code>. Once powered on and connected to Wi-Fi, the device will appear in the <b>Detected New ESP32 Devices</b> section on the Devices page within 15 seconds. Click <b>Register</b> to assign it to a new or existing hive. If the device already has a matching <code>HIVE_ID</code> in the database it will send data immediately.'],

                            ['📤 &nbsp;Can I export the sensor data?',
                             'Yes. Go to the <b>Export Data</b> page to download sensor readings, ML predictions, and alert history in CSV, JSON, or PDF format. You can filter by hive, sensor type, and date range. All data is stored permanently — there is no automatic deletion. This is useful for thesis research, academic analysis, and physical beekeeper records.'],

                            ['🏥 &nbsp;What is the Hive Health Score?',
                             'The hive health score (0–100) is a composite metric calculated from the latest sensor readings per hive:<br><br>
                             • Temperature outside 33–36°C → −5 points<br>
                             • CO₂ above 4,000 ppm → −5 points<br>
                             • Humidity outside 40–95% → −3 points<br>
                             • Colony status not "normal" (CNN audio result) → −8 points<br><br>
                             A score of 80 or above is considered <b>Healthy</b>. The Dashboard shows the average score across all active hives.'],

                            ['📋 &nbsp;What are Inspection Notes?',
                             'Inspection Notes are manual records created by beekeepers or admins to document physical hive checks. Each note records: inspection date, hive condition (excellent / good / fair / poor / critical), colony strength (strong / moderate / weak / queenless), and free-text observations and recommendations. Notes are visible on the Inspection Notes page and help correlate sensor data with real-world hive state. You can also mark a hive as "checked" directly from the Hive modal using the <b>Mark as Checked</b> button — this saves the timestamp of the last physical inspection.'],
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
                                ['fa-tag',        'text-yellow-600',  '#FEF9C3', 'Version',         'BeeGuard v1.0.0'],
                                ['fa-brain',       'text-purple-600',  '#F5F3FF', 'CNN Model',        'AudioCNN v3.0 (3-class)'],
                                ['fa-camera',      'text-blue-600',    '#EFF6FF', 'Camera Model',     'YOLOv8n (Custom)'],
                                ['fa-microchip',   'text-green-600',   '#ECFDF5', 'Firmware',         'ESP32 v2.4.1'],
                                ['fa-wifi',        'text-indigo-600',  '#EEF2FF', 'Send Interval',    'Every 15 seconds'],
                                ['fa-clock',       'text-orange-600',  '#FFF7ED', 'Offline Timeout',  '2 minutes'],
                                ['fa-database',    'text-gray-600',    '#F9FAFB', 'Database',         'MySQL (bee_monitoring)'],
                                ['fa-calendar',    'text-blue-600',    '#EFF6FF', 'Last Update',      'October 2026'],
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
                                ['hives.php',            'fa-layer-group', 'View Live Hives'],
                                ['analytics.php',        'fa-chart-line',  'Open Analytics'],
                                ['alerts.php',           'fa-bell',        'Check Alerts'],
                                ['upload_sound.php',     'fa-microphone',  'Upload Sound for CNN'],
                                ['export.php',           'fa-download',    'Export Data'],
                                ['inspection_notes.php', 'fa-clipboard',   'Inspection Notes'],
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
                            <p class="text-yellow-100 text-sm mt-0.5">Contact the BeeGuard team for hardware setup, calibration help, or firmware issues.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <a href="mailto:jtgutierrez3165qc@student.fatima.edu.ph"
                           class="bg-white text-yellow-600 px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-yellow-50 transition-all shadow flex items-center gap-2">
                            <i class="fas fa-envelope"></i>Contact Support
                        </a>
                        <a href="inspection_notes.php"
                           class="border-2 border-white/60 text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-white/10 transition-all flex items-center gap-2">
                            <i class="fas fa-clipboard"></i>Log Inspection
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </main>
</body>
</html>
