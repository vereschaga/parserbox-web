<?php

namespace AwardWallet\Engine\korean\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ScheduleChange extends \TAccountChecker
{
    public $mailFiles = "korean/it-638892505.eml";
    public $subjects = [
        'Schedule Change Notice',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@koreanair.com') !== false) {
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
        return $this->http->XPath->query("//a[contains(@href, 'koreanair.com')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Schedule Change'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('My Trip'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]koreanair.com$/', $from) > 0;
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

        $confText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking Reference')]/ancestor::tr[1]");

        if (preg_match("/Booking Reference[\s\:]+(?<otaConf>[\d\-]+)\s+\((?<conf>[A-Z\d]{6})\)/", $confText, $m)
        || preg_match("/^Booking Reference[\s\:]+(?<conf>[A-Z\d]{6})$/", $confText, $m)) {
            if (isset($m['otaConf']) && !empty($m['otaConf'])) {
                $email->ota()
                    ->confirmation($m['otaConf']);
            }

            $f->general()
                ->confirmation($m['conf']);
        }

        $nodes = $this->http->XPath->query("//tr[starts-with(normalize-space(), 'Original') and contains(normalize-space(), 'Changed')]/following::table[not(contains(normalize-space(), 'Flight Canceled'))][2]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airInfo = implode("\n", $this->http->FindNodes("./descendant::img[contains(@src, 'flight')]/ancestor::tr[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})\n(?<aircraft>.+)\n(?<cabin>.+)$/", $airInfo, $m)
            || preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})\nOperated by\n(?<operator>.+)\n(?<cabin>.+)$/", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                if (isset($m['operator']) && !empty($m['operator'])) {
                    $s->airline()
                        ->operator($m['operator']);
                }

                if (isset($m['aircraft']) && !empty($m['aircraft'])) {
                    $s->extra()
                        ->aircraft($m['aircraft']);
                }

                $s->extra()
                    ->cabin($m['cabin']);
            }

            $depInfo = implode("\n", $this->http->FindNodes("./descendant::img[contains(@src, 'flight')]/preceding::tr[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<depCode>[A-Z]{3})\n(?<depName>.+)\n(?<year>\d{4})\.(?<month>\d{1,2})\.(?<day>\d{1,2})\s*\(\D*\)\s*(?<time>[\d\:]+)$/", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date(strtotime($m['day'] . '.' . $m['month'] . '.' . $m['year'] . ', ' . $m['time']));
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./descendant::img[contains(@src, 'flight')]/following::tr[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<arrCode>[A-Z]{3})\n(?<arrName>.+)\n(?<year>\d{4})\.(?<month>\d{1,2})\.(?<day>\d{1,2})\s*\(\D*\)\s*(?<time>[\d\:]+)$/", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->date(strtotime($m['day'] . '.' . $m['month'] . '.' . $m['year'] . ', ' . $m['time']));
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
}
