module.exports = {
  content: [
    "./app/**/*.php",
    "./includes/**/*.php",
  ],
  theme: {
    extend: {
      fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
      colors: {
        primary: { DEFAULT: '#0056B3', dark: '#00448f', darker: '#00337a', light: '#cce0f5' },
        ink: { 900: '#0F172A', 700: '#334155', 500: '#64748B', 400: '#94A3B8' },
        canvas: '#F8FAFC',
        line: { DEFAULT: '#E2E8F0', soft: '#F1F5F9', mid: '#CBD5E1' },
        accent: '#10B981',
        info: '#3B82F6'
      },
      boxShadow: {
        card: '0 1px 2px 0 rgba(15,23,42,.05)',
        drop: '0 4px 6px -1px rgba(15,23,42,.08), 0 2px 4px -2px rgba(15,23,42,.04)',
        modal: '0 10px 15px -3px rgba(15,23,42,.10), 0 4px 6px -4px rgba(15,23,42,.05)'
      }
    }
  },
  plugins: []
}
