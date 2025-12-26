<?php

namespace AwardWallet\Engine\starbucks;

use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\ParseAllowedInterface;

class StarbucksExtensionOptions implements ParseAllowedInterface
{
    public function isParseAllowed(AccountOptions $options) : bool
    {
        return in_array($options->login2, ['USA', 'Canada', '']);
    }
}