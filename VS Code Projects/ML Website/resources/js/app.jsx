import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { ExplorerProvider, useExplorer } from './context/ExplorerContext';
import { ToastProvider } from './context/ToastContext';
import Header from './components/Header';
import Footer from './components/Footer';
import ToastContainer from './context/ToastContext';
import Home from './pages/Home';
import About from './pages/About';
import Contact from './pages/Contact';
import Datasets from './pages/Datasets';
import MissionOrbit from './pages/missions/MissionOrbit';
import MissionForum from './pages/missions/MissionForum';
import MissionExoplanets from './pages/missions/MissionExoplanets';
import MissionTransit from './pages/missions/MissionTransit';
import MissionTelescope from './pages/missions/MissionTelescope';
import MissionGalaxy from './pages/missions/MissionGalaxy';
import MyLog from './pages/MyLog';
import Onboarding from './pages/Onboarding';
import '../css/app.css';

function AppRoutes() {
  const { explorer, loading } = useExplorer();
  
  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-center">
          <div className="orbit-animation" role="img" aria-label="Loading animation">
            <div className="orbit-ring orbit-ring-1" style={{ '--orbit-radius': '40px' }}></div>
            <div className="orbit-ring orbit-ring-2" style={{ '--orbit-radius': '50px' }}></div>
            <div className="orbit-ring orbit-ring-3" style={{ '--orbit-radius': '55px' }}></div>
            <div className="orbit-planet orbit-planet-1" style={{ '--orbit-radius': '40px' }}></div>
            <div className="orbit-planet orbit-planet-2" style={{ '--orbit-radius': '50px' }}></div>
            <div className="orbit-planet orbit-planet-3" style={{ '--orbit-radius': '55px' }}></div>
          </div>
          <p className="mt-4 text-secondary">Initializing mission control...</p>
        </div>
      </div>
    );
  }

  const isOnboarded = !!explorer;
  const publicPaths = ['/', '/about', '/contact', '/datasets'];
  const currentPath = window.location.pathname;
  const isPublicPath = publicPaths.some(p => currentPath === p || currentPath.startsWith(p + '/'));

  return (
    <Routes>
      <Route path="/onboarding" element={<Onboarding />} />
      <Route path="/login" element={<Onboarding />} />
      
      {isOnboarded ? (
        <>
          <Route path="/" element={<Home />} />
          <Route path="/about" element={<About />} />
          <Route path="/contact" element={<Contact />} />
          <Route path="/datasets" element={<Datasets />} />
          <Route path="/missions/orbit" element={<MissionOrbit />} />
          <Route path="/missions/forum" element={<MissionForum />} />
          <Route path="/missions/exoplanets" element={<MissionExoplanets />} />
          <Route path="/missions/transit" element={<MissionTransit />} />
          <Route path="/missions/telescope" element={<MissionTelescope />} />
          <Route path="/missions/galaxy" element={<MissionGalaxy />} />
          <Route path="/my-log" element={<MyLog />} />
        </>
      ) : (
        <>
          {isPublicPath ? (
            <>
              <Route path="/" element={<Home />} />
              <Route path="/about" element={<About />} />
              <Route path="/contact" element={<Contact />} />
              <Route path="/datasets" element={<Datasets />} />
            </>
          ) : (
            <Route path="/*" element={<Navigate to="/onboarding" replace />} />
          )}
        </>
      )}
    </Routes>
  );
}

function App() {
  return (
    <ExplorerProvider>
      <ToastProvider>
        <div className="min-h-screen flex flex-col">
          <div className="stars-bg" aria-hidden="true" />
          <Header />
          <main className="flex-1">
            <AppRoutes />
          </main>
          <Footer />
          <ToastContainer />
        </div>
      </ToastProvider>
    </ExplorerProvider>
  );
}

export default App;

const root = document.getElementById('root');

if (root) {
  createRoot(root).render(
    <React.StrictMode>
      <BrowserRouter>
        <App />
      </BrowserRouter>
    </React.StrictMode>,
  );
}
