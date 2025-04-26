<?php

namespace Drupal\ni_oidc\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\user\UserDataInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Service for managing OpenID Connect authentication with Next Identity.
 */
class AuthManager {

  /**
   * The Next Identity OIDC configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The session manager.
   *
   * @var \Drupal\Core\Session\SessionManagerInterface
   */
  protected $sessionManager;
  
  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * OIDC endpoints discovered from the provider.
   *
   * @var array
   */
  protected $endpoints = [];

  /**
   * Constructs a new AuthManager.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   *   The session manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ClientInterface $http_client,
    LoggerChannelFactoryInterface $logger_factory,
    AccountInterface $current_user,
    UserDataInterface $user_data,
    EntityTypeManagerInterface $entity_type_manager,
    SessionManagerInterface $session_manager,
    MessengerInterface $messenger
  ) {
    $this->config = $config_factory->get('ni_oidc.settings');
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory->get('ni_oidc');
    $this->currentUser = $current_user;
    $this->userData = $user_data;
    $this->entityTypeManager = $entity_type_manager;
    $this->sessionManager = $session_manager;
    $this->messenger = $messenger;
  }

  /**
   * Discovers OpenID Connect configuration from the provider.
   *
   * @return array
   *   The discovered OIDC endpoints.
   */
  public function discoverOidcConfig() {
    if (!empty($this->endpoints)) {
      return $this->endpoints;
    }

    $provider_url = $this->config->get('provider_url');
    if (empty($provider_url)) {
      $this->loggerFactory->error('Next Identity provider URL not configured');
      return [];
    }

    try {
      $well_known_url = $provider_url . '/.well-known/openid-configuration';
      $this->loggerFactory->notice('Attempting to discover OIDC configuration from: @url', ['@url' => $well_known_url]);
      
      $response = $this->httpClient->request('GET', $well_known_url);
      $this->endpoints = json_decode((string) $response->getBody(), TRUE);
      
      // Log the discovered endpoints for debugging
      $this->loggerFactory->notice('Successfully discovered OIDC configuration: @endpoints', [
        '@endpoints' => print_r($this->endpoints, TRUE)
      ]);
      
      // Override userinfo endpoint if configured
      $userinfo_endpoint = $this->config->get('userinfo_endpoint');
      if (!empty($userinfo_endpoint)) {
        $this->endpoints['userinfo_endpoint'] = $userinfo_endpoint;
      }
      
      return $this->endpoints;
    }
    catch (RequestException $e) {
      $error_message = $e->getMessage();
      $this->loggerFactory->error('Failed to discover OIDC configuration: @error', ['@error' => $error_message]);
      
      // More detailed error logging
      if ($e->hasResponse()) {
        $body = (string) $e->getResponse()->getBody();
        $status_code = $e->getResponse()->getStatusCode();
        $this->loggerFactory->error('Response status code: @code, body: @body', [
          '@code' => $status_code,
          '@body' => $body
        ]);
      } else {
        $this->loggerFactory->error('No response from server. This may indicate network connectivity issues or incorrect provider URL.');
      }
      
      // This is likely a configuration or connectivity issue
      $this->messenger->addError(t('Unable to connect to the Identity Provider. Please check your configuration and ensure the provider is accessible.'));
      
      return [];
    }
  }

  /**
   * Generates a random state parameter for CSRF protection.
   *
   * @return string
   *   A random string for the state parameter.
   */
  protected function generateState() {
    $state = bin2hex(random_bytes(16));
    $_SESSION['ni_oidc_state'] = $state;
    return $state;
  }

  /**
   * Validates the state parameter to prevent CSRF attacks.
   *
   * @param string $state
   *   The state parameter from the callback.
   *
   * @return bool
   *   TRUE if the state is valid, FALSE otherwise.
   */
  protected function validateState($state) {
    if (empty($_SESSION['ni_oidc_state']) || $state !== $_SESSION['ni_oidc_state']) {
      $this->loggerFactory->error('Invalid state parameter in OIDC callback');
      return FALSE;
    }
    
    // Clear the state from the session
    unset($_SESSION['ni_oidc_state']);
    
    return TRUE;
  }

  /**
   * Redirects the user to the Next Identity login page.
   *
   * @param array $options
   *   Additional options for the authorization request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function login(array $options = []) {
    return $this->authorize($options);
  }

  /**
   * Redirects the user to the Next Identity registration page.
   *
   * @param array $options
   *   Additional options for the authorization request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function register(array $options = []) {
    $options['action'] = 'register';
    return $this->authorize($options);
  }

  /**
   * Redirects the user to the Next Identity profile editing page.
   *
   * @param array $options
   *   Additional options for the authorization request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function editProfile(array $options = []) {
    $options['action'] = 'personal-details';
    return $this->authorize($options);
  }

  /**
   * Redirects the user to the Next Identity authorization endpoint.
   *
   * @param array $options
   *   Additional options for the authorization request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function authorize(array $options = []) {
    $endpoints = $this->discoverOidcConfig();
    if (empty($endpoints) || empty($endpoints['authorization_endpoint'])) {
      $this->messenger->addError(t('Failed to discover OIDC authorization endpoint.'));
      return new RedirectResponse('/');
    }

    $authorization_endpoint = $endpoints['authorization_endpoint'];
    
    // If an action is specified (register or personal-details), modify the endpoint
    if (!empty($options['action'])) {
      $base_url = $this->config->get('provider_url');
      $authorization_endpoint = $base_url . '/' . $options['action'];
    }
    
    $client_id = $this->config->get('client_id');
    $redirect_uri = $options['redirect_uri'] ?? $this->getCallbackUrl();
    $scopes = $options['scopes'] ?? $this->config->get('scopes');
    $state = $this->generateState();
    
    $query = [
      'client_id' => $client_id,
      'redirect_uri' => $redirect_uri,
      'response_type' => 'code',
      'scope' => $scopes,
      'state' => $state,
    ];
    
    // Add optional parameters if provided
    if (!empty($options['prompt'])) {
      $query['prompt'] = $options['prompt'];
    }
    
    if (!empty($options['max_age'])) {
      $query['max_age'] = $options['max_age'];
    }
    
    $auth_url = $authorization_endpoint . '?' . http_build_query($query);
    
    // Must use TrustedRedirectResponse for external URLs
    return new TrustedRedirectResponse($auth_url);
  }

  /**
   * Handles the callback from the Next Identity provider.
   *
   * @param string $code
   *   The authorization code.
   * @param string $state
   *   The state parameter.
   *
   * @return array|false
   *   The token response if successful, FALSE otherwise.
   */
  public function handleCallback($code, $state) {
    if (!$this->validateState($state)) {
      $this->messenger->addError(t('Invalid state parameter. Authentication failed.'));
      return FALSE;
    }
    
    $endpoints = $this->discoverOidcConfig();
    if (empty($endpoints) || empty($endpoints['token_endpoint'])) {
      $this->messenger->addError(t('Failed to discover OIDC token endpoint.'));
      return FALSE;
    }

    // Exchange the authorization code for tokens
    $client_id = $this->config->get('client_id');
    $client_secret = $this->config->get('client_secret');
    $redirect_uri = $this->getCallbackUrl();
    
    try {
      $response = $this->httpClient->request('POST', $endpoints['token_endpoint'], [
        'form_params' => [
          'grant_type' => 'authorization_code',
          'code' => $code,
          'client_id' => $client_id,
          'client_secret' => $client_secret,
          'redirect_uri' => $redirect_uri,
        ],
      ]);
      
      $tokens = json_decode((string) $response->getBody(), TRUE);
      if (empty($tokens) || empty($tokens['access_token'])) {
        $this->loggerFactory->error('Invalid token response from OIDC provider');
        return FALSE;
      }
      
      // Store tokens in session
      $_SESSION['ni_oidc_access_token'] = $tokens['access_token'];
      $_SESSION['ni_oidc_id_token'] = $tokens['id_token'] ?? NULL;
      if (!empty($tokens['refresh_token'])) {
        $_SESSION['ni_oidc_refresh_token'] = $tokens['refresh_token'];
      }
      
      // Fetch user info using the access token
      $user_info = $this->getUserInfo($tokens['access_token']);
      if ($user_info) {
        $_SESSION['ni_oidc_userinfo'] = $user_info;
        return [
          'tokens' => $tokens,
          'userinfo' => $user_info,
        ];
      }
      
      return $tokens;
    }
    catch (RequestException $e) {
      $this->loggerFactory->error('Failed to exchange code for tokens: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * Fetches user information from the userinfo endpoint.
   *
   * @param string $access_token
   *   The access token to use for the request.
   *
   * @return array|false
   *   The user info if successful, FALSE otherwise.
   */
  public function getUserInfo($access_token) {
    $endpoints = $this->discoverOidcConfig();
    if (empty($endpoints) || empty($endpoints['userinfo_endpoint'])) {
      $this->loggerFactory->error('Failed to discover OIDC userinfo endpoint');
      return FALSE;
    }

    try {
      $response = $this->httpClient->request('GET', $endpoints['userinfo_endpoint'], [
        'headers' => [
          'Authorization' => 'Bearer ' . $access_token,
        ],
      ]);
      
      $user_info = json_decode((string) $response->getBody(), TRUE);
      if (empty($user_info) || empty($user_info['sub'])) {
        $this->loggerFactory->error('Invalid user info response from OIDC provider');
        return FALSE;
      }
      
      return $user_info;
    }
    catch (RequestException $e) {
      $this->loggerFactory->error('Failed to fetch user info: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * Logs the user out from Next Identity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function logout() {
    $endpoints = $this->discoverOidcConfig();
    $id_token = $_SESSION['ni_oidc_id_token'] ?? NULL;
    
    // Clear session data
    unset($_SESSION['ni_oidc_access_token']);
    unset($_SESSION['ni_oidc_id_token']);
    unset($_SESSION['ni_oidc_refresh_token']);
    unset($_SESSION['ni_oidc_userinfo']);
    
    // If end_session_endpoint is available and we have an ID token, redirect to it
    if (!empty($endpoints['end_session_endpoint']) && !empty($id_token)) {
      $logout_url = $endpoints['end_session_endpoint'] . '?';
      $params = ['id_token_hint' => $id_token];
      
      // Add post_logout_redirect_uri if desired
      // $params['post_logout_redirect_uri'] = $base_url;
      
      $logout_url .= http_build_query($params);
      // Must use TrustedRedirectResponse for external URLs
      return new TrustedRedirectResponse($logout_url);
    }
    
    // Otherwise, just redirect to the homepage
    return new RedirectResponse('/');
  }

  /**
   * Gets the callback URL for the OIDC flow.
   *
   * @return string
   *   The callback URL.
   */
  public function getCallbackUrl() {
    global $base_url;
    
    // Get the current language prefix if language module is enabled
    $language_prefix = '';
    if (\Drupal::moduleHandler()->moduleExists('language')) {
      $language_manager = \Drupal::languageManager();
      $current_language = $language_manager->getCurrentLanguage()->getId();
      
      // Only add prefix for non-default languages if using path prefix
      $config = \Drupal::config('language.negotiation');
      $prefixes = $config->get('url.prefixes');
      
      if (!empty($prefixes[$current_language])) {
        $language_prefix = '/' . $prefixes[$current_language];
      }
    }
    
    // Log the callback URL we're generating
    $callback_url = $base_url . $language_prefix . '/ni-oidc/callback';
    $this->loggerFactory->notice('Generated callback URL: @url', ['@url' => $callback_url]);
    
    return $callback_url;
  }

  /**
   * Gets the current access token if available.
   *
   * @return string|null
   *   The access token if available, NULL otherwise.
   */
  public function getAccessToken() {
    return $_SESSION['ni_oidc_access_token'] ?? NULL;
  }

  /**
   * Gets the current ID token if available.
   *
   * @return string|null
   *   The ID token if available, NULL otherwise.
   */
  public function getIdToken() {
    return $_SESSION['ni_oidc_id_token'] ?? NULL;
  }

  /**
   * Gets the current refresh token if available.
   *
   * @return string|null
   *   The refresh token if available, NULL otherwise.
   */
  public function getRefreshToken() {
    return $_SESSION['ni_oidc_refresh_token'] ?? NULL;
  }

  /**
   * Gets the current user info if available.
   *
   * @return array|null
   *   The user info if available, NULL otherwise.
   */
  public function getUserInfoFromSession() {
    return $_SESSION['ni_oidc_userinfo'] ?? NULL;
  }

  /**
   * Checks whether the user has a valid access token.
   *
   * @return bool
   *   TRUE if the user has a valid access token, FALSE otherwise.
   */
  public function hasValidAccessToken() {
    // Simply check if there's an access token in the session.
    // In a production environment, you might want to validate the token
    // or check its expiration.
    return !empty($_SESSION['ni_oidc_access_token']);
  }
} 