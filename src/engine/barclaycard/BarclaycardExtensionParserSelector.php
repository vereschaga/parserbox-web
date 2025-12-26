<?php

namespace AwardWallet\Engine\barclaycard;


use AwardWallet\Engine\amex\AmexExtensionCH;
use AwardWallet\Engine\amex\AmexExtensionGlobalsplash;
use AwardWallet\Engine\amex\AmexExtensionUS;
use AwardWallet\ExtensionWorker\ParserSelectorInterface;
use AwardWallet\ExtensionWorker\SelectParserRequest;
use Psr\Log\LoggerInterface;

class BarclaycardExtensionParserSelector implements ParserSelectorInterface
{
    public function selectParser(SelectParserRequest $request, LoggerInterface $logger): string
    {
        if (in_array($request->getLogin2(), ['UK'])) {
            return BarclaycardExtensionUK::class;
        }
        return BarclaycardExtensionUS::class;
    }
}

