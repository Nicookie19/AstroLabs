import React, { useEffect, useState } from 'react';
import { Link, NavLink, useLocation } from 'react-router-dom';
import { useExplorer } from '../context/ExplorerContext';

const missions = [
  ['orbit', 'Orbit'], ['forum', 'Forum'], ['exoplanets', 'Twins'],
  ['transit', 'Transit'], ['telescope', 'Telescope'], ['galaxy', 'Galaxy'],
];

const navClass = ({ isActive }) => `nav-link ${isActive ? 'nav-link-active' : ''}`;

export default function Header() {
  const { explorer, logout, loading } = useExplorer();
  const [menuOpen, setMenuOpen] = useState(false);
  const location = useLocation();
  useEffect(() => { setMenuOpen(false); }, [location.pathname]);
  const closeMenu = () => setMenuOpen(false);

  return (
    <header className="site-header" onKeyDown={event => { if (event.key === 'Escape') closeMenu(); }}>
      <nav className="container site-nav" aria-label="Main navigation">
        <Link to="/" className="brand" onClick={closeMenu}>
          <img className="brand-mark" src="/galaxy.svg" alt="" aria-hidden="true" />
          <span>Astrolabs</span>
        </Link>

        <button className="menu-toggle" type="button" onClick={() => setMenuOpen(!menuOpen)} aria-expanded={menuOpen} aria-controls="primary-links">
          <span className="sr-only">Toggle navigation</span>{menuOpen ? 'Close' : 'Menu'}
        </button>

        <div id="primary-links" className={`nav-panel ${menuOpen ? 'nav-panel-open' : ''}`}>
          <div className="nav-main-links">
            <NavLink to="/" end className={navClass} onClick={closeMenu}>Home</NavLink>
            <NavLink to="/datasets" className={navClass} onClick={closeMenu}>Data</NavLink>
            <NavLink to="/about" className={navClass} onClick={closeMenu}>About</NavLink>
            <NavLink to="/contact" className={navClass} onClick={closeMenu}>Contact</NavLink>
          </div>

          <div className="mission-nav" aria-label="Missions">
            {missions.map(([key, label], index) => (
              <NavLink key={key} to={`/missions/${key}`} className={navClass} onClick={closeMenu}>
                <span className="mission-number">{String(index + 1).padStart(2, '0')}</span>{label}
              </NavLink>
            ))}
          </div>

          {explorer ? (
            <div className="explorer-chip">
              <span className="explorer-status"><span className="status-dot" />{explorer.callsign}</span>
              <span className="explorer-score">★ {explorer.total_points || 0}</span>
              <NavLink to="/my-log" className={navClass} onClick={closeMenu}>Log</NavLink>
              <button type="button" className="nav-link" onClick={() => { logout(); closeMenu(); }}>Sign out</button>
            </div>
          ) : !loading && (
            <Link to="/onboarding" className="btn btn-primary nav-join" onClick={closeMenu}>Join mission</Link>
          )}
        </div>
      </nav>
    </header>
  );
}
