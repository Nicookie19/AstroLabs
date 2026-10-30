import React, { useState } from 'react';
import { api } from '../utils/api';
import { useToast } from '../context/ToastContext';

export default function Contact() {
  const { addToast } = useToast();
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    subject: '',
    message: '',
  });
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);

  const validate = () => {
    const newErrors = {};
    if (!formData.name.trim()) newErrors.name = 'Name is required';
    if (!formData.email.trim()) newErrors.email = 'Email is required';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) newErrors.email = 'Invalid email format';
    if (!formData.subject.trim()) newErrors.subject = 'Subject is required';
    if (!formData.message.trim()) newErrors.message = 'Message is required';
    else if (formData.message.length < 10) newErrors.message = 'Message must be at least 10 characters';
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!validate()) return;
    
    setSubmitting(true);
    try {
      await api.sendContact(formData);
      addToast('Message sent successfully! We\'ll get back to you soon.', 'success');
      setFormData({ name: '', email: '', subject: '', message: '' });
    } catch (e) {
      addToast(e.message || 'Failed to send message', 'error');
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
    <div className="min-h-screen">
      <section className="section">
        <div className="container max-w-2xl">
          <header className="mb-10 text-center">
            <h1 className="text-4xl font-bold mb-4 text-gradient">Contact Us</h1>
            <p className="text-secondary text-lg">
              Have questions about our missions, datasets, or algorithms? We'd love to hear from you.
            </p>
          </header>

          <form onSubmit={handleSubmit} className="card" noValidate>
            <div className="form-group">
              <label htmlFor="name">Name</label>
              <input
                type="text"
                id="name"
                name="name"
                value={formData.name}
                onChange={handleChange}
                className={errors.name ? 'border-error' : ''}
                placeholder="Your name"
                required
                disabled={submitting}
              />
              {errors.name && <p className="text-error text-sm mt-1" role="alert">{errors.name}</p>}
            </div>

            <div className="form-group">
              <label htmlFor="email">Email</label>
              <input
                type="email"
                id="email"
                name="email"
                value={formData.email}
                onChange={handleChange}
                className={errors.email ? 'border-error' : ''}
                placeholder="your@email.com"
                required
                disabled={submitting}
              />
              {errors.email && <p className="text-error text-sm mt-1" role="alert">{errors.email}</p>}
            </div>

            <div className="form-group">
              <label htmlFor="subject">Subject</label>
              <input
                type="text"
                id="subject"
                name="subject"
                value={formData.subject}
                onChange={handleChange}
                className={errors.subject ? 'border-error' : ''}
                placeholder="What's this about?"
                required
                disabled={submitting}
              />
              {errors.subject && <p className="text-error text-sm mt-1" role="alert">{errors.subject}</p>}
            </div>

            <div className="form-group">
              <label htmlFor="message">Message</label>
              <textarea
                id="message"
                name="message"
                value={formData.message}
                onChange={handleChange}
                rows={6}
                className={errors.message ? 'border-error' : ''}
                placeholder="Your message..."
                required
                disabled={submitting}
              />
              {errors.message && <p className="text-error text-sm mt-1" role="alert">{errors.message}</p>}
            </div>

            <button
              type="submit"
              className="btn btn-primary w-full md:w-auto mt-4"
              disabled={submitting}
            >
              {submitting ? 'Sending...' : 'Send Message'}
            </button>
          </form>

          <div className="mt-10 card bg-bg-elevated/50">
            <h3 className="font-bold mb-4">Other Ways to Connect</h3>
            <dl className="space-y-3 text-sm">
              <div className="flex gap-4">
                <dt className="text-secondary min-w-[100px]">GitHub</dt>
                <dd><a href="https://github.com" target="_blank" rel="noopener noreferrer" className="text-accent-amber hover:underline">github.com/astronomy-odyssey</a></dd>
              </div>
              <div className="flex gap-4">
                <dt className="text-secondary min-w-[100px]">Email</dt>
                <dd><a href="mailto:hello@astronomyodyssey.edu" className="text-accent-amber hover:underline">hello@astronomyodyssey.edu</a></dd>
              </div>
              <div className="flex gap-4">
                <dt className="text-secondary min-w-[100px]">Course</dt>
                <dd><span className="text-muted">ELEC026.23 Machine Learning Final Project</span></dd>
              </div>
            </dl>
          </div>
        </div>
      </section>
    </div>
  );
}