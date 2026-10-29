import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // The user's Appearance setting puts "dark" on <html>; see layouts/app.
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            // The admin pages' design system (Mobilise). The personal app keeps its own look.
            colors: {
                brand: {
                    navy: '#002e62', // primary: sidebar, headings, primary buttons, links, chart bars
                    cyan: '#00a7cb', // accent: fills, current-month bar, text on navy only
                    tint: '#f0fafc', // chip and pill backgrounds, "Low" badge
                },
                ink: {
                    DEFAULT: '#1f2937', // body text
                    muted: '#4b5563',   // secondary text, captions, table headers, axis labels
                },
                surface: {
                    page: '#f2f4f7',  // page background, tracks, row dividers
                    card: '#ffffff',
                    line: '#e5e7eb',  // table header border
                    field: '#d1d5db', // input, segmented-control and info-strip borders
                },
                chart: { 3: '#6b7280', 4: '#9ca3af' }, // neutral series; #9ca3af is also the baseline
                status: {
                    success: '#16a34a',     // dots and fills only
                    successText: '#15803d', // green text
                    warning: '#f59e0b',     // fills only, with black text
                    error: '#dc2626',
                },
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Poppins', 'Inter', 'system-ui', 'sans-serif'], // headings and big numbers
                inter: ['Inter', 'system-ui', 'sans-serif'],              // admin body and UI text
                mono: ['"JetBrains Mono"', 'Menlo', 'monospace'],        // IDs like "user #4821"
            },
            borderRadius: {
                chip: '8px',
                card: '12px',
            },
        },
    },

    plugins: [forms],
};
