<?php

declare(strict_types=1);

namespace Drupal\Tests\d11asdd_theme\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\d11asdd_theme\Traits\ThemeSettingsValuesTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the d11asdd_theme declaration.
 *
 * Spec 001: CA1, CA2, CA3, CA11, CA14, CA7d, CA23.
 */
#[Group('d11asdd_theme')]
class ThemeTest extends KernelTestBase {

  use ThemeSettingsValuesTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'block', 'user'];

  /**
   * Regions inherited from Bootstrap5 (spec 001, R3).
   */
  private const REGIONS = [
    'header',
    'nav_branding',
    'nav_main',
    'nav_additional',
    'breadcrumb',
    'content',
    'sidebar_first',
    'sidebar_second',
    'footer',
  ];

  /**
   * Blocks created when the theme is installed, keyed by region (CA11).
   */
  private const BLOCKS = [
    'd11asdd_theme_branding' => 'nav_branding',
    'd11asdd_theme_main_navigation' => 'nav_main',
    'd11asdd_theme_account_menu' => 'nav_additional',
    'd11asdd_theme_breadcrumbs' => 'breadcrumb',
    'd11asdd_theme_messages' => 'content',
    'd11asdd_theme_page_title' => 'content',
    'd11asdd_theme_local_tasks' => 'content',
    'd11asdd_theme_local_actions' => 'content',
    'd11asdd_theme_content' => 'content',
    'd11asdd_theme_footer' => 'footer',
    'd11asdd_theme_powered_by_drupal' => 'footer',
  ];

  /**
   * CA1: the theme is discovered with its name, base theme and core version.
   */
  public function testThemeIsDiscovered(): void {
    $themes = $this->container->get('extension.list.theme')->getList();
    $this->assertArrayHasKey('d11asdd_theme', $themes);
    $info = $themes['d11asdd_theme']->info;
    $this->assertSame('D11asdd', $info['name']);
    $this->assertSame('bootstrap5', $info['base theme']);
    $this->assertStringContainsString('^11', $info['core_version_requirement']);
    // R2: the base theme is also declared as an explicit dependency.
    $this->assertContains('bootstrap5:bootstrap5', $info['dependencies']);
  }

  /**
   * CA2: installing the theme also installs bootstrap5 and its regions.
   */
  public function testInstallRequiresBaseThemeAndHasRegions(): void {
    $this->container->get('theme_installer')->install(['d11asdd_theme']);
    $handler = $this->container->get('theme_handler');
    $this->assertTrue($handler->themeExists('d11asdd_theme'));
    $this->assertTrue($handler->themeExists('bootstrap5'));

    $regions = array_keys($handler->getTheme('d11asdd_theme')->info['regions']);
    foreach (self::REGIONS as $region) {
      $this->assertContains($region, $regions, "Region $region exists.");
    }
  }

  /**
   * CA3: the theme provides the global-styling library with style.css.
   */
  public function testGlobalStylingLibrary(): void {
    $this->container->get('theme_installer')->install(['d11asdd_theme']);
    $library = $this->container->get('library.discovery')
      ->getLibraryByName('d11asdd_theme', 'global-styling');
    $this->assertIsArray($library, 'Library d11asdd_theme/global-styling exists.');
    $paths = array_column($library['css'], 'data');
    $this->assertContains('themes/custom/d11asdd_theme/css/style.css', $paths);
  }

  /**
   * CA11: installing the theme places its blocks in Bootstrap5 regions.
   */
  public function testInstallPlacesBlocks(): void {
    $this->installConfig(['system']);
    $this->container->get('theme_installer')->install(['d11asdd_theme']);
    $storage = $this->container->get('entity_type.manager')->getStorage('block');

    foreach (self::BLOCKS as $id => $region) {
      $block = $storage->load($id);
      $this->assertNotNull($block, "Block $id exists.");
      $this->assertSame('d11asdd_theme', $block->getTheme(), "Block $id belongs to d11asdd_theme.");
      $this->assertSame($region, $block->getRegion(), "Block $id is in region $region.");
    }

    // Help and Search are not enabled, so their blocks are not created.
    $this->assertNull($storage->load('d11asdd_theme_help'));
    $this->assertNull($storage->load('d11asdd_theme_search_form'));
  }

  /**
   * CA14: logo.svg and screenshot.png differ from those of Bootstrap5.
   */
  public function testOwnLogoAndScreenshot(): void {
    $own = DRUPAL_ROOT . '/themes/custom/d11asdd_theme';
    $base = DRUPAL_ROOT . '/themes/contrib/bootstrap5';
    foreach (['logo.svg', 'screenshot.png'] as $file) {
      $this->assertFileExists("$own/$file");
      $this->assertFileExists("$base/$file", "Bootstrap5 still ships $file to compare with.");
      $this->assertNotSame(
        hash_file('sha256', "$base/$file"),
        hash_file('sha256', "$own/$file"),
        "$file differs from the Bootstrap5 one."
      );
    }
  }

  /**
   * CA23 (R13, R5, v1.2): the navbar library is defined with correct deps.
   *
   * Checks that d11asdd_theme/navbar exists with js/navbar.js and declares
   * core/drupal, core/once and bootstrap5/bootstrap5-js-latest as
   * dependencies (the last one provides the global `bootstrap` object).
   */
  public function testNavbarLibrary(): void {
    $this->container->get('theme_installer')->install(['d11asdd_theme']);
    $library = $this->container->get('library.discovery')
      ->getLibraryByName('d11asdd_theme', 'navbar');
    $this->assertIsArray($library, 'Library d11asdd_theme/navbar exists.');

    $paths = array_column($library['js'], 'data');
    $this->assertContains(
      'themes/custom/d11asdd_theme/js/navbar.js',
      $paths,
      'navbar library includes js/navbar.js.'
    );

    $deps = $library['dependencies'] ?? [];
    $this->assertContains('core/drupal', $deps, 'Depends on core/drupal.');
    $this->assertContains('core/once', $deps, 'Depends on core/once.');
    $this->assertContains(
      'bootstrap5/bootstrap5-js-latest',
      $deps,
      'Depends on bootstrap5/bootstrap5-js-latest (provides window.bootstrap).'
    );
  }

  /**
   * CA7d: the theme settings are installed and valid against their schema.
   *
   * KernelTestBase enables strict config schema checking, so installing the
   * theme fails if d11asdd_theme.settings has no schema.
   */
  public function testThemeSettingsInstalledWithSchema(): void {
    $this->container->get('theme_installer')->install(['d11asdd_theme']);

    $this->assertTrue(
      $this->container->get('config.typed')->hasConfigSchema('d11asdd_theme.settings'),
      'd11asdd_theme.settings has a schema.'
    );
    $settings = $this->config('d11asdd_theme.settings');
    $this->assertFalse($settings->isNew(), 'd11asdd_theme.settings is installed.');
    foreach (self::R10_SETTINGS as $key => $value) {
      $this->assertSame($value, $settings->get($key), "Setting $key has the R10 value.");
    }
  }

}
