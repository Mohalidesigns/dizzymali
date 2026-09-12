import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/app.tsx'],
      ssr: 'resources/js/ssr.tsx',
      refresh: true,
    }),
    react(),
    tailwindcss(),
  ],
  build: {
    // The performance budget is 200 KB of initial JS gzipped, on a mid-range
    // Android over 3G. Splitting the vendor chunk keeps the app chunk small
    // enough that a content change does not re-download React.
    rollupOptions: {
      output: {
        manualChunks: {
          react: ['react', 'react-dom'],
          inertia: ['@inertiajs/react'],
        },
      },
    },
  },
})
