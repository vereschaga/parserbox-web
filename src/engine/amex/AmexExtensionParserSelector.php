<?php

namespace AwardWallet\Engine\amex;


use AwardWallet\ExtensionWorker\ParserSelectorInterface;
use AwardWallet\ExtensionWorker\SelectParserRequest;
use Psr\Log\LoggerInterface;

class AmexExtensionParserSelector implements ParserSelectorInterface
{
    public function selectParser(SelectParserRequest $request, LoggerInterface $logger): string
    {
        if (in_array($request->getLogin2(), ["Bahrain", "Egypt", "Lebanon", "Jordan", "Kuwait", "Oman", "UAE", "United Arab Emirates"])) {
            return AmexExtensionGlobalsplash::class;
        }
        if (in_array($request->getLogin2(), ["Schweiz", "Switzerland"])) {
            return AmexExtensionCH::class;
        }
        return AmexExtensionUS::class;
    }
}

