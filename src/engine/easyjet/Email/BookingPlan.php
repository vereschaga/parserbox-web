<?php

namespace AwardWallet\Engine\easyjet\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingPlan extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-695503869.eml";
    public $subjects = [
        '/^Your booking\s+[A-Z\d]+\:\s+.*is just days away$/',
    ];

    public $lang = 'en';
    public $conf = '';
    public $date;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@info.easyjet.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'easyJet Airline')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('PLAN YOUR ARRIVAL'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your flight departs at'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]info\.easyjet\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (preg_match("/^Your booking\s+([A-Z\d]+)\:/", $parser->getSubject(), $m)) {
            $this->conf = $m[1];
        }

        $this->date = strtotime($parser->getDate());

        $this->parseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->conf);

        $segText = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Depart:']/ancestor::tr[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^Depart\:\n*(?<depName>.+\)?)\s+(?<depDate>[[:alpha:]]+\s*\d+\s*[[:alpha:]]+)\s+(?<depTime>[\d\:]+)\n*Flight time\:\n*(?<duration>.+)\n*Arrive:\n*(?<arrName>.+\)?)\s+(?<arrDate>[[:alpha:]]+\s*\d+\s*[[:alpha:]]+)\s+(?<arrTime>[\d\:]+)/", $segText, $m)) {
            $s = $f->addSegment();

            $s->airline()
                ->name('U2')
                ->noNumber();

            $s->departure()
                ->date($this->normalizeDate($m['depDate'] . ', ' . $m['depTime']))
                ->name($m['depName'])
                ->noCode();

            $s->arrival()
                ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime']))
                ->name($m['arrName'])
                ->noCode();

            $s->extra()
                ->duration($m['duration']);
        }
        $this->logger->debug($segText);
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
            // Thu 13 Feb, 20:10
            "/^\s*([[:alpha:]]+)\s+(\d{1,2})\s*([[:alpha:]]+)\s*,\s*(\d{1,2}:\d{2}(?: *[ap]m)?)\s*$/u",
        ];
        $out = [
            "$1, $2 $3 $year, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }
}
