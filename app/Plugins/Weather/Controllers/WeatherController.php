<?php

declare(strict_types=1);

namespace Plugins\Weather\Controllers;

use App\Controllers\BaseController;
use App\Models\PluginModel;
use Plugins\Weather\Libraries\WeatherClient;

class WeatherController extends BaseController
{
    public function index(): string
    {
        $client  = new WeatherClient();
        $regions = WeatherClient::parseRegions(model(PluginModel::class)->settingsFor('weather')['regions'] ?? '');
        $items   = [];

        foreach ($regions as $region) {
            $items[] = ['region' => $region, 'weather' => $client->current($region)];
        }

        return $this->render('\Plugins\Weather\Views\index', [
            'page'  => ['title' => '날씨', 'meta_title' => '날씨', 'meta_desc' => '', 'noindex' => true],
            'items' => $items,
        ]);
    }
}
