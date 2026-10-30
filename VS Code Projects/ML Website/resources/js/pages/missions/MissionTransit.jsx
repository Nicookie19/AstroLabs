import React, { useEffect, useState, useRef } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { useExplorer } from '../../context/ExplorerContext';
import { api } from '../../utils/api';
import { useToast } from '../../context/ToastContext';

const datasetOptions = {
  kepler_koi: { title: 'Kepler Objects of Interest', publisher: 'NASA Exoplanet Archive' },
  tess_toi: { title: 'TESS Objects of Interest', publisher: 'NASA Exoplanet Archive' },
};

const levelExplanations = {
  Beginner: {
    formula: 'SVM draws a line separating transits from non-transits. Points on one side = planet, other side = star. The "margin" is the buffer zone around the line.',
    workedExample: 'Input: Depth=500 ppm, SNR=50. Log: depth=2.7, SNR=1.7. If point is above the line → Transit. Distance from line = confidence.',
  },
  Intermediate: {
    formula: 'Linear SVM: f(x) = w·x + b. Hinge loss: max(0, 1 - y·f(x)). SGD updates: w ← w - λw + y·x (if margin < 1). Features: log₁₀(depth), log₁₀(SNR). Labels: +1=Transit, -1=Star.',
    workedExample: 'Trained on 2000 balanced KOIs. Decision boundary: w₀·log₁₀(depth) + w₁·log₁₀(SNR) + b = 0. Margin = 1/||w||. Points within margin are support vectors.',
  },
  Advanced: {
    formula: 'Primal: min ½||w||² + C Σ ξᵢ s.t. yᵢ(w·xᵢ+b) ≥ 1-ξᵢ, ξᵢ≥0. Dual: max Σαᵢ - ½ΣΣαᵢαⱼyᵢyⱼxᵢ·xⱼ. SGD on primal with λ=1/C. 300 epochs, lr=0.05. Standardized features.',
    workedExample: 'log₁₀(depth) mean=2.1, std=0.8. log₁₀(SNR) mean=1.5, std=0.6. w=[0.42, -0.31], b=-0.15. Test accuracy=0.87. Support vectors: 234/2000 points with margin < 1.',
  },
};

export default function MissionTransit() {
  const { explorer, loading: explorerLoading } = useExplorer();
  const { addToast } = useToast();
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();
  
  const [datasetKey, setDatasetKey] = useState(searchParams.get('dataset') || 'kepler_koi');
  const [dataset, setDataset] = useState(null);
  const [formData, setFormData] = useState({
    transit_depth_ppm: 500,
    transit_duration_hours: 3,
    signal_to_noise: 50,
    lambda: 0.01,
    user_guess_label: '',
  });
  const [errors, setErrors] = useState({});
  const [result, setResult] = useState(null);
  const [boundaryData, setBoundaryData] = useState(null);
  const [lightCurve, setLightCurve] = useState(null);
  const [loading, setLoading] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const chartRef = useRef(null);

  useEffect(() => {
    if (!explorerLoading) {
      loadDataset();
    }
  }, [datasetKey, explorerLoading]);

  const loadDataset = async () => {
    try {
      const res = await api.getTransitDataset({ dataset: datasetKey });
      setDataset(res.dataset);
    } catch (e) {
      console.error('Failed to load dataset:', e);
    }
  };

  const validateForm = () => {
    const newErrors = {};
    if (!formData.transit_depth_ppm || formData.transit_depth_ppm <= 0) newErrors.transit_depth_ppm = 'Must be positive';
    if (!formData.transit_duration_hours || formData.transit_duration_hours <= 0) newErrors.transit_duration_hours = 'Must be positive';
    if (!formData.signal_to_noise || formData.signal_to_noise <= 0) newErrors.signal_to_noise = 'Must be positive';
    if (!formData.lambda || formData.lambda <= 0) newErrors.lambda = 'Must be positive';
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
      const [classifyRes] = await Promise.all([
        api.classifyTransit({ ...formData, dataset: datasetKey }),
      ]);
      setResult(classifyRes.classification);
      setLightCurve({
        time: Array.from({length: 100}, (_, i) => -2 + i * 0.04),
        flux: Array.from({length: 100}, (_, i) => {
          const t = -2 + i * 0.04;
          const dur = formData.transit_duration_hours / 24 / 2;
          return Math.abs(t) < dur ? 1 - formData.transit_depth_ppm / 1e6 : 1;
        }),
        note: 'Reconstructed from catalog values (depth, duration), not raw telescope data.',
      });
      addToast('Transit classified!', 'success');
    } catch (e) {
      addToast(e.message || 'Classification failed', 'error');
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: parseFloat(value) || value }));
    if (errors[name]) setErrors(prev => ({ ...prev, [name]: '' }));
    setSubmitted(false);
    setResult(null);
    setBoundaryData(null);
    setLightCurve(null);
  };

  const handleDatasetChange = (key) => {
    setDatasetKey(key);
    setSearchParams({ dataset: key });
    setResult(null);
    setBoundaryData(null);
    setLightCurve(null);
    setFormData(prev => ({ ...prev, user_guess_label: '' }));
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
                <strong>Candidates:</strong> {dataset.row_count?.toLocaleString()} | 
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
                  Enter transit signal parameters. Adjust lambda (margin penalty). Press <strong>Run Model</strong>.
                </p>
                
                <form onSubmit={handleSubmit} noValidate>
                  <div className="form-group">
                    <label htmlFor="transit_depth_ppm">Transit Depth (ppm)</label>
                    <input
                      type="number"
                      id="transit_depth_ppm"
                      name="transit_depth_ppm"
                      value={formData.transit_depth_ppm}
                      onChange={handleChange}
                      min="0.1"
                      max="50000"
                      step="1"
                      className={errors.transit_depth_ppm ? 'border-error' : ''}
                      required
                      disabled={loading}
                    />
                    {errors.transit_depth_ppm && <p className="text-error text-sm mt-1">{errors.transit_depth_ppm}</p>}
                  </div>

                  <div className="form-group">
                    <label htmlFor="transit_duration_hours">Transit Duration (hours)</label>
                    <input
                      type="number"
                      id="transit_duration_hours"
                      name="transit_duration_hours"
                      value={formData.transit_duration_hours}
                      onChange={handleChange}
                      min="0.1"
                      max="24"
                      step="0.1"
                      className={errors.transit_duration_hours ? 'border-error' : ''}
                      required
                      disabled={loading}
                    />
                    {errors.transit_duration_hours && <p className="text-error text-sm mt-1">{errors.transit_duration_hours}</p>}
                  </div>

                  <div className="form-group">
                    <label htmlFor="signal_to_noise">Signal-to-Noise Ratio</label>
                    <input
                      type="number"
                      id="signal_to_noise"
                      name="signal_to_noise"
                      value={formData.signal_to_noise}
                      onChange={handleChange}
                      min="1"
                      max="1000"
                      step="0.1"
                      className={errors.signal_to_noise ? 'border-error' : ''}
                      required
                      disabled={loading}
                    />
                    {errors.signal_to_noise && <p className="text-error text-sm mt-1">{errors.signal_to_noise}</p>}
                  </div>

                  <div className="form-group">
                    <label htmlFor="lambda">Lambda (Margin Penalty)</label>
                    <input
                      type="number"
                      id="lambda"
                      name="lambda"
                      value={formData.lambda}
                      onChange={handleChange}
                      min="0.001"
                      max="1"
                      step="0.001"
                      className={errors.lambda ? 'border-error' : ''}
                      required
                      disabled={loading}
                    />
                    {errors.lambda && <p className="text-error text-sm mt-1">{errors.lambda}</p>}
                    <p className="text-muted text-xs mt-1">Higher = stricter margin, more regularization</p>
                  </div>

                  <div className="form-group">
                    <label htmlFor="user_guess_label">Your Guess (optional)</label>
                    <select
                      id="user_guess_label"
                      name="user_guess_label"
                      value={formData.user_guess_label}
                      onChange={handleChange}
                      disabled={loading}
                    >
                      <option value="">— Select —</option>
                      <option value="Exoplanet Transit Detected">Exoplanet Transit Detected</option>
                      <option value="Standard Star (no planet signal)">Standard Star (no planet signal)</option>
                    </select>
                  </div>

                  <button
                    type="submit"
                    className="btn btn-primary w-full py-3 mt-2"
                    disabled={loading}
                  >
                    {loading ? 'Classifying...' : 'Run Model'}
                  </button>
                </form>

                {submitted && result && (
                  <div className="mt-6 p-4 bg-bg-elevated rounded-lg border border-border space-y-2">
                    <h4 className="font-semibold">Classification</h4>
                    <div className="flex items-center gap-3">
                      <span className={`badge ${result.label === 'Exoplanet Transit Detected' ? 'badge-success' : 'badge-danger'}`}>
                        {result.label}
                      </span>
                    </div>
                    <div className="grid grid-cols-2 gap-2 text-sm">
                      <div><span className="text-secondary">Score:</span> <span className="font-mono">{result.score}</span></div>
                      <div><span className="text-secondary">Margin dist:</span> <span className="font-mono">{result.distance_from_boundary}</span></div>
                    </div>
                    {formData.user_guess_label && (
                      <div className="text-sm">
                        <span className="text-secondary">Your guess:</span> 
                        <span className={`font-mono ${formData.user_guess_label === result.label ? 'text-accent-teal' : 'text-accent-coral'}`}>
                          {formData.user_guess_label} {formData.user_guess_label === result.label ? '✓' : '✗'}
                        </span>
                      </div>
                    )}
                    <details className="mt-4">
                      <summary className="cursor-pointer text-sm text-secondary">Explanation details</summary>
                      <pre className="mt-2 bg-bg-deep p-2 rounded text-xs overflow-auto font-mono">
                        {JSON.stringify(result.explanation, null, 2)}
                      </pre>
                    </details>
                  </div>
                )}
              </div>
            </div>

            {/* Explanation Panel */}
            <div className="mission-learning-panel space-y-6">
              <div className="card">
                <h2 className="font-bold text-xl mb-4">Mission Briefing</h2>
                <p className="text-secondary">
                  Kepler and TESS monitor millions of stars for tiny brightness dips.
                  Most dips are false positives (starspots, binaries, noise).
                  SVM finds the optimal separating line between real transits and impostors.
                </p>
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">How the Algorithm Works</h2>
                <div className="space-y-4 text-secondary">
                  <p>
                    <strong>Linear SVM with Hinge Loss:</strong> We want a decision boundary f(x) = w·x + b = 0
                    that separates +1 (Transit) from -1 (Star). Hinge loss: max(0, 1 - y·f(x)).
                  </p>
                  <p>
                    <strong>SGD Training:</strong> {'For each sample, if y·f(x) < 1 (inside margin or wrong side),'}{' '}
                    update w ← w - λw + y·x, b ← b + y. Else w ← w - λw. Repeat for 300 epochs.
                  </p>
                  <p>
                    <strong>Features:</strong> log₁₀(transit depth ppm) and log₁₀(signal-to-noise).
                    Both standardized using train-set mean/std. Lambda controls regularization.
                  </p>
                  <p dangerouslySetInnerHTML={{ __html: '<strong>Support Vectors:</strong> Points with margin < 1. They "support" the boundary. In SGD, every update from a point with margin < 1 acts as a support vector.' }} />
                  <div className="p-3 bg-bg-elevated rounded border border-border">
                    <strong>Worked Example ({currentLevel}):</strong>
                    <p className="mt-2">{levelContent.workedExample}</p>
                  </div>
                </div>
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">Decision Boundary Visualization</h2>
                {result ? (
                  <div className="space-y-4">
                    <div className="p-3 bg-bg-elevated rounded border border-border">
                      <h4 className="font-semibold mb-2">Your Point on the Boundary</h4>
                      <div className="grid grid-cols-2 gap-2 text-sm">
                        <div><span className="text-secondary">log₁₀(Depth):</span> <span className="font-mono">{result.explanation?.standardized?.depth?.toFixed(3)}</span></div>
                        <div><span className="text-secondary">log₁₀(SNR):</span> <span className="font-mono">{result.explanation?.standardized?.snr?.toFixed(3)}</span></div>
                        <div><span className="text-secondary">Decision Score:</span> <span className="font-mono">{result.explanation?.score}</span></div>
                        <div><span className="text-secondary">Margin Distance:</span> <span className="font-mono">{result.explanation?.distance_from_boundary}</span></div>
                      </div>
                    </div>

                    <div>
                      <h4 className="font-semibold mb-2">Scatter Plot (Conceptual)</h4>
                      <div className="aspect-square bg-bg-deep rounded border border-border flex items-center justify-center">
                        <canvas ref={chartRef} width={400} height={400} className="w-full h-full" />
                        <p className="text-muted text-center">SVM boundary chart would render here</p>
                      </div>
                    </div>

                    <div className="p-3 bg-bg-elevated rounded border border-border">
                      <h4 className="font-semibold mb-2">Reconstructed Light Curve</h4>
                      <p className="text-muted text-sm mb-2">{lightCurve?.note}</p>
                      <div className="aspect-video bg-bg-deep rounded border border-border flex items-center justify-center">
                        <p className="text-muted">Light curve visualization (depth: {formData.transit_depth_ppm} ppm, duration: {formData.transit_duration_hours}h)</p>
                      </div>
                    </div>
                  </div>
                ) : (
                  <p className="text-secondary">Enter values and press <strong>Run Model</strong> to see the decision boundary with your point marked.</p>
                )}
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">Try This</h2>
                <ul className="space-y-2 text-secondary">
                  <li>Set depth=100 ppm, SNR=10, lambda=0.01 — likely "Standard Star"</li>
                  <li>Set depth=5000 ppm, SNR=100 — should be "Exoplanet Transit Detected"</li>
                  <li>Increase lambda to 0.1 — boundary becomes simpler, more regularization</li>
                  <li>Switch to TESS dataset — same features, different distribution</li>
                </ul>
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">Real Dataset</h2>
                <p className="text-secondary text-sm mb-4">
                  Source: {datasetOptions[datasetKey]?.publisher} | {dataset?.license} | Candidates: {dataset?.row_count?.toLocaleString()}
                </p>
                <div className="table-container">
                  <table>
                    <thead>
                      <tr>
                        <th>KOI Name</th>
                        <th>Disposition</th>
                        <th>Depth (ppm)</th>
                        <th>SNR</th>
                        <th>Period (days)</th>
                      </tr>
                    </thead>
                    <tbody>
                      {dataset?.candidates?.data?.slice(0, 10).map((c, i) => (
                        <tr key={i}>
                          <td className="font-mono">{c.kepoi_name}</td>
                          <td><span className={`badge ${c.disposition === 'CONFIRMED' ? 'badge-success' : 'badge-danger'}`}>{c.disposition}</span></td>
                          <td className="font-mono">{c.transit_depth_ppm}</td>
                          <td className="font-mono">{c.signal_to_noise}</td>
                          <td className="font-mono">{c.orbital_period_days}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <p className="text-muted text-xs mt-2">
                  CONFIRMED = "Exoplanet Transit Detected", FALSE POSITIVE = "Standard Star (no planet signal)". 
                  Kepler KOI cumulative table / TESS TOI table from NASA Exoplanet Archive.
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}
