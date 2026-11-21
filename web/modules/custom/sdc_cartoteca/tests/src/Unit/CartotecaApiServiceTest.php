<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_cartoteca\Unit;

use Drupal\sdc_cartoteca\Service\CartotecaApiService;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Tests unitarios de CartotecaApiService.
 *
 * @group sdc_cartoteca
 * @coversDefaultClass \Drupal\sdc_cartoteca\Service\CartotecaApiService
 */
class CartotecaApiServiceTest extends UnitTestCase {

  /**
   * Crea un mock de ResponseInterface con el cuerpo dado.
   */
  private function makeResponse(string $body): ResponseInterface {
    $stream = $this->createMock(StreamInterface::class);
    $stream->method('__toString')->willReturn($body);
    $stream->method('getContents')->willReturn($body);

    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(200);
    $response->method('getBody')->willReturn($stream);

    return $response;
  }

  /**
   * Devuelve datos válidos simulados de la API (un registro, sin envolver).
   *
   * Los campos de texto simple se dan como [{value: ...}] para que
   * detectAndExtract los reconozca correctamente.
   *
   * @return array<string, mixed>
   *   Datos simulados de un registro de la API.
   */
  private function validApiData(): array {
    return [
      'field_001_numero_de_control' => [['value' => 'IECA1988118769']],
      'field_245_mencion_del_titulo' => [
        [
          'field_245_a' => [['value' => '[Mapamundi]']],
          'field_245_c' => [['value' => 'Isidoro de Sevilla']],
        ],
      ],
      'field_autor' => [['value' => 'Sevilla, Isidoro de']],
      'field_906_fecha_completa' => [
        ['field_906_b' => [['value' => '627? (1050?)']]],
      ],
      'field_siglo_de_inicio' => [['value' => 'XI']],
      'field_siglo_de_fin' => [['value' => 'XI']],
      'field_lugar' => [['label' => 'Mapamundi']],
      'field_comunidad_autonoma' => [['label' => 'Varias']],
      'field_provincia' => [['label' => 'Varias']],
      'field_municipio' => [['label' => 'Varios']],
      'field_pais_web' => [['label' => 'Varios']],
      'field_escala_numerica' => [['value' => 'Sin escala']],
      'field_idioma_web' => [['label' => 'Latín']],
      'field_categoria' => [['label' => 'Mapas']],
      'field_fuente_de_adquisicion' => [['label' => 'Biblioteca Nacional de España']],
      'field_imagen_destacada_media' => [
        [
          'original-view' => 'https://example.com/original',
          'big' => 'https://example.com/big',
          'medium' => 'https://example.com/medium',
          'small' => 'https://example.com/small',
        ],
      ],
      'field_imagenes_media' => [],
      'field_imagenes_b_n' => [],
    ];
  }

  /**
   * CA3 — valor en minúsculas se envía en mayúsculas a la API; estado 'loaded'.
   *
   * @covers ::fetchMapa
   */
  public function testApiCall(): void {
    $client = $this->createMock(ClientInterface::class);
    $client->expects($this->once())
      ->method('request')
      ->with(
        'GET',
        $this->anything(),
        $this->callback(
          fn($opts) => ($opts['query']['numero_de_control'] ?? '') === 'IECA1988118769',
        ),
      )
      ->willReturn($this->makeResponse(json_encode([$this->validApiData()])));

    $service = new CartotecaApiService($client);
    $result = $service->fetchMapa('ieca1988118769');

    $this->assertSame('loaded', $result['status']);
    $this->assertNotEmpty($result['fields']);
    $this->assertNotEmpty($result['slides']);
  }

  /**
   * CA10a — array vacío → estado 'not_found' + imagen por defecto.
   *
   * @covers ::fetchMapa
   */
  public function testEmptyArrayIsNotFound(): void {
    $client = $this->createMock(ClientInterface::class);
    $client->method('request')
      ->willReturn($this->makeResponse('[]'));

    $service = new CartotecaApiService($client);
    $result = $service->fetchMapa('IECA9999999999');

    $this->assertSame('not_found', $result['status']);
    $this->assertCount(1, $result['slides']);
    $this->assertStringContainsString('imagen-no-disponible', $result['slides'][0]['default']);
    $this->assertStringContainsString('no existe', $result['message']);
  }

  /**
   * CA10b — excepción HTTP → estado 'error' + imagen por defecto.
   *
   * @covers ::fetchMapa
   */
  public function testHttpErrorHandling(): void {
    $client = $this->createMock(ClientInterface::class);
    $client->method('request')
      ->willThrowException(
        new RequestException('Server error', new Request('GET', 'test')),
      );

    $service = new CartotecaApiService($client);
    $result = $service->fetchMapa('IECA1988118769');

    $this->assertSame('error', $result['status']);
    $this->assertCount(1, $result['slides']);
    $this->assertStringContainsString('imagen-no-disponible', $result['slides'][0]['default']);
    $this->assertStringContainsString('no está disponible', $result['message']);
  }

  /**
   * CA4b — sin imágenes en los tres campos → una sola diapositiva por defecto.
   *
   * @covers ::fetchMapa
   */
  public function testNoImagesUsesDefault(): void {
    $data = $this->validApiData();
    $data['field_imagen_destacada_media'] = [];
    $data['field_imagenes_media'] = [];
    $data['field_imagenes_b_n'] = [];

    $client = $this->createMock(ClientInterface::class);
    $client->method('request')
      ->willReturn($this->makeResponse(json_encode([$data])));

    $service = new CartotecaApiService($client);
    $result = $service->fetchMapa('IECA1988118769');

    $this->assertSame('loaded', $result['status']);
    $this->assertCount(1, $result['slides']);
    $this->assertStringContainsString('imagen-no-disponible', $result['slides'][0]['default']);
  }

  /**
   * CA7b — campos vacíos ([], '', null) no aparecen en fields.
   *
   * @covers ::fetchMapa
   */
  public function testEmptyFieldsAreStripped(): void {
    $data = $this->validApiData();
    $data['field_lugar'] = [];
    $data['field_comunidad_autonoma'] = '';
    $data['field_provincia'] = NULL;

    $client = $this->createMock(ClientInterface::class);
    $client->method('request')
      ->willReturn($this->makeResponse(json_encode([$data])));

    $service = new CartotecaApiService($client);
    $result = $service->fetchMapa('IECA1988118769');

    $labels = array_column($result['fields'], 'label');
    // Campos vaciados: no deben aparecer.
    $this->assertNotContains('Lugar', $labels);
    $this->assertNotContains('Comunidad autonoma', $labels);
    $this->assertNotContains('Provincia', $labels);
    // Campos con valor: deben aparecer (etiquetas generadas por fieldLabel()).
    $this->assertContains('Autor', $labels);
    $this->assertContains('Siglo de inicio', $labels);
  }

}
