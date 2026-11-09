<?php

declare(strict_types=1);

namespace Drupal\sdc_cartoteca\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sdc_cartoteca\Service\CartotecaApiService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulario de búsqueda de mapas de la Cartoteca IECA.
 *
 * El campo y el botón están al nivel raíz para un getValue() limpio.
 * Los dos componentes SDC están en un contenedor Bootstrap row/col-md-6.
 * Progressive enhancement: sin JS hace un rebuild completo de la página.
 */
class CartotecaSearchForm extends FormBase {

  public function __construct(
    private CartotecaApiService $apiService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('sdc_cartoteca.api_service'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'sdc_cartoteca_search';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $result = $form_state->get('cartoteca_result');

    $form['#attributes']['class'][] = 'sdc-cartoteca__form';

    $form['numero_control'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Número de control'),
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
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Buscando…'),
        ],
        'disable-refocus' => TRUE,
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
        'id' => 'cartoteca-images-wrapper',
        'class' => ['col-md-6'],
      ],
    ];
    $form['results']['images_wrapper']['component'] =
      $this->buildSliderComponent($result);

    $form['results']['info_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'cartoteca-info-wrapper',
        'class' => ['col-md-6'],
      ],
    ];
    $form['results']['info_wrapper']['component'] =
      $this->buildInfoComponent($result);

    return $form;
  }

  /**
   * Callback AJAX: devuelve los dos componentes actualizados.
   */
  public function ajaxCallback(
    array &$form,
    FormStateInterface $form_state,
  ): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand(
      '#cartoteca-images-wrapper',
      $form['results']['images_wrapper'],
    ));
    $response->addCommand(new ReplaceCommand(
      '#cartoteca-info-wrapper',
      $form['results']['info_wrapper'],
    ));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $numeroControl = (string) $form_state->getValue('numero_control');
    $result = $this->apiService->fetchMapa($numeroControl);
    $form_state->set('cartoteca_result', $result);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Construye el render array del componente slider.
   */
  private function buildSliderComponent(?array $result): array {
    return [
      '#type' => 'component',
      '#component' => 'sdc_cartoteca:cartoteca-image-slider',
      '#props' => [
        'slides' => $result['slides'] ?? [
          ['default' => CartotecaApiService::DEFAULT_IMAGE],
        ],
        'state' => $result['status'] ?? 'initial',
      ],
    ];
  }

  /**
   * Construye el render array del componente de información.
   */
  private function buildInfoComponent(?array $result): array {
    $state = $result['status'] ?? 'initial';
    $message = match ($state) {
      'loaded' => '',
      'initial' => (string) $this->t('Esperando selección'),
      default => $result['message'] ?? '',
    };

    return [
      '#type' => 'component',
      '#component' => 'sdc_cartoteca:cartoteca-mapa-info',
      '#props' => [
        'fields' => $result['fields'] ?? [],
        'state' => $state,
        'message' => $message,
      ],
    ];
  }

}
