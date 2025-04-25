# Next Identity Drupal Module: OpenID Connect

## Overview

The Next Identity Drupal module provides seamless integration with Next Identity authentication services via OpenID Connect (OIDC) protocol. This module allows your Drupal site to delegate authentication to Next Identity, a powerful Customer Identity and Access Management (CIAM) provider. Next Identity provides no-code CIAM orchestration and passwordless options such as Passkeys. NI can also orchestrate authentication between any additional auth provider, allowing you to have a fully unified login capability for your site or suite of sites. 

## Features

- Simple configuration interface with provider URL, client credentials, and scope settings
- Automatic display of callback URL for easy configuration on the Next Identity side
- Support for login, registration, and profile editing redirects
- User account creation and synchronization with Next Identity profiles
- Customizable login and registration buttons
- Drupal role assignment for users authenticated through Next Identity
- Clean logout process with proper session termination

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

If you encounter issues with the module:

1. **Check Configuration**: Ensure that the Provider URL, Client ID, and Client Secret are correct.
2. **Verify Callback URL**: Confirm that the callback URL is properly registered in your Next Identity application settings.
3. **Enable Logging**: Check the Drupal logs for any error messages related to the OIDC authentication process.
4. **Check Scopes**: Ensure that the requested scopes are allowed for your Next Identity application.

## Credits

This module integrates with Next Identity using the OpenID Connect protocol. It is inspired by other Drupal authentication modules and the Next Identity OIDC libraries.

## License

This project is licensed under the GPL v2 or later.
