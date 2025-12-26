<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class EarnedMilesThisYear extends \TAccountChecker
{
    public $mailFiles = "mileageplus/statements/it-13113115.eml, mileageplus/statements/it-13113891.eml";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEmail($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@news.united.com') !== false
            && isset($headers['subject']) && stripos($headers['subject'], 'award miles this year with the help of your Card') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return strpos($this->http->Response['body'], 'United MileagePlus') !== false
        && strpos($this->http->Response['body'], 'Thank you for being a Cardmember.') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]united\./", $from);
    }

    protected function ParseEmail(Email $email)
    {
        $st = $email->add()->statement();
        $number = $this->http->FindSingleNode("//text()[contains(., 'XXXXX')]", null, true, "/XXXXX([A-Z\d]{3})/");

        if ($number) {
            $st->setLogin($number)->masked('left');
            $st->setNumber($number)->masked('left');
        }
        $balance = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.), "You have")]', null, true, '/^You have ([\d,]+) miles/');

        if (isset($balance)) {
            $st->setBalance(str_replace(',', '', $balance));
        }
    }
}
