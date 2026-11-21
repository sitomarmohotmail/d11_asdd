<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_cartoteca\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests con JavaScript del bloque buscador Cartoteca.
 *
 * Cubren los criterios que dependen de la carga asíncrona (fetch + render en
 * cliente) y de la interacción del slider. Requieren un WebDriver
 * (chromedriver/Selenium) accesible en MINK_DRIVER_ARGS_WEBDRIVER, por lo que
 * viven fuera de la suite custom-functional. Pendientes de implementar: ver
 * specs/pendientes.md.
 *
 * @group sdc_cartoteca
 */
class CartotecaJavascriptTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['sdc_cartoteca', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalPlaceBlock('sdc_cartoteca', ['region' => 'content']);
  }

  /**
   * CA4 / CA7 — metadatos e imágenes tras búsqueda.
   */
  public function testInfoAndSliderAfterSearch(): void {
    $this->markTestIncomplete(
      'Requiere mock del http_client o acceso a red. Pendiente de test-service.',
    );
  }

  /**
   * CA5 — controles de slider cuando hay múltiples imágenes.
   */
  public function testSliderControls(): void {
    $this->markTestIncomplete(
      'Requiere mock del http_client o acceso a red. Pendiente de test-service.',
    );
  }

  /**
   * CA6 — selector de resolución refleja la imagen activa.
   */
  public function testResolutionSelector(): void {
    $this->markTestIncomplete(
      'Requiere mock del http_client o acceso a red. Pendiente de test-service.',
    );
  }

  /**
   * CA9 — actualización AJAX sin recarga de página.
   */
  public function testAjaxUpdate(): void {
    $this->markTestIncomplete(
      'Requiere implementar el flujo JS completo con WebDriver.',
    );
  }

  /**
   * CA-A3 — tabla de metadatos con aria-label y th scope="row".
   *
   * La tabla la genera el JS tras el fetch.
   */
  public function testMetadataTableAccessibility(): void {
    $this->markTestIncomplete(
      'La tabla se renderiza por JS tras el fetch; pendiente de test-service.',
    );
  }

}
