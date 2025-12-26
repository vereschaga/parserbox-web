<?php

namespace AwardWallet\Engine\livingsocial;

use AwardWallet\ExtensionWorker\ParserSelectorInterface;
use AwardWallet\ExtensionWorker\SelectParserRequest;
use Psr\Log\LoggerInterface;

class LivingsocialExtensionParserSelector implements ParserSelectorInterface
{
    public function selectParser(SelectParserRequest $request, LoggerInterface $logger): string
    {
        if ($request->getLogin2() == 'UK') {
            return LivingsocialExtensionUK::class;
        }

        return LivingsocialExtension::class;
    }
}
