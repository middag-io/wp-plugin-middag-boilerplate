import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';

/**
 * Production build for WordPress: a single self-contained IIFE bundle.
 *
 * WordPress has no module loader and no global React/Inertia, so everything is
 * bundled into one file (inlineDynamicImports) emitted as assets/dist/app.js
 * plus the bundled stylesheet. The PHP InertiaAdapter enqueues these and mounts
 * on the '#{component}-app' div; the entry's createInertiaApp id must match
 * (middag-boilerplate-app).
 */
export default defineConfig({
  plugins: [react(), tailwindcss()],
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    outDir: '../assets/dist',
    emptyOutDir: true,
    cssCodeSplit: false,
    lib: {
      entry: 'src/entry-wordpress.tsx',
      formats: ['iife'],
      name: 'MiddagWpBoilerplate',
      fileName: () => 'app.js',
    },
    rollupOptions: {
      output: {
        // IIFE cannot code-split; a single bundle is required. Blocks are
        // registered selectively (no registerDefaults) so heavy Pro deps stay out.
        inlineDynamicImports: true,
        // Stable CSS name the PHP enqueue references (single bundled stylesheet,
        // fonts inlined — so this never collides with another asset).
        assetFileNames: 'style.css',
      },
    },
  },
});
