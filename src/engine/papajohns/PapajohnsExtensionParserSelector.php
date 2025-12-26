<?php

namespace AwardWallet\Engine\papajohns;

use AwardWallet\ExtensionWorker\ParserSelectorInterface;
use AwardWallet\ExtensionWorker\SelectParserRequest;
use Psr\Log\LoggerInterface;

class PapajohnsExtensionParserSelector implements ParserSelectorInterface
{
    public function selectParser(SelectParserRequest $request, LoggerInterface $logger): string
    {
        if ($request->getLogin2() == 'UK') {
            return PapajohnsExtensionUk::class;
        }

        return PapajohnsExtensionUs::class;
    }
}
