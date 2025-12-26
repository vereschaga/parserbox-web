<?php

namespace AwardWallet\Engine\starbucks;

use AwardWallet\ExtensionWorker\ParserSelectorInterface;
use AwardWallet\ExtensionWorker\SelectParserRequest;
use Psr\Log\LoggerInterface;

class StarbucksExtensionParserSelector implements ParserSelectorInterface
{
    public function selectParser(SelectParserRequest $request, LoggerInterface $logger): string
    {
        if (in_array($request->getLogin2(), ['', 'USA', 'Canada'])) {
            return StarbucksExtensionAmerica::class;
        }

        if (in_array($request->getLogin2(), ['Peru'])) {
            return StarbucksExtensionPeru::class;
        }

        if (in_array($request->getLogin2(), ['Ireland', 'Germany', 'Spain', 'Switzerland', 'Thailand'])) {
            return StarbucksExtensionEurope::class;
        }

        if (in_array($request->getLogin2(), ['UK'])) {
            return StarbucksExtensionUK::class;
        }

        if (in_array($request->getLogin2(), ['HongKong'])) {
            return StarbucksExtensionHongKong::class;
        }

        if (in_array($request->getLogin2(), ['Vietnam'])) {
            return StarbucksExtensionVietnam::class;
        }

        if (in_array($request->getLogin2(), ['Mexico'])) {
            return StarbucksExtensionMexico::class;
        }

        if (in_array($request->getLogin2(), ['Taiwan'])) {
            return StarbucksExtensionTaiwan::class;
        }

        if (in_array($request->getLogin2(), ['India'])) {
            return StarbucksExtensionIndia::class;
        }

        if (in_array($request->getLogin2(), ['Japan'])) {
            return StarbucksExtensionJapan::class;
        }

        return StarbucksExtension::class;
    }
}
