import React, { useEffect, useState } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { useExplorer } from '../../context/ExplorerContext';
import { api } from '../../utils/api';
import { useToast } from '../../context/ToastContext';

const datasetOptions = {
  messier: { title: 'Messier Objects', publisher: 'OpenNGC' },
  bright_ngc_ic: { title: 'Brightest NGC and IC Objects (mag < 10)', publisher: 'OpenNGC' },
  solar_system: { title: 'Solar System Bodies', publisher: 'NASA/JPL' },
};

const distanceRankLabels = {
  0: 'Moon (distance rank 0)',
  1: 'Planet (distance rank 1)',
  2: 'Inside Milky Way (distance rank 2)',
  3: 'Beyond Milky Way (distance rank 3)',
};

const levelExplanations = {
  Beginner: {
    formula: 'The telescope wizard asks two questions: How dark is your sky (Bortle 1-9)? How far is your target? Rules: Light-polluted sky + deep sky = light pollution filter, long exposure. Dark sky + planet = neutral density filter, short exposure.',
    workedExample: 'Bortle 7 (suburban), target M31 (galaxy, rank 3). Path: Bortle≥6 → Distance≥2 → Light pollution filter, 180s, 200mm. Dark sky (Bortle 3), same target: Bortle<6 → Distance≥3 → No filter, 600s, 300mm.',
  },
  Intermediate: {
    formula: 'Decision tree with explicit rules:\n1. Bortle ≥ 6?\n   - Yes: Distance ≥ 2? → (Light-pollution filter, 180s, 200mm) : (Colour planetary filter, 1/60s, 100mm)\n   - No: Distance ≥ 2?\n       - Yes: Distance ≥ 3? → (No filter, 600s, 300mm) : (H-alpha filter, 240s, 200mm)\n       - No: (Neutral density filter, 1/125s, 80mm)',
    workedExample: 'Bortle 4, target Jupiter (rank 1). Path: Bortle<6 → Distance<2 → ND filter, 1/125s, 80mm. Why? Dark sky but bright planet — need to reduce brightness, not gather light.',
  },
  Advanced: {
    formula: 'Tree structure is hardcoded expert rules (NOT learned). Splits chosen by domain knowledge:\n- Bortle scale: sky brightness affects SNR for faint objects\n- Distance rank: determines angular size, surface brightness, optimal exposure\nNo training data exists for "correct settings" — these are rules of thumb from astrophotography community.',
    workedExample: 'Bortle 5, M42 (Orion Nebula, rank 2). Path: Bortle<6 → Distance≥2 (but <3) → H-alpha filter, 240s, 200mm. H-alpha isolates 656nm emission from ionized hydrogen. 240s balances SNR vs trailing. 200mm aperture for light grasp.',
  },
};

export default function MissionTelescope() {
  const { explorer, loading: explorerLoading } = useExplorer();
  const { addToast } = useToast();
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();
  
  const [datasetKey, setDatasetKey] = useState(searchParams.get('dataset') || 'messier');
  const [dataset, setDataset] = useState(null);
  const [targets, setTargets] = useState([]);
  const [constellation, setConstellation] = useState('');
  const [formData, setFormData] = useState({
    bortle_scale: 4,
    target_id: '',
    user_guess: { filter: '', exposure: '', aperture: '' },
  });
  const [errors, setErrors] = useState({});
  const [result, setResult] = useState(null);
  const [loading, setLoading] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');

  useEffect(() => {
    if (!explorerLoading) {
      loadDataset();
    }
  }, [datasetKey, explorerLoading]);

  const loadDataset = async () => {
    try {
      const response = await api.getTelescopeTargets({ dataset: datasetKey });
      setDataset(response.dataset);
      setTargets(response.targets);
    } catch (e) {
      console.error('Failed to load dataset:', e);
    }
  };

  const validateForm = () => {
    const newErrors = {};
    if (!formData.bortle_scale || formData.bortle_scale < 1 || formData.bortle_scale > 9) newErrors.bortle_scale = 'Must be 1-9';
    if (!formData.target_id) newErrors.target_id = 'Select a target';
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
      const res = await api.recommendTelescope({ ...formData, dataset: datasetKey });
      setResult(res.recommendation);
      addToast('Recommendation ready!', 'success');
    } catch (e) {
      addToast(e.message || 'Recommendation failed', 'error');
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    if (name.startsWith('user_guess.')) {
      setFormData(prev => ({ ...prev, user_guess: { ...prev.user_guess, [name.split('.')[1]]: value } }));
    } else {
      setFormData(prev => ({ ...prev, [name]: parseInt(value) || value }));
    }
    if (errors[name]) setErrors(prev => ({ ...prev, [name]: '' }));
    setSubmitted(false);
    setResult(null);
  };

  const handleDatasetChange = (key) => {
    setDatasetKey(key);
    setSearchParams({ dataset: key });
    setResult(null);
    setFormData(prev => ({ ...prev, target_id: '', user_guess: { filter: '', exposure: '', aperture: '' } }));
    setSearchTerm('');
    setConstellation('');
  };

  const currentLevel = explorer?.level || 'Beginner';
  const levelContent = levelExplanations[currentLevel];

  if (explorerLoading) {
    return <div className="section container text-center"><div className="orbit-animation" style={{margin: '0 auto'}} /></div>;
  }

  const constellations = [...new Set(targets.map(target => target.constellation).filter(Boolean))].sort();
  const visibleTargets = targets.filter(target => {
    const matchesConstellation = !constellation || target.constellation === constellation;
    const searchable = `${target.name} ${target.catalog_id} ${target.category}`.toLowerCase();
    return matchesConstellation && searchable.includes(searchTerm.toLowerCase());
  });

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
                <strong>Targets:</strong> {dataset.row_count?.toLocaleString()} | 
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
                  Select sky darkness (Bortle 1-9) and a target. Optionally guess the settings for points!
                </p>
                
                <form onSubmit={handleSubmit} noValidate>
                  <div className="form-group">
                    <label htmlFor="bortle_scale">Bortle Sky Darkness (1-9)</label>
                    <input
                      type="range"
                      id="bortle_scale"
                      name="bortle_scale"
                      value={formData.bortle_scale}
                      onChange={handleChange}
                      min="1"
                      max="9"
                      step="1"
                      className="w-full"
                      disabled={loading}
                    />
                    <div className="flex justify-between text-xs text-secondary mt-1">
                      <span>1 = Excellent dark sky</span>
                      <span>{formData.bortle_scale}</span>
                      <span>9 = Inner city</span>
                    </div>
                  </div>

                  <div className="form-group">
                    <label htmlFor="constellation">Constellation</label>
                    <select id="constellation" value={constellation} onChange={(e) => {
                      setConstellation(e.target.value);
                      setFormData(prev => ({ ...prev, target_id: '' }));
                      setResult(null);
                    }} disabled={loading}>
                      <option value="">All constellations</option>
                      {constellations.map(name => <option key={name} value={name}>{name}</option>)}
                    </select>
                    {!constellations.length && <p className="text-muted text-xs mt-1">This catalog has no constellation field (for example, Solar System bodies).</p>}
                  </div>

                  <div className="form-group">
                    <label htmlFor="target_search">Find a target</label>
                    <input
                      type="search"
                      id="target_search"
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      placeholder="Search name or catalog ID"
                      disabled={loading}
                    />
                  </div>

                  <div className="form-group">
                    <label htmlFor="target_id">Select Target</label>
                    <select
                      id="target_id"
                      name="target_id"
                      value={formData.target_id}
                      onChange={handleChange}
                      className={errors.target_id ? 'border-error' : ''}
                      required
                      disabled={loading}
                    >
                      <option value="">— Choose a target —</option>
                      {visibleTargets.map(target => (
                        <option key={target.id} value={target.id}>
                          {target.name} ({target.category}, {distanceRankLabels[target.distance_rank]})
                          {target.apparent_magnitude !== null && `, mag ${target.apparent_magnitude}`}
                        </option>
                      ))}
                    </select>
                    <p className="text-muted text-xs mt-1">{visibleTargets.length} matching target{visibleTargets.length === 1 ? '' : 's'}.</p>
                    {errors.target_id && <p className="text-error text-sm mt-1">{errors.target_id}</p>}
                  </div>

                  <div className="form-group">
                    <label>Your Guess (optional)</label>
                    <div className="space-y-2">
                      <input
                        type="text"
                        placeholder="Filter guess"
                        value={formData.user_guess.filter}
                        onChange={handleChange}
                        name="user_guess.filter"
                        className="text-sm"
                      />
                      <input
                        type="text"
                        placeholder="Exposure guess (e.g., 180s, 1/60s)"
                        value={formData.user_guess.exposure}
                        onChange={handleChange}
                        name="user_guess.exposure"
                        className="text-sm"
                      />
                      <input
                        type="text"
                        placeholder="Aperture guess (e.g., 200mm)"
                        value={formData.user_guess.aperture}
                        onChange={handleChange}
                        name="user_guess.aperture"
                        className="text-sm"
                      />
                    </div>
                  </div>

                  <button
                    type="submit"
                    className="btn btn-primary w-full py-3 mt-2"
                    disabled={loading || !formData.target_id}
                  >
                    {loading ? 'Computing...' : 'Run Model'}
                  </button>
                </form>

                {submitted && result && (
                  <div className="mt-6 p-4 bg-bg-elevated rounded-lg border border-border space-y-2">
                    <h4 className="font-semibold">Recommendation</h4>
                    <div className="grid grid-cols-2 gap-2 text-sm">
                      <div><span className="text-secondary">Filter:</span> <span className="font-mono font-bold text-accent-amber">{result.filter}</span></div>
                      <div><span className="text-secondary">Exposure:</span> <span className="font-mono font-bold text-accent-amber">{result.exposure}</span></div>
                      <div><span className="text-secondary">Aperture:</span> <span className="font-mono font-bold text-accent-amber">{result.aperture}</span></div>
                    </div>
                    {Object.keys(formData.user_guess).some(k => formData.user_guess[k]) && (
                      <div className="text-sm mt-2">
                        <span className="text-secondary">Your score:</span> 
                        <span className="font-bold text-accent-teal ml-2">{result.explanation?.score}/3</span>
                      </div>
                    )}
                  </div>
                )}
              </div>
            </div>

            {/* Explanation Panel */}
            <div className="mission-learning-panel space-y-6">
              <div className="card">
                <h2 className="font-bold text-xl mb-4">Mission Briefing</h2>
                <p className="text-secondary">
                  Astrolabs's telescope academy needs to recommend optimal settings for any target.
                  No dataset of "correct settings" exists — we use expert rules of thumb encoded as a decision tree.
                  The tree splits on Bortle sky darkness and target distance rank.
                </p>
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">How the Algorithm Works</h2>
                <div className="space-y-4 text-secondary">
                  <p>
                    <strong>Explicit Decision Tree (Rule-Based):</strong> This is NOT a learned model. The splits and leaves
                    are handcrafted from astrophotography best practices.
                  </p>
                  <pre className="bg-bg-deep p-3 rounded text-sm overflow-auto font-mono">
{`IF Bortle ≥ 6 (light polluted):
    IF Distance Rank ≥ 2 (deep sky):
        → Light-pollution filter, 180s, 200mm
    ELSE (solar system):
        → Colour planetary filter, 1/60s, 100mm
ELSE (dark sky):
    IF Distance Rank ≥ 2:
        IF Distance Rank ≥ 3 (beyond galaxy):
            → No filter, 600s, 300mm
        ELSE (inside galaxy):
            → H-alpha filter, 240s, 200mm
    ELSE (solar system):
        → Neutral density filter, 1/125s, 80mm`}
                  </pre>
                  <p>
                    <strong>Why These Splits:</strong> Light pollution washes out faint objects → need filters and shorter exposures.
                    Distance rank determines if target is bright/small (planets) or faint/large (nebulae, galaxies).
                  </p>
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
                    <div className="p-3 bg-bg-elevated rounded border border-border">
                      <h4 className="font-semibold mb-2">Decision Path</h4>
                      <ol className="space-y-1">
                        {result.explanation.path_taken.map((step, i) => (
                          <li key={i} className="text-sm">{step}</li>
                        ))}
                      </ol>
                    </div>

                    <div className="p-3 bg-bg-elevated rounded border border-border">
                      <h4 className="font-semibold mb-2">Target Details</h4>
                      <div className="grid grid-cols-2 gap-2 text-sm">
                        <div><span className="text-secondary">Name:</span> <span className="font-mono">{result.explanation.target?.name}</span></div>
                        <div><span className="text-secondary">Category:</span> <span>{result.explanation.target?.category}</span></div>
                        <div><span className="text-secondary">Distance Rank:</span> <span>{distanceRankLabels[result.explanation.target?.distance_rank]}</span></div>
                        <div><span className="text-secondary">Magnitude:</span> <span className="font-mono">{result.explanation.target?.apparent_magnitude}</span></div>
                        <div><span className="text-secondary">Bortle Scale:</span> <span className="font-mono">{result.explanation.bortle_scale}</span></div>
                      </div>
                    </div>

                    <div className="p-3 bg-bg-elevated rounded border border-border">
                      <h4 className="font-semibold mb-2">Why Each Split Matters</h4>
                      <dl className="space-y-2 text-sm">
                        <div>
                          <dt className="font-medium">Bortle Scale</dt>
                          <dd className="text-secondary ml-4">{result.explanation.why_splits?.['Bortle scale']}</dd>
                        </div>
                        <div>
                          <dt className="font-medium">Distance Rank</dt>
                          <dd className="text-secondary ml-4">{result.explanation.why_splits?.['Distance rank']}</dd>
                        </div>
                      </dl>
                    </div>

                    {Object.keys(formData.user_guess).some(k => formData.user_guess[k]) && (
                      <div className="p-3 bg-bg-elevated rounded border border-border">
                        <h4 className="font-semibold mb-2">Your Guess vs Recommendation</h4>
                        <div className="space-y-1 text-sm">
                          <div className={`flex justify-between ${result.explanation.comparison?.filter?.match ? 'text-accent-teal' : 'text-accent-coral'}`}>
                            <span>Filter:</span>
                            <span>Guess: {formData.user_guess.filter} → Actual: {result.filter} {result.explanation.comparison?.filter?.match ? '✓' : '✗'}</span>
                          </div>
                          <div className={`flex justify-between ${result.explanation.comparison?.exposure?.match ? 'text-accent-teal' : 'text-accent-coral'}`}>
                            <span>Exposure:</span>
                            <span>Guess: {formData.user_guess.exposure} → Actual: {result.exposure} {result.explanation.comparison?.exposure?.match ? '✓' : '✗'}</span>
                          </div>
                          <div className={`flex justify-between ${result.explanation.comparison?.aperture?.match ? 'text-accent-teal' : 'text-accent-coral'}`}>
                            <span>Aperture:</span>
                            <span>Guess: {formData.user_guess.aperture} → Actual: {result.aperture} {result.explanation.comparison?.aperture?.match ? '✓' : '✗'}</span>
                          </div>
                          <div className="font-bold text-accent-amber mt-2">Total: {result.explanation.score}/3 points</div>
                        </div>
                      </div>
                    )}
                  </div>
                ) : (
                  <p className="text-secondary">Select inputs and press <strong>Run Model</strong> to see the decision path and comparison.</p>
                )}
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">Try This</h2>
                <ul className="space-y-2 text-secondary">
                  <li>Bortle 8 (city), target Andromeda Galaxy (rank 3) — light pollution filter</li>
                  <li>Bortle 3 (rural), target Jupiter (rank 1) — neutral density filter</li>
                  <li>Bortle 5 (suburban), target Orion Nebula (rank 2) — H-alpha filter</li>
                  <li>Switch to Solar System dataset — try Moon (rank 0) vs Neptune (rank 1)</li>
                </ul>
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">Real Dataset</h2>
                <p className="text-secondary text-sm mb-4">
                  Source: {datasetOptions[datasetKey]?.publisher} | {dataset?.license} | Targets: {dataset?.row_count?.toLocaleString()}
                </p>
                <p className="text-warning text-sm mb-4">
                  ⚠️ {dataset?.honesty_note || 'No public dataset of "correct telescope settings" exists. Rules are expert rules of thumb.'}
                </p>
                <div className="table-container">
                  <table>
                    <thead>
                      <tr>
                        <th>Name</th>
                        <th>Catalog</th>
                        <th>Category</th>
                        <th>Distance Rank</th>
                        <th>Magnitude</th>
                        <th>Constellation</th>
                      </tr>
                    </thead>
                    <tbody>
                      {targets.slice(0, 10).map((t, i) => (
                        <tr key={i}>
                          <td className="font-mono">{t.name}</td>
                          <td>{t.catalog_id}</td>
                          <td><span className="badge badge-primary">{t.category}</span></td>
                          <td>{distanceRankLabels[t.distance_rank]}</td>
                          <td className="font-mono">{t.apparent_magnitude}</td>
                          <td>{t.constellation}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}
