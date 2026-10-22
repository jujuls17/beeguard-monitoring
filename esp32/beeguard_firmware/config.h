// =============================================================
//  BeeGuard IoT - ESP32 Configuration
//  Edit WIFI and SERVER sections before flashing.
// =============================================================
//WIFI="Pewww" "bjZDf5c8" SERVER_HOST="192.168.100.166"
//WIFI="WOW""87654321" SERVER_HOST="10.87.223.103", SERVER_HOST="10.212.190.103", SERVER_HOST="10.237.17.103"
#ifndef CONFIG_H
#define CONFIG_H

// --- WiFi Credentials ---
#define WIFI_SSID           "WOW"
#define WIFI_PASSWORD       "87654321"

// --- Server Settings ---
// Run ipconfig (Windows) or ifconfig (Linux/Mac) to get your PC IP
// Example: "192.168.1.100"
#define SERVER_HOST         "10.164.152.103"
#define SERVER_PORT         80
#define API_BASE            "/beemonitoring/api"
#define API_KEY             "BEEGUARD_SECRET_2026"

// --- Device Identity ---
// Must match device_id in the edge_devices database table
#define DEVICE_ID           "ESP32-001"
#define HIVE_ID             1

// --- Sensor Pins (Your 30-pin ESP32) ---
//  LEFT SIDE:                RIGHT SIDE:
//  Pin 1  → 3V3             Pin 12 → D34 ← MQ-135 AO
//  Pin 2  → GND             Pin 11 → D35 ← MAX4466 OUT
//  Pin 4  → D4  ← DHT22
//
#define DHT_PIN             4       // D4  (Left pin 4)  - DHT22 DATA
#define DHT_TYPE            DHT22
#define MQ135_PIN           34      // D34 (Right pin 12) - MQ-135 AO
#define MIC_PIN             35      // D35 (Right pin 11) - MAX4466 OUT

// --- OLED Display Pins (I2C) ---
#define OLED_SDA            21      // D21 (GPIO 21)
#define OLED_SCL            22      // D22 (GPIO 22)
#define OLED_ADDR           0x3C    // Default I2C address (try 0x3D if blank)

// --- Timing ---
// --- Timing ---
#define SEND_INTERVAL_MS    15000   // 15 seconds
#define OLED_REFRESH_MS     2000    // OLED updates every 2 seconds
#define WIFI_RETRY_DELAY_MS 5000
#define MAX_WIFI_RETRIES    10

// --- MQ-135 CO2 Calibration ---
// Calibrate in fresh air: read raw value then adjust CO2_R0
// Raw ~400 in fresh air (400ppm CO2) on 12-bit ADC (0-4095)
#define CO2_R0              400.0f  // Sensor resistance in clean air
#define CO2_SCALE           1.8f    // Scaling factor for hive environment

// --- Microphone Sampling (MIC IS INSIDE THE HIVE) ---
// Inside-hive placement: bees are directly on the mic, so raw RMS values are
// much higher than external placement. MIC_RMS_MAX must reflect this.
#define MIC_SAMPLES         512     // RMS sample count
#define MIC_FREQ_SAMPLES    2048    // Zero-crossing sample count (more = more stable)
#define MIC_BASELINE        2048    // ADC midpoint (12-bit, 0-4095). Should be ~2048 for MAX4466.
// MIC_RMS_MAX: the RMS value that maps to 100% sound level.
// Inside hive → bees are loud → set higher (1200–1800).
// To calibrate: open Serial Monitor, note the RMS printed during normal bee activity,
// set MIC_RMS_MAX to ~120% of that value.
#define MIC_RMS_MAX         1500    // Tune up if sound% always shows 100%, down if always <30%

#endif // CONFIG_H
