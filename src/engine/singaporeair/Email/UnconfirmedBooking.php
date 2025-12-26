<?php

namespace AwardWallet\Engine\singaporeair\Email;

use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parser for junk
class UnconfirmedBooking extends \TAccountChecker
{
    public $mailFiles = "singaporeair/it-57284902-junk.eml";

    public $lang = 'en';
    public static $dict = [
        'en' => [
            //            'Booking reference' => '',
            //            'Flight' => '',
            //            'Depart' => '',
        ],
    ];

    private $detectFrom = '@singaporeair.com.sg';

    private $detectSubject = [
        'Ticketing time limit for your upcoming flight(s)',
        'Ticketing Time Limit - Booking Ref:',
        'Your Waitlisted Flight(s) Is Available For Confirmation',
    ];

    private $detectBody = [
        'en' => [
            'Ticketing time limit for your upcoming flight',
            'Ticketing time limit for your waitlisted flight',
            'Your waitlisted flight(s) is available for confirmation',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("(//*[" . $this->contains($detectBody) . "])[1]")->length > 0) {
                $this->lang = $lang;
            }
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'singaporeair.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("(//*[" . $this->contains($detectBody) . "])[1]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) == false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function parseEmail(Email $email)
    {
        $xpath = "//tr[./td[normalize-space()][1][" . $this->eq($this->t('Flight')) . "] and ./td[normalize-space()][2][" . $this->eq($this->t('Depart')) . "]]/following::tr[1][count(./td[normalize-space()] ) = 3]/ancestor::*[1]/tr";
        $nodes = $this->http->XPath->query($xpath);
        // Status
        $junkText = [
            'to complete the booking for your following flight(s):',
            'the following waitlisted flight(s) is available for confirmation',
        ];

        if ($nodes->length > 0 && !empty($this->http->FindSingleNode("(//*[" . $this->contains($junkText) . "])[1]"))) {
            $email->setIsJunk(true);

            return $email;
        }

        return $email;

        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->starts($this->t('Booking reference')) . "]", null, true,
                "#" . $this->preg_implode($this->t('Booking reference')) . "\s+([A-Z\d]{5,7})\s*$#"))
        ;

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $text = $this->http->FindSingleNode("./td[normalize-space()][1]", $root);

            if (preg_match("#^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d{1,5})\s*$#", $text, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
            }

            $regexp = '#^\s*(?<code>[A-Z]{3})\s+(?<time>\d{1,2}:\d{2})\s+(?<date>.+)\s*\n\s*(?<name>[\s\S]+)#';
            // Departure
            $text = implode("\n", $this->http->FindNodes("./td[normalize-space()][2]//text()[normalize-space()]", $root));

            if (preg_match($regexp, $text, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name(preg_replace("#\s*\n\s*#", ', ', trim($m['name'])))
                    ->date(strtotime($m['date'] . ', ' . $m['time']))
                ;
            }
            // Arrival
            $text = implode("\n", $this->http->FindNodes("./td[normalize-space()][3]//text()[normalize-space()]", $root));

            if (preg_match($regexp, $text, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name(preg_replace("#\s*\n\s*#", ', ', trim($m['name'])))
                    ->date(strtotime($m['date'] . ', ' . $m['time']))
                ;
            }
        }
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        $in = [
            //02 Jul (Mon), 15:50
            '#^\s*(\d+)\s+([^\d\s]+)\s*\(([^\d\s]+)\)\s*,\s*(\d+:\d+)$#u',
        ];
        $out = [
            '$3, $1 $2 year, $4',
        ];
        $str = preg_replace($in, $out, $date);

        if (preg_match("#^(?<week>[^\d\s]+),\s+(?<date>\d+\s+\w+.+)#", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $m['date'] = str_replace('year', date('Y', $this->dateEmail), $m['date']);
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } else {
            $str = strtotime($str);
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
