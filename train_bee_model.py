"""
BeeGuard - Train bee/insect detection model
Uses merged dataset (dataset/combined/) with 2 classes:
    0 = bee     (nonpollenbee + pollenbee)
    1 = insect  (non-bee insects: wasp, fly, etc.)

How to get the dataset:
    1. Go to roboflow.com/universe → search "bee detection"
    2. Pick a dataset → Versions → Export → YOLOv8 → Download ZIP
    3. Extract the ZIP into a folder, e.g.:
           C:/xampp/htdocs/beemonitoring/dataset/roboflow/
       The folder should contain:
           data.yaml
           train/images/  train/labels/
           valid/images/  valid/labels/
    4. Set DATASET_YAML below to the path of data.yaml
    5. Run:  python train_bee_model.py

Usage:
    python train_bee_model.py
"""

import sys
import shutil
from pathlib import Path

# ── Config — EDIT THESE ───────────────────────────────────────
DATASET_YAML = "dataset/combined/data.yaml"   # ← merged bee + insect dataset
BASE_MODEL   = "yolov8s.pt"                   # YOLOv8 small — more accurate than nano
                                               # change to "models/bee_insect.pt" to fine-tune existing
EPOCHS       = 100
IMG_SIZE     = 640
BATCH        = 8        # lower to 4 if GPU runs out of memory
DEVICE       = "0"      # "0" = first GPU | "cpu" = CPU only
OUTPUT_DIR   = "models/trained"
# ──────────────────────────────────────────────────────────────


def check_dependencies():
    missing = []
    try:
        import ultralytics
    except ImportError:
        missing.append("ultralytics")
    if missing:
        print(f"[SETUP] Installing: {', '.join(missing)}")
        import os
        os.system(f"pip install {' '.join(missing)}")
        print("[SETUP] Done.\n")


def validate_dataset(yaml_path: str) -> bool:
    p = Path(yaml_path)
    if not p.exists():
        print(f"❌ data.yaml not found at: {yaml_path}")
        print()
        print("Steps to fix:")
        print("  1. Download a bee dataset from roboflow.com/universe")
        print("  2. Export as YOLOv8 format → Download ZIP")
        print("  3. Extract to:  dataset/roboflow/")
        print("  4. Make sure data.yaml is at:", yaml_path)
        return False

    # Read and show what classes are in the dataset
    content = p.read_text()
    print(f"[DATASET] Found data.yaml at {yaml_path}")
    for line in content.splitlines():
        if line.strip().startswith("nc:") or line.strip().startswith("names:"):
            print(f"  {line.strip()}")

    # Warn if nc != 2
    for line in content.splitlines():
        if line.strip().startswith("nc:"):
            nc = line.split(":")[1].strip()
            if nc != "2":
                print(f"\n⚠  Dataset has {nc} class(es). Expected 2 (bee, insect).")
                print("   Training will still work — just the class count will differ.")
    print()
    return True


def train(yaml_path: str):
    from ultralytics import YOLO

    print(f"[TRAIN] Starting training:")
    print(f"  Dataset    : {yaml_path}")
    print(f"  Base model : {BASE_MODEL}")
    print(f"  Epochs     : {EPOCHS}")
    print(f"  Image size : {IMG_SIZE}")
    print(f"  Batch      : {BATCH}")
    print(f"  Device     : {DEVICE}")
    print()

    model = YOLO(BASE_MODEL)
    model.train(
        data      = yaml_path,
        epochs    = EPOCHS,
        imgsz     = IMG_SIZE,
        batch     = BATCH,
        device    = DEVICE,
        project   = OUTPUT_DIR,
        name      = "bee_3class",
        exist_ok  = True,
        patience  = 15,
        augment   = True,
        cache     = True,
    )

    best = Path(OUTPUT_DIR) / "bee_3class" / "weights" / "best.pt"
    if best.exists():
        dest = Path("models") / "bee_insect.pt"
        dest.parent.mkdir(exist_ok=True)
        shutil.copy(best, dest)
        print(f"\n✅ Training complete!")
        print(f"   New model saved → {dest}")
        print(f"\n   Restart camera_capture.py to use the updated model.")
    else:
        print(f"\n⚠  best.pt not found at {best}")
        print(f"   Check {OUTPUT_DIR}/bee_3class/weights/ manually.")


if __name__ == "__main__":
    print("=" * 55)
    print("  BeeGuard — 2-Class Bee/Insect Detector Trainer")
    print("  Classes: bee | insect")
    print("=" * 55)
    print()

    check_dependencies()

    if not validate_dataset(DATASET_YAML):
        sys.exit(1)

    train(DATASET_YAML)
