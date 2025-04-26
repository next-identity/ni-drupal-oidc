<?php

namespace Drupal\ni_oidc\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block with Next Identity login and register buttons.
 *
 * @Block(
 *   id = "ni_oidc_block",
 *   admin_label = @Translation("Next Identity Login"),
 *   category = @Translation("User"),
 * )
 */
class NiOidcBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new NiOidcBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = \Drupal::config('ni_oidc.settings');
    
    // If the module is not configured, don't show anything
    if (empty($config->get('provider_url')) || empty($config->get('client_id'))) {
      return [];
    }
    
    $build = [
      '#attached' => [
        'library' => ['ni_oidc/ni_oidc'],
      ],
    ];
    
    if ($this->currentUser->isAuthenticated()) {
      // Add profile button
      $profile_url = Url::fromRoute('ni_oidc.edit_profile')->toString();
      $build['profile'] = [
        '#theme' => 'ni_oidc_profile_button',
        '#url' => $profile_url,
        '#text' => $config->get('profile_button_text') ?: $this->t('My Profile'),
      ];
      
      // Add logout button
      $logout_url = Url::fromRoute('ni_oidc.logout')->toString();
      $build['logout'] = [
        '#theme' => 'ni_oidc_logout_button',
        '#url' => $logout_url,
        '#text' => $config->get('logout_button_text') ?: $this->t('Logout'),
      ];
    }
    else {
      // Add login button
      $login_url = Url::fromRoute('ni_oidc.authorize')->toString();
      $build['login'] = [
        '#theme' => 'ni_oidc_login_button',
        '#url' => $login_url,
        '#text' => $config->get('login_button_text') ?: $this->t('Log in with Next Identity'),
      ];
      
      // Add register button
      $register_url = Url::fromRoute('ni_oidc.register')->toString();
      $build['register'] = [
        '#theme' => 'ni_oidc_register_button',
        '#url' => $register_url,
        '#text' => $config->get('register_button_text') ?: $this->t('Register with Next Identity'),
      ];
    }
    
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // Cache based on the current user being anonymous or authenticated
    return 0;
  }

} 