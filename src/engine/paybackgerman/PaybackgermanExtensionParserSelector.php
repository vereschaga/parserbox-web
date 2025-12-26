<?php

namespace AwardWallet\Engine\paybackgerman;

use AwardWallet\ExtensionWorker\ParserSelectorInterface;
use AwardWallet\ExtensionWorker\SelectParserRequest;
use Psr\Log\LoggerInterface;

class PaybackgermanExtensionParserSelector implements ParserSelectorInterface
{
    public function selectParser(SelectParserRequest $request, LoggerInterface $logger): string
    {
        // India - ?
        // Mexico - timeout

        if (in_array($request->getLogin3(), ['Italy'])) {
            return PaybackgermanExtensionItaly::class;
        }

        if (in_array($request->getLogin3(), ['Poland'])) {
            return PaybackgermanExtensionPoland::class;
        }

        return PaybackgermanExtension::class;
    }
}
