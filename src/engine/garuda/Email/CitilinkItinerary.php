<?php

namespace AwardWallet\Engine\garuda\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CitilinkItinerary extends \TAccountChecker
{
    public $mailFiles = "garuda/it-56504890.eml, garuda/it-56602450.eml";
    private $lang = '';
    private $reFrom = [
        '@citilink',
    ];
    private $reProvider = ['Citilink Indonesia'];
    private $reSubject = [
        "Here's your confirmed Citilink Itinerary.",
    ];
    private $reBody = [
        'en' => [
            'Additional Services:',
        ],
        'id' => [
            'Informasi Penerbangan:',
        ],
    ];

    private static $dictionary = [
        'en' => [],
        'id' => [
            'Confirmation Number:' => 'Nomor Konfirmasi:',
            'Booking Date:'        => 'Tanggal Pesan:',
            'Customer Number'      => 'Nomor Pelanggan',
            'Depart'               => 'Berangkat',
            'TOTAL FEES'           => 'TOTAL BIAYA',
            'Total Basic Fare'     => 'Harga Dasar',
            'Tax Detail'           => 'Detil Pajak',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->parseFlight($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
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

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();
        $conf = $this->http->FindSingleNode("//td[{$this->starts($this->t('Confirmation Number:'))}]/following-sibling::td[1]",
            null, true, "/([\w\-]{6,})$/");
        $f->general()->confirmation($conf, $this->t('Confirmation Number:'));
        $f->general()->date2($this->http->FindSingleNode("//td[{$this->starts($this->t('Booking Date:'))}]/following-sibling::td[1]"));

        $travellers = $this->http->XPath->query($xpath = "//td[{$this->starts($this->t('Customer Number'))}]/ancestor::tr[1]/following-sibling::tr");
        $seats = [];

        foreach ($travellers as $traveller) {
            if ($t = $this->http->FindSingleNode('td[1]', $traveller)) {
                $f->general()->traveller($t);
            }
            // QG 501/16C
            // QG 500/15C QG 501/15C
            if (preg_match_all('#([A-Z]{2}\s*\d+)/([\w]+)#', $this->http->FindSingleNode('td[3]', $traveller), $m, PREG_SET_ORDER)) {
                foreach ($m as $item) {
                    $seats[str_replace(' ', '', $item[1])] = $item[2];
                }
            }
        }

        $segments = $this->http->XPath->query($xpath = "//td[{$this->starts($this->t('Depart'))}]/ancestor::tr[1]/following-sibling::tr[count(td)=7]");
        $this->logger->notice($xpath);

        foreach ($segments as $segment) {
            $s = $f->addSegment();
            $date = $this->http->FindSingleNode('td[1]', $segment);

            if (preg_match("/([A-Z]{2})\s*(\d{2,4})/", $this->http->FindSingleNode('td[2]', $segment), $m)) {
                $s->airline()->name($m[1]);
                $s->airline()->number($m[2]);
            }
            $s->departure()->code($this->http->FindSingleNode('td[3]', $segment, false, '/^[A-Z]{3}$/'));
            $s->departure()->date2("{$date} {$this->http->FindSingleNode('td[4]', $segment, false, '/^\d+:\d+$/')}");
            $s->arrival()->code($this->http->FindSingleNode('td[5]', $segment, false, '/^[A-Z]{3}$/'));
            $s->arrival()->date2("{$date} {$this->http->FindSingleNode('td[6]', $segment, false, '/^\d+:\d+$/')}");
            $s->extra()->stops($this->http->FindSingleNode('td[7]', $segment, false, '/^\d+$/'));

            if ($seats[$s->getAirlineName() . $s->getFlightNumber()]) {
                $s->extra()->seat($seats[$s->getAirlineName() . $s->getFlightNumber()]);
            }
        }

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('TOTAL FEES'))}]/ancestor::td[1]/following-sibling::td[last()]");

        if (preg_match('/^(?<currency>.+?)\s?(?<amount>\d[,.\'\d]*)/', $totalPrice, $m)) {
            $f->price()->total($this->normalizeAmount($m['amount']))
                ->currency($this->normalizeCurrency($m['currency']));
        }
        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Basic Fare'))}]/ancestor::td[1]/following-sibling::td[1]");

        if (preg_match('/^(?<currency>.+?)\s?(?<amount>\d[,.\'\d]*)/', $totalPrice, $m)) {
            $f->price()->cost($this->normalizeAmount($m['amount']));
        }
        $totalPrice = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Tax Detail'))}]/ancestor::tr[1]/following-sibling::tr/td[{$this->eq($this->t('TOTAL :'))}]/following-sibling::td[last()]");
        $this->logger->error($totalPrice);

        if (preg_match('/^(?<currency>.+?)\s?(?<amount>\d[,.\'\d]*)/', $totalPrice, $m)) {
            $f->price()->tax($this->normalizeAmount($m['amount']));
        }
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $value) {
            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'RUB' => ['Руб.'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'IDR' => ['Rp.'],
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

    private function normalizeDate($str)
    {
        $in = [
            // Miércoles 14 de octubre de 2020
            '/^\w+ (\d+) de (\w+) de (\d{4})$/u',
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
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

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
}
