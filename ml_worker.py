"""
BeeGuard - ML helper for the BeeGuard computer (laptop)

The hosted site cannot run Python/TensorFlow, so this script does the ML work locally
and sends the results back to the site:

  * Retrain Model (Settings page): downloads the dataset from the site (including new
    uploaded sound samples), backs up the current model, runs ml/train_model.py, then
    uploads the new model, accuracy and charts back to the site.
  * Upload Sound Sample (Settings page): classifies the uploaded sound with the CNN and
    sends the result back, so the page shows "ML Prediction: ...".

Leave it running during the demo:
    python ml_worker.py

The Settings page shows "BeeGuard computer connected" while this is running.
The site URL and key are taken from push_cnn_results.py.
"""

import os
import re
import sys
import json
import time
import shutil
import subprocess
from collections import deque
from datetime import datetime
from pathlib import Path

import requests

PROJECT = Path(__file__).parent
ML_DIR = PROJECT / "ml"
sys.path.insert(0, str(PROJECT))
sys.path.insert(0, str(ML_DIR))

from push_cnn_results import HOSTINGER_URL, API_KEY   # noqa: E402

API        = f"{HOSTINGER_URL}/api/ml_worker.php"
HEADERS    = {"X-Api-Key": API_KEY}
DATA_DIR   = ML_DIR / "data" / "audio"
MODELS_DIR = ML_DIR / "models"
UPLOAD_DIR = ML_DIR / "data" / "panel_uploads"
SYNC_LIST  = ML_DIR / "data" / ".synced_from_site.txt"   # dataset files that came from the site
POLL_SEC   = 3
RESULT_FILES = ["beeguard_cnn.keras", "metrics.json", "classification_report.txt",
                "confusion_matrix.csv", "confusion_matrix.png", "training_history.png"]
ANSI = re.compile(r"\x1b\[[0-9;]*m")


def log(msg: str) -> None:
    print(f"[{datetime.now():%H:%M:%S}] {msg}", flush=True)


def api_get(action: str, **params) -> requests.Response:
    r = requests.get(API, params={"action": action, **params}, headers=HEADERS, timeout=60)
    r.raise_for_status()
    return r


def api_post_json(action: str, data, **params) -> None:
    r = requests.post(API, params={"action": action, **params}, headers=HEADERS, json=data, timeout=30)
    r.raise_for_status()


# ── Dataset sync ──────────────────────────────────────────────────────────────
def sync_dataset(say) -> None:
    files = api_get("dataset").json()["files"]
    on_site = {f"{f['cls']}/{f['name']}" for f in files}
    synced = set(SYNC_LIST.read_text(encoding="utf-8").split()) if SYNC_LIST.exists() else set()

    new = 0
    for f in files:
        local = DATA_DIR / f["cls"] / f["name"]
        if local.exists() and local.stat().st_size == f["size"]:
            continue
        local.parent.mkdir(parents=True, exist_ok=True)
        local.write_bytes(api_get("file", kind="dataset", cls=f["cls"], name=f["name"]).content)
        synced.add(f"{f['cls']}/{f['name']}")
        new += 1

    # A sample that came from the site and was deleted there is removed here too
    removed = 0
    for key in list(synced):
        if key not in on_site:
            (DATA_DIR / key).unlink(missing_ok=True)
            synced.discard(key)
            removed += 1

    SYNC_LIST.write_text("\n".join(sorted(synced)), encoding="utf-8")
    say(f"[Sync] Dataset ready: {len(files)} recordings on the site, {new} new downloaded, {removed} removed")


# ── Training ──────────────────────────────────────────────────────────────────
def backup_model() -> Path:
    dest = MODELS_DIR / f"backup_{datetime.now():%Y%m%d_%H%M%S}"
    dest.mkdir(parents=True, exist_ok=True)
    for name in RESULT_FILES + ["beeguard_cnn.h5"]:
        if (MODELS_DIR / name).exists():
            shutil.copy2(MODELS_DIR / name, dest / name)
    shutil.copy2(MODELS_DIR / "beeguard_cnn.keras", MODELS_DIR / "beeguard_cnn.keras.bak")
    return dest


def restore_model(backup: Path) -> None:
    for f in backup.iterdir():
        shutil.copy2(f, MODELS_DIR / f.name)


def run_training() -> None:
    lines = deque(maxlen=40)
    last_sent = [0.0]

    def say(line: str, force: bool = False) -> None:
        lines.append(line)
        log(line)
        if force or time.time() - last_sent[0] > 2:
            try:
                api_post_json("status", {"status": "running", "log": list(lines)})
            except requests.RequestException:
                pass
            last_sent[0] = time.time()

    say("[Start] The BeeGuard computer is retraining the CNN model", force=True)
    backup = None
    try:
        sync_dataset(lambda m: say(m, force=True))
        backup = backup_model()
        say(f"[Backup] Current model saved in ml/models/{backup.name}", force=True)

        env = dict(os.environ, PYTHONIOENCODING="utf-8", TF_CPP_MIN_LOG_LEVEL="2")
        proc = subprocess.Popen([sys.executable, "-u", str(ML_DIR / "train_model.py")], cwd=str(ML_DIR),
                                stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                                text=True, encoding="utf-8", errors="replace", env=env)
        for raw in proc.stdout:
            line = ANSI.sub("", raw).rstrip()
            # Skip Keras progress bars; keep steps, epochs, results and errors
            if not line or "━" in line or "step" in line or "=" * 5 in line:
                continue
            if line.startswith(("[", "Epoch", "Traceback")) or re.search(r"error|exception", line, re.I):
                say(line)
        code = proc.wait()

        if code != 0 or not (MODELS_DIR / "beeguard_cnn.keras").exists():
            raise RuntimeError(f"train_model.py stopped with an error (exit code {code})")

        say("[Upload] Sending the new model and results to the site...", force=True)
        files = {n.replace(".", "_"): (n, open(MODELS_DIR / n, "rb")) for n in RESULT_FILES if (MODELS_DIR / n).exists()}
        try:
            r = requests.post(API, params={"action": "result"}, headers=HEADERS, files=files, timeout=180)
            r.raise_for_status()
            if not r.json().get("success"):
                raise RuntimeError(r.json().get("message", "upload refused"))
        finally:
            for _, fh in files.values():
                fh.close()

        acc = json.loads((MODELS_DIR / "metrics.json").read_text()).get("val_accuracy")
        lines.append(f"[Eval] Validation accuracy: {acc}%")
        lines.append("[Done] Training complete!")
        api_post_json("status", {"status": "done", "log": list(lines)})
        log(f"Training done - validation accuracy {acc}%. Site updated.")
    except Exception as e:
        if backup:
            restore_model(backup)
            lines.append("[Restore] The previous model was put back; nothing changed.")
        lines.append(f"[ERROR] {e}")
        log(f"Training FAILED: {e}")
        try:
            api_post_json("status", {"status": "error", "log": list(lines)})
        except requests.RequestException:
            pass


# ── Classify an uploaded sound ────────────────────────────────────────────────
def classify_upload(name: str) -> None:
    from predict import predict
    UPLOAD_DIR.mkdir(parents=True, exist_ok=True)
    path = UPLOAD_DIR / name
    path.write_bytes(api_get("file", kind="upload", name=name).content)
    pred = predict(str(path))
    if "error" in pred:
        result = {"status": "error", "label": "Could not classify this file", "confidence": 0,
                  "all_probs": {}, "error": pred["error"]}
        log(f"Upload {name}: could not classify - {pred['error']}")
    else:
        labels = {"normal": "Normal", "queenless": "Queenless", "swarming": "Swarming"}
        result = {
            "status":         pred["colony_status"],
            "label":          labels.get(pred["colony_status"], pred["colony_status"]),
            "confidence":     pred["confidence"],
            "all_probs":      pred["all_probabilities"],
            "clips_analyzed": pred.get("clips_analyzed", 1),
            "frequency":      pred.get("frequency", {}),
        }
        log(f"Upload {name}: {result['label']} ({result['confidence']}%)")
    api_post_json("prediction", result, name=name)


def main() -> None:
    log("BeeGuard ML helper started. Waiting for the site... (Ctrl+C to stop)")
    connected = False
    while True:
        try:
            job = api_get("poll").json()
            if not connected:
                log("Connected to the site. Settings page now shows 'BeeGuard computer connected'.")
                connected = True
            for name in job.get("predict", []):
                classify_upload(name)
            if job.get("train", {}).get("status") == "queued":
                run_training()
        except requests.RequestException as e:
            if connected:
                log(f"Lost contact with the site ({e.__class__.__name__}). Retrying...")
            connected = False
        except Exception as e:   # keep running during the demo no matter what
            log(f"Error: {e}")
        time.sleep(POLL_SEC)


if __name__ == "__main__":
    main()
