import React, { useEffect, useState } from 'react';
import { api } from '../utils/api';
import { useToast } from '../context/ToastContext';
import { useExplorer } from '../context/ExplorerContext';

const missionLabels = {
  orbit: 'Orbit Calculator',
  forum: 'Forum Sorter',
  exoplanets: 'Exoplanet Twin Finder',
  transit: 'Transit Hunter',
  telescope: 'Telescope Setup',
  galaxy: 'Galaxy Classifier',
};

export default function MyLog() {
  const { explorer, loading: explorerLoading } = useExplorer();
  const { addToast } = useToast();
  const [progress, setProgress] = useState(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState('all');

  useEffect(() => {
    if (!explorerLoading) {
      loadProgress();
    }
  }, [explorerLoading]);

  const loadProgress = async () => {
    try {
      const data = await api.getProgress();
      setProgress(data);
    } catch (e) {
      console.error('Failed to load progress:', e);
    } finally {
      setLoading(false);
    }
  };

  const handleReload = async (runId) => {
    try {
      const data = await api.reloadRun(runId);
      addToast('Run loaded! Redirecting to mission...', 'success');
      // Navigate to the appropriate mission page with the run data
      window.location.href = `/missions/${data.run.mission_key}`;
    } catch (e) {
      addToast('Failed to reload run', 'error');
    }
  };

  const formatDate = (dateStr) => {
    return new Date(dateStr).toLocaleString(undefined, {
      dateStyle: 'medium',
      timeStyle: 'short',
    });
  };

  if (explorerLoading || loading) {
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

  if (!explorer) {
    return (
      <div className="section container text-center">
        <div className="card max-w-md mx-auto">
          <h2 className="text-2xl font-bold mb-4">Mission Log Locked</h2>
          <p className="text-secondary mb-6">Complete the onboarding to access your mission log.</p>
          <a href="/onboarding" className="btn btn-primary">Join Mission</a>
        </div>
      </div>
    );
  }

  const filteredRuns = progress?.runs?.filter(run => 
    activeTab === 'all' || run.mission_key === activeTab
  ) || [];

  const missionsWithRuns = [...new Set(progress?.runs?.map(r => r.mission_key) || [])];

  return (
    <div className="min-h-screen">
      <section className="section">
        <div className="container max-w-5xl">
          <header className="mb-8">
            <h1 className="text-3xl font-bold mb-2 text-gradient">Mission Log</h1>
            <p className="text-secondary">Commander {explorer.callsign} ({explorer.level}) — ★ {explorer.total_points} total points</p>
          </header>

          <div className="card mb-8">
            <div className="flex flex-wrap gap-4">
              <button
                className={`btn ${activeTab === 'all' ? 'btn-primary' : 'btn-secondary'}`}
                onClick={() => setActiveTab('all')}
              >
                All Missions
              </button>
              {missionsWithRuns.map(key => (
                <button
                  key={key}
                  className={`btn ${activeTab === key ? 'btn-primary' : 'btn-secondary'}`}
                  onClick={() => setActiveTab(key)}
                >
                  {missionLabels[key] || key}
                </button>
              ))}
            </div>
          </div>

          {filteredRuns.length === 0 ? (
            <div className="card text-center py-12">
              <div className="text-4xl mb-4">📋</div>
              <h3 className="text-xl font-bold mb-2">No runs recorded yet</h3>
              <p className="text-secondary mb-6">
                {activeTab === 'all' 
                  ? 'Complete missions to see your history here.' 
                  : `No runs for ${missionLabels[activeTab] || activeTab} yet.`}
              </p>
              <a href="/" className="btn btn-primary">Explore Missions</a>
            </div>
          ) : (
            <div className="space-y-4">
              {filteredRuns.map(run => (
                <article key={run.id} className="card hover:border-accent-amber/50 transition-colors">
                  <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="flex items-center gap-4">
                      <div className="w-12 h-12 rounded-lg bg-bg-elevated flex items-center justify-center text-xl">
                        {Object.entries(missionLabels).find(([k]) => k === run.mission_key)?.[1]?.[0] || '🚀'}
                      </div>
                      <div>
                        <h3 className="font-bold">{missionLabels[run.mission_key] || run.mission_key}</h3>
                        <div className="flex items-center gap-3 text-sm text-secondary mt-1">
                          <span className="badge badge-primary">{run.dataset_title || run.dataset_key}</span>
                          <span className="font-mono">{formatDate(run.created_at)}</span>
                          <span className="text-accent-amber">★ {run.points} pts</span>
                        </div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => handleReload(run.id)}
                        className="btn btn-secondary text-sm"
                        aria-label={`Reload ${missionLabels[run.mission_key]} run`}
                      >
                        Reload Run
                      </button>
                    </div>
                  </div>
                  
                  <details className="mt-4 pt-4 border-t border-border">
                    <summary className="cursor-pointer text-sm text-secondary font-medium flex items-center gap-2">
                      View Details <span className="text-accent-amber">▼</span>
                    </summary>
                    <div className="mt-4 grid md:grid-cols-2 gap-4 text-sm">
                      <div>
                        <h4 className="font-semibold mb-2">Inputs</h4>
                        <pre className="bg-bg-deep p-3 rounded text-xs overflow-auto font-mono max-h-64">
                          {JSON.stringify(run.inputs, null, 2)}
                        </pre>
                      </div>
                      <div>
                        <h4 className="font-semibold mb-2">Model Output</h4>
                        <pre className="bg-bg-deep p-3 rounded text-xs overflow-auto font-mono max-h-64">
                          {JSON.stringify(run.output, null, 2)}
                        </pre>
                      </div>
                      {run.user_guess && (
                        <div className="md:col-span-2">
                          <h4 className="font-semibold mb-2">Your Guess</h4>
                          <pre className="bg-bg-deep p-3 rounded text-xs overflow-auto font-mono">
                            {JSON.stringify(run.user_guess, null, 2)}
                          </pre>
                        </div>
                      )}
                    </div>
                  </details>
                </article>
              ))}
            </div>
          )}
        </div>
      </section>
    </div>
  );
}