import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.jsx',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Bricolage Grotesque', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                'bg-deep': 'rgb(var(--color-bg-deep-rgb) / <alpha-value>)',
                'bg-card': 'rgb(var(--color-bg-card-rgb) / <alpha-value>)',
                'bg-elevated': 'rgb(var(--color-bg-elevated-rgb) / <alpha-value>)',
                'accent-amber': 'rgb(var(--color-accent-amber-rgb) / <alpha-value>)',
                'accent-teal': 'rgb(var(--color-accent-teal-rgb) / <alpha-value>)',
                'accent-coral': 'rgb(var(--color-accent-coral-rgb) / <alpha-value>)',
                primary: 'rgb(var(--color-text-primary-rgb) / <alpha-value>)',
                secondary: 'rgb(var(--color-text-secondary-rgb) / <alpha-value>)',
                muted: 'rgb(var(--color-text-muted-rgb) / <alpha-value>)',
                border: 'rgb(var(--color-border-rgb) / <alpha-value>)',
                error: 'rgb(var(--color-accent-coral-rgb) / <alpha-value>)',
            },
        },
    },
    plugins: [],
};
