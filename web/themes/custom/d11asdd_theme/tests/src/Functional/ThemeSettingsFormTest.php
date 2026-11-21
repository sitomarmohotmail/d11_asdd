<?php

declare(strict_types=1);

namespace Drupal\Tests\d11asdd_theme\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\SchemaCheckTestTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the theme settings form inherited from Bootstrap5 (spec 001: CA7e).
 */
#[Group('d11asdd_theme')]
class ThemeSettingsFormTest extends BrowserTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'd11asdd_theme';

  /**
   * CA7e: saving the form unchanged stores schema-valid settings.
   */
  public function testSaveFormKeepsValidSchema(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer themes']));
    $this->drupalGet('admin/appearance/settings/d11asdd_theme');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $config = $this->config('d11asdd_theme.settings');
    $this->assertNotNull($config->get('subtheme_machine_name'), 'The Bootstrap5 subtheme keys are stored.');
    $this->assertConfigSchemaByName('d11asdd_theme.settings');
  }

}
