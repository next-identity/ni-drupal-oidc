<?php

namespace Drupal\ni_oidc\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Configure Next Identity OIDC settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ni_oidc.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ni_oidc_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ni_oidc.settings');

    // Add the Next Identity logo to the form
    $module_path = \Drupal::service('extension.list.module')->getPath('ni_oidc');
    $logo_path = $module_path . '/images/Next_Identity_Logo_Black.svg';
    $form['logo'] = [
      '#markup' => '<img src="/' . $logo_path . '" alt="Next Identity" class="ni-oidc-settings-logo" />',
      '#weight' => -100,
    ];

    $form['provider_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Next Identity Provider Settings'),
      '#open' => TRUE,
    ];

    $form['provider_settings']['provider_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Next Identity Provider URL'),
      '#description' => $this->t('The base URL of your Next Identity provider (e.g., https://auth.nextidentity.com)'),
      '#default_value' => $config->get('provider_url'),
      '#required' => TRUE,
    ];

    $form['provider_settings']['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#description' => $this->t('The client ID provided by Next Identity'),
      '#default_value' => $config->get('client_id'),
      '#required' => TRUE,
    ];

    $form['provider_settings']['client_secret'] = [
      '#type' => 'password',
      '#title' => $this->t('Client Secret'),
      '#description' => $this->t('The client secret provided by Next Identity'),
      '#default_value' => $config->get('client_secret'),
      '#required' => TRUE,
    ];

    if (!empty($config->get('client_secret'))) {
      $form['provider_settings']['client_secret']['#description'] = $this->t('The client secret provided by Next Identity. Leave empty to keep using the saved secret.');
      $form['provider_settings']['client_secret']['#required'] = FALSE;
    }

    $form['provider_settings']['scopes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Scopes'),
      '#description' => $this->t('Space-separated list of OAuth scopes to request (e.g., openid profile email)'),
      '#default_value' => $config->get('scopes') ?: 'openid profile email',
      '#required' => TRUE,
    ];

    // Display the callback URL that needs to be configured on the Next Identity side
    $callback_url = Url::fromRoute('ni_oidc.callback', [], ['absolute' => TRUE])->toString();
    $form['provider_settings']['callback_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Callback URL (Redirect URI)'),
      '#description' => $this->t('Configure this URL as the redirect URI in your Next Identity application settings.'),
      '#default_value' => $callback_url,
      '#attributes' => ['readonly' => 'readonly', 'class' => ['ni-oidc-callback-url']],
    ];

    $form['user_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('User Account Settings'),
      '#open' => TRUE,
    ];

    $form['user_settings']['auto_register'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto-register users'),
      '#description' => $this->t('Automatically create a new Drupal user if not already registered.'),
      '#default_value' => $config->get('auto_register') ?? TRUE,
    ];

    $form['user_settings']['user_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles to assign to new users'),
      '#description' => $this->t('Select roles to assign to users created through Next Identity authentication.'),
      '#options' => array_map('\Drupal\Component\Utility\Html::escape', user_role_names(TRUE)),
      '#default_value' => $config->get('user_roles') ?: [],
    ];

    $form['button_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Button Settings'),
      '#open' => TRUE,
    ];

    $form['button_settings']['login_button_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Login Button Text'),
      '#description' => $this->t('Text to display on the login button'),
      '#default_value' => $config->get('login_button_text') ?: $this->t('Log in with Next Identity'),
    ];

    $form['button_settings']['register_button_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Register Button Text'),
      '#description' => $this->t('Text to display on the register button'),
      '#default_value' => $config->get('register_button_text') ?: $this->t('Register with Next Identity'),
    ];

    // Show preview of buttons
    $login_icon = '/' . $module_path . '/images/Next_Identity_Icon_White.svg';
    $register_icon = '/' . $module_path . '/images/Next_Identity_Icon_Black.svg';
    
    $form['button_settings']['button_preview'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ni-oidc-button-preview']],
    ];
    
    $form['button_settings']['button_preview']['heading'] = [
      '#markup' => '<h4>' . $this->t('Button Preview') . '</h4>',
    ];
    
    $form['button_settings']['button_preview']['login'] = [
      '#markup' => '<div class="button-preview-item"><a href="#" class="ni-oidc-login-button button button--primary"><img src="' . $login_icon . '" alt="Next Identity" class="ni-oidc-icon"><span>' . $config->get('login_button_text') . '</span></a></div>',
    ];
    
    $form['button_settings']['button_preview']['register'] = [
      '#markup' => '<div class="button-preview-item"><a href="#" class="ni-oidc-register-button button"><img src="' . $register_icon . '" alt="Next Identity" class="ni-oidc-icon"><span>' . $config->get('register_button_text') . '</span></a></div>',
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Settings'),
      '#open' => FALSE,
    ];

    $form['advanced']['userinfo_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User Info Endpoint'),
      '#description' => $this->t('Override the default userinfo endpoint. Leave empty to use the one from discovery.'),
      '#default_value' => $config->get('userinfo_endpoint'),
      '#required' => FALSE,
    ];

    // Add favicon link to Drupal site if module is enabled
    $form['advanced']['use_favicon'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use Next Identity favicon'),
      '#description' => $this->t('Replace the default Drupal favicon with the Next Identity favicon.'),
      '#default_value' => $config->get('use_favicon') ?? FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $provider_url = $form_state->getValue('provider_url');
    if ($provider_url && substr($provider_url, -1) === '/') {
      // Remove trailing slash from provider URL
      $form_state->setValue('provider_url', rtrim($provider_url, '/'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ni_oidc.settings');
    
    $config
      ->set('provider_url', $form_state->getValue('provider_url'))
      ->set('client_id', $form_state->getValue('client_id'))
      ->set('scopes', $form_state->getValue('scopes'))
      ->set('auto_register', $form_state->getValue('auto_register'))
      ->set('user_roles', $form_state->getValue('user_roles'))
      ->set('login_button_text', $form_state->getValue('login_button_text'))
      ->set('register_button_text', $form_state->getValue('register_button_text'))
      ->set('userinfo_endpoint', $form_state->getValue('userinfo_endpoint'))
      ->set('use_favicon', $form_state->getValue('use_favicon'));

    // Only update the client secret if a new one was provided
    if ($form_state->getValue('client_secret')) {
      $config->set('client_secret', $form_state->getValue('client_secret'));
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }
} 