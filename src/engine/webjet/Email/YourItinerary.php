<?php

namespace AwardWallet\Engine\webjet\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourItinerary extends \TAccountChecker
{
    public $mailFiles = "webjet/it-421285708.eml, webjet/it-422386014.eml";
    public $subjects = [
        'URGENT: Changes to your itinerary',
    ];

    public $lang = '';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@webjet.com.au') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Webjet Customer Service')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Webjet Reference:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your current itinerary can always be found at'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]webjet\.com\.au$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlight($email);

        $otaConf = $this->http->FindSingleNode("//text()[normalize-space()='Webjet Reference:']/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf, 'Webjet Reference');
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='Webjet Reference:']/following::text()[normalize-space()][3]", null, true, "/^([A-Z\d]{6})$/");
        $confDesc = $this->http->FindSingleNode("//text()[normalize-space()='Webjet Reference:']/following::text()[normalize-space()][2]");

        $travellers = $this->http->FindNodes("//text()[normalize-space()='This change affects the following passenger(s):']/following::text()[normalize-space()][1]/ancestor::*[1]/descendant::text()[normalize-space()]", null, "/^\d+\.\s*(\D+)/");

        $f->general()
            ->confirmation($confirmation, trim($confDesc, ':'))
            ->travellers(preg_replace("/(?:MRS|MR|MS)/", "", $travellers), true);

        $xpath = "//text()[starts-with(normalize-space(), 'FLIGHT ')]/ancestor::td[1][not(contains(@style, 'line-through'))][not(contains(normalize-space(), 'CHANGE'))]/following::text()[starts-with(normalize-space(), 'From:')][1]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./preceding::tr[1]", $root, true, "/((?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))/"))
                ->number($this->http->FindSingleNode("./preceding::tr[1]", $root, true, "/(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{2,4})/"));

            $operator = $this->http->FindSingleNode("./preceding::tr[1]/descendant::text()[contains(normalize-space(), 'operated by')]", $root, true, "/{$this->opt($this->t('operated by'))}\s*(.+)\)/");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $depDate = $this->http->FindSingleNode("./following::tr[2]/descendant::td[2]", $root);
            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::td[2]", $root, true, "/^([A-Z]{3})/"))
                ->date(strtotime($depDate));

            if ($this->http->XPath->query("./following::tr[3]/descendant::td[2]/descendant::s[1]", $root)->length == 0) {
                $arrDate = $this->http->FindSingleNode("./following::tr[3]/descendant::td[2]", $root);
            } else {
                $arrDate = $this->http->FindSingleNode("./following::tr[3][contains(normalize-space(), 'Arriving:')]/following::tr[1]/descendant::td[2][contains(normalize-space(), '(')]", $root, true, "/^(.+)\s*\(/");
            }

            $s->arrival()
                ->code($this->http->FindSingleNode("./following::tr[1]/descendant::td[2]", $root, true, "/^([A-Z]{3})/"))
                ->date(strtotime($arrDate));
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
}
