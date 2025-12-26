<?php

namespace AwardWallet\Engine\yukon\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightReminder extends \TAccountChecker
{
    public $mailFiles = "yukon/it-766281536.eml";
    public $subjects = [
        "Airline Flight Reminder",
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@flyairnorth.com') !== false) {
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
        return $this->http->XPath->query("//text()[{$this->contains($this->t('flyairnorth.com'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Welcome Aboard.'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('We look forward to welcoming you onboard on'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Flight:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flyairnorth\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Itinerary Number:']/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/"))
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/^{$this->opt($this->t('Dear'))}\s*(.+)\,$/"));

        $s = $f->addSegment();

        $flightInfo = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Flight:')]/following::text()[normalize-space()][1]");

        if (preg_match("/(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})$/", $flightInfo, $m)) {
            $s->airline()
                ->name($m['aName'])
                ->number($m['fNumber']);
        }

        $date = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'We look forward to welcoming you onboard on')]", null, true, "/^{$this->opt($this->t('We look forward to welcoming you onboard on'))}\s*\w+\,(.+)\.$/");

        $depInfo = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Departing:')]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<depName>.+)\s*\((?<depCode>[A-Z]{3})\)\s*\-\s*(?<depTime>[\d\:]+A?P?M)$/", $depInfo, $m)) {
            $s->departure()
                ->name($m['depName'])
                ->code($m['depCode'])
                ->date(strtotime($date . ', ' . $m['depTime']));
        }

        $arrInfo = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Arriving:')]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<arrName>.+)\s*\((?<arrCode>[A-Z]{3})\)\s*\-\s*(?<arrTime>[\d\:]+A?P?M)$/", $arrInfo, $m)) {
            $s->arrival()
                ->name($m['arrName'])
                ->code($m['arrCode'])
                ->date(strtotime($date . ', ' . $m['arrTime']));
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }
}
