<?php

declare(strict_types=1);

namespace Drupal\sdc_cartoteca\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Formulario de búsqueda de mapas de la Cartoteca IECA.
 *
 * PHP solo renderiza la estructura inicial de los componentes SDC.
 * La consulta a la API, el parseo y el renderizado los gestiona el JS de
 * cada componente, que recibe la URL a través del evento 'cartoteca:load'.
 */
class CartotecaSearchForm extends FormBase {

  private const API_BASE = 'https://ws089.juntadeandalucia.es/institutodeestadisticaycartografia/cartoteca/api/v1/mapa-apitest?_format=json';

  private const DEFAULT_IMAGE = '/modules/custom/sdc_cartoteca/images/imagen-no-disponible.jpg';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'sdc_cartoteca_search';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   El array del formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   El estado del formulario.
   *
   * @return array<string, mixed>
   *   El array del formulario construido.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes']['class'][] = 'sdc-cartoteca__form';
    $form['#attributes']['data-cartoteca-instance'] = '';
    $form['#attributes']['novalidate'] = 'novalidate';
    $form['#attached']['library'][] = 'sdc_cartoteca/cartoteca-search-form';
    $form['#attached']['drupalSettings']['sdc_cartoteca'] = [
      'apiBase' => self::API_BASE,
      'defaultImage' => self::DEFAULT_IMAGE,
    ];

    $form['numero_control'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Número de control IECA1988118769'),
      '#maxlength' => 15,
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['sdc-cartoteca__input'],
        'autocomplete' => 'off',
        'placeholder' => $this->t('Ej. IECA1988118769'),
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Buscar'),
      '#attributes' => ['class' => ['btn', 'btn-primary']],
    ];
    $form['actions']['limpiar'] = [
      '#type' => 'button',
      '#value' => $this->t('Limpiar'),
      '#attributes' => [
        'class' => ['btn', 'btn-outline-secondary'],
        'data-cartoteca-limpiar' => '',
      ],
    ];

    // Fila de resultados: imágenes | información.
    $form['results'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['sdc-cartoteca__results', 'row', 'g-3', 'mt-2'],
      ],
    ];

    $form['results']['images_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['col-md-6'],
        'data-cartoteca-images' => '',
      ],
    ];
    $form['results']['images_wrapper']['component'] = [
      '#type' => 'component',
      '#component' => 'sdc_cartoteca:cartoteca-image-slider',
      '#props' => [
        'url' => '',
        'default_image' => self::DEFAULT_IMAGE,
      ],
    ];

    $form['results']['info_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['col-md-6'],
        'data-cartoteca-info' => '',
      ],
    ];
    $form['results']['info_wrapper']['component'] = [
      '#type' => 'component',
      '#component' => 'sdc_cartoteca:cartoteca-mapa-info',
      '#props' => ['url' => ''],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   El array del formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   El estado del formulario.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // La búsqueda y el renderizado los gestiona JavaScript.
  }

}
