# WP Passkeys

A WordPress plugin that implements WebAuthn/Passkeys authentication for WordPress sites. Login without username and password - the most secure way to login to your WordPress site.

## Overview

WP Passkeys allows users to register and authenticate using passkeys instead of traditional username and password combinations. Passkeys are based on the WebAuthn standard, which provides a more secure and convenient authentication method that is resistant to phishing and credential theft.

## Features

- **Passkey Authentication**: Allow users to log in using passkeys (biometrics, security keys, etc.)
- **Passkey Registration**: Enable users to register passkeys during account creation or in their profile
- **Device Management**: Users can manage their registered passkeys in their profile
- **Admin Settings**: Configure passkey behavior and settings
- **Fallback Authentication**: Traditional login still available when needed
- **Security Tracking**: Track passkey usage and devices for security monitoring

## Requirements

- WordPress 5.9+
- PHP 8.2+
- HTTPS enabled (WebAuthn requires a secure context)
- Modern browser with WebAuthn support

## Installation

### From Source

1. Clone the repository:
   ```
   git clone https://github.com/your-username/wp-passkeys.git
   ```

2. Install dependencies:
   ```
   composer install
   yarn install
   ```

3. Build assets:
   ```
   yarn build
   ```

4. Upload the plugin to your WordPress site or install it directly in the plugins directory.

5. Activate the plugin through the WordPress admin interface.

### Configuration

1. Go to Settings > WP Passkeys in your WordPress admin.
2. Configure the plugin settings according to your needs.
3. Save changes.

## Usage

### For Users

1. **Registration**: During registration, users will be prompted to create a passkey.
2. **Login**: On the login page, users can click "Login with Passkey" to authenticate.
3. **Managing Passkeys**: Users can manage their passkeys in their WordPress profile.

### For Administrators

1. Configure plugin settings in Settings > WP Passkeys.
2. Monitor passkey usage and security in the admin dashboard.
3. Assist users with passkey management if needed.

## Development

### Local Development Environment

The project includes Docker configuration for local development:

1. Start the Docker environment:
   ```
   docker-compose up -d
   ```

2. Install dependencies:
   ```
   composer install
   yarn install
   ```

3. Watch for changes during development:
   ```
   yarn watch
   ```

### Project Structure

- `includes/`: PHP classes
  - `PasskeysPlugin.php`: Main plugin class
  - `ServiceProvider.php`: Dependency injection setup
  - `Ceremonies/`: WebAuthn registration and authentication
  - `Credentials/`: Credential management
  - `RestApi/`: REST API endpoints
  - `Admin/`: Admin settings
  - `Form/`: Login form modifications

- `assets/`: Frontend assets
  - `js/`: JavaScript files
    - `authentication/`: Authentication implementation
    - `registration/`: Registration implementation
    - `form/`: Form handling
    - `admin/`: Admin panel functionality
  - `css/`: Stylesheets

### Build Process

- JavaScript/TypeScript is bundled using Parcel
- SCSS is compiled to CSS using Parcel
- PHP follows PSR-4 autoloading

### Testing

Run PHP tests:
```
composer phpunit
```

Check PHP code quality:
```
composer phpcs
```

Check TypeScript:
```
yarn check
```

## REST API Endpoints

The plugin provides the following REST API endpoints:

- `GET /wp-json/wp-passkeys/register/options`: Get registration options
- `POST /wp-json/wp-passkeys/register/verify`: Verify registration
- `GET /wp-json/wp-passkeys/authenticator/options`: Get authentication options
- `POST /wp-json/wp-passkeys/authenticator/verify`: Verify authentication
- `GET /wp-json/wp-passkeys/creds/user`: Get user credentials
- `POST /wp-json/wp-passkeys/creds/user`: Set user credentials
- `DELETE /wp-json/wp-passkeys/creds/user/remove/{id}`: Remove user credential

## Technical Architecture

### Backend (PHP)
- PHP 8.2+ with PSR-4 autoloading
- League Container for dependency injection
- Symfony Serializer for data serialization
- Web-Auth/WebAuthn-Lib for WebAuthn implementation
- Custom database table for credential storage

### Frontend (JavaScript/TypeScript)
- TypeScript for type safety
- SimpleWebAuthn/Browser for client-side WebAuthn implementation
- Parcel for bundling
- Object-oriented approach with classes for Authentication and Registration

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-feature`
3. Commit your changes: `git commit -am 'Add new feature'`
4. Push to the branch: `git push origin feature/my-feature`
5. Submit a pull request

## License

This project is licensed under the GPL2 License - see the LICENSE file for details.

## Acknowledgments

- [Web-Auth/WebAuthn-Lib](https://github.com/web-auth/webauthn-framework) for the PHP WebAuthn implementation
- [SimpleWebAuthn](https://github.com/MasterKale/SimpleWebAuthn) for the JavaScript WebAuthn implementation 