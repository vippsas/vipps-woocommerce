import { defineConfig, esmExternalRequirePlugin } from 'vite';
import react from '@vitejs/plugin-react';

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react({
      // Disable automatic runtime injection 
      jsxRuntime: 'classic', 
    }),
    esmExternalRequirePlugin({
      external: ['react', 'react-dom', 'react-dom/client', 'react/jsx-runtime'],
    }),


],
  build: {
    rolldownOptions: {
      external: ['react', 'react-dom', 'react-dom/client', 'react/jsx-runtime'],
      output: {
        format: "iife", // or "umd"
        globals: {      // Ensure we can load react from WP
          react: 'wp.element',
          'react-dom': 'wp.element',
          'react-dom/client': 'wp.element',
          'react-dom/jsx-runtime': 'wp.element',
        },
        dir: '../../../admin/settings/dist',
        // Necessary to have a consistent output path, so it's easy to reference it when we call wp_enqueue_script().
        entryFileNames: 'plugin.js',
        chunkFileNames: 'chunk.js',
        manualChunks: undefined
      },
    }
  }
});
