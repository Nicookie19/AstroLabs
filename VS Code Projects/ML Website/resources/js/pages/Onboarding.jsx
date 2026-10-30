import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useExplorer } from '../context/ExplorerContext';
import { useToast } from '../context/ToastContext';

const levels = [
  {
    value: 'Beginner',
    label: 'Beginner',
    description: 'Plain-language explanations with analogies. No formulas shown.',
    icon: '🌱',
  },
  {
    value: 'Intermediate',
    label: 'Intermediate',
    description: 'Key formulas and worked examples with real numbers.',
    icon: '🚀',
  },
  {
    value: 'Advanced',
    label: 'Advanced',
    description: 'Full mathematical derivations (normal equations, log-probabilities, hinge loss, backpropagation).',
    icon: '🧠',
  },
];

export default function Onboarding() {
  const { onboard } = useExplorer();
  const { addToast } = useToast();
  const navigate = useNavigate();
  
  const [formData, setFormData] = useState({
    callsign: '',
    level: 'Beginner',
  });
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);

  const validate = () => {
    const newErrors = {};
    if (!formData.callsign.trim()) newErrors.callsign = 'Callsign is required';
    else if (formData.callsign.length < 2) newErrors.callsign = 'Callsign must be at least 2 characters';
    else if (formData.callsign.length > 20) newErrors.callsign = 'Callsign cannot exceed 20 characters';
    else if (!/^[a-zA-Z0-9_\-\s]+$/.test(formData.callsign)) newErrors.callsign = 'Only letters, numbers, spaces, hyphens, and underscores allowed';
    if (!formData.level) newErrors.level = 'Please select an experience level';
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!validate()) return;
    
    setSubmitting(true);
    try {
      await onboard(formData.callsign.trim(), formData.level);
      addToast(`Welcome, ${formData.callsign}! Mission control initialized.`, 'success');
      navigate('/');
    } catch (e) {
      addToast(e.message || 'Onboarding failed', 'error');
    } finally {
      setSubmitting(false);
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
    if (errors[name]) setErrors(prev => ({ ...prev, [name]: '' }));
  };

  return (
    <div className="min-h-screen flex items-center justify-center p-4">
      <div className="w-full max-w-md">
        <div className="text-center mb-8">
          <h1 className="text-4xl font-bold mb-4 text-gradient">Join the Mission</h1>
          <p className="text-secondary">
            Create your explorer profile to unlock all six ML missions. Your callsign and experience level
            will personalize your journey through real astronomical data.
          </p>
        </div>

        <form onSubmit={handleSubmit} className="card space-y-6" noValidate>
          <div className="form-group">
            <label htmlFor="callsign">Callsign</label>
            <input
              type="text"
              id="callsign"
              name="callsign"
              value={formData.callsign}
              onChange={handleChange}
              className={errors.callsign ? 'border-error' : ''}
              placeholder="Enter your callsign (2-20 chars)"
              maxLength={20}
              required
              disabled={submitting}
              autoFocus
            />
            {errors.callsign && <p className="text-error text-sm mt-1" role="alert">{errors.callsign}</p>}
            <p className="text-muted text-xs mt-1">Letters, numbers, spaces, hyphens, underscores only</p>
          </div>

          <div className="form-group">
            <label>Experience Level</label>
            <div className="space-y-3" role="radiogroup" aria-label="Experience level">
              {levels.map(level => (
                <label
                  key={level.value}
                  className={`relative cursor-pointer transition-all ${formData.level === level.value ? 'ring-2 ring-accent-amber' : ''}`}
                >
                  <input
                    type="radio"
                    name="level"
                    value={level.value}
                    checked={formData.level === level.value}
                    onChange={handleChange}
                    className="sr-only"
                    required
                    disabled={submitting}
                  />
                  <div className={`p-4 rounded-lg border-2 ${formData.level === level.value ? 'border-accent-amber bg-bg-elevated' : 'border-border hover:border-accent-amber/50'}`}>
                    <div className="flex items-start gap-3">
                      <span className="text-2xl flex-shrink-0">{level.icon}</span>
                      <div>
                        <div className="font-semibold">{level.label}</div>
                        <div className="text-secondary text-sm mt-1">{level.description}</div>
                      </div>
                    </div>
                  </div>
                </label>
              ))}
            </div>
            {errors.level && <p className="text-error text-sm mt-1" role="alert">{errors.level}</p>}
          </div>

          <button
            type="submit"
            className="btn btn-primary w-full py-3 text-lg"
            disabled={submitting}
          >
            {submitting ? 'Initializing...' : 'Begin Mission'}
          </button>
        </form>

        <div className="mt-6 text-center text-sm text-muted">
          <p>By joining, you agree to store your progress locally and in our database.</p>
          <p className="mt-1">No personal data is collected beyond your chosen callsign.</p>
        </div>
      </div>
    </div>
  );
}