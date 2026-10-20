<?php

namespace Drupal\Tests\privatus_analytics\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\privatus_analytics\TrackerAttachment;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the script tag attributes and the tracking decision.
 *
 * @group privatus_analytics
 * @coversDefaultClass \Drupal\privatus_analytics\TrackerAttachment
 */
class TrackerAttachmentTest extends UnitTestCase {

  /**
   * Settings as installed, with a site id.
   *
   * @return array
   *   The settings.
   */
  protected function settings(array $overrides = []): array {
    return $overrides + [
      'site_id' => 'pa_TEST123',
      'script_host' => 'https://privatusstats.com',
      'spa' => 'auto',
      'manual' => FALSE,
      'modules' => ['engage'],
      'domains' => '',
      'excluded_paths' => '',
      'path_masks' => '',
      'extra_params' => '',
      'canonical' => FALSE,
      'hash' => FALSE,
      'exclude_admin_pages' => TRUE,
      'excluded_roles' => [],
    ];
  }

  /**
   * Builds the service with a user and an admin route flag.
   */
  protected function service(bool $admin_route, bool $bypass, array $roles = []): TrackerAttachment {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->with(TrackerAttachment::BYPASS_PERMISSION)->willReturn($bypass);
    $account->method('getRoles')->willReturn($roles);
    $admin = $this->createMock(AdminContext::class);
    $admin->method('isAdminRoute')->willReturn($admin_route);
    return new TrackerAttachment($this->createMock(ConfigFactoryInterface::class), $account, $admin);
  }

  /**
   * The default settings print the minimal tag.
   *
   * @covers ::scriptAttributes
   */
  public function testDefaultAttributes(): void {
    $this->assertSame([
      'defer' => TRUE,
      'src' => 'https://privatusstats.com/js/pa.js',
      'data-site' => 'pa_TEST123',
    ], TrackerAttachment::scriptAttributes($this->settings()));
  }

  /**
   * Every option maps to its data attribute.
   *
   * @covers ::scriptAttributes
   */
  public function testAllAttributes(): void {
    $attributes = TrackerAttachment::scriptAttributes($this->settings([
      'script_host' => 'https://stats.example.com/',
      'spa' => 'history',
      'manual' => TRUE,
      'modules' => ['engage', 'ask', 'bogus'],
      'domains' => 'example.com',
      'excluded_paths' => '/user/*',
      'path_masks' => '/u/*->/u/:id',
      'extra_params' => 'page',
      'canonical' => TRUE,
      'hash' => TRUE,
    ]));
    $this->assertSame('https://stats.example.com/js/pa.js', $attributes['src']);
    $this->assertSame('history', $attributes['data-spa']);
    $this->assertSame('true', $attributes['data-manual']);
    $this->assertSame('engage,ask', $attributes['data-modules']);
    $this->assertSame('example.com', $attributes['data-domains']);
    $this->assertSame('/user/*', $attributes['data-exclude']);
    $this->assertSame('/u/*->/u/:id', $attributes['data-mask']);
    $this->assertSame('page', $attributes['data-params']);
    $this->assertSame('true', $attributes['data-canonical']);
    $this->assertSame('true', $attributes['data-hash']);
  }

  /**
   * An empty module list turns every module off.
   *
   * @covers ::scriptAttributes
   */
  public function testNoModules(): void {
    $attributes = TrackerAttachment::scriptAttributes($this->settings(['modules' => []]));
    $this->assertSame('', $attributes['data-modules']);
  }

  /**
   * No site id means no tag.
   *
   * @covers ::shouldTrack
   */
  public function testNoSiteId(): void {
    $this->assertFalse($this->service(FALSE, FALSE)->shouldTrack($this->settings(['site_id' => ''])));
  }

  /**
   * Visitors are tracked, administration pages and bypass users are not.
   *
   * @covers ::shouldTrack
   */
  public function testExclusions(): void {
    $this->assertTrue($this->service(FALSE, FALSE)->shouldTrack($this->settings()));
    $this->assertFalse($this->service(TRUE, FALSE)->shouldTrack($this->settings()));
    $this->assertTrue($this->service(TRUE, FALSE)->shouldTrack($this->settings(['exclude_admin_pages' => FALSE])));
    $this->assertFalse($this->service(FALSE, TRUE)->shouldTrack($this->settings()));
    $this->assertFalse($this->service(FALSE, FALSE, ['editor'])->shouldTrack($this->settings(['excluded_roles' => ['editor']])));
    $this->assertTrue($this->service(FALSE, FALSE, ['authenticated'])->shouldTrack($this->settings(['excluded_roles' => ['editor']])));
  }

}
