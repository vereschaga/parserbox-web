<?php

namespace AwardWallet\Engine\pieology;

use AwardWallet\ExtensionWorker\ParserSelectorInterface;
use AwardWallet\ExtensionWorker\SelectParserRequest;
use Psr\Log\LoggerInterface;

class PieologyExtensionParserSelector implements ParserSelectorInterface
{
    public function selectParser(SelectParserRequest $request, LoggerInterface $logger): string
    {
        /*
        return PieologyPunchhDotComExtension::class;
        */
        return PieologyExtension::class;
    }
}
