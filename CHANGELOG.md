# BeeGuard Changelog

The version number is in `version.php` and shows in the sidebar (under "IoT Monitor") and on the login page.
**Every update:** bump the number in `version.php`, add a section here, then push.
If the sidebar on the hosted site shows an older number than this file, the server is not running the latest push.

## v1.4 — 2026-11-11
- **Colony check now listens to the hive mic.** Each ESP32 records a 5-second clip from its MAX4466 mic (22,050 Hz) about every 2 minutes, with the first one 20 s after it starts, and uploads it to `api/upload_hive_clip.php`. The site keeps the newest 5 clips per hive in `uploads/hive_clips/`, which cannot be opened from a browser.
- New `cnn_worker.py` (runs on the BeeGuard laptop next to `camera_capture.py`). It fetches new clips through `api/hive_clips.php`, runs the CNN locally, sends the result to `api/update_colony_status.php`, then removes the clip from the site. It keeps a local copy in `ml/data/hive_mic_clips/` and skips silent clips (mic not hearing anything).
- Alerts: a repeated colony finding (for example "queenless" every 2 minutes) no longer creates a new alert each time. A new alert appears only when the finding changes.
- `ml/predict.py`: keeps the model in memory between clips, so each check takes milliseconds instead of reloading the model.
- Hive cards, hive window, Help page: the colony check wording now explains that it comes from the hive mic's clips ("Waiting for the first recording from the hive mic"). The Help page also says 5-second clips, not 3-second.
- ESP32 firmware v1.3: records and uploads the mic clips (OLED shows "Recording clip..." / "Sending clip..." for about 6 s). **Re-flash both boards.**
- No database change.

## v1.3 — 2026-11-11
- Live Hives: "Live · page updates by itself · checked 3 sec ago" indicator in the page header. It turns yellow while a hive window is open (updates paused), and red with a plain message if updates stop ("Can't reach the server" / "Signed out · reload the page"). Before, a failed update was silent and the page just looked frozen.
- Live Hives: a reading that changes glows briefly, so you can see it update without reloading.
- Live Hives: the summary counts at the top now update live. "Normal" renamed to "No issues", so it isn't confused with the colony check's "Colony sounds normal".
- Live Hives: small trend lines refresh every 15 s (was 60 s).
- CO₂: a hive whose CO₂ sensor sends nothing now shows "No reading" / "CO₂ sensor not sending" instead of "0 ppm".
- ESP32 firmware v1.2: version shown on the OLED ("BeeGuard v1.2") and the Serial Monitor. A mic resting at 0 or at full scale is reported as a wiring problem and sends no sound/pitch instead of a fake value. **Re-flash both boards.**

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
