# Battle.net Connect Package #

This package integrates an authenticator for Blizzard's Battle.net gaming platform. It allows your website visitors
to register and login using their Battle.net account, linking the concreteCMS account with their Battle.net account ID
and BattleTag in the process.

**This is NOT an official implementation by Blizzard Entertainment. The creator is not associated with Blizzard
Entertainment in any way.**

## Usage ##

- Clone or download this repo from GitHub or install it via composer (`haeflimi/battlenet_connect`).
- Install the Package via c5 Dashboard.
- Create a client in the [Blizzard Developer Portal](https://develop.battle.net/access/clients) and add the redirect
  URL shown in the dashboard (`https://<your site>/ccm/system/authentication/oauth2/battlenet/callback`).
- On /dashboard/system/registration/authentication Battle.net should be available as an Authentication type now.
- Edit it, enter the client ID and secret, and enable it.
- You are good to login with Battle.net now.

### Upgrading from 0.x ###

- The settings and the authentication type are kept.
- Old versions stored invalid account links: they are removed, these users have to attach their Battle.net account
  again.
- The redirect URL has no trailing slash anymore: update it in the Blizzard Developer Portal.

## Configuration ##

- **Dashboard** (System & Settings › Login & Registration › Authentication Types › Battle.net): OAuth2 client, region,
  registration.
- **Config file**: the package settings and their defaults are in `config/settings.php`. Override them in
  `application/config/battlenet_connect/settings.php`. The client ID/secret and the registration settings are stored
  in the core config (`auth.battlenet.*`). Values saved in the dashboard are written to
  `application/config/generated_overrides/`.

## Features ##

- Register new users using Battle.net login (Battle.net doesn't share email addresses: new users have to confirm one)
- Login existing users using Battle.net login
- Connect a Battle.net account to a concreteCMS account via the user profile
- Store the Battle.net account IDs and BattleTags for further use (the BattleTag is updated on every login)

## Developer Information ##

The Battle.net account ID is the binding in the core `OauthUserMap` table (namespace `battlenet`), the BattleTag is
stored in `BattlenetConnectProfiles`.

```php
use BattlenetConnect\BattlenetAccounts;

$accounts = app(BattlenetAccounts::class);
$battlenetId = $accounts->getBattlenetId($user);       // Battle.net account ID of a Concrete user, or null
$uID = $accounts->getUserID($battlenetId);             // and the other way around
$battleTag = $accounts->getProfile($user)->getBattleTag();
$profile = $accounts->findByBattleTag('Name#1234');    // BattlenetConnect\Entity\BattlenetProfile, or null
$all = $accounts->getLinkedAccounts();                 // [battlenetId => uID]
```

Battle.net only issues user access tokens valid for 24 hours and no refresh tokens, so game profiles (WoW characters,
SC2 profiles, ...) can only be read while the user logs in.

## Prerequisites ##

- Concrete CMS 9.0 or higher, PHP 8
- A client in the Blizzard Developer Portal

## Support ##

This Package is Open Source software under the MIT License. It is provided "as is",
without warranty of any kind.
However, if you find a Bug or any kind of problem with it.: Feel free to create a Issue or
Pull Request here on Github.
