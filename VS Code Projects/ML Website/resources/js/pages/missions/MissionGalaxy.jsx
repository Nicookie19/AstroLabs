import React, { useEffect, useState, useRef } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { useExplorer } from '../../context/ExplorerContext';
import { api } from '../../utils/api';
import { useToast } from '../../context/ToastContext';

const datasetOptions = {
  galaxy10_deca: { title: 'Galaxy10 DECaLS', publisher: 'astroNN / UToronto' },
  galaxy10_sdss: { title: 'Galaxy10 SDSS', publisher: 'astroNN / UToronto' },
};

const classLabels = ['Elliptical', 'Spiral', 'Irregular'];
const classColors = {
  Elliptical: 'badge-primary',
  Spiral: 'badge-success',
  Irregular: 'badge-info',
  'Edge-on disk': 'badge-warning',
};

const levelExplanations = {
  Beginner: {
    formula: 'The neural network sees a 16×16 grayscale image (256 pixels). It has 24 hidden neurons with tanh activation, then 3 outputs with softmax. It learns patterns like "smooth round = elliptical", "spiral arms = spiral".',
    workedExample: 'Input: 256 pixel values (0-1). Hidden layer: 24 neurons compute weighted sums + tanh. Output: 3 probabilities summing to 1. Highest wins. Trained on 720 galaxies, tested on 180.',
  },
  Intermediate: {
    formula: 'Architecture: 256→24→3. Forward: h = tanh(W₁x + b₁), y = softmax(W₂h + b₂). Loss: cross-entropy. Backprop: ∂L/∂W₂ = (y - t)hᵀ, ∂L/∂W₁ = (W₂ᵀ(y - t) ⊙ (1-h²))xᵀ. SGD with lr=0.01, 50 epochs.',
    workedExample: 'Galaxy image → 16×16 grayscale → flatten to 256. Hidden activations show which features activate. Output probabilities: Elliptical 0.72, Spiral 0.18, Irregular 0.10. Pixel importance heatmap highlights spiral arms or smooth core.',
  },
  Advanced: {
    formula: 'Xavier init: W ∼ U(-√(6/(n_in+n_out)), √(6/(n_in+n_out))). SGD with shuffling each epoch. Batch size 32. Pixel importance: Iᵢ = Σₕ |W₁ᵢₕ| · |hₕ| · |W₂ₕc| for winning class c. Confusion matrix shows per-class accuracy on test set.',
    workedExample: 'Inspect the measured test accuracy and confusion matrix below to see which classes the model confuses. Tiny 16×16 inputs lose spiral-arm detail. Heatmap: Iᵢ = Σₕ |W₁ᵢₕ|·|tanh(ΣW₁x+b₁)ₕ|·|W₂ₕ,argmax|.',
  },
};

export default function MissionGalaxy() {
  const { explorer, loading: explorerLoading } = useExplorer();
  const { addToast } = useToast();
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();
  
  const [datasetKey, setDatasetKey] = useState(searchParams.get('dataset') || '');
  const [dataset, setDataset] = useState(null);
  const [gallery, setGallery] = useState([]);
  const [selectedImage, setSelectedImage] = useState(null);
  const [formData, setFormData] = useState({
    user_guess_label: '',
    image_id: null,
    pixels: null,
  });
  const [errors, setErrors] = useState({});
  const [result, setResult] = useState(null);
  const [runId, setRunId] = useState(null);
  const [revealed, setRevealed] = useState(false);
  const [revealResult, setRevealResult] = useState(null);
  const [metrics, setMetrics] = useState(null);
  const [loading, setLoading] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [galleryError, setGalleryError] = useState('');
  const [galleryLoading, setGalleryLoading] = useState(true);
  const requestVersion = useRef(0);
  const canvasRef = useRef(null);

  useEffect(() => {
    const heatmap = result?.explanation?.pixel_importance_heatmap;
    const context = canvasRef.current?.getContext('2d');
    if (!context || !Array.isArray(heatmap)) return;
    heatmap.forEach((row, y) => row.forEach((value, x) => {
      const intensity = Math.max(0, Math.min(1, Number(value) || 0));
      context.fillStyle = `rgb(${Math.round(255 * intensity)}, ${Math.round(191 * intensity)}, ${Math.round(50 * intensity)})`;
      context.fillRect(x, y, 1, 1);
    }));
  }, [result]);

  useEffect(() => {
    if (!explorerLoading) {
      loadDataset();
    }
    return () => { requestVersion.current += 1; };
  }, [datasetKey, explorerLoading]);

  const loadDataset = async () => {
    const version = ++requestVersion.current;
    setGalleryLoading(true);
    setDataset(null);
    setGalleryError('');
    setGallery([]);
    setMetrics(null);
    try {
      const galleryRes = await api.getGalaxyGallery({ ...(datasetKey ? { dataset: datasetKey } : {}), count: 12 });
      if (version !== requestVersion.current) return;
      if (!datasetKey && galleryRes.dataset?.key) {
        setDatasetKey(galleryRes.dataset.key);
        return;
      }
      setDataset(galleryRes.dataset);
      const images = Array.isArray(galleryRes.images) ? galleryRes.images : [];
      setGallery(images);
      if (!images.length) {
        setGalleryError('Images are not ready for this dataset. Finish the download, prepare and import the images, then train the model. DECaLS and SDSS can be set up independently.');
        return;
      }

      try {
        const metricsRes = await api.getGalaxyMetrics({ dataset: datasetKey });
        if (version !== requestVersion.current) return;
        setMetrics(metricsRes.metrics);
      } catch (metricsError) {
        if (version !== requestVersion.current) return;
        setGalleryError('This Galaxy10 dataset has not been trained yet. Add the prepared images, then train the neural network to unlock predictions and metrics.');
      }
    } catch (e) {
      if (version !== requestVersion.current) return;
      console.error('Failed to load dataset:', e);
      setGalleryError(e.message || 'The galaxy gallery could not be loaded.');
    } finally {
      if (version === requestVersion.current) setGalleryLoading(false);
    }
  };

  const validateForm = () => {
    const newErrors = {};
    if (!formData.user_guess_label) newErrors.user_guess_label = 'You must guess the galaxy type first';
    if (!formData.image_id && !formData.pixels) newErrors.image_id = 'Select a galaxy from the gallery';
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSelectImage = (image) => {
    setSelectedImage(image);
    setFormData(prev => ({ ...prev, image_id: image.id, user_guess_label: '' }));
    setResult(null);
    setRunId(null);
    setRevealed(false);
    setRevealResult(null);
    setErrors({});
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
      const res = await api.classifyGalaxy({ ...formData, dataset: datasetKey });
      setResult(res.classification);
      setRunId(res.run_id);
      addToast('Neural network ran! Now reveal the true label.', 'success');
    } catch (e) {
      addToast(e.message || 'Classification failed', 'error');
    } finally {
      setLoading(false);
    }
  };

  const handleReveal = async (e) => {
    e.preventDefault();
    if (!formData.user_guess_label || !formData.image_id) return;
    
    setLoading(true);
    try {
      const res = await api.revealGalaxy({
        image_id: formData.image_id,
        user_guess_label: formData.user_guess_label,
        model_guess_label: result.prediction,
        dataset: datasetKey,
        run_id: runId,
      });
      setRevealResult(res);
      setRevealed(true);
      addToast(res.user_correct ? 'Correct! 🎉' : res.model_correct ? 'Model won this round' : 'Both wrong', res.user_correct ? 'success' : 'info');
    } catch (e) {
      addToast(e.message || 'Reveal failed', 'error');
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
    if (errors[name]) setErrors(prev => ({ ...prev, [name]: '' }));
  };

  const handleDatasetChange = (key) => {
    requestVersion.current += 1;
    setDatasetKey(key);
    setSearchParams({ dataset: key });
    setResult(null);
    setRunId(null);
    setRevealed(false);
    setRevealResult(null);
    setFormData({ user_guess_label: '', image_id: null, pixels: null });
    setSelectedImage(null);
  };

  const currentLevel = explorer?.level || 'Beginner';
  const levelContent = levelExplanations[currentLevel] || levelExplanations.Beginner;

  if (explorerLoading) {
    return <div className="section container text-center"><div className="orbit-animation" style={{margin: '0 auto'}} /></div>;
  }

  const datasetLabels = Array.isArray(dataset?.label_names) && dataset.label_names.length
    ? dataset.label_names.filter(label => typeof label === 'string')
    : datasetKey === 'galaxy10_sdss' ? ['Elliptical', 'Spiral', 'Edge-on disk'] : classLabels;

  return (
    <div className="min-h-screen">
      <section className="section">
        <div className="container max-w-5xl">
          <h1 className="text-3xl font-bold mb-3">Galaxy Classifier</h1>
          <p className="text-secondary mb-6">Explore real galaxy images, make your guess, and compare it with a neural network.</p>
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
                  disabled={loading}
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
                <strong>Images:</strong> {dataset.row_count?.toLocaleString()} | 
                <strong>License:</strong> {dataset.license} |
                <strong>Classes:</strong> {datasetLabels.join(', ')}
              </div>
            )}
          </div>

          <div className="mission-workspace">
            {/* Input Panel */}
            <div className="mission-input-panel">
              <div className="card sticky top-24 h-fit">
                <h2 className="font-bold text-lg mb-4">Your Turn</h2>
                <p className="text-secondary text-sm mb-4">
                  1. Pick a galaxy from the gallery (true label hidden).<br/>
                  2. Enter your guess (required).<br/>
                  3. Press <strong>Run Neural Network</strong>.<br/>
                  4. Press <strong>Reveal True Label</strong> for points!
                </p>
                
                {galleryError && (
                  <div className="p-4 mb-4 rounded-lg border border-accent-amber bg-accent-amber/10 text-sm">
                    <p className="font-semibold text-accent-amber">Galaxy classifier setup required</p>
                    <p className="text-secondary mt-1">{galleryError}</p>
                    <button type="button" className="btn btn-secondary mt-3" onClick={loadDataset} disabled={galleryLoading}>Check again</button>
                    <code className="block mt-3 p-2 rounded bg-bg-deep text-xs text-primary overflow-x-auto">python3 scripts/prepare_galaxy10.py && php artisan data:import-galaxies && php artisan ml:train-all --mission=galaxy</code>
                  </div>
                )}

                <form onSubmit={handleSubmit} noValidate>
                  <div className="form-group">
                    <label>Your Guess <span className="text-error">*</span></label>
                    <div className="flex flex-wrap gap-2">
                      {datasetLabels.map(label => (
                        <button
                          key={label}
                          type="button"
                          onClick={() => setFormData(prev => ({ ...prev, user_guess_label: label }))}
                          disabled={loading || !!result}
                          className={`flex-1 min-w-[80px] py-2 px-3 rounded-lg text-sm transition-all ${
                            formData.user_guess_label === label
                              ? 'bg-accent-amber text-bg-deep font-semibold'
                              : 'bg-bg-elevated border border-border text-secondary hover:border-accent-amber'
                          }`}
                        >
                          {label}
                        </button>
                      ))}
                    </div>
                    {errors.user_guess_label && <p className="text-error text-sm mt-1">{errors.user_guess_label}</p>}
                  </div>

                  <div className="form-group">
                    <label>Select Galaxy from Gallery</label>
                    {galleryLoading ? <p role="status" className="text-secondary">Loading galaxy images…</p> : gallery.length ? <div className="grid grid-cols-2 gap-2">
                      {gallery.map(img => (
                        <button
                          key={img.id}
                          type="button"
                          onClick={() => handleSelectImage(img)}
                          disabled={loading}
                          aria-pressed={formData.image_id === img.id}
                          className={`relative aspect-square rounded-lg overflow-hidden border-2 transition-all ${
                            formData.image_id === img.id
                              ? 'border-accent-amber shadow-glow'
                              : 'border-border hover:border-accent-amber'
                          }`}
                        >
                          <img
                            src={img.image_url}
                            alt={`Galaxy ${img.id}`}
                            className="w-full h-full object-cover"
                          />
                          {formData.image_id === img.id && (
                            <div className="absolute inset-0 bg-accent-amber/20 flex items-center justify-center">
                              <span className="text-accent-amber font-bold">SELECTED</span>
                            </div>
                          )}
                        </button>
                      ))}
                    </div> : (
                      <div className="rounded-lg border border-dashed border-border p-4 text-sm text-secondary">
                        No images available yet. Once this dataset is prepared, galaxy choices will appear here.
                      </div>
                    )}
                    {errors.image_id && <p className="text-error text-sm mt-1">{errors.image_id}</p>}
                  </div>

                  <button
                    type="submit"
                    className="btn btn-primary w-full py-3 mt-2"
                    disabled={loading || galleryLoading || !!galleryError || !!result || !formData.user_guess_label || !formData.image_id}
                  >
                    {loading ? 'Running Network...' : 'Run Neural Network'}
                  </button>
                </form>

                {submitted && result && !revealed && (
                  <div className="mt-6 p-4 bg-bg-elevated rounded-lg border border-border space-y-2">
                    <h4 className="font-semibold">Network Prediction</h4>
                    <div className="flex flex-wrap gap-2">
                      {Object.entries(result.probabilities).map(([label, prob]) => (
                        <span key={label} className={`badge ${classColors[label] || 'badge-primary'}`}>
                          {label}: {prob}%
                        </span>
                      ))}
                    </div>
                    <div className="flex items-center gap-3 mt-2">
                      <span className="text-secondary">Your guess:</span>
                      <span className={`font-mono ${formData.user_guess_label === result.prediction ? 'text-accent-teal' : 'text-accent-coral'}`}>
                        {formData.user_guess_label} {formData.user_guess_label === result.prediction ? '✓' : '✗'}
                      </span>
                    </div>
                    <button
                      onClick={handleReveal}
                      className="btn btn-success w-full mt-2"
                      disabled={loading}
                    >
                      Reveal True Label
                    </button>
                  </div>
                )}

                {revealed && revealResult && (
                  <div className="mt-6 p-4 bg-bg-elevated rounded-lg border border-border space-y-2">
                    <h4 className="font-semibold">Reveal Result</h4>
                    <div className="grid grid-cols-3 gap-2 text-sm">
                      <div className={`p-2 rounded ${revealResult.user_correct ? 'bg-accent-teal/20 border border-accent-teal' : 'bg-accent-coral/20 border border-accent-coral'}`}>
                        <div className="text-secondary">Your Guess</div>
                        <div className="font-bold">{revealResult.user_guess}</div>
                        <div className={revealResult.user_correct ? 'text-accent-teal' : 'text-accent-coral'}>
                          {revealResult.user_correct ? '✓ Correct' : '✗ Wrong'}
                        </div>
                      </div>
                      <div className={`p-2 rounded ${revealResult.model_correct ? 'bg-accent-teal/20 border border-accent-teal' : 'bg-accent-coral/20 border border-accent-coral'}`}>
                        <div className="text-secondary">Model Guess</div>
                        <div className="font-bold">{revealResult.model_guess}</div>
                        <div className={revealResult.model_correct ? 'text-accent-teal' : 'text-accent-coral'}>
                          {revealResult.model_correct ? '✓ Correct' : '✗ Wrong'}
                        </div>
                      </div>
                      <div className={`p-2 rounded bg-accent-amber/20 border border-accent-amber`}>
                        <div className="text-secondary">True Label</div>
                        <div className="font-bold text-accent-amber">{revealResult.true_label}</div>
                      </div>
                    </div>
                    <div className="text-accent-amber font-bold">Points earned: {revealResult.points}</div>
                    <button
                      onClick={() => {
                        setFormData(prev => ({ ...prev, user_guess_label: '', image_id: null }));
                        setSelectedImage(null);
                        setResult(null);
                        setRevealed(false);
                        setRevealResult(null);
                      }}
                      className="btn btn-secondary w-full mt-2"
                    >
                      Try Another Galaxy
                    </button>
                  </div>
                )}
              </div>
            </div>

            {/* Explanation Panel */}
            <div className="mission-learning-panel space-y-6">
              <div className="card">
                <h2 className="font-bold text-xl mb-4">Mission Briefing</h2>
                <p className="text-secondary">
                  Classify galaxy morphology from real survey images. The neural network sees only 16×16 grayscale pixels —
                  a tiny version of the original. Can it distinguish {datasetLabels.join(', ')} galaxies?
                </p>
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">How the Algorithm Works</h2>
                <div className="space-y-4 text-secondary">
                  <p>
                    <strong>Feed-Forward Neural Network:</strong> 256 inputs (16×16 grayscale) → 24 tanh hidden neurons → 3 softmax outputs.
                    Trained with backpropagation + SGD from scratch in PHP. No TensorFlow/PyTorch at runtime.
                  </p>
                  <p>
                    <strong>Training:</strong> 80/20 split, fixed seed. Xavier initialization. Cross-entropy loss.
                    50 epochs, lr=0.01, with one weight update per image. Weights saved to database after training.
                  </p>
                  <p>
                    <strong>Inference:</strong> Forward pass only. Pixel values (0-1) → hidden activations (tanh) → output logits → softmax probabilities.
                    Pixel importance heatmap: Iᵢ = Σₕ |W₁ᵢₕ| · |hₕ| · |W₂ₕ,winning_class|.
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
                    <div className="grid md:grid-cols-2 gap-4">
                      <div className="p-3 bg-bg-elevated rounded border border-border">
                        <h4 className="font-semibold mb-2">Architecture</h4>
                        <div className="space-y-1 text-sm font-mono">
                          <div>Input: {result.explanation.architecture?.input} (16×16)</div>
                          <div>Hidden: {result.explanation.architecture?.hidden} tanh</div>
                          <div>Output: {result.explanation.architecture?.output} softmax</div>
                        </div>
                      </div>
                      <div className="p-3 bg-bg-elevated rounded border border-border">
                        <h4 className="font-semibold mb-2">Probabilities</h4>
                        <div className="space-y-1">
                          {Object.entries(result.probabilities).map(([label, prob]) => (
                            <div key={label} className="flex justify-between">
                              <span className={`badge ${classColors[label] || 'badge-primary'}`}>{label}</span>
                              <span>{prob}%</span>
                            </div>
                          ))}
                        </div>
                      </div>
                    </div>

                    <div className="p-3 bg-bg-elevated rounded border border-border">
                      <h4 className="font-semibold mb-2">Pixel Importance Heatmap (Winning Class)</h4>
                      <div className="aspect-square bg-bg-deep rounded border border-border relative overflow-hidden">
                        {result.explanation.pixel_importance_heatmap && (
                          <>
                            <canvas
                              ref={canvasRef}
                              width={16}
                              height={16}
                              style={{width: '100%', height: '100%', imageRendering: 'pixelated'}}
                            />
                            <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                              <span className="text-muted text-xs">16×16 heatmap</span>
                            </div>
                          </>
                        )}
                      </div>
                      <p className="text-muted text-xs mt-1">Brighter = more important for the network's decision.</p>
                    </div>

                    <div className="p-3 bg-bg-elevated rounded border border-border">
                      <h4 className="font-semibold mb-2">Hidden Activations (First 8)</h4>
                      <div className="flex flex-wrap gap-1">
                        {result.explanation.hidden_activations?.slice(0, 8).map((h, i) => (
                          <span key={i} className={`badge ${h > 0 ? 'badge-success' : 'badge-danger'}`}>
                            h{i}: {h.toFixed(3)}
                          </span>
                        ))}
                      </div>
                    </div>

                    <div className="p-3 bg-bg-elevated rounded border border-border">
                      <h4 className="font-semibold mb-2">Note</h4>
                      <p className="text-secondary text-sm">{result.explanation.note}</p>
                    </div>
                  </div>
                ) : (
                  <p className="text-secondary">Select a galaxy, guess its type, and press <strong>Run Neural Network</strong> to see the breakdown.</p>
                )}
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">Try This</h2>
                <ul className="space-y-2 text-secondary">
                  <li>Pick a clearly spiral galaxy — network should be confident</li>
                  <li>{datasetKey === 'galaxy10_sdss' ? 'Pick a thin, edge-on disk — compare it with a round elliptical' : 'Pick a messy irregular — watch probabilities spread out'}</li>
                  <li>Compare different images from the same class</li>
                  <li>Compare your guess vs network — can you beat it?</li>
                </ul>
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">Model Metrics</h2>
                {metrics ? (
                  <div className="space-y-4">
                    <div className="p-3 bg-bg-elevated rounded border border-border">
                      <h4 className="font-semibold mb-2">Test Accuracy: {(metrics.test_accuracy * 100).toFixed(1)}%</h4>
                    </div>
                    <div className="p-3 bg-bg-elevated rounded border border-border">
                      <h4 className="font-semibold mb-2">Confusion Matrix</h4>
                      <div className="table-container">
                        <table>
                          <thead>
                            <tr>
                              <th>True \ Pred</th>
                              {metrics.confusion_matrix ? Object.keys(metrics.confusion_matrix).map(k => <th key={k}>{k}</th>) : []}
                            </tr>
                          </thead>
                          <tbody>
                            {metrics.confusion_matrix ? Object.entries(metrics.confusion_matrix).map(([trueLabel, row]) => (
                              <tr key={trueLabel}>
                                <td className="font-medium">{trueLabel}</td>
                                {Object.entries(row).map(([predLabel, count]) => (
                                  <td key={predLabel} className={trueLabel === predLabel ? 'text-accent-teal font-bold' : ''}>{count}</td>
                                ))}
                              </tr>
                            )) : []}
                          </tbody>
                        </table>
                      </div>
                    </div>
                    <div className="p-3 bg-bg-elevated rounded border border-border">
                      <h4 className="font-semibold mb-2">Per-Class Accuracy</h4>
                      <div className="flex flex-wrap gap-2">
                        {Object.entries(metrics.per_class_accuracy || {}).map(([label, acc]) => (
                          <span key={label} className={`badge ${classColors[label] || 'badge-primary'}`}>
                            {label}: {(acc * 100).toFixed(1)}%
                          </span>
                        ))}
                      </div>
                    </div>
                  </div>
                ) : (
                  <p className="text-secondary">{galleryLoading ? 'Loading metrics…' : 'Metrics will appear after this dataset has a trained model.'}</p>
                )}
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">Real Dataset</h2>
                <p className="text-secondary text-sm mb-4">
                  Source: {datasetOptions[datasetKey]?.publisher} | {dataset?.license} | Images: {dataset?.row_count?.toLocaleString()} | Classes: {datasetLabels.join(', ')}
                </p>
                <p className="text-warning text-sm mb-4">
                  Images from {datasetKey === 'galaxy10_deca' ? 'DESI Legacy Imaging Surveys (DECaLS)' : 'Sloan Digital Sky Survey (SDSS)'}.
                  Galaxy Zoo labels grouped into three classes for this learning exercise. Original labels are retained in the database.
                </p>
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                  {gallery.slice(0, 8).map(img => (
                    <div key={img.id} className="aspect-square rounded-lg overflow-hidden border border-border">
                      <img src={img.image_url} alt={`Galaxy ${img.id}`} className="w-full h-full object-cover" />
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}
