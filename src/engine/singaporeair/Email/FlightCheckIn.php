<?php

namespace AwardWallet\Engine\singaporeair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightCheckIn extends \TAccountChecker
{
    public $mailFiles = "singaporeair/it-639262512.eml, singaporeair/it-639313867.eml, singaporeair/it-639316529.eml, singaporeair/it-717024836.eml, singaporeair/it-726168072.eml, singaporeair/it-732221198.eml, singaporeair/it-732885447.eml, singaporeair/it-791603871.eml, singaporeair/it-800416400.eml";
    public $subjects = [
        'Your check-in confirmation',
        'Your check-in has been cancelled',
        'Your seat selection confirmation',
        'Auto Check-in Turned Off',
        'has shared a check-in itinerary',
        'Check-in & Travel Reminder',
        'Your booking confirmation',
    ];

    public $lang = 'en';
    public $year;

    public static $dictionary = [
        "en" => [
            'New seat'                                    => ['New seat', 'Seat'],
            'Passengers'                                  => ['Passengers', 'Passengers checked in', 'Passenger'],
            'Here are the details of your seat selection' =>
                [
                    'Here are the details of your seat selection',
                    'You are all set for your trip!',
                    'Your check-in has been cancelled',
                    'Thank you for providing your details to be automatically checked in',
                    'You\'re all set for your next trip',
                    'Your flight is now open for check-in',
                ],
            'Itinerary' => ['Itinerary', 'Flight 1', 'Depart'],
            'Cancelled' => ['Your check-in has been cancelled'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && (stripos($headers['from'], '@singaporeair.com') !== false || stripos($headers['from'], '@flightinfo.singaporeair.com') !== false)) {
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
        return $this->http->XPath->query("//text()[{$this->contains(['Singapore Airlines.', 'singaporeair.com'])}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Here are the details of your seat selection'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Itinerary'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]singaporeair.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $travellers = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Seat'))} or {$this->eq($this->t('Baggage'))} or {$this->eq($this->t('Meal'))}][not(preceding::text()[{$this->eq($this->t('Payment details'))}])]/ancestor::table[1]"
            . "/descendant::text()[starts-with(translate(normalize-space(), '0123456789', 'dddddddddd'), 'd.')]/ancestor::tr[1]",
            null, "/^\s*\d+\.(?:\s*(?:Mrs|Mr|Ms|Miss|Mstr|Dr)\s+)?(.+)/u")));

        $travellers = array_unique(array_filter(array_merge($travellers,
            $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::table[descendant::text()[normalize-space()][1][{$this->eq($this->t('Passengers'))}]][last()]"
                . "/descendant::text()[starts-with(translate(normalize-space(), '0123456789', 'dddddddddd'), 'd.')]/ancestor::tr[1]",
            null, "/^\s*\d+\.(?:\s*(?:Mrs|Mr|Ms|Miss|Mstr|Dr)\s+)?(.+)/u"))));

        foreach ($travellers as $i => $traveller) {
            if (preg_match("/\s*\(\s*Infant\s*\)\s*$/", $traveller)) {
                $names = $this->http->FindNodes("//tr[not(.//tr)][{$this->contains($traveller)}]//text()[normalize-space()]");
                $names = array_values(array_filter(preg_replace("/^\s*\d+\.\s*$/u", '', $names)));

                if (count($names) === 2) {
                    $travellers[$i] = preg_replace(["/^\s*\d+\.\s*/u", "/^\s*(?:Mrs|Mr|Ms|Miss|Mstr|Dr)\s+/u"], '', $names[0]);
                    $f->general()
                        ->infant(preg_replace("/\s*\(\s*Infant\s*\)\s*$/", '', $names[1]));
                }
            }
        }

        if (count($travellers) === 0) {
            $travellers[] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, true, "/^\s*{$this->opt($this->t('Dear '))}\s*(?:MISS|MSTR|MR|MS|MRS)\s+(.+)/u");
            $travellers = array_filter($travellers);
        }

        if (count($travellers) > 0) {
            $f->general()
                ->travellers(str_replace("Join KrisFlyer", "", $travellers));
        }

        $confs = $this->http->FindSingleNode("//text()[normalize-space()='BOOKING REFERENCE']/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6})$/");

        if (!empty($confs)) {
            $f->general()
                ->confirmation($confs);
        } else {
            $f->general()
                ->noConfirmation();
        }

        $nodes = $this->http->XPath->query("//img[contains(@src, 'line-with-padding')]/ancestor::table[normalize-space()][1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flightInfo = implode("\n", $this->http->FindNodes("./preceding::table[normalize-space()][1]/ancestor::*[2]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<aName>[A-Z\d]{2})\s*(?<fNumber>\d{1,4})\s*[\n•]\s*(?<aircraft>.+)\n\s*(?<cabin>.+)\s*\n\s*Operated by\s*(?<operator>.*)\s*$/u", $flightInfo, $m)
                || preg_match("/^\s*(?<aName>[A-Z\d]{2})\s*(?<fNumber>\d{1,4})\s*[\n•]\s*(?<cabin>[[:alpha:]]+)\n\s*Operated by\s*(?<operator>.*)\s*$/u", $flightInfo, $m)
                || preg_match("/^\s*(?<aName>[A-Z\d]{2})\s*(?<fNumber>\d{1,4})\s*[\n•]\s*(?<aircraft>.+)\n\s*(?<cabin>.+)\s*$/u", $flightInfo, $m)
                || preg_match("/^\s*(?<aName>[A-Z\d]{2})\s*(?<fNumber>\d{1,4})\s*[\n•]\s*(?<cabin>.+)\s*$/u", $flightInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $s->extra()
                    ->cabin($m['cabin']);

                if (isset($m['aircraft']) && !empty($m['aircraft'])) {
                    $s->extra()
                        ->aircraft($m['aircraft']);
                }

                if (isset($m['operator']) && !empty($m['operator'])) {
                    $s->airline()
                        ->operator($m['operator']);
                }
            }
            $depInfo = $this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $root);

            if (preg_match("/^(?<date>.+?)\s*(?<code>[A-Z]{3})\s*(?<time>[\d\:]+)\s*(?<depName>.+)\s+Terminal\s*(?<depTerminal>.+)$/", $depInfo, $m)
                || preg_match("/^(?<date>.+?)\s*(?<code>[A-Z]{3})\s*(?<time>[\d\:]+)\s*(?<depName>.+)$/", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['time']));

                if (isset($m['depTerminal']) && !empty($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }
            }

            $arrInfo = $this->http->FindSingleNode("./descendant::table[3]", $root);

            if (preg_match("/^(?<date>.+?)\s*(?<code>[A-Z]{3})\s*(?<time>[\d\:]+)\s*(?<arrName>.+)\s+Terminal\s*(?<arrTerminal>.+)$/", $arrInfo, $m)
                || preg_match("/^(?<date>.+?)\s*(?<code>[A-Z]{3})\s*(?<time>[\d\:]+)\s*(?<arrName>.+)$/", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['time']));

                if (isset($m['arrTerminal']) && !empty($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }
            }

            $seats = array_filter($this->http->FindNodes("//text()[{$this->eq($s->getDepCode() . ' - ' . $s->getArrCode())}]/following::text()[normalize-space()][1][{$this->contains($this->t('New seat'))}]/ancestor::tr[1]", null, "/{$this->opt($this->t('New seat'))}\s*(\d+[A-Z])/"));

            if (count($seats) > 0) {
                $s->setSeats($seats);
            }

            $meals = $this->http->FindNodes("//text()[{$this->eq($s->getDepCode() . ' - ' . $s->getArrCode())}]/following::tr[starts-with(normalize-space(), 'Meal')][1]/descendant::td[2]/descendant::text()[normalize-space()]");

            if (count($meals) > 0) {
                $s->extra()
                    ->meals(array_unique($meals));
            }
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Cancelled'))}]")->length > 0) {
            $f->general()
                ->cancelled()
                ->status('cancelled');
        }

        $notDisplay = "not(ancestor-or-self::*[contains(@style, 'display: none') or contains(@style, 'display:none')])";

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Total cost'][{$notDisplay}]/ancestor::tr[1]/td[normalize-space()][last()]", null, true, "/^([\d\.\,]+)$/");
        $currency = $this->http->FindSingleNode("//text()[normalize-space()='Cost breakdown'][{$notDisplay}]/ancestor::tr[1]/td[normalize-space()][last()]", null, true, "/^([A-Z]{3})$/");

        if (!empty($total) && !empty($currency)) {
            $f->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Fares'][{$notDisplay}]/ancestor::tr[1]/td[normalize-space()][last()]", null, true, "/^([\d\.\,]+)$/");

            if (!empty($cost)) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $taxNodes = $this->http->XPath->query("//text()[normalize-space()='Fares'][{$notDisplay}]/ancestor::tr[1]/following-sibling::tr[{$notDisplay}]");

            foreach ($taxNodes as $tRoot) {
                $name = $this->http->FindSingleNode("td[normalize-space()][1]", $tRoot);
                $tax = $this->http->FindSingleNode("td[normalize-space()][last()]", $tRoot, true, "/^([\d\.\,]+)$/");

                if (!empty($tax)) {
                    $f->price()
                        ->fee($name, PriceHelper::parse($tax, $currency));
                }
            }
        }

        $earned = $this->http->FindSingleNode("//text()[{$this->eq(['Earn KrisFlyer miles'])}][{$notDisplay}]/following::text()[string-length()>2][1][contains(normalize-space(), 'miles')][not(contains(., '%'))]");

        if (empty($earned)) {
            $earned = $this->http->FindSingleNode("//text()[{$this->eq(['You\'ll earn up to'])}][{$notDisplay}]/ancestor::*[not({$this->eq(['You\'ll earn up to'])})][1]",
                null, true, "/You'll earn up to (\d+[\d,]* KrisFlyer miles)\s*$/");
        }

        if (!empty($earned)) {
            $f->setEarnedAwards($earned);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->year = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Singapore Airlines')][contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dddd')]", null, true, "/\s(\d+)\s*{$this->opt('Singapore Airlines')}/");
        $this->ParseFlight($email);

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

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $year = $this->year;
        // $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // 12 Feb 2025 (Wed), 06:50
            "#^\s*(\d{1,2})\s*([[:alpha:]]+)\s+(\d{4})\s*\([[:alpha:]]+\)\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)$#iu",
            // Fri, 02 Feb
            "#^\s*(\w+\,\s*\d+\s*\w+)\,\s*([\d\:]+)$#iu",
            // 01 Feb (Thu), 00:20
            "#^\s*(\d{1,2})\s*([[:alpha:]]+)\s*\(([[:alpha:]]+)\)\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)$#iu",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $year, $2",
            "$3, $1 $2 $year, $4",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str = '.print_r( $str,true));

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
