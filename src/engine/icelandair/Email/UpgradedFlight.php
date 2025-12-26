<?php

namespace AwardWallet\Engine\icelandair\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class UpgradedFlight extends \TAccountChecker
{
    public $mailFiles = "icelandair/it-113556129.eml";
    public $subjects = [
        'Get Upgraded on your Icelandair flight',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@icelandair.is') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'ICELAND')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Flight')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Origin'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Destination'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]icelandair\.is$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Booking number:')]/following::text()[normalize-space()][1]", null, true, "/([A-Z\d]{5,})/"), 'Booking number');

        $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hi')]/ancestor::*[1]", null, true, "/{$this->opt($this->t('Hi'))}\s*(\D+)(?:\!|\,)/");

        if (!empty($traveller) && $traveller !== 'Guests') {
            $f->general()
                ->traveller($traveller, false);
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Flight']/ancestor::tr[1]/following-sibling::tr");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./td[1]", $root, true, "/^\s*([A-Z\d]{2})\s+/"))
                ->number($this->http->FindSingleNode("./td[1]", $root, true, "/^\s*[A-Z\d]{2}\s+(\d{2,4})/"));

            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::td[normalize-space()][2]", $root, true, "/\(([A-Z]{3})\)/"))
                ->date(strtotime(str_replace(' - ', ', ', $this->http->FindSingleNode("./descendant::td[normalize-space()][4]", $root))));

            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::td[normalize-space()][3]", $root, true, "/\(([A-Z]{3})\)/"))
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
