import { defineConfig } from 'vite';
import { resolve } from 'path';
import copy from 'rollup-plugin-copy';
import fs from 'fs';
import path from 'path';

// Define entry points
const entryPoints = {
  'js/form/index': resolve(__dirname, 'assets/js/form/index.ts'),
  'js/registration/index': resolve(__dirname, 'assets/js/registration/index.ts'),
  'js/authentication/index': resolve(__dirname, 'assets/js/authentication/index.ts'),
  'js/admin/index': resolve(__dirname, 'assets/js/admin/index.ts'),
  'css/default-login': resolve(__dirname, 'assets/css/default-login.scss'),
  'css/plugin-settings': resolve(__dirname, 'assets/css/plugin-settings.scss'),
};

// Load environment variables
const { VITE_WP_PLUGIN_PATH, VITE_DEV_SERVER_PORT, VITE_SITE_URL } = process.env;

// Get the site URL from environment variable or use a default
// This should match your Local by Flywheel site URL
const siteUrl = VITE_SITE_URL || 'https://webauth.local';

export default defineConfig(({ mode }) => {
  const isProduction = mode === 'production';
  
  return {
    base: VITE_WP_PLUGIN_PATH ? `${VITE_WP_PLUGIN_PATH}dist/` : '/wp-content/plugins/wp-passkeys/dist/',
    build: {
      outDir: 'dist',
      emptyOutDir: true,
      manifest: true,
      minify: isProduction ? 'terser' : false,
      sourcemap: !isProduction,
      cssCodeSplit: true,
      rollupOptions: {
        input: entryPoints,
        output: {
          entryFileNames: '[name].js',
          chunkFileNames: 'chunks/[name]-[hash].js',
          assetFileNames: '[name].[ext]',
          format: 'es',
        },
        plugins: [
          // Copy static assets
          copy({
            targets: [
              { src: 'assets/img/*', dest: 'dist/img' }
            ],
            hook: 'writeBundle'
          })
        ]
      },
      terserOptions: {
        compress: {
          drop_console: isProduction,
          drop_debugger: isProduction,
        },
      },
    },
    resolve: {
      alias: {
        '@types': resolve(__dirname, 'assets/js/types'),
      },
    },
    css: {
      devSourcemap: true,
      preprocessorOptions: {
        scss: {
          quietDeps: true, // Suppress Sass deprecation warnings from dependencies
        },
      },
    },
    server: {
      // Configure dev server
      port: VITE_DEV_SERVER_PORT ? parseInt(VITE_DEV_SERVER_PORT, 10) : 3000,
      strictPort: true,
      // Use proxy to handle HTTPS through Local by Flywheel
      proxy: {
        // Proxy all requests to the Local by Flywheel site
        '/': {
          target: siteUrl,
          changeOrigin: true,
          secure: false,
        }
      },
      hmr: {
        host: 'localhost',
        port: VITE_DEV_SERVER_PORT ? parseInt(VITE_DEV_SERVER_PORT, 10) : 3000,
      },
    },
    optimizeDeps: {
      include: ['@simplewebauthn/browser'],
    },
  };
}); 