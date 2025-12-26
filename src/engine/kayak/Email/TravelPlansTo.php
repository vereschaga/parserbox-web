<?php

namespace AwardWallet\Engine\kayak\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelPlansTo extends \TAccountChecker
{
    public $mailFiles = "kayak/it-144078237.eml, kayak/it-144563089.eml, kayak/it-146150671.eml, kayak/it-397716108.eml";
    public $subjects = [
        // en
        'Check out your travel plans to',
        // de
        'Schau dir deine Reiseplanung für',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            // flight
            'Route'               => 'Route',
            'Flight confirmation' => ['Flight confirmation', 'Flight Confirmation'],
            // 'Operated by' => '',
            // rental
            'Your car rental details' => 'Your car rental details',
            'Confirmation number:'    => ['Confirmation number:', 'Confirmation Number:', 'Bestätigungsnummer:'], //  hotel
            // 'Pick-up location' => '',
            // 'Vehicle type:' => '',
            // 'Pick up' => '',
            'Drop off' => ['Drop off', 'Drop-off'],
            // 'Enterprise' => '', ??
            // hotel
            // 'Your stay at' => '',
            // 'View hotel' => '',
            'Check in' => 'Check in',
            // 'Check out' => '',
        ],
        "de" => [
            // flight
            'Route'               => 'Route',
            'Flight confirmation' => ['Flight confirmation', 'Flight Confirmation'],
            // 'Operated by' => '',
            // rental
            'Your car rental details' => 'Your car rental details',
            // 'Confirmation number:' => '', //  hotel
            // 'Pick-up location' => '',
            // 'Vehicle type:' => '',
            // 'Pick up' => '',
            // 'Drop off' => '',
            // 'Enterprise' => '', ??
            // hotel
            'Your stay at' => 'Dein Aufenthalt im',
            'View hotel'   => 'Hotel ansehen',
            'Check in'     => 'Check-in',
            'Check out'    => 'Check-out',
        ],
    ];
    private $date;

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@message.kayak.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'KAYAK Software Corporation')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]message\.kayak\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $conf = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Flight confirmation'))}])[last()]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6})$/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation number:'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6})$/");
        }
        $f->general()
            ->confirmation($conf);

        $xpath = "//text()[{$this->eq($this->t('Route'))}]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flight = '';
            $flightNode = $this->http->FindSingleNode("./following::text()[contains(normalize-space(), ':')][1]/ancestor::td[1]/descendant::text()[normalize-space()][1]", $root);

            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,5})\s*$/", $flightNode, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
                $flight = $flightNode;
            } else {
                $s->airline()
                    ->noName()
                    ->noNumber();
            }

            $operator = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Operated by'))}][1]/following::text()[normalize-space()][1]", $root);

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $s->departure()
                ->code($this->http->FindSingleNode("./following::text()[contains(normalize-space(), 'to')][1]", $root, true, "/^\s*([A-Z]{3})\s/"))
                ->date($this->normalizeDate($this->http->FindSingleNode("./following::text()[contains(normalize-space(), ':')][1]/ancestor::td[1]", $root, true, "/^\s*{$flight}\s*(.+)/")));

            $s->arrival()
                ->code($this->http->FindSingleNode("./following::text()[contains(normalize-space(), 'to')][1]", $root, true, "/\s([A-Z]{3})\s*$/"))
                ->date($this->normalizeDate($this->http->FindSingleNode("./following::text()[contains(normalize-space(), ':')][2]/ancestor::td[1]", $root, true, "/^\s*{$flight}\s*(.+)/")));
        }
    }

    public function ParseCar(Email $email)
    {
        $r = $email->add()->rental();
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation number:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation number:'))}\s*([\dA-Z]+)\s*$/"));

        $r->pickup()
            ->location($this->http->FindSingleNode("//text()[{$this->contains($this->t('Pick-up location'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Pick-up location'))}\s*(?:{$this->opt($this->t('Enterprise'))})?\s*(.+)/"));

        $r->car()
            ->type($this->http->FindSingleNode("//text()[{$this->contains($this->t('Vehicle type:'))}]", null, true, "/{$this->opt($this->t('Vehicle type:'))}\s*(.+)/"))
            ->model($this->http->FindSingleNode("//text()[{$this->contains($this->t('Vehicle type:'))}]/ancestor::tr[1]/descendant::text()[normalize-space()][2]"))
            ->image($this->http->FindSingleNode("//text()[{$this->contains($this->t('Vehicle type:'))}]/following::img[1]/@src"));

        $depDate = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Pick up'))}]/following::text()[normalize-space()][1]");
        $depTime = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Pick up'))}]/following::text()[normalize-space()][2]");

        $arrDate = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Drop off'))}]/following::text()[normalize-space()][1]");
        $arrTime = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Drop off'))}]/following::text()[normalize-space()][2]");

        $r->pickup()
            ->date(strtotime($depDate . ', ' . $depTime));

        $r->dropoff()
            ->noLocation()
            ->date(strtotime($arrDate . ', ' . $arrTime));
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation number:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation number:'))}\s*([A-Z\d]+)/");

        if ($this->http->XPath->query("(//node()[{$this->contains($this->t('Confirmation number:'))}])[1]")->length === 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your stay at'))}]/following::text()[{$this->contains($this->t('View hotel'))}]")->length > 0
            && $this->http->XPath->query("//text()[contains(., ':')][preceding::text()[{$this->contains($this->t('Your stay at'))}] and following::text()[{$this->contains($this->t('View hotel'))}]]")->length === 0
        ) {
            $h->general()
                ->noConfirmation();
        } else {
            $h->general()
                ->confirmation($conf);
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your stay at'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Your stay at'))}\s*(.+)/"))
            ->address($this->http->FindSingleNode("//text()[{$this->starts($this->t('View hotel'))}]/preceding::text()[string-length()> 10][1]/ancestor::td[1]"));

        $inDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check in'))}]/following::text()[string-length()>3][1]");
        $inTime = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check in'))}]/following::text()[string-length()>3][2]");

        $outDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check out'))}]/following::text()[string-length()>3][1]");
        $outTime = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check out'))}]/following::text()[string-length()>3][2]");

        $h->booked()
            ->checkIn($this->normalizeDate($inDate . ', ' . $inTime))
            ->checkOut($this->normalizeDate($outDate . ', ' . $outTime));
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Route'))}]")->length > 0) {
            $this->ParseFlight($email);
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Your car rental details'))}]")->length > 0) {
            $this->ParseCar($email);
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Check in'))}]")->length > 0) {
            $this->ParseHotel($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (
                !empty($dict['Route']) && $this->http->XPath->query("//text()[{$this->eq($dict['Route'])}]")->length > 0
                || !empty($dict['Your car rental details']) && $this->http->XPath->query("//text()[{$this->eq($dict['Your car rental details'])}]")->length > 0
                || !empty($dict['Check in']) && $this->http->XPath->query("//text()[{$this->eq($dict['Check in'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
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

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        // $this->logger->debug('$str = '.print_r( $str,true));
        $in = [ // with year - first
            // Dienstag, 13. Juni 2023, 15:00
            "#^\w+,\s*(\d+)[.]?\s*([[:alpha:]]+)\.?\s*(\d{4}),\s*([\d\:]+\s*(?:[ap]\.?m\.?)?)$#u",
            //Tue Jun 21 6:15 am
            "#^(\w+)\.?\s*(\w+)\.?\s*(\d+)\s*([\d\:]+\s*a?\.?p?\.?m\.?)$#u",
            //Fri. 1 Apr. 2:55 p.m.
            "#^(\w+)\.?\s*(\d+)\s*(\w+)\.?\s*([\d\:]+\s*a?\.?p?\.?m\.?)$#u",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1, $3 $2 $year, $4",
            "$1, $2 $3 $year, $4",
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
