import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  build: {
    rollupOptions: {
      output: {
        dir: 'dist',
        entryFileNames: 'plugin.js',
        assetFileNames: 'plugin.css',
        chunkFileNames: 'chunk.js',
        manualChunks: undefined
      }
    }
  }
});
