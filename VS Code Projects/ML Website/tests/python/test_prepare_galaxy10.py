"""Pipeline regression fixtures are temporary test data, never website data."""

import contextlib
import importlib.util
import io
import json
import tempfile
import unittest
from pathlib import Path

try:
    import h5py
    import numpy as np
    from PIL import Image
    HAVE_DEPENDENCIES = True
except ImportError:
    HAVE_DEPENDENCIES = False

if HAVE_DEPENDENCIES:
    spec = importlib.util.spec_from_file_location(
        'prepare_galaxy10', Path(__file__).resolve().parents[2] / 'scripts/prepare_galaxy10.py')
    preparation = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(preparation)


@unittest.skipUnless(HAVE_DEPENDENCIES, 'Install h5py numpy pillow to run preparation tests')
class GalaxyPreparationTest(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name)
        self.raw = self.root / 'raw'
        self.raw.mkdir()
        self.data = self.root / 'data'
        self.images = self.root / 'images'

    def fixture(self, filename, labels):
        """Constant brightness records let us detect mismatched source indices."""
        path = self.raw / filename
        with h5py.File(path, 'w') as handle:
            handle['ans'] = labels
            handle['images'] = np.stack([
                np.full((8, 8, 3), index + 10, dtype=np.uint8)
                for index in range(len(labels))
            ])
        return path

    def prepare(self, dataset):
        with contextlib.redirect_stdout(io.StringIO()):
            return preparation.prepare(dataset, self.raw, self.data, self.images)

    def test_sdss_indices_labels_images_and_stratified_split(self):
        # Indices are deliberately far from sequential selected-row positions.
        labels = np.array([0] * 20 + [1] * 10 + [4] * 10 + [9] * 10)
        self.fixture('Galaxy10.h5', labels)
        result = self.prepare('sdss')['sdss']
        self.assertEqual(30, len(result))
        expected = {1: 'Elliptical', 4: 'Edge-on disk', 9: 'Spiral'}
        for row in result:
            index = int(row['source_id'].split('_')[1])
            self.assertEqual(expected[labels[index]], row['label'])
            self.assertEqual(preparation.SDSS_CLASS_NAMES[labels[index]], row['original_label'])
            self.assertEqual(f'galaxies/sdss_{index}.jpg', row['image_path'])
            self.assertEqual(256, len(row['pixels']))
            self.assertTrue(all(abs(value - (index + 10) / 255) < 1e-6 for value in row['pixels']))
            with Image.open(self.images / Path(row['image_path']).name) as image:
                self.assertEqual((128, 128), image.size)
                self.assertAlmostEqual(index + 10, image.getpixel((0, 0))[0], delta=1)
        for label in expected.values():
            self.assertEqual(8, sum(r['label'] == label and r['split'] == 'train' for r in result))
            self.assertEqual(2, sum(r['label'] == label and r['split'] == 'test' for r in result))
        self.assertEqual(result, self.prepare('sdss')['sdss'])

    def test_decals_uses_its_own_label_order(self):
        labels = np.repeat(np.arange(10), 5)
        self.fixture('Galaxy10_DECals.h5', labels)
        rows = self.prepare('deca')['deca']
        self.assertEqual(40, len(rows))
        expected = ['Irregular', 'Irregular', 'Elliptical', 'Elliptical',
                    'Elliptical', 'Spiral', 'Spiral', 'Spiral']
        for row in rows:
            index = int(row['source_id'].split('_')[1])
            self.assertEqual(expected[labels[index]], row['label'])

    def test_missing_input_does_not_overwrite_prepared_data(self):
        self.data.mkdir()
        output = self.data / 'galaxies.json'
        output.write_text('{"deca": [{"source_id":"previous"}], "sdss": []}')
        original = output.read_bytes()
        with self.assertRaises(FileNotFoundError):
            self.prepare('sdss')
        self.assertEqual(original, output.read_bytes())
        with self.assertRaises(FileNotFoundError):
            self.prepare('all')
        self.assertEqual(original, output.read_bytes())

    def test_single_available_dataset_preserves_other_prepared_dataset(self):
        self.data.mkdir()
        previous = [{'source_id': 'previous-deca-fixture'}]
        (self.data / 'galaxies.json').write_text(json.dumps({'deca': previous, 'sdss': []}))
        self.fixture('Galaxy10.h5', np.repeat([1, 4, 7], 5))
        result = self.prepare('all')
        self.assertEqual(previous, result['deca'])
        self.assertEqual(15, len(result['sdss']))

    def test_invalid_second_source_preserves_previous_json_and_images(self):
        self.data.mkdir()
        self.images.mkdir()
        output = self.data / 'galaxies.json'
        output.write_text('{"deca": [], "sdss": []}')
        original = output.read_bytes()
        marker = self.images / 'deca_1.jpg'
        marker.write_bytes(b'previous image')
        self.fixture('Galaxy10_DECals.h5', np.repeat([0, 2, 5], 5))
        (self.raw / 'Galaxy10.h5').write_bytes(b'incomplete download')
        with self.assertRaises(OSError):
            self.prepare('all')
        self.assertEqual(original, output.read_bytes())
        self.assertEqual(b'previous image', marker.read_bytes())
        self.assertEqual(1, len(list(self.images.iterdir())))


if __name__ == '__main__':
    unittest.main()
