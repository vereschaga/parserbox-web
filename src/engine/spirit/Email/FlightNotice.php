<?php

namespace AwardWallet\Engine\spirit\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightNotice extends \TAccountChecker
{
    public $mailFiles = "spirit/it-222010823.eml, spirit/it-223201270.eml, spirit/it-226094776.eml, spirit/it-226539068.eml";

    public $lang = 'en';
    public $subject;

    public static $dictionary = [
        "en" => [
            'We are sorry we had to change your itinerary' => [
                'We are sorry we had to change your itinerary',
                'We are so sorry to let you know that you will not make your connecting',
                'If you changed your departure airport and have already checked bags with Spirit',
                'We are so sorry to let you know that your flight',
                'We are so sorry we had to change your itinerary',
            ],

            'We\'ve confirmed you on another flight:' => [
                'We\'ve confirmed you on another flight:',
                'We have confirmed you on another flight:',
                'Here is your new flight information:',
                'We\'ve confirmed you on another airline:',
                'We\'ve confirmed you on another flight:',
            ],

            'Flight number(s):' => ['Flight number(s):', 'Airline:'],
        ],
    ];
    private $date;

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Spirit Airlines'))}]")->length > 0) {
            if ($this->http->XPath->query("//text()[{$this->contains($this->t('We are sorry we had to change your itinerary'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('We\'ve confirmed you on another flight:'))}]")->length > 0) {
                return true;
            }

            if ($this->http->XPath->query("//text()[{$this->contains($this->t('One or more of your flights have been cancelled due to a schedule adjustment'))}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]spirit\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='YOUR CONFIRMATION CODE']/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]+)$/"))
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hi')]/following::text()[normalize-space()][1]"));

        $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Flight number(s):'))}]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            if ($this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root) == 'Delta Air Lines') {
                $s->airline()
                    ->name('Delta Air Lines')
                    ->noNumber()
                    ->confirmation($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Confirmation Code:')][1]/following::text()[normalize-space()][1]", $root));
            } elseif ($this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root) == 'American Airlines') {
                $s->airline()
                    ->name('American Airlines')
                    ->noNumber()
                    ->confirmation($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Confirmation Code:')][1]/following::text()[normalize-space()][1]", $root));
            } else {
                $s->airline()
                    ->name($this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root, true, "/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,4}/"))
                    ->number($this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root, true, "/^[\dA-Z]{2}(\d{1,4})/"));
            }

            $date = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Date:')][1]/following::text()[normalize-space()][1]", $root);

            //it-226539068.eml
            if (!empty($time = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Time:')][1]/following::text()[normalize-space()][1]", $root))) {
                $s->departure()
                    ->code($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Depart:')]/following::text()[normalize-space()][1]", $root, true, "/\(([A-Z]{3})\)/"))
                    ->date($this->normalizeDate($date . ', ' . $time));

                $s->arrival()
                    ->code($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Arrive:')]/following::text()[normalize-space()][1]", $root, true, "/\(([A-Z]{3})\)/"))
                    ->noDate();
            } else {
                //others formats
                $depInfo = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Departs:')][1]/following::text()[normalize-space()][1]", $root);

                if (preg_match("/\(([A-Z]{3})\)\s*([\d\:]+\s*A?P?M)$/", $depInfo, $m)) {
                    $s->departure()
                        ->code($m[1])
                        ->date($this->normalizeDate($date . ', ' . $m[2]));
                }

                $arrInfo = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Arrives:')][1]/following::text()[normalize-space()][1]", $root);

                if (preg_match("/\(([A-Z]{3})\)\s*([\d\:]+\s*A?P?M)$/", $arrInfo, $m)) {
                    $s->arrival()
                        ->code($m[1])
                        ->date($this->normalizeDate($date . ', ' . $m[2]));
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
        $this->date = strtotime($parser->getHeader('date'));
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

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            //Nov 09, 6:30 PM
            "#^(\w+)\s+(\d+)\,\s*([\d\:]+\s*A?P?M)$#i",
        ];
        $out = [
            "$2 $1 $year, $3",
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
