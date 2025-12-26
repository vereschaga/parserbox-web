<?php

namespace AwardWallet\Engine\oojo\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightConfirmation extends \TAccountChecker
{
	public $mailFiles = "oojo/it-807146626.eml, oojo/it-821812115.eml, oojo/it-824507481.eml";
    public $subjects = [
        'OOJO: Your Travel Booking Confirmation',
        'Thank you for booking with Oojo. Almost there!'
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'detectPhrase' => ['Thank you for choosing Oojo!'],
            'Your OOJO Confirmation number' => 'Your OOJO Confirmation number',
            "Depart" => "Depart",
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'oojo.com') !== false) {
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
        if (
            $this->http->XPath->query("//a/@href[{$this->contains(['oojo.com'])}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Oojo International B.V.'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['detectPhrase']) && $this->http->XPath->query("//*[{$this->contains($dict['detectPhrase'])}]")->length > 0
                && !empty($dict['Your OOJO Confirmation number']) && $this->http->XPath->query("//*[{$this->contains($dict['Your OOJO Confirmation number'])}]")->length > 0
                && !empty($dict['Depart']) && $this->http->XPath->query("//*[{$this->contains($dict['Depart'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]oojo\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->Flight($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your OOJO Confirmation number'))}]/following::td[1]", null, true, "/^[A-Z\d]{5,7}$/"), $this->t('OOJO Confirmation number'));

        $segmentNodes = $this->http->XPath->query("//td[{$this->eq($this->t('Depart'))} or {$this->eq($this->t('Return'))}]/ancestor::table[1]/descendant::td[2]");

        foreach ($segmentNodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->noName()
                ->noNumber();

            $year = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Oojo International B.V.'))}]", $root, false, "/^\©\s*(\d{4})\s*Oojo\s*International\s*B\.V\.\,$/");

            $departureDate = $this->http->FindSingleNode("./descendant::tr[1]/descendant::td[1]", $root);

            if (preg_match("/^(?<time>\d{1,2}\:\d{2}\s*[Aa]?[Pp]?[Mm]?)\s+(?<date>\w+\,\s*\w+\s*\d+)$/", $departureDate, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m['date'] . ' '  . $year . ' ' . $m['time']));
            }

            $departureCode = $this->http->FindSingleNode("./descendant::tr[1]/descendant::td[2]", $root);

            if (preg_match("/^(?<code>[A-Z]{3})\s*\,\s*\D+$/", $departureCode, $m)) {
                $s->departure()
                    ->code($m['code']);
            } else if (preg_match("/^(?<name>.+)\s*\b(?<code>[A-Z]{3})\s*$/", $departureCode, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code']);
            }

            $arrivalDate = $this->http->FindSingleNode("./descendant::tr[2]/descendant::td[1]", $root);

            if (preg_match("/^(?<time>\d{1,2}\:\d{2}\s*[Aa]?[Pp]?[Mm]?)\s+(?<date>\w+\,\s*\w+\s*\d+)$/", $arrivalDate, $m)) {
                $s->arrival()
                    ->date($this->normalizeDate($m['date'] . ' ' . $year . ' ' . $m['time']));
            }

            $arrivalCode = $this->http->FindSingleNode("./descendant::tr[2]/descendant::td[2]", $root);
            if (preg_match("/^(?<code>[A-Z]{3})\s*\,\s*\D{2}$/", $arrivalCode, $m)) {
                $s->arrival()
                    ->code($m['code']);
            } else if (preg_match("/^(?<name>.+)\s*\b(?<code>[A-Z]{3})\s*$/", $arrivalCode, $m)) {

                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code']);
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

    private function normalizeDate($date)
    {
        if (preg_match("/^(?<weekDay>\w+)\,\s*(?<month>\w+)\s*(?<date>\d+)\s*(?<year>\d{4})\s*(?<time>\d{1,2}\:\d{2}\s*[Aa]?[Pp]?[Mm]?)$/u", $date, $x)) {
            $dayOfWeekInt = WeekTranslate::number1(WeekTranslate::translate($x['weekDay'], $this->lang));

            if ($en = MonthTranslate::translate($x['month'], $this->lang)){
                $date = EmailDateHelper::parseDateUsingWeekDay($x['time'] . ' ' . $x['date'] . ' ' . $en . ' ' . $x['year'], $dayOfWeekInt);
            } else {
                $date = EmailDateHelper::parseDateUsingWeekDay($x['time'] . ' ' . $x['date'] . ' ' . $x['month'] . ' ' . $x['year'], $dayOfWeekInt);
            }
        }

        return $date;
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
