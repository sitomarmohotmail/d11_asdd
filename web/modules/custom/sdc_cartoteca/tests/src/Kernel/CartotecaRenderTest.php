<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_cartoteca\Kernel;

use Drupal\KernelTests\Components\ComponentKernelTestBase;

/**
 * Tests de renderizado de los componentes SDC de la Cartoteca.
 *
 * Renderiza cada componente en estado inicial (url vacía) y comprueba su HTML,
 * cubriendo en kernel los criterios de accesibilidad y estado inicial que antes
 * requerían un BrowserTestBase.
 *
 * @group sdc_cartoteca
 */
class CartotecaRenderTest extends ComponentKernelTestBase {

  /**
   * Imagen por defecto que el formulario pasa al componente slider.
   */
  private const DEFAULT_IMAGE = '/modules/custom/sdc_cartoteca/images/imagen-no-disponible.jpg';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'serialization',
    'sdc_cartoteca',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $themes = ['stark'];

  /**
   * CA-A2 / CA8 — el slider muestra su <h2> e imagen por defecto inicial.
   */
  public function testSliderInitialState(): void {
    $crawler = $this->renderComponentRenderArray([
      '#type' => 'component',
      '#component' => 'sdc_cartoteca:cartoteca-image-slider',
      '#props' => [
        'url' => '',
        'default_image' => self::DEFAULT_IMAGE,
      ],
    ]);

    $title = $crawler->filter('h2.sdc-cartoteca__slider-title');
    $this->assertCount(1, $title);
    $this->assertStringContainsString('Imágenes del mapa', $title->text());

    $img = $crawler->filter('img.sdc-cartoteca__image');
    $this->assertCount(1, $img);
    $this->assertStringContainsString('imagen-no-disponible', (string) $img->attr('src'));
  }

  /**
   * CA-A2 / CA8 — la ficha de info muestra su <h2> y el mensaje de espera.
   */
  public function testInfoInitialState(): void {
    $crawler = $this->renderComponentRenderArray([
      '#type' => 'component',
      '#component' => 'sdc_cartoteca:cartoteca-mapa-info',
      '#props' => ['url' => ''],
    ]);

    $title = $crawler->filter('h2.sdc-cartoteca__info-title');
    $this->assertCount(1, $title);
    $this->assertStringContainsString('Datos del mapa', $title->text());

    $this->assertStringContainsString('Esperando selección', $crawler->text());
  }

}
