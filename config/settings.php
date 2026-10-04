<?php

// Defaults of the Battle.net Connect package. Override them in application/config/battlenet_connect/settings.php,
// e.g. return ['region' => 'cn'];
// Values saved in the dashboard (Battle.net authentication type) end up in application/config/generated_overrides.
// The OAuth2 client ID/secret and the registration settings are stored in the core config (auth.battlenet.*).
return [
    // Battle.net login server: "global" (oauth.battle.net, all regions except China) or "cn" (oauth.battlenet.com.cn)
    'region' => 'global',
];
