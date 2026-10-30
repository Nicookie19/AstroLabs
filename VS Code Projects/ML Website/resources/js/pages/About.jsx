import React, { useEffect, useState } from 'react';
import { api } from '../utils/api';

export default function About() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      const res = await api.getAbout();
      setData(res);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  };

  if (loading) return <div className="section container"><div className="text-center py-12">Loading...</div></div>;
  if (error) return <div className="section container"><div className="card text-center">Error: {error}</div></div>;

  return (
    <div className="min-h-screen">
      <section className="section">
        <div className="container max-w-4xl">
          <header className="mb-12">
            <h1 className="text-4xl font-bold mb-4 text-gradient">About Astrolabs</h1>
            <p className="text-secondary text-lg">{data?.story}</p>
          </header>

          <div className="grid md:grid-cols-2 gap-8 mb-12">
            <div className="card">
              <h2 className="text-xl font-bold mb-4">Mission</h2>
              <p className="text-secondary">{data?.mission}</p>
            </div>
            <div className="card">
              <h2 className="text-xl font-bold mb-4">Vision</h2>
              <p className="text-secondary">{data?.vision}</p>
            </div>
          </div>

          <section className="mb-12">
            <h2 className="text-2xl font-bold mb-6">Values</h2>
            <div className="grid md:grid-cols-3 gap-6">
              {data?.values?.map((value, i) => (
                <div key={i} className="card">
                  <h3 className="font-semibold mb-2">{value.split(':')[0]}</h3>
                  <p className="text-secondary text-sm">{value.split(':').slice(1).join(':').trim()}</p>
                </div>
              ))}
            </div>
          </section>

          <section className="mb-12">
            <h2 className="text-2xl font-bold mb-6">What We Offer</h2>
            <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
              {data?.services?.map(service => (
                <div key={service.id} className="card">
                  <h3 className="font-bold mb-2">{service.name}</h3>
                  <p className="text-secondary text-sm mb-3">{service.description}</p>
                  <div className="flex items-center gap-2 text-sm">
                    <span className="badge badge-primary">Algorithm: {data?.algorithm_mapping?.[service.name] || 'ML'}</span>
                  </div>
                </div>
              ))}
            </div>
          </section>

          <section className="mb-12">
            <h2 className="text-2xl font-bold mb-6">Algorithm & Service Mapping</h2>
            <div className="table-container">
              <table>
                <thead>
                  <tr>
                    <th>Service</th>
                    <th>Algorithm</th>
                  </tr>
                </thead>
                <tbody>
                  {Object.entries(data?.algorithm_mapping || {}).map(([service, algo]) => (
                    <tr key={service}>
                      <td>{service}</td>
                      <td><span className="badge badge-primary">{algo}</span></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>

          <section className="mb-12">
            <h2 className="text-2xl font-bold mb-6">Roadmap</h2>
            <div className="space-y-4">
              {data?.roadmap?.map(item => (
                <div key={item.id} className="card flex flex-col md:flex-row md:items-center justify-between gap-4">
                  <div>
                    <h3 className="font-bold">{item.title}</h3>
                    <p className="text-secondary text-sm">{item.description}</p>
                  </div>
                  <div className="flex items-center gap-3">
                    <span className={`badge ${item.status === 'completed' ? 'badge-success' : item.status === 'in_progress' ? 'badge-primary' : 'badge-warning'}`}>
                      {item.status}
                    </span>
                    {item.target_date && <span className="text-sm text-muted font-mono">{item.target_date}</span>}
                  </div>
                </div>
              ))}
            </div>
          </section>

          <section className="mb-12">
            <h2 className="text-2xl font-bold mb-6">Our Team</h2>
            <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
              {data?.team?.map(member => (
                <div key={member.id} className="card text-center">
                  <div className="w-20 h-20 rounded-full bg-bg-elevated flex items-center justify-center text-2xl font-bold text-accent-amber mx-auto mb-4 border border-border">
                    {member.avatar_initials}
                  </div>
                  <h3 className="font-bold">{member.name}</h3>
                  <p className="text-accent-amber text-sm mb-2">{member.role}</p>
                  <p className="text-secondary text-sm">{member.bio}</p>
                </div>
              ))}
            </div>
          </section>

          <section className="mb-12">
            <h2 className="text-2xl font-bold mb-6">FAQ</h2>
            <div className="space-y-4">
              {data?.faqs?.map(faq => (
                <details key={faq.id} className="card group">
                  <summary className="font-semibold cursor-pointer list-none flex items-center justify-between p-2">
                    {faq.question}
                    <span className="text-accent-amber transition-transform group-open:rotate-180">▼</span>
                  </summary>
                  <div className="px-2 pb-4 text-secondary border-t border-border mt-2">
                    {faq.answer}
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