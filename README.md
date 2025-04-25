![Next Identity Logo](images/Next_Identity_Logo_White.svg)
# 

# Next Identity Drupal Module: OpenID Connect

## Overview

The Next Identity Drupal module provides seamless integration with Next Identity authentication services via OpenID Connect (OIDC) protocol. This module allows your Drupal site to delegate authentication to Next Identity, a powerful Customer Identity and Access Management (CIAM) provider. Next Identity provides no-code CIAM orchestration and passwordless options such as Passkeys. NI can also orchestrate authentication between any additional auth provider, allowing you to have a fully unified login capability for your site or suite of sites. 

## Features

- Simple configuration interface with provider URL, client credentials, and scope settings
- Automatic display of callback URL for easy configuration on the Next Identity side
- Support for login, registration, and profile editing redirects
- User account creation and synchronization with Next Identity profiles
- Customizable login and registration buttons with Next Identity branding
- Drupal role assignment for users authenticated through Next Identity
- Clean logout process with proper session termination
- Option to use Next Identity favicon for your Drupal site
- Blocks to easily place Next Identity login/register buttons anywhere on your site

## Requirements

- Drupal 9 or 10
- PHP 7.4 or higher
- A Next Identity account with access to create OIDC applications

## Installation

1. Download and install this module using composer:
   ```
   composer require drupal/ni_oidc
   ```

2. Enable the module through the Drupal admin UI or using Drush:
   ```
   drush en ni_oidc
   ```

3. Configure the module at `/admin/config/people/ni-oidc`

## Configuration

1. **Provider Settings**
   - **Provider URL**: Enter the base URL of your Next Identity provider (e.g., https://auth.nextidentity.com)
   - **Client ID**: Enter the client ID provided by Next Identity
   - **Client Secret**: Enter the client secret provided by Next Identity
   - **Scopes**: Specify the required OAuth scopes (default: `openid profile email`)
   - **Callback URL**: Copy this URL and register it as an authorized redirect URI in your Next Identity application settings

2. **User Account Settings**
   - **Auto-register users**: Enable to automatically create user accounts for new Next Identity users
   - **Roles to assign**: Select which roles should be assigned to users authenticated through Next Identity

3. **Button Settings**
   - **Login Button Text**: Customize the text displayed on the login button
   - **Register Button Text**: Customize the text displayed on the register button
   - **Preview**: See how your buttons will look with the Next Identity branding

4. **Advanced Settings**
   - **User Info Endpoint**: Optional override for the user info endpoint
   - **Use Next Identity favicon**: Option to replace your site's favicon with the Next Identity favicon

## Usage

### Adding Login and Registration Buttons

You can add login and registration buttons to your Drupal site using the provided theme functions:

```php
// Login button
$login_url = Url::fromRoute('ni_oidc.authorize')->toString();
$login_button = [
  '#theme' => 'ni_oidc_login_button',
  '#url' => $login_url,
  '#text' => t('Log in with Next Identity'),
];

// Registration button
$register_url = Url::fromRoute('ni_oidc.register')->toString();
$register_button = [
  '#theme' => 'ni_oidc_register_button',
  '#url' => $register_url,
  '#text' => t('Register with Next Identity'),
];
```

### Using the Next Identity Login Block

The module provides a block that can be placed in any region of your site:

1. Go to Structure > Block layout
2. Click "Place block" in your desired region
3. Find "Next Identity Login" in the list and add it
4. Configure block settings as needed and save

The block will automatically display login and registration buttons to anonymous users, and will be hidden for authenticated users.

### Programmatic Authentication

You can also initiate authentication programmatically:

```php
// Login
$auth_manager = \Drupal::service('ni_oidc.auth_manager');
$redirect = $auth_manager->login();

// Registration
$redirect = $auth_manager->register();

// Profile editing
$redirect = $auth_manager->editProfile();
```

### Accessing User Information

Once a user is authenticated, you can access their Next Identity profile information:

```php
$auth_manager = \Drupal::service('ni_oidc.auth_manager');
$user_info = $auth_manager->getUserInfoFromSession();

if ($user_info) {
  $sub = $user_info['sub']; // Next Identity subject ID
  $email = $user_info['email'];
  $name = $user_info['name'];
  // ...
}
```

## Troubleshooting

### Redirect Error (/ni-oidc/authorize path returns error)

If clicking the login or register buttons leads to an error at `/ni-oidc/authorize` or `/en/ni-oidc/authorize`, this typically indicates that the module cannot connect to the Next Identity provider. Follow these steps to resolve the issue:

1. **Verify Provider Configuration**:
   - Ensure that the Provider URL is correct (e.g., `https://auth.nextidentity.com`) with no trailing slash
   - Verify your Client ID and Client Secret are correct
   - Enable Drupal logging and check for specific error messages related to OpenID Connect discovery

2. **OIDC Discovery Document Access**:
   - The module attempts to access the OIDC discovery document at `[provider_url]/.well-known/openid-configuration`
   - Verify this URL is accessible by visiting it directly in your browser
   - If you can't access it, contact your Next Identity provider to ensure the service is available

3. **Redirect URI Configuration**:
   - Ensure the Callback URL shown in the module settings is registered as an authorized redirect URI in your Next Identity application settings
   - The exact URL that appears in the "Callback URL" field must be registered without any modifications

4. **HTTPS Requirements**:
   - OIDC typically requires HTTPS for security. If your Drupal site is using HTTP, you may encounter issues
   - For production, ensure your site uses HTTPS
   - For development, you might need to adjust the Next Identity provider settings to allow non-HTTPS redirects

5. **Language Prefix Handling**:
   - If you're using language prefixes in URLs (e.g., `/en/ni-oidc/authorize`), ensure the Callback URL registered with Next Identity includes this prefix
   - You may need to register multiple callback URLs for each language prefix your site uses

6. **Check Network Connectivity**:
   - Ensure your server can connect to the Next Identity provider
   - Check for firewall rules or network restrictions that might block outgoing connections

7. **Debug with Drupal Logs**:
   - Enable Drupal logging and check for errors in Reports > Recent log messages
   - Look for entries from the 'ni_oidc' channel that might provide more specific error details

8. **Check for Timeout Issues**:
   - If the connection to the Next Identity provider is slow, it might time out
   - Adjust PHP timeout settings if necessary

### Other Common Issues

If you encounter other issues with the module:

1. **Check Configuration**: Ensure that the Provider URL, Client ID, and Client Secret are correct.
2. **Verify Callback URL**: Confirm that the callback URL is properly registered in your Next Identity application settings.
3. **Enable Logging**: Check the Drupal logs for any error messages related to the OIDC authentication process.
4. **Check Scopes**: Ensure that the requested scopes are allowed for your Next Identity application.
5. **Clear Caches**: Clear Drupal caches after making configuration changes.

## Credits

This module integrates with Next Identity using the OpenID Connect protocol. It is inspired by other Drupal authentication modules and the Next Identity OIDC libraries.

## License

This project is licensed under the GPL v2 or later.
