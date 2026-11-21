<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_cartoteca\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test del error de validación inline del formulario Cartoteca (CA2b).
 *
 * Comprueba que, con el campo vacío, el error lo muestra inline_form_errors
 * asociado al campo (aria-invalid + mensaje inline), no solo en la barra de
 * mensajes.
 *
 * @group sdc_cartoteca
 */
class CartotecaFormErrorTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'sdc_cartoteca',
    'block',
    'inline_form_errors',
  ];

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
   * CA2b — el envío vacío muestra el error inline asociado al campo.
   */
  public function testEmptyFieldInlineError(): void {
    $this->drupalGet('user/login');
    $assert = $this->assertSession();

    // El formulario y su botón están presentes.
    $assert->buttonExists('Buscar');

    // Envío con el campo obligatorio vacío.
    $this->submitForm(['numero_control' => ''], 'Buscar');

    // inline_form_errors marca el campo como inválido (no en la barra).
    $assert->elementExists(
      'css',
      'input[name="numero_control"][aria-invalid="true"]',
    );
    // Y renderiza el mensaje de error inline, asociado al campo.
    $assert->elementExists('css', '.form-item--error-message');
  }

}
