# Astronomy Odyssey — Presentation Script for 4 Members

**Total Duration:** ~20 minutes  
**Team:** 4 Presenters  
**Format:** Live demo + slides + Q&A

---

## 📋 Presentation Structure Overview

| Segment | Time | Presenter | Focus |
|---------|------|-----------|-------|
| 1. Introduction & Architecture | 4 min | **Member 1** | Project overview, tech stack, system design |
| 2. Six ML Missions Deep Dive | 8 min | **Member 2** | All 6 missions, algorithms, data sources |
| 3. ML Implementation & Explainability | 5 min | **Member 3** | Training pipelines, transparency, "Why this answer?" |
| 4. User Experience, Gamification & Future | 3 min | **Member 4** | Explorer system, progress tracking, roadmap |

---

## 🎤 MEMBER 1 — Introduction & Architecture (4 minutes)

### Slide 1: Title Slide
> **Speaker:** "Good [morning/afternoon]. We're presenting **Astronomy Odyssey** — an educational portal for ELEC026.23 that teaches machine learning through real astronomy. I'm [Name], joined by [Names of other members]. Over the next 20 minutes, we'll walk you through the architecture, six ML missions, our commitment to explainability, and the gamified learning experience."

### Slide 2: What is Astronomy Odyssey?
> **Speaker:** "Astronomy Odyssey is a **fictional educational venture** built for ELEC026.23. The core idea: *learn ML by doing astronomy with real data*. Every mission uses authentic NASA catalogs, Stack Exchange Q&A, and galaxy surveys — no synthetic datasets. Students interact with models through a React frontend, get predictions, and crucially — see **exactly why** the model gave that answer."

### Slide 3: Tech Stack
> **Speaker:** "Full-stack Laravel + React. Backend: **PHP 8.2, Laravel 11, MySQL 8**. Frontend: **React 19, Vite, Tailwind CSS, React Router v7**. ML is implemented in **pure PHP service classes** — no Python microservices, no black-box libraries. This was a deliberate choice: every calculation is inspectable in the codebase."

### Slide 4: System Architecture
> **Speaker:** "Architecture is API-first. The Laravel JSON API serves six mission endpoints under `/api/` — each mission has its own controller, request validation, and ML service. Data flows: **Raw downloads → `storage/app/private/raw/` → Import commands → MySQL → Training commands → `MlModel` records → Inference on demand**. Models are persisted with parameters + metrics, versioned by dataset."

### Slide 5: Database & Data Integrity
> **Speaker:** "Key models: `Dataset` (mission + version), `DataSource` (provenance), `MlModel` (parameters + metrics + training timestamp), `Explorer` (session-based auth), `MissionRun` (audit trail of every prediction). Every import records **source, license, retrieval date, row count** — full reproducibility."

---

## 🎤 MEMBER 2 — Six ML Missions Deep Dive (8 minutes)

### Slide 6: Mission Overview
> **Speaker:** "Six missions, six algorithms, six real datasets. Each mission requires **user input before showing results** and awards points for guesses — turning inference into a game. Let's walk through each."

---

### Mission 1: Orbit Calculator — Linear Regression
> **Speaker:** "**Algorithm:** Linear regression in log-space, implementing Kepler's Third Law: `ln(T) = b₀ + b₁·ln(a) + b₂·ln(M)`. **Data:** NASA Exoplanet Archive `pscomppars` — confirmed exoplanets with period, semi-major axis, star mass. **Honesty note:** We use bulk density and equilibrium temperature as substitutes for atmospheric density and true surface temperature — real catalogs don't provide those. **Key feature:** Compare datasets side-by-side — Solar System planets yield exponents ~1.5 and -0.5 (Kepler's law); exoplanet datasets deviate due to selection effects."

### Mission 2: Forum Sorter — Naive Bayes
> **Speaker:** "**Algorithm:** Multinomial Naive Bayes with Laplace smoothing. **Data:** Astronomy + Space Exploration Stack Exchange API (CC BY-SA 4.0). Three categories: Planetary Science, Stellar Physics, Space Exploration Tech. **Pipeline:** Tokenize titles → remove stop words (including astronomy-specific: 'space', 'planet', 'star') → count word frequencies per class → apply priors. **Explainability:** Returns top 5 contributing words per category with log-probabilities — students see *which words drove the decision*."

### Mission 3: Exoplanet Twin Finder — K-Nearest Neighbors
> **Speaker:** "**Algorithm:** KNN with standardized features. **Features:** `ln(radius)`, `ln(density)`, equilibrium temperature — **NOT mass**. **Crucial design:** Planet type (Terrestrial/Super-Earth <10 M⊕, Neptune-like 10-50, Gas Giant >50) is *derived from mass only* — mass is excluded from features. This makes classification non-trivial. **Presets:** Earth-like, Hot Jupiter, Sub-Neptune. **Explainability:** Shows k nearest neighbors with feature differences, vote counts, and held-out accuracy for that k."

### Mission 4: Transit Hunter — Linear SVM (SGD)
> **Speaker:** "**Algorithm:** Linear SVM trained via SGD with hinge loss + L2 regularization (λ=0.01). **Data:** NASA KOI cumulative table — CONFIRMED vs FALSE POSITIVE. Balanced sampling (1000 per class). **Features:** `log10(transit_depth_ppm)`, `log10(signal_to_noise)`. **Visual output:** Decision boundary + margin lines + data points in 2D feature space. **Light curve:** Reconstructed box-model from catalog depth/duration. **Honesty:** Ingress/egress simplified; not raw telescope data."

### Mission 5: Telescope Setup Wizard — Decision Tree (Rule-Based)
> **Speaker:** "**Algorithm:** Hardcoded decision tree — **NOT learned from data**. **Why?** No public dataset of 'correct telescope settings' exists. **Rules:** Expert astrophotography rules of thumb. Splits on Bortle scale (≥6 vs <6) → Distance Rank (Solar System <2, Milky Way =2, Beyond ≥3). **Output:** Filter, exposure, aperture. **Explainability:** Full tree visualization + path taken + 'Why these splits?' — light pollution washes out faint objects; distance determines brightness/angular size."

### Mission 6: Galaxy Classifier — Neural Network
> **Speaker:** "**Algorithm:** Feed-forward network: 256 inputs (16×16 grayscale) → 24 tanh hidden → 3 softmax (Elliptical, Spiral, Irregular). **Data:** Galaxy10 DECaLS/SDSS (CC BY 4.0). **Training:** 50 epochs, batch 32, LR 0.01, Xavier init. **Explainability:** Pixel importance heatmap (gradient × activation × output weight), hidden activations, output logits, per-class probabilities. **Honesty note:** 'Tiny network on tiny images makes mistakes; see confusion matrix.'"

---

## 🎤 MEMBER 3 — ML Implementation & Explainability (5 minutes)

### Slide 7: Training Pipeline Architecture
> **Speaker:** "All training runs via `php artisan ml:train-all` — single command trains all 6 missions. Each service implements `train(Dataset $dataset): MlModel`. Training is **deterministic where possible** (fixed seeds for NN, shuffle with fixed split). Models stored in `ml_models` table with JSON `parameters` + `metrics`. Versioning by dataset key — re-run import → new dataset → new model."

### Slide 8: Transparency by Design — Code as Documentation
> **Speaker:** "No scikit-learn, no TensorFlow, no PyTorch. Every algorithm is **~150-350 lines of readable PHP** in `app/Services/ML/`. Gaussian elimination for linear regression, manual SGD for SVM, backprop from scratch for NN. Students can **read the exact math** — no hidden C++/CUDA. This is the 'white box' philosophy: if you can't explain it, you don't understand it."

### Slide 9: "Why This Answer?" — Explainability in Every Response
> **Speaker:** "Every prediction endpoint returns an `explanation` object. Examples:
- **Orbit:** Substituted formula, step-by-step log calculations, learned vs expected Kepler exponents, model R²
- **Exoplanet:** Standardized input, k neighbors with feature diffs, vote breakdown, accuracy for that k
- **Transit:** Standardized features, decision function, score, margin, distance from boundary, support vector note
- **Telescope:** Full tree, path taken, target properties, comparison with user guess, score
- **Galaxy:** Architecture, input preview, hidden activations, logits, probabilities, **pixel importance heatmap**
- **Forum:** Class priors, log-posteriors, top contributing words for winner + others"

### Slide 10: Honesty Notes & Educational Integrity
> **Speaker:** "Every dataset endpoint returns an `honesty_note` — we explicitly document limitations:
- Orbit: 'No catalog provides atmospheric density or true surface temperature'
- Exoplanet: 'Type derived from MASS only, not used as features'
- Transit: 'CONFIRMED=Exoplanet Transit Detected mapping for education'
- Telescope: 'Rules are expert rules of thumb, NOT learned from data'
- Galaxy: 'Tiny network on tiny images makes mistakes'
This teaches **critical thinking about ML limitations** — not just blind trust."

---

## 🎤 MEMBER 4 — User Experience, Gamification & Future (3 minutes)

### Slide 11: Explorer System — Gamified Learning
> **Speaker:** "Users create an **Explorer** with callsign + level (Beginner/Intermediate/Advanced). Session-based auth via `X-Session-Token` header — no passwords, no OAuth complexity. Every mission run creates a `MissionRun` record: inputs, outputs, user guess, points awarded. **Points system:** Orbit/Exoplanet/Transit/Forum = 100 for correct, scaled for close guesses. Galaxy = 150 for beating the AI, 100 for both correct, 25 for AI-only correct, 10 for both wrong."

### Slide 12: Progress Dashboard
> **Speaker:** "`GET /api/progress` returns: total points, missions tried, completed mission keys, full history with timestamps. `GET /api/progress/{runId}` reloads any past run — students can revisit explanations. Leaderboard-ready (though not yet implemented)."

### Slide 13: Company/About Pages — Real-World Context
> **Speaker:** "The API also serves a fictional company profile: mission statement, values (Scientific Integrity, Educational Excellence, Open Access), services, team, FAQ, roadmap. Algorithm mapping shows which ML technique powers each branded mission name (Orbit Lab → Linear Regression, etc.). This frames the project as a **product**, not just homework."

### Slide 14: Data Attribution & Reproducibility
> **Speaker:** "Every dataset cites source, publisher, URL, license, retrieval date, row count, citation text. Raw downloads preserved in `storage/app/private/raw/`. Import commands are idempotent — re-run to refresh. Galaxy10 too large to bundle; documented manual download + Python prep script (`scripts/prepare_galaxy10.py`)."

### Slide 15: Roadmap & Future Work
> **Speaker:** "Planned: Leaderboards, mission badges, dataset comparison UI, confusion matrix visualizations, model versioning UI, export run as PDF report, multi-language support. Technical: Move training to queue workers, add model A/B testing, integrate with Laravel Telescope for debugging."

### Slide 16: Live Demo
> **Speaker:** "[Switch to browser] Let's walk through a live session: onboard as Explorer → Orbit Calculator (guess period → see explanation) → Transit Hunter (adjust depth/SNR → watch decision boundary) → Galaxy Classifier (pick test image → see heatmap) → Progress dashboard."

### Slide 17: Summary & Q&A
> **Speaker:** "Astronomy Odyssey demonstrates: **real data + transparent ML + gamified UX = effective education**. Six algorithms, six datasets, zero black boxes. All code open for inspection. Thank you — questions?"

---

## 🎯 Demo Checklist (Run Before Presentation)

```bash
# 1. Fresh environment
php artisan migrate:fresh --seed
php artisan data:import-exoplanets
php artisan data:import-koi
php artisan data:import-forum --max-pages=5
php artisan data:import-targets
# Galaxy10: manual download + python prep if needed
php artisan ml:train-all

# 2. Start servers
npm run dev &        # Vite HMR
php artisan serve    # Laravel on :8000

# 3. Verify missions work
curl -X POST http://127.0.0.1:8000/api/explorer/onboard \
  -H "Content-Type: application/json" \
  -d '{"callsign":"Demo","level":"Beginner"}'
# → copy session_token, test each mission endpoint
```

---

## 📝 Speaker Notes & Handoffs

### Member 1 → Member 2
> "Now [Member 2] will dive into each of the six missions — the algorithms, the real NASA data behind them, and what makes each one educationally valuable."

### Member 2 → Member 3
> "With the missions covered, [Member 3] will show you how we implemented these algorithms from scratch in PHP and — most importantly — how every prediction comes with a full 'Why this answer?' explanation."

### Member 3 → Member 4
> "Understanding the model is half the battle. [Member 4] will show how we wrapped this in a gamified explorer experience with progress tracking, and where the project is heading."

### Member 4 → Q&A
> "That's our tour of Astronomy Odyssey. We'd love your questions — whether about the ML, the Laravel architecture, the frontend, or the educational design."

---

## 🎭 Presentation Tips

1. **Keep slides minimal** — diagrams > bullet points. Use the architecture diagram, decision tree visual, SVM boundary plot, NN heatmap.
2. **Live demo is the hero** — practice the flow: onboard → orbit → transit → galaxy → progress.
3. **Emphasize "honesty notes"** — this differentiates the project (educational integrity).
4. **Highlight PHP-only ML** — unusual, impressive, explains transparency.
5. **Timebox** — 20 min hard limit. If running long, condense Mission 5/6 explanations.

---

## 🔗 Key Files for Reference During Q&A

| Topic | File |
|-------|------|
| Orbit LR math | `app/Services/ML/LinearRegressionService.php:70-123` |
| KNN feature scaling | `app/Services/ML/KnnService.php:112-121` |
| Naive Bayes tokenization | `app/Services/ML/NaiveBayesService.php:124-132` |
| SVM SGD training | `app/Services/ML/SvmService.php:117-145` |
| Decision tree rules | `app/Services/ML/DecisionTreeService.php:37-79` |
| NN backprop | `app/Services/ML/NeuralNetworkService.php:146-207` |
| API routes | `routes/api.php` |
| Import commands | `app/Console/Commands/DataImport*.php` |
| Training command | `app/Console/Commands/MlTrainAll.php` |

---

## 🎓 Educational Talking Points (If Asked)

- **Why PHP for ML?** Accessibility — students already know PHP from Laravel; no context switch to Python. Proves ML concepts transcend language.
- **Why no libraries?** Pedagogical — gradient descent, normal equations, backprop are *the curriculum*. Libraries hide them.
- **How does this scale?** It doesn't — and that's fine. It's a teaching tool, not a production serving layer. Training is offline; inference is fast enough.
- **What about overfitting?** Small datasets, simple models, held-out test sets, confusion matrices exposed. Students see the limitations.
- **Can I extend it?** Yes — add a `Dataset`, create a `*Service.php`, register in `MlTrainAll`, add controller + routes. Pattern is consistent.

---

*End of Script — Break a leg! 🚀*