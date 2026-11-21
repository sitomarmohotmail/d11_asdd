<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_cartoteca\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests de kernel del bloque Cartoteca.
 *
 * @group sdc_cartoteca
 */
class CartotecaBlockTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'sdc_cartoteca',
    'block',
    'inline_form_errors',
    'system',
    'user',
  ];

  /**
   * CA1 — plugin sdc_cartoteca registrado en BlockManager.
   */
  public function testBlockExists(): void {
    /** @var \Drupal\Core\Block\BlockManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.block');
    $definitions = $manager->getDefinitions();
    $this->assertArrayHasKey('sdc_cartoteca', $definitions);
    $this->assertEquals('Cartoteca IECA', (string) $definitions['sdc_cartoteca']['admin_label']);
  }

}
