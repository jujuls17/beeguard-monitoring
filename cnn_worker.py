"""
BeeGuard - Hive Mic Colony Check Worker (runs on the local server / laptop)

What it does, in a loop:
  1. Asks the hosted site for new hive mic clips (each ESP32 records a 5-second clip
     from its MAX4466 mic every few minutes and uploads it).
  2. Downloads each clip and runs the same CNN as ml/predict.py on it, locally.
  3. Sends the result to api/update_colony_status.php, so the Live Hives page shows it.
  4. Tells the site the clip is done, and keeps a local copy in ml/data/hive_mic_clips/
     (useful later as training data from your own hives).

Usage (leave it running during the demo, next to camera_capture.py):
    python cnn_worker.py            # check for new clips every 10 s
    python cnn_worker.py --once     # process what is waiting, then exit
    python cnn_worker.py --dry-run  # classify but don't send results or delete clips

The site URL and API key are taken from push_cnn_results.py, so there is only one place to edit.
"""

import sys
import time
import argparse
from datetime import datetime
from pathlib import Path

import numpy as np
import requests

PROJECT = Path(__file__).parent
sys.path.insert(0, str(PROJECT))
sys.path.insert(0, str(PROJECT / "ml"))

from push_cnn_results import HOSTINGER_URL, API_KEY, push_result   # noqa: E402
from predict import predict, SAMPLE_RATE                           # noqa: E402

CLIPS_API   = f"{HOSTINGER_URL}/api/hive_clips.php"
LOCAL_DIR   = PROJECT / "ml" / "data" / "hive_mic_clips"
HEADERS     = {"X-Api-Key": API_KEY}
POLL_SEC    = 10
MIN_RMS     = 0.01   # quieter than this = mic silent / not connected → don't classify


def log(msg: str) -> None:
    print(f"[{datetime.now():%H:%M:%S}] {msg}", flush=True)


def list_clips() -> list:
    r = requests.get(CLIPS_API, params={"action": "list"}, headers=HEADERS, timeout=15)
    r.raise_for_status()
    data = r.json()
    if not data.get("success"):
        raise RuntimeError(data.get("message", "list failed"))
    return data["clips"]


def download(name: str) -> Path:
    r = requests.get(CLIPS_API, params={"action": "get", "name": name}, headers=HEADERS, timeout=30)
    r.raise_for_status()
    if r.content[:4] != b"RIFF":
        raise RuntimeError("download is not a WAV file")
    LOCAL_DIR.mkdir(parents=True, exist_ok=True)
    path = LOCAL_DIR / name
    path.write_bytes(r.content)
    return path


def mark_done(name: str) -> None:
    requests.post(CLIPS_API, params={"action": "done", "name": name}, headers=HEADERS, timeout=15)


def clip_loudness(path: Path) -> float:
    import librosa
    y, _ = librosa.load(str(path), sr=SAMPLE_RATE, mono=True)
    y = y - float(np.mean(y))
    return float(np.sqrt(np.mean(y ** 2))) if len(y) else 0.0


def process(clip: dict, dry_run: bool) -> None:
    name, hive_id = clip["name"], clip["hive_id"]
    path = download(name)
    rms = clip_loudness(path)
    if rms < MIN_RMS:
        log(f"Hive {hive_id}: {name} is almost silent (level {rms:.4f}) - mic not hearing anything? Skipped.")
        if not dry_run:
            mark_done(name)
        return

    pred = predict(str(path))
    if "error" in pred:
        log(f"Hive {hive_id}: CNN failed on {name}: {pred['error']} (clip kept on server, will retry)")
        return

    probs = ", ".join(f"{k} {v:.0f}%" for k, v in pred["all_probabilities"].items())
    log(f"Hive {hive_id}: {name} -> {pred['colony_status'].upper()} "
        f"({pred['confidence']:.1f}%)  [{probs}]  {pred['inference_ms']:.0f} ms")
    if push_result(hive_id, pred, dry_run) and not dry_run:
        mark_done(name)


def main() -> None:
    ap = argparse.ArgumentParser(description="Classify hive mic clips with the CNN and send the results")
    ap.add_argument("--once", action="store_true", help="process waiting clips, then exit")
    ap.add_argument("--dry-run", action="store_true", help="classify only; don't send or delete")
    args = ap.parse_args()

    log("BeeGuard colony check worker started. Waiting for hive mic clips... (Ctrl+C to stop)")
    seen = set()   # in --dry-run, clips stay on the server; don't classify them again
    while True:
        try:
            clips = list_clips()
            for clip in clips:
                if args.dry_run and clip["name"] in seen:
                    continue
                process(clip, args.dry_run)
                seen.add(clip["name"])
        except requests.RequestException as e:
            log(f"Can't reach the site ({e.__class__.__name__}). Retrying in {POLL_SEC} s.")
        except Exception as e:   # keep running during the demo no matter what
            log(f"Error: {e}")
        if args.once:
            break
        time.sleep(POLL_SEC)


if __name__ == "__main__":
    main()
