<?php

namespace AwardWallet\Engine\rewardsnet;

use AwardWallet\ExtensionWorker\ParserSelectorInterface;
use AwardWallet\ExtensionWorker\SelectParserRequest;
use Psr\Log\LoggerInterface;

class RewardsnetExtensionParserSelector implements ParserSelectorInterface
{
    public function selectParser(SelectParserRequest $request, LoggerInterface $logger): string
    {
        if ($request->getLogin2() === 'https://www.aadvantagedining.com/') {
            return RewardsnetExtensionAmericanAirlines::class;
        }

        if ($request->getLogin2() === 'https://eataroundtown.marriott.com/') {
            return RewardsnetExtensionMarriott::class;
        }

        if ($request->getLogin2() === 'https://mpdining.rewardsnetwork.com/') {
            return RewardsnetExtensionUnited::class;
        }

        return RewardsnetExtension::class;
    }
}
