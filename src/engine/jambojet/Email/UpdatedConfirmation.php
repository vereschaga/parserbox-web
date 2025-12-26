<?php

namespace AwardWallet\Engine\jambojet\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class UpdatedConfirmation extends \TAccountChecker
{
    public $mailFiles = "jambojet/it-763923883.eml, jambojet/it-765774597.eml";

    public $subjects = [
        'Important information about your Jambojet Booking',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'jambojet.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Jambojet'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('IMPORTANT! YOUR FLIGHT SCHEDULE HAS BEEN CHANGED!'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Flight information:'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]jambojet\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->UpdatedConfirmation($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function UpdatedConfirmation(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation number:'))}]/ancestor::tr[1]", null, true, "/^\D+\:\s*([A-Z\d]{6})$/"));

        $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Dear'))}]/ancestor::td[1]", null, false, "/Dear\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\,$/u");
        $f->addTraveller(preg_replace("/^(?:Mr|Ms)/", "", $traveller), false);

        $segmentNodes = $this->http->XPath->query("//text()[{$this->contains($this->t('From'))}]/ancestor::tr[1]/following-sibling::tr");

        foreach ($segmentNodes as $root) {
            $s = $f->addSegment();

            $airInfo = $this->http->FindSingleNode("./descendant::td[1]", $root, true, '/((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,4})$/');

            if (preg_match("/(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})$/", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $depInfo = $this->http->FindSingleNode("./descendant::td[2]", $root);

            if (preg_match("/^(?<depName>.*)\((?<depCode>[A-Z]{3})\)\s*(?<depTime>\d+\:\d+)\s*(?<depDate>\w+\s*\d+\s*\w+\s*\d+)$/", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->date(strtotime($m['depDate'] . ' ' . $m['depTime']))
                    ->code($m['depCode']);
            }

            $arrInfo = $this->http->FindSingleNode("./descendant::td[3]", $root);

            if (preg_match("/^(?<arrName>.*)\((?<arrCode>[A-Z]{3})\)\s*(?<arrTime>\d+\:\d+)\s*(?<arrDate>\w+\s*\d+\s*\w+\s*\d+)$/", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->date(strtotime($m['arrDate'] . ' ' . $m['arrTime']))
                    ->code($m['arrCode']);
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
