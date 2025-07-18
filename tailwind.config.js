// tailwind.config.js
module.exports = {
  content: [
    './resources/js/**/*.vue',
    './resources/css/tool.css',
  ],
  theme: {
    extend: {
      colors: {
        primary: '#6366f1',
        'primary-dark': '#4f46e5',
        secondary: '#6b7280',
        surface: '#ffffff',
        bg: '#f9fafb',
      },
      borderRadius: {
        xl: '1rem',
      },
      boxShadow: {
        card: '0 4px 16px rgba(0,0,0,0.08)',
      }
    }
  },
  plugins: [],
}
