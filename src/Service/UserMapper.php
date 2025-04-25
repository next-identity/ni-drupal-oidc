<?php

namespace Drupal\ni_oidc\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\Entity\User;

/**
 * Service for mapping Next Identity users to Drupal users.
 */
class UserMapper {

  /**
   * The Next Identity OIDC configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * Constructs a new UserMapper.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    UserDataInterface $user_data
  ) {
    $this->config = $config_factory->get('ni_oidc.settings');
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory->get('ni_oidc');
    $this->userData = $user_data;
  }

  /**
   * Finds or creates a Drupal user based on Next Identity user info.
   *
   * @param array $user_info
   *   The user info from Next Identity.
   *
   * @return \Drupal\user\UserInterface|bool
   *   The Drupal user entity if found or created, FALSE otherwise.
   */
  public function findOrCreateUser(array $user_info) {
    // First try to find the user by sub (subject) ID
    $user = $this->findUserBySub($user_info['sub']);
    if ($user) {
      // Update the user with the latest info
      $this->updateUser($user, $user_info);
      return $user;
    }

    // If auto-registration is enabled, we create a new user
    if ($this->config->get('auto_register')) {
      return $this->createUser($user_info);
    }

    $this->loggerFactory->notice('User @sub not found and auto-registration is disabled', [
      '@sub' => $user_info['sub'],
    ]);
    return FALSE;
  }

  /**
   * Finds a Drupal user by Next Identity subject ID.
   *
   * @param string $sub
   *   The subject ID from Next Identity.
   *
   * @return \Drupal\user\UserInterface|false
   *   The Drupal user entity if found, FALSE otherwise.
   */
  public function findUserBySub($sub) {
    $users = $this->entityTypeManager->getStorage('user')->loadByProperties([
      'ni_oidc_sub' => $sub,
    ]);
    
    if (!empty($users)) {
      return reset($users);
    }

    // If we couldn't find by custom field, try user data
    $uids = $this->userData->get('ni_oidc', NULL, 'sub_' . md5($sub));
    if (!empty($uids)) {
      foreach ($uids as $uid => $value) {
        if ($value === $sub) {
          $user = $this->entityTypeManager->getStorage('user')->load($uid);
          if ($user) {
            return $user;
          }
        }
      }
    }
    
    return FALSE;
  }

  /**
   * Creates a new Drupal user based on Next Identity user info.
   *
   * @param array $user_info
   *   The user info from Next Identity.
   *
   * @return \Drupal\user\UserInterface|false
   *   The created Drupal user entity if successful, FALSE otherwise.
   */
  protected function createUser(array $user_info) {
    // Generate a unique username based on the provided email or preferred_username
    $name = !empty($user_info['preferred_username']) 
      ? $user_info['preferred_username'] 
      : (!empty($user_info['email']) 
          ? explode('@', $user_info['email'])[0] 
          : 'user_' . substr($user_info['sub'], 0, 8));
    
    // Ensure username uniqueness
    $name = $this->ensureUniqueUsername($name);
    
    // Create the user
    $user = User::create([
      'name' => $name,
      'mail' => $user_info['email'] ?? '',
      'pass' => user_password(),
      'status' => 1,
    ]);
    
    // Set user fields based on the user info
    if (isset($user_info['given_name'])) {
      $user->set('field_first_name', $user_info['given_name']);
    }
    
    if (isset($user_info['family_name'])) {
      $user->set('field_last_name', $user_info['family_name']);
    }
    
    // Save the user
    $user->save();
    
    // Store the sub ID in user data
    $this->userData->set('ni_oidc', $user->id(), 'sub_' . md5($user_info['sub']), $user_info['sub']);
    
    // Try also saving in a custom field if it exists
    if ($user->hasField('ni_oidc_sub')) {
      $user->set('ni_oidc_sub', $user_info['sub']);
      $user->save();
    }
    
    // Assign roles to the new user
    $this->assignRoles($user);
    
    $this->loggerFactory->notice('Created new user @name for Next Identity user @sub', [
      '@name' => $name,
      '@sub' => $user_info['sub'],
    ]);
    
    return $user;
  }

  /**
   * Updates an existing Drupal user with Next Identity user info.
   *
   * @param \Drupal\user\UserInterface $user
   *   The Drupal user entity to update.
   * @param array $user_info
   *   The user info from Next Identity.
   */
  protected function updateUser($user, array $user_info) {
    $changed = FALSE;
    
    // Update email if it has changed
    if (!empty($user_info['email']) && $user->getEmail() !== $user_info['email']) {
      $user->setEmail($user_info['email']);
      $changed = TRUE;
    }
    
    // Update first and last name if available
    if (isset($user_info['given_name']) && $user->hasField('field_first_name')) {
      $user->set('field_first_name', $user_info['given_name']);
      $changed = TRUE;
    }
    
    if (isset($user_info['family_name']) && $user->hasField('field_last_name')) {
      $user->set('field_last_name', $user_info['family_name']);
      $changed = TRUE;
    }
    
    // Save the user if changes were made
    if ($changed) {
      $user->save();
      $this->loggerFactory->info('Updated user @name with Next Identity user info', [
        '@name' => $user->getAccountName(),
      ]);
    }
  }

  /**
   * Ensures that a username is unique.
   *
   * @param string $name
   *   The base username.
   *
   * @return string
   *   A unique username.
   */
  protected function ensureUniqueUsername($name) {
    $base_name = $name;
    $count = 1;
    
    while ($this->usernameExists($name)) {
      $name = $base_name . '_' . $count;
      $count++;
    }
    
    return $name;
  }

  /**
   * Checks if a username already exists.
   *
   * @param string $name
   *   The username to check.
   *
   * @return bool
   *   TRUE if the username exists, FALSE otherwise.
   */
  protected function usernameExists($name) {
    $users = $this->entityTypeManager->getStorage('user')->loadByProperties([
      'name' => $name,
    ]);
    
    return !empty($users);
  }

  /**
   * Assigns roles to a user based on the module configuration.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to assign roles to.
   */
  protected function assignRoles($user) {
    $roles = array_filter($this->config->get('user_roles') ?: []);
    foreach ($roles as $role_id => $enabled) {
      if ($enabled && !$user->hasRole($role_id)) {
        $user->addRole($role_id);
      }
    }
    $user->save();
  }
} 