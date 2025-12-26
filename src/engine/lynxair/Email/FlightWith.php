<?php

namespace AwardWallet\Engine\lynxair\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightWith extends \TAccountChecker
{
    public $mailFiles = "lynxair/it-635462709.eml, lynxair/it-640752796.eml, lynxair/it-806341076.eml";
    public $subjects = [
        'Important information about your upcoming flight with Lynx Air',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Flight Details' => ['Flight Details', 'Your New Flight'],
            'Departure'      => ['Departure', 'Revised departure:'],
            'Arrival'        => ['Arrival', 'Revised arrival:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@notifications.lynxair.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'www.FlyLynx.com')]")->length > 0
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('Booking Reference Code:'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('Revised departure:'))}]")->length > 0)
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Flight Details'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]notifications\.lynxair\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->FlightHTML($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function FlightHTML(Email $email)
    {
        $f = $email->add()->flight();

        $travellers = $this->http->FindNodes("//text()[normalize-space()='Passenger(s)']/ancestor::tr[1]/following-sibling::tr/descendant-or-self::tr[not(.//tr)]/td[1]");

        if (!empty($travellers)) {
            $f->general()
                ->travellers(preg_replace("/^\s*(?:Mrs|Ms|Mr|Miss|Mstr)\s+(.+)/", "$1", $travellers));
        }

        $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference Code:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking Reference Code:'))}\s*([A-Z\d]{6})$/");

        if (!empty($conf)) {
            $f->general()
                ->confirmation($conf);
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('Your new flights are now confirmed'))}]")->length > 0
        && $this->http->XPath->query("//text()[{$this->contains($this->t('Revised departure:'))}]")->length > 0) {
            $f->general()
                ->noConfirmation();
        }

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Flight Details'))}]/ancestor::tr[1]/following::text()[{$this->starts($this->t('Departure'))}]/ancestor::td[contains(normalize-space(), ':')][1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $airlineInfo = $this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(), '#')][1]", $root);

            if (preg_match("/^[#]\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})$/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $depInfo = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));

            if (preg_match("/^{$this->opt($this->t('Departure'))}\n(?<date>\d+.*\d{4})\n(?<time>\d+\:\d+\s*A?P?M)\n(?<depCode>[A-Z]{3})[\s\-]*(?<depName>.+)$/", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date(strtotime($m['date'] . ', ' . $m['time']));
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./following::td[1][{$this->contains($this->t('Arrival'))}]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^{$this->opt($this->t('Arrival'))}\n(?<date>\d+.*\d{4})\n(?<time>\d+\:\d+\s*A?P?M)\n(?<arrCode>[A-Z]{3})[\s\-]*(?<arrName>.+)$/", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->date(strtotime($m['date'] . ', ' . $m['time']));
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }
}
