import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react({
      // Disable automatic runtime injection 
      jsxRuntime: 'classic', 
    })],
  build: {
    rollupOptions: {
      external: ['react', 'react-dom'],
      output: {
        format: "iife", // or "umd"
        globals: {      // Ensure we can load react from WP
          react: 'wp.element',
          'react-dom': 'wp.element',
        },
        dir: '../../../admin/settings/dist',
        // Necessary to have a consistent output path, so it's easy to reference it when we call wp_enqueue_script().
        entryFileNames: 'plugin.js',
        chunkFileNames: 'chunk.js',
        manualChunks: undefined
      }
    }
  }
});
