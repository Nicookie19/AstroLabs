import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';

const ExplorerContext = createContext(null);

const API_BASE = '/api';

export function ExplorerProvider({ children }) {
  const [explorer, setExplorer] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem('explorer_token');
    if (token) {
      fetchExplorer(token);
    } else {
      setLoading(false);
    }
  }, []);

  const fetchExplorer = async (token) => {
    try {
      const res = await fetch(`${API_BASE}/explorer/me`, {
        headers: { 'X-Session-Token': token },
      });
      if (res.ok) {
        const data = await res.json();
        setExplorer(data.explorer);
      } else {
        localStorage.removeItem('explorer_token');
      }
    } catch (e) {
      console.error('Failed to fetch explorer:', e);
      localStorage.removeItem('explorer_token');
    } finally {
      setLoading(false);
    }
  };

  const onboard = useCallback(async (callsign, level) => {
    try {
      const res = await fetch(`${API_BASE}/explorer/onboard`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ callsign, level }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Onboarding failed');
      
      localStorage.setItem('explorer_token', data.explorer.session_token);
      setExplorer(data.explorer);
      return data.explorer;
    } catch (e) {
      throw e;
    }
  }, []);

  const updateExplorer = useCallback(async (updates) => {
    if (!explorer) return;
    try {
      const res = await fetch(`${API_BASE}/explorer/me`, {
        method: 'PUT',
        headers: { 
          'Content-Type': 'application/json',
          'X-Session-Token': explorer.session_token,
        },
        body: JSON.stringify(updates),
      });
      const data = await res.json();
      if (res.ok) {
        setExplorer(data.explorer);
      }
    } catch (e) {
      console.error('Failed to update explorer:', e);
    }
  }, [explorer]);

  const logout = useCallback(() => {
    localStorage.removeItem('explorer_token');
    setExplorer(null);
  }, []);

  return (
    <ExplorerContext.Provider value={{ explorer, loading, onboard, updateExplorer, logout }}>
      {children}
    </ExplorerContext.Provider>
  );
}

export function useExplorer() {
  const context = useContext(ExplorerContext);
  if (!context) {
    throw new Error('useExplorer must be used within ExplorerProvider');
  }
  return context;
}