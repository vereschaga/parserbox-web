<?php

namespace AwardWallet\Engine\virgin\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ScheduleChange extends \TAccountChecker
{
    public $mailFiles = "virgin/it-126909990.eml";
    public $subjects = [
        'Schedule Change affecting your flight',
    ];

    public $lang = 'en';
    public $head;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        $this->head = $headers;

        if (isset($headers['from']) && stripos($headers['from'], '@virginatlantic.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Virgin Atlantic Airways Limited')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('The departure and/or arrival time of your flight'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('What\'s Changed?'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]virginatlantic\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Booking Reference Number:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Your Booking Reference Number:'))}\s*([A-Z\d]{6,})/"));

        $xpath = "//text()[starts-with(normalize-space(), 'The departure and/or arrival time of your flight')]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            if (!empty($this->head) && $this->detectEmailByHeaders($this->head) == true
             || $this->http->XPath->query("//text()[contains(normalize-space(), 'virginatlanticschedulechanges@virginatlantic.com')]")->length > 0) {
                $s->airline()
                    ->name('VS');
            } else {
                $s->airline()
                    ->noName();
            }

            $s->airline()
                ->number($this->http->FindSingleNode("./descendant::text()[normalize-space()='Flight Number']/following::text()[normalize-space()][1]", $root))
                ->operator($this->http->FindSingleNode("./descendant::text()[normalize-space()='Operated By']/following::text()[normalize-space()][1]", $root));

            $depTime = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Departure Time']/following::text()[normalize-space()][1]", $root);
            $depDate = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Departure Date']/following::text()[normalize-space()][1]", $root);

            $arrTime = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Arrival Time']/following::text()[normalize-space()][1]", $root);
            $arrDate = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Arrival Date']/following::text()[normalize-space()][1]", $root);

            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()='From']/following::text()[normalize-space()][1]", $root))
                ->date(strtotime($depDate . ', ' . $depTime));

            $s->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()='To']/following::text()[normalize-space()][1]", $root))
                ->date(strtotime($arrDate . ', ' . $arrTime));
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
