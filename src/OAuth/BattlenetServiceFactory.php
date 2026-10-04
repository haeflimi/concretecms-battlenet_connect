<?php

namespace BattlenetConnect\OAuth;

use BattlenetConnect\BattlenetConfig;
use Concrete\Core\Authentication\Type\OAuth\HttpClient;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Http\Request;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use OAuth\Common\Consumer\Credentials;
use OAuth\Common\Http\Uri\Uri;
use OAuth\Common\Storage\SymfonySession;
use Symfony\Component\HttpFoundation\Session\Session;

class BattlenetServiceFactory
{
    /** @var Repository */
    protected $config;

    /** @var BattlenetConfig */
    protected $battlenetConfig;

    /** @var Session */
    protected $session;

    /** @var ResolverManagerInterface */
    protected $urlResolver;

    /** @var Request */
    protected $request;

    /** @var HttpClient */
    protected $httpClient;

    public function __construct(Repository $config, BattlenetConfig $battlenetConfig, Session $session, ResolverManagerInterface $urlResolver, Request $request, HttpClient $httpClient)
    {
        $this->config = $config;
        $this->battlenetConfig = $battlenetConfig;
        $this->session = $session;
        $this->urlResolver = $urlResolver;
        $this->request = $request;
        $this->httpClient = $httpClient;
    }

    public function createService(): BattlenetService
    {
        $credentials = new Credentials(
            (string) $this->config->get('auth.battlenet.client_id', ''),
            (string) $this->config->get('auth.battlenet.client_secret', ''),
            $this->getCallbackUrl()
        );
        $host = $this->battlenetConfig->getRegion() === BattlenetConfig::REGION_CN ? BattlenetService::HOST_CN : BattlenetService::HOST_GLOBAL;

        $scopes = [BattlenetService::SCOPE_OPENID];
        if ($this->battlenetConfig->isWowEnabled()) {
            // To import the WoW characters of the account
            $scopes[] = BattlenetService::SCOPE_WOW_PROFILE;
        }

        return new BattlenetService($credentials, $this->httpClient, new SymfonySession($this->session, false), $scopes, new Uri($host));
    }

    /**
     * The redirect URL that has to be added to the Battle.net client. Used for logging in and for attaching accounts.
     */
    public function getCallbackUrl(): string
    {
        $callbackUrl = $this->urlResolver->resolve(['/ccm/system/authentication/oauth2/battlenet/callback']);
        if ($callbackUrl->getHost() == '') {
            $callbackUrl = $callbackUrl->setHost($this->request->getHost());
            $callbackUrl = $callbackUrl->setScheme($this->request->getScheme());
        }

        return (string) $callbackUrl;
    }
}
