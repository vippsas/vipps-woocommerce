import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  build: {
    rollupOptions: {
      output: {
        dir: '../../../admin/settings/dist',
        // Necessary to have a consistent output path, so it's easy to reference it when we call wp_enqueue_script().
        entryFileNames: 'plugin.js',
        // Necessary to have a consistent output path, so it's easy to reference it when we call wp_enqueue_style().
        assetFileNames: 'plugin.css',
        chunkFileNames: 'chunk.js',
        manualChunks: undefined
      }
    }
  }
});
