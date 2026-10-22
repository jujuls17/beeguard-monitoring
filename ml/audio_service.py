"""
BeeGuard IoT - Audio Service
Records 5-second clips from mic (or loads a file), runs CNN inference,
and POSTs colony status to api/update_colony_status.php when confident.

Usage:
    python audio_service.py --hive-id 1
    python audio_service.py --hive-id 1 --device-index 2
    python audio_service.py --list-devices
    python audio_service.py --hive-id 1 --file path/to/audio.wav
    python audio_service.py --hive-id 1 --server http://192.168.1.100/beemonitoring
"""

import argparse
import json
import sys
import time
import warnings
from collections import deque
from pathlib import Path

warnings.filterwarnings('ignore')

import numpy as np
import librosa
import requests

# Shared constants with train_model.py / predict.py
SAMPLE_RATE   = 22050
DURATION_SEC  = 5
N_MFCC        = 40
HOP_LENGTH    = 512
TARGET_FRAMES = 216
CLASSES       = ['normal', 'queenless', 'swarming']

MODELS_DIR    = Path(__file__).parent / 'models'
MODEL_PATH    = MODELS_DIR / 'beeguard_cnn.keras'
MODEL_PATH_H5 = MODELS_DIR / 'beeguard_cnn.h5'

# Consecutive non-normal results needed before posting
CONSECUTIVE_THRESHOLD = 3


# ── Preprocessing ──────────────────────────────────────────────────
def extract_mfcc(y: np.ndarray) -> np.ndarray:
    """Same preprocessing as train_model.py and predict.py."""
    target_len = SAMPLE_RATE * DURATION_SEC
    if len(y) < target_len:
        y = np.pad(y, (0, target_len - len(y)))
    else:
        y = y[:target_len]

    mfcc = librosa.feature.mfcc(y=y, sr=SAMPLE_RATE, n_mfcc=N_MFCC,
                                  hop_length=HOP_LENGTH)
    if mfcc.shape[1] < TARGET_FRAMES:
        mfcc = np.pad(mfcc, ((0, 0), (0, TARGET_FRAMES - mfcc.shape[1])))
    else:
        mfcc = mfcc[:, :TARGET_FRAMES]

    return mfcc[:, :, np.newaxis][np.newaxis, ...].astype(np.float32)


# ── Model ──────────────────────────────────────────────────────────
def load_model():
    try:
        import tensorflow as tf
    except ImportError:
        print("[ERROR] TensorFlow not installed. Run: pip install tensorflow==2.17.0")
        sys.exit(1)

    model_path = MODEL_PATH if MODEL_PATH.exists() else (
        MODEL_PATH_H5 if MODEL_PATH_H5.exists() else None)

    if model_path is None:
        print("[ERROR] No trained model found. Run train_model.py first.")
        sys.exit(1)

    print(f"[Model] Loading {model_path.name} …")
    model = tf.keras.models.load_model(str(model_path), compile=False)
    print("[Model] Ready")
    return model


# ── Inference ──────────────────────────────────────────────────────
def infer(model, y: np.ndarray) -> tuple[str, float, np.ndarray, float]:
    """
    Run CNN on audio array y.
    Returns (colony_status, confidence_pct, probs_array, inference_ms).
    """
    t0 = time.perf_counter()
    X  = extract_mfcc(y)
    probs = model.predict(X, verbose=0)[0]
    inference_ms = round((time.perf_counter() - t0) * 1000, 1)

    class_idx  = int(np.argmax(probs))
    confidence = round(float(probs[class_idx]) * 100, 1)
    return CLASSES[class_idx], confidence, probs, inference_ms


# ── Recording ──────────────────────────────────────────────────────
def record_clip(device_index: int) -> np.ndarray:
    """Record DURATION_SEC seconds from the microphone."""
    try:
        import pyaudio
    except ImportError:
        print("[ERROR] pyaudio not installed. Run: pip install pyaudio")
        sys.exit(1)

    pa    = pyaudio.PyAudio()
    chunk = 1024
    frames = []

    stream = pa.open(format=pyaudio.paInt16,
                     channels=1,
                     rate=SAMPLE_RATE,
                     input=True,
                     input_device_index=device_index,
                     frames_per_buffer=chunk)
    print(f"  Recording {DURATION_SEC}s …", end='', flush=True)
    for _ in range(int(SAMPLE_RATE / chunk * DURATION_SEC)):
        frames.append(stream.read(chunk, exception_on_overflow=False))
    print(" done")

    stream.stop_stream()
    stream.close()
    pa.terminate()

    raw = b''.join(frames)
    y   = np.frombuffer(raw, dtype=np.int16).astype(np.float32) / 32768.0
    return y


def list_audio_devices():
    try:
        import pyaudio
    except ImportError:
        print("[ERROR] pyaudio not installed. Run: pip install pyaudio")
        sys.exit(1)
    pa = pyaudio.PyAudio()
    print("Available audio input devices:")
    for i in range(pa.get_device_count()):
        info = pa.get_device_info_by_index(i)
        if info['maxInputChannels'] > 0:
            print(f"  [{i}] {info['name']}  (channels: {info['maxInputChannels']})")
    pa.terminate()


# ── POST ───────────────────────────────────────────────────────────
def post_status(server: str, hive_id: int, colony_status: str,
                confidence: float, probs: np.ndarray, inference_ms: float):
    url  = server.rstrip('/') + '/api/update_colony_status.php'
    data = {
        'api_key':       'BEEGUARD_SECRET_2026',
        'hive_id':       hive_id,
        'colony_status': colony_status,
        'confidence':    confidence,
        'prob_normal':   round(float(probs[0]) * 100, 1),
        'prob_queenless': round(float(probs[1]) * 100, 1),
        'prob_swarming': round(float(probs[2]) * 100, 1),
        'inference_ms':  inference_ms,
    }
    try:
        resp = requests.post(url, json=data, timeout=10)
        resp_json = resp.json()
        if resp_json.get('success'):
            print(f"  [POST] OK -> {colony_status} ({confidence:.1f}%)")
        else:
            print(f"  [POST] Server error: {resp_json.get('message')}")
    except Exception as e:
        print(f"  [POST] Failed: {e}")


# ── Main ───────────────────────────────────────────────────────────
def main():
    parser = argparse.ArgumentParser(description='BeeGuard Audio Service')
    parser.add_argument('--hive-id',      type=int,   default=0)
    parser.add_argument('--device-index', type=int,   default=0)
    parser.add_argument('--list-devices', action='store_true')
    parser.add_argument('--file',         type=str,   default=None)
    parser.add_argument('--server',       type=str,   default='http://localhost/beemonitoring')
    args = parser.parse_args()

    if args.list_devices:
        list_audio_devices()
        sys.exit(0)

    if args.file is None and args.hive_id == 0:
        parser.error('--hive-id is required (unless --list-devices is used)')

    model = load_model()

    # ── Single-file mode ─────────────────────────────────────────
    if args.file:
        path = args.file
        if not Path(path).exists():
            print(f"[ERROR] File not found: {path}")
            sys.exit(1)

        print(f"[File] Classifying {path} …")
        y, _ = librosa.load(path, sr=SAMPLE_RATE, mono=True)
        status, confidence, probs, inf_ms = infer(model, y)

        print(f"  Colony Status:  {status.upper()}")
        print(f"  Confidence:     {confidence:.1f}%")
        print(f"  Inference Time: {inf_ms} ms")
        print(f"  Probabilities:")
        for cls, p in zip(CLASSES, probs):
            print(f"    {cls:<15} {round(float(p)*100,1)}%")

        if args.hive_id > 0:
            post_status(args.server, args.hive_id, status, confidence, probs, inf_ms)
        sys.exit(0)

    # ── Continuous mic mode ───────────────────────────────────────
    recent_results: deque[str] = deque(maxlen=CONSECUTIVE_THRESHOLD)

    print(f"[Service] Starting continuous monitoring for hive {args.hive_id}")
    print(f"          Server: {args.server}")
    print(f"          Device index: {args.device_index}")
    print(f"          Press Ctrl+C to stop\n")

    while True:
        try:
            y = record_clip(args.device_index)
            status, confidence, probs, inf_ms = infer(model, y)

            print(f"[{time.strftime('%H:%M:%S')}] {status.upper()} ({confidence:.1f}%)  "
                  f"[{inf_ms} ms]")

            recent_results.append(status)

            # Post logic:
            # - Always post when result is normal (immediate dismiss)
            # - Post non-normal only when 3 consecutive identical results
            should_post = False
            if status == 'normal':
                should_post = True
            elif len(recent_results) == CONSECUTIVE_THRESHOLD and \
                    len(set(recent_results)) == 1:
                should_post = True

            if should_post:
                post_status(args.server, args.hive_id, status, confidence, probs, inf_ms)

        except KeyboardInterrupt:
            print("\n[Service] Stopped by user")
            break
        except Exception as e:
            print(f"[WARN] Error in recording loop: {e}")
            time.sleep(2)


if __name__ == '__main__':
    main()
