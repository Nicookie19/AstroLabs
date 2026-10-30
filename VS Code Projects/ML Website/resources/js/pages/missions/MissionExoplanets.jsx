import React, { useEffect, useState } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { useExplorer } from '../../context/ExplorerContext';
import { api } from '../../utils/api';
import { useToast } from '../../context/ToastContext';

const datasetOptions = {
  confirmed_exoplanets: { title: 'Confirmed Exoplanets', publisher: 'NASA Exoplanet Archive' },
  transit_discovered: { title: 'Transit-discovered Exoplanets', publisher: 'NASA Exoplanet Archive' },
  planet_host_stars: { title: 'Planet Host Stars', publisher: 'NASA Exoplanet Archive' },
};

const typeLabels = ['Terrestrial/Super-Earth', 'Neptune-like', 'Gas giant'];
const presets = {
  'Earth-like': { radius_earths: 1, bulk_density_g_cm3: 5.51, equilibrium_temp_k: 288 },
  'Hot Jupiter': { radius_earths: 11, bulk_density_g_cm3: 1.3, equilibrium_temp_k: 1500 },
  'Sub-Neptune': { radius_earths: 2.5, bulk_density_g_cm3: 3.0, equilibrium_temp_k: 600 },
};

const levelExplanations = {
  Beginner: {
    formula: 'KNN finds the most similar planets by comparing temperature, size, and density. The "k" nearest neighbors vote on the type.',
    workedExample: 'Input: Earth-like (288K, 1 R⊕, 5.5 g/cm³). The 3 nearest might be Earth, Venus, and a super-Earth. All are Terrestrial → prediction: Terrestrial.',
  },
  Intermediate: {
    formula: 'Features standardized: z = (x - μ)/σ. Euclidean distance: d = √Σ(z_user - z_train)². Majority vote among k nearest. Type derived from mass: <10 M⊕ = Terrestrial, 10-50 = Neptune-like, >50 = Gas Giant.',
    workedExample: 'Earth: z_temp ≈ -0.5, z_radius ≈ -1.2, z_density ≈ 0.8. Distance to Venus ≈ 0.3, to hot Jupiter ≈ 4.2. k=3 picks 3 closest → all Terrestrial.',
  },
  Advanced: {
    formula: 'Standardization: μ, σ computed on 80% train split. Distance: d(x,x\') = √Σ((xᵢ-μᵢ)/σᵢ - (x\'ᵢ-μᵢ)/σᵢ)². Leave-one-out or held-out accuracy per k. Type from mass only (not feature): M<10=Terrestrial, 10<M<50=Neptune, M>50=Gas Giant.',
    workedExample: 'Train n=2000, test n=500. For k=3: accuracy=0.72. Confusion: Terrestrial→Neptune 12%, Gas Giant→Neptune 8%. Mass threshold at 10 M⊕ creates boundary in (T,R,ρ) space.',
  },
};

export default function MissionExoplanets() {
  const { explorer, loading: explorerLoading } = useExplorer();
  const { addToast } = useToast();
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();
  
  const [datasetKey, setDatasetKey] = useState(searchParams.get('dataset') || 'confirmed_exoplanets');
  const [dataset, setDataset] = useState(null);
  const [formData, setFormData] = useState({
    radius_earths: 1,
    bulk_density_g_cm3: 5.51,
    equilibrium_temp_k: 288,
    k: 3,
    user_guess_type: '',
  });
  const [errors, setErrors] = useState({});
  const [result, setResult] = useState(null);
  const [loading, setLoading] = useState(false);
  const [submitted, setSubmitted] = useState(false);

  useEffect(() => {
    if (!explorerLoading) {
      loadDataset();
    }
  }, [datasetKey, explorerLoading]);

  const loadDataset = async () => {
    try {
      const res = await api.getExoplanetsDataset({ dataset: datasetKey });
      setDataset(res.dataset);
    } catch (e) {
      console.error('Failed to load dataset:', e);
    }
  };

  const validateForm = () => {
    const newErrors = {};
    if (!formData.radius_earths || formData.radius_earths <= 0) newErrors.radius_earths = 'Must be positive';
    if (!formData.bulk_density_g_cm3 || formData.bulk_density_g_cm3 <= 0) newErrors.bulk_density_g_cm3 = 'Must be positive';
    if (!formData.equilibrium_temp_k || formData.equilibrium_temp_k <= 0) newErrors.equilibrium_temp_k = 'Must be positive';
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!validateForm()) return;
    if (!explorer) {
      addToast('Please complete onboarding first', 'error');
      navigate('/onboarding');
      return;
    }

    setLoading(true);
    setSubmitted(true);
    try {
      const res = await api.findNeighbors({ ...formData, dataset: datasetKey });
      setResult(res.prediction);
      addToast('Neighbors found!', 'success');
    } catch (e) {
      addToast(e.message || 'Search failed', 'error');
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
    if (errors[name]) setErrors(prev => ({ ...prev, [name]: '' }));
    setSubmitted(false);
    setResult(null);
  };

  const handlePreset = (preset) => {
    setFormData(prev => ({ ...prev, ...preset }));
    setSubmitted(false);
    setResult(null);
  };

  const handleDatasetChange = (key) => {
    setDatasetKey(key);
    setSearchParams({ dataset: key });
    setResult(null);
    setFormData(prev => ({ ...prev, user_guess_type: '' }));
  };

  const currentLevel = explorer?.level || 'Beginner';
  const levelContent = levelExplanations[currentLevel];

  if (explorerLoading) {
    return <div className="section container text-center"><div className="orbit-animation" style={{margin: '0 auto'}} /></div>;
  }

  return (
    <div className="min-h-screen">
      <section className="section">
        <div className="container max-w-5xl">
          {/* Dataset Switcher */}
          <div className="mission-dataset-bar">
            <label className="block text-sm font-medium text-secondary mb-2">Dataset</label>
            <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Select dataset">
              {Object.entries(datasetOptions).map(([key, info]) => (
                <button
                  key={key}
                  role="radio"
                  aria-checked={datasetKey === key}
                  onClick={() => handleDatasetChange(key)}
                  className={`px-4 py-2 rounded-lg text-sm transition-all ${
                    datasetKey === key
                      ? 'bg-accent-amber text-bg-deep font-semibold shadow-glow'
                      : 'bg-bg-card border border-border text-secondary hover:border-accent-amber hover:text-primary'
                  }`}
                >
                  {info.title}
                </button>
              ))}
            </div>
            {dataset && (
              <div className="mt-3 text-sm text-secondary">
                <strong>Publisher:</strong> {datasetOptions[datasetKey]?.publisher} | 
                <strong>Planets:</strong> {dataset.row_count?.toLocaleString()} | 
                <strong>License:</strong> {dataset.license}
              </div>
            )}
          </div>

          <div className="mission-workspace">
            {/* Input Panel */}
            <div className="mission-input-panel">
              <div className="card sticky top-24 h-fit">
                <h2 className="font-bold text-lg mb-4">Your Turn</h2>
                <p className="text-secondary text-sm mb-4">
                  Adjust planet properties. Use presets or enter custom values. Press <strong>Run Model</strong>.
                </p>
                
                <div className="mb-4">
                  <label className="block text-sm text-secondary mb-2">Presets</label>
                  <div className="flex flex-wrap gap-2">
                    {Object.entries(presets).map(([name, values]) => (
                      <button
                        key={name}
                        type="button"
                        onClick={() => handlePreset(values)}
                        className="btn btn-secondary text-xs py-1 px-2"
                      >
                        {name}
                      </button>
                    ))}
                  </div>
                </div>

                <form onSubmit={handleSubmit} noValidate>
                  <div className="form-group">
                    <label htmlFor="equilibrium_temp_k">Equilibrium Temperature (K)</label>
                    <input
                      type="number"
                      id="equilibrium_temp_k"
                      name="equilibrium_temp_k"
                      value={formData.equilibrium_temp_k}
                      onChange={handleChange}
                      min="10"
                      max="5000"
                      step="1"
                      className={errors.equilibrium_temp_k ? 'border-error' : ''}
                      required
                      disabled={loading}
                    />
                    {errors.equilibrium_temp_k && <p className="text-error text-sm mt-1">{errors.equilibrium_temp_k}</p>}
                  </div>

                  <div className="form-group">
                    <label htmlFor="radius_earths">Radius (Earth Radii)</label>
                    <input
                      type="number"
                      id="radius_earths"
                      name="radius_earths"
                      value={formData.radius_earths}
                      onChange={handleChange}
                      min="0.01"
                      max="50"
                      step="0.01"
                      className={errors.radius_earths ? 'border-error' : ''}
                      required
                      disabled={loading}
                    />
                    {errors.radius_earths && <p className="text-error text-sm mt-1">{errors.radius_earths}</p>}
                  </div>

                  <div className="form-group">
                    <label htmlFor="bulk_density_g_cm3">Bulk Density (g/cm³)</label>
                    <input
                      type="number"
                      id="bulk_density_g_cm3"
                      name="bulk_density_g_cm3"
                      value={formData.bulk_density_g_cm3}
                      onChange={handleChange}
                      min="0.01"
                      max="100"
                      step="0.01"
                      className={errors.bulk_density_g_cm3 ? 'border-error' : ''}
                      required
                      disabled={loading}
                    />
                    {errors.bulk_density_g_cm3 && <p className="text-error text-sm mt-1">{errors.bulk_density_g_cm3}</p>}
                  </div>

                  <div className="form-group">
                    <label htmlFor="k">Number of Neighbors (k)</label>
                    <select
                      id="k"
                      name="k"
                      value={formData.k}
                      onChange={handleChange}
                      disabled={loading}
                    >
                      <option value={1}>1</option>
                      <option value={3}>3</option>
                      <option value={5}>5</option>
                      <option value={7}>7</option>
                      <option value={9}>9</option>
                    </select>
                  </div>

                  <div className="form-group">
                    <label htmlFor="user_guess_type">Your Guess (optional)</label>
                    <select
                      id="user_guess_type"
                      name="user_guess_type"
                      value={formData.user_guess_type}
                      onChange={handleChange}
                      disabled={loading}
                    >
                      <option value="">— Select type —</option>
                      {typeLabels.map(t => <option key={t} value={t}>{t}</option>)}
                    </select>
                  </div>

                  <button
                    type="submit"
                    className="btn btn-primary w-full py-3 mt-2"
                    disabled={loading}
                  >
                    {loading ? 'Finding Neighbors...' : 'Run Model'}
                  </button>
                </form>

                {submitted && result && (
                  <div className="mt-6 p-4 bg-bg-elevated rounded-lg border border-border space-y-2">
                    <h4 className="font-semibold">Prediction</h4>
                    <div className="flex items-center gap-3">
                      <span className="badge badge-primary">{result.prediction}</span>
                      {formData.user_guess_type && (
                        <span className={`font-mono ${formData.user_guess_type === result.prediction ? 'text-accent-teal' : 'text-accent-coral'}`}>
                          Your guess: {formData.user_guess_type} {formData.user_guess_type === result.prediction ? '✓' : '✗'}
                        </span>
                      )}
                    </div>
                    <div className="text-sm text-secondary mt-2">
                      Held-out accuracy (k={formData.k}): {(result.explanation?.held_out_accuracy * 100 || 0).toFixed(1)}%
                    </div>
                  </div>
                )}
              </div>
            </div>

            {/* Explanation Panel */}
            <div className="mission-learning-panel space-y-6">
              <div className="card">
                <h2 className="font-bold text-xl mb-4">Mission Briefing</h2>
                <p className="text-secondary" dangerouslySetInnerHTML={{ __html: 'Find exoplanet "twins" — planets with similar physical properties. Type is derived from MASS only (not a feature!): < 10 M⊕ = Terrestrial/Super-Earth, 10-50 = Neptune-like, > 50 = Gas giant. Features: equilibrium temperature, radius, bulk density. KNN with standardized Euclidean distance.' }} />
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">How the Algorithm Works</h2>
                <div className="space-y-4 text-secondary">
                  <p>
                    <strong>K-Nearest Neighbors:</strong> Simple but effective. No training phase — just store the data.
                    For a query point, compute distance to all training points, pick k closest, majority vote.
                  </p>
                  <p>
                    <strong>Feature Standardization:</strong> Each feature (ln radius, ln density, temperature) is z-scored using
                    train-set mean and std. This prevents temperature (100-3000) from dominating radius (0.1-20).
                  </p>
                  <p>
                    <strong>Distance Metric:</strong> Euclidean distance in 3D standardized space.
                    d = √[(z₁ - z'₁)² + (z₂ - z'₂)² + (z₃ - z'₃)²].
                  </p>
                  <p dangerouslySetInnerHTML={{ __html: '<strong>Type Derivation:</strong> IMPORTANT — type comes from MASS, which is NOT a feature. Mass thresholds: <10 M⊕ (Terrestrial), 10-50 (Neptune-like), >50 (Gas giant). This makes classification non-trivial — similar T/R/ρ can have different types.' }} />
                  <div className="p-3 bg-bg-elevated rounded border border-border">
                    <strong>Worked Example ({currentLevel}):</strong>
                    <p className="mt-2">{levelContent.workedExample}</p>
                  </div>
                </div>
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">Why This Answer?</h2>
                {result && result.explanation ? (
                  <div className="space-y-4">
                    <div className="p-3 bg-bg-elevated rounded border border-border font-mono text-sm">
                      <h4 className="font-semibold mb-2">Standardized Input</h4>
                      <div className="space-y-1">
                        {Object.entries(result.explanation.standardized_input).map(([k, v]) => (
                          <div key={k}>{k}: {v.toFixed(4)}</div>
                        ))}
                      </div>
                    </div>

                    <div className="p-3 bg-bg-elevated rounded border border-border">
                      <h4 className="font-semibold mb-2">{formData.k} Nearest Neighbors</h4>
                      <div className="space-y-2 max-h-64 overflow-auto">
                        {result.explanation.neighbors?.map((n, i) => (
                          <div key={i} className="p-2 bg-bg-deep rounded border border-border text-sm">
                            <div className="flex justify-between">
                              <span className="font-mono">{n.name} ({n.host_star})</span>
                              <span className={`badge ${n.type === 'Terrestrial/Super-Earth' ? 'badge-primary' : n.type === 'Neptune-like' ? 'badge-success' : 'badge-warning'}`}>
                                {n.type}
                              </span>
                            </div>
                            <div className="text-xs text-secondary mt-1">
                              Distance: {n.distance.toFixed(4)} | 
                              ΔT: {n.feature_differences?.eq_temp?.toFixed(3)} | 
                              ΔR: {n.feature_differences?.ln_radius?.toFixed(3)} | 
                              Δρ: {n.feature_differences?.ln_density?.toFixed(3)}
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>

                    <div className="p-3 bg-bg-elevated rounded border border-border">
                      <h4 className="font-semibold mb-2">Vote Counts</h4>
                      <div className="flex flex-wrap gap-2">
                        {Object.entries(result.explanation.vote_counts || {}).map(([type, count]) => (
                          <span key={type} className={`badge ${type === 'Terrestrial/Super-Earth' ? 'badge-primary' : type === 'Neptune-like' ? 'badge-success' : 'badge-warning'}`}>
                            {type}: {count}
                          </span>
                        ))}
                      </div>
                    </div>

                    <div className="p-3 bg-bg-elevated rounded border border-border">
                      <h4 className="font-semibold mb-2">Accuracy by k</h4>
                      <div className="flex flex-wrap gap-2">
                        {Object.entries(result.explanation.all_k_accuracies || {}).map(([k, acc]) => (
                          <span key={k} className={`badge ${k == formData.k ? 'badge-primary' : 'badge-secondary'}`}>
                            k={k}: {(acc * 100).toFixed(1)}%
                          </span>
                        ))}
                      </div>
                    </div>
                  </div>
                ) : (
                  <p className="text-secondary">Enter values and press <strong>Run Model</strong> to see neighbor details and voting.</p>
                )}
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">Try This</h2>
                <ul className="space-y-2 text-secondary">
                  <li>Try Earth-like preset, then increase temperature to 1000K — watch type change</li>
                  <li>Set k=1 vs k=9 — see how voting smooths boundaries</li>
                  <li>Switch to "Planet Host Stars" dataset — input becomes stellar properties</li>
                  <li>Find a hot Jupiter (high T, large R, low ρ) — should classify as Gas Giant</li>
                </ul>
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">Real Dataset</h2>
                <p className="text-secondary text-sm mb-4">
                  Source: {datasetOptions[datasetKey]?.publisher} | {dataset?.license} | Planets: {dataset?.row_count?.toLocaleString()}
                </p>
                <div className="table-container">
                  <table>
                    <thead>
                      <tr>
                        <th>Name</th>
                        <th>Host Star</th>
                        <th>Radius (R⊕)</th>
                        <th>Density (g/cm³)</th>
                        <th>Temp (K)</th>
                        <th>Mass (M⊕)</th>
                      </tr>
                    </thead>
                    <tbody>
                      {dataset?.planets?.data?.slice(0, 10).map((p, i) => (
                        <tr key={i}>
                          <td className="font-mono">{p.name}</td>
                          <td>{p.host_star}</td>
                          <td className="font-mono">{p.radius_earths}</td>
                          <td className="font-mono">{p.bulk_density_g_cm3}</td>
                          <td className="font-mono">{p.equilibrium_temp_k}</td>
                          <td className="font-mono">{p.mass_earths}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <p className="text-muted text-xs mt-2">
                  Type derived from mass only. Features: temperature, radius, density. 
                  {datasetKey === 'planet_host_stars' ? 'This dataset uses stellar properties (radius, mass, luminosity) to find star twins.' : ''}
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}
