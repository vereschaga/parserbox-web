<?php

namespace AwardWallet\Engine\silversea\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PreCruiseFlight extends \TAccountChecker
{
    public $mailFiles = "silversea/it-662348980.eml";
    public $subjects = [
        'Your Clients Flight Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@silversea.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Thank You For Choosing Silversea'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('FLIGHT DETAILS'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('PRE-CRUISE FLIGHT #:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]silversea\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->Flight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email)
    {
        $f = $email->add()->flight();

        $travellers = explode("|", $this->http->FindSingleNode("//text()[normalize-space()='Guest Name(s):']/following::text()[normalize-space()][1]"));
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Booking:']/following::text()[normalize-space()][1]", null, true, "/^([\d\-]+)$/"))
            ->travellers(preg_replace("/(?:Mrs\s|Ms\s|Mr\s)/", "", $travellers));

        $nodes = $this->http->XPath->query("//text()[normalize-space()='FLIGHT DETAILS']/following::table[1]/descendant::tr[normalize-space()]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineInfo = implode("\n", $this->http->FindNodes("./td[2]/descendant::text()[normalize-space()]", $root));
            $depDate = $arrDate = '';

            if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})\s.*\n(?<depDate>[\d\-]+)\n(?<arrDate>[\d\-]+)$/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $depDate = $m['depDate'];
                $arrDate = $m['arrDate'];
            }

            $timeInfo = implode("\n", $this->http->FindNodes("./td[3]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^CLASS\:\s*(?<cabin>.+)\n(?<depTime>[\d\:]+\s*A?P?M)\n(?<arrTime>[\d\:]+\s*A?P?M)$/", $timeInfo, $m)) {
                $s->extra()
                    ->cabin($m['cabin']);

                $s->departure()
                    ->date(strtotime($depDate . ', ' . $m['depTime']));

                $s->arrival()
                    ->date(strtotime($arrDate . ', ' . $m['arrTime']));
            }

            $depArrInfo = implode("\n", $this->http->FindNodes("./td[4]/descendant::text()[normalize-space()]", $root));

            $segConf = $this->re("/^{$this->opt($this->t('Flight Booking Locator #:'))}\s*([A-Z\d]{6})\n/m", $depArrInfo);

            if (!empty($segConf)) {
                $s->setConfirmation($segConf);
            }

            if (preg_match("/\n(?<depCode>[A-Z]{3})\s+(?<depName>.+)\n/", $depArrInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode']);
            } else {
                $s->departure()
                    ->name($this->re("/\n(.+)\n/", $depArrInfo))
                    ->noCode();
            }

            if (preg_match("/\n(?<arrCode>[A-Z]{3})\s+(?<arrName>.+)$/", $depArrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode']);
            } else {
                $s->arrival()
                    ->name($this->re("/\n(.+)$/", $depArrInfo))
                    ->noCode();
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
