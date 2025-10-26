import type { Config } from 'tailwindcss'

const config: Config = {
  content: [
    './pages/**/*.{js,ts,jsx,tsx,mdx}',
    './components/**/*.{js,ts,jsx,tsx,mdx}',
    './app/**/*.{js,ts,jsx,tsx,mdx}',
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: '#3498db',
          dark: '#2980b9',
          light: '#5dade2',
        },
        navy: {
          DEFAULT: '#1e3a52',
          light: '#2c5270',
          dark: '#152834',
        },
        gold: {
          DEFAULT: '#f39c12',
          light: '#f5b041',
          dark: '#d68910',
        },
        success: {
          DEFAULT: '#28a745',
          light: '#48c774',
          dark: '#1e7e34',
        },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
export default config
