import React, { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { api } from '../utils/api';

const missionLabels = {
  orbit: 'Orbit Calculator (Linear Regression)',
  forum: 'Forum Sorter (Naive Bayes)',
  exoplanets: 'Exoplanet Twin Finder (KNN)',
  transit: 'Transit Hunter (SVM)',
  telescope: 'Telescope Setup Wizard (Decision Tree)',
  galaxy: 'Galaxy Classifier (Neural Network)',
};

const honestyNotes = {
  orbit: 'No real catalog provides exoplanet "atmospheric density" or true surface temperature. We use bulk density (g/cm³) and equilibrium temperature (K) as real substitutes. Planet type for KNN is derived from MASS only (not used as feature).',
  forum: 'Stack Exchange content is CC BY-SA. arXiv metadata is CC0. Note: Stack Exchange uses everyday language while arXiv uses scientific language.',
  exoplanets: 'Planet type derived from MASS only (not a feature): <10 M⊕ = Terrestrial/Super-Earth, 10-50 = Neptune-like, >50 = Gas giant. Features: temperature, radius, density.',
  transit: 'CONFIRMED = "Exoplanet Transit Detected", FALSE POSITIVE = "Standard Star". Kepler KOI and TESS TOI use same features (depth, SNR) for fair comparison.',
  telescope: 'No public dataset of "correct telescope settings" exists. The decision tree rules are expert astrophotography rules of thumb, NOT learned from data.',
  galaxy: 'Network sees 16×16 grayscale (256 inputs) → 24 tanh hidden → 3 softmax outputs. Tiny network on tiny images makes mistakes; see confusion matrix. SDSS labels: Elliptical, Spiral, Edge-on disk.',
};

export default function Datasets() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchParams, setSearchParams] = useSearchParams();
  const [selectedMission, setSelectedMission] = useState(null);
  const [selectedDataset, setSelectedDataset] = useState(null);
  const [selectedDatasetInfo, setSelectedDatasetInfo] = useState(null);
  const [preview, setPreview] = useState(null);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      const res = await api.getDatasets();
      setData(res);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  };

  const handleDatasetClick = async (missionKey, datasetKey) => {
    setSelectedMission(missionKey);
    setSelectedDataset(datasetKey);
    setSearchParams({ mission: missionKey, dataset: datasetKey });
    try {
      const res = await api.getDataset(missionKey, datasetKey);
      setPreview(res.preview);
      setSelectedDatasetInfo(res.dataset);
    } catch (e) {
      console.error('Failed to load preview:', e);
    }
  };

  const closePreview = () => {
    setSelectedMission(null);
    setSelectedDataset(null);
    setSelectedDatasetInfo(null);
    setPreview(null);
    setSearchParams({});
  };

  if (loading) return <div className="section container"><div className="text-center py-12">Loading datasets...</div></div>;
  if (error) return <div className="section container"><div className="card text-center">Error: {error}</div></div>;

  return (
    <div className="min-h-screen">
      <section className="section">
        <div className="container">
          <header className="mb-10">
            <h1 className="text-4xl font-bold mb-4 text-gradient">Datasets & Sources</h1>
            <p className="text-secondary max-w-3xl">
              All astronomical data used in Astrolabs missions comes from real, authoritative sources.
              Each dataset is documented with its publisher, license, retrieval date, and citation.
              <strong className="text-primary">We never generate or fake astronomical data.</strong>
            </p>
          </header>

          {data?.datasets_by_mission?.map(missionGroup => (
            <section key={missionGroup.mission} className="mb-16" aria-labelledby={`mission-${missionGroup.mission}`}>
              <h2 id={`mission-${missionGroup.mission}`} className="text-2xl font-bold mb-6 flex items-center gap-3">
                <span className="text-accent-amber">▸</span>
                {missionLabels[missionGroup.mission] || missionGroup.mission}
              </h2>

              <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                {missionGroup.datasets.map(dataset => (
                  <article
                    key={dataset.key}
                    className={`card ${selectedMission === missionGroup.mission && selectedDataset === dataset.key ? 'ring-2 ring-accent-amber' : ''}`}
                    onClick={() => handleDatasetClick(missionGroup.mission, dataset.key)}
                    style={{ cursor: 'pointer' }}
                    tabIndex={0}
                    onKeyDown={e => e.key === 'Enter' && handleDatasetClick(missionGroup.mission, dataset.key)}
                    role="button"
                    aria-pressed={selectedMission === missionGroup.mission && selectedDataset === dataset.key}
                  >
                    <div className="flex items-start justify-between gap-4 mb-3">
                      <div className="flex-1">
                        <h3 className="font-bold text-lg">{dataset.title}</h3>
                        <p className="text-sm text-secondary mt-1">{dataset.description}</p>
                      </div>
                      {dataset.is_default && <span className="badge badge-primary flex-shrink-0">Default</span>}
                    </div>
                    
                    <dl className="space-y-2 text-sm">
                      <div className="flex justify-between"><dt className="text-secondary">Rows</dt><dd className="font-mono">{dataset.row_count?.toLocaleString()}</dd></div>
                      <div className="flex justify-between"><dt className="text-secondary">License</dt><dd>{dataset.license}</dd></div>
                      <div className="flex justify-between"><dt className="text-secondary">Retrieved</dt><dd className="font-mono">{dataset.retrieved_at ? new Date(dataset.retrieved_at).toLocaleDateString() : 'N/A'}</dd></div>
                    </dl>
                  </article>
                ))}
              </div>

              {selectedMission === missionGroup.mission && selectedDataset && (
                <div className="mt-6 animate-fade-in" role="region" aria-label="Dataset preview">
                  <div className="card">
                    <div className="flex items-center justify-between mb-4">
                      <h3 className="font-bold">Dataset Details & Preview</h3>
                      <button onClick={closePreview} className="btn btn-ghost text-sm" aria-label="Close preview">Close ✕</button>
                    </div>
                    
                    <div className="space-y-4 mb-6">
                      <div className="grid md:grid-cols-2 gap-4">
                        <div><dt className="text-secondary text-sm">Publisher</dt><dd className="font-medium">{selectedDatasetInfo?.publisher || 'N/A'}</dd></div>
                        <div><dt className="text-secondary text-sm">License</dt><dd>{selectedDatasetInfo?.license || 'N/A'}</dd></div>
                        <div><dt className="text-secondary text-sm">Retrieved</dt><dd className="font-mono">{selectedDatasetInfo?.retrieved_at ? new Date(selectedDatasetInfo.retrieved_at).toLocaleDateString() : 'N/A'}</dd></div>
                        <div><dt className="text-secondary text-sm">Row Count</dt><dd className="font-mono">{selectedDatasetInfo?.row_count?.toLocaleString() || 'N/A'}</dd></div>
                      </div>
                      
                      <div>
                        <dt className="text-secondary text-sm mb-1">Citation</dt>
                        <dd className="text-sm bg-bg-deep p-3 rounded font-mono whitespace-pre-wrap">{selectedDatasetInfo?.citation_text || 'No citation available.'}</dd>
                      </div>

                      <div>
                        <dt className="text-secondary text-sm mb-1">Honesty Note</dt>
                        <dd className="text-sm bg-bg-deep p-3 rounded border-l-4 border-accent-amber">{honestyNotes[missionGroup.mission]}</dd>
                      </div>

                      {selectedDatasetInfo?.feature_schema && (
                        <div>
                          <dt className="text-secondary text-sm mb-1">Feature Schema</dt>
                          <dd className="text-sm bg-bg-deep p-3 rounded font-mono">{JSON.stringify(selectedDatasetInfo.feature_schema, null, 2)}</dd>
                        </div>
                      )}

                      {selectedDatasetInfo?.label_names && (
                        <div>
                          <dt className="text-secondary text-sm mb-1">Label Names</dt>
                          <dd className="text-sm bg-bg-deep p-3 rounded font-mono">{JSON.stringify(selectedDatasetInfo.label_names, null, 2)}</dd>
                        </div>
                      )}
                    </div>

                    {preview && preview.length > 0 && (
                      <div>
                        <h4 className="font-semibold mb-3">Sample Rows (first 10)</h4>
                        <div className="table-container">
                          <table>
                            <thead>
                              <tr>
                                {Object.keys(preview[0]).map(key => <th key={key}>{key}</th>)}
                              </tr>
                            </thead>
                            <tbody>
                              {preview.map((row, i) => (
                                <tr key={i}>
                                  {Object.values(row).map((val, j) => (
                                    <td key={j}>
                                      {val !== null ? String(val) : <em className="text-muted">null</em>}
                                    </td>
                                  ))}
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              )}
            </section>
          ))}

          <section aria-labelledby="sources-title" className="mt-16">
            <h2 id="sources-title" className="text-2xl font-bold mb-6">All Data Sources</h2>
            <div className="space-y-4">
              {data?.sources?.map(source => (
                <details key={source.key} className="card group">
                  <summary className="font-semibold cursor-pointer list-none flex items-center justify-between p-2">
                    <span>{source.title}</span>
                    <span className="text-accent-amber transition-transform group-open:rotate-180">▼</span>
                  </summary>
                  <div className="px-2 pb-4 space-y-3 text-sm border-t border-border mt-2">
                    <div className="grid md:grid-cols-2 gap-2">
                      <div><dt className="text-secondary">Publisher</dt><dd>{source.publisher}</dd></div>
                      <div><dt className="text-secondary">License</dt><dd>{source.license}</dd></div>
                      <div><dt className="text-secondary">Rows</dt><dd className="font-mono">{source.row_count?.toLocaleString()}</dd></div>
                      <div><dt className="text-secondary">Retrieved</dt><dd className="font-mono">{source.retrieved_at ? new Date(source.retrieved_at).toLocaleDateString() : 'N/A'}</dd></div>
                    </div>
                    <div>
                      <dt className="text-secondary">URL</dt>
                      <dd><a href={source.url} target="_blank" rel="noopener noreferrer" className="text-accent-amber hover:underline break-all">{source.url}</a></dd>
                    </div>
                    <div>
                      <dt className="text-secondary">Citation</dt>
                      <dd className="font-mono bg-bg-deep p-2 rounded">{source.citation_text}</dd>
                    </div>
                  </div>
                </details>
              ))}
            </div>
          </section>
        </div>
      </section>
    </div>
  );
}
