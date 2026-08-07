import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50: '#eef4ff',
                    100: '#dbe6fe',
                    200: '#bfd3fe',
                    300: '#93b4fd',
                    400: '#608cfa',
                    500: '#3b63f6',
                    600: '#2544eb',
                    700: '#1d33d8',
                    800: '#1e2caf',
                    900: '#1e2b8a',
                    950: '#171d54',
                },
                ink: {
                    50: '#f6f7f9',
                    100: '#eceef2',
                    200: '#d5dae3',
                    300: '#b0b9ca',
                    400: '#8593ac',
                    500: '#657593',
                    600: '#505e79',
                    700: '#414c62',
                    800: '#384153',
                    900: '#0f1523',
                    950: '#080c16',
                },
            },
            boxShadow: {
                glow: '0 0 60px -15px rgba(59, 99, 246, 0.5)',
                card: '0 1px 2px rgba(16,24,40,.04), 0 4px 16px -2px rgba(16,24,40,.08)',
            },
            keyframes: {
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(12px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-10px)' },
                },
            },
            animation: {
                'fade-up': 'fade-up .6s ease-out both',
                float: 'float 6s ease-in-out infinite',
            },
        },
    },

    plugins: [forms],
};
