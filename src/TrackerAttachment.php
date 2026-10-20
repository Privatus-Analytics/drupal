<?php

namespace Drupal\privatus_analytics;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Session\AccountInterface;

/**
 * Builds the Privatus Analytics script tag and decides whether to attach it.
 *
 * The module only prints the browser script. It never sends events from the
 * server.
 */
class TrackerAttachment {

  /**
   * The default origin that serves /js/pa.js.
   */
  const DEFAULT_HOST = 'https://privatusstats.com';

  /**
   * Permission that keeps a user out of the reports.
   */
  const BYPASS_PERMISSION = 'bypass privatus analytics tracking';

  /**
   * Tracker modules a site can turn on.
   */
  const MODULES = ['engage', 'auto', 'vitals', 'clicks', 'ask'];

  /**
   * Values the SPA mode setting accepts.
   */
  const SPA_MODES = ['auto', 'history', 'hash', 'off'];

  /**
   * Constructs the service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Routing\AdminContext $adminContext
   *   The admin route context.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected AccountInterface $currentUser,
    protected AdminContext $adminContext,
  ) {}

  /**
   * Adds the tracker to the page attachments array of hook_page_attachments().
   *
   * @param array $attachments
   *   The page attachments render array.
   */
  public function attach(array &$attachments): void {
    $config = $this->configFactory->get('privatus_analytics.settings');
    $metadata = CacheableMetadata::createFromObject($config);
    $metadata->addCacheContexts(['user.permissions', 'user.roles', 'route']);

    if ($this->shouldTrack($config->get())) {
      $attachments['#attached']['html_head'][] = [
        [
          '#tag' => 'script',
          '#attributes' => self::scriptAttributes($config->get()),
        ],
        'privatus_analytics_tracker',
      ];
    }
    $metadata->applyTo($attachments);
  }

  /**
   * Whether the current request should get the tracker.
   *
   * @param array $settings
   *   The module settings.
   *
   * @return bool
   *   TRUE when the script tag should be printed.
   */
  public function shouldTrack(array $settings): bool {
    if (empty($settings['site_id'])) {
      return FALSE;
    }
    if (!empty($settings['exclude_admin_pages']) && $this->adminContext->isAdminRoute()) {
      return FALSE;
    }
    if ($this->currentUser->hasPermission(self::BYPASS_PERMISSION)) {
      return FALSE;
    }
    $excluded_roles = array_filter((array) ($settings['excluded_roles'] ?? []));
    if ($excluded_roles && array_intersect($this->currentUser->getRoles(), $excluded_roles)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Builds the script tag attributes from the settings.
   *
   * @param array $settings
   *   The module settings.
   *
   * @return array
   *   Attribute name to value. TRUE marks a boolean attribute.
   */
  public static function scriptAttributes(array $settings): array {
    $host = rtrim((string) ($settings['script_host'] ?? ''), '/');
    $attributes = [
      'defer' => TRUE,
      'src' => ($host !== '' ? $host : self::DEFAULT_HOST) . '/js/pa.js',
      'data-site' => (string) $settings['site_id'],
    ];
    $spa = $settings['spa'] ?? 'auto';
    if ($spa !== 'auto' && in_array($spa, self::SPA_MODES, TRUE)) {
      $attributes['data-spa'] = $spa;
    }
    if (!empty($settings['manual'])) {
      $attributes['data-manual'] = 'true';
    }
    $modules = array_values(array_intersect(self::MODULES, (array) ($settings['modules'] ?? ['engage'])));
    if ($modules !== ['engage']) {
      // An empty string is meaningful: it turns every module off.
      $attributes['data-modules'] = implode(',', $modules);
    }
    $lists = [
      'domains' => 'data-domains',
      'excluded_paths' => 'data-exclude',
      'path_masks' => 'data-mask',
      'extra_params' => 'data-params',
    ];
    foreach ($lists as $key => $attribute) {
      $value = trim((string) ($settings[$key] ?? ''));
      if ($value !== '') {
        $attributes[$attribute] = $value;
      }
    }
    if (!empty($settings['canonical'])) {
      $attributes['data-canonical'] = 'true';
    }
    if (!empty($settings['hash'])) {
      $attributes['data-hash'] = 'true';
    }
    return $attributes;
  }

}
