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
    public const CACHE_TTL     = 1800;
    public const FAILURE_TTL   = 300;
    public const MAX_REGIONS   = 5;
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

        /** @var array{results?: list<array{name?: string, latitude?: float, longitude?: float}>} $geo */
        $geo   = json_decode($http->get(self::GEOCODE_URL, ['query' => ['name' => $region, 'count' => 1, 'language' => 'ko']])->getBody(), true) ?? [];
        $place = $geo['results'][0] ?? null;

        if ($place === null || ! isset($place['latitude'], $place['longitude'])) {
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
            'name'        => $place['name'] ?? $region,
            'temperature' => (float) $now['temperature_2m'],
            'humidity'    => (int) ($now['relative_humidity_2m'] ?? 0),
            'wind'        => (float) ($now['wind_speed_10m'] ?? 0),
            'label'       => $label,
            'icon'        => $icon,
        ];
    }
}
