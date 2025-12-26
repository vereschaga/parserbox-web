<?php

namespace AwardWallet\Engine\citybank;

use AwardWallet\ExtensionWorker\ParserSelectorInterface;
use AwardWallet\ExtensionWorker\SelectParserRequest;
use Psr\Log\LoggerInterface;

class CitybankExtensionParserSelector implements ParserSelectorInterface
{
    public function selectParser(SelectParserRequest $request, LoggerInterface $logger): string
    {
        if (in_array($request->getLogin2(), ['Singapore'])) {
            return CitybankExtensionOldDesign::class;
        }

        return CitybankExtensionUs::class;
    }
}
