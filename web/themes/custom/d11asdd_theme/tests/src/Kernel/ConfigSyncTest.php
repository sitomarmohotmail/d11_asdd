<?php

declare(strict_types=1);

namespace Drupal\Tests\d11asdd_theme\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\d11asdd_theme\Traits\ThemeSettingsValuesTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the exported site configuration.
 *
 * Spec 001: CA5b, CA10, CA7c, CA-A6.
 */
#[Group('d11asdd_theme')]
class ConfigSyncTest extends KernelTestBase {

  use ThemeSettingsValuesTrait;

  /**
   * The config/sync storage of the project.
   */
  private function syncStorage(): FileStorage {
    return new FileStorage(DRUPAL_ROOT . '/../config/sync');
  }

  /**
   * CA5b: config/sync sets d11asdd_theme as default and Claro as admin.
   */
  public function testExportedThemeConfiguration(): void {
    $storage = $this->syncStorage();

    $theme = $storage->read('system.theme');
    $this->assertSame('d11asdd_theme', $theme['default']);
    $this->assertSame('gin', $theme['admin']);

    $extensions = $storage->read('core.extension');
    $this->assertArrayHasKey('d11asdd_theme', $extensions['theme']);
  }

  /**
   * CA10: every optional block whose modules are enabled is exported.
   */
  public function testExportedBlocks(): void {
    $sync = $this->syncStorage();
    $enabled = array_keys($sync->read('core.extension')['module']);
    $optional = new FileStorage(DRUPAL_ROOT . '/themes/custom/d11asdd_theme/config/optional');

    $names = $optional->listAll('block.block.d11asdd_theme_');
    $this->assertNotEmpty($names);
    foreach ($names as $name) {
      $modules = $optional->read($name)['dependencies']['module'] ?? [];
      if (array_diff($modules, $enabled)) {
        continue;
      }
      $this->assertTrue($sync->exists($name), "$name is exported to config/sync.");
    }
  }

  /**
   * CA-A6: block labels are in Spanish, in config/optional and config/sync.
   */
  public function testBlockLabelsInSpanish(): void {
    $labels = [
      'account_menu' => 'Menú de la cuenta de usuario',
      'branding' => 'Marca del sitio',
      'breadcrumbs' => 'Ruta de navegación',
      'cartotecaieca' => 'Cartoteca IECA',
      'content' => 'Contenido principal de la página',
      'footer' => 'Pie de página',
      'help' => 'Ayuda',
      'local_actions' => 'Acciones administrativas principales',
      'local_tasks' => 'Pestañas',
      'main_navigation' => 'Navegación principal',
      'messages' => 'Mensajes',
      'page_title' => 'Título de la página',
      'powered_by_drupal' => 'Funciona con Drupal',
      'search_form' => 'Formulario de búsqueda',
    ];
    $storages = [
      'config/optional' => new FileStorage(DRUPAL_ROOT . '/themes/custom/d11asdd_theme/config/optional'),
      'config/sync' => $this->syncStorage(),
    ];
    // config/optional: 12 bloques base + search_form = 13.
    // config/sync: 12 bloques base + cartotecaieca (spec-007) = 13.
    $expected_counts = ['config/optional' => 13, 'config/sync' => 13];
    foreach ($storages as $label => $storage) {
      $names = $storage->listAll('block.block.d11asdd_theme_');
      // Search is not enabled, so its block is not exported (QA-009).
      $this->assertCount($expected_counts[$label], $names, "$label has the expected number of blocks.");
      foreach ($names as $name) {
        $key = substr($name, strlen('block.block.d11asdd_theme_'));
        $this->assertArrayHasKey($key, $labels, "$label: $name has an expected Spanish label.");
        $block = $storage->read($name);
        $this->assertSame('es', $block['langcode'], "$label: $name has langcode es.");
        $this->assertSame($labels[$key], $block['settings']['label'], "$label: $name label is in Spanish.");
      }
    }
  }

  /**
   * CA7c: theme settings exist in config/install and config/sync with R10.
   */
  public function testThemeSettingsExported(): void {
    $install = new FileStorage(DRUPAL_ROOT . '/themes/custom/d11asdd_theme/config/install');
    $storages = [
      'config/install' => $install,
      'config/sync' => $this->syncStorage(),
    ];
    foreach ($storages as $label => $storage) {
      $settings = $storage->read('d11asdd_theme.settings');
      $this->assertIsArray($settings, "d11asdd_theme.settings exists in $label.");
      foreach (self::R10_SETTINGS as $key => $value) {
        $this->assertSame($value, $settings[$key] ?? NULL, "$label: $key has the R10 value.");
      }
    }
  }

}
