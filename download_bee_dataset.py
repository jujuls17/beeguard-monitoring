"""
BeeGuard - Download labeled bee images from Google Open Images Dataset
No API key, no account needed. Uses public Google Cloud Storage URLs.

Usage:
    python download_bee_dataset.py
"""

import os
import sys
import csv
import io
import time
import urllib.request
from pathlib import Path

OUTPUT_DIR  = Path("dataset/bee_images")
MAX_IMAGES  = 1000   # max bee images to download

# Google Open Images public URLs
BASE_URL   = "https://storage.googleapis.com/openimages/v6"
TRAIN_BBOX = f"{BASE_URL}/oidv6-class-descriptions.csv"
VAL_BBOX   = "https://storage.googleapis.com/openimages/v5/validation-annotations-bbox.csv"
VAL_IMGS   = "https://storage.googleapis.com/openimages/2018_04/validation/validation-images-boxable.csv"
TRAIN_BBOX_URL = "https://storage.googleapis.com/openimages/v6/train-annotations-bbox.csv"
TRAIN_IMGS_URL = "https://storage.googleapis.com/openimages/2018_04/train/train-images-boxable-segm.csv"

BEE_LABEL  = "/m/09dzg"   # Open Images class ID for "Bee"


def fetch_csv(url, label="", retries=3):
    cache_file = Path("dataset") / (url.split("/")[-1])
    cache_file.parent.mkdir(parents=True, exist_ok=True)

    # Use cached file if already downloaded
    if cache_file.exists() and cache_file.stat().st_size > 1000:
        print(f"[FETCH] {label} (cached) ✅")
        return cache_file.read_text(encoding="utf-8")

    print(f"[FETCH] {label} (this may take a few minutes for large files)...")
    for attempt in range(1, retries + 1):
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
            with urllib.request.urlopen(req, timeout=120) as r:
                total = int(r.headers.get("Content-Length", 0))
                downloaded = 0
                chunks = []
                while True:
                    chunk = r.read(1024 * 512)  # 512 KB chunks
                    if not chunk:
                        break
                    chunks.append(chunk)
                    downloaded += len(chunk)
                    if total:
                        pct = downloaded / total * 100
                        print(f"  {pct:.0f}%  ({downloaded//1024//1024} MB)", end="\r")
                data = b"".join(chunks).decode("utf-8")
                cache_file.write_text(data, encoding="utf-8")
                print(f"\n  ✅ Done ({downloaded//1024//1024} MB)")
                return data
        except Exception as e:
            print(f"\n  Attempt {attempt}/{retries} failed: {e}")
            if attempt < retries:
                print("  Retrying in 5 seconds...")
                time.sleep(5)
    raise RuntimeError(f"Failed to fetch {url} after {retries} attempts")


def download_image(url, dest_path):
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    try:
        with urllib.request.urlopen(req, timeout=15) as r:
            dest_path.write_bytes(r.read())
        return True
    except Exception:
        return False


def run():
    img_dir   = OUTPUT_DIR / "images"
    label_dir = OUTPUT_DIR / "labels"
    img_dir.mkdir(parents=True, exist_ok=True)
    label_dir.mkdir(parents=True, exist_ok=True)

    # ── Step 1: Get validation bounding boxes for Bee ──────────
    print("\n[STEP 1] Loading bee bounding box annotations...")
    bbox_csv = fetch_csv(VAL_BBOX, "validation bounding boxes")

    bee_boxes = {}   # image_id → list of (x1,y1,x2,y2)
    reader = csv.DictReader(io.StringIO(bbox_csv))
    for row in reader:
        if row["LabelName"] != BEE_LABEL:
            continue
        img_id = row["ImageID"]
        box = (
            float(row["XMin"]), float(row["YMin"]),
            float(row["XMax"]), float(row["YMax"]),
        )
        bee_boxes.setdefault(img_id, []).append(box)

    print(f"  Found {len(bee_boxes)} validation images with bees")

    # ── Step 2: Get image download URLs ────────────────────────
    print("\n[STEP 2] Loading image URLs...")
    imgs_csv = fetch_csv(VAL_IMGS, "validation image list")

    id_to_url = {}
    reader = csv.DictReader(io.StringIO(imgs_csv))
    for row in reader:
        img_id = row.get("ImageID") or row.get("image_name", "").replace(".jpg", "")
        url    = row.get("OriginalURL") or row.get("original_url", "")
        if img_id and url:
            id_to_url[img_id] = url

    # ── Step 3: Download images + save YOLO labels ─────────────
    targets = [(img_id, boxes) for img_id, boxes in bee_boxes.items()
               if img_id in id_to_url][:MAX_IMAGES]

    print(f"\n[STEP 3] Downloading {len(targets)} bee images...")
    saved = 0
    for i, (img_id, boxes) in enumerate(targets):
        url      = id_to_url[img_id]
        img_path = img_dir / f"bee_{img_id}.jpg"
        lbl_path = label_dir / f"bee_{img_id}.txt"

        if img_path.exists():
            saved += 1
            continue

        ok = download_image(url, img_path)
        if not ok:
            continue

        # Write YOLO label (boxes are already normalized 0-1)
        lines = []
        for (x1, y1, x2, y2) in boxes:
            xc = (x1 + x2) / 2
            yc = (y1 + y2) / 2
            bw = x2 - x1
            bh = y2 - y1
            lines.append(f"0 {xc:.6f} {yc:.6f} {bw:.6f} {bh:.6f}")
        lbl_path.write_text("\n".join(lines))
        saved += 1

        if (i + 1) % 50 == 0:
            print(f"  {i+1}/{len(targets)} downloaded...")

    print(f"\n✅ Done! {saved} labeled bee images saved to {OUTPUT_DIR}/images/")
    return saved


def create_yaml(count):
    yaml_path = OUTPUT_DIR / "data.yaml"
    yaml_path.write_text(
        f"path: {OUTPUT_DIR.absolute()}\n"
        f"train: images\n"
        f"val: images\n"
        f"\nnc: 1\nnames: ['bee']\n"
    )
    print(f"[YAML] {yaml_path} created.")
    print(f"\n   Dataset ready! {count} images.")
    print("   Now run:  python train_bee_model.py")


if __name__ == "__main__":
    print("=" * 55)
    print("  BeeGuard — Open Images Bee Downloader")
    print("  No API key needed!")
    print("=" * 55)
    count = run()
    if count > 0:
        create_yaml(count)
    else:
        print("❌ No images downloaded. Check internet connection.")
