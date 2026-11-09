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
 * @group sdc_cartoteca
 * @coversDefaultClass \Drupal\sdc_cartoteca\Service\CartotecaApiService
 */
class CartotecaApiServiceTest extends UnitTestCase {

  private function makeResponse(string $body): ResponseInterface {
    $stream = $this->createMock(StreamInterface::class);
    $stream->method('__toString')->willReturn($body);
    $stream->method('getContents')->willReturn($body);

    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(200);
    $response->method('getBody')->willReturn($stream);

    return $response;
  }

  private function validApiData(): array {
    return [
      'field_001_numero_de_control' => 'IECA1988118769',
      'field_245_mencion_del_titulo' => [
        'a' => '[Mapamundi]',
        'b' => [],
        'c' => 'Isidoro de Sevilla',
      ],
      'field_autor' => 'Sevilla, Isidoro de',
      'field_906_fecha_completa' => ['b' => '627? (1050?)'],
      'field_siglo_de_inicio' => 'XI',
      'field_siglo_de_fin' => 'XI',
      'field_lugar' => 'Mapamundi',
      'field_comunidad_autonoma' => 'Varias',
      'field_provincia' => 'Varias',
      'field_municipio' => 'Varios',
      'field_pais_web' => 'Varios',
      'field_escala_numerica' => 'Sin escala',
      'field_300_descripcion_fisica' => [
        'a' => '1 mapa',
        'b' => 'ms., tinta negra y roja sobre pergamino',
        'c' => 'hoja de 32 x 25 cm',
      ],
      'field_500_notas_generales' => ['a' => 'Nota descriptiva del mapamundi.'],
      'field_idioma_web' => 'Latín',
      'field_categoria' => 'Mapas',
      'field_fuente_de_adquisicion' => 'Biblioteca Nacional de España',
      'field_imagen_destacada_media' => [
        'original-view' => 'https://example.com/original',
        'big' => 'https://example.com/big',
        'medium' => 'https://example.com/medium',
        'small' => 'https://example.com/small',
      ],
      'field_imagenes_media' => [],
      'field_imagenes_b_n' => [],
    ];
  }

  /**
   * @covers ::fetchMapa
   * CA3 — valor en minúsculas se envía en mayúsculas a la API; estado 'loaded'.
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
      ->willReturn($this->makeResponse(json_encode($this->validApiData())));

    $service = new CartotecaApiService($client);
    $result = $service->fetchMapa('ieca1988118769');

    $this->assertSame('loaded', $result['status']);
    $this->assertNotEmpty($result['fields']);
    $this->assertNotEmpty($result['slides']);
  }

  /**
   * @covers ::fetchMapa
   * CA10a — array vacío → estado 'not_found' + imagen por defecto.
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
   * @covers ::fetchMapa
   * CA10b — excepción HTTP → estado 'error' + imagen por defecto.
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
   * @covers ::fetchMapa
   * CA4b — sin imágenes en los tres campos → una sola diapositiva por defecto.
   */
  public function testNoImagesUsesDefault(): void {
    $data = $this->validApiData();
    $data['field_imagen_destacada_media'] = [];
    $data['field_imagenes_media'] = [];
    $data['field_imagenes_b_n'] = [];

    $client = $this->createMock(ClientInterface::class);
    $client->method('request')
      ->willReturn($this->makeResponse(json_encode($data)));

    $service = new CartotecaApiService($client);
    $result = $service->fetchMapa('IECA1988118769');

    $this->assertSame('loaded', $result['status']);
    $this->assertCount(1, $result['slides']);
    $this->assertStringContainsString('imagen-no-disponible', $result['slides'][0]['default']);
  }

  /**
   * @covers ::fetchMapa
   * CA7b — campos vacíos ([], '', null) no aparecen en fields.
   */
  public function testEmptyFieldsAreStripped(): void {
    $data = $this->validApiData();
    $data['field_lugar'] = [];
    $data['field_comunidad_autonoma'] = '';
    $data['field_provincia'] = null;

    $client = $this->createMock(ClientInterface::class);
    $client->method('request')
      ->willReturn($this->makeResponse(json_encode($data)));

    $service = new CartotecaApiService($client);
    $result = $service->fetchMapa('IECA1988118769');

    $labels = array_column($result['fields'], 'label');
    $this->assertNotContains('Lugar', $labels);
    $this->assertNotContains('Comunidad autónoma', $labels);
    $this->assertNotContains('Provincia', $labels);
    $this->assertContains('Nº de control', $labels);
    $this->assertContains('Título', $labels);
  }

}
