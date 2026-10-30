#!/usr/bin/env python3
"""Prepare real Galaxy10 images for Astrolabs.

Usage: python3 scripts/prepare_galaxy10.py --dataset sdss
Dependencies: python3 -m pip install h5py numpy pillow

Selects up to 300 images per broad class, with a reproducible stratified
80/20 train/test split. Outputs 16×16 grayscale pixels and 128×128 JPEGs.
"""

import argparse
import json
import shutil
import sys
import tempfile
from collections import Counter
from pathlib import Path

import h5py
import numpy as np
from PIL import Image

PROJECT_DIR = Path(__file__).resolve().parents[1]
RAW_DIR = PROJECT_DIR / "storage/app/raw"
PRIVATE_RAW_DIR = PROJECT_DIR / "storage/app/private/raw"
DATA_DIR = PROJECT_DIR / "database/data"
IMAGES_DIR = PROJECT_DIR / "storage/app/public/galaxies"
SEED = 42
PER_CLASS_COUNT = 300
TRAIN_RATIO = 0.8
IMAGE_SIZE = (16, 16)
DISPLAY_SIZE = (128, 128)

# Original labels: https://astronn.readthedocs.io/en/stable/galaxy10.html
# These broad classes are educational groupings; retain each original label.
DECALS_CLASS_NAMES = {
    0: 'Disturbed', 1: 'Merging', 2: 'Round Smooth',
    3: 'In-between Round Smooth', 4: 'Cigar Shaped Smooth',
    5: 'Barred Spiral', 6: 'Unbarred Tight Spiral', 7: 'Unbarred Loose Spiral',
    8: 'Edge-on without Bulge', 9: 'Edge-on with Bulge',
}
DECALS_CLASS_MAP = {
    0: 'Irregular', 1: 'Irregular', 2: 'Elliptical', 3: 'Elliptical',
    4: 'Elliptical', 5: 'Spiral', 6: 'Spiral', 7: 'Spiral', 8: None, 9: None,
}

# Original labels: https://astronn.readthedocs.io/en/stable/galaxy10sdss.html
SDSS_CLASS_NAMES = {
    0: 'Disk face-on no spiral', 1: 'Smooth completely round',
    2: 'Smooth in-between round', 3: 'Smooth cigar shaped',
    4: 'Disk edge-on rounded bulge', 5: 'Disk edge-on boxy bulge',
    6: 'Disk edge-on no bulge', 7: 'Disk face-on tight spiral',
    8: 'Disk face-on medium spiral', 9: 'Disk face-on loose spiral',
}
SDSS_CLASS_MAP = {
    0: None, 1: 'Elliptical', 2: 'Elliptical', 3: 'Elliptical',
    4: 'Edge-on disk', 5: 'Edge-on disk', 6: 'Edge-on disk',
    7: 'Spiral', 8: 'Spiral', 9: 'Spiral',
}
DATASETS = {
    'deca': ('Galaxy10_DECals.h5', DECALS_CLASS_MAP, DECALS_CLASS_NAMES),
    'sdss': ('Galaxy10.h5', SDSS_CLASS_MAP, SDSS_CLASS_NAMES),
}


def process_h5_file(h5_path, class_map, class_names, dataset_name,
                    images_dir=IMAGES_DIR, per_class_count=PER_CLASS_COUNT):
    """Read only selected RGB images, preserving source indices and labels."""
    print(f"\nProcessing {dataset_name}: {h5_path}")
    rng = np.random.default_rng(SEED)
    images_dir = Path(images_dir)
    with h5py.File(h5_path, 'r') as source:
        images = source['images']  # HDF5 handle: do not load the whole survey.
        labels = source['ans'][:]
        if (images.ndim != 4 or images.shape[-1] != 3 or labels.ndim != 1
                or len(images) != len(labels)):
            raise ValueError('Expected matching RGB images (N, H, W, 3) and labels (N,).')
        if not np.isin(labels, list(class_map)).all():
            raise ValueError('Dataset contains labels outside the documented Galaxy10 classes.')
        print(f"  Source shape: {images.shape}")
        selections = []
        for broad_label in sorted({v for v in class_map.values() if v is not None}):
            original_ids = [key for key, value in class_map.items() if value == broad_label]
            available = np.flatnonzero(np.isin(labels, original_ids))
            count = min(per_class_count, len(available))
            if count < 2:
                raise ValueError(f'Need at least two images for {broad_label}; found {count}.')
            selected = rng.choice(available, size=count, replace=False)
            train_count = min(count - 1, max(1, int(count * TRAIN_RATIO)))
            for position, index in enumerate(selected):
                selections.append((int(index), broad_label,
                                   'train' if position < train_count else 'test'))
            print(f"  {broad_label}: {train_count} train, {count - train_count} test")
        rng.shuffle(selections)
        images_dir.mkdir(parents=True, exist_ok=True)
        results = []
        for index, broad_label, split in selections:
            image_array = images[index]
            if not np.isfinite(image_array).all():
                raise ValueError(f'Image {index} contains non-finite pixels.')
            if image_array.dtype != np.uint8:
                # Accept normalized float RGB or byte-range numeric RGB.
                if np.issubdtype(image_array.dtype, np.floating) and image_array.max() <= 1:
                    image_array = image_array * 255
                if image_array.min() < 0 or image_array.max() > 255:
                    raise ValueError(f'Image {index} has unsupported pixel values.')
                image_array = np.rint(image_array).astype(np.uint8)
            image = Image.fromarray(image_array)
            filename = f'{dataset_name}_{index}.jpg'  # Do not reveal the answer in the URL.
            image.resize(DISPLAY_SIZE, Image.Resampling.LANCZOS).save(
                images_dir / filename, 'JPEG', quality=85)
            grayscale = image.resize(IMAGE_SIZE, Image.Resampling.LANCZOS).convert('L')
            pixels = (np.asarray(grayscale, dtype=np.float32) / 255).flatten().tolist()
            results.append({
                'source_id': f'{dataset_name}_{index}',
                'label': broad_label,
                'original_label': class_names[int(labels[index])],
                'image_path': f'galaxies/{filename}',
                'pixels': pixels,
                'split': split,
            })
        return results


def prepare(dataset='all', raw_dir=None, data_dir=DATA_DIR, images_dir=IMAGES_DIR):
    """Prepare available sources; publish JSON only after all selected sources succeed."""
    requested = list(DATASETS) if dataset == 'all' else [dataset]
    search_dirs = [Path(raw_dir).expanduser().resolve()] if raw_dir else [RAW_DIR, PRIVATE_RAW_DIR]
    found = {}
    for key in requested:
        filename = DATASETS[key][0]
        path = next((directory / filename for directory in search_dirs
                     if (directory / filename).is_file()), None)
        if path is None:
            if dataset != 'all':
                raise FileNotFoundError(f'{filename} not found in {", ".join(map(str, search_dirs))}')
            print(f'Skipping {key}: {filename} has not been downloaded.')
        else:
            found[key] = path
    if not found:
        raise FileNotFoundError('No complete Galaxy10 HDF5 files found. Existing prepared data was not changed.')

    data_dir, images_dir = Path(data_dir), Path(images_dir)
    output_path = data_dir / 'galaxies.json'
    combined = {'deca': [], 'sdss': []}
    if output_path.exists():
        previous = json.loads(output_path.read_text())
        if not isinstance(previous, dict) or any(not isinstance(v, list) for v in previous.values()):
            raise ValueError('Existing galaxies.json is invalid; it was not overwritten.')
        combined.update(previous)

    data_dir.mkdir(parents=True, exist_ok=True)
    # Stage JPEGs so a corrupt or incomplete input cannot damage existing images.
    with tempfile.TemporaryDirectory(prefix='.galaxy-prepare-', dir=data_dir) as stage:
        staging = Path(stage)
        for key, path in found.items():
            _, class_map, class_names = DATASETS[key]
            combined[key] = process_h5_file(path, class_map, class_names, key, staging)
        staged_json = staging / 'galaxies.json'
        staged_json.write_text(json.dumps(combined, allow_nan=False))
        images_dir.mkdir(parents=True, exist_ok=True)
        for image_path in staging.glob('*.jpg'):
            shutil.copyfile(image_path, images_dir / image_path.name)
        staged_json.replace(output_path)

    print(f'\nSaved {output_path}')
    for key in found:
        counts = Counter((row['label'], row['split']) for row in combined[key])
        print(f'{key}: {len(combined[key])} images; {dict(counts)}')
    print(f'JPEGs saved to {images_dir}')
    return combined


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--dataset', choices=['all', 'deca', 'sdss'], default='all',
                        help='Prepare one dataset, or all downloaded datasets (default).')
    parser.add_argument('--raw-dir', type=Path,
                        help='Directory containing the HDF5 file; otherwise searches both project raw directories.')
    args = parser.parse_args(argv)
    try:
        prepare(args.dataset, args.raw_dir)
    except (OSError, ValueError, KeyError) as error:
        print(f'ERROR: {error}', file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
