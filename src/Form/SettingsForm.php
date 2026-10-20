<?php

namespace Drupal\privatus_analytics\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\privatus_analytics\TrackerAttachment;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for the Privatus Analytics tracking script.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Constructs the form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'privatus_analytics_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['privatus_analytics.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('privatus_analytics.settings');

    $form['intro'] = [
      '#type' => 'item',
      '#markup' => $this->t('Privatus Analytics uses no cookies and stores nothing on visitors\' devices. Once you save a site id, visitors\' browsers load a script from the Privatus Analytics service and send it anonymous page view data. Nothing is sent before that. <a href=":terms">Terms of service</a> | <a href=":privacy">Privacy policy</a>', [
        ':terms' => 'https://privatusanalytics.com/terms',
        ':privacy' => 'https://privatusanalytics.com/privacy',
      ]),
    ];

    $form['tracking'] = [
      '#type' => 'details',
      '#title' => $this->t('Tracking'),
      '#open' => TRUE,
    ];
    $form['tracking']['site_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site ID'),
      '#default_value' => $config->get('site_id'),
      '#size' => 30,
      '#maxlength' => 80,
      '#placeholder' => 'pa_...',
      '#description' => $this->t('Your public site id, for example pa_AbC123. Find it in your Privatus Analytics dashboard under Site settings, then Setup. Leave it empty to turn tracking off.'),
    ];
    $form['tracking']['script_host'] = [
      '#type' => 'url',
      '#title' => $this->t('Script host'),
      '#default_value' => $config->get('script_host') ?: TrackerAttachment::DEFAULT_HOST,
      '#description' => $this->t('The address that serves the tracking script. Keep the default unless you host your own copy.'),
    ];

    $form['exclusions'] = [
      '#type' => 'details',
      '#title' => $this->t('Exclusions'),
      '#open' => TRUE,
    ];
    $form['exclusions']['exclude_admin_pages'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Do not track administration pages'),
      '#default_value' => $config->get('exclude_admin_pages'),
    ];
    $roles = [];
    foreach ($this->entityTypeManager->getStorage('user_role')->loadMultiple() as $role) {
      $roles[$role->id()] = $role->label();
    }
    $form['exclusions']['excluded_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Do not track users with these roles'),
      '#options' => $roles,
      '#default_value' => (array) $config->get('excluded_roles'),
      '#description' => $this->t('You can also give the "Do not track with Privatus Analytics" permission to a role on the permissions page.'),
    ];
    $form['exclusions']['excluded_paths'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Excluded paths'),
      '#default_value' => $config->get('excluded_paths'),
      '#maxlength' => 500,
      '#description' => $this->t('Comma-separated paths that are never tracked. A star matches any characters. Example: /user/*,/private/*'),
    ];

    $form['script'] = [
      '#type' => 'details',
      '#title' => $this->t('Script options'),
      '#open' => FALSE,
    ];
    $form['script']['spa'] = [
      '#type' => 'select',
      '#title' => $this->t('Single-page app mode'),
      '#options' => array_combine(TrackerAttachment::SPA_MODES, TrackerAttachment::SPA_MODES),
      '#default_value' => $config->get('spa'),
      '#description' => $this->t('How client-side navigations are detected.'),
    ];
    $form['script']['manual'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Manual pageviews'),
      '#default_value' => $config->get('manual'),
      '#description' => $this->t('Turn off the automatic pageview. You then call privatus.pageview() yourself.'),
    ];
    $form['script']['modules'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Modules'),
      '#options' => [
        'engage' => $this->t('Engagement: time on page and scroll depth'),
        'auto' => $this->t('Automatic events: outbound links, downloads and forms'),
        'vitals' => $this->t('Core Web Vitals'),
        'clicks' => $this->t('Click maps'),
        'ask' => $this->t('On-site surveys'),
      ],
      '#default_value' => (array) $config->get('modules'),
    ];
    $form['script']['domains'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed domains'),
      '#default_value' => $config->get('domains'),
      '#maxlength' => 500,
      '#description' => $this->t('Comma-separated hostnames. Visits on other hostnames, such as a staging site, are ignored. Example: example.com,www.example.com'),
    ];
    $form['script']['path_masks'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path masks'),
      '#default_value' => $config->get('path_masks'),
      '#maxlength' => 500,
      '#description' => $this->t('Comma-separated rules in the form pattern->replacement. Example: /user/*->/user/:id'),
    ];
    $form['script']['extra_params'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Extra query parameters'),
      '#default_value' => $config->get('extra_params'),
      '#maxlength' => 500,
      '#description' => $this->t('Comma-separated query parameters to keep. UTM parameters are always kept. Example: page,lang'),
    ];
    $form['script']['canonical'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use canonical URL'),
      '#default_value' => $config->get('canonical'),
      '#description' => $this->t('Record the rel=canonical URL instead of the address bar.'),
    ];
    $form['script']['hash'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hash routing'),
      '#default_value' => $config->get('hash'),
      '#description' => $this->t('Treat #fragments as separate pages.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $site_id = trim((string) $form_state->getValue('site_id'));
    if ($site_id !== '' && !preg_match('/^pa_[A-Za-z0-9_-]{1,64}$/', $site_id)) {
      $form_state->setErrorByName('site_id', $this->t('The site id must look like pa_XXXX.'));
    }
    $host = trim((string) $form_state->getValue('script_host'));
    if ($host !== '') {
      $parts = parse_url($host);
      $path = rtrim($parts['path'] ?? '', '/');
      if (!UrlHelper::isValid($host, TRUE) || !in_array($parts['scheme'] ?? '', ['https', 'http'], TRUE) || $path !== '' || isset($parts['query']) || isset($parts['fragment'])) {
        $form_state->setErrorByName('script_host', $this->t('The script host must be an address such as https://privatusstats.com, with no path.'));
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $host = rtrim(trim((string) $form_state->getValue('script_host')), '/');
    $lists = ['domains', 'excluded_paths', 'path_masks', 'extra_params'];
    $config = $this->config('privatus_analytics.settings')
      ->set('site_id', trim((string) $form_state->getValue('site_id')))
      ->set('script_host', $host !== '' ? $host : TrackerAttachment::DEFAULT_HOST)
      ->set('exclude_admin_pages', (bool) $form_state->getValue('exclude_admin_pages'))
      ->set('excluded_roles', array_values(array_filter($form_state->getValue('excluded_roles'))))
      ->set('spa', $form_state->getValue('spa'))
      ->set('manual', (bool) $form_state->getValue('manual'))
      ->set('modules', array_values(array_filter($form_state->getValue('modules'))))
      ->set('canonical', (bool) $form_state->getValue('canonical'))
      ->set('hash', (bool) $form_state->getValue('hash'));
    foreach ($lists as $list) {
      $items = array_filter(array_map('trim', explode(',', (string) $form_state->getValue($list))), 'strlen');
      $config->set($list, implode(',', $items));
    }
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
