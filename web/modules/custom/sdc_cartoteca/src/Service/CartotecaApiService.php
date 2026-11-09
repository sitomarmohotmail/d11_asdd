<?php

declare(strict_types=1);

namespace Drupal\sdc_cartoteca\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Consulta la API REST de la Cartoteca IECA y normaliza los datos.
 */
class CartotecaApiService {

  public const DEFAULT_IMAGE = '/modules/custom/sdc_cartoteca/images/imagen-no-disponible.jpg';

  private const API_BASE_URL = 'https://ws089.juntadeandalucia.es/institutodeestadisticaycartografia/cartoteca/api/v1/mapa-apitest';

  private const IMAGE_SIZES = ['original-view', 'big', 'medium', 'small'];

  public function __construct(
    private ClientInterface $httpClient,
  ) {}

  /**
   * Busca un mapa por número de control y devuelve los datos normalizados.
   *
   * @return array
   *   Array con claves status, slides, fields y message.
   */
  public function fetchMapa(string $numeroControl): array {
    $numeroControl = strtoupper(trim($numeroControl));

    try {
      $response = $this->httpClient->request('GET', self::API_BASE_URL, [
        'query' => [
          '_format' => 'json',
          'numero_de_control' => $numeroControl,
        ],
        'timeout' => 10,
      ]);

      $data = json_decode((string) $response->getBody(), TRUE);

      if (!is_array($data) || empty($data)) {
        return $this->notFoundResult();
      }

      return [
        'status' => 'loaded',
        'slides' => $this->normalizeImages($data),
        'fields' => $this->normalizeFields($data),
        'message' => '',
      ];
    }
    catch (GuzzleException) {
      return $this->errorResult();
    }
  }

  /**
   * Construye la lista de diapositivas a partir de los tres campos de imagen.
   */
  private function normalizeImages(array $raw): array {
    $slides = [];

    $destacada = $raw['field_imagen_destacada_media'] ?? [];
    if (!empty($destacada) && is_array($destacada) && $this->hasSizes($destacada)) {
      $slides[] = $this->extractSizes($destacada);
    }

    foreach ((array) ($raw['field_imagenes_media'] ?? []) as $img) {
      if (!empty($img) && is_array($img) && $this->hasSizes($img)) {
        $slides[] = $this->extractSizes($img);
      }
    }

    foreach ((array) ($raw['field_imagenes_b_n'] ?? []) as $img) {
      if (!empty($img) && is_array($img) && $this->hasSizes($img)) {
        $slides[] = $this->extractSizes($img);
      }
    }

    return $slides ?: [$this->defaultSlide()];
  }

  /**
   * Comprueba si la imagen tiene al menos una variante de tamaño.
   */
  private function hasSizes(array $img): bool {
    foreach (self::IMAGE_SIZES as $size) {
      if (!empty($img[$size])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Extrae las variantes de tamaño disponibles de una imagen.
   */
  private function extractSizes(array $img): array {
    $sizes = [];
    foreach (self::IMAGE_SIZES as $size) {
      if (!empty($img[$size])) {
        $sizes[$size] = $img[$size];
      }
    }
    return $sizes ?: $this->defaultSlide();
  }

  /**
   * Mapea los campos MARC del JSON a pares etiqueta-valor, omitiendo vacíos.
   */
  private function normalizeFields(array $raw): array {
    $fields = [];

    $this->add($fields, 'Nº de control', $raw['field_001_numero_de_control'] ?? NULL);

    $t245 = $raw['field_245_mencion_del_titulo'] ?? [];
    $titulo = trim(($this->str($t245['a'] ?? '')) . ' ' . ($this->str($t245['b'] ?? '')));
    $this->add($fields, 'Título', $titulo ?: NULL);
    $this->add($fields, 'Mención de responsabilidad', $t245['c'] ?? NULL);

    $this->add($fields, 'Autor', $raw['field_autor'] ?? NULL);

    $periodo = $raw['field_906_fecha_completa']['b'] ?? NULL;
    if (empty($periodo)) {
      $i = $this->str($raw['field_fecha_inicio'] ?? '');
      $f = $this->str($raw['field_fecha_fin'] ?? '');
      $periodo = ($i && $f && $i !== $f) ? "$i - $f" : ($i ?: NULL);
    }
    $this->add($fields, 'Período', $periodo);

    $si = $this->str($raw['field_siglo_de_inicio'] ?? '');
    $sf = $this->str($raw['field_siglo_de_fin'] ?? '');
    $siglo = ($si && $sf && $si !== $sf) ? "$si - $sf" : ($si ?: NULL);
    $this->add($fields, 'Siglo', $siglo);

    $this->add($fields, 'Lugar', $raw['field_lugar'] ?? NULL);
    $this->add($fields, 'Comunidad autónoma', $raw['field_comunidad_autonoma'] ?? NULL);
    $this->add($fields, 'Provincia', $raw['field_provincia'] ?? NULL);
    $this->add($fields, 'Municipio', $raw['field_municipio'] ?? NULL);
    $this->add($fields, 'País', $raw['field_pais_web'] ?? NULL);

    $escala = $raw['field_escala_numerica'] ?? NULL;
    if (empty($escala)) {
      $escala = $raw['field_255_datos_matematicos_cartograficos']['a'] ?? NULL;
    }
    $this->add($fields, 'Escala', $escala);

    $desc300 = $raw['field_300_descripcion_fisica'] ?? [];
    $descParts = array_filter(array_map(
      fn($k) => $this->str($desc300[$k] ?? ''),
      ['a', 'b', 'c'],
    ));
    $this->add($fields, 'Descripción física', $descParts ? implode(', ', $descParts) : NULL);

    $this->add($fields, 'Notas generales', $raw['field_500_notas_generales']['a'] ?? NULL);
    $this->add($fields, 'Idioma', $raw['field_idioma_web'] ?? NULL);
    $this->add($fields, 'Categoría', $raw['field_categoria'] ?? NULL);
    $this->add($fields, 'Fuente', $raw['field_fuente_de_adquisicion'] ?? NULL);

    return $fields;
  }

  /**
   * Añade un par etiqueta-valor si el valor no es vacío.
   */
  private function add(array &$fields, string $label, mixed $value): void {
    if (is_array($value)) {
      $value = empty($value) ? NULL : implode(', ', array_filter(array_map([$this, 'str'], $value)));
    }
    $value = $this->str((string) ($value ?? ''));
    if ($value !== '') {
      $fields[] = ['label' => $label, 'value' => $value];
    }
  }

  /**
   * Convierte un valor a cadena, devolviendo vacío para arrays.
   */
  private function str(mixed $v): string {
    return is_array($v) ? '' : trim((string) $v);
  }

  /**
   * Resultado cuando la API devuelve un array vacío (mapa no encontrado).
   */
  private function notFoundResult(): array {
    return [
      'status' => 'not_found',
      'slides' => [$this->defaultSlide()],
      'fields' => [],
      'message' => 'No se han encontrado datos para ese número de control o el mapa no existe',
    ];
  }

  /**
   * Resultado de error HTTP (servicio no disponible).
   */
  private function errorResult(): array {
    return [
      'status' => 'error',
      'slides' => [$this->defaultSlide()],
      'fields' => [],
      'message' => 'El servicio de la Cartoteca no está disponible en este momento',
    ];
  }

  /**
   * Devuelve la diapositiva con la imagen por defecto.
   */
  private function defaultSlide(): array {
    return ['default' => self::DEFAULT_IMAGE];
  }

}
