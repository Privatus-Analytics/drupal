<?php

namespace Drupal\Tests\privatus_analytics\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the installed config, the schema and the page attachment.
 *
 * @group privatus_analytics
 */
#[RunTestsInSeparateProcesses]
class TrackerPageAttachmentsTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'privatus_analytics'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['privatus_analytics']);
    $this->installEntitySchema('user');
  }

  /**
   * The default config matches its schema.
   */
  public function testDefaultConfigMatchesSchema(): void {
    $typed = $this->container->get('config.typed');
    $config = $this->config('privatus_analytics.settings');
    $this->assertNotEmpty($typed->get('privatus_analytics.settings'));
    $this->assertSame('https://privatusstats.com', $config->get('script_host'));
    $this->assertSame('', $config->get('site_id'));
  }

  /**
   * Nothing is attached until a site id is set.
   */
  public function testNoTagWithoutSiteId(): void {
    $attachments = [];
    privatus_analytics_page_attachments($attachments);
    $this->assertArrayNotHasKey('#attached', $attachments);
    $this->assertContains('config:privatus_analytics.settings', $attachments['#cache']['tags']);
  }

  /**
   * The tag is attached for a visitor with the right attributes and cache data.
   */
  public function testTagAttached(): void {
    $this->config('privatus_analytics.settings')->set('site_id', 'pa_KERNEL1')->save();
    $this->setUpCurrentUser(['uid' => 0]);
    $attachments = [];
    privatus_analytics_page_attachments($attachments);
    $tag = $attachments['#attached']['html_head'][0][0];
    $this->assertSame('script', $tag['#tag']);
    $this->assertSame('pa_KERNEL1', $tag['#attributes']['data-site']);
    $this->assertSame('https://privatusstats.com/js/pa.js', $tag['#attributes']['src']);
    $this->assertContains('user.permissions', $attachments['#cache']['contexts']);
    $this->assertContains('route', $attachments['#cache']['contexts']);
  }

  /**
   * A user with the bypass permission gets no tag.
   */
  public function testBypassPermission(): void {
    $this->config('privatus_analytics.settings')->set('site_id', 'pa_KERNEL1')->save();
    // Create user 1 first so the next user is not the super user.
    $this->createUser();
    $account = $this->createUser(['bypass privatus analytics tracking']);
    $this->setCurrentUser($account);
    $attachments = [];
    privatus_analytics_page_attachments($attachments);
    $this->assertArrayNotHasKey('#attached', $attachments);
  }

}
