<?php

namespace AwardWallet\Engine\oojo\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
	public $mailFiles = "oojo/it-826722873.eml, oojo/it-828708769.eml";
    public $subjects = [
        'OOJO: Your Travel Booking Confirmation',
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
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your OOJO Confirmation number'))}]", null, true, "/^{$this->t('Your OOJO Confirmation number')}\s*\:\s*([A-Z\d]{5,7})$/"), $this->t('OOJO Confirmation number'));

        $segmentNodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Depart'))} or {$this->contains($this->t('Return'))}]/ancestor::tr[2]/following-sibling::tr[1]/descendant::td[2]/descendant::div[not(./text())]/following-sibling::*");
        
        foreach ($segmentNodes as $root) {
            $s = $f->addSegment();

            $airName = $this->http->FindSingleNode("./descendant::a[{$this->contains($this->t("Airline's full baggage policy"))}]/@href", $root, false, '/^https\:\/\/viewtrip\.travelport\.com\/BAGGAGEPOLICY\/([A-Z\d]{2})$/');

            if ($airName !== null) {
                $s->airline()
                    ->name($airName);
            } else {
                $s->airline()
                    ->noName();
            }

            $s->airline()
                ->noNumber();

            $flightCodes = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $root);

            if (preg_match("/^(?<depCode>[A-Z]{3})\b\s*.+\s*\-\s*(?<arrCode>[A-Z]{3})\b\s*.+$/", $flightCodes, $m)){
                $s->departure()
                    ->code($m['depCode']);

                $s->arrival()
                    ->code($m['arrCode']);

                $year = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Oojo International B.V.'))}]", $root, false, "/^\©\s*(\d{4})\s*Oojo\s*International\s*B\.V\.\,$/");

                $departureDate = $this->http->FindSingleNode("./preceding-sibling::*[normalize-space()][last()]/descendant::td[normalize-space()][1]", $root);

                if ($year !== null && preg_match("/^(?<time>\d{1,2}\:\d{2}\s*[Aa]?[Pp]?[Mm]?)\s+(?<date>\w+\,\s*\w+\s*\d+)\s*{$m['depCode']}.+$/", $departureDate, $d)){
                    $s->departure()
                        ->date($this->normalizeDate($d['date'] . ' ' . $year . ' ' . $d['time']));
                } else {
                    $s->departure()
                        ->noDate();
                }

                $arrivalDate = $this->http->FindSingleNode("./preceding-sibling::*[normalize-space()][last()]/descendant::td[normalize-space()][2]", $root);

                if ($year !== null && preg_match("/^(?<time>\d{1,2}\:\d{2}\s*[Aa]?[Pp]?[Mm]?)\s+(?<date>\w+\,\s*\w+\s*\d+)\s*{$m['arrCode']}.+$/", $arrivalDate, $d)){
                    $s->arrival()
                        ->date($this->normalizeDate($d['date'] . ' ' . $year . ' ' . $d['time']));
                } else {
                    $s->arrival()
                        ->noDate();
                }
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
