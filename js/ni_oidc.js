/**
 * @file
 * JavaScript behaviors for the Next Identity OIDC module.
 */

(function (Drupal, drupalSettings, once) {

  'use strict';

  /**
   * Behavior for the Next Identity OIDC forms and buttons.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.niOidc = {
    attach: function (context, settings) {
      // Make the callback URL field easy to copy with a button
      once('ni-callback-copy', '.ni-oidc-callback-url', context).forEach(function (element) {
        const wrapper = document.createElement('div');
        wrapper.style.position = 'relative';
        wrapper.style.display = 'inline-block';
        wrapper.style.width = '100%';
        wrapper.style.maxWidth = '600px';
        
        const copyButton = document.createElement('button');
        copyButton.textContent = Drupal.t('Copy');
        copyButton.className = 'button button--small';
        copyButton.style.position = 'absolute';
        copyButton.style.right = '5px';
        copyButton.style.top = '5px';
        
        // Replace the field with our wrapped version
        const parent = element.parentNode;
        wrapper.appendChild(element);
        wrapper.appendChild(copyButton);
        parent.appendChild(wrapper);
        
        // Add click event to copy the URL
        copyButton.addEventListener('click', function (e) {
          e.preventDefault();
          element.select();
          document.execCommand('copy');
          
          // Show a message that the URL was copied
          const originalText = copyButton.textContent;
          copyButton.textContent = Drupal.t('Copied!');
          setTimeout(function () {
            copyButton.textContent = originalText;
          }, 2000);
        });
      });

      // Add 'active' class to buttons when clicked for better UX
      once('ni-button-active', ['.ni-oidc-login-button', '.ni-oidc-register-button'], context).forEach(function (element) {
        element.addEventListener('click', function (e) {
          this.classList.add('active');
          this.textContent = Drupal.t('Redirecting...');
        });
      });
    }
  };

})(Drupal, drupalSettings, once); 