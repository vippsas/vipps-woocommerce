import { defineConfig, esmExternalRequirePlugin } from 'vite';
import react from '@vitejs/plugin-react';

// Match the payment settings build: a standalone IIFE using WordPress's React.
const external = ['react', 'react-dom', 'react-dom/client', 'react/jsx-runtime'];
export default defineConfig({
  plugins: [react({ jsxRuntime: 'classic' }), esmExternalRequirePlugin({ external })],
  build: {
    outDir: '../../../admin/settings/dist',
    cssCodeSplit: false,
    rolldownOptions: {
      external,
      output: {
        format: 'iife',
        globals: {
          react: 'wp.element',
          'react-dom': 'wp.element',
          'react-dom/client': 'wp.element',
          'react/jsx-runtime': 'wp.element',
        },
        // Stable names allow PHP to enqueue both the script and extracted CSS.
        entryFileNames: 'plugin.js',
        assetFileNames: 'plugin[extname]',
        chunkFileNames: 'chunk.js',
        manualChunks: undefined,
      },
    },
  },
});
