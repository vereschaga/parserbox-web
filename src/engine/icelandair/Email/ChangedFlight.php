<?php

namespace AwardWallet\Engine\icelandair\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ChangedFlight extends \TAccountChecker
{
	public $mailFiles = "icelandair/it-850389505.eml, icelandair/it-857943178.eml";
    public $subjects = [
        'Important information about your flight',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'detectPhrase' => ['Check your new itinerary',],
            'DEPARTURE' => 'DEPARTURE'
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'info.icelandair.is') !== false) {
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
        // detect Provider
        if (
            $this->http->XPath->query("//a/@href[{$this->contains(['icelandair.is'])}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Icelandair'])}]")->length === 0
        ) {
            return false;
        }

        // detect Format
        foreach (self::$dictionary as $dict) {
            if (!empty($dict['detectPhrase']) && $this->http->XPath->query("//*[{$this->contains($dict['detectPhrase'])}]")->length > 0
                && !empty($dict['DEPARTURE']) && $this->http->XPath->query("//*[{$this->contains($dict['DEPARTURE'])}]")->length > 0
            ) {
                return true;
            }
        }
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]info\.icelandair\.is$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference:'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{5,7})$/"), 'Booking reference');

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}[ ]*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*(\,|$)/u");

        if (!empty($traveller) && $traveller !== 'passenger') {
            $f->general()
                ->traveller($traveller, false);
        }

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('CURRENT BOOKING'))}]/ancestor::tr[1]/following-sibling::tr/descendant::td[./img/@src[{$this->contains($this->t('plane-blue.png'))}]]/ancestor::td[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root, true, "/^((?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s+\b\d{1,}\b/"))
                ->number($this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root, true, "/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(\b\d{1,}\b)\s+/"));

            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::tr[2]/descendant::td[normalize-space()][1]", $root, false, "/^([A-Z]{3})$/"));

            $depInfo = $this->http->FindSingleNode("./descendant::tr[./text()][3]/descendant::td[1]/descendant::td[1]/descendant::text()[normalize-space()][3]", $root);

            if (preg_match("/^(.+\b)\,\s+{$this->opt($this->t('Terminal'))}[ ]+([A-Z0-9]+)$/", $depInfo, $m)){
                $s->departure()
                    ->name($m[1])
                    ->terminal($m[2]);
            } else if ($depInfo !== null) {
                $s->departure()
                    ->name($depInfo);
            }

            $depDate = $this->http->FindSingleNode("./descendant::tr[./text()][3]/descendant::td[1]/descendant::td[1]/descendant::text()[normalize-space()][1]", $root, false, "/^(?:\w+[ ]+)?(\d{1,2}[ ]+\D+[ ]+\d{4})$/");
            $depTime = $this->http->FindSingleNode("./descendant::tr[./text()][3]/descendant::td[1]/descendant::td[1]/descendant::text()[normalize-space()][2]", $root, false, "/^(\d{1,2}\:\d{2})$/");

            if ($depDate !== null && $depTime !== null){
                $s->departure()
                    ->date(strtotime($depDate . ' ' . $depTime));
            }

            $arrInfo = $this->http->FindSingleNode("./descendant::tr[./text()][3]/descendant::td[1]/descendant::td[2]/descendant::text()[normalize-space()][3]", $root);

            if (preg_match("/^(.+\b)\,\s+{$this->opt($this->t('Terminal'))}[ ]+([A-Z0-9]+)$/", $arrInfo, $m)){
                $s->arrival()
                    ->name($m[1])
                    ->terminal($m[2]);
            } else if ($arrInfo !== null) {
                $s->arrival()
                    ->name($arrInfo);
            }

            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::tr[2]/descendant::td[normalize-space()][last()]", $root, false, "/^([A-Z]{3})$/"));

            $arrDate = $this->http->FindSingleNode("./descendant::tr[./text()][3]/descendant::td[1]/descendant::td[2]/descendant::text()[normalize-space()][1]", $root, false, "/^(?:\w+[ ]+)?(\d{1,2}[ ]+\D+[ ]+\d{4})$/");
            $arrTime = $this->http->FindSingleNode("./descendant::tr[./text()][3]/descendant::td[1]/descendant::td[2]/descendant::text()[normalize-space()][2]", $root, false, "/^(\d{1,2}\:\d{2})$/");

            if ($arrDate !== null && $arrTime !== null){
                $s->arrival()
                    ->date(strtotime($arrDate . ' ' . $arrTime));
            }

            $duration = $this->http->FindSingleNode("./descendant::tr[1]/descendant::td[normalize-space()][2]", $root, false, "/^{$this->opt($this->t('Duration'))}\:[ ]+(.+{$this->opt($this->t('min'))})$/");

            if ($duration !== null){
                $s->extra()
                    ->duration($duration);
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
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
                return str_replace(' ', '\s+', preg_quote($s, '/'));
            }, $field)) . ')';
    }
}
