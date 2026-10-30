# Astrolabs

Astrolabs is a fictional ELEC026.23 educational portal. Its Laravel JSON API and React/Vite interface let learners explore six astronomy ML missions with cited real data. Models are implemented in small PHP service classes so their calculations can be inspected and explained.

## Setup

Requirements: PHP 8.2+, Composer, Node.js, MySQL 8, and (optionally) MAMP. With MAMP's default MySQL port, use `8889`.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# Create MySQL databases: astronomy_odyssey and astronomy_odyssey_test
php artisan migrate --seed
php artisan data:import-exoplanets
php artisan data:import-koi
php artisan data:import-forum --max-pages=10
php artisan data:import-targets
php artisan ml:train-all
npm run dev
php artisan serve
```

Open `http://127.0.0.1:8000`. For MAMP, set its document root to this repository's `public` directory and use `http://localhost:8888`.

Run checks with:

```bash
php artisan test
npm run build
```

## Datasets and attribution

- NASA Exoplanet Archive `pscomppars`: Orbit Calculator and Exoplanet Twin Finder. NASA government data is public domain. Bulk density and equilibrium temperature are used honestly as available substitutes for atmospheric density and true surface temperature.
- NASA Exoplanet Archive KOI cumulative table: Transit Hunter. Confirmed KOIs map to “Exoplanet Transit Detected”; false positives map to “Standard Star (no planet signal)”.
- Astronomy and Space Exploration Stack Exchange API: Forum Sorter, licensed CC BY-SA 4.0.
- [OpenNGC](https://github.com/mattiaverga/OpenNGC): Telescope Setup Wizard, licensed CC BY-SA 4.0. Telescope recommendations are expert rules of thumb, not learned labels.
- [Galaxy10 DECaLS and SDSS](https://astronn.readthedocs.io/en/latest/galaxy10.html): Galaxy Classifier. Galaxy Zoo labels are CC BY 4.0; survey-image terms are retained in the dataset attribution.

The live import commands retain raw downloads under `storage/app/private/raw/` and record source, license, retrieval date, and row count in the database.

### Galaxy10 manual preparation

Galaxy10 DECaLS is too large to bundle. Download `Galaxy10_DECals.h5` and (for the alternate dataset) `Galaxy10.h5` from the Galaxy10 documentation source, save them in `storage/app/raw/`, then run:

```bash
python3 -m pip install h5py numpy pillow
python3 scripts/prepare_galaxy10.py
php artisan data:import-galaxies
php artisan ml:train-all --mission=galaxy
```

## ML missions

- **Orbit Calculator — linear regression:** learns a log-space form of Kepler’s third law. Its coefficients show how orbital period changes with semi-major axis and star mass.
- **Forum Sorter — Naive Bayes:** counts informative words in real question titles, applies Laplace smoothing, and combines word evidence with category priors.
- **Exoplanet Twin Finder — k-nearest neighbours:** standardizes radius, density, and temperature, then votes among nearby real exoplanets. Its class is derived from mass, not used as an input feature.
- **Transit Hunter — linear SVM:** learns a maximum-margin boundary between confirmed transits and false positives from catalog signal features.
- **Telescope Setup Wizard — decision tree:** follows transparent, expert-written branches based on sky brightness and target distance.
- **Galaxy Classifier — neural network:** runs a compact feed-forward network on a 16×16 grayscale view of a real Galaxy10 image.

Each mission requires user input before a result is shown and returns intermediate values for the “Why this answer?” explanation.
