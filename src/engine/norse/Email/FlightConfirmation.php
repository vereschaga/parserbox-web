<?php

namespace AwardWallet\Engine\norse\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightConfirmation extends \TAccountChecker
{
    public $mailFiles = "norse/it-180006409.eml, norse/it-184831180.eml, norse/it-722175298.eml";
    public $subjects = [
        'Your booking confirmation',
    ];

    public $lang = 'en';
    public $date;

    public static $dictionary = [
        "en" => [
            'New Flight Summary:' => ['New Flight Summary:', 'Departing flight', 'Returning flight'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@flynorse.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Norse')]")->length > 0) {
            if ($this->http->XPath->query("//text()[{$this->contains($this->t('Manage reservation'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Passenger Details'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Purchase summary'))}]")->length > 0) {
                return true;
            }

            if ($this->http->XPath->query("//text()[{$this->contains($this->t('Please review your new trip details carefully'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('We are very sorry to inform you that there has been a minor time change to your flight'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('New Flight Summary:'))}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flynorse.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $confNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Confirmation number'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6})$/");

        if (empty($confNumber)) {
            $confNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confirmation code'))}]", null, true, "/{$this->opt($this->t('confirmation code'))}\s*([A-Z\d]{6})\.?$/");
        }

        $paxs = $this->http->FindNodes("//text()[contains(normalize-space(), 'Passenger Details')]/following::text()[normalize-space()='Flight']/ancestor::tr[1]/preceding::tr[1][not(contains(normalize-space(), 'Bags'))]");

        if (empty($paxs)) {
            $paxs = explode(', ', $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Passengers:')]", null, true, "/{$this->opt($this->t('Passengers:'))}\s*(.+)/"));
        }

        $f->general()
            ->confirmation($confNumber)
            ->travellers($paxs);

        $priceText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Total')]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^\s*(?<currency>\D)(?<total>[\d\.\,]+)/u", $priceText, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Fares']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D([\d\,\.]+)$/u");

            if (!empty($cost)) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='Taxes']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D([\d\,\.]+)$/u");

            if (!empty($tax)) {
                $f->price()
                    ->tax(PriceHelper::parse($tax, $currency));
            }

            $feeName = $this->http->FindSingleNode("//text()[normalize-space()='Additional']");
            $fee = $this->http->FindSingleNode("//text()[normalize-space()='Additional']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D([\d\,\.]+)$/u");

            if (!empty($fee)) {
                $f->price()
                    ->fee($feeName, PriceHelper::parse($fee, $currency));
            }
        }

        $xpath = "//text()[{$this->eq($this->t('New Flight Summary:'))}]/following::img[contains(@src, 'circle-arrow')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineInfo = $this->http->FindSingleNode("./ancestor::table[1]/descendant::text()[starts-with(normalize-space(), 'Flight')]", $root, true, "/^{$this->opt($this->t('Flight'))}\s*(.+)/");

            if (preg_match("/^(?<name>[A-Z\d]{2})(?<number>\d{1,4})/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $operator = $this->http->FindSingleNode("./ancestor::table[1]/descendant::text()[starts-with(normalize-space(), 'Operated by')]", $root, true, "/^{$this->opt($this->t('Operated by'))}\s*(.+)/");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $flightText = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^(?<date>\w+\,\s*\d+\s*\w+)\s*(?<depTime>[\d\:]+)\s*(?<depCode>[A-Z]{3})\s*(?<arrTime>[\d\:]+)\s*(?<arrCode>[A-Z]{3})\s*(?<nextDay>[+]\d)?\D*\s*(?<duration>\d+.+)?\s*$/", $flightText, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['depTime']))
                    ->code($m['depCode']);

                if (isset($m['nextDay']) && !empty($m['nextDay'])) {
                    $s->arrival()
                        ->date(strtotime('+1 day', $this->normalizeDate($m['date'] . ', ' . $m['arrTime'])));
                } else {
                    $s->arrival()
                        ->date($this->normalizeDate($m['date'] . ', ' . $m['arrTime']));
                }

                $s->arrival()
                    ->code($m['arrCode']);

                $s->extra()
                    ->cabin($this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()][1]", $root));

                if (!empty($m['duration'])) {
                    $s->extra()
                        ->duration($m['duration']);
                }
            }

            $seats = array_filter($this->http->FindNodes("//text()[normalize-space()='Passenger Details']/following::table/descendant::text()[{$this->starts($s->getDepCode())} and {$this->contains($s->getArrCode())}]/ancestor::tr[1]/following-sibling::tr[1][{$this->contains($this->t('Seat'))}]/descendant::td[2]"));

            foreach ($seats as $seat) {
                $pax = $this->http->FindSingleNode("//text()[{$this->eq($seat)}]/ancestor::table[2]/descendant::text()[normalize-space()][1]");

                if (!empty($pax)) {
                    $s->addSeat($seat, true, true, $pax);
                } else {
                    $s->addSeat($seat);
                }
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
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

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            '$'   => ['$'],
            '€'   => ['EUR'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
            'AUD' => ['AU $'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            //Sat, 03 Sep, 00:30
            "/^(\w+\,\s*\d+\s*\w+)\,\s*([\d\:]+)$/",
        ];
        $out = [
            "$1 $year, $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }
}
