"""
BeeGuard IoT - CNN Prediction Script
Classifies a bee sound file using the trained CNN model.

Usage:
    python predict.py <path_to_audio_file> [--json]
    python predict.py ../uploads/sounds/sound_abc123.wav --json
"""

import sys
import json as _json
import time
import numpy as np
import librosa
from pathlib import Path
import warnings
warnings.filterwarnings('ignore')

CLASSES      = ['normal', 'queenless', 'swarming']
SAMPLE_RATE  = 22050
DURATION_SEC = 5
N_MFCC       = 40
HOP_LENGTH   = 512
TARGET_FRAMES = 216

MODELS_DIR   = Path(__file__).parent / 'models'
MODEL_PATH   = MODELS_DIR / 'beeguard_cnn.keras'
MODEL_PATH_H5 = MODELS_DIR / 'beeguard_cnn.h5'


def _get_model_path():
    if MODEL_PATH.exists():
        return MODEL_PATH
    if MODEL_PATH_H5.exists():
        return MODEL_PATH_H5
    return None


def extract_mfcc(y: np.ndarray) -> np.ndarray:
    """Shared preprocessing: returns (1, 40, 216, 1) batched MFCC tensor."""
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

    return mfcc[:, :, np.newaxis][np.newaxis, ...].astype(np.float32)  # (1,40,216,1)


def analyze_frequency(y: np.ndarray, sr: int = SAMPLE_RATE) -> dict:
    """Return basic frequency statistics without altering colony_status."""
    try:
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
            'dominant_freq':     dominant_freq,
            'mean_freq':         mean_freq,
            'spectral_centroid': spectral_centroid,
            'freq_min':          freq_min,
            'freq_max':          freq_max,
            'sound_level':       sound_level,
            'duration_sec':      round(len(y) / sr, 2),
            'sample_rate':       sr,
        }
    except Exception as e:
        return {'error': str(e)}


def predict(file_path: str) -> dict:
    """
    Run CNN inference on a single audio file.
    Returns dict with colony_status, confidence, all_probabilities, frequency.
    """
    model_path = _get_model_path()
    if model_path is None:
        return {'error': 'Model not found. Run train_model.py first.'}
    if not Path(file_path).exists():
        return {'error': f'File not found: {file_path}'}

    # Lazy-import tensorflow to avoid slow startup when not needed
    try:
        import tensorflow as tf
    except ImportError:
        return {'error': 'TensorFlow is not installed. Run: pip install tensorflow==2.17.0'}

    try:
        model = tf.keras.models.load_model(str(model_path), compile=False)
    except Exception as e:
        return {'error': f'Failed to load model: {e}'}

    try:
        y, _ = librosa.load(file_path, sr=SAMPLE_RATE, mono=True)
    except Exception as e:
        return {'error': f'Failed to load audio: {e}'}

    t_start = time.perf_counter()
    X = extract_mfcc(y)
    probs = model.predict(X, verbose=0)[0]
    inference_ms = round((time.perf_counter() - t_start) * 1000, 1)

    class_idx  = int(np.argmax(probs))
    confidence = round(float(probs[class_idx]) * 100, 1)

    all_probs = {cls: round(float(p) * 100, 1)
                 for cls, p in zip(CLASSES, probs)}

    freq_info = analyze_frequency(y)

    return {
        'file':              file_path,
        'colony_status':     CLASSES[class_idx],
        'confidence':        confidence,
        'all_probabilities': all_probs,
        'prob_normal':       round(float(probs[0]) * 100, 1),
        'prob_queenless':    round(float(probs[1]) * 100, 1),
        'prob_swarming':     round(float(probs[2]) * 100, 1),
        'inference_ms':      inference_ms,
        'frequency':         freq_info,
    }


if __name__ == '__main__':
    json_mode = '--json' in sys.argv
    pos_args  = [a for a in sys.argv[1:] if not a.startswith('--')]

    if not pos_args:
        print('Usage: python predict.py <audio_file> [--json]')
        sys.exit(1)

    result = predict(pos_args[0])

    if json_mode:
        print(_json.dumps(result))
        sys.exit(1 if 'error' in result else 0)

    if 'error' in result:
        print(f"[ERROR] {result['error']}")
        sys.exit(1)

    print(f"\n=== BeeGuard CNN Prediction ===")
    print(f"File:           {result['file']}")
    print(f"Colony Status:  {result['colony_status'].upper()}")
    print(f"Confidence:     {result['confidence']}%")
    print(f"Inference Time: {result['inference_ms']} ms")

    freq = result.get('frequency', {})
    if freq and 'error' not in freq:
        print(f"\nFrequency Analysis:")
        print(f"  Dominant Freq : {freq.get('dominant_freq')} Hz")
        print(f"  Mean Freq     : {freq.get('mean_freq')} Hz")
        print(f"  Spectral Cent.: {freq.get('spectral_centroid')} Hz")
        print(f"  Freq Range    : {freq.get('freq_min')} - {freq.get('freq_max')} Hz")
        print(f"  Sound Level   : {freq.get('sound_level')}%")
        print(f"  Duration      : {freq.get('duration_sec')}s @ {freq.get('sample_rate')} Hz")

    print(f"\nAll probabilities:")
    for label, prob in sorted(result['all_probabilities'].items(), key=lambda x: -x[1]):
        bar = chr(9608) * int(prob / 5)
        print(f"  {label:<25} {bar:<20} {prob:.1f}%")
    print()
