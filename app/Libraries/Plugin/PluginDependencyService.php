<?php

declare(strict_types=1);

namespace App\Libraries\Plugin;

use App\Models\PluginModel;
use App\Models\UserPluginModel;

final class PluginDependencyService
{
    /**
     * @param array<string, mixed> $plugin plugins 테이블 한 행
     *
     * @return list<string> 사용자가 보유하지 않은 선행 플러그인의 표시 이름
     */
    public function missingForUser(int $userId, array $plugin): array
    {
        /** @var mixed $decoded */
        $decoded  = json_decode((string) $plugin['manifest'], true);
        $required = is_array($decoded['requires']['plugins'] ?? null) ? $decoded['requires']['plugins'] : [];

        $missing     = [];
        $pluginModel = new PluginModel();
        $userPlugins = new UserPluginModel();

        foreach ($required as $requiredKey) {
            if (! is_string($requiredKey)) {
                continue;
            }

            $entitlement = $userPlugins->findEntitlement($userId, $requiredKey);

            if (($entitlement?->accessLevel() ?? AccessLevel::None) === AccessLevel::None) {
                $requiredPlugin = $pluginModel->findByKey($requiredKey);
                $missing[]      = $requiredPlugin !== null ? (string) $requiredPlugin['name'] : $requiredKey;
            }
        }

        return $missing;
    }
}
