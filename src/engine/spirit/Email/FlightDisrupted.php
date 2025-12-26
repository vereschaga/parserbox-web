<?php

namespace AwardWallet\Engine\spirit\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightDisrupted extends \TAccountChecker
{
    public $mailFiles = "spirit/it-220440367.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Spirit Airlines'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('We are sorry to let you know that your flight'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('has been disrupted'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('REBOOKING OPTIONS'))}]")->length > 0;
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

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('has been disrupted'))}]")->length > 0) {
            $f->general()
                ->cancelled()
                ->status('disrupted');
        }

        $s = $f->addSegment();

        $flightInfo = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Hi')]/following::text()[contains(normalize-space(), 'from')][1]/ancestor::tr[1]");

        if (preg_match("/flight\s*(?<name>[A-Z\d]{2})(?<number>\d{2,4}).+\((?<depCode>[A-Z]{3})\).*\((?<arrCode>[A-Z]{3})\).*on\s*(?<day>.+\d{4})/", $flightInfo, $m)) {
            $s->airline()
                ->name($m['name'])
                ->number($m['number']);

            $s->departure()
                ->code($m['depCode'])
                ->day(strtotime($m['day']));

            $s->arrival()
                ->code($m['arrCode'])
                ->noDate();
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
