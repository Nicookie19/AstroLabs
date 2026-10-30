import React, { useEffect, useState } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { useExplorer } from '../../context/ExplorerContext';
import { api } from '../../utils/api';
import { useToast } from '../../context/ToastContext';

const datasetOptions = {
  stackexchange_qa: { title: 'Stack Exchange Q&A', publisher: 'Stack Exchange Inc.' },
  arxiv: { title: 'arXiv Research Abstracts', publisher: 'arXiv (CC0)' },
};

const categoryColors = {
  'Planetary Science': 'badge-primary',
  'Stellar Physics': 'badge-success',
  'Space Exploration Tech': 'badge-info',
};

const levelExplanations = {
  Beginner: {
    formula: 'Naive Bayes counts how often words appear in each category. It assumes words are independent (naive) and uses Bayes\' theorem to calculate probabilities.',
    workedExample: 'Question: "Mars rover finds water". Words "mars", "rover", "water" appear often in Planetary Science. The model multiplies their probabilities to predict "Planetary Science".',
  },
  Intermediate: {
    formula: 'P(category|words) ∝ P(category) × ∏ P(word|category). With Laplace smoothing: P(word|cat) = (count + 1) / (total_words + vocab_size). Log-probabilities prevent underflow.',
    workedExample: 'If "mars" appears 50 times in 1000 Planetary words (vocab=5000), P(mars|Planetary) = 51/6000 = 0.0085. In Stellar: 2 times → 3/6000 = 0.0005. Strong signal for Planetary.',
  },
  Advanced: {
    formula: 'Log-posterior: log P(c|w) = log P(c) + Σ log P(wᵢ|c). Training: count word frequencies per category, compute priors P(c) = N_c/N. Smoothing parameter α=1. Prediction: argmax_c log-posterior.',
    workedExample: 'Vocabulary size V=5000. Planetary: 1000 docs, 50000 words. Prior=0.33. Word "mars": count=50 → log(51/55000) = -6.93. Word "star": count=5 → log(6/55000) = -9.04. Sum determines winning category.',
  },
};

export default function MissionForum() {
  const { explorer, loading: explorerLoading } = useExplorer();
  const { addToast } = useToast();
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();
  
  const [datasetKey, setDatasetKey] = useState(searchParams.get('dataset') || 'stackexchange_qa');
  const [dataset, setDataset] = useState(null);
  const [formData, setFormData] = useState({
    question: '',
    user_guess_category: '',
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
      const res = await api.getForumDataset({ dataset: datasetKey });
      setDataset(res.dataset);
    } catch (e) {
      console.error('Failed to load dataset:', e);
    }
  };

  const validateForm = () => {
    const newErrors = {};
    if (!formData.question.trim()) newErrors.question = 'Please enter a question';
    else if (formData.question.length < 10) newErrors.question = 'Question must be at least 10 characters';
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
      const res = await api.classifyForum({ ...formData, dataset: datasetKey });
      setResult(res.classification);
      addToast('Question classified!', 'success');
    } catch (e) {
      addToast(e.message || 'Classification failed', 'error');
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

  const handleDatasetChange = (key) => {
    setDatasetKey(key);
    setSearchParams({ dataset: key });
    setResult(null);
    setFormData({ question: '', user_guess_category: '' });
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
                <strong>Questions:</strong> {dataset.row_count?.toLocaleString()} | 
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
                  Type a space/astronomy question. Optionally guess the category for points!
                </p>
                
                <form onSubmit={handleSubmit} noValidate>
                  <div className="form-group">
                    <label htmlFor="question">Your Question</label>
                    <textarea
                      id="question"
                      name="question"
                      value={formData.question}
                      onChange={handleChange}
                      rows={4}
                      className={errors.question ? 'border-error' : ''}
                      placeholder="e.g., How does a black hole form? What is the temperature of Mars?"
                      required
                      disabled={loading}
                    />
                    {errors.question && <p className="text-error text-sm mt-1">{errors.question}</p>}
                  </div>

                  <div className="form-group">
                    <label htmlFor="user_guess_category">Your Guess (optional)</label>
                    <select
                      id="user_guess_category"
                      name="user_guess_category"
                      value={formData.user_guess_category}
                      onChange={handleChange}
                      className={errors.user_guess_category ? 'border-error' : ''}
                      disabled={loading}
                    >
                      <option value="">— Select category —</option>
                      <option value="Planetary Science">Planetary Science</option>
                      <option value="Stellar Physics">Stellar Physics</option>
                      <option value="Space Exploration Tech">Space Exploration Tech</option>
                    </select>
                  </div>

                  <button
                    type="submit"
                    className="btn btn-primary w-full py-3 mt-2"
                    disabled={loading || !formData.question.trim()}
                  >
                    {loading ? 'Classifying...' : 'Run Model'}
                  </button>
                </form>

                {submitted && result && (
                  <div className="mt-6 p-4 bg-bg-elevated rounded-lg border border-border space-y-2">
                    <h4 className="font-semibold">Classification</h4>
                    <div className="flex items-center gap-3">
                      <span className={`badge ${categoryColors[result.category] || 'badge-primary'}`}>
                        {result.category}
                      </span>
                      <span className="text-sm text-secondary">Confidence: {(result.confidence * 100).toFixed(1)}%</span>
                    </div>
                    {formData.user_guess_category && (
                      <div className="text-sm">
                        <span className="text-secondary">Your guess:</span> 
                        <span className={`font-mono ml-2 ${formData.user_guess_category === result.category ? 'text-accent-teal' : 'text-accent-coral'}`}>
                          {formData.user_guess_category} {formData.user_guess_category === result.category ? '✓' : '✗'}
                        </span>
                      </div>
                    )}
                    <details className="mt-4">
                      <summary className="cursor-pointer text-sm text-secondary">All scores</summary>
                      <pre className="mt-2 bg-bg-deep p-2 rounded text-xs overflow-auto font-mono">
                        {JSON.stringify(result.all_scores, null, 2)}
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
                  Astrolabs's forum receives thousands of questions daily. We need to automatically route
                  them to the right expert team: Planetary Science, Stellar Physics, or Space Exploration Tech.
                  Naive Bayes is perfect for this — fast, interpretable, and works well with text.
                </p>
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">How the Algorithm Works</h2>
                <div className="space-y-4 text-secondary">
                  <p>
                    <strong>Multinomial Naive Bayes with Laplace Smoothing:</strong> We tokenize question titles into lowercase words,
                    remove stop words, and count word frequencies per category.
                  </p>
                  <p>
                    <strong>Training:</strong> For each category c, compute prior P(c) = N_c/N.
                    For each word w, compute P(w|c) = (count(w,c) + 1) / (total_words_in_c + vocab_size).
                    The +1 is Laplace smoothing — prevents zero probabilities for unseen words.
                  </p>
                  <p>
                    <strong>Prediction:</strong> For a new question, compute log-posterior for each category:
                    log P(c) + Σ log P(wᵢ|c). Pick the category with highest score.
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
                      <h4 className="font-semibold mb-2">Prior Probabilities</h4>
                      <div className="flex flex-wrap gap-2">
                        {Object.entries(result.explanation.priors).map(([cat, prob]) => (
                          <span key={cat} className={`badge ${categoryColors[cat] || 'badge-primary'}`}>
                            {cat}: {(prob * 100).toFixed(1)}%
                          </span>
                        ))}
                      </div>
                    </div>

                    <div className="p-3 bg-bg-elevated rounded border border-border">
                      <h4 className="font-semibold mb-2">Top Words Pushing Toward: {result.category}</h4>
                      <div className="space-y-1 text-sm">
                        {result.explanation.top_words_for_winner?.map((w, i) => (
                          <div key={i} className="flex justify-between">
                            <span className="font-mono">{w.word}</span>
                            <span className="text-accent-teal">log P = {w.log_probability.toFixed(3)}</span>
                          </div>
                        ))}
                      </div>
                    </div>

                    <div className="p-3 bg-bg-elevated rounded border border-border">
                      <h4 className="font-semibold mb-2">Words Pushing Other Categories</h4>
                      <div className="space-y-2">
                        {Object.entries(result.explanation.top_words_for_others || {}).map(([cat, words]) => (
                          <div key={cat} className="border-t border-border pt-2">
                            <span className={`badge ${categoryColors[cat] || 'badge-primary'} mb-1`}>{cat}</span>
                            <div className="space-y-1 text-sm">
                              {words?.map((w, i) => (
                                <div key={i} className="flex justify-between">
                                  <span className="font-mono">{w.word}</span>
                                  <span className="text-accent-coral">log P = {w.log_probability.toFixed(3)}</span>
                                </div>
                              ))}
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>

                    <div className="p-3 bg-bg-elevated rounded border border-border">
                      <h4 className="font-semibold mb-2">Log Posteriors</h4>
                      <div className="flex flex-wrap gap-2">
                        {Object.entries(result.all_scores).map(([cat, score]) => (
                          <span key={cat} className={`badge ${categoryColors[cat] || 'badge-primary'}`}>
                            {cat}: {score.toFixed(2)}
                          </span>
                        ))}
                      </div>
                    </div>
                  </div>
                ) : (
                  <p className="text-secondary">Enter a question and press <strong>Run Model</strong> to see the word-by-word breakdown.</p>
                )}
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">Try This</h2>
                <ul className="space-y-2 text-secondary">
                  <li>Type "Mars rover discovers ancient riverbed" — should be Planetary Science</li>
                  <li>Type "Supernova remnant expands at 10000 km/s" — should be Stellar Physics</li>
                  <li>Type "SpaceX Starship orbital refueling demonstration" — should be Space Exploration Tech</li>
                  <li>Switch to arXiv dataset — notice how scientific language changes predictions</li>
                </ul>
              </div>

              <div className="card">
                <h2 className="font-bold text-xl mb-4">Real Dataset</h2>
                <p className="text-secondary text-sm mb-4">
                  Source: {datasetOptions[datasetKey]?.publisher} | {dataset?.license} | Questions: {dataset?.row_count?.toLocaleString()}
                </p>
                <div className="table-container">
                  <table>
                    <thead>
                      <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Site</th>
                        <th>Score</th>
                      </tr>
                    </thead>
                    <tbody>
                      {dataset?.questions?.data?.slice(0, 10).map((q, i) => (
                        <tr key={i}>
                          <td className="max-w-xs truncate">{q.title}</td>
                          <td><span className={`badge ${categoryColors[q.category] || 'badge-primary'}`}>{q.category}</span></td>
                          <td>{q.source_site}</td>
                          <td className="font-mono">{q.score}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <p className="text-muted text-xs mt-2">
                  Attribution: Data from Astronomy Stack Exchange and Space Exploration Stack Exchange via Stack Exchange API. Content licensed under CC BY-SA 4.0.
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}
