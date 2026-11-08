<?php

declare(strict_types=1);

namespace Drupal\Tests\d11assd_theme\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the exported site configuration (spec 001: CA5, R6, R9).
 */
#[Group('d11assd_theme')]
class ConfigSyncTest extends KernelTestBase {

  /**
   * CA5/R9: config/sync sets d11assd_theme as default and Claro as admin.
   */
  public function testExportedThemeConfiguration(): void {
    $storage = new FileStorage(DRUPAL_ROOT . '/../config/sync');

    $theme = $storage->read('system.theme');
    $this->assertSame('d11assd_theme', $theme['default']);
    $this->assertSame('claro', $theme['admin']);

    $extensions = $storage->read('core.extension');
    $this->assertArrayHasKey('d11assd_theme', $extensions['theme']);

    $branding = $storage->read('block.block.d11assd_theme_branding');
    $this->assertIsArray($branding, 'Branding block is exported.');
    $this->assertSame('d11assd_theme', $branding['theme']);
  }

}
