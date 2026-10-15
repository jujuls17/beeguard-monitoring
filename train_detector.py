"""
BeeGuard — Custom Bee + Insect Detector Training
=================================================
NO API KEY OR ACCOUNT REQUIRED.

Downloads labeled images from two completely free public sources:

  Source A  HuggingFace  keremberke/bee-detection   ~2,000 labeled bee photos
  Source B  Open Images  (Google, via fiftyone)       ~2,000 labeled insect photos
                         Bee, Wasp, Hornet, Moth, Fly, Hoverfly

Total: ~4,000 labeled images.

Trains YOLOv8s to detect:
  bee    → GREEN box        wasp / hornet / moth / fly → RED box

Output: models/bee_insect.pt
        Restart camera_capture.py — it auto-loads the trained model.

────────────────────────────────────────
 SETUP (run once in terminal)
────────────────────────────────────────
  pip install ultralytics datasets fiftyone Pillow

 RUN
────────────────────────────────────────
  python train_detector.py

 Optional flags:
   --epochs 60     training rounds   (default 60)
   --imgsz  640    image size px     (default 640)
   --device cpu    force CPU         (default: auto GPU if available)
   --skip-oi       skip Open Images  (saves time, ~1,000 fewer images)
"""

import argparse, os, sys, shutil, yaml, time
from pathlib import Path
import numpy as np

# ── CLI ───────────────────────────────────────────────────────
p = argparse.ArgumentParser()
p.add_argument('--epochs',   type=int, default=20)
p.add_argument('--imgsz',    type=int, default=320)
p.add_argument('--device',   type=str, default='')
p.add_argument('--skip-oi',  action='store_true')
p.add_argument('--max-imgs', type=int, default=500)
args = p.parse_args()

# ── Paths ─────────────────────────────────────────────────────
ROOT       = Path(__file__).parent
MODELS_DIR = ROOT / 'models'
DATA_DIR   = ROOT / 'training_data'
MERGED     = DATA_DIR / 'merged'
MODELS_DIR.mkdir(exist_ok=True)
DATA_DIR.mkdir(exist_ok=True)

for split in ('train', 'valid'):
    (MERGED / split / 'images').mkdir(parents=True, exist_ok=True)
    (MERGED / split / 'labels').mkdir(parents=True, exist_ok=True)

# ── Canonical classes ─────────────────────────────────────────
CANONICAL = ['bee', 'wasp', 'hornet', 'moth', 'fly', 'insect']

def normalise(raw: str) -> str:
    r = raw.lower().strip()
    if any(x in r for x in ('honeybee','bumblebee','bee','hoverfly')): return 'bee'
    if 'wasp'   in r: return 'wasp'
    if 'hornet' in r: return 'hornet'
    if 'moth'   in r or 'butterfly' in r: return 'moth'
    if any(x in r for x in ('fly','mosquito','gnat')): return 'fly'
    return 'insect'

# ── Check deps ────────────────────────────────────────────────
try:
    from ultralytics import YOLO
except ImportError:
    print('[ERROR] Run:  pip install ultralytics'); sys.exit(1)

try:
    from PIL import Image
except ImportError:
    print('[ERROR] Run:  pip install Pillow'); sys.exit(1)

try:
    import datasets as hf_datasets
except ImportError:
    print('[ERROR] Run:  pip install datasets'); sys.exit(1)

import torch
device = args.device or ('cuda' if torch.cuda.is_available() else 'cpu')

print('\n' + '='*60)
print('  BeeGuard — Free Multi-Source Training (no API key)')
print(f'  Device : {device}   Epochs : {args.epochs}')
print('='*60)

file_idx = 0   # global image counter (avoids name collisions)

# ═══════════════════════════════════════════════════════════════
#  SOURCE A — HuggingFace: keremberke/bee-detection
#  ~2,000 labeled honeybee images, completely free, no login
# ═══════════════════════════════════════════════════════════════

HF_DEST = DATA_DIR / 'hf_bee_yolo'

if HF_DEST.exists() and any(HF_DEST.rglob('*.jpg')):
    print('\n[SKIP] HuggingFace bee dataset already on disk')
else:
    print('\n[DOWNLOAD] HuggingFace keremberke/bee-detection ...')
    print('           ~120 MB, downloading once only')
    try:
        ds = hf_datasets.load_dataset(
            'keremberke/bee-detection',
            'full',
            trust_remote_code=True,
        )

        for split_name, hf_split in ds.items():
            yolo_split = 'valid' if split_name == 'validation' else 'train'
            img_dir = HF_DEST / yolo_split / 'images'
            lbl_dir = HF_DEST / yolo_split / 'labels'
            img_dir.mkdir(parents=True, exist_ok=True)
            lbl_dir.mkdir(parents=True, exist_ok=True)

            for i, sample in enumerate(hf_split):
                pil_img = sample['image']
                W, H    = pil_img.size

                objects = sample.get('objects', {})
                bboxes  = objects.get('bbox',     [])
                cats    = objects.get('category', [])

                lines = []
                for box, cat_id in zip(bboxes, cats):
                    # HuggingFace bbox: [x_min, y_min, width, height] in pixels
                    x, y, w, h = box
                    cx = (x + w / 2) / W
                    cy = (y + h / 2) / H
                    nw = w / W
                    nh = h / H
                    # All objects in this dataset are bees
                    lines.append(f'0 {cx:.6f} {cy:.6f} {nw:.6f} {nh:.6f}')

                if not lines:
                    continue

                fname = f'hf_{split_name}_{i:05d}.jpg'
                pil_img.convert('RGB').save(img_dir / fname, quality=90)
                (lbl_dir / fname.replace('.jpg', '.txt')).write_text('\n'.join(lines))

        total = len(list(HF_DEST.rglob('*.jpg')))
        print(f'[OK] HuggingFace: {total} bee images saved')

    except Exception as e:
        print(f'[WARN] HuggingFace download failed: {e}')
        print('       Training will continue with Open Images only.')

# ═══════════════════════════════════════════════════════════════
#  SOURCE B — Google Open Images v7 (via fiftyone)
#  Bee, Wasp, Hornet, Moth, Fly — free, no account
# ═══════════════════════════════════════════════════════════════

OI_DEST = DATA_DIR / 'oi_insect_yolo'
OI_CLASSES = ['Bee', 'Insect', 'Wasp', 'Hornet', 'Moth', 'Fly', 'Hoverfly', 'Bumblebee']
OI_MAX     = args.max_imgs

if args.skip_oi:
    print('\n[SKIP] Open Images (--skip-oi flag set)')
    oi_ok = False
else:
    try:
        import fiftyone as fo
        import fiftyone.zoo as foz
        oi_ok = True
    except ImportError:
        print('\n[WARN] fiftyone not installed — skipping Open Images.')
        print('       To add ~2,000 more images run:  pip install fiftyone')
        oi_ok = False

if oi_ok:
    if OI_DEST.exists() and any(OI_DEST.rglob('*.jpg')):
        print('\n[SKIP] Open Images already on disk')
    else:
        print(f'\n[DOWNLOAD] Google Open Images — {OI_CLASSES[:4]}… ({OI_MAX} images)')
        print('           May take 5–15 min depending on internet speed')
        try:
            fo_ds = foz.load_zoo_dataset(
                'open-images-v7',
                split='train',
                label_types=['detections'],
                classes=OI_CLASSES,
                max_samples=OI_MAX,
                dataset_name='beeguard_oi',
                overwrite=True,
            )

            total_oi  = len(fo_ds)
            valid_cut = max(1, int(total_oi * 0.15))

            for split in ('train', 'valid'):
                (OI_DEST / split / 'images').mkdir(parents=True, exist_ok=True)
                (OI_DEST / split / 'labels').mkdir(parents=True, exist_ok=True)

            saved = 0
            for i, sample in enumerate(fo_ds):
                split     = 'valid' if i < valid_cut else 'train'
                img_path  = Path(sample.filepath)
                if not img_path.exists(): continue

                try:
                    with Image.open(img_path) as im:
                        W, H = im.size
                except Exception:
                    continue

                dets = sample.ground_truth
                if dets is None: continue

                lines = []
                for det in dets.detections:
                    canon = normalise(det.label)
                    cls   = CANONICAL.index(canon)
                    bx, by, bw, bh = det.bounding_box   # already [0,1]
                    cx = bx + bw / 2
                    cy = by + bh / 2
                    lines.append(f'{cls} {cx:.6f} {cy:.6f} {bw:.6f} {bh:.6f}')

                if not lines: continue

                fname = f'oi_{i:06d}.jpg'
                try:
                    with Image.open(img_path) as im:
                        im.convert('RGB').save(OI_DEST / split / 'images' / fname, quality=88)
                except Exception:
                    continue
                (OI_DEST / split / 'labels' / f'oi_{i:06d}.txt').write_text('\n'.join(lines))
                saved += 1

            print(f'[OK] Open Images: {saved} insect images saved')

        except Exception as e:
            print(f'[WARN] Open Images failed: {e}')
            oi_ok = False

# ═══════════════════════════════════════════════════════════════
#  MERGE all sources → training_data/merged/
# ═══════════════════════════════════════════════════════════════

print('\n[MERGE] Combining sources ...')

def copy_source(src_root: Path, prefix: str):
    global file_idx
    for split in ('train', 'valid'):
        img_src = src_root / split / 'images'
        lbl_src = src_root / split / 'labels'
        if not img_src.exists(): continue
        for img in img_src.iterdir():
            if img.suffix.lower() not in ('.jpg', '.jpeg', '.png'): continue
            lbl = lbl_src / img.with_suffix('.txt').name
            if not lbl.exists(): continue
            content = lbl.read_text().strip()
            if not content: continue
            out_name = f'{prefix}_{file_idx:06d}'
            shutil.copy(img, MERGED / split / 'images' / (out_name + img.suffix))
            (MERGED / split / 'labels' / (out_name + '.txt')).write_text(content)
            file_idx += 1

if HF_DEST.exists():
    copy_source(HF_DEST, 'hf')
    print(f'   HuggingFace bee images copied')

if oi_ok and OI_DEST.exists():
    copy_source(OI_DEST, 'oi')
    print(f'   Open Images insect images copied')

counts = {s: len(list((MERGED/s/'images').glob('*'))) for s in ('train','valid')}
total  = sum(counts.values())
print(f'[MERGE] train={counts["train"]}  valid={counts["valid"]}  total={total}')

if total == 0:
    print('[ERROR] No images — check download logs above.'); sys.exit(1)

# Write data.yaml
data_yaml = MERGED / 'data.yaml'
data_yaml.write_text(yaml.dump({
    'path':  str(MERGED),
    'train': 'train/images',
    'val':   'valid/images',
    'nc':    len(CANONICAL),
    'names': CANONICAL,
}))

# ═══════════════════════════════════════════════════════════════
#  TRAIN YOLOv8s
# ═══════════════════════════════════════════════════════════════

print('\n' + '='*60)
print('  Fine-tuning YOLOv8s')
print(f'  Classes : {CANONICAL}')
print(f'  Images  : {total}')
print(f'  Epochs  : {args.epochs}   Device: {device}')
print('  Estimated time:')
print('    CPU only  →  30–90 min')
print('    GPU (CUDA) →  5–15 min')
print('='*60 + '\n')

model = YOLO('yolov8n.pt')   # nano = fastest on CPU, downloads once

model.train(
    data     = str(data_yaml),
    epochs   = args.epochs,
    imgsz    = args.imgsz,
    device   = device,
    batch    = -1,
    patience = 15,
    augment  = True,
    hsv_h    = 0.015,
    hsv_s    = 0.7,
    hsv_v    = 0.4,
    flipud   = 0.2,
    fliplr   = 0.5,
    mosaic   = 1.0,
    mixup    = 0.15,
    degrees  = 10.0,
    project  = str(ROOT / 'runs' / 'bee_insect'),
    name     = 'train',
    exist_ok = True,
)

# ═══════════════════════════════════════════════════════════════
#  SAVE MODEL
# ═══════════════════════════════════════════════════════════════

best = ROOT / 'runs' / 'bee_insect' / 'train' / 'weights' / 'best.pt'
if best.exists():
    dest = MODELS_DIR / 'bee_insect.pt'
    shutil.copy(best, dest)
    size_mb = round(dest.stat().st_size / 1_000_000, 1)
    print(f'\n{"="*60}')
    print(f'  ✅  DONE — custom model saved')
    print(f'  Path : {dest}')
    print(f'  Size : {size_mb} MB')
    print(f'  Next : restart camera_capture.py')
    print(f'         It will auto-load bee_insect.pt on startup.')
    print(f'{"="*60}')
else:
    print(f'\n[WARN] best.pt not found — check training output above.')
