import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50:  '#eef5ff',
                    100: '#d9e8ff',
                    200: '#bcd5ff',
                    300: '#8db8ff',
                    400: '#5790ff',
                    500: '#3066ff',
                    600: '#1f47f5',
                    700: '#1934dd',
                    800: '#1c2eb2',
                    900: '#1d2e8b',
                    950: '#161d54',
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
                soft: '0 4px 24px -8px rgba(15, 23, 42, 0.08)',
                ring: '0 0 0 4px rgba(48, 102, 255, 0.15)',
            },
        },
    },
    plugins: [forms, typography],
};
