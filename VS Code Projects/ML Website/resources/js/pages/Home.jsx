import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useExplorer } from '../context/ExplorerContext';
import { api } from '../utils/api';

const missionCards = [
  {
    key: 'orbit',
    title: 'Orbit Calculator',
    algorithm: 'Linear Regression',
    description: 'Predict orbital periods from semi-major axis and star mass using Kepler\'s third law. Compare across 5 real datasets.',
    icon: '🪐',
    features: ['Multiple linear regression on logs', 'Normal equations solver', 'R² on held-out data', '5 switchable datasets'],
  },
  {
    key: 'forum',
    title: 'Forum Sorter',
    algorithm: 'Naive Bayes',
    description: 'Classify space science questions into Planetary Science, Stellar Physics, or Space Exploration Tech.',
    icon: '💬',
    features: ['Multinomial NB with Laplace smoothing', 'Word contribution analysis', '2 real datasets', 'Stack Exchange + arXiv'],
  },
  {
    key: 'exoplanets',
    title: 'Exoplanet Twin Finder',
    algorithm: 'K-Nearest Neighbors',
    description: 'Find similar exoplanets by temperature, radius, and density. Type derived from mass (not a feature).',
    icon: '🔭',
    features: ['Euclidean distance on standardized features', 'k=1,3,5,7,9 adjustable', '3 real datasets', 'Shows k nearest neighbors'],
  },
  {
    key: 'transit',
    title: 'Transit Hunter',
    algorithm: 'Support Vector Machine',
    description: 'Detect exoplanet transits from depth and signal-to-noise. Adjustable margin penalty (lambda).',
    icon: '📈',
    features: ['Linear SVM with SGD', 'Decision boundary visualization', '2 datasets (Kepler + TESS)', 'Reconstructed light curve'],
  },
  {
    key: 'telescope',
    title: 'Telescope Setup Wizard',
    algorithm: 'Decision Tree',
    description: 'Get optimal filter, exposure, and aperture for any target based on Bortle scale and distance.',
    icon: '🔭',
    features: ['Expert rule-based tree', '3 real target catalogs', 'Path explanation', 'Solar system + deep sky'],
  },
  {
    key: 'galaxy',
    title: 'Galaxy Classifier',
    algorithm: 'Neural Network',
    description: 'Classify galaxy morphology from 16×16 grayscale images. 24 hidden tanh neurons, 3 softmax outputs.',
    icon: '🌌',
    features: ['Feed-forward network in PHP', 'Backpropagation with SGD', '2 datasets (DECaLS + SDSS)', 'Pixel importance heatmap'],
  },
];

export default function Home() {
  const { explorer, loading } = useExplorer();
  const [companyData, setCompanyData] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!loading) {
      loadCompanyData();
    }
  }, [loading]);

  const loadCompanyData = async () => {
    try {
      const data = await api.getHome();
      setCompanyData(data);
    } catch (e) {
      setError(e.message);
      console.error('Failed to load home data:', e);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="orbit-animation" role="img" aria-label="Loading">
          <div className="orbit-ring orbit-ring-1" style={{ '--orbit-radius': '40px' }}></div>
          <div className="orbit-ring orbit-ring-2" style={{ '--orbit-radius': '50px' }}></div>
          <div className="orbit-ring orbit-ring-3" style={{ '--orbit-radius': '55px' }}></div>
          <div className="orbit-planet orbit-planet-1" style={{ '--orbit-radius': '40px' }}></div>
          <div className="orbit-planet orbit-planet-2" style={{ '--orbit-radius': '50px' }}></div>
          <div className="orbit-planet orbit-planet-3" style={{ '--orbit-radius': '55px' }}></div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen">
      <section className="mission-hero" aria-labelledby="hero-title">
        <div className="container">
          <div className="hero-content">
            <p className="eyebrow">REAL DATA · HUMAN-SIZED MODELS</p>
            <h1 id="hero-title" className="hero-title">
              <span className="text-gradient">Astrolabs</span>
            </h1>
            <p className="hero-copy">
              {companyData?.company_profile || 'Your interactive gateway to space science, where real astronomical data meets machine learning.'}
            </p>
            
            {explorer ? (
              <div className="hero-progress">
                <div className="progress-card">
                  <span className="badge badge-primary">{explorer.level}</span>
                  <span>Explorer <strong className="font-mono">{explorer.callsign}</strong></span>
                  <span className="text-accent-amber">★ {explorer.total_points} pts</span>
                  <span className="progress-divider" />
                  <span><strong>{explorer.missions_tried || 0}/6</strong> missions explored</span>
                </div>
              </div>
            ) : (
              <Link to="/onboarding" className="btn btn-primary hero-cta">
                Start your explorer profile <span aria-hidden="true">→</span>
              </Link>
            )}
            <p className="hero-note">Six guided experiments. Results only appear after you make a prediction.</p>
          </div>
        </div>
      </section>

      <section className="section" aria-labelledby="missions-title">
        <div className="container">
          <header className="section-heading">
            <div><p className="eyebrow">MISSION CONTROL</p><h2 id="missions-title">Choose a question to investigate</h2></div>
            <p>
              {companyData?.data_driven_needs || 'Astrolabs needs to process massive astronomical datasets, classify celestial objects, predict orbital mechanics, and guide observational strategies—all requiring robust machine learning.'}
            </p>
          </header>

          <div className="mission-grid">
            {missionCards.map((mission, index) => (
              <Link
                key={mission.key}
                to={explorer ? `/missions/${mission.key}` : '/onboarding'}
                className="mission-card group"
              >
                <div className="mission-card-top">
                  <span className="mission-index">{String(index + 1).padStart(2, '0')}</span>
                  <span className="mission-icon">{mission.icon}</span>
                  <div>
                    <h3>{mission.title}</h3>
                    <p>{mission.algorithm}</p>
                  </div>
                </div>
                <p className="mission-description">{mission.description}</p>
                <ul className="mission-features">
                  {mission.features.slice(0, 2).map(feature => (
                    <li key={feature}>
                      <span>✓</span> {feature}
                    </li>
                  ))}
                </ul>
                <span className="mission-open">Open mission <span aria-hidden="true">→</span></span>
              </Link>
            ))}
          </div>
        </div>
      </section>

      <section className="section bg-bg-elevated/50" aria-labelledby="data-trust-title">
        <div className="container">
          <div className="card text-center max-w-3xl mx-auto">
            <h2 id="data-trust-title" className="text-2xl font-bold mb-4">Data You Can Trust</h2>
            <p className="text-secondary mb-6">
              Every mission uses real astronomical datasets from authoritative sources. No generated or fake data.
              All sources cited with licenses and retrieval dates.
            </p>
            <Link to="/datasets" className="btn btn-secondary inline-block">
              View All Datasets & Sources
            </Link>
          </div>
        </div>
      </section>
    </div>
  );
}
