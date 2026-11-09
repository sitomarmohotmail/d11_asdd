<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_cartoteca\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests funcionales del bloque buscador Cartoteca.
 *
 * @group sdc_cartoteca
 */
class CartotecaFunctionalTest extends BrowserTestBase {

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
   * CA2 — bloque renderiza campo de texto con maxlength=15 y botón Buscar.
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
    // Drupal añade aria-invalid="true" al campo cuando falla #required.
    $field = $this->getSession()->getPage()->findField('numero_control');
    $this->assertNotNull($field, 'Campo numero_control no encontrado tras envío.');
    $this->assertEquals('true', $field->getAttribute('aria-invalid'));
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
   * CA3b — URL en drupalSettings, no en atributos de campos de formulario.
   */
  public function testApiUrlInDrupalSettings(): void {
    $this->drupalGet('<front>');
    $html = $this->getSession()->getPage()->getContent();
    // La URL base está en drupalSettings (JSON en script tag), accesible al JS.
    // JSON escapa '/' como '\/' por lo que buscamos la clave sin barras.
    $this->assertStringContainsString('"sdc_cartoteca"', $html);
    $this->assertStringContainsString('mapa-apitest', $html);
    // No debe estar en atributos value de campos del formulario.
    $this->assertStringNotContainsString('value="https://ws089', $html);
  }

  /**
   * CA7/CA4 — metadatos e imágenes tras búsqueda. Pendiente mock.
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
