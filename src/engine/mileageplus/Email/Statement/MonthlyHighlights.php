<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: Looks like "MonthlyStatement", but I would not combine
class MonthlyHighlights extends \TAccountChecker
{
    public $mailFiles = "mileageplus/statements/st-54322210.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && stripos($headers["from"], "mymileageplus@news.united.com") !== false
            && isset($headers['subject']) && stripos($headers['subject'], 'Your monthly statement') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//*[text()[contains(., "MileagePlus")] and text()[contains(., "highlights")]]')->length > 0
            && $this->http->XPath->query('//*[text()[contains(., "here’s a look at your")]]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]united\.com/", $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        if ($balance = $this->http->FindSingleNode('//text()[contains(normalize-space(.), "Award miles that never expire")]/following::text()[normalize-space(.)][1]', null, true, '/^[\d,]+$/')) {
            $st->setBalance(str_replace(',', '', $balance));
        }

        $numberNode = $this->http->XPath->query('//text()[contains(., "MileagePlus") and contains(., "# XXXX")]');

        if ($numberNode->length > 0) {
            $lifeTime = $this->http->FindNodes('following::text()[contains(normalize-space(.), "Lifetime flight miles")]/ancestor::*[position() < 5]', $numberNode->item(0));

            foreach (array_reverse($lifeTime) as $item) {
                if (preg_match('/^Lifetime flight miles.+:\s*([\d,]+)$/', $item, $m)) {
                    $st->addProperty('LifetimeMiles', $m[1]);
                    $st->addProperty('Name', $this->http->FindSingleNode('preceding::text()[normalize-space(.)][1]', $numberNode->item(0)));

                    break;
                }
            }
        }

        return $email;
    }
}
