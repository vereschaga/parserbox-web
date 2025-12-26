<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Ad extends \TAccountChecker
{
    public $mailFiles = "mileageplus/statements/it-13413608.eml, mileageplus/statements/it-3134669.eml, mileageplus/statements/st-10113818.eml, mileageplus/statements/st-10192596.eml, mileageplus/statements/st-10633690.eml, mileageplus/statements/st-10636852.eml, mileageplus/statements/st-10685141.eml, mileageplus/statements/st-11101231.eml, mileageplus/statements/st-68472614.eml";

    private $reFrom = ['@news.united.com', 'MileagePlus_Partner@united.com'];
    private $reProvider = ['MileagePlus'];
    private $reSubject = [
        '1,000 bonus miles – the Treat Yourself Bonus starts now',
        'Viva plenamente o verão em Chicago',
        'off your purchase of extra award miles on yourupcoming flight to',
        'Plan the perfect ski vacation, both on and off the slopes',
        'Find the perfect hotel in ',
        'Earn even more miles while you get your finances in line',
    ];
    private $reBody = [
        'en' => [
            ['To ensure delivery to your inbox, please add', 'MileagePlus #'],
            ['Here\'s your purchase confirmation', 'MileagePlus #'],
            ['This card product is available to you if you do not have this card and have not received', 'MileagePlus #'],
            ['To ensure delivery to your inbox, please add', 'The tools you need. The miles you want'],
            ['Miles accrued, awards, and benefits issued are subject to change and are subject to the rules of the United MileagePlus', 'MileagePlus #', 'Mileage balance'],
        ],
        'pt' => [
            ['Para garantir a entrega em sua caixa de entrada de e-mail, por favor, adicione', 'MileagePlus #'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . 'Statement');

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    protected function ParseEmail(Email $email)
    {
        $this->assignLang();

        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode("//text()[contains(., 'XXXX') and contains(., 'MileagePlus')]|//text()[contains(., 'Hi')]/following-sibling::*[contains(., 'XXXX')]", null, true, "/X+(\d+)$/");

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//div[not(.//div) and contains(., 'XXXX') and contains(., 'MileagePlus')]",
                null, true, "/X+(\d+)$/");
        }

        if (empty($number)) {
            $number = $this->http->FindSingleNode("(//text()[contains(., 'XXXX') and starts-with(., 'MileagePlus')])[1]",
                null, true, "/MileagePlus\s*\#\s*X+(\d+)$/");
            $this->logger->debug('$number = ' . print_r($number, true));
        }

        if ($number) {
            $st->setLogin($number)->masked('left');
            $st->setNumber($number)->masked('left');
        }

        $q = $this->http->XPath->query("//sup");

        for ($i = 0; $i < $q->length; $i++) {
            $q->item($i)->nodeValue = "";
        }

        $balance = $this->http->FindSingleNode("//tr[contains(., 'Mileage') and contains(., 'balance') and contains(., 'as') and contains(., 'of') and not(.//tr)]/preceding-sibling::tr[1][contains(., 'miles')]", null, true, "/^([\d\,]+) miles$/");

        if (!isset($balance) || !$balance) {
            $balance = $this->http->FindSingleNode("//*[contains(., 'award') and contains(., 'miles') and starts-with(normalize-space(.), 'Your MileagePlus')]", null, true, "/^Your MileagePlus account balance ([\d\,]+) award miles$/");
        }

        if (!isset($balance) || !$balance) {
            $balance = $this->http->FindSingleNode("//td[contains(., 'mileage') and contains(., 'balance') and contains(., 'as') and contains(., 'of') and not(.//td)]", null, true, "/\D([\d\,]+) miles$/");
        }

        if (!isset($balance) || !$balance) {
            $balance = $this->http->FindSingleNode('//text()[contains(., "Find the hotel you want")]', null, true, '/Find the hotel you want with your ([\d\,]+) miles/');
        }

        if (!isset($balance) || !$balance) {
            $balance = $this->http->FindSingleNode('//tr[contains(., "miles") and following-sibling::tr[contains(., "Mileage") and contains(., "balance") and contains(., "as") and contains(., "of")]]', null, true, '/^([\d\,]+) miles$/');
        }

        if (!isset($balance) || !$balance) {
            $balance = $this->http->FindSingleNode('//text()[contains(., "More ways to use your")]', null, true, '/More ways to use your ([\d\s]+) miles/');

            if (isset($balance)) {
                $balance = str_replace(' ', '', $balance);
            }
        }

        if (!isset($balance) || !$balance) {
            $balance = $this->http->FindSingleNode('//*[text()[normalize-space(.) = "award miles"]]', null, true, '/^\s*(\d[\d\,]+)[ *]+award miles$/');
        }

        if (!isset($balance) && ($url = $this->http->FindSingleNode('//img[contains(@src, "http://www.movable-ink-5643.com/p/rp")]/@src')) && preg_match('/\?(miles|val1)=(?<b>[\d,]+)$/', urldecode($url), $m)) {
            $balance = $m['b'];
        }

        if (!isset($balance) || !$balance) {
            $balance = $this->http->FindSingleNode('//text()[contains(normalize-space(.), "you can drive away in style ")]', null, true, '/^With ([\d\,]+) miles, you can/');
        }

        if (!isset($balance) || !$balance) {
            $balance = $this->http->FindSingleNode("//text()[contains(., 'Your mileage balance as of')]/ancestor::tr[1]/following-sibling::tr[1]//text()[normalize-space(.)='miles']/preceding::text()[normalize-space(.)][1]", null, true, "#^([\d,]+)$#");
        }

        if (!isset($balance) || !$balance) {
            $balance = $this->http->FindSingleNode("//span[@class='fmark-legal']/preceding-sibling::span[1]", null, true, '/^([\d\,]+)$/');
        }

        if (!isset($balance) || !$balance) {
            $balance = $this->http->FindSingleNode('(//text()[contains(., "Saldo de milhas")])[1]', null, true, '/Saldo de milhas*: ([\d.,]+)/');
        }
        // You have 11,185 miles as of May 1, 2018.
        if (!isset($balance) || !$balance) {
            $balance = $this->http->FindSingleNode('(//text()[contains(., "You have")])[1]', null, true, '/You have ([\d.,]+)/');
        }
//        if (!isset($balance) || !$balance)
//            $balance = $this->http->FindSingleNode("//text()[contains(., 'Total miles earned')]/following-sibling::span[1]", null, true, '/(^[\d.,]+$)/');
        if (empty($balance) && $balanceImg = $this->http->FindSingleNode("//img[contains(@src, 'movable-ink-5643')]/@src")) {
            $balanceImg = urldecode($balanceImg);
            // 3134669 - another email with balance in image
            if (preg_match("/\?first=[^\&]+\&last=([\d\,]+)$/", $balanceImg, $m) || preg_match('/\?awardMiles=\s*([\d\,]+)$/', $balanceImg, $m)) {
                $balance = $m[1];
            }
        }

        if (!isset($balance) || !$balance) {
            $balance = $this->http->FindSingleNode('//text()[contains(., "Mileage balance")]', null, true, '/Mileage balance\s*\:? ([\d,]+)/');
        }

        if ($balance !== null) {
            $st->setBalance($this->amount($balance));
        } elseif (empty($balance = $this->http->FindSingleNode("(//*[contains(., 'balance')])[1]"))) {
            $st->setNoBalance(true);
        }
        $name = $this->http->FindSingleNode("//text()[contains(., 'Hi,')]", null, true, "/Hi,(.+)$/");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        return $email;
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $value) {
            foreach ($value as $val) {
                if ($this->http->XPath->query("//text()[{$this->contains($val[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($val[1])}]")->length > 0
                    && (!isset($val[2]) || (isset($val[2]) && $this->http->XPath->query("//text()[{$this->contains($val[2])}]")->length == 0))
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }
}
