<?php

namespace AwardWallet\Engine\sephora;

use AwardWallet\ExtensionWorker\ParserSelectorInterface;
use AwardWallet\ExtensionWorker\SelectParserRequest;
use Psr\Log\LoggerInterface;

class SephoraExtensionParserSelector implements ParserSelectorInterface
{
    public function selectParser(SelectParserRequest $request, LoggerInterface $logger): string
    {
        /*if (in_array($request->getLogin2(), ['Spain', 'Italy'])) {
            return StarbucksExtension::class;
        }*/

        return SephoraExtension::class;
    }
}
