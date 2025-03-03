# WP Passkeys - Vite Build System

This plugin now uses Vite as its build system, replacing the previous Parcel setup. Vite provides faster builds, better development experience, and improved optimization for production.

## Build Commands

The following npm/yarn commands are available:

- `yarn dev` - Start the development server (proxies to Local by Flywheel)
- `yarn build` - Build for development (includes sourcemaps)
- `yarn build:prod` - Build for production (minified, no sourcemaps)
- `yarn preview` - Preview the production build locally
- `yarn check` - Run TypeScript type checking
- `yarn lint` - Run ESLint on TypeScript files
- `yarn lint:fix` - Run ESLint and fix issues automatically
- `yarn clean` - Remove build artifacts and cache

## Development Workflow

1. Install dependencies:
   ```
   yarn install
   ```

2. Start the development server:
   ```
   yarn dev
   ```

3. Build for production:
   ```
   yarn build:prod
   ```

## SSL with Local by Flywheel

This project is configured to work with Local by Flywheel's built-in SSL support. Local automatically handles SSL certificates for your local development environment, so no additional certificate configuration is needed.

The Vite development server is configured to proxy requests to your Local by Flywheel site, allowing you to use the HTTPS setup provided by Local.

### Local by Flywheel SSL Configuration

Local by Flywheel provides a one-click solution for creating and trusting SSL certificates:

1. In the Local app, select your site
2. Click on the "SSL" tab
3. Toggle "Enable SSL" to on
4. Your site will be accessible via HTTPS

For more information, see the [Local by Flywheel SSL documentation](https://localwp.com/help-docs/getting-started/ssl-in-local/).

## Configuration Files

- `vite.config.js` - Main Vite configuration
- `.env` - Environment variables for Vite
- `tsconfig.json` - TypeScript configuration

## Environment Variables

You can customize the build by setting the following environment variables in `.env`:

- `VITE_WP_PLUGIN_PATH` - The path to the plugin in WordPress (default: `/wp-content/plugins/wp-passkeys/`)
- `VITE_DEV_SERVER_PORT` - The port for the development server (default: `3000`)
- `VITE_SITE_URL` - The URL of your Local by Flywheel site (default: `https://webauth.local`)

## Asset Structure

The build system processes the following assets:

- JavaScript entry points:
  - `assets/js/form/index.ts`
  - `assets/js/registration/index.ts`
  - `assets/js/authentication/index.ts`
  - `assets/js/admin/index.ts`

- CSS entry points:
  - `assets/css/default-login.scss`
  - `assets/css/plugin-settings.scss`

- Static assets:
  - `assets/img/*` - Copied to `dist/img/`

## Output Structure

The build output is in the `dist` directory with the following structure:

- `js/` - JavaScript files
- `css/` - CSS files
- `chunks/` - Shared JavaScript chunks
- `img/` - Static images
- `.vite/manifest.json` - Asset manifest for WordPress

## Browser Support

The build system targets modern browsers:

- `> 1%` - Browsers with more than 1% global usage
- `last 2 versions` - The last 2 versions of each browser
- `not dead` - Browsers that are still maintained
- `not ie 11` - Excludes Internet Explorer 11

## Troubleshooting

If you encounter issues with the build:

1. Try cleaning the cache:
   ```
   yarn clean
   ```

2. Ensure all dependencies are installed:
   ```
   yarn install
   ```

3. Check for TypeScript errors:
   ```
   yarn check
   ```

4. Proxy/SSL Issues:
   - Make sure your Local by Flywheel site has SSL enabled
   - Check that the site URL in `vite.config.js` matches your Local by Flywheel site URL
   - If you're experiencing CORS issues, try adjusting the proxy settings in `vite.config.js`

## WordPress Integration

The plugin uses WordPress's script enqueuing system with the following considerations:

1. **ES Modules**: All JavaScript files are built as ES modules and are loaded with `type="module"` attribute.
   - Scripts are enqueued with `wp_enqueue_script()`
   - The `script_loader_tag` filter is used to add `type="module"` to the script tags
   - This approach is more reliable than using `wp_script_add_data()` which can be inconsistent

2. **Asset Manifest**: The build generates a manifest file that maps source files to their hashed output files.
   - The `EnqueueAssets` class reads this manifest to load the correct file versions

3. **Dependencies**: Scripts maintain proper dependencies between components.
   - The form script is the base dependency for other scripts
   - WordPress core dependencies like `wp-api-fetch` are properly included

4. **Script Localization**: Data is passed to scripts using `wp_localize_script()` for REST API endpoints and nonces.

The `EnqueueAssets` class handles loading all scripts and styles with the appropriate attributes and dependencies. 