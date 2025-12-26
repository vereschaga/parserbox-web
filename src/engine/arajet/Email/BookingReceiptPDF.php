<?php

namespace AwardWallet\Engine\arajet\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingReceiptPDF extends \TAccountChecker
{
    public $mailFiles = "arajet/it-845235560.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public $subjects = [
        'Booking Receipt #',
    ];

    public static $dictionary = [
        "en" => [
            'Flight or Bus number' => ['Flight or Bus number', 'Flight no.'],
            'feesName'             => ['Optional Charges', 'Penalty fee'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@arajet.com') !== false) {
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
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'www.arajet.com') === false) {
                return false;
            }

            if (strpos($text, "Booking Number:") !== false
                && (strpos($text, 'Receipt and Itinerary created') !== false)
                && (strpos($text, 'Duration') !== false)
                && (strpos($text, 'Details') !== false)
                && (strpos($text, 'Departure') !== false)
                && (strpos($text, 'Departure Time:') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]arajet\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/Booking Number[\s\:]+([A-Z\d]+)\s/", $text))
            ->date(strtotime($this->re("#{$this->opt($this->t('Receipt and Itinerary created'))}.*\:\s\w+[\s\-]+(\d+\s*\w+\s*\d{4}\s+[\d\:]+\s*A?P?M)\n#", $text)));

        $flightsText = $this->splitText($text, "/^(.*{$this->opt($this->t('Flight or Bus number'))}.*)/m");

        $travellers = [];

        foreach ($flightsText as $flightText) {
            $s = $f->addSegment();

            $durationHrs = $this->re("/\s(?<hrs>\d+[\/\s]+Hours)/", $flightText);
            $durationMin = $this->re("/\s(?<min>\d+\s+\/\s+Minutes)/", $flightText);

            if (!empty($durationHrs) || !empty($durationMin)) {
                $s->extra()
                    ->duration(str_replace(['/', '  '], ['', ' '], $durationHrs . ' ' . $durationMin));
            }

            if (preg_match("/^\s*(?<aName>([A-Z][A-Z\d]|[A-Z\d][A-Z]))[\-\s]+(?<fNumber>[\d\s]{2,})[ ]{5,}/mu", $flightText, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number(preg_replace("/\s/", "", $m['fNumber']));
            }

            if (preg_match("/Departure\n.+\-\s+(?<depName>.+)\n.*Departure Time:\s+\w+[\s\-]+(?<depDate>\d+\s*\w+\s*\d{4}\s*[\d\:]+\s*A?P?M)/", $flightText, $m)) {
                $s->departure()
                    ->noCode()
                    ->name($m['depName'])
                    ->date(strtotime($m['depDate']));
            }

            if (preg_match("/Arrival\n.+\-\s+(?<arrName>.+)\n.*Arrival Time:\s+\w+[\s\-]+(?<arrDate>\d+\s*\w+\s*\d{4}\s*[\d\:]+\s*A?P?M)/", $flightText, $m)) {
                $s->arrival()
                    ->noCode()
                    ->name($m['arrName'])
                    ->date(strtotime($m['arrDate']));
            }

            if (preg_match_all("/^\s*(?<pax>.+)\n\s*Seat.*\:\s+(?<seat>(?:\d+[A-Z]|No asignado))/m", $flightText, $m)) {
                foreach ($m['pax'] as $key => $paxInfo) {
                    $travellers[] = $this->nicePax($paxInfo);

                    if (stripos($m['seat'][$key], 'No asignado') === false) {
                        $s->extra()
                            ->seat($m['seat'][$key], true, true, $this->nicePax($paxInfo));
                    }
                }
            }
        }

        $f->general()
            ->travellers(array_filter(array_unique($travellers)));

        $priceText = $this->re("/Reservation Totals\n+(.+)/su", $text);

        if (preg_match("/Total:\s+(?<total>[\d\.\,\']+)\s*(?<currency>[A-Z]{3})/", $priceText, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total']), $m['currency'])
                ->currency($m['currency']);

            $tax = $this->re("/Taxes and fees:\s+([\d\.\,\']+)\s+/", $text);

            if ($tax !== null) {
                $f->price()
                    ->tax(PriceHelper::parse($tax), $m['currency']);
            }

            $cost = $this->re("/Airfare:\s+([\d\.\,\']+)\s+/", $text);

            if ($cost !== null) {
                $f->price()
                    ->cost(PriceHelper::parse($cost), $m['currency']);
            }

            foreach ($this->t('feesName') as $feeName) {
                $feeSumm = $this->re("/{$feeName}\:\s+([\d\.\,\']+)\s+/", $text);

                if ($feeSumm !== null) {
                    $f->price()
                        ->fee($feeName, PriceHelper::parse($feeSumm, $m['currency']));
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseFlightPDF($email, $text);
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

    private function nicePax($pax)
    {
        return preg_replace("/\s+/", " ", $pax);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }
}
