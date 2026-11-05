<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin;

use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Plugins\Weather\Libraries\WeatherClient;
use RuntimeException;

/**
 * @internal
 */
final class WeatherClientTest extends CIUnitTestCase
{
    public static function setUpBeforeClass(): void
    {
        // 플러그인 네임스페이스는 활성화돼야 오토로드된다 — 테스트에서는 파일을 직접 읽는다
        class_exists(WeatherClient::class, false) || require_once APPPATH . 'Plugins/Weather/Libraries/WeatherClient.php';
    }

    protected function tearDown(): void
    {
        cache()->clean();
        parent::tearDown();
    }

    public function testParseRegionsTrimsDedupesAndCaps(): void
    {
        $this->assertSame(['서울', '부산'], WeatherClient::parseRegions(' 서울 , 부산,, 서울'));
        $this->assertSame([], WeatherClient::parseRegions(' , '));
        $this->assertCount(WeatherClient::MAX_REGIONS, WeatherClient::parseRegions('a,b,c,d,e,f,g'));
    }

    public function testDescribeMapsWeatherCodes(): void
    {
        $this->assertSame('맑음', WeatherClient::describe(0)[0]);
        $this->assertSame('흐림', WeatherClient::describe(3)[0]);
        $this->assertSame('비', WeatherClient::describe(63)[0]);
        $this->assertSame('눈', WeatherClient::describe(73)[0]);
        $this->assertSame('뇌우', WeatherClient::describe(95)[0]);
    }

    public function testCurrentGeocodesThenReadsForecastAndCaches(): void
    {
        $http = $this->createMock(CURLRequest::class);
        $http->expects($this->exactly(2))->method('get')->willReturnOnConsecutiveCalls(
            $this->body('{"results":[{"name":"서울","latitude":37.5,"longitude":127.0}]}'),
            $this->body('{"current":{"temperature_2m":21.5,"relative_humidity_2m":60,"wind_speed_10m":3.2,"weather_code":0}}'),
        );

        $client = new WeatherClient($http);
        $result = $client->current('서울-테스트');

        $this->assertSame('서울', $result['name'] ?? null);
        $this->assertSame(21.5, $result['temperature'] ?? null);
        $this->assertSame('맑음', $result['label'] ?? null);

        // 두 번째 호출은 캐시 — HTTP 는 위 exactly(2) 로 더 호출되지 않아야 한다
        $this->assertSame($result, $client->current('서울-테스트'));
    }

    public function testKnownCitySkipsGeocodingAndUnknownPicksMostPopulous(): void
    {
        $forecast = '{"current":{"temperature_2m":10.0,"relative_humidity_2m":50,"wind_speed_10m":1.0,"weather_code":3}}';

        // 주요 도시는 지오코딩 없이 예보 1회
        $known = $this->createMock(CURLRequest::class);
        $known->expects($this->once())->method('get')->willReturn($this->body($forecast));
        $this->assertSame('부산', (new WeatherClient($known))->current('부산')['name'] ?? null);

        // 그 밖의 지역은 인구가 가장 많은 후보
        $other = $this->createMock(CURLRequest::class);
        $other->expects($this->exactly(2))->method('get')->willReturnOnConsecutiveCalls(
            $this->body('{"results":[{"name":"작은마을","latitude":1,"longitude":1,"population":10},{"name":"큰도시","latitude":2,"longitude":2,"population":9000}]}'),
            $this->body($forecast),
        );
        $this->assertSame('큰도시', (new WeatherClient($other))->current('테스트시')['name'] ?? null);
    }

    public function testCurrentReturnsNullWhenRegionNotFoundOrApiFails(): void
    {
        $http = $this->createMock(CURLRequest::class);
        $http->method('get')->willReturn($this->body('{}'));
        $this->assertNull((new WeatherClient($http))->current('없는곳'));

        $failing = $this->createMock(CURLRequest::class);
        $failing->expects($this->once())->method('get')->willThrowException(new RuntimeException('timeout'));
        $client = new WeatherClient($failing);
        $this->assertNull($client->current('실패지역'));
        // 실패는 잠시 기억돼 재호출하지 않는다 (위 mock 은 1회만 허용하는 것과 같다)
        $this->assertNull($client->current('실패지역'));
    }

    private function body(string $json): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($json);

        return $response;
    }
}
