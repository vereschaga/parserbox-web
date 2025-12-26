<?php

namespace AwardWallet\Engine\egyptair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightUpgraded extends \TAccountChecker
{
    public $mailFiles = "egyptair/it-779245180.eml";
    public $subjects = [
        "- You've been upgraded!",
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@egyptair.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'EGYPTAIR')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Summary of your upgrade'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Welcome onboard!'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]egyptair\.com$/', $from) > 0;
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
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Confirmation Number:']/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Confirmation Number:'))}\s*([A-Z\d]{6})$/"));

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Amount paid']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Amount paid'))}\s*(.+)/");

        if (
            preg_match("/^(?:\D{1,2})?(?<total>[\d\.\,\']+)\s*(?<currency>\D{1,3})$/u", $price, $m)
            || preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,\']+)$/u", $price, $m)
        ) {
            $f->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['total'], $m['currency']));
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Offer']/ancestor::tr[1]/ancestor::tbody[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $airlineInfo = $this->http->FindSingleNode("./descendant::tr[normalize-space()][1]", $root);

            if (preg_match("/(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $depDay = $this->http->FindSingleNode("./descendant::tr[normalize-space()][2]", $root, true, "/^(\w+\s*\d+\s*\w+\s*\d{4})$/");

            $s->departure()
                ->name($this->http->FindSingleNode("./descendant::tr[normalize-space()][3]/descendant::tr[1]/descendant::td[1]", $root))
                ->code($this->http->FindSingleNode("./descendant::tr[normalize-space()][5]/descendant::tr[1]/descendant::td[1]", $root))
                ->day(strtotime($depDay))
                ->noDate();

            $s->arrival()
                ->name($this->http->FindSingleNode("./descendant::tr[normalize-space()][3]/descendant::tr[1]/descendant::td[2]", $root))
                ->code($this->http->FindSingleNode("./descendant::tr[normalize-space()][5]/descendant::tr[1]/descendant::td[last()]", $root))
                ->noDate();
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
