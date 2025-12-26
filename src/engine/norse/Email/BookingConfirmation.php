<?php

namespace AwardWallet\Engine\norse\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "norse/it-672237531.eml";
    public $subjects = [
        'Your booking confirmation',
    ];

    public $lang = 'en';
    public $date;

    public static $dictionary = [
        "en" => [
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Thank you for choosing to fly Norse')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Itinerary'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Traveler '))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Ticket Amount'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flynorse\.com$/', $from) > 0;
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

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation#:')]", null, true, "/\:\s*([A-Z\d]{6})$/"))
            ->travellers(preg_replace("/(?:,|Seat)/", "", array_unique($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Traveler')]/following::text()[normalize-space()][1]"))));

        $priceText = $this->http->FindSingleNode("//text()[normalize-space()='Ticket Amount']/ancestor::tr[1]/descendant::td[2]");

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

        $xpath = "//img[contains(@src, 'circle-arrow')]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineInfo = $this->http->FindSingleNode("./ancestor::tr[1]/preceding::tr[1]/ancestor::table[1]/descendant::text()[starts-with(normalize-space(), 'Flight')][1]", $root, true, "/^{$this->opt($this->t('Flight'))}\s*(.+)/");

            if (preg_match("/^(?<name>[A-Z\d]{2})(?<number>\d{1,4})/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $operator = $this->http->FindSingleNode("./ancestor::tr[1]/preceding::tr[1]/ancestor::table[1]/descendant::text()[starts-with(normalize-space(), 'Operated by')][1]", $root, true, "/^{$this->opt($this->t('Operated by'))}\s*(.+)/");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $depText = implode("\n", $this->http->FindNodes("./ancestor::tr[1]/descendant::td[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/(?<depDate>\w+\,\s*\d+\s*\w+)\n(?<depTime>[\d\:]+\s*A?P?M)\n(?<depCode>[A-Z]{3})\n(?<depName>.+)/", $depText, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date(strtotime($m['depDate'] . ', ' . $m['depTime']));
            }

            $s->extra()
                ->duration($this->http->FindSingleNode("./ancestor::td[1]", $root));

            $arrText = implode("\n", $this->http->FindNodes("./ancestor::tr[1]/descendant::td[3]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/(?<arrDate>\w+\,\s*\d+\s*\w+)\n(?<arrTime>[\d\:]+\s*A?P?M)\n(?<arrCode>[A-Z]{3})\n(?<arrName>.+)/", $arrText, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->date(strtotime($m['arrDate'] . ', ' . $m['arrTime']));
            }

            $cabin = array_unique($this->http->FindNodes("./ancestor::tr[1]/preceding::tr[1]/ancestor::table[1]/descendant::text()[starts-with(normalize-space(), 'Cabin')][1]/ancestor::tr[1]/descendant::td[2]", $root));

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin(implode(', ', $cabin));
            }

            $meals = array_filter(array_unique($this->http->FindNodes("./ancestor::tr[1]/preceding::tr[1]/ancestor::table[1]/descendant::text()[starts-with(normalize-space(), 'Meals')][1]/ancestor::tr[1]/descendant::td[2][not(contains(normalize-space(), 'None'))]", $root)));

            if (!empty($meals)) {
                $s->extra()
                    ->meals($meals);
            }

            $seat = $this->http->FindNodes("./ancestor::tr[1]/preceding::tr[1]/ancestor::table[1]/descendant::text()[contains(normalize-space(), 'Seat')]/ancestor::td[1]", $root, "/{$this->opt($this->t('Seat'))}\s*(\d+[A-Z])/");

            if (count($seat) > 0) {
                $s->extra()
                    ->seats($seat);
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
