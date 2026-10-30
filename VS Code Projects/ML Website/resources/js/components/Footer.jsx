import React from 'react';
import { Link } from 'react-router-dom';

const missions = [
  ['orbit', 'Orbit calculator'],
  ['forum', 'Forum sorter'],
  ['exoplanets', 'Twin finder'],
  ['transit', 'Transit hunter'],
  ['telescope', 'Telescope setup'],
  ['galaxy', 'Galaxy classifier'],
];

export default function Footer() {
  return (
    <footer className="site-footer">
      <div className="container">
        <div className="footer-grid">
          <div className="footer-intro">
            <Link to="/" className="brand">
              <img className="brand-mark" src="/galaxy.svg" alt="" aria-hidden="true" />
              <span>Astrolabs</span>
            </Link>
            <p>A fictional learning lab where students explore real astronomy data through transparent, hands-on machine learning models.</p>
            <Link to="/onboarding" className="footer-cta">Start an expedition <span aria-hidden="true">→</span></Link>
          </div>

          <nav aria-label="Explore Astrolabs">
            <h2>Explore</h2>
            <Link to="/">Mission control</Link>
            <Link to="/datasets">Data & sources</Link>
            <Link to="/about">About the lab</Link>
            <Link to="/contact">Contact</Link>
          </nav>

          <nav aria-label="Mission links">
            <h2>Experiments</h2>
            {missions.map(([key, label], index) => (
              <Link key={key} to={`/missions/${key}`}><span>{String(index + 1).padStart(2, '0')}</span>{label}</Link>
            ))}
          </nav>

          <div className="footer-data">
            <h2>Built on real data</h2>
            <p>NASA Exoplanet Archive · OpenNGC · Stack Exchange CC BY-SA · Galaxy10 / astroNN</p>
            <Link to="/datasets">View dataset licenses & citations</Link>
          </div>
        </div>

        <div className="footer-bottom">
          <p>Fictional company created for ELEC026.23 Machine Learning.</p>
          <p>Learn by predicting first. Inspect the reasoning after.</p>
        </div>
      </div>
    </footer>
  );
}
