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

    const WOW_RETAIL = 'retail';

    /** Regions of the WoW API */
    const WOW_API_REGIONS = ['eu', 'us', 'kr', 'tw'];

    const WOW_LOCALES = ['en_US', 'en_GB', 'de_DE', 'fr_FR', 'es_ES', 'it_IT', 'pt_PT', 'ru_RU', 'es_MX', 'pt_BR', 'ko_KR', 'zh_TW'];

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

    /**
     * @return array<string, string> names of all known WoW versions (see wow_namespaces), by key
     */
    public function getWowVersionNames(): array
    {
        $names = [
            'retail' => t('Retail'),
            'classic' => t('Classic (progression realms)'),
            'classic_era' => t('Classic Era'),
            'anniversary' => t('Classic Anniversary'),
        ];
        $result = [];
        foreach (array_keys($this->getWowNamespaces()) as $version) {
            $result[$version] = $names[$version] ?? ucwords(str_replace('_', ' ', $version));
        }

        return $result;
    }

    /**
     * @return string[] the WoW versions whose characters are imported, empty if the WoW features are disabled
     */
    public function getWowVersions(): array
    {
        $known = $this->getWowNamespaces();

        return array_values(array_filter((array) $this->get('wow_versions', []), static function ($version) use ($known) {
            return is_string($version) && isset($known[$version]);
        }));
    }

    public function isWowEnabled(): bool
    {
        return $this->getWowVersions() !== [];
    }

    public function getWowApiRegion(): string
    {
        $region = (string) $this->get('wow_api_region', 'eu');

        return in_array($region, self::WOW_API_REGIONS, true) ? $region : 'eu';
    }

    public function getWowLocale(): string
    {
        $locale = (string) $this->get('wow_locale', 'en_US');

        return in_array($locale, self::WOW_LOCALES, true) ? $locale : 'en_US';
    }

    /**
     * The API namespace of the profile data of a WoW version, e.g. "profile-classic1x-eu".
     */
    public function getWowProfileNamespace(string $version): string
    {
        $namespace = (string) ($this->getWowNamespaces()[$version] ?? '');

        return 'profile-' . ($namespace === '' ? '' : $namespace . '-') . $this->getWowApiRegion();
    }

    /**
     * @return array<string, string>
     */
    protected function getWowNamespaces(): array
    {
        $namespaces = [];
        foreach ((array) $this->get('wow_namespaces', []) as $version => $namespace) {
            if (is_string($version) && preg_match('/^[a-z0-9_]+$/', $version) && preg_match('/^[a-z0-9]*$/', (string) $namespace)) {
                $namespaces[$version] = (string) $namespace;
            }
        }

        return $namespaces ?: [self::WOW_RETAIL => ''];
    }
}
