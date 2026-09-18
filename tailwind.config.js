import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './app/Livewire/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Plus Jakarta Sans', 'Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Tema: Teal–Emerald segar
                brand: {
                    50:  '#f0fdfa',
                    100: '#ccfbf1',
                    200: '#99f6e4',
                    300: '#5eead4',
                    400: '#2dd4bf',
                    500: '#14b8a6',
                    600: '#0d9488',
                    700: '#0f766e',
                    800: '#115e59',
                    900: '#134e4a',
                    950: '#042f2e',
                },
                // Aksen pendamping (emerald) untuk gradien & highlight
                accent: {
                    50:  '#ecfdf5',
                    100: '#d1fae5',
                    200: '#a7f3d0',
                    300: '#6ee7b7',
                    400: '#34d399',
                    500: '#10b981',
                    600: '#059669',
                    700: '#047857',
                    800: '#065f46',
                    900: '#064e3b',
                },
                ink: {
                    900: '#0f172a',
                    800: '#1e293b',
                    700: '#334155',
                    600: '#475569',
                    500: '#64748b',
                },
            },
            boxShadow: {
                soft: '0 4px 24px -8px rgba(13, 148, 136, 0.12)',
                card: '0 1px 2px rgba(15,23,42,0.04), 0 8px 24px -12px rgba(15,23,42,0.10)',
                ring: '0 0 0 4px rgba(20, 184, 166, 0.15)',
                glow: '0 8px 30px -6px rgba(13, 148, 136, 0.35)',
            },
            backgroundImage: {
                'brand-gradient': 'linear-gradient(135deg, #14b8a6 0%, #0d9488 50%, #059669 100%)',
                'brand-mesh': 'radial-gradient(at 15% 20%, rgba(20,184,166,0.18) 0, transparent 45%), radial-gradient(at 85% 25%, rgba(16,185,129,0.14) 0, transparent 50%), radial-gradient(at 50% 100%, rgba(56,189,248,0.10) 0, transparent 55%)',
            },
            keyframes: {
                fadeInUp: {
                    '0%':   { opacity: '0', transform: 'translateY(8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                shimmer: {
                    '0%':   { backgroundPosition: '-200% 0' },
                    '100%': { backgroundPosition: '200% 0' },
                },
                float: {
                    '0%,100%': { transform: 'translateY(0)' },
                    '50%':     { transform: 'translateY(-6px)' },
                },
            },
            animation: {
                'fade-in-up': 'fadeInUp .4s ease-out both',
                shimmer: 'shimmer 1.5s linear infinite',
                float: 'float 6s ease-in-out infinite',
            },
        },
    },
    plugins: [forms, typography],
};
