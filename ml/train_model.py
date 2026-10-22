"""
BeeGuard IoT - CNN Audio Classification Training Script
Chapter 3 specification: TensorFlow/Keras CNN on 2D MFCC matrices.

Classes: normal, queenless, swarming (3 classes)
Input shape: (40, 216, 1)  -- 40 MFCC coefficients x 216 time frames x 1 channel

Usage:
    cd ml/
    pip install -r requirements.txt
    python train_model.py
"""

import os
import sys
import json
import csv
import numpy as np
import librosa
from pathlib import Path
from datetime import datetime
import warnings
warnings.filterwarnings('ignore')

import tensorflow as tf
from sklearn.model_selection import GroupShuffleSplit
from sklearn.metrics import (classification_report, confusion_matrix,
                             accuracy_score, precision_recall_fscore_support)
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt

# ── Config ─────────────────────────────────────────────────────────
CLASSES       = ['normal', 'queenless', 'swarming']
SAMPLE_RATE   = 22050
DURATION_SEC  = 5
N_MFCC        = 40
HOP_LENGTH    = 512
TARGET_FRAMES = 216   # floor(22050 * 5 / 512) + 1 = 216 (librosa default)

CLIP_SECONDS  = 5
RANDOM_SEED   = 42
BATCH_SIZE    = 16
MAX_EPOCHS    = 30
PATIENCE_ES   = 5
PATIENCE_LR   = 3

DATA_DIR   = Path(__file__).parent / 'data' / 'audio'
MODELS_DIR = Path(__file__).parent / 'models'
MODEL_PATH = MODELS_DIR / 'beeguard_cnn.keras'
MODEL_PATH_H5 = MODELS_DIR / 'beeguard_cnn.h5'

MIN_VAL_CLIPS = 10   # stop with error if any class has fewer than this

# ── Preprocessing ──────────────────────────────────────────────────
def extract_mfcc(y: np.ndarray) -> np.ndarray:
    """Shared preprocessing function: returns (40, T, 1) MFCC matrix."""
    # Pad or trim to exactly 5 seconds
    target_len = SAMPLE_RATE * DURATION_SEC
    if len(y) < target_len:
        y = np.pad(y, (0, target_len - len(y)))
    else:
        y = y[:target_len]

    mfcc = librosa.feature.mfcc(y=y, sr=SAMPLE_RATE, n_mfcc=N_MFCC,
                                  hop_length=HOP_LENGTH)
    # Ensure exactly TARGET_FRAMES columns
    if mfcc.shape[1] < TARGET_FRAMES:
        pad_w = TARGET_FRAMES - mfcc.shape[1]
        mfcc = np.pad(mfcc, ((0, 0), (0, pad_w)))
    else:
        mfcc = mfcc[:, :TARGET_FRAMES]

    return mfcc[:, :, np.newaxis].astype(np.float32)   # (40, 216, 1)


def load_audio(path: str) -> np.ndarray | None:
    """Load audio file as mono, 22050 Hz."""
    try:
        y, _ = librosa.load(path, sr=SAMPLE_RATE, mono=True)
        return y
    except Exception as e:
        print(f"  [WARN] Cannot load {path}: {e}")
        return None


def split_into_clips(y: np.ndarray, sr: int = SAMPLE_RATE,
                     clip_sec: int = CLIP_SECONDS) -> list[np.ndarray]:
    """Split a long audio waveform into non-overlapping 5-second clips."""
    clip_len = sr * clip_sec
    clips = []
    offset = 0
    while offset + clip_len <= len(y):
        clips.append(y[offset: offset + clip_len])
        offset += clip_len
    # Include a partial last clip if at least 1 second remains
    if len(y) - offset >= sr:
        tail = y[offset:]
        tail = np.pad(tail, (0, clip_len - len(tail)))
        clips.append(tail)
    return clips


# ── Dataset Loading ────────────────────────────────────────────────
def load_dataset():
    """
    Scan ml/data/audio/{normal,queenless,swarming}/ for .wav/.mp3 files.
    Each file is treated as one 'recording'; it is split into 5-second clips.
    Returns: X (N, 40, 216, 1), y (N,), groups (N,) — recording ID per clip.
    """
    manifest_path = Path(__file__).parent / 'data' / 'manifest.csv'

    X, y_labels, groups = [], [], []
    recording_id = 0

    clip_counts = {cls: 0 for cls in CLASSES}

    for label in CLASSES:
        class_dir = DATA_DIR / label
        if not class_dir.exists():
            print(f"  [WARN] Directory not found: {class_dir}")
            continue

        audio_files = (list(class_dir.glob('*.wav')) +
                       list(class_dir.glob('*.mp3')))
        if not audio_files:
            print(f"  [WARN] No .wav/.mp3 files in {class_dir}")
            continue

        for file_path in sorted(audio_files):
            wav = load_audio(str(file_path))
            if wav is None:
                continue

            clips = split_into_clips(wav)
            if not clips:
                print(f"  [SKIP] {file_path.name}: too short for 1 clip")
                continue

            print(f"  [OK] {label}/{file_path.name} -> {len(clips)} clip(s)")

            for clip in clips:
                feat = extract_mfcc(clip)
                X.append(feat)
                y_labels.append(CLASSES.index(label))
                groups.append(recording_id)
                clip_counts[label] += 1

            recording_id += 1

    print(f"\n[Data] Total clips: {sum(clip_counts.values())}")
    for cls, cnt in clip_counts.items():
        print(f"       {cls}: {cnt} clips")

    if not X:
        return None, None, None

    return np.array(X), np.array(y_labels), np.array(groups)


# ── CNN Architecture ───────────────────────────────────────────────
def build_cnn(input_shape=(N_MFCC, TARGET_FRAMES, 1), n_classes=3):
    """Paper Chapter 3 CNN — must match exactly."""
    inp = tf.keras.Input(shape=input_shape, name='mfcc_input')

    # Block 1
    x = tf.keras.layers.Conv2D(32, (3, 3), activation='relu',
                                padding='same', name='conv1')(inp)
    x = tf.keras.layers.BatchNormalization(name='bn1')(x)
    x = tf.keras.layers.MaxPooling2D((2, 2), name='pool1')(x)

    # Block 2
    x = tf.keras.layers.Conv2D(64, (3, 3), activation='relu',
                                padding='same', name='conv2')(x)
    x = tf.keras.layers.BatchNormalization(name='bn2')(x)
    x = tf.keras.layers.MaxPooling2D((2, 2), name='pool2')(x)

    # Block 3
    x = tf.keras.layers.Conv2D(128, (3, 3), activation='relu',
                                padding='same', name='conv3')(x)
    x = tf.keras.layers.BatchNormalization(name='bn3')(x)
    x = tf.keras.layers.MaxPooling2D((2, 2), name='pool3')(x)

    # Classification head
    x = tf.keras.layers.GlobalAveragePooling2D(name='gap')(x)
    x = tf.keras.layers.Dense(128, activation='relu', name='fc1')(x)
    x = tf.keras.layers.Dropout(0.4, name='dropout')(x)
    out = tf.keras.layers.Dense(n_classes, activation='softmax', name='output')(x)

    model = tf.keras.Model(inputs=inp, outputs=out, name='beeguard_cnn')
    model.compile(
        optimizer=tf.keras.optimizers.Adam(),
        loss='sparse_categorical_crossentropy',
        metrics=['accuracy'],
    )
    return model


# ── Evaluation Outputs ─────────────────────────────────────────────
def save_confusion_matrix(y_true, y_pred):
    cm = confusion_matrix(y_true, y_pred)

    # PNG heatmap
    fig, ax = plt.subplots(figsize=(6, 5))
    im = ax.imshow(cm, interpolation='nearest', cmap='Blues')
    fig.colorbar(im, ax=ax)
    ax.set(xticks=range(len(CLASSES)), yticks=range(len(CLASSES)),
           xticklabels=CLASSES, yticklabels=CLASSES,
           xlabel='Predicted', ylabel='True', title='Confusion Matrix')
    for i in range(len(CLASSES)):
        for j in range(len(CLASSES)):
            ax.text(j, i, str(cm[i, j]),
                    ha='center', va='center', color='black', fontsize=12)
    plt.tight_layout()
    plt.savefig(MODELS_DIR / 'confusion_matrix.png', dpi=120)
    plt.close()

    # CSV
    with open(MODELS_DIR / 'confusion_matrix.csv', 'w', newline='') as f:
        writer = csv.writer(f)
        writer.writerow([''] + CLASSES)
        for cls, row in zip(CLASSES, cm):
            writer.writerow([cls] + row.tolist())

    print(f"[Eval] Confusion matrix saved")
    return cm


def save_training_history(history):
    fig, axes = plt.subplots(2, 2, figsize=(10, 8))
    axes[0, 0].plot(history.history['loss'], label='Train Loss')
    axes[0, 0].plot(history.history['val_loss'], label='Val Loss')
    axes[0, 0].set_title('Loss')
    axes[0, 0].legend()

    axes[0, 1].plot(history.history['accuracy'], label='Train Acc')
    axes[0, 1].plot(history.history['val_accuracy'], label='Val Acc')
    axes[0, 1].set_title('Accuracy')
    axes[0, 1].legend()

    # Epoch index line for early stopping
    n_epochs = len(history.history['loss'])
    axes[1, 0].bar(range(n_epochs), history.history['loss'], color='steelblue', alpha=0.7, label='Train Loss')
    axes[1, 0].set_title('Train Loss per Epoch')
    axes[1, 0].legend()

    axes[1, 1].bar(range(n_epochs), history.history['accuracy'], color='darkorange', alpha=0.7, label='Train Acc')
    axes[1, 1].set_title('Train Accuracy per Epoch')
    axes[1, 1].legend()

    plt.suptitle('BeeGuard CNN Training History')
    plt.tight_layout()
    plt.savefig(MODELS_DIR / 'training_history.png', dpi=120)
    plt.close()
    print(f"[Eval] Training history plot saved")


def save_metrics(y_true, y_pred, history,
                 train_class_counts, val_class_counts):
    precision, recall, f1, support = precision_recall_fscore_support(
        y_true, y_pred, labels=list(range(len(CLASSES))), zero_division=0)

    per_class = {}
    for i, cls in enumerate(CLASSES):
        per_class[cls] = {
            'precision': round(float(precision[i]), 4),
            'recall':    round(float(recall[i]), 4),
            'f1':        round(float(f1[i]), 4),
            'support':   int(support[i]),
        }

    # Macro averages
    macro = {
        'precision': round(float(np.mean(precision)), 4),
        'recall':    round(float(np.mean(recall)), 4),
        'f1':        round(float(np.mean(f1)), 4),
    }

    accuracy = round(float(accuracy_score(y_true, y_pred)) * 100, 2)
    epochs_run = len(history.history['loss'])

    metrics = {
        'val_accuracy':          accuracy,
        'final_accuracy':        accuracy,
        'per_class':             per_class,
        'macro_avg':             macro,
        'overall_accuracy':      accuracy,
        'train_clip_counts':     {cls: train_class_counts.get(cls, 0) for cls in CLASSES},
        'val_clip_counts':       {cls: val_class_counts.get(cls, 0) for cls in CLASSES},
        'epochs_run':            epochs_run,
        'training_date':         datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
        'classes':               CLASSES,
        'model':                 'BeeGuard CNN (Conv2D x3 + GAP)',
        'sample_count':          sum(train_class_counts.values()) + sum(val_class_counts.values()),
        'trained_at':            datetime.now().strftime('%b %d, %Y %H:%M'),
    }

    with open(MODELS_DIR / 'metrics.json', 'w') as f:
        json.dump(metrics, f, indent=2)
    print(f"[Eval] metrics.json saved  (val_accuracy={accuracy}%)")
    return metrics


def save_classification_report(y_true, y_pred):
    report = classification_report(y_true, y_pred,
                                   target_names=CLASSES,
                                   labels=list(range(len(CLASSES))),
                                   zero_division=0)
    with open(MODELS_DIR / 'classification_report.txt', 'w') as f:
        f.write('BeeGuard CNN Classification Report\n')
        f.write('=' * 50 + '\n')
        f.write(f'Generated: {datetime.now().strftime("%Y-%m-%d %H:%M:%S")}\n\n')
        f.write(report)
    print("[Eval] classification_report.txt saved")
    print("\n[Results]\n" + report)


# ── Main ───────────────────────────────────────────────────────────
def main():
    MODELS_DIR.mkdir(exist_ok=True)
    print("\n=== BeeGuard CNN Training (Chapter 3 Spec) ===\n")

    X, y, groups = load_dataset()

    if X is None or len(X) == 0:
        print("\n[ERROR] No training clips found. "
              "Add .wav/.mp3 files under ml/data/audio/{normal,queenless,swarming}/")
        sys.exit(1)

    print(f"\n[Data] {len(X)} total clips loaded  |  shape: {X[0].shape}")

    # ── 80/20 group split by original_recording_id ──────────────────
    gss = GroupShuffleSplit(n_splits=1, test_size=0.2, random_state=RANDOM_SEED)
    train_idx, val_idx = next(gss.split(X, y, groups=groups))

    X_train, X_val = X[train_idx], X[val_idx]
    y_train, y_val = y[train_idx], y[val_idx]

    # Count per-class clips in splits
    train_counts = {cls: int(np.sum(y_train == i)) for i, cls in enumerate(CLASSES)}
    val_counts   = {cls: int(np.sum(y_val   == i)) for i, cls in enumerate(CLASSES)}

    print(f"\n[Split] Train: {len(X_train)} clips  |  Val: {len(X_val)} clips")
    for cls in CLASSES:
        print(f"        {cls}: train={train_counts[cls]}  val={val_counts[cls]}")

    # Validate minimum validation clips
    for cls in CLASSES:
        if val_counts[cls] < MIN_VAL_CLIPS:
            print(f"\n[ERROR] Class '{cls}' has only {val_counts[cls]} validation clips "
                  f"(minimum required: {MIN_VAL_CLIPS}). "
                  f"Add more recordings for this class and retrain.")
            sys.exit(1)

    # ── Build and train CNN ──────────────────────────────────────────
    model = build_cnn()
    model.summary()

    callbacks = [
        tf.keras.callbacks.EarlyStopping(
            monitor='val_loss', patience=PATIENCE_ES,
            restore_best_weights=True, verbose=1),
        tf.keras.callbacks.ReduceLROnPlateau(
            monitor='val_loss', patience=PATIENCE_LR,
            factor=0.5, min_lr=1e-6, verbose=1),
    ]

    history = model.fit(
        X_train, y_train,
        validation_data=(X_val, y_val),
        epochs=MAX_EPOCHS,
        batch_size=BATCH_SIZE,
        callbacks=callbacks,
        verbose=1,
    )

    # ── Save model ───────────────────────────────────────────────────
    try:
        model.save(MODEL_PATH)
        print(f"[Model] Saved -> {MODEL_PATH}")
    except Exception as e:
        print(f"[WARN] Could not save .keras: {e}. Trying .h5 …")
        model.save(MODEL_PATH_H5)
        print(f"[Model] Saved -> {MODEL_PATH_H5}")

    # ── Evaluate ─────────────────────────────────────────────────────
    y_pred_probs = model.predict(X_val, verbose=0)
    y_pred       = np.argmax(y_pred_probs, axis=1)

    save_confusion_matrix(y_val, y_pred)
    save_metrics(y_val, y_pred, history, train_counts, val_counts)
    save_classification_report(y_val, y_pred)
    save_training_history(history)

    print("\n[Done] Training complete!\n")


if __name__ == "__main__":
    main()
