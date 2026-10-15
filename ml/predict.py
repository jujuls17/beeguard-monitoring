"""
BeeGuard IoT - ML Prediction Script
Classifies a bee sound file using the trained MLP model.

Usage:
    python predict.py <path_to_audio_file>
    python predict.py ../uploads/sounds/sound_abc123.wav [--json]
"""

import sys
import numpy as np
import librosa
import joblib
from pathlib import Path

MODEL_PATH   = Path(__file__).parent / "models" / "beeguard_mlp.pkl"
SCALER_PATH  = Path(__file__).parent / "models" / "scaler.pkl"
ENCODER_PATH = Path(__file__).parent / "models" / "label_encoder.pkl"

SAMPLE_RATE  = 22050
DURATION_SEC = 5
N_MFCC       = 40

def extract_features(file_path: str) -> np.ndarray:
    y, sr = librosa.load(file_path, sr=SAMPLE_RATE, duration=DURATION_SEC, mono=True)
    target_len = SAMPLE_RATE * DURATION_SEC
    if len(y) < target_len:
        y = np.pad(y, (0, target_len - len(y)))
    else:
        y = y[:target_len]

    mfcc   = librosa.feature.mfcc(y=y, sr=sr, n_mfcc=N_MFCC)
    chroma = librosa.feature.chroma_stft(y=y, sr=sr)
    zcr    = librosa.feature.zero_crossing_rate(y)
    rms    = librosa.feature.rms(y=y)

    return np.concatenate([
        mfcc.mean(axis=1), mfcc.std(axis=1),
        chroma.mean(axis=1), chroma.std(axis=1),
        zcr.mean(axis=1),   zcr.std(axis=1),
        rms.mean(axis=1),   rms.std(axis=1),
    ])

def analyze_frequency(file_path: str) -> dict:
    try:
        y, sr = librosa.load(file_path, sr=SAMPLE_RATE, duration=DURATION_SEC, mono=True)

        rms         = float(np.sqrt(np.mean(y ** 2)))
        sound_level = round(min(rms * 500, 100.0), 1)

        fft_mag  = np.abs(np.fft.rfft(y))
        fft_freq = np.fft.rfftfreq(len(y), d=1.0 / sr)

        dominant_idx  = int(np.argmax(fft_mag))
        dominant_freq = round(float(fft_freq[dominant_idx]), 1)

        total_mag = float(np.sum(fft_mag)) or 1.0
        mean_freq = round(float(np.sum(fft_freq * fft_mag) / total_mag), 1)

        threshold   = float(np.max(fft_mag)) * 0.01
        active_freq = fft_freq[fft_mag > threshold]
        freq_min    = round(float(active_freq.min()), 1) if len(active_freq) else 0.0
        freq_max    = round(float(active_freq.max()), 1) if len(active_freq) else 0.0

        centroid          = librosa.feature.spectral_centroid(y=y, sr=sr)
        spectral_centroid = round(float(np.mean(centroid)), 1)

        return {
            "dominant_freq":     dominant_freq,
            "mean_freq":         mean_freq,
            "spectral_centroid": spectral_centroid,
            "freq_min":          freq_min,
            "freq_max":          freq_max,
            "sound_level":       sound_level,
            "duration_sec":      round(len(y) / sr, 2),
            "sample_rate":       sr,
        }
    except Exception as e:
        return {"error": str(e)}

def predict(file_path: str) -> dict:
    if not MODEL_PATH.exists():
        return {"error": "Model not found. Run train_model.py first."}
    if not Path(file_path).exists():
        return {"error": f"File not found: {file_path}"}

    model   = joblib.load(MODEL_PATH)
    scaler  = joblib.load(SCALER_PATH)
    le      = joblib.load(ENCODER_PATH)

    features = extract_features(file_path)
    X        = scaler.transform([features])

    probs      = model.predict_proba(X)[0]
    class_idx  = int(np.argmax(probs))
    confidence = float(probs[class_idx]) * 100

    all_probs = {label: round(float(p) * 100, 1)
                 for label, p in zip(le.classes_, probs)}

    freq_info = analyze_frequency(file_path)

    return {
        "file":              file_path,
        "colony_status":     le.classes_[class_idx],
        "confidence":        round(confidence, 1),
        "all_probabilities": all_probs,
        "frequency":         freq_info,
    }

if __name__ == "__main__":
    import json as _json

    json_mode = "--json" in sys.argv
    pos_args  = [a for a in sys.argv[1:] if not a.startswith("--")]

    if not pos_args:
        print("Usage: python predict.py <audio_file> [--json]")
        sys.exit(1)

    result = predict(pos_args[0])

    if json_mode:
        print(_json.dumps(result))
        sys.exit(1 if "error" in result else 0)

    if "error" in result:
        print(f"[ERROR] {result['error']}")
        sys.exit(1)

    print(f"\n=== BeeGuard Prediction ===")
    print(f"File:           {result['file']}")
    print(f"Colony Status:  {result['colony_status'].upper()}")
    print(f"Confidence:     {result['confidence']}%")
    freq = result.get("frequency", {})
    if freq and "error" not in freq:
        print(f"\nFrequency Analysis:")
        print(f"  Dominant Freq : {freq.get('dominant_freq')} Hz")
        print(f"  Mean Freq     : {freq.get('mean_freq')} Hz")
        print(f"  Spectral Cent.: {freq.get('spectral_centroid')} Hz")
        print(f"  Freq Range    : {freq.get('freq_min')} – {freq.get('freq_max')} Hz")
        print(f"  Sound Level   : {freq.get('sound_level')}%")
        print(f"  Duration      : {freq.get('duration_sec')}s @ {freq.get('sample_rate')} Hz")
    print(f"\nAll probabilities:")
    for label, prob in sorted(result["all_probabilities"].items(), key=lambda x: -x[1]):
        bar = "█" * int(prob / 5)
        print(f"  {label:<25} {bar:<20} {prob:.1f}%")
    print()
