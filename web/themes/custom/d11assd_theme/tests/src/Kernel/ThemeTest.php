<?php

declare(strict_types=1);

namespace Drupal\Tests\d11assd_theme\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the d11assd_theme declaration (spec 001: CA1, CA2, CA3).
 */
#[Group('d11assd_theme')]
class ThemeTest extends KernelTestBase {

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
   * CA1: the theme is discovered and its base theme is bootstrap5.
   */
  public function testThemeIsDiscovered(): void {
    $themes = $this->container->get('extension.list.theme')->getList();
    $this->assertArrayHasKey('d11assd_theme', $themes);
    $this->assertSame('D11assd', $themes['d11assd_theme']->info['name']);
    $this->assertSame('bootstrap5', $themes['d11assd_theme']->info['base theme']);
  }

  /**
   * CA2: installing the theme also installs bootstrap5 and its regions.
   */
  public function testInstallRequiresBaseThemeAndHasRegions(): void {
    $this->container->get('theme_installer')->install(['d11assd_theme']);
    $handler = $this->container->get('theme_handler');
    $this->assertTrue($handler->themeExists('d11assd_theme'));
    $this->assertTrue($handler->themeExists('bootstrap5'));

    $regions = array_keys($handler->getTheme('d11assd_theme')->info['regions']);
    foreach (self::REGIONS as $region) {
      $this->assertContains($region, $regions, "Region $region exists.");
    }
  }

  /**
   * CA3: the theme provides the global-styling library with style.css.
   */
  public function testGlobalStylingLibrary(): void {
    $this->container->get('theme_installer')->install(['d11assd_theme']);
    $library = $this->container->get('library.discovery')
      ->getLibraryByName('d11assd_theme', 'global-styling');
    $this->assertIsArray($library, 'Library d11assd_theme/global-styling exists.');
    $paths = array_column($library['css'], 'data');
    $this->assertContains('themes/custom/d11assd_theme/css/style.css', $paths);
  }

}
