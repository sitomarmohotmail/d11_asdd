<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_cartoteca\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * @group sdc_cartoteca
 */
class CartotecaFunctionalTest extends BrowserTestBase {

  protected static $modules = ['sdc_cartoteca', 'block', 'sdc'];

  protected $defaultTheme = 'stark';

  protected function setUp(): void {
    parent::setUp();
    $this->drupalPlaceBlock('sdc_cartoteca', ['region' => 'content']);
  }

  /**
   * CA2 — bloque renderiza campo de texto con maxlength=15 y required,
   * y botón "Buscar".
   */
  public function testFormRendered(): void {
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);

    $field = $this->getSession()->getPage()->findField('numero_control');
    $this->assertNotNull($field, 'Campo numero_control no encontrado.');
    $this->assertEquals('15', $field->getAttribute('maxlength'));
    $this->assertNotNull($field->getAttribute('required'));
    $this->assertSession()->buttonExists('Buscar');
  }

  /**
   * CA2b — envío con campo vacío muestra error de validación.
   */
  public function testFormRequiredValidation(): void {
    $this->drupalGet('<front>');
    $this->submitForm(['numero_control' => ''], 'Buscar');
    // Drupal Form API añade error cuando #required y el valor está vacío.
    $this->assertSession()->pageTextContains('campo es obligatorio');
  }

  /**
   * CA8 — estado inicial: imagen por defecto y mensaje 'Esperando selección'.
   */
  public function testInitialState(): void {
    $this->drupalGet('<front>');
    $this->assertSession()->pageTextContains('Esperando selección');
    $html = $this->getSession()->getPage()->getContent();
    $this->assertStringContainsString('imagen-no-disponible', $html);
  }

  /**
   * CA3b — URL de la API no aparece en el HTML de la página en ningún estado.
   */
  public function testApiUrlNotExposed(): void {
    $this->drupalGet('<front>');
    $html = $this->getSession()->getPage()->getContent();
    $this->assertStringNotContainsString(
      'institutodeestadisticaycartografia/cartoteca/api',
      $html,
    );
  }

  /**
   * CA7 / CA4 — después de búsqueda, se muestran campos de metadatos e imágenes.
   * Requiere acceso a red o servicio mock — pendiente.
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
      'Requiere WebDriverTestBase con JavaScript habilitado.',
    );
  }

}
