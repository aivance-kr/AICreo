<?php

declare(strict_types=1);

namespace Plugins\Weather\Libraries;

use CodeIgniter\HTTP\CURLRequest;
use Throwable;

/**
 * Open-Meteo(키 불필요)로 지역 이름의 현재 날씨를 조회한다.
 *
 * 지역 이름 → 좌표(지오코딩) → 현재 날씨. 결과는 지역별로 30분 캐시한다.
 * 외부 API 실패는 요청 흐름으로 번지지 않고 null 을 돌려준다.
 */
class WeatherClient
{
    public const CACHE_TTL   = 1800;
    public const FAILURE_TTL = 300;
    public const MAX_REGIONS = 5;

    /**
     * 지오코딩이 짧은 한글 도시명을 못 찾거나 엉뚱한 동네를 돌려주는 경우("서울" 결과 없음,
     * "부산" → 경북의 작은 마을)가 있어, 주요 도시는 좌표를 직접 둔다.
     *
     * @var array<string, array{name: string, latitude: float, longitude: float}>
     */
    private const KNOWN_PLACES = [
        '서울' => ['name' => '서울', 'latitude' => 37.5665, 'longitude' => 126.9780],
        '부산' => ['name' => '부산', 'latitude' => 35.1796, 'longitude' => 129.0756],
        '인천' => ['name' => '인천', 'latitude' => 37.4563, 'longitude' => 126.7052],
        '대구' => ['name' => '대구', 'latitude' => 35.8714, 'longitude' => 128.6014],
        '대전' => ['name' => '대전', 'latitude' => 36.3504, 'longitude' => 127.3845],
        '광주' => ['name' => '광주', 'latitude' => 35.1595, 'longitude' => 126.8526],
        '울산' => ['name' => '울산', 'latitude' => 35.5384, 'longitude' => 129.3114],
        '세종' => ['name' => '세종', 'latitude' => 36.4800, 'longitude' => 127.2890],
        '수원' => ['name' => '수원', 'latitude' => 37.2636, 'longitude' => 127.0286],
        '춘천' => ['name' => '춘천', 'latitude' => 37.8813, 'longitude' => 127.7298],
        '강릉' => ['name' => '강릉', 'latitude' => 37.7519, 'longitude' => 128.8761],
        '청주' => ['name' => '청주', 'latitude' => 36.6424, 'longitude' => 127.4890],
        '전주' => ['name' => '전주', 'latitude' => 35.8242, 'longitude' => 127.1480],
        '포항' => ['name' => '포항', 'latitude' => 36.0190, 'longitude' => 129.3435],
        '창원' => ['name' => '창원', 'latitude' => 35.2280, 'longitude' => 128.6811],
        '제주' => ['name' => '제주', 'latitude' => 33.4996, 'longitude' => 126.5312],
    ];

    private const GEOCODE_URL  = 'https://geocoding-api.open-meteo.com/v1/search';
    private const FORECAST_URL = 'https://api.open-meteo.com/v1/forecast';

    public function __construct(private readonly ?CURLRequest $http = null)
    {
    }

    /**
     * "서울, 부산" 같은 입력을 지역 이름 목록으로 나눈다 (공백 제거·중복 제거·최대 5개).
     *
     * @return list<string>
     */
    public static function parseRegions(string $input): array
    {
        $names = array_filter(array_map('trim', explode(',', $input)), static fn (string $name): bool => $name !== '');

        return array_slice(array_values(array_unique($names)), 0, self::MAX_REGIONS);
    }

    /**
     * @return array{region: string, name: string, temperature: float, humidity: int, wind: float, label: string, icon: string}|null
     */
    public function current(string $region): ?array
    {
        $cacheKey = 'weather_' . md5($region);
        $cached   = cache($cacheKey);

        if (is_array($cached)) {
            /** @var array{region: string, name: string, temperature: float, humidity: int, wind: float, label: string, icon: string} $cached */
            return $cached;
        }

        // 실패도 짧게 기억한다 — 외부 API 장애 때 모든 공개 페이지 요청이 매번 느린 호출을 반복하지 않게
        if (cache($cacheKey . '_fail') !== null) {
            return null;
        }

        try {
            $result = $this->fetch($region);
        } catch (Throwable $e) {
            log_message('warning', '[Weather] 조회 실패: ' . $e->getMessage());
            $result = null;
        }

        if ($result === null) {
            cache()->save($cacheKey . '_fail', 1, self::FAILURE_TTL);

            return null;
        }

        cache()->save($cacheKey, $result, self::CACHE_TTL);

        return $result;
    }

    /**
     * WMO 날씨 코드 → 한글 설명·아이콘.
     *
     * @return array{0: string, 1: string}
     */
    public static function describe(int $code): array
    {
        return match (true) {
            $code === 0                     => ['맑음', '☀️'],
            $code <= 2                      => ['구름 조금', '🌤️'],
            $code === 3                     => ['흐림', '☁️'],
            in_array($code, [45, 48], true) => ['안개', '🌫️'],
            $code >= 51 && $code <= 57      => ['이슬비', '🌦️'],
            $code >= 61 && $code <= 67      => ['비', '🌧️'],
            $code >= 71 && $code <= 77      => ['눈', '❄️'],
            $code >= 80 && $code <= 82      => ['소나기', '🌦️'],
            $code === 85 || $code === 86    => ['눈보라', '🌨️'],
            $code >= 95                     => ['뇌우', '⛈️'],
            default                         => ['알 수 없음', '🌡️'],
        };
    }

    /**
     * @return array{region: string, name: string, temperature: float, humidity: int, wind: float, label: string, icon: string}|null
     */
    private function fetch(string $region): ?array
    {
        $http = $this->http ?? service('curlrequest', ['timeout' => 5, 'http_errors' => true]);

        $place = self::KNOWN_PLACES[$region] ?? $this->geocode($http, $region);

        if ($place === null) {
            return null;
        }

        /** @var array{current?: array{temperature_2m?: float, relative_humidity_2m?: int, wind_speed_10m?: float, weather_code?: int}} $forecast */
        $forecast = json_decode($http->get(self::FORECAST_URL, ['query' => [
            'latitude'  => $place['latitude'],
            'longitude' => $place['longitude'],
            'current'   => 'temperature_2m,relative_humidity_2m,wind_speed_10m,weather_code',
            'timezone'  => 'Asia/Seoul',
        ]])->getBody(), true) ?? [];
        $now = $forecast['current'] ?? null;

        if ($now === null || ! isset($now['temperature_2m'], $now['weather_code'])) {
            return null;
        }

        [$label, $icon] = self::describe((int) $now['weather_code']);

        return [
            'region'      => $region,
            'name'        => $place['name'],
            'temperature' => (float) $now['temperature_2m'],
            'humidity'    => (int) ($now['relative_humidity_2m'] ?? 0),
            'wind'        => (float) ($now['wind_speed_10m'] ?? 0),
            'label'       => $label,
            'icon'        => $icon,
        ];
    }

    /**
     * 목록에 없는 지역: 후보 여럿 중 인구가 가장 많은 곳을 고른다 (동명 소지역 오인 방지).
     *
     * @return array{name: string, latitude: float, longitude: float}|null
     */
    private function geocode(CURLRequest $http, string $region): ?array
    {
        /** @var array{results?: list<array{name?: string, latitude?: float, longitude?: float, population?: int}>} $geo */
        $geo     = json_decode($http->get(self::GEOCODE_URL, ['query' => ['name' => $region, 'count' => 10, 'language' => 'ko']])->getBody(), true) ?? [];
        $results = $geo['results'] ?? [];
        usort($results, static fn (array $a, array $b): int => ($b['population'] ?? 0) <=> ($a['population'] ?? 0));
        $place = $results[0] ?? null;

        if ($place === null || ! isset($place['latitude'], $place['longitude'])) {
            return null;
        }

        return ['name' => $place['name'], 'latitude' => $place['latitude'], 'longitude' => $place['longitude']];
    }
}
