<?php

namespace AwardWallet\Engine\cebu\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryReceipt extends \TAccountChecker
{
    public $mailFiles = "cebu/it-102227268.eml, cebu/it-769351255.eml, cebu/it-770495534.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            //            'BOOKING DATE' => '',
            //            'BOOKING REFERENCE NO.' => '',
            'DEPARTURE' => 'DEPARTURE',
            'ARRIVAL'   => 'ARRIVAL',
            //            'NAME' => '',
            //            'Fare, Taxes and Fees:' => '',
            //            'Base Fare' => '',
        ],
    ];

    private $detectSubject = [
        // en
        'Your Itinerary Receipt for Booking No.',
    ];
    private $detectBody = [
        'en' => [
            'Your transaction was successful. See you on board soon',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.mycebupacific.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        // case 1: from and subject
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if ($this->http->XPath->query("//a[{$this->contains(['.mycebupacific.com'], '@*')}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->contains('Cebu Air, Inc')}]")->length === 0) {
            return false;
        }

        // detect Format
        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('BOOKING REFERENCE NO.'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
        ;
        $travellerNodes = $this->http->XPath->query("//tr[td[1][{$this->eq($this->t('NAME'))}]]/following-sibling::tr");
        $traveller = null;
        $seatsAll = [];

        foreach ($travellerNodes as $tRoot) {
            $t = $this->http->FindSingleNode("td[1]/descendant::text()[normalize-space()][1]", $tRoot);

            if (!empty($t)) {
                $t = preg_replace("/^\s*(MS|MR|MISS|MRS|MSTR)\s+/", '', $t);
                $traveller = $t;
                $f->general()
                    ->traveller($traveller, true);
            }
            $infant = $this->http->FindSingleNode("td[1][descendant::text()[normalize-space()][4][{$this->eq($this->t('Infant on lap'))}]]/descendant::text()[normalize-space()][3]", $tRoot);

            if (!empty($infant)) {
                $infant = preg_replace("/^\s*(MS|MR|MISS|MRS|MSTR)\s+/", '', $infant);
                $f->general()
                    ->infant($infant);
            }

            $seat = $this->http->FindSingleNode("td[3]/descendant::text()[{$this->starts($this->t('Seat'))}]", $tRoot, true,
                "/{$this->opt($this->t('Seat'))}\s*(\d{1,2}[A-Z])\s*$/");

            if (!empty($seat)) {
                $route = preg_replace('/[^A-Z]+/', '', $this->http->FindSingleNode("td[2]", $tRoot));
                $seatsAll[$route][] = ['seat' => $seat, 'traveller' => $traveller];
            }
        }

        $date = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('BOOKING DATE'))}]/following::text()[normalize-space()][1]"));

        if (!empty($date)) {
            $f->general()
                ->date($date);
        }

        // Price
        $total = $this->http->FindSingleNode("//td[{$this->eq($this->t('Fare, Taxes and Fees:'))}]/following-sibling::td[1]");

        if (preg_match("#^\s*(?<curr>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[A-Z]{3})\s*$#", $total, $m)) {
            $f->price()
                ->total(PriceHelper::cost($m['amount']))
                ->currency($m['curr'])
            ;
        }
        $priceRows = $this->http->XPath->query("//tr[td[1][{$this->eq($this->t('Fare, Taxes and Fees:'))}]]/following-sibling::tr[normalize-space()]");
        $discount = 0.0;

        foreach ($priceRows as $proot) {
            $name = $this->http->FindSingleNode("td[1]", $proot);
            $amount = $this->http->FindSingleNode("td[2]", $proot);

            if (preg_match("#^\s*(?<curr>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $amount, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[A-Z]{3})\s*$#", $amount, $m)
            ) {
                $amount = $m['amount'];
            } elseif (preg_match("#^\s*(?<curr>[A-Z]{3})\s*\(\s*(?<amount>\d[\d\., ]*)\s*\)\s*$#", $amount, $m)
                || preg_match("#^\s*\s*\(\s*(?<amount>\d[\d\., ]*)\s*\)\s*(?<curr>[A-Z]{3})\s*$#", $amount, $m)
            ) {
                $discount += PriceHelper::cost($m['amount']);

                continue;
            }
            $amount = PriceHelper::cost($amount);

            if (in_array($name, (array) $this->t('Base Fare'))) {
                $f->price()
                    ->cost($amount);
            } elseif (!preg_match("/:\s*$/", $name)) {
                $f->price()
                    ->fee($name, $amount);
            } elseif (preg_match("/:\s*$/", $name) && $f->getPrice() && $f->getPrice()->getTotal()) {
                $f->price()
                    ->total($f->getPrice()->getTotal() + $amount);
            }
        }

        if (!empty($discount)) {
            $f->price()
                ->discount($discount);
        }

        $xpath = "//tr[td[2][{$this->eq($this->t('DEPARTURE'))}]]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]/td[2]", $root);

            if (preg_match("/^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?<fn>\d{1,5})\s*$/", $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]/td[1]", $root, true,
                    "/^\s*([A-Z]{3})\s*-\s*[A-Z]{3}\s*$/"))
                ->date($this->normalizeDate(
                    $this->http->FindSingleNode("td[1]", $root)
                    . ', ' . $this->http->FindSingleNode("following-sibling::tr[1]/td[1]", $root)
                ))
            ;
            $name = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/td[2]", $root);

            if (preg_match("/(.+) Terminal (\w+)\s*$/", $name, $m)) {
                $name = $m[1];
                $s->departure()
                    ->terminal($m[2]);
            }
            $s->departure()
                ->name($name);

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]/td[1]", $root, true,
                    "/^\s*[A-Z]{3}\s*-\s*([A-Z]{3})\s*$/"))
                ->date($this->normalizeDate(
                    $this->http->FindSingleNode("following-sibling::tr[normalize-space()][2]/td[1]", $root)
                    . ', ' . $this->http->FindSingleNode("following-sibling::tr[normalize-space()][3]/td[1]", $root)
                ))
            ;
            $name = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][3]/td[2]", $root);

            if (preg_match("/(.+) Terminal (\w+)\s*$/", $name, $m)) {
                $name = $m[1];
                $s->arrival()
                    ->terminal($m[2]);
            }
            $s->arrival()
                ->name($name);

            $route = $s->getDepCode() . $s->getArrCode();

            if (strlen($route) === 6 && !empty($seatsAll[$route])) {
                foreach ($seatsAll[$route] as $sa) {
                    $s->extra()
                        ->seat($sa['seat'], true, true, $sa['traveller']);
                }
            }
        }

        return true;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["DEPARTURE"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['DEPARTURE'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods
    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1 :43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $date = $this->dateTranslate($date);

//        $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
