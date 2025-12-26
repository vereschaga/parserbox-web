<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Galileo extends \TAccountChecker
{
    public $mailFiles = "mta/it-36359659.eml, mta/it-36663748.eml";

    public $reFrom = ["mtatravel.com.au"];
    public $reBody = [
        'en' => ['Flight Segments'],
    ];
    public $reSubject = [
        'MTA - Galileo (Galileo Booking)',
        'MTA - Sabre (Sabre Booking)',
        'MTA - Amadeus (Amadeus Booking)',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Departure' => 'Departure',
            'Passenger' => 'Passenger',
        ],
    ];
    private $keywordProv = ['MTA - Galileo', 'MTA - Sabre', 'MTA - Amadeus'];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt,'mta Heading')] | //a[contains(@href,'mtatravel.com.au')]")->length > 0) {
            if ($this->stripos($this->http->Response['body'],
                    $this->keywordProv) && $this->detectBody($this->http->Response['body'])
            ) {
                return $this->assignLang();
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || $this->stripos($headers["subject"], $this->keywordProv) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $node = $this->nextTD('PNR');
        $email->ota()
            ->confirmation($this->re("#^([A-Z\d]{5,})\s*\(#", $node), 'PNR', true)
            ->confirmation($this->re("#\((\d+)\)#", $node), 'PNR()');

        $total = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'TOTAL ALL PAX')]");

        if (preg_match("/TOTAL ALL PAX\s([A-Z]{3})([\d\.\,]+)\s/", $total, $m)) {
            $email->price()
                ->total(PriceHelper::cost($m[2], ',', '.'))
                ->currency($m[1]);
        }

        $pax = $this->nextTDs('Passenger');

        $xpath = "//text()[{$this->eq($this->t('Departure'))}]/ancestor::tr[{$this->contains($this->t('Destination'))}][1]/following-sibling::tr[not({$this->contains($this->t('OPERATED BY'))})]";
        $nodes = $this->http->XPath->query($xpath);
        $flights = [];

        foreach ($nodes as $root) {
            $flights[$this->http->FindSingleNode("./td[9]", $root, false, "#^([A-Z\d]{5,6})(?:\-|$)#")][] = $root;
        }

        foreach ($flights as $rl => $roots) {
            $r = $email->add()->flight();
            $r->general()
                ->confirmation($rl)
                ->travellers($pax, true);

            foreach ($roots as $root) {
                $s = $r->addSegment();
                $node = $this->http->FindSingleNode("./td[1]", $root);
                $s->departure()
                    ->name($this->re("#(.+)\s+\(#", $node))
                    ->code($this->re("#\(([A-Z]{3})\)#", $node))
                    ->date($this->normalizeDate($this->http->FindSingleNode("./td[3]",
                            $root) . ', ' . $this->http->FindSingleNode("./td[4]", $root)));

                $node = $this->http->FindSingleNode("./td[2]", $root);
                $s->arrival()
                    ->name($this->re("#(.+)\s+\(#", $node))
                    ->code($this->re("#\(([A-Z]{3})\)#", $node))
                    ->date($this->normalizeDate($this->http->FindSingleNode("./td[5]",
                            $root) . ', ' . $this->http->FindSingleNode("./td[6]", $root)));

                $s->extra()->bookingCode($this->http->FindSingleNode("./td[7]", $root, false, '#^[A-Z]{1,2}$#'));

                $node = $this->http->FindSingleNode("./td[8]", $root);
                $s->airline()
                    ->name($this->re("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+$#", $node))
                    ->number($this->re("#^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$#", $node));

                $node = $this->http->FindSingleNode("./following-sibling::tr[1][{$this->contains($this->t('OPERATED BY'))}]",
                    $root, false, "#{$this->opt($this->t('OPERATED BY'))}\s+(.+)#");

                if (!empty($node)) {
                    $s->airline()->operator($node);
                }
            }
        }

        return true;
    }

    private function nextTD($field)
    {
        return $this->http->FindSingleNode("//text()[{$this->starts($field)}]/ancestor::td[1]/following-sibling::td[1]");
    }

    private function nextTDs($field)
    {
        return $this->http->FindNodes("//text()[{$this->starts($field)}]/ancestor::td[1]/following-sibling::td[1]");
    }

    private function normalizeDate($date)
    {
        $in = [
            //19/01/2018, 1245 | 19/01/2018, 12:45
            '#^\s*(\d+)\/(\d+)\/(\d+), (\d{2}):?(\d{2})\s*$#',
        ];
        $out = [
            '$3-$2-$1, $4:$5',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                foreach ($reBody as $re) {
                    if (stripos($body, $re) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Departure"], $words["Passenger"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Departure'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Passenger'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
