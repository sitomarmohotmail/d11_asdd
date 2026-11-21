<?php

declare(strict_types=1);

namespace Drupal\sdc_cartoteca\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sdc_cartoteca\Form\CartotecaSearchForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Bloque buscador de mapas de la Cartoteca IECA.
 */
#[Block(
  id: 'sdc_cartoteca',
  admin_label: new TranslatableMarkup('Cartoteca IECA'),
  category: new TranslatableMarkup('Custom'),
)]
final class CartotecaBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Construye el bloque buscador de la Cartoteca.
   *
   * @param array<string, mixed> $configuration
   *   La configuración del plugin.
   * @param string $plugin_id
   *   El id del plugin.
   * @param mixed $plugin_definition
   *   La definición del plugin.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   El servicio constructor de formularios.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected FormBuilderInterface $formBuilder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   El contenedor de servicios.
   * @param array<string, mixed> $configuration
   *   La configuración del plugin.
   * @param string $plugin_id
   *   El id del plugin.
   * @param mixed $plugin_definition
   *   La definición del plugin.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   El render array del bloque.
   */
  public function build(): array {
    $build = $this->formBuilder->getForm(CartotecaSearchForm::class);
    $build['#attached']['library'][] = 'sdc_cartoteca/cartoteca-image-slider';
    $build['#attached']['library'][] = 'sdc_cartoteca/cartoteca-mapa-info';
    $build['#cache']['max-age'] = 0;
    return $build;
  }

}
