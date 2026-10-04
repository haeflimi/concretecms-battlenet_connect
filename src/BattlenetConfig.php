<?php

namespace BattlenetConnect;

use Concrete\Core\Package\PackageService;

/**
 * Access to the package settings, see config/settings.php for the available keys and their defaults.
 */
class BattlenetConfig
{
    const REGION_GLOBAL = 'global';
    const REGION_CN = 'cn';

    /** @var \Concrete\Core\Config\Repository\Liaison */
    protected $config;

    public function __construct(PackageService $packageService)
    {
        $this->config = $packageService->getClass('battlenet_connect')->getFileConfig();
    }

    public function get(string $key, $default = null)
    {
        return $this->config->get('settings.' . $key, $default);
    }

    public function save(string $key, $value): void
    {
        $this->config->save('settings.' . $key, $value);
    }

    /**
     * @return array<string, string>
     */
    public static function getRegionNames(): array
    {
        return [
            self::REGION_GLOBAL => t('Global (all regions except China)'),
            self::REGION_CN => t('China'),
        ];
    }

    public function getRegion(): string
    {
        $region = (string) $this->get('region', self::REGION_GLOBAL);

        return isset(self::getRegionNames()[$region]) ? $region : self::REGION_GLOBAL;
    }
}
