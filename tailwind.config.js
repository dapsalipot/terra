import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],

    theme: {
        extend: {
            colors: {
                sand: '#F1EBDD',
                canvas: '#FBF9F4',
                surface: '#FFFFFF',
                pine: '#2C3826',
                moss: '#5E6B44',
                ink: '#1F1C15',
                terra: '#BC4B26',
                amber: '#A8761F',
                // Text-safe siblings of amber and terra. On their own tints the brand tones read 3.2:1 and
                // 4.1:1, which is fine for a dot or an icon but not for words; these are the same hues
                // pulled toward ink until the smallest text clears 4.5:1 on canvas, sand and the tint.
                'amber-deep': '#7F5B1C',
                'terra-deep': '#9D4223',
                cream: '#F7F4EC',
            },
            fontFamily: {
                serif: ['Fraunces', 'Georgia', 'serif'],
                sans: ['"Avenir Next"', 'Avenir', '"Helvetica Neue"', 'Arial', 'sans-serif'],
                mono: ['ui-monospace', 'Menlo', 'monospace'],
            },
        },
    },

    plugins: [forms],
};
