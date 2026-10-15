"""
BeeGuard IoT - ML Model Training Script
Trains an MLP classifier on uploaded bee sound samples.
Uses scikit-learn (no TensorFlow) — works on Python 3.13+.

Usage:
    cd ml/
    pip install -r requirements.txt
    python train_model.py
"""

import os
import sys
import json
import numpy as np
import librosa
import mysql.connector
import joblib
from pathlib import Path
from datetime import datetime

from sklearn.neural_network import MLPClassifier
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder, StandardScaler
from sklearn.metrics import classification_report, accuracy_score

# ── Config ────────────────────────────────────────────────────
DB_CONFIG = {
    "host":     "localhost",
    "user":     "root",
    "password": "",
    "database": "bee_monitoring",
}

SOUNDS_DIR   = Path(__file__).parent.parent / "uploads" / "sounds"
MODELS_DIR   = Path(__file__).parent / "models"
MODEL_PATH   = MODELS_DIR / "beeguard_mlp.pkl"
SCALER_PATH  = MODELS_DIR / "scaler.pkl"
ENCODER_PATH = MODELS_DIR / "label_encoder.pkl"

CLASSES      = ["normal", "queenless", "swarming", "varroa_mite"]
SAMPLE_RATE  = 22050
DURATION_SEC = 5
N_MFCC       = 40

# ── Feature Extraction ────────────────────────────────────────
def extract_features(file_path: str) -> np.ndarray | None:
    try:
        y, sr = librosa.load(file_path, sr=SAMPLE_RATE, duration=DURATION_SEC, mono=True)
        target_len = SAMPLE_RATE * DURATION_SEC
        if len(y) < target_len:
            y = np.pad(y, (0, target_len - len(y)))
        else:
            y = y[:target_len]

        mfcc     = librosa.feature.mfcc(y=y, sr=sr, n_mfcc=N_MFCC)
        chroma   = librosa.feature.chroma_stft(y=y, sr=sr)
        zcr      = librosa.feature.zero_crossing_rate(y)
        rms      = librosa.feature.rms(y=y)

        # Flatten each feature to mean + std = compact fixed-size vector
        features = np.concatenate([
            mfcc.mean(axis=1), mfcc.std(axis=1),
            chroma.mean(axis=1), chroma.std(axis=1),
            zcr.mean(axis=1),   zcr.std(axis=1),
            rms.mean(axis=1),   rms.std(axis=1),
        ])
        return features
    except Exception as e:
        print(f"  [WARN] Could not process {file_path}: {e}")
        return None

# ── Load Samples from DB ──────────────────────────────────────
def load_dataset():
    conn = mysql.connector.connect(**DB_CONFIG)
    cur  = conn.cursor(dictionary=True)
    placeholders = ','.join(['%s'] * len(CLASSES))
    cur.execute(
        f"SELECT stored_filename, classification FROM sound_uploads "
        f"WHERE classification IN ({placeholders}) ORDER BY uploaded_at",
        CLASSES,
    )
    rows = cur.fetchall()
    cur.close()
    conn.close()

    X, y = [], []
    print(f"[Data] Found {len(rows)} records in DB")

    for row in rows:
        path = SOUNDS_DIR / row["stored_filename"]
        if not path.exists():
            print(f"  [SKIP] File not found: {path.name}")
            continue
        features = extract_features(str(path))
        if features is not None:
            X.append(features)
            y.append(row["classification"])
            print(f"  [OK]  {path.name}  ->  {row['classification']}")

    return np.array(X), np.array(y)

# ── Main ──────────────────────────────────────────────────────
def main():
    MODELS_DIR.mkdir(exist_ok=True)
    print("\n=== BeeGuard MLP Training ===\n")

    X, y_raw = load_dataset()

    if len(X) == 0:
        print("\n[ERROR] No training samples found. Upload sound files via Settings first.")
        sys.exit(1)

    print(f"\n[Data] Loaded {len(X)} samples across classes: {np.unique(y_raw)}")

    # Encode labels
    le = LabelEncoder()
    y  = le.fit_transform(y_raw)
    joblib.dump(le, ENCODER_PATH)
    print(f"[Data] Label encoder saved -> {ENCODER_PATH}")

    # Scale features
    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(X)
    joblib.dump(scaler, SCALER_PATH)
    print(f"[Data] Scaler saved -> {SCALER_PATH}")

    # Split
    class_counts = np.bincount(y)
    can_stratify  = bool(np.all(class_counts >= 2))
    X_train, X_val, y_train, y_val = train_test_split(
        X_scaled, y, test_size=0.2, random_state=42,
        stratify=y if can_stratify else None
    )
    print(f"[Train] {len(X_train)} train  |  {len(X_val)} val  |  stratify={can_stratify}")

    # Train MLP
    model = MLPClassifier(
        hidden_layer_sizes=(256, 128, 64),
        activation='relu',
        solver='adam',
        max_iter=500,
        early_stopping=True,
        validation_fraction=0.1,
        n_iter_no_change=15,
        random_state=42,
        verbose=True,
    )
    model.fit(X_train, y_train)

    # Evaluate
    y_pred = model.predict(X_val)
    all_labels = list(range(len(le.classes_)))
    report = classification_report(
        y_val, y_pred,
        labels=all_labels,
        target_names=le.classes_,
        zero_division=0,
        output_dict=True,
    )
    print("\n[Results]\n" + classification_report(
        y_val, y_pred,
        labels=all_labels,
        target_names=le.classes_,
        zero_division=0,
    ))

    val_acc = round(float(accuracy_score(y_val, y_pred)) * 100, 1)

    # Save model
    joblib.dump(model, MODEL_PATH)
    print(f"[Model] Saved -> {MODEL_PATH}")

    # Save metrics
    metrics = {
        "val_accuracy":   val_acc,
        "final_accuracy": val_acc,
        "sample_count":   len(X),
        "classes":        le.classes_.tolist(),
        "trained_at":     datetime.now().strftime("%b %d, %Y %H:%M"),
        "per_class":      {k: round(v['f1-score'] * 100, 1)
                           for k, v in report.items()
                           if k in le.classes_},
    }
    with open(MODELS_DIR / "metrics.json", "w") as f:
        json.dump(metrics, f)
    print(f"[Metrics] val_accuracy={val_acc}%")
    print("[Done] Training complete!\n")

if __name__ == "__main__":
    main()
