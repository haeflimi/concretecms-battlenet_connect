# Battle.net Connect Package #

This package integrates an authenticator for Blizzard's Battle.net gaming platform. It allows your website visitors
to register and login using their Battle.net account, linking the concreteCMS account with their Battle.net account ID
and BattleTag in the process. It imports the World of Warcraft characters of the users (Retail and the Classic
versions) and shows them with a block.

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

### World of Warcraft ###

- Choose the WoW versions (Retail, Classic progression, Classic Era, Classic Anniversary), the game region and the
  language of the names in the Battle.net authentication type.
- The characters of a user are imported when they log in with Battle.net or attach their account: they're asked for
  access to their WoW profile. Battle.net only allows this with the user's token (valid for 24 hours), so new or
  deleted characters show up on the next login (or with "Refresh the list of characters" on their account page).
- Schedule the "Sync Battle.net Data" task (Dashboard › System & Settings › Automation, or
  `concrete/bin/concrete task:sync-battlenet-data`): it updates level, spec, guild, item level, achievement points,
  Mythic+ rating (retail) and the character images, and records the progress for the activity ranking.
- Add the "WoW Characters" block: the characters of a member (place it on the public profile page /members/profile to
  show every member's characters) or a ranking of all members' characters by item level, Mythic+ rating,
  achievement points, level, last played or activity (achievement points gained in a period).
- Characters not played for a long time, renamed or transferred can't be found by the task until their owner logs in
  again. Blizzard doesn't provide the played time.

### Upgrading from 0.x ###

- The settings and the authentication type are kept.
- Old versions stored invalid account links: they are removed, these users have to attach their Battle.net account
  again.
- The redirect URL has no trailing slash anymore: update it in the Blizzard Developer Portal.

## Configuration ##

- **Dashboard** (System & Settings › Login & Registration › Authentication Types › Battle.net): OAuth2 client, region,
  World of Warcraft versions, game region and language, registration.
- **Config file**: the package settings and their defaults are in `config/settings.php`. Override them in
  `application/config/battlenet_connect/settings.php`. The client ID/secret and the registration settings are stored
  in the core config (`auth.battlenet.*`). Values saved in the dashboard are written to
  `application/config/generated_overrides/`. When Blizzard adds a new kind of Classic realms with its own API
  namespace, add it to `wow_namespaces`.

## Features ##

- Register new users using Battle.net login (Battle.net doesn't share email addresses: new users have to confirm one)
- Login existing users using Battle.net login
- Connect a Battle.net account to a concreteCMS account via the user profile
- Store the Battle.net account IDs and BattleTags for further use (the BattleTag is updated on every login)
- Import the World of Warcraft characters of the users and keep them up to date
- Show the characters of a member, or rankings of all members' characters, with the "WoW Characters" block

## Developer Information ##

The Battle.net account ID is the binding in the core `OauthUserMap` table (namespace `battlenet`), the BattleTag is
stored in `BattlenetConnectProfiles`, the characters in `BattlenetConnectWowCharacters` and their progress in
`BattlenetConnectWowSnapshots`.

```php
use BattlenetConnect\Api\BattlenetApi;
use BattlenetConnect\BattlenetAccounts;
use BattlenetConnect\Wow\WowRoster;

$accounts = app(BattlenetAccounts::class);
$battlenetId = $accounts->getBattlenetId($user);       // Battle.net account ID of a Concrete user, or null
$uID = $accounts->getUserID($battlenetId);             // and the other way around
$battleTag = $accounts->getProfile($user)->getBattleTag();
$profile = $accounts->findByBattleTag('Name#1234');    // BattlenetConnect\Entity\BattlenetProfile, or null
$all = $accounts->getLinkedAccounts();                 // [battlenetId => uID]

// WoW characters, e.g. the best character of every member by Mythic+ rating
$rows = app(WowRoster::class)->getCharacters(['sort' => WowRoster::SORT_MYTHIC_RATING, 'onePerUser' => true, 'withValueOnly' => true]);
foreach ($rows as $row) {
    echo $row['character']->getName(), ' ', $row['value'], ' ', $row['ownerName'];
}

// Any other character endpoint of the WoW profile API, with the application token
$equipment = app(BattlenetApi::class)->request('profile/wow/character/blackhand/thrall/equipment', 'retail');
```

## Prerequisites ##

- Concrete CMS 9.0 or higher, PHP 8
- A client in the Blizzard Developer Portal

## Support ##

This Package is Open Source software under the MIT License. It is provided "as is",
without warranty of any kind.
However, if you find a Bug or any kind of problem with it.: Feel free to create a Issue or
Pull Request here on Github.
