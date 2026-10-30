const API_BASE = '/api';

async function fetchWithAuth(endpoint, options = {}) {
  const token = localStorage.getItem('explorer_token');
  const headers = {
    'Content-Type': 'application/json',
    ...options.headers,
  };
  if (token) {
    headers['X-Session-Token'] = token;
  }
  
  const response = await fetch(`${API_BASE}${endpoint}`, {
    ...options,
    headers,
  });
  
  const data = await response.json().catch(() => ({}));
  
  if (!response.ok) {
    const error = new Error(data.message || `HTTP ${response.status}`);
    error.status = response.status;
    error.data = data;
    throw error;
  }
  
  return data;
}

export const api = {
  // Explorer
  onboard: (callsign, level) => fetchWithAuth('/explorer/onboard', {
    method: 'POST',
    body: JSON.stringify({ callsign, level }),
  }),
  getExplorer: () => fetchWithAuth('/explorer/me'),
  updateExplorer: (updates) => fetchWithAuth('/explorer/me', {
    method: 'PUT',
    body: JSON.stringify(updates),
  }),
  
  // Progress
  getProgress: () => fetchWithAuth('/progress'),
  reloadRun: (runId) => fetchWithAuth(`/progress/reload/${runId}`),
  
  // Company
  getHome: () => fetchWithAuth('/company/home'),
  getAbout: () => fetchWithAuth('/company/about'),
  sendContact: (data) => fetchWithAuth('/company/contact', {
    method: 'POST',
    body: JSON.stringify(data),
  }),
  
  // Datasets
  getDatasets: () => fetchWithAuth('/datasets'),
  getDataset: (missionKey, datasetKey) => fetchWithAuth(`/datasets/${missionKey}/${datasetKey}`),
  
  // Missions
  // Orbit
  getOrbitDataset: (params) => fetchWithAuth(`/missions/orbit/dataset?${new URLSearchParams(params)}`),
  predictOrbit: (data) => fetchWithAuth('/missions/orbit/predict', {
    method: 'POST',
    body: JSON.stringify(data),
  }),
  compareOrbitDatasets: (data) => fetchWithAuth('/missions/orbit/compare', {
    method: 'POST',
    body: JSON.stringify(data),
  }),
  
  // Forum
  getForumDataset: (params) => fetchWithAuth(`/missions/forum/dataset?${new URLSearchParams(params)}`),
  classifyForum: (data) => fetchWithAuth('/missions/forum/classify', {
    method: 'POST',
    body: JSON.stringify(data),
  }),
  
  // Exoplanets
  getExoplanetsDataset: (params) => fetchWithAuth(`/missions/exoplanets/dataset?${new URLSearchParams(params)}`),
  findNeighbors: (data) => fetchWithAuth('/missions/exoplanets/neighbors', {
    method: 'POST',
    body: JSON.stringify(data),
  }),
  
  // Transit
  getTransitDataset: (params) => fetchWithAuth(`/missions/transit/dataset?${new URLSearchParams(params)}`),
  classifyTransit: (data) => fetchWithAuth('/missions/transit/classify', {
    method: 'POST',
    body: JSON.stringify(data),
  }),
  
  // Telescope
  getTelescopeTargets: (params) => fetchWithAuth(`/missions/telescope/targets?${new URLSearchParams(params)}`),
  recommendTelescope: (data) => fetchWithAuth('/missions/telescope/recommend', {
    method: 'POST',
    body: JSON.stringify(data),
  }),
  
  // Galaxy
  getGalaxyGallery: (params) => fetchWithAuth(`/missions/galaxy/gallery?${new URLSearchParams(params)}`),
  classifyGalaxy: (data) => fetchWithAuth('/missions/galaxy/classify', {
    method: 'POST',
    body: JSON.stringify(data),
  }),
  revealGalaxy: (data) => fetchWithAuth('/missions/galaxy/reveal', {
    method: 'POST',
    body: JSON.stringify(data),
  }),
  getGalaxyMetrics: (params = {}) => fetchWithAuth(`/missions/galaxy/metrics?${new URLSearchParams(params)}`),
};

export default api;
