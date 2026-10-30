import React, { useEffect, useState, useRef } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { useExplorer } from '../../context/ExplorerContext';
import { api } from '../../utils/api';
import { useToast } from '../../context/ToastContext';

const datasetOptions = {
  all_confirmed: { title: 'All Confirmed Exoplanets', publisher: 'NASA Exoplanet Archive' },
  kepler: { title: 'Kepler-discovered Planets', publisher: 'NASA Exoplanet Archive' },
  tess: { title: 'TESS-discovered Planets', publisher: 'NASA Exoplanet Archive' },
  radial_velocity: { title: 'Radial Velocity Discoveries', publisher: 'NASA Exoplanet Archive' },
  solar_system: { title: 'Our Solar System', publisher: 'NASA/JPL Planetary Fact Sheets' },
};

const levelExplanations = {
  Beginner: {
    formula: 'The orbital period depends on distance from the star and the star\'s mass. Farther planets take longer to orbit. More massive stars pull harder, making orbits faster.',
    workedExample: 'Earth orbits at 1 AU around a 1 solar mass star. Period = 365 days. If we double the distance to 2 AU, the period becomes about 2.8× longer (≈1030 days). If we double the star mass, the period becomes about 0.7× shorter (≈258 days).',
  },
  Intermediate: {
    formula: 'Kepler\'s Third Law: T² ∝ a³/M. In log form: ln(T) = b₀ + b₁·ln(a) + b₂·ln(M). Our model learns b₁ ≈ 1.5 (expected) and b₂ ≈ -0.5 (expected).',
    workedExample: 'For Earth: a=1 AU, M=1 M☉. ln(T) = b₀ + b₁·0 + b₂·0 = b₀. So T = e^{b₀}. The model learns b₀ from the data.',
  },
  Advanced: {
    formula: 'Normal equations: (XᵀX)β = Xᵀy where X = [1, ln(a), ln(M)] and y = ln(T). Solution: β = (XᵀX)⁻¹Xᵀy via Gaussian elimination. R² = 1 - SS_res/SS_tot on held-out 20% test set.',
    workedExample: 'Design matrix X has rows [1, ln(aᵢ), ln(Mᵢ)]. Target vector y has ln(Tᵢ). The 3×3 system solves for intercept, distance exponent, mass exponent. Learned exponents deviate from Kepler due to selection effects and measurement noise.',
  },
};

export default function MissionOrbit() {
  const { explorer, loading: explorerLoading } = useExplorer();
  const { addToast } = useToast();
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();
  
  const [datasetKey, setDatasetKey] = useState(searchParams.get('dataset') || 'all_confirmed');
  const [dataset, setDataset] = useState(null);
  const [model, setModel] = useState(null);
  const [formData, setFormData] = useState({
    semi_major_axis_au: 1,
    star_mass_solar: 1,
    user_guess_days: '',
  });
  const [errors, setErrors] = useState({});
  const [result, setResult] = useState(null);
  const [comparison, setComparison] = useState(null);
  const [closestPlanet, setClosestPlanet] = useState(null);
  const [loading, setLoading] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [showComparison, setShowComparison] = useState(false);
  const chartRef = useRef(null);

  useEffect(() => {
    if (!explorerLoading) {
      loadDataset();
    }
  }, [datasetKey, explorerLoading]);

  const loadDataset = async () => {
    try {
      const [datasetRes, modelRes] = await Promise.all([
        api.getOrbitDataset({ dataset: datasetKey }),
        // Model is loaded via the predict call, but we can fetch dataset info
      ]);
      setDataset(datasetRes.dataset);
    } catch (e) {
      console.error('Failed to load dataset:', e);
    }
  };

  const validateForm = () => {
    const newErrors = {};
    if (!formData.semi_major_axis_au || formData.semi_major_axis_au <= 0) newErrors.semi_major_axis_au = 'Must be positive';
    if (!formData.star_mass_solar || formData.star_mass_solar <= 0) newErrors.star_mass_solar = 'Must be positive';
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
      const [predictRes, comparisonRes, closestRes] = await Promise.all([
        api.predictOrbit({ ...formData, dataset: datasetKey }),
        api.compareOrbitDatasets({ semi_major_axis_au: formData.semi_major_axis_au, star_mass_solar: formData.star_mass_solar }),
        // Closest planet is in predict response
      ]);
      
      setResult(predictRes.prediction);
      setComparison(comparisonRes.comparisons);
      setClosestPlanet(predictRes.closest_real_planet);
      addToast('Orbit calculated!', 'success');
    } catch (e) {
      addToast(e.message || 'Prediction failed', 'error');
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
    setComparison(null);
    setClosestPlanet(null);
  };

  const handleDatasetChange = (key) => {
    setDatasetKey(key);
    setSearchParams({ dataset: key });
    setResult(null);
    setComparison(null);
    setClosestPlanet(null);
    setFormData(prev => ({ ...prev, user_guess_days: '' }));
  };

  const explanation = result ? result.explanation : null;
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
                <strong>Rows:</strong> {dataset.row_count?.toLocaleString()} | 
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
                  Enter orbital parameters and press <strong>Run Model</strong>. 
                  Optionally guess the period for points!
                </p>
                
                <form onSubmit={handleSubmit} noValidate>
                  <div className="form-group">
                    <label htmlFor="semi_major_axis_au">Semi-major Axis (AU)</label>
                    <input
                      type="number"
                      id="semi_major_axis_au"
                      name="semi_major_axis_au"
                      value={formData.semi_major_axis_au}
                      onChange={handleChange}
                      min="0.001"
                      max="1000"
                      step="0.01"
                      className={errors.semi_major_axis_au ? 'border-error' : ''}
                      required
                      disabled={loading}
                    />
                    {errors.semi_major_axis_au && <p className="text-error text-sm mt-1">{errors.semi_major_axis_au}</p>}
                  </div>

                  <div className="form-group">
                    <label htmlFor="star_mass_solar">Star Mass (Solar Masses)</label>
                    <input
                      type="number"
                      id="star_mass_solar"
                      name="star_mass_solar"
                      value={formData.star_mass_solar}
                      onChange={handleChange}
                      min="0.01"
                      max="100"
                      step="0.01"
                      className={errors.star_mass_solar ? 'border-error' : ''}
                      required
                      disabled={loading}
                    />
                    {errors.star_mass_solar && <p className="text-error text-sm mt-1">{errors.star_mass_solar}</p>}
                  </div>

                  <div className="form-group">
                    <label htmlFor="user_guess_days">Your Guess: Period (days) <span className="font-normal text-muted">(optional)</span></label>
                    <input
                      type="number"
                      id="user_guess_days"
                      name="user_guess_days"
                      value={formData.user_guess_days}
                      onChange={handleChange}
                      min="0.01"
                      max="10000000"
                      step="0.1"
                      className={errors.user_guess_days ? 'border-error' : ''}
                      placeholder="e.g., 365"
                    />
                  </div>

                  <button
                    type="submit"
                    className="btn btn-primary w-full py-3 mt-2"
                    disabled={loading || !formData.semi_major_axis_au || !formData.star_mass_solar}
                  >
                    {loading ? 'Calculating...' : 'Run Model'}
                  </button>
                </form>

                {submitted && result && (
                  <div className="mt-6 p-4 bg-bg-elevated rounded-lg border border-border space-y-2">
                    <h4 className="font-semibold">Result</h4>
                    <div className="grid grid-cols-2 gap-2 text-sm">
                      <div><span className="text-secondary">Period:</span> <span className="font-mono font-bold text-accent-amber">{result.period_days.toLocaleString()} days</span></div>
                      <div><span className="text-secondary">Years:</span> <span className="font-mono">{result.period_years.toFixed(2)}</span></div>
                    </div>
                    {formData.user_guess_days && (
                      <div className="text-sm">
                        <span className="text-secondary">Your guess:</span> <span className="font-mono">{parseFloat(formData.user_guess_days).toLocaleString()} days</span>
                        <span className="ml-2 text-accent-teal">({result.user_guess ? '✓' : '✗'})</span>
                      </div>
                    )}
                    {closestPlanet && (
                      <div className="text-sm mt-2 p-2 bg-bg-deep rounded border border-border">
                        <span className="text-secondary">Closest real planet:</span>
                        <span className="font-mono ml-2">{closestPlanet.name} ({closestPlanet.host_star})</span>
                        <span className="text-muted ml-2">Period: {closestPlanet.orbital_period_days?.toLocaleString()} days</span>
                      </div>
                    )}
                  </div>
                )}

                {showComparison && comparison && (
                  <div className="mt-6">
                    <h4 className="font-semibold mb-3">Compare Datasets</h4>
                    <div className="table-container">
                      <table>
                        <thead>
                          <tr>
                            <th>Dataset</th>
                            <th>Prediction (days)</th>
                            <th>R²</th>
                            <th>a exponent</th>
                            <th>M exponent</th>
                          </tr>
                        </thead>
                        <tbody>
                          {comparison.map(c => (
                            <tr key={c.dataset_key}>
                              <td className="font-medium">{c.dataset_title}</td>
                              <td className="font-mono">{c.prediction.period_days.toLocaleString()}</td>
                              <td>{c.test_r2?.toFixed(4)}</td>
                              <td className="font-mono">{c.learned_exponents?.learned_a?.toFixed(3)}</td>
                              <td className="font-mono">{c.learned_exponents?.learned_m?.toFixed(3)}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                    <p className="text-sm text-secondary mt-2">{levelExplanations.Advanced.workedExample}</p>
                  </div>
                )}
              </div>
            </div>

            {/* Explanation Panel */}
            <div className="mission-learning-panel space-y-6">
              <div className="card">
                <h2 className="font-bold text-xl mb-4">Mission Briefing</h2>
                <p className="text-secondary">
                  Astrolabs needs to predict orbital periods for newly discovered exoplanets.
                  Kepler's Third Law says T² ∝ a³/M, but real exoplanet data has noise, selection effects,
                  and measurement errors. Can a linear regression on log-transformed data recover the law?
                </p>
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">How the Algorithm Works</h2>
                <div className="space-y-4 text-secondary">
                  <p>
                    <strong>Multiple Linear Regression on Logs:</strong> We transform Kepler's law to linear form:
                    ln(T) = b₀ + b₁·ln(a) + b₂·ln(M), where T = period (days), a = semi-major axis (AU), M = star mass (solar).
                  </p>
                  <p>
                    <strong>Normal Equations:</strong> We solve (XᵀX)β = Xᵀy using Gaussian elimination (no matrix inversion libraries).
                    The design matrix X has columns [1, ln(a), ln(M)], target y = ln(T).
                  </p>
                  <p>
                    <strong>Training:</strong> 80/20 random split. Model learns coefficients b₀, b₁, b₂.
                    We report R² on the held-out 20% test set.
                  </p>
                  <p>
                    <strong>Expected vs Learned:</strong> Kepler predicts b₁ ≈ 1.5, b₂ ≈ -0.5.
                    Real exoplanet data shows deviations due to selection bias (we detect close-in planets more easily).
                  </p>
                  <div className="p-3 bg-bg-elevated rounded border border-border">
                    <strong>Worked Example ({currentLevel}):</strong>
                    <p className="mt-2">{levelContent.workedExample}</p>
                  </div>
                </div>
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">Why This Answer?</h2>
                {result && explanation ? (
                  <div className="space-y-4">
                    <div className="p-3 bg-bg-elevated rounded border border-border font-mono text-sm">
                      <div>{explanation.formula}</div>
                      <div className="mt-1">{explanation.substituted}</div>
                      <div className="mt-2">Steps:</div>
                      {explanation.steps.map((step, i) => (
                        <div key={i} className="ml-4">→ {step}</div>
                      ))}
                    </div>
                    
                    <div className="grid md:grid-cols-2 gap-4">
                      <div className="p-3 bg-bg-elevated rounded border border-border">
                        <h4 className="font-semibold mb-2">Kepler Comparison</h4>
                        <div className="space-y-1 text-sm">
                          <div><span className="text-secondary">Distance exponent (a):</span> Expected 1.50, Learned {explanation.kepler_comparison?.learned_exponent_a}</div>
                          <div><span className="text-secondary">Mass exponent (M):</span> Expected -0.50, Learned {explanation.kepler_comparison?.learned_exponent_m}</div>
                        </div>
                      </div>
                      <div className="p-3 bg-bg-elevated rounded border border-border">
                        <h4 className="font-semibold mb-2">Model Quality</h4>
                        <div className="space-y-1 text-sm">
                          <div><span className="text-secondary">Test R²:</span> {explanation.model_metrics?.test_r2?.toFixed(4)}</div>
                          <div><span className="text-secondary">Test RMSE:</span> {explanation.model_metrics?.test_rmse?.toFixed(2)} days</div>
                        </div>
                      </div>
                    </div>
                  </div>
                ) : (
                  <p className="text-secondary">Enter values and press <strong>Run Model</strong> to see the step-by-step explanation.</p>
                )}
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">Try This</h2>
                <ul className="space-y-2 text-secondary">
                  <li>Set star mass to 0.5 and see what happens to the period</li>
                  <li>Set semi-major axis to 5 AU (Jupiter-like) with star mass 1</li>
                  <li>Try the Solar System dataset — the exponents should be nearly perfect</li>
                  <li>Compare all 5 datasets using the button above</li>
                </ul>
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">Real Dataset</h2>
                <p className="text-secondary text-sm mb-4">
                  Source: {datasetOptions[datasetKey]?.publisher} | {dataset?.license} | Rows: {dataset?.row_count?.toLocaleString()}
                </p>
                <div className="table-container">
                  <table>
                    <thead>
                      <tr>
                        <th>Name</th>
                        <th>Host Star</th>
                        <th>Period (days)</th>
                        <th>Semi-major Axis (AU)</th>
                        <th>Star Mass (M☉)</th>
                        <th>Method</th>
                      </tr>
                    </thead>
                    <tbody>
                      {dataset?.planets?.data?.slice(0, 10).map((planet, i) => (
                        <tr key={i}>
                          <td className="font-mono">{planet.name}</td>
                          <td>{planet.host_star}</td>
                          <td className="font-mono">{planet.orbital_period_days?.toLocaleString()}</td>
                          <td className="font-mono">{planet.semi_major_axis_au}</td>
                          <td className="font-mono">{planet.star_mass_solar}</td>
                          <td>{planet.discovery_method}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <p className="text-muted text-xs mt-2">
                  {datasetOptions[datasetKey]?.title} — {dataset?.row_count?.toLocaleString()} planets. 
                  {datasetKey === 'solar_system' ? 'Real solar system bodies from NASA/JPL fact sheets.' : 'From NASA Exoplanet Archive pscomppars table.'}
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}
