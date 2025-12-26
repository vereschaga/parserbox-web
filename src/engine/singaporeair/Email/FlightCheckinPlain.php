<?php

namespace AwardWallet\Engine\singaporeair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightCheckinPlain extends \TAccountChecker
{
    public $mailFiles = "singaporeair/it-10873498.eml, singaporeair/it-35429034.eml, singaporeair/it-8624073.eml, singaporeair/it-8697813.eml";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    private $reSubject = [
        "en" => "Flight Check-in", "Check-in & Travel Reminder",
    ];

    private $reBody = 'singaporeair.com';

    private $reBody2 = [
        "en"  => "You are booked on",
        "en2" => "We look forward to welcoming you on board",
    ];

    private $text;

    private $year;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@singaporeair.com.sg') !== false
            || stripos($from, '@flightinfo.singaporeair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->year = date('Y', strtotime($parser->getDate()));

        $this->http->FilterHTML = false;
        $this->text = $parser->getHtmlBody();

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $email->setType('FlightCheckinPlain' . ucfirst($this->lang));
        $this->parsePlain($email);

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

    private function parsePlain(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $text = strip_tags(preg_replace("#<br(\s*/\s*|\s+[^>]*)?>#", "\n", $this->text));

        $f = $email->add()->flight();

        // RecordLocator
        $f->general()
            ->confirmation($this->re("#(?:Booking\s*Reference\s*Number:|Booking reference|Booking Reference)\s+([A-Z\d]{5,7})\b#", $text));

        // TripNumber
        // Passengers
        $traveller = $this->re("/^[> ]*Dear[ ]+({$patterns['travellerName']})[ ]*,$/mu", $text)
            ?? $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Dear ')][1]", null, true, "/^[> ]*Dear[ ]+({$patterns['travellerName']})[ ]*,/u")
        ;

        if (!empty($traveller) && !preg_match("/^{$this->opt(['Sir/Madam', 'Sir', 'Madam'])}$/", $traveller)) {
            $f->addTraveller($traveller);
        }

        $s = $f->addSegment();

        /*
            You are booked on SQ917 departing on 18 JAN at 14:05, from Manila (MNL) to Singapore (SIN).
        */
        $regexp1 = "/You are booked on (?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<FlightNumber>\d+) departing on (?<Date>.*?) at (?<Time>{$patterns['time']})[ ]*,[ ]*from (?<DepName>.*?) \([ ]*(?<DepCode>[A-Z]{3})[ ]*\) to (?<ArrName>.*?) \([ ]*(?<ArrCode>[A-Z]{3})[ ]*\)[ ]*\./";

        /*
            We look forward to welcoming you on board SQ709 departing on 04 Apr 2022 from Bangkok (BKK) to Singapore (SIN).
        */
        $rexexp2 = "/\b(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<FlightNumber>\d+) departing on (?<Date>\d{1,2} [[:alpha:]]+(?: \d{4})?) from (?<DepName>.+)[ ]+\([ ]*(?<DepCode>[A-Z]{3})[ ]*\) to (?<ArrName>.+) \([ ]*(?<ArrCode>[A-Z]{3})[ ]*\)/iu";

        $textHtml = $this->http->FindSingleNode("//text()[contains(normalize-space(),'departing on')]");

        if (preg_match($regexp1, $text, $m) || preg_match($rexexp2, $text, $m)
            || preg_match($regexp1, $textHtml, $m) || preg_match($rexexp2, $textHtml, $m)
        ) {
            $s->airline()
                ->name($m['AirlineName'])
                ->number($m['FlightNumber']);
            $s->departure()
                ->name($m['DepName'])
                ->code($m['DepCode']);

            $date = strtotime($this->normalizeDate($m['Date']));

            if (!empty($m['Time'])) {
                $s->departure()->date(strtotime($m['Time'], $date));
            } else {
                $s->departure()
                    ->noDate()
                    ->day($date);
            }
            $s->arrival()
                ->name($m['ArrName'])
                ->noDate()
                ->code($m['ArrCode']);
        }
    }

    private function normalizeDate(string $str): string
    {
        $in = [
            "/^(\d{1,2}) ([[:alpha:]]+)$/u", // 22 SEP
        ];
        $out = [
            "$1 $2 {$this->year}",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
