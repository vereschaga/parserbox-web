<?php

namespace AwardWallet\Engine\avelo\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Reminder extends \TAccountChecker
{
    public $mailFiles = "avelo/it-775186090.eml";
    public $subjects = [
        'Check-in Reminder',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    private $date;

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mail.aveloair.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Avelo Airlines')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Travel Tips'))}]")->length > 0
            && $this->http->XPath->query("//img[contains(@src, 'OnlineCheckIn')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Flight #'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mail\.aveloair\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Conf. #')]/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Conf. #'))}\s*([A-Z\d]{6})$/"))
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hi')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Hi'))}\s*(\D+)\,/"));

        $date = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Flight #')]/ancestor::tr[1]/following::tr[1]");

        $fNumber = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Flight #')]/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Flight #'))}\s*(\d{1,4})$/");
        $aName = 'XP';

        $s = $f->addSegment();

        $s->airline()
            ->name($aName)
            ->number($fNumber);

        $depInfo = implode("\n", $this->http->FindNodes("//img[contains(@src, 'YellowArrowRight')]/ancestor::tr[3]/descendant::td[normalize-space()][1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^(?<depTime>\d+\:\d+\s*A?P?M)\n(?<depName>.+)\s+\((?<depCode>[A-Z]{3})\)/", $depInfo, $m)) {
            $s->departure()
                ->date($this->normalizeDate($date . ', ' . $m['depTime']))
                ->code($m['depCode'])
                ->name($m['depName']);
        }

        $arrInfo = implode("\n", $this->http->FindNodes("//img[contains(@src, 'YellowArrowRight')]/ancestor::tr[3]/descendant::td[normalize-space()][2]/descendant::text()[normalize-space()]"));

        if (preg_match("/^(?<arrTime>\d+\:\d+\s*A?P?M)\n(?<arrName>.+)\s+\((?<arrCode>[A-Z]{3})\)/", $arrInfo, $m)) {
            $s->arrival()
                ->date($this->normalizeDate($date . ', ' . $m['arrTime']))
                ->code($m['arrCode'])
                ->name($m['arrName']);
        }

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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);

        $in = [
            "#^(\w+)\,\s+(\w+)\s+(\d+)\,\s+(\d+\:\d+\s*A?P?M)$#u", //Sun, Oct 27, 05:55 PM
        ];
        $out = [
            "$1, $3 $2 $year $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^(?<week>[\w\.]+), (?<date>\d+ \w+ .+|\d+-\d+-.+)#u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }
}
