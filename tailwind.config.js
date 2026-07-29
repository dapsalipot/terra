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
