<?php

namespace AwardWallet\Engine\olacabs\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Ride extends \TAccountChecker
{
    public $mailFiles = "olacabs/it-56504566.eml";
    public $reFrom = '@olacabs.com';
    public $reSubject = [
        '/Your \w+ ride with Ola/',
        '/Your \w+ ride to/',
        '/Invoice for your Ride \w+/',
        '/Tipping invoice for your Ride \w+/',
    ];
    public $reBody = 'olacabs.com';
    public $reBody2 = [
        'en' => ['Thanks for travelling with us,', 'Thanks for the tip,'],
    ];

    public static $dictionary = [
        'en' => [
            'Thanks for travelling with us,' => ['Thanks for travelling with us,', 'Thanks for the tip,'],
        ],
    ];

    public $lang = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseTransfer($email);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers["from"], $headers["subject"]) || stripos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (preg_match($re, $headers["subject"])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[{$this->contains($this->reBody, '@href')}]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseTransfer(Email $email): void
    {
        $patterns = [
            'date' => '\b\d{1,2}\s+[[:alpha:]]+[,\s]+\d{4}\b', // 01 Sep, 2023
        ];

        $t = $email->add()->transfer();
        $t->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Thanks for travelling with us,'))}]
            /ancestor::tr[1]/preceding-sibling::tr[1]", null, false, '/^\s*[A-Z]{3}\d+/'))
            ->traveller($this->http->FindSingleNode("//text()[{$this->contains($this->t('Thanks for travelling with us,'))}]",
                null, false, '/,\s+(.+)/'));

        $dateValues = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Thanks for travelling with us,'))}]/preceding::tr/*[contains(@class,'date-time')] | //text()[{$this->contains($this->t('Thanks for travelling with us,'))}]/preceding::tr[count(*)=2 and *[2]/descendant::img and normalize-space(*[2])='']/*[1]", null, "/^{$patterns['date']}$/u"));
        $date = count(array_unique($dateValues)) === 1 ? array_shift($dateValues) : null;

        if (!$date) {
            $this->logger->debug('Date empty!');

            return;
        }
        $date = str_replace(',', '', $date);
        $nodes = $this->http->XPath->query("//img[contains(@src,'Invoice_src_dest')]/ancestor::table[1]//tr[not(.//tr)]");

        if ($nodes->length == 2) {
            $s = $t->addSegment();
            // 04:19 PM		503 Murray St, Perth WA 6000, Australia
            if (preg_match('/(\d+:\d+(?:\s*[AP]M)?)\s+(.+)/', $nodes[0]->nodeValue, $m)) {
                $s->departure()->date2("{$date}, {$m[1]}");
                $s->departure()->address($m[2]);
            }

            if (preg_match('/(\d+:\d+(?:\s*[AP]M)?)\s+(.+)/', $nodes[1]->nodeValue, $m)) {
                $s->arrival()->date2("{$date}, {$m[1]}");
                $s->arrival()->address($m[2]);
            }

            if (!empty($s->getArrDate()) && !empty($s->getDepDate()) && $s->getArrDate() < $s->getDepDate()
                && strtotime('+1 day', $s->getArrDate()) > $s->getDepDate()
                && strtotime('+1 day', $s->getArrDate()) < strtotime('+4 hours', $s->getDepDate())
            ) {
                $s->arrival()->date(strtotime('+1 day', $s->getArrDate()));
            }

            $dashVal = $this->http->FindSingleNode("//tr/*[img[contains(@src,'dash_icon')] and normalize-space()='']/following-sibling::*[normalize-space()][1]");

            if (preg_match('/^(\d[\d.\s]*[kmiles]+)\s+(\d{1,4}\s*(?:min|h))$/i', $dashVal, $m)) {
                // 22.7 km    65 min
                $s->extra()->miles($m[1])->duration($m[2]);
            } elseif (preg_match('/^\d[\d.\s]*[kmiles]+/i', $dashVal, $m)) {
                // 22.7 km
                $s->extra()->miles($m[0]);
            }

            // Prime Sedan - Blue Camry
            $model = $this->http->FindSingleNode("//td[img[{$this->contains(['/Invoice_Prime_Sedan_Icon', 'Invoice_Micro_Icon', 'Invoice_Mini_Icon', 'Invoice_Prime_Suv_Icon', '/Invoice_auto', '/Invoice_Prime_Plus_Icon.', '/Invoice_Bike_Icon', '/Invoice_Prime_Play_Icon'], '@src')}]]/following-sibling::td");
            preg_match('/^(.+?) - (.+?)$/', $model, $m);
            $s->extra()->type($m[1]);
            $s->extra()->model($m[2]);
        }

        // Total Bill A$6.84
        $pregAmount = '/\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d., ]*)\s*/';
        $total = $this->http->FindSingleNode("//*[{$this->eq($this->t('Total Payable'))}]/following-sibling::*[normalize-space(.)!=''][1]");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total Bill'))}]/following::table[1][normalize-space(.)!='']");
        }

        if (preg_match($pregAmount, $total, $m)) {
            $t->price()->currency($this->normalizeCurrency($m['curr']));
            $t->price()->total($m['amount']);
        }
        // Taxes
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes'))}]/following::table[1][normalize-space(.)!='']");
        // Includes ₹39.81 Taxes
        if (!isset($total)) {
            $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Includes'))} or {$this->contains($this->t('Taxes'))}]");
        }

        if (preg_match($pregAmount, $total, $m)) {
            $t->price()->tax($m['amount']);
        }
        // Ride Fare
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Ride Fare'))}]/following::table[1][normalize-space(.)!='']");

        if (preg_match($pregAmount, $total, $m)) {
            $t->price()->cost($m['amount']);
        }
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody2 as $lang => $re) {
            if ($this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0) {
                $this->lang = $lang;

                return true;
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

    private function normalizeCurrency($s)
    {
        if (preg_match("#^\s*([A-Z]{3})\s*$#", $s, $m)) {
            return $m[1];
        }
        $sym = [
            '€'   => 'EUR',
            'A$'  => 'AUD',
            '£'   => 'GBP',
            'CA$' => 'CAD',
            'MX$' => 'MXN',
            '₹'   => 'INR',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'normalize-space(' . $node . ')="' . $s . '"';
                }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'contains(normalize-space(' . $node . '),"' . $s . '")';
                }, $field))
            . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }
}
