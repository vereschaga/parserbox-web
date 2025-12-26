<?php

namespace AwardWallet\Engine\wagonlit\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TripItineraryFor2022 extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-187480958.eml";
    public $subjects = [
        'Trip itinerary for',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Traveler:' => ['Traveler:', 'Travelers:'],
        ],
    ];
    private $date;

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@reservation.mycwt.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('CWT'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('CWT TRIP LOCATOR:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('IN CASE YOU NEED ASSISTANCE'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your trip itinerary'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]reservation\.mycwt\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $dateRes = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Date:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Date:'))}\s*(.+)/");

        if (!empty($dateRes)) {
            $f->general()
                ->date($this->normalizeDate($dateRes));
        }

        $f->general()
            ->travellers(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Traveler:'))}]/ancestor::tr[1]/descendant::text()[not({$this->contains($this->t('Traveler:'))})]")))
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Trip Locator:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Trip Locator:'))}\s*(.+)/"));

        $nodes = $this->http->XPath->query("//img[contains(@src, 'FlightTrip')]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./preceding::text()[normalize-space()='DEPARTURE'][1]/preceding::text()[normalize-space()][1]", $root, true, "/\s([A-Z\d]{2})\d{2,4}/"))
                ->number($this->http->FindSingleNode("./preceding::text()[normalize-space()='DEPARTURE'][1]/preceding::text()[normalize-space()][1]", $root, true, "/\s[A-Z\d]{2}(\d{2,4})/"));

            $s->departure()
                ->code($this->http->FindSingleNode("./preceding::text()[normalize-space()='DEPARTURE'][1]/following::text()[normalize-space()][1]", $root, true, "/\(([A-Z]{3})\)/u"))
                ->date($this->normalizeDate($this->http->FindSingleNode("./preceding::text()[normalize-space()='DEPARTURE'][1]/following::text()[normalize-space()][2]", $root)));

            $s->arrival()
                ->code($this->http->FindSingleNode("./following::text()[normalize-space()='ARRIVAL'][1]/following::text()[normalize-space()][1]", $root, true, "/\(([A-Z]{3})\)/"))
                ->date($this->normalizeDate($this->http->FindSingleNode("./following::text()[normalize-space()='ARRIVAL'][1]/following::text()[normalize-space()][2]", $root)));

            $s->extra()
                ->duration($this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root, true, "/{$this->opt($this->t('Flight duration:'))}\s*(.+)/"))
                ->cabin($this->http->FindSingleNode("./following::text()[contains(normalize-space(), 'Piece')][1]", $root, true, "/^(\D+)\s*\([A-Z]\)/"))
                ->bookingCode($this->http->FindSingleNode("./following::text()[contains(normalize-space(), 'Piece')][1]", $root, true, "/^\D+\s*\(([A-Z])\)/"));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        if ($this->http->XPath->query("//img[contains(@src, 'FlightTrip')]")->length > 0) {
            $this->ParseFlight($email);
        }

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

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);

        $in = [
            "#^(\d+)\s*(\w+)\s*(\d{2})$#u", //24 Aug 22
            "#^(\w+)\,\s*(\w+)\s*(\d+)[\|\s]+([\d\:]+)$#", //Mon, Sep 19 | 11:30
        ];
        $out = [
            "$1 $2 20$3",
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
