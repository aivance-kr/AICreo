<?php

declare(strict_types=1);

namespace Plugins\Weather\Widgets;

use App\Libraries\Plugin\WidgetInterface;
use App\Models\PluginModel;
use Plugins\Weather\Libraries\WeatherClient;

/**
 * 사이드바 날씨 위젯의 데이터 준비. 지역은 플러그인 설정(regions)에서 읽는다.
 */
final class WeatherWidget implements WidgetInterface
{
    public function data(array $context = []): array
    {
        $client = new WeatherClient();
        $items  = [];

        foreach (WeatherClient::parseRegions(model(PluginModel::class)->settingsFor('weather')['regions'] ?? '') as $region) {
            $items[] = ['region' => $region, 'weather' => $client->current($region)];
        }

        return ['items' => $items];
    }
}
