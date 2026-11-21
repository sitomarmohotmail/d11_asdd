<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_cartoteca\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests de kernel del bloque y servicio Cartoteca.
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

  /**
   * CA3 / CA10a / CA10b — servicio declarado y resoluble en el contenedor.
   */
  public function testApiServiceIsRegistered(): void {
    $this->assertTrue(
      $this->container->has('sdc_cartoteca.api_service'),
      'El servicio sdc_cartoteca.api_service no está registrado.',
    );
    // get() lanza una excepción si el servicio no es resoluble: la propia
    // llamada es la comprobación de que puede instanciarse.
    $this->container->get('sdc_cartoteca.api_service');
  }

}
