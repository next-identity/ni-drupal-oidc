<?php

namespace Drupal\ni_oidc\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\ni_oidc\Service\AuthManager;
use Drupal\ni_oidc\Service\UserMapper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for Next Identity OIDC authentication endpoints.
 */
class AuthController extends ControllerBase {

  /**
   * The Next Identity auth manager service.
   *
   * @var \Drupal\ni_oidc\Service\AuthManager
   */
  protected $authManager;

  /**
   * The user mapper service.
   *
   * @var \Drupal\ni_oidc\Service\UserMapper
   */
  protected $userMapper;

  /**
   * Constructs a new AuthController.
   *
   * @param \Drupal\ni_oidc\Service\AuthManager $auth_manager
   *   The authentication manager service.
   * @param \Drupal\ni_oidc\Service\UserMapper $user_mapper
   *   The user mapper service.
   */
  public function __construct(AuthManager $auth_manager, UserMapper $user_mapper) {
    $this->authManager = $auth_manager;
    $this->userMapper = $user_mapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ni_oidc.auth_manager'),
      $container->get('ni_oidc.user_mapper')
    );
  }

  /**
   * Redirects the user to the Next Identity authorization endpoint.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function authorize() {
    return $this->authManager->login();
  }

  /**
   * Redirects the user to the Next Identity registration page.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function register() {
    return $this->authManager->register();
  }

  /**
   * Redirects the user to the Next Identity profile editing page.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function editProfile() {
    return $this->authManager->editProfile();
  }

  /**
   * Handles the callback from the Next Identity provider.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function callback(Request $request) {
    $code = $request->query->get('code');
    $state = $request->query->get('state');
    $error = $request->query->get('error');
    $error_description = $request->query->get('error_description');

    // Check for errors in the callback
    if ($error) {
      $this->messenger()->addError($this->t('Authentication error: @error - @description', [
        '@error' => $error,
        '@description' => $error_description,
      ]));
      return new RedirectResponse(Url::fromRoute('<front>')->toString());
    }

    // Check for required parameters
    if (empty($code) || empty($state)) {
      $this->messenger()->addError($this->t('Invalid authentication response.'));
      return new RedirectResponse(Url::fromRoute('<front>')->toString());
    }

    // Process the callback
    $response = $this->authManager->handleCallback($code, $state);
    if (!$response || empty($response['userinfo'])) {
      $this->messenger()->addError($this->t('Failed to authenticate with Next Identity.'));
      return new RedirectResponse(Url::fromRoute('<front>')->toString());
    }

    // Find or create a Drupal user based on the Next Identity user info
    $user = $this->userMapper->findOrCreateUser($response['userinfo']);
    if (!$user) {
      $this->messenger()->addError($this->t('Unable to find or create a user account.'));
      return new RedirectResponse(Url::fromRoute('<front>')->toString());
    }

    // Log the user in
    user_login_finalize($user);
    $this->messenger()->addStatus($this->t('You have been logged in.'));

    // Redirect to the destination or user page
    $destination = $request->query->get('destination');
    if ($destination) {
      return new RedirectResponse($destination);
    }
    return new RedirectResponse(Url::fromRoute('user.page')->toString());
  }

  /**
   * Logs the user out.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function logout() {
    // Get the Next Identity logout URL before logging out from Drupal
    $redirect = $this->authManager->logout();
    
    // Log out from Drupal
    user_logout();
    
    // Return the redirect response from the auth manager
    return $redirect;
  }
} 