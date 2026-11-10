# BeeGuard Changelog

The version number is in `version.php` and shows in the sidebar (under "IoT Monitor") and on the login page.
**Every update:** bump the number in `version.php`, add a section here, then push.
If the sidebar on the hosted site shows an older number than this file, the server is not running the latest push.

## v1.2 — 2026-11-11
- Fix: hive cards never updated without a page reload. The live update request (`api/live_hives.php`) crashed on every call because a required file was not loaded. Cards now really update every 5 s.
- ESP32 firmware: the microphone now measures its own resting level instead of assuming a fixed value. Before, a board whose mic rested at a different level read a false 50 Hz and a raised sound %. When the hive is too quiet to measure, the pitch shows "— Hz". **Re-flash both ESP32 boards.**
- Hive cards: when there is no colony check yet, the card now says "Waiting for a sound recording" and explains that the live sensors don't run this check.
- Help page: new "What the Words Mean" glossary (ppm, Hz, DHT22, MQ-135, MAX4466, ESP32, CNN, YOLO, confidence, warning/critical, and more). Corrected the camera confidence threshold to 0.35 and the refresh time to 5 s, and removed the device API key from the page.

## v1.1 — 2026-11-11
Changes after the evaluation, prepared for the defense.

- Hive cards: live sensor readings (temperature, humidity, CO₂, sound level, buzz pitch) refresh every 5 s instead of 15 s. The ESP32 still sends every 15 s.
- Hive cards: "Sensors updated 12 sec ago" label that counts up every second; turns red when a hive has sent nothing for 2 minutes, and its readings fade.
- Hive cards and hive window: plain-language wording for beekeepers. Live readings ("Hive sound level · from hive mic", "Buzz pitch") are now separate from the colony check ("Colony check · from a sound recording"), which shows when the recording was checked and how long ago.
- Settings: clearer warning before "Retrain Model" (it overwrites the current model; a backup is kept).
- Version number shown in the sidebar and login page.

## v1.0
Build evaluated by the respondents (local server), as described in the paper.
