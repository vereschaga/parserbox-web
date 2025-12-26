<?php

namespace AwardWallet\Engine\paytm\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BusTicket extends \TAccountChecker
{
    public $mailFiles = "paytm/it-69350787.eml";
    private $lang = '';
    private $reFrom = ['no-reply@paytm.com'];
    private $reProvider = ['Paytm is only'];
    private $reSubject = [
        'Bus Ticket - ',
    ];
    private $reBody = [
        'en' => [
            ['Bus Operator Name', 'Boarding Point', 'Dropping Point'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'Total Fare' => ['Total Amount Paid :', 'Total Fare'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $b = $email->add()->bus();

        $conf = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Order ID'))}]/../following-sibling::span[1]");
        $b->general()->confirmation($conf, $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Order ID'))}]"));
        $b->general()->travellers($this->http->FindNodes("//tr[th[{$this->eq($this->t('Name'))}] and th[{$this->eq($this->t('Gender'))}]]/following-sibling::tr/td[1]"));
        $b->addTicketNumber($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Ticket ID'))}]/../following-sibling::span[1]"), false);
        $b->addProviderPhone($this->http->FindSingleNode("//img[{$this->contains($this->t('/Bus/ticket/group.png'), '@src')}]/following-sibling::span[1]"));

        $roots = $this->http->XPath->query("//text()[{$this->eq($this->t('Departure'))}]/ancestor::tr[1]");

        foreach ($roots as $root) {
            $s = $b->addSegment();
            $s->extra()->number($this->http->FindSingleNode(".//text()[{$this->eq($this->t('PNR'))}]/../following-sibling::span[1]"));

            $s->departure()->name($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Departure'))}]/../following-sibling::p[1]", $root));
            $s->departure()->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Boarding Point'))}]/following::p[normalize-space()][1]"));
            $d = join(', ', array_reverse($this->http->FindNodes(".//text()[{$this->eq($this->t('Departure'))}]/../following-sibling::p[2]//text()", $root)));
            $s->departure()->date2($d);

            $s->arrival()->name($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Arrival'))}]/../following-sibling::p[1]", $root));
            $s->arrival()->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Dropping Point'))}]/following::p[normalize-space()][1]"));
            $d = join(', ', array_reverse($this->http->FindNodes(".//text()[{$this->eq($this->t('Arrival'))}]/../following-sibling::p[2]//text()", $root)));
            $s->arrival()->date2($d);

            $s->extra()->seats($this->http->FindNodes("//tr[th[{$this->eq($this->t('Name'))}] and th[{$this->eq($this->t('Gender'))}]]/following-sibling::tr/td[3]"));
        }

        $price = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Base Fare ('))}]/ancestor::td[1]/following-sibling::td[1]");

        if (preg_match('/^(?<amount>\d[,.\'\d]+)/', $price, $matches)) {
            $b->price()
                ->cost($matches['amount']);
        }
        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Fare'))}]/ancestor::td[1]/following-sibling::td[1]");

        if (preg_match('/^(?<amount>\d[,.\'\d]+)/', $price, $matches)) {
            $b->price()
                ->total($matches['amount']);
        }

        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
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

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[2])}]")->length > 0) {
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

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
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

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'SGD' => ['SG$'],
            'ZAR' => ['R'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
