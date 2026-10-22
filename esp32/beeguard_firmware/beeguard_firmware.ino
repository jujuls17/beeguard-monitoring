// =============================================================
//  BeeGuard IoT - ESP32 Sensor Firmware
//
//  Hardware (Your 30-pin ESP32):
//    DHT22   → D4  (GPIO 4)   — Temperature & Humidity
//    MQ-135  → D34 (GPIO 34)  — CO₂
//    MAX4466 → D35 (GPIO 35)  — Microphone
//    OLED    → D21 (SDA) / D22 (SCL) — 1.3" SH1106 Display
//
//  Libraries (Arduino Library Manager):
//    • DHT sensor library     — Adafruit
//    • Adafruit Unified Sensor — Adafruit
//    • ArduinoJson            — Benoit Blanchon
//    • U8g2                   — Oliver (OLED)
//
//  Board: ESP32 Dev Module | Baud: 115200 | Flash: 4MB
// =============================================================

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <DHT.h>
#include <math.h>
#include <U8g2lib.h>
#include <Wire.h>
#include "config.h"

// ── OLED: 1.3" SH1106 128x64 via I2C ────────────────────────
// If screen is blank after flashing, swap to the SSD1306 line:
U8G2_SH1106_128X64_NONAME_F_HW_I2C  u8g2(U8G2_R0, U8X8_PIN_NONE);
// U8G2_SSD1306_128X64_NONAME_F_HW_I2C u8g2(U8G2_R0, U8X8_PIN_NONE);

DHT dht(DHT_PIN, DHT_TYPE);

// ── Timing ────────────────────────────────────────────────────
unsigned long lastSendTime    = 0;
unsigned long lastDisplayTime = 0;
int           syncIntervalMs  = SEND_INTERVAL_MS;

// ── Cached Sensor Values (shared between display & send) ──────
float g_temperature  = 0;
float g_humidity     = 0;
int   g_co2          = 0;
float g_soundLevel   = 0;
float g_dominantFreq = 0;
bool  g_wifiOk       = false;

// =============================================================
//  SETUP
// =============================================================
void setup() {
    Serial.begin(115200);
    delay(1000);

    // Init I2C + OLED first so boot status is visible
    Wire.begin(OLED_SDA, OLED_SCL);
    u8g2.begin();
    showBootScreen("BeeGuard IoT", "Initializing...");

    Serial.println(F("\n========================================"));
    Serial.println(F("       BeeGuard IoT - ESP32 Node"));
    Serial.println(F("========================================"));

    printPinConfig();

    showBootScreen("BeeGuard IoT", "Sensors init...");
    initSensors();

    showBootScreen("BeeGuard IoT", "Connecting WiFi");
    connectWiFi();
    fetchServerConfig();

    showBootScreen("BeeGuard IoT", "System Ready!");
    delay(1000);
}

// =============================================================
//  LOOP
// =============================================================
void loop() {
    g_wifiOk = (WiFi.status() == WL_CONNECTED);

    if (!g_wifiOk) {
        Serial.println(F("[WiFi] Lost — reconnecting..."));
        connectWiFi();
    }

    unsigned long now = millis();

    // Real-time OLED + sensor refresh every 2 seconds
    if (now - lastDisplayTime >= OLED_REFRESH_MS) {
        lastDisplayTime = now;
        readSensorsOnly();
        updateOLED();
    }

    // Send to server every 5 minutes
    if (now - lastSendTime >= (unsigned long)syncIntervalMs) {
        lastSendTime = now;
        sendToServer(g_temperature, g_humidity, g_co2, g_soundLevel, g_dominantFreq);
    }
}

// =============================================================
//  OLED: BOOT SCREEN
// =============================================================
void showBootScreen(const char* line1, const char* line2) {
    u8g2.clearBuffer();
    u8g2.setFont(u8g2_font_ncenB08_tr);
    u8g2.drawStr(0, 16, line1);
    u8g2.setFont(u8g2_font_6x10_tr);
    u8g2.drawStr(0, 34, line2);
    u8g2.sendBuffer();
}

// =============================================================
//  OLED: BEE DRAWING (right side)
// =============================================================
void drawBee(int cx, int cy) {
    // Antennae
    u8g2.setDrawColor(1);
    u8g2.drawLine(cx - 3, cy - 13, cx - 8, cy - 20);
    u8g2.drawLine(cx + 3, cy - 13, cx + 8, cy - 20);
    u8g2.drawDisc(cx - 8, cy - 20, 2, U8G2_DRAW_ALL);
    u8g2.drawDisc(cx + 8, cy - 20, 2, U8G2_DRAW_ALL);

    // Wings (outline ellipses)
    u8g2.drawEllipse(cx - 11, cy - 5, 8, 11, U8G2_DRAW_ALL);
    u8g2.drawEllipse(cx + 11, cy - 5, 8, 11, U8G2_DRAW_ALL);

    // Head
    u8g2.drawDisc(cx, cy - 7, 6, U8G2_DRAW_ALL);

    // Body (filled oval)
    u8g2.drawFilledEllipse(cx, cy + 9, 9, 14, U8G2_DRAW_ALL);

    // Stripes — erase horizontal lines through body
    u8g2.setDrawColor(0);
    u8g2.drawHLine(cx - 9, cy + 3,  18);
    u8g2.drawHLine(cx - 9, cy + 9,  18);
    u8g2.drawHLine(cx - 9, cy + 15, 18);

    // Stinger
    u8g2.setDrawColor(1);
    u8g2.drawLine(cx - 2, cy + 22, cx, cy + 27);
    u8g2.drawLine(cx + 2, cy + 22, cx, cy + 27);

    u8g2.setDrawColor(1);
}

// =============================================================
//  OLED: REAL-TIME SENSOR DISPLAY
// =============================================================
void updateOLED() {
    char buf[20];

    u8g2.clearBuffer();
    u8g2.setDrawColor(1);

    // --- Header ---
    u8g2.setFont(u8g2_font_5x7_tr);
    u8g2.drawStr(0, 8, "BeeGuard");
    u8g2.drawStr(g_wifiOk ? 80 : 78, 8, g_wifiOk ? "WiFi:OK" : "No WiFi");
    u8g2.drawHLine(0, 10, 128);

    // --- Vertical divider ---
    u8g2.drawVLine(74, 10, 54);

    // --- Sensor data (left side) ---
    u8g2.setFont(u8g2_font_5x7_tr);

    snprintf(buf, sizeof(buf), "T:%.1fC",    g_temperature);
    u8g2.drawStr(0, 21, buf);

    snprintf(buf, sizeof(buf), "H:%.1f%%",   g_humidity);
    u8g2.drawStr(0, 30, buf);

    snprintf(buf, sizeof(buf), "CO2:%dppm",  g_co2);
    u8g2.drawStr(0, 39, buf);

    snprintf(buf, sizeof(buf), "Snd:%.0f%%", g_soundLevel);
    u8g2.drawStr(0, 48, buf);

    snprintf(buf, sizeof(buf), "Hz:%.0f",    g_dominantFreq);
    u8g2.drawStr(0, 57, buf);


    // --- Bee (right side) ---
    drawBee(101, 38);

    u8g2.sendBuffer();
}

// =============================================================
//  SENSORS: READ ONLY (for real-time OLED, no HTTP)
// =============================================================
void readSensorsOnly() {
    float t = dht.readTemperature();
    float h = dht.readHumidity();
    if (!isnan(t) && !isnan(h)) {
        g_temperature = t;
        g_humidity    = h;
    }

    g_co2 = readCO2();
    readMicrophone(g_soundLevel, g_dominantFreq);
}

// =============================================================
//  PIN CONFIG PRINT
// =============================================================
void printPinConfig() {
    Serial.println(F("\n📌 PIN CONFIGURATION:"));
    Serial.println(F("--------------------------------"));
    Serial.println(F("DHT22 Data  → D4  (GPIO 4)"));
    Serial.println(F("MQ-135 AO   → D34 (GPIO 34)"));
    Serial.println(F("MAX4466 OUT → D35 (GPIO 35)"));
    Serial.println(F("OLED SDA    → D21 (GPIO 21)"));
    Serial.println(F("OLED SCL    → D22 (GPIO 22)"));
    Serial.println(F("--------------------------------\n"));
}

// =============================================================
//  SENSOR INITIALIZATION
// =============================================================
void initSensors() {
    // DHT22
    Serial.print(F("⏳ Initializing DHT22..."));
    dht.begin();
    delay(2000);
    float t = dht.readTemperature();
    float h = dht.readHumidity();
    if (!isnan(t) && !isnan(h)) {
        Serial.println(F(" ✅ OK"));
    } else {
        Serial.println(F(" ⚠️  No reading — continuing"));
    }

    // MQ-135 warmup
    Serial.print(F("⏳ Warming up MQ-135 — 30 seconds..."));
    for (int i = 30; i > 0; i--) {
        delay(1000);
        if (i % 10 == 0) Serial.printf(" %ds", i);
        char buf[24];
        snprintf(buf, sizeof(buf), "MQ-135 warm: %ds", i);
        showBootScreen("Sensor Init", buf);
    }
    int rawCO2 = analogRead(MQ135_PIN);
    Serial.printf("\n   Raw ADC: %d\n", rawCO2);
    Serial.println(rawCO2 > 100 ? F("   ✅ MQ-135 OK") : F("   ⚠️  Low — check 5V on VIN"));

    // MAX4466
    Serial.print(F("⏳ Initializing MAX4466..."));
    int rawMic = analogRead(MIC_PIN);
    Serial.println(rawMic > 0 ? F(" ✅ OK") : F(" ⚠️  Raw=0 — check 3V3"));

    Serial.println(F("\n✅ ALL SENSORS INITIALIZED!"));
    delay(3000);
}

// =============================================================
//  WIFI
// =============================================================
void connectWiFi() {
    Serial.printf("\n[WiFi] Connecting to %s", WIFI_SSID);
    WiFi.mode(WIFI_STA);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

    int retries = 0;
    while (WiFi.status() != WL_CONNECTED && retries < MAX_WIFI_RETRIES) {
        delay(WIFI_RETRY_DELAY_MS);
        Serial.print(".");
        retries++;
    }

    if (WiFi.status() == WL_CONNECTED) {
        g_wifiOk = true;
        Serial.printf("\n[WiFi] Connected — IP: %s\n", WiFi.localIP().toString().c_str());
    } else {
        g_wifiOk = false;
        Serial.println(F("\n[WiFi] Could not connect. Will retry."));
    }
}

// =============================================================
//  FETCH SERVER CONFIG
// =============================================================
void fetchServerConfig() {
    if (WiFi.status() != WL_CONNECTED) return;

    String url = String("http://") + SERVER_HOST + ":" + SERVER_PORT +
                 API_BASE + "/get_config.php?api_key=" + API_KEY +
                 "&device_id=" + DEVICE_ID;

    HTTPClient http;
    http.begin(url);
    int code = http.GET();

    if (code == 200) {
        StaticJsonDocument<512> doc;
        if (!deserializeJson(doc, http.getString())) {
            // Prefer sync_interval_seconds (new), fall back to minutes (legacy)
            int secs = doc["sync_interval_seconds"] | 0;
            if (secs <= 0) {
                int mins = doc["sync_interval_minutes"] | 5;
                secs = mins * 60;
            }
            syncIntervalMs = secs * 1000;
            Serial.printf("[Config] Hive: %s | Sync: every %ds\n",
                          (const char*)doc["hive_name"], secs);
        }
    } else {
        Serial.printf("[Config] Server %d — using default %ds interval\n",
                      code, syncIntervalMs / 1000);
    }
    http.end();
}

// =============================================================
//  CO2 READING (MQ-135)
// =============================================================
int readCO2() {
    long sum = 0;
    for (int i = 0; i < 10; i++) {
        sum += analogRead(MQ135_PIN);
        delay(10);
    }
    int raw = sum / 10;
    int co2 = (int)(CO2_R0 + (raw - 400) * CO2_SCALE);
    return constrain(co2, 400, 8000);
}

// =============================================================
//  MICROPHONE READING (MAX4466)
// =============================================================
void readMicrophone(float &soundLevel, float &dominantFreq) {
    // RMS sound level
    long sumSq = 0;
    for (int i = 0; i < MIC_SAMPLES; i++) {
        int raw  = analogRead(MIC_PIN) - MIC_BASELINE;
        sumSq   += (long)raw * raw;
        delayMicroseconds(100);
    }
    float rms = sqrt((float)sumSq / MIC_SAMPLES);
    soundLevel = constrain(map((long)rms, 0, MIC_RMS_MAX, 0, 100), 0, 100);

    // Dominant frequency via zero-crossing
    int crossings  = 0;
    int prevSample = analogRead(MIC_PIN) - MIC_BASELINE;
    unsigned long startUs = micros();

    for (int i = 0; i < MIC_FREQ_SAMPLES; i++) {
        int curr = analogRead(MIC_PIN) - MIC_BASELINE;
        if ((prevSample < 0 && curr >= 0) || (prevSample >= 0 && curr < 0)) {
            crossings++;
        }
        prevSample = curr;
        delayMicroseconds(100);
    }

    float dur    = (micros() - startUs) / 1000000.0f;
    dominantFreq = constrain((crossings / 2.0f) / dur, 50, 800);
}

// =============================================================
//  HTTP POST TO SERVER
// =============================================================
void sendToServer(float temp, float hum, int co2, float sound, float freq) {
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println(F("[HTTP] Not connected — data not sent"));
        return;
    }

    StaticJsonDocument<300> doc;
    doc["api_key"]       = API_KEY;
    doc["device_id"]     = DEVICE_ID;
    doc["hive_id"]       = HIVE_ID;
    doc["temperature"]   = round(temp  * 10.0f) / 10.0f;
    doc["humidity"]      = round(hum   * 10.0f) / 10.0f;
    doc["co2"]           = co2;
    doc["sound_level"]   = round(sound * 10.0f) / 10.0f;
    doc["dominant_freq"] = round(freq);

    String payload;
    serializeJson(doc, payload);

    String url = String("http://") + SERVER_HOST + ":" + SERVER_PORT +
                 API_BASE + "/receive_data.php";

    HTTPClient http;
    http.begin(url);
    http.addHeader("Content-Type", "application/json");
    http.setTimeout(10000);

    Serial.println(F("\n[HTTP] Sending to server..."));
    Serial.printf("  Temp: %.1f°C | Hum: %.1f%% | CO2: %dppm | Snd: %.1f%% | Freq: %.0fHz\n",
                  temp, hum, co2, sound, freq);

    int code = http.POST(payload);

    if (code == 200) {
        Serial.println(F("[HTTP] ✅ Sent OK"));
        Serial.printf("[HTTP] Response: %s\n", http.getString().c_str());
    } else if (code > 0) {
        Serial.printf("[HTTP] ❌ HTTP %d\n", code);
    } else {
        Serial.printf("[HTTP] ❌ Failed (%d) — check SERVER_HOST\n", code);
    }
    http.end();
}