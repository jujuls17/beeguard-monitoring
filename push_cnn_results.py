"""
BeeGuard - CNN Push Script
Runs the CNN on local audio files and sends each result to the hosted Hostinger database.

Usage:
    python push_cnn_results.py                      # process all files in ml/data/audio/
    python push_cnn_results.py --dry-run            # predict only, do not POST to server
    python push_cnn_results.py --hive-id 1          # only send results for a specific hive
    python push_cnn_results.py path/to/file.wav 1   # single file for hive_id 1

Before running:
    1. Set HOSTINGER_URL to your hosted site URL (no trailing slash).
    2. Set HIVE_MAP to match your Hostinger hive IDs (get these from hives.php on the server).
    3. Run: python push_cnn_results.py --dry-run   to verify predictions without touching the DB.
"""

import sys
import json
import time
import argparse
from pathlib import Path

import requests

PROJECT  = Path(__file__).parent
ML_DIR   = PROJECT / "ml"
AUDIO    = ML_DIR / "data" / "audio"
CLASSES  = ["normal", "queenless", "swarming"]

# ── Configuration ──────────────────────────────────────────────
# Set your Hostinger site URL here (no trailing slash):
HOSTINGER_URL = "https://darkturquoise-loris-483393.hostingersite.com"

# Map local class folder → hive_id on Hostinger.
# If you are sending one recording for a specific hive, the class doesn't matter —
# just pass --hive-id on the command line.
# Edit these IDs to match your actual hive IDs on the hosted database:
HIVE_MAP = {
    1: "normal",      # hive_id 1 → send normal-class clips
    2: "queenless",   # hive_id 2 → send queenless-class clips
}

API_KEY = "BEEGUARD_SECRET_2026"
# ───────────────────────────────────────────────────────────────


def push_result(hive_id: int, prediction: dict, dry_run: bool) -> bool:
    """POST one CNN result to api/update_colony_status.php. Returns True on success."""
    payload = {
        "api_key":        API_KEY,
        "hive_id":        hive_id,
        "colony_status":  prediction["colony_status"],
        "confidence":     prediction["confidence"],
        "prob_normal":    prediction.get("prob_normal",    0),
        "prob_queenless": prediction.get("prob_queenless", 0),
        "prob_swarming":  prediction.get("prob_swarming",  0),
        "inference_ms":   prediction.get("inference_ms",  0),
    }

    if dry_run:
        print(f"  [DRY RUN] Would POST hive_id={hive_id}: "
              f"{prediction['colony_status']} ({prediction['confidence']:.1f}%)")
        return True

    url = f"{HOSTINGER_URL}/api/update_colony_status.php"
    try:
        resp = requests.post(url, json=payload, timeout=15)
        resp.raise_for_status()
        data = resp.json()
        if data.get("success"):
            print(f"  [OK] hive_id={hive_id}: {prediction['colony_status']} "
                  f"({prediction['confidence']:.1f}%) → recorded")
            return True
        else:
            print(f"  [FAIL] hive_id={hive_id}: server said: {data.get('message')}")
            return False
    except requests.RequestException as e:
        print(f"  [ERROR] hive_id={hive_id}: {e}")
        return False


def run_prediction(file_path: str) -> dict:
    """Reuse ml/predict.py logic to run the CNN on a single file."""
    sys.path.insert(0, str(ML_DIR))
    from predict import predict
    return predict(file_path)


def main():
    parser = argparse.ArgumentParser(description="Push CNN results to Hostinger")
    parser.add_argument("file",     nargs="?", help="Single audio file to process")
    parser.add_argument("hive_id",  nargs="?", type=int, help="Hive ID for single-file mode")
    parser.add_argument("--dry-run", action="store_true",
                        help="Predict only — do not POST to server")
    parser.add_argument("--hive-id", dest="hive_id_flag", type=int,
                        help="Override hive_id (single-file or all-files mode)")
    args = parser.parse_args()

    dry_run = args.dry_run
    if dry_run:
        print("[DRY RUN MODE — no data will be sent to the server]\n")

    # ── Single-file mode ───────────────────────────────────────
    if args.file:
        hive_id = args.hive_id or args.hive_id_flag
        if not hive_id:
            print("[ERROR] Provide a hive_id: python push_cnn_results.py file.wav 1")
            sys.exit(1)
        print(f"Processing {Path(args.file).name} → hive_id={hive_id}")
        pred = run_prediction(args.file)
        if "error" in pred:
            print(f"[ERROR] Prediction failed: {pred['error']}")
            sys.exit(1)
        print(f"  Prediction: {pred['colony_status']} ({pred['confidence']:.1f}%)")
        ok = push_result(hive_id, pred, dry_run)
        sys.exit(0 if ok else 1)

    # ── All-files mode ─────────────────────────────────────────
    # Build hive_id → class list from HIVE_MAP
    hive_to_class = {hid: cls for hid, cls in HIVE_MAP.items()}
    if args.hive_id_flag:
        # Only process one hive
        hive_to_class = {args.hive_id_flag: hive_to_class.get(args.hive_id_flag)}

    ok_count = 0
    fail_count = 0

    for hive_id, cls in hive_to_class.items():
        if cls is None:
            print(f"[SKIP] hive_id={hive_id} not in HIVE_MAP — set a class for it")
            continue

        cls_dir = AUDIO / cls
        files   = sorted(list(cls_dir.glob("*.wav")) + list(cls_dir.glob("*.mp3")))
        files   = [f for f in files if f.name != ".gitkeep"]

        if not files:
            print(f"[SKIP] No files in {cls_dir}")
            continue

        # Use the most recent file as the representative sample for this hive
        latest = max(files, key=lambda f: f.stat().st_mtime)
        print(f"\nHive {hive_id} ({cls}): running CNN on {latest.name}")

        pred = run_prediction(str(latest))
        if "error" in pred:
            print(f"  [ERROR] {pred['error']}")
            fail_count += 1
            continue

        print(f"  Prediction: {pred['colony_status']} ({pred['confidence']:.1f}%)")
        ok = push_result(hive_id, pred, dry_run)
        if ok:
            ok_count += 1
        else:
            fail_count += 1
        time.sleep(0.5)  # polite pause between requests

    print(f"\nDone: {ok_count} pushed, {fail_count} failed.")
    sys.exit(0 if fail_count == 0 else 1)


if __name__ == "__main__":
    main()
