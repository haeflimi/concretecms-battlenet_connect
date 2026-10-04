<?php

namespace BattlenetConnect\Api;

use BattlenetConnect\BattlenetConfig;
use Concrete\Core\Cache\Level\ExpensiveCache;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Http\Client\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Minimal client for the World of Warcraft profile API.
 *
 * The characters of an account can only be read with the access token of the user (wow.profile scope), which is only
 * valid for 24 hours: that's done when they log in. The data of a character (summary, media, Mythic+) is public and
 * read with an application token (client credentials), so it can be updated at any time.
 *
 * @see https://develop.battle.net/documentation/world-of-warcraft/profile-apis
 */
class BattlenetApi
{
    protected const TOKEN_URL = 'https://oauth.battle.net/token';

    protected const TOKEN_CACHE_KEY = 'battlenet_connect/app_token';

    /** How often a rate limited request is retried */
    protected const MAX_RETRIES = 3;

    /** @var Client */
    protected $httpClient;

    /** @var Repository */
    protected $config;

    /** @var BattlenetConfig */
    protected $battlenetConfig;

    /** @var ExpensiveCache */
    protected $cache;

    public function __construct(Client $httpClient, Repository $config, BattlenetConfig $battlenetConfig, ExpensiveCache $cache)
    {
        $this->httpClient = $httpClient;
        $this->config = $config;
        $this->battlenetConfig = $battlenetConfig;
        $this->cache = $cache;
    }

    public function hasCredentials(): bool
    {
        return $this->getClientId() !== '' && $this->getClientSecret() !== '';
    }

    /**
     * All characters of the account of a user in a WoW version (of the configured region).
     *
     * @param string $userToken OAuth2 access token of the user with the wow.profile scope
     *
     * @return array[] the character objects of the account profile summary (id, name, realm, playable_class, playable_race, faction, level, ...)
     *
     * @throws BattlenetApiException
     */
    public function getAccountCharacters(string $version, string $userToken): array
    {
        $data = $this->request('profile/user/wow', $version, [], $userToken);
        $characters = [];
        foreach ($data['wow_accounts'] ?? [] as $account) {
            foreach ($account['characters'] ?? [] as $character) {
                $characters[] = $character;
            }
        }

        return $characters;
    }

    /**
     * @return array|null the character profile summary, null if the character was not found (renamed, transferred or
     *                    not played for a long time)
     *
     * @throws BattlenetApiException
     */
    public function getCharacter(string $version, string $realmSlug, string $name): ?array
    {
        return $this->request($this->getCharacterPath($realmSlug, $name), $version);
    }

    /**
     * @return array|null the character media (assets: avatar, inset, main, main-raw)
     *
     * @throws BattlenetApiException
     */
    public function getCharacterMedia(string $version, string $realmSlug, string $name): ?array
    {
        return $this->request($this->getCharacterPath($realmSlug, $name) . '/character-media', $version);
    }

    /**
     * Retail only.
     *
     * @return array|null the Mythic+ profile (current_mythic_rating), null if the character never did a Mythic+ dungeon
     *
     * @throws BattlenetApiException
     */
    public function getMythicKeystoneProfile(string $realmSlug, string $name): ?array
    {
        return $this->request($this->getCharacterPath($realmSlug, $name) . '/mythic-keystone-profile', BattlenetConfig::WOW_RETAIL);
    }

    /**
     * GET request to the WoW API of the configured region.
     *
     * @param string|null $userToken access token of a user, the application token is used if null
     *
     * @return array|null the decoded response, null for "404 Not Found"
     *
     * @throws BattlenetApiException
     */
    public function request(string $path, string $version, array $query = [], ?string $userToken = null): ?array
    {
        $url = 'https://' . $this->battlenetConfig->getWowApiRegion() . '.api.blizzard.com/' . ltrim($path, '/');
        $query += [
            'namespace' => $this->battlenetConfig->getWowProfileNamespace($version),
            'locale' => $this->battlenetConfig->getWowLocale(),
        ];
        $renewedToken = false;
        for ($attempt = 0; ; ++$attempt) {
            try {
                $response = $this->httpClient->request('GET', $url, [
                    'query' => $query,
                    'headers' => [
                        'Authorization' => 'Bearer ' . ($userToken ?? $this->getAppToken()),
                        'Accept' => 'application/json',
                    ],
                    'timeout' => 20,
                    'http_errors' => false,
                ]);
            } catch (GuzzleException $e) {
                throw new BattlenetApiException(t('Unable to reach the Battle.net API: %s', $e->getMessage()), 0, $e);
            }
            $status = $response->getStatusCode();
            if ($status === 429 && $attempt < self::MAX_RETRIES) {
                sleep(1);
                continue;
            }
            if ($status === 401 && $userToken === null && !$renewedToken) {
                // The cached application token expired early
                $this->getAppToken(true);
                $renewedToken = true;
                continue;
            }
            if ($status === 404) {
                return null;
            }
            $data = json_decode((string) $response->getBody(), true);
            if ($status >= 400 || !is_array($data)) {
                $message = is_array($data) ? ($data['detail'] ?? $data['error_description'] ?? $data['error'] ?? $response->getReasonPhrase()) : $response->getReasonPhrase();

                throw (new BattlenetApiException(t('Battle.net API error %s on %s: %s', $status, $path, $message), $status))
                    ->setResponseData(is_array($data) ? $data : null);
            }

            return $data;
        }
    }

    /**
     * Application access token (client credentials flow), cached until shortly before it expires.
     *
     * @throws BattlenetApiException
     */
    public function getAppToken(bool $renew = false): string
    {
        $item = $this->cache->getItem(self::TOKEN_CACHE_KEY);
        if (!$renew && !$item->isMiss() && is_string($item->get()) && $item->get() !== '') {
            return $item->get();
        }
        if (!$this->hasCredentials()) {
            throw new BattlenetApiException(t('The Battle.net client ID and secret are not configured.'));
        }
        try {
            $response = $this->httpClient->request('POST', self::TOKEN_URL, [
                'auth' => [$this->getClientId(), $this->getClientSecret()],
                'form_params' => ['grant_type' => 'client_credentials'],
                'timeout' => 20,
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            throw new BattlenetApiException(t('Unable to reach the Battle.net API: %s', $e->getMessage()), 0, $e);
        }
        $data = json_decode((string) $response->getBody(), true);
        if ($response->getStatusCode() !== 200 || empty($data['access_token'])) {
            $message = is_array($data) ? ($data['error_description'] ?? $data['error'] ?? '') : '';

            throw (new BattlenetApiException(t('Unable to get a Battle.net API token: %s', $message ?: $response->getReasonPhrase()), $response->getStatusCode()))
                ->setResponseData(is_array($data) ? $data : null);
        }

        $item->set((string) $data['access_token']);
        $item->expiresAfter(max(60, (int) ($data['expires_in'] ?? 3600) - 300));
        $this->cache->save($item);

        return (string) $data['access_token'];
    }

    protected function getCharacterPath(string $realmSlug, string $name): string
    {
        return 'profile/wow/character/' . rawurlencode($realmSlug) . '/' . rawurlencode(mb_strtolower($name));
    }

    protected function getClientId(): string
    {
        return trim((string) $this->config->get('auth.battlenet.client_id', ''));
    }

    protected function getClientSecret(): string
    {
        return trim((string) $this->config->get('auth.battlenet.client_secret', ''));
    }
}
