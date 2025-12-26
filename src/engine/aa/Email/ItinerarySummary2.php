<?php

namespace AwardWallet\Engine\aa\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ItinerarySummary2 extends \TAccountChecker
{
    public $mailFiles = "aa/it-821061098.eml";
    public $subjects = [
        'AA.com Itinerary Summary On Hold',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@info.email.aa.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'aa.com')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('This reservation is on HOLD until'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Carrier'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Total Taxes and Carrier-Imposed Fees'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]info\.email\.aa\.com$/', $from) > 0;
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
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/^{$this->opt($this->t('Dear'))}\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\,$/"))
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Record Locator:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Record Locator:'))}\s*([A-Z\d]{6})/"));

        $earnedMiles = $this->http->FindSingleNode("//text()[normalize-space() ='Total Award Miles Required']/ancestor::tr[1]/descendant::td[2]", null, true, "/([\d\,]+\s+.+)/");

        if (!empty($earnedMiles)) {
            $f->setEarnedAwards($earnedMiles);
        }

        $year = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'This reservation is on HOLD until')]/following::text()[normalize-space()][1][contains(normalize-space(), 'EST')]", null, true, "/\,\s+(\d{4})/");

        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Carrier')]/ancestor::tr[1][contains(normalize-space(), 'Flight #')]/following-sibling::tr[contains(normalize-space(), ':')]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airName = $this->http->FindSingleNode("./descendant::td[1]", $root);

            if (preg_match("/(?<aName>.+)\s+OPERATED BY\s+(?<operator>.+)/", $airName, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->operator($m['operator']);
            } else {
                $s->airline()
                    ->name($airName);
            }

            $s->airline()
                ->number($this->http->FindSingleNode("./descendant::td[2]", $root, true, "/^(\d+)$/"));

            $depInfo = implode("\n", $this->http->FindNodes("./descendant::td[3]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<depName>.+)\n\((?<depCode>[A-Z]{3})\)\n(?<depDate>.+)\n(?<depTime>[\d\:]+\s*A?P?M)$/", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date(strtotime($m['depDate'] . ' ' . $year . ', ' . $m['depTime']));
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./descendant::td[4]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<arrName>.+)\n\((?<arrCode>[A-Z]{3})\)\n(?<arrDate>.+)\n(?<arrTime>[\d\:]+\s*A?P?M)$/", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->date(strtotime($m['arrDate'] . ' ' . $year . ', ' . $m['arrTime']));
            }

            $s->extra()
                ->cabin($this->http->FindSingleNode("./descendant::td[5]", $root, true, "/^(\w+)$/"))
                ->bookingCode($this->http->FindSingleNode("./descendant::td[6]", $root, true, "/^([A-Z])$/"))
                ->meal($this->http->FindSingleNode("./descendant::td[8]", $root), true, true);

            $seats = array_filter(explode(" ", $this->http->FindSingleNode("./descendant::td[7]", $root)));

            if (count($seats) > 0) {
                $s->extra()
                    ->seats($seats);
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
