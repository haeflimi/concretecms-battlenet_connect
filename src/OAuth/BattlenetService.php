<?php

namespace BattlenetConnect\OAuth;

use OAuth\Common\Consumer\CredentialsInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;
use OAuth\Common\Http\Uri\UriInterface;
use OAuth\Common\Storage\TokenStorageInterface;
use OAuth\OAuth2\Service\AbstractService;
use OAuth\OAuth2\Token\StdOAuth2Token;

/**
 * Battle.net OAuth2 service for the OAuth library used by the core. The library ships one, but it uses the regional
 * endpoints Blizzard shut down: this one uses oauth.battle.net (or oauth.battlenet.com.cn for China).
 *
 * @see https://develop.battle.net/documentation/guides/using-oauth
 */
class BattlenetService extends AbstractService
{
    /** Needed for the user info (account ID and BattleTag) */
    const SCOPE_OPENID = 'openid';

    const SCOPE_WOW_PROFILE = 'wow.profile';
    const SCOPE_SC2_PROFILE = 'sc2.profile';
    const SCOPE_D3_PROFILE = 'd3.profile';

    const HOST_GLOBAL = 'https://oauth.battle.net/';
    const HOST_CN = 'https://oauth.battlenet.com.cn/';

    /**
     * @param UriInterface|null $baseApiUri the login server, HOST_GLOBAL by default
     */
    public function __construct(
        CredentialsInterface $credentials,
        ClientInterface $httpClient,
        TokenStorageInterface $storage,
        $scopes = [],
        ?UriInterface $baseApiUri = null
    ) {
        // Always send and verify the state parameter
        parent::__construct($credentials, $httpClient, $storage, $scopes, $baseApiUri ?? new Uri(self::HOST_GLOBAL), true);
    }

    public function getAuthorizationEndpoint()
    {
        return new Uri($this->baseApiUri->getAbsoluteUri() . 'authorize');
    }

    public function getAccessTokenEndpoint()
    {
        return new Uri($this->baseApiUri->getAbsoluteUri() . 'token');
    }

    protected function getAuthorizationMethod()
    {
        return static::AUTHORIZATION_METHOD_HEADER_BEARER;
    }

    protected function parseAccessTokenResponse($responseBody)
    {
        $data = json_decode($responseBody, true);
        if (!is_array($data)) {
            throw new TokenResponseException('Unable to parse response.');
        }
        if (isset($data['error'])) {
            throw new TokenResponseException('Error in retrieving token: "' . ($data['error_description'] ?? $data['error']) . '"');
        }
        if (empty($data['access_token'])) {
            throw new TokenResponseException('No access token received.');
        }

        // Battle.net doesn't issue refresh tokens, access tokens are valid for 24 hours
        $token = new StdOAuth2Token();
        $token->setAccessToken($data['access_token']);
        if (isset($data['expires_in'])) {
            $token->setLifetime((int) $data['expires_in']);
        }
        unset($data['access_token'], $data['expires_in']);
        $token->setExtraParams($data);

        return $token;
    }
}
