<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_cartoteca\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\sdc_cartoteca\Form\CartotecaSearchForm;

/**
 * Tests de estructura y validación del formulario Cartoteca.
 *
 * Comprueban la salida del formulario a nivel de render array y su validación
 * de servidor, sin instalar un sitio completo. Cubren en kernel los criterios
 * que antes requerían un BrowserTestBase, a una fracción del coste.
 *
 * @group sdc_cartoteca
 */
class CartotecaFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'sdc_cartoteca',
    'system',
    'user',
  ];

  /**
   * Construye el formulario y devuelve su render array.
   *
   * @return array<string, mixed>
   *   El array del formulario sin procesar (buildForm).
   */
  private function buildCartotecaForm(): array {
    $form_object = CartotecaSearchForm::create($this->container);
    return $form_object->buildForm([], new FormState());
  }

  /**
   * CA2 — campo numero_control con maxlength=15, requerido y botón Buscar.
   *
   * El campo es un #type textfield con #title, lo que garantiza que el núcleo
   * de Drupal renderiza un <label> asociado (WCAG 1.3.1).
   */
  public function testFieldMaxlengthRequiredAndButton(): void {
    $form = $this->buildCartotecaForm();

    $this->assertSame('textfield', $form['numero_control']['#type']);
    $this->assertSame(15, $form['numero_control']['#maxlength']);
    $this->assertTrue($form['numero_control']['#required']);
    $this->assertNotEmpty((string) $form['numero_control']['#title']);

    $this->assertSame('submit', $form['actions']['submit']['#type']);
    $this->assertSame('Buscar', (string) $form['actions']['submit']['#value']);
  }

  /**
   * CA3b — la URL de la API viaja en drupalSettings, no en el formulario.
   */
  public function testApiUrlInDrupalSettings(): void {
    $form = $this->buildCartotecaForm();

    $settings = $form['#attached']['drupalSettings']['sdc_cartoteca'] ?? [];
    $this->assertArrayHasKey('apiBase', $settings);
    $this->assertStringContainsString('mapa-apitest', $settings['apiBase']);

    // El campo de texto no expone la URL como valor por defecto.
    $this->assertArrayNotHasKey('#default_value', $form['numero_control']);
  }

  /**
   * CA2b — enviar el formulario con el campo vacío produce error de #required.
   */
  public function testRequiredValidationError(): void {
    $form_state = new FormState();
    $form_state->setValues(['numero_control' => '']);

    $this->container->get('form_builder')
      ->submitForm(CartotecaSearchForm::class, $form_state);

    $this->assertArrayHasKey('numero_control', $form_state->getErrors());
  }

}
