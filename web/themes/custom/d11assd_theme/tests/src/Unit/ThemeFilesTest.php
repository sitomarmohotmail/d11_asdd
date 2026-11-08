<?php

declare(strict_types=1);

namespace Drupal\Tests\d11assd_theme\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the files of d11assd_theme (spec 001: CA9, CA9b).
 */
#[Group('d11assd_theme')]
class ThemeFilesTest extends UnitTestCase {

  /**
   * Absolute path of the theme.
   */
  private function themePath(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * CA9: no SCSS/Sass files, package.json or node_modules in the theme.
   */
  public function testNoScssOrNodeFiles(): void {
    $this->assertFileDoesNotExist($this->themePath() . '/package.json');
    $this->assertDirectoryDoesNotExist($this->themePath() . '/node_modules');

    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($this->themePath(), \FilesystemIterator::SKIP_DOTS)
    );
    $forbidden = [];
    foreach ($iterator as $file) {
      if (in_array(strtolower($file->getExtension()), ['scss', 'sass'], TRUE)) {
        $forbidden[] = $file->getPathname();
      }
    }
    $this->assertSame([], $forbidden, 'The theme contains no .scss or .sass files.');
  }

  /**
   * CA9b: the global-styling library only declares .css files.
   */
  public function testGlobalStylingOnlyDeclaresCss(): void {
    $libraries = Yaml::decode(file_get_contents($this->themePath() . '/d11assd_theme.libraries.yml'));
    $library = $libraries['global-styling'];

    $this->assertArrayNotHasKey('js', $library, 'global-styling declares no JavaScript.');
    $files = [];
    foreach ($library['css'] as $group) {
      $files = array_merge($files, array_keys($group));
    }
    $this->assertNotEmpty($files);
    foreach ($files as $file) {
      $this->assertStringEndsWith('.css', $file);
    }
  }

}
