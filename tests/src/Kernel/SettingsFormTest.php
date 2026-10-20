<?php

namespace Drupal\Tests\privatus_analytics\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\privatus_analytics\Form\SettingsForm;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the settings form validation and saving.
 *
 * @group privatus_analytics
 */
#[RunTestsInSeparateProcesses]
class SettingsFormTest extends KernelTestBase {

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
    Role::create(['id' => 'editor', 'label' => 'Editor'])->save();
  }

  /**
   * Submits the form with values and returns the form state.
   */
  protected function submit(array $values): FormState {
    $form_state = (new FormState())->setValues($values + [
      'site_id' => '',
      'script_host' => 'https://privatusstats.com',
      'exclude_admin_pages' => 1,
      'excluded_roles' => [],
      'excluded_paths' => '',
      'spa' => 'auto',
      'manual' => 0,
      'modules' => ['engage' => 'engage'],
      'domains' => '',
      'path_masks' => '',
      'extra_params' => '',
      'canonical' => 0,
      'hash' => 0,
    ]);
    $this->container->get('form_builder')->submitForm(SettingsForm::class, $form_state);
    return $form_state;
  }

  /**
   * A valid submission is saved and normalized.
   */
  public function testSave(): void {
    $form_state = $this->submit([
      'site_id' => ' pa_FORM123 ',
      'script_host' => 'https://stats.example.com/',
      'excluded_roles' => ['editor' => 'editor'],
      'excluded_paths' => ' /user/* , ,/private/* ',
      'modules' => ['engage' => 'engage', 'clicks' => 'clicks'],
    ]);
    $this->assertSame([], $form_state->getErrors());
    $config = $this->config('privatus_analytics.settings');
    $this->assertSame('pa_FORM123', $config->get('site_id'));
    $this->assertSame('https://stats.example.com', $config->get('script_host'));
    $this->assertSame(['editor'], $config->get('excluded_roles'));
    $this->assertSame('/user/*,/private/*', $config->get('excluded_paths'));
    $this->assertSame(['engage', 'clicks'], $config->get('modules'));
  }

  /**
   * A bad site id or script host is refused.
   */
  public function testValidation(): void {
    $this->assertArrayHasKey('site_id', $this->submit(['site_id' => 'abc'])->getErrors());
    $this->assertArrayHasKey('script_host', $this->submit(['script_host' => 'https://example.com/path'])->getErrors());
    $this->assertArrayHasKey('script_host', $this->submit(['script_host' => 'javascript:alert(1)'])->getErrors());
    $this->assertSame('', $this->config('privatus_analytics.settings')->get('site_id'));
  }

}
