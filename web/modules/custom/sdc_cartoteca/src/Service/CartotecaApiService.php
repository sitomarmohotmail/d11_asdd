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
   * @return array<string, mixed>
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
      $slides = $this->normalizeImages($data[0]);
      $fields = $this->normalizeFields($data[0]);
      return [
        'status' => 'loaded',
        'slides' => $slides,
        'fields' => $fields,
        'message' => '',
      ];
    }
    catch (GuzzleException) {
      return $this->errorResult();
    }
  }

  /**
   * Construye la lista de diapositivas a partir de los tres campos de imagen.
   *
   * @param array<string, mixed> $raw
   *   Registro del mapa decodificado del JSON.
   *
   * @return array<int, array<string, string>>
   *   Lista de diapositivas; cada una es un mapa de tamaño a URL.
   */
  private function normalizeImages(array $raw): array {
    $slides = [];

    $destacada = $raw['field_imagen_destacada_media'][0] ?? [];
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
   *
   * @param array<string, mixed> $img
   *   Datos de una imagen del JSON.
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
   *
   * @param array<string, mixed> $img
   *   Datos de una imagen del JSON.
   *
   * @return array<string, string>
   *   Mapa de tamaño a URL; al menos la imagen por defecto.
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
   * Mapea los campos field_* del JSON a pares etiqueta-valor normalizados.
   *
   * @param array<string, mixed> $raw
   *   Registro del mapa decodificado del JSON.
   *
   * @return array<int, array{label: string, value: string}>
   *   Lista de pares etiqueta-valor con los campos no vacíos.
   */
  private function normalizeFields(array $raw): array {
    static $skip = [
      'field_imagenes_media',
      'field_imagen_destacada_media',
      'field_imagenes_b_n',
    ];

    $fields = [];
    foreach ($raw as $key => $records) {
      if (!str_starts_with($key, 'field_') || in_array($key, $skip, TRUE)) {
        continue;
      }
      $value = $this->detectAndExtract($key, $records);
      if ($value !== NULL && $value !== '') {
        $fields[] = ['label' => $this->fieldLabel($key), 'value' => $value];
      }
    }
    return $fields;
  }

  /**
   * Genera el título de un campo a partir de su clave.
   *
   * Field_906_fecha_completa  → "Fecha completa"
   * field_siglo_de_inicio     → "Siglo de inicio"
   */
  private function fieldLabel(string $key): string {
    $name = substr($key, 6);
    $name = (string) preg_replace('/^\d+_/', '', $name);
    return ucfirst(str_replace('_', ' ', $name));
  }

  /**
   * Detecta el tipo de campo por su estructura y extrae el valor.
   *
   * Tipos:
   *  - [{label}]                        → campo de taxonomía / referencia
   *  - [{value}]                        → campo de valor simple
   *  - [{field_NNN_x: [{value}], ...}]  → subcampos MARC.
   */
  private function detectAndExtract(string $key, mixed $records): ?string {
    if (!is_array($records) || empty($records)) {
      return NULL;
    }
    $first = $records[0] ?? NULL;
    if (!is_array($first)) {
      return $this->str((string) $first) ?: NULL;
    }
    // Campo de imagen: saltar.
    if (isset($first['original-view']) || isset($first['big'])) {
      return NULL;
    }
    // Campo de taxonomía / referencia: [{label: '...'}].
    if (array_key_exists('label', $first)) {
      $vals = $this->extractLabels($records);
      return $vals ? implode('; ', $vals) : NULL;
    }
    // Campo de valor simple: [{value: '...'}].
    if (array_key_exists('value', $first) && !is_array($first['value'])) {
      return $this->str($first['value']) ?: NULL;
    }
    // Campo MARC con subcampos: el prefijo se deduce de la clave exterior.
    if (preg_match('/^(field_\d+)_/', $key, $m)) {
      $vals = $this->extractSubfields($records, $m[1]);
      return $vals ? implode('; ', $vals) : NULL;
    }
    return NULL;
  }

  /**
   * Extrae subcampos MARC de un array de registros.
   *
   * Cada registro es un array con claves del tipo {prefix}_{sub} donde el
   * valor está en [0]['value']. Las claves _indicador_1 e _indicador_2
   * se omiten siempre.
   *
   * @param array<int, mixed> $records
   *   Array de registros del campo.
   * @param string $prefix
   *   Prefijo del campo (p.ej. 'field_245')
   * @param string[] $only
   *   Si no vacío, solo recoge estos subcampos (p.ej. ['a','c'])
   *
   * @return string[]
   *   Un string por registro con subcampos unidos por espacio.
   */
  private function extractSubfields(array $records, string $prefix, array $only = []): array {
    $result = [];
    foreach ($records as $record) {
      if (!is_array($record)) {
        continue;
      }
      $parts = [];
      foreach ($record as $key => $subfield) {
        if ($key === 'title' || $key === 'metatag'
            || str_ends_with($key, '_indicador_1')
            || str_ends_with($key, '_indicador_2')) {
          continue;
        }
        if ($only !== []) {
          $suffix = substr($key, strlen($prefix) + 1);
          if (!in_array($suffix, $only, TRUE)) {
            continue;
          }
        }
        $val = $this->str($subfield[0]['value'] ?? '');
        if ($val !== '') {
          $parts[] = $val;
        }
      }
      if ($parts !== []) {
        $result[] = implode(' ', $parts);
      }
    }
    return $result;
  }

  /**
   * Extrae los valores 'label' de campos de tipo referencia/taxonomía.
   *
   * Estructura: $raw['field_*'] = [{'label': '...', ...}, ...]
   *
   * @param array<int, mixed> $records
   *   Array de registros del campo.
   *
   * @return string[]
   *   Un string por elemento; vacíos omitidos.
   */
  private function extractLabels(array $records): array {
    return array_values(array_filter(array_map(
      fn($r) => $this->str(is_array($r) ? ($r['label'] ?? '') : $r),
      $records,
    )));
  }

  /**
   * Convierte un valor a cadena, devolviendo vacío para arrays.
   */
  private function str(mixed $v): string {
    return is_array($v) ? '' : trim((string) $v);
  }

  /**
   * Resultado cuando la API devuelve un array vacío (mapa no encontrado).
   *
   * @return array<string, mixed>
   *   Array con claves status, slides, fields y message.
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
   *
   * @return array<string, mixed>
   *   Array con claves status, slides, fields y message.
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
   *
   * @return array<string, string>
   *   Mapa con la clave 'default' y la ruta de la imagen por defecto.
   */
  private function defaultSlide(): array {
    return ['default' => self::DEFAULT_IMAGE];
  }

}
