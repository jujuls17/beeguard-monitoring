"""
Merge bee dataset + insect dataset into one combined dataset.

Result: dataset/combined/
  Classes:
    0 = bee     (from nonpollenbee + pollenbee in bee dataset)
    1 = insect  (from Insects class in insect dataset)

Bee dataset (dataset/roboflow/):
  class 0 = nonpollenbee → remap to 0 (bee)
  class 1 = pollen       → SKIP (not a bee or insect)
  class 2 = pollenbee    → remap to 0 (bee)

Insect dataset (train/, valid/ at root):
  class 0 = Insects → remap to 1 (insect)
"""

import shutil
from pathlib import Path

BASE = Path("C:/xampp/htdocs/beemonitoring")
BEE_DIR    = BASE / "dataset" / "roboflow"
INSECT_DIR = BASE  # insect dataset is at root (train/, valid/)
OUT_DIR    = BASE / "dataset" / "combined"

# Class remapping
# bee dataset: nonpollenbee=0→0(bee), pollen=1→skip, pollenbee=2→0(bee)
BEE_REMAP = {0: 0, 1: None, 2: 0}

# insect dataset: Insects=0→1(insect)
INSECT_REMAP = {0: 1}


def copy_and_remap(src_img_dir: Path, src_lbl_dir: Path,
                   dst_img_dir: Path, dst_lbl_dir: Path,
                   class_remap: dict, prefix: str):
    dst_img_dir.mkdir(parents=True, exist_ok=True)
    dst_lbl_dir.mkdir(parents=True, exist_ok=True)

    images = list(src_img_dir.glob("*.[jJpP][pPnN][gG]*")) + \
             list(src_img_dir.glob("*.jpeg")) + \
             list(src_img_dir.glob("*.JPEG"))
    # deduplicate
    images = list({p.stem: p for p in images}.values())

    copied = 0
    for img_path in images:
        lbl_path = src_lbl_dir / (img_path.stem + ".txt")

        new_name = prefix + "_" + img_path.name
        new_lbl_name = prefix + "_" + img_path.stem + ".txt"

        # Always copy image
        shutil.copy2(img_path, dst_img_dir / new_name)

        # Remap label
        new_lines = []
        if lbl_path.exists():
            for line in lbl_path.read_text().strip().splitlines():
                parts = line.strip().split()
                if not parts:
                    continue
                old_cls = int(parts[0])
                new_cls = class_remap.get(old_cls)
                if new_cls is None:
                    continue  # skip this annotation
                new_lines.append(f"{new_cls} " + " ".join(parts[1:]))

        (dst_lbl_dir / new_lbl_name).write_text("\n".join(new_lines))
        copied += 1

    return copied


def main():
    print("=" * 55)
    print("  BeeGuard — Dataset Merger")
    print("  Output: dataset/combined/")
    print("=" * 55)
    print()

    # Clean output
    if OUT_DIR.exists():
        print(f"[CLEAN] Removing old {OUT_DIR}")
        shutil.rmtree(OUT_DIR)

    splits = [
        ("train", "train"),
        ("valid", "valid"),
    ]

    for split, split_name in splits:
        print(f"[{split_name.upper()}] Processing bee dataset...")
        n = copy_and_remap(
            src_img_dir = BEE_DIR / split / "images",
            src_lbl_dir = BEE_DIR / split / "labels",
            dst_img_dir = OUT_DIR / split_name / "images",
            dst_lbl_dir = OUT_DIR / split_name / "labels",
            class_remap = BEE_REMAP,
            prefix      = "bee",
        )
        print(f"         {n} bee images copied")

        print(f"[{split_name.upper()}] Processing insect dataset...")
        n = copy_and_remap(
            src_img_dir = INSECT_DIR / split / "images",
            src_lbl_dir = INSECT_DIR / split / "labels",
            dst_img_dir = OUT_DIR / split_name / "images",
            dst_lbl_dir = OUT_DIR / split_name / "labels",
            class_remap = INSECT_REMAP,
            prefix      = "insect",
        )
        print(f"         {n} insect images copied")
        print()

    # Write combined data.yaml
    yaml_content = (
        "train: train/images\n"
        "val: valid/images\n"
        "\n"
        "nc: 2\n"
        "names: ['bee', 'insect']\n"
    )
    (OUT_DIR / "data.yaml").write_text(yaml_content)
    print(f"[DONE] data.yaml written → nc: 2, names: ['bee', 'insect']")

    # Count results
    train_imgs = len(list((OUT_DIR / "train" / "images").glob("*")))
    valid_imgs = len(list((OUT_DIR / "valid" / "images").glob("*")))
    print(f"[DONE] Train images: {train_imgs}")
    print(f"[DONE] Valid images: {valid_imgs}")
    print()
    print("Next step:")
    print("  Run:  python train_bee_model.py")
    print("  (it will use dataset/combined/data.yaml automatically)")


if __name__ == "__main__":
    main()
