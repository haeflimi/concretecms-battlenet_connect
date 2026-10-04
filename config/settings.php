<?php

// Defaults of the Battle.net Connect package. Override them in application/config/battlenet_connect/settings.php,
// e.g. return ['wow_versions' => ['retail', 'classic_era']];
// Values saved in the dashboard (Battle.net authentication type) end up in application/config/generated_overrides.
// The OAuth2 client ID/secret and the registration settings are stored in the core config (auth.battlenet.*).
return [
    // Battle.net login server: "global" (oauth.battle.net, all regions except China) or "cn" (oauth.battlenet.com.cn)
    'region' => 'global',

    // World of Warcraft versions whose characters are imported when users log in, see wow_namespaces.
    // Empty = no WoW features (the "wow.profile" permission isn't requested).
    'wow_versions' => ['retail'],
    // Region of the WoW API: us, eu, kr or tw. Only characters of this region are imported.
    'wow_api_region' => 'eu',
    // Language of class, race, spec and realm names: en_US, en_GB, de_DE, fr_FR, es_ES, it_IT, pt_PT, ru_RU,
    // es_MX, pt_BR, ko_KR, zh_TW
    'wow_locale' => 'en_US',
    // WoW versions and their API namespace: the profile data is in "profile-<namespace>-<region>", or
    // "profile-<region>" for an empty namespace. Blizzard adds namespaces for new Classic realm types: add them here.
    'wow_namespaces' => [
        'retail' => '',
        // Classic progression realms (Cataclysm Classic, Mists of Pandaria Classic, ...)
        'classic' => 'classic',
        // Classic Era realms (vanilla, Hardcore, Season of Discovery)
        'classic_era' => 'classic1x',
        // Anniversary realms (e.g. TBC Anniversary)
        'anniversary' => 'classicann',
    ],
];
