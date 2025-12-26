<?php

namespace AwardWallet\Engine\ryanair\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ScheduledTime extends \TAccountChecker
{
    public $mailFiles = "ryanair/it-158051854.eml";
    public $subjects = [
        "We’re sorry the scheduled time of your flight(s) has been changed",
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@change.ryanair.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'The Ryanair Customer Service Team')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('We’re sorry to inform you that there has been a time change to your Ryanair booking'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Accept flight(s) time change'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Accept flight change'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]change\.ryanair\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Reservation:']/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{6})$/"));

        $paxText = $this->http->FindSingleNode("//text()[normalize-space()='Passenger/s:']/following::text()[normalize-space()][1]");
        $pax = array_filter(preg_split("/\d+\.\s*/", $paxText));

        if (count($pax) > 0) {
            $f->general()
                ->travellers($pax, true);
        }

        $xpath = "//text()[contains(normalize-space(), 'Outbound') or contains(normalize-space(), 'Return')]/following::text()[normalize-space()][1]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $segmentText = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^.+\s*\((?<depCode>[A-Z]{3})\)\s*to.+\((?<arrCode>[A-Z]{3})\)\s*\w+\s*(?<day>\d+)(?<month>\w+)(?<year>\d{4})\s*Flight\s*(?<fName>[A-Z\d]{2})(?<fNumber>\d{2,4}).+at\s*(?<depHours>\d{2})(?<depMin>\d{2}).+at\s*(?<arrHours>\d{2})(?<arrMin>\d{2})/su", $segmentText, $m)) {
                $s = $f->addSegment();
                $s->airline()
                    ->name($m['fName'])
                    ->number($m['fNumber']);

                $s->departure()
                    ->code($m['depCode'])
                    ->date(strtotime($m["day"] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['depHours'] . ':' . $m['depMin']));

                $s->arrival()
                    ->code($m['arrCode'])
                    ->date(strtotime($m["day"] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['arrHours'] . ':' . $m['arrMin']));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
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
}
