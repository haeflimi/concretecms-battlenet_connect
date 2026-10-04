<?php

namespace Concrete\Package\BattlenetConnect\Authentication\Battlenet;

use BattlenetConnect\BattlenetAccounts;
use BattlenetConnect\BattlenetConfig;
use BattlenetConnect\Entity\BattlenetProfile;
use BattlenetConnect\OAuth\BattlenetService;
use BattlenetConnect\OAuth\BattlenetServiceFactory;
use Concrete\Core\Attribute\Key\UserKey;
use Concrete\Core\Authentication\Type\OAuth\OAuth2\GenericOauth2TypeController;
use Concrete\Core\Form\Service\Widget\GroupSelector;
use Concrete\Core\Routing\RedirectResponse;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Concrete\Core\User\Group\GroupRepository;
use Concrete\Core\User\User;
use Concrete\Core\User\UserInfo;
use Concrete\Core\User\UserInfoRepository;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Battle.net login. The core OAuth2 controller provides the routes (/ccm/system/authentication/oauth2/battlenet/...),
 * the user binding storage (OauthUserMap, the binding is the Battle.net account ID) and the attach/detach integration
 * in the user profile. The flows are overridden to return responses and to confirm the email address on registration:
 * Battle.net doesn't share it.
 */
class Controller extends GenericOauth2TypeController
{
    protected const SESSION_PENDING = 'battlenet_connect.pending_registration';

    /** The core routes the attach flow through the login callback too, it is recognized by this state prefix */
    protected const ATTACH_STATE_PREFIX = 'attach:';

    public function getHandle()
    {
        return 'battlenet';
    }

    public function getAuthenticationTypeIconHTML()
    {
        return '<i class="fab fa-battle-net"></i>';
    }

    public function supportsRegistration()
    {
        return (bool) $this->app->make('config')->get('auth.battlenet.registration.enabled', false);
    }

    public function registrationGroupID()
    {
        return (int) $this->app->make('config')->get('auth.battlenet.registration.group');
    }

    /**
     * @return BattlenetService
     */
    public function getService()
    {
        if (!$this->service) {
            $this->service = $this->app->make(BattlenetServiceFactory::class)->createService();
        }

        return $this->service;
    }

    public function getUniqueId()
    {
        return $this->getBindingForUser($this->app->make(User::class));
    }

    public function handle_authentication_attempt()
    {
        if ($this->app->make(User::class)->isRegistered()) {
            return $this->errorResponse(t('You are already logged in.'));
        }

        return new RedirectResponse((string) $this->getService()->getAuthorizationUri($this->getAdditionalRequestParameters()));
    }

    public function handle_authentication_callback()
    {
        if (strpos((string) $this->request->query->get('state'), self::ATTACH_STATE_PREFIX) === 0) {
            return $this->handle_attach_callback();
        }
        if ($this->app->make(User::class)->isRegistered()) {
            return $this->errorResponse(t('You are already logged in.'));
        }

        $battlenetUser = $this->fetchBattlenetUser();
        if ($battlenetUser === null) {
            return $this->errorResponse(t('Battle.net authentication failed. Please try again.'));
        }

        $userID = $this->getBoundUserID($battlenetUser['id']);
        if ($userID) {
            $userInfo = $this->app->make(UserInfoRepository::class)->getByID($userID);
            if (!$userInfo) {
                return $this->errorResponse(t('Failed to complete authentication.'));
            }
            // Accounts registered through Battle.net stay inactive until the email address has been confirmed.
            if (!$userInfo->isActive() && $userInfo->isValidated()) {
                return $this->errorResponse(t($this->app->make('config')->get('concrete.user.deactivation.message')));
            }
            if (!$userInfo->isActive() || ($this->app->make('config')->get('concrete.user.registration.validate_email') && !$userInfo->isValidated())) {
                return $this->errorResponse(t('This account has not yet been validated. Please check the email associated with this account and follow the link it contains.'));
            }

            $user = User::loginByUserID($userID);
            if (!$user || $user->isError()) {
                return $this->errorResponse(t('Failed to complete authentication.'));
            }
            $this->app->make('session')->migrate();
            $this->onConnected($battlenetUser, (int) $userID);

            return $this->completeAuthentication($user);
        }

        if ($this->supportsRegistration()) {
            // Battle.net doesn't share the email address, so we have to ask the visitor for it before creating the account.
            $this->app->make('session')->set(self::SESSION_PENDING, $battlenetUser);
            $token = $this->app->make('token')->generate('battlenet_register');

            return $this->redirectResponse('/login/callback/battlenet/handle_register/' . $token);
        }

        return $this->errorResponse(t('No local user account is associated with this Battle.net account. Please log in with a local account and connect your Battle.net account from your user profile.'));
    }

    public function handle_register($token = null)
    {
        $session = $this->app->make('session');
        $battlenetUser = $session->get(self::SESSION_PENDING);
        $tokenValidator = $this->app->make('token');
        if (!$this->supportsRegistration() || !is_array($battlenetUser) || empty($battlenetUser['id'])
            || (!$tokenValidator->validate('battlenet_register', $token) && !$tokenValidator->validate('battlenet_register'))
        ) {
            $this->set('error', t('Your registration session has expired. Please sign in with Battle.net again.'));

            return;
        }

        $this->set('show_email', true);
        $this->set('username', $battlenetUser['battletag'] ?? $battlenetUser['id']);
        $this->set('token', $tokenValidator);

        $email = $this->request->request->get('uEmail');
        if (!is_string($email) || $email === '') {
            return;
        }
        $email = trim($email);
        if (!$this->app->make('helper/validation/strings')->email($email)) {
            $this->set('error', t('Please enter a valid email address.'));

            return;
        }
        if ($this->app->make(UserInfoRepository::class)->getByEmail($email)) {
            $this->set('error', t('A user account already exists for this email, please log in and attach your Battle.net account from your account page.'));

            return;
        }

        try {
            $userInfo = $this->registerUser($battlenetUser, $email);
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            $this->set('error', t('Unable to create new account.'));

            return;
        }
        $session->remove(self::SESSION_PENDING);
        $this->onConnected($battlenetUser, (int) $userInfo->getUserID());

        $this->set('show_email', false);
        $this->set('message', t('Your account has been created. We sent an email to %s: click on the link it contains to confirm your email address, then you can log in with Battle.net.', $email));
    }

    public function handle_attach_attempt()
    {
        if (!$this->app->make(User::class)->isRegistered()) {
            return $this->errorResponse(t('A user must be logged in to attach a Battle.net account.'));
        }

        $state = self::ATTACH_STATE_PREFIX . bin2hex(random_bytes(16));

        return new RedirectResponse((string) $this->getService()->getAuthorizationUri($this->getAdditionalRequestParameters() + ['state' => $state]));
    }

    public function handle_attach_callback()
    {
        $user = $this->app->make(User::class);
        if (!$user->isRegistered()) {
            return $this->redirectResponse('/login');
        }

        $battlenetUser = $this->fetchBattlenetUser();
        if ($battlenetUser === null) {
            return $this->errorResponse(t('Battle.net authentication failed. Please try again.'));
        }

        $userID = (int) $user->getUserID();
        $boundUserID = $this->getBoundUserID($battlenetUser['id']);
        if ($boundUserID && (int) $boundUserID !== $userID) {
            return $this->errorResponse(t('This Battle.net account is already connected to another user account.'));
        }

        try {
            $this->bindUserID($userID, $battlenetUser['id']);
        } catch (Throwable $e) {
            return $this->errorResponse(t('Unable to attach user.'));
        }
        $this->onConnected($battlenetUser, $userID);

        return $this->successResponse(t('Successfully attached.'));
    }

    public function handle_detach_attempt()
    {
        $user = $this->app->make(User::class);
        if (!$user->isRegistered()) {
            return $this->redirectResponse('/login');
        }

        $binding = $this->getBindingForUser($user);
        if ($binding !== null) {
            try {
                $this->getBindingService()->clearBinding($user->getUserID(), $binding, $this->getHandle(), true);
                $this->app->make(BattlenetAccounts::class)->deleteProfile($binding);
            } catch (Throwable $e) {
                return $this->errorResponse(t('Unable to detach account.'));
            }
        }

        return $this->successResponse(t('Successfully detached.'));
    }

    public function saveAuthenticationType($args)
    {
        $config = $this->app->make('config');
        $config->save('auth.battlenet.client_id', trim((string) ($args['client_id'] ?? '')));
        $config->save('auth.battlenet.client_secret', trim((string) ($args['client_secret'] ?? '')));
        $config->save('auth.battlenet.registration.enabled', !empty($args['registration_enabled']));
        $config->save('auth.battlenet.registration.group', (int) ($args['registration_group'] ?? 0));
        $region = (string) ($args['region'] ?? '');
        $this->app->make(BattlenetConfig::class)->save('region', isset(BattlenetConfig::getRegionNames()[$region]) ? $region : BattlenetConfig::REGION_GLOBAL);
    }

    public function edit()
    {
        $config = $this->app->make('config');
        $this->set('form', $this->app->make('helper/form'));
        $this->set('groupSelector', $this->app->make(GroupSelector::class));
        $this->set('callbackUrl', $this->app->make(BattlenetServiceFactory::class)->getCallbackUrl());
        $this->set('clientId', (string) $config->get('auth.battlenet.client_id', ''));
        $this->set('clientSecret', (string) $config->get('auth.battlenet.client_secret', ''));
        $this->set('region', $this->app->make(BattlenetConfig::class)->getRegion());
        $this->set('regions', BattlenetConfig::getRegionNames());
        $this->set('registrationEnabled', (bool) $config->get('auth.battlenet.registration.enabled'));
        $registrationGroupID = (int) $config->get('auth.battlenet.registration.group');
        $registrationGroup = $registrationGroupID === 0 ? null : $this->app->make(GroupRepository::class)->getGroupById($registrationGroupID);
        $this->set('registrationGroup', $registrationGroup === null ? null : (int) $registrationGroup->getGroupID());
    }

    /**
     * The stored Battle.net profile of a user, null if they didn't link a Battle.net account.
     */
    public function getProfile(User $user): ?BattlenetProfile
    {
        return $this->app->make(BattlenetAccounts::class)->getProfile($user);
    }

    /**
     * Exchange the code of the OAuth2 callback for an access token and fetch the Battle.net user info with it.
     *
     * @return array|null the user info: id (account ID, as string) and battletag
     */
    protected function fetchBattlenetUser(): ?array
    {
        // No code: the user cancelled (?error=access_denied)
        $code = (string) $this->request->query->get('code');
        if ($code === '') {
            return null;
        }
        try {
            $service = $this->getService();
            $this->setToken($service->requestAccessToken($code, (string) $this->request->query->get('state')));
            $battlenetUser = json_decode($service->request('userinfo'), true);
        } catch (Throwable $e) {
            $this->logger->notice(t('Battle.net authentication failed: %s', $e->getMessage()));

            return null;
        }
        if (!is_array($battlenetUser) || !BattlenetAccounts::isValidId((string) ($battlenetUser['id'] ?? ''))) {
            return null;
        }
        $battlenetUser['id'] = (string) $battlenetUser['id'];

        return $battlenetUser;
    }

    /**
     * Store the Battle.net profile (the BattleTag can change).
     */
    protected function onConnected(array $battlenetUser, int $userID): void
    {
        try {
            $this->app->make(BattlenetAccounts::class)->saveUser($battlenetUser, $userID);
        } catch (Throwable $e) {
            // The login itself worked, don't fail it
            $this->logger->warning(t('Unable to store the Battle.net profile %s: %s', $battlenetUser['id'], $e->getMessage()), ['exception' => $e]);
        }
    }

    /**
     * Create an inactive, unvalidated account bound to the Battle.net account and send the core "validate your email"
     * mail. Battle.net doesn't provide an email address, so the one the visitor typed in has to be confirmed. The link
     * in the mail (/login/callback/concrete/v/<hash>) marks the account as validated and activates it.
     */
    protected function registerUser(array $battlenetUser, string $email): UserInfo
    {
        $registration = $this->app->make('user/registration');
        $name = isset($battlenetUser['battletag']) ? explode('#', (string) $battlenetUser['battletag'], 2)[0] : null;
        $userInfo = $registration->create([
            'uName' => $registration->getNewUsernameFromUserDetails($email, $name),
            'uPassword' => Str::random(64),
            'uEmail' => $email,
            'uIsValidated' => 0,
        ]);
        if (!$userInfo) {
            throw new RuntimeException('Unable to create new account.');
        }
        $userInfo->deactivate();

        if ($groupID = $this->registrationGroupID()) {
            $group = $this->app->make(GroupRepository::class)->getGroupById($groupID);
            if ($group) {
                User::getByUserID($userInfo->getUserID())->enterGroup($group);
            }
        }

        $attributes = UserKey::getRegistrationList();
        if (!empty($attributes)) {
            $userInfo->saveUserAttributesDefault($attributes);
        }

        $this->bindUserID((int) $userInfo->getUserID(), $battlenetUser['id']);

        $this->app->make('user/status')->sendEmailValidation($userInfo);

        return $userInfo;
    }

    protected function redirectResponse(string $path): RedirectResponse
    {
        return new RedirectResponse((string) $this->app->make(ResolverManagerInterface::class)->resolve([$path]));
    }

    protected function errorResponse(string $error): RedirectResponse
    {
        $this->markError($error);

        return $this->redirectResponse('/login/callback/battlenet/handle_error');
    }

    protected function successResponse(string $message): RedirectResponse
    {
        $this->markSuccess($message);

        return $this->redirectResponse('/login/callback/battlenet/handle_success');
    }
}
