<?php

namespace AwardWallet\Engine\tapportugal\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationCancelled extends \TAccountChecker
{
    public $mailFiles = "tapportugal/it-59916699.eml, tapportugal/it-73772013.eml";
    private $provider = 'TAP Air Portugal';

    private $subjects = "Cancellation";
    private $from = ["TAP Air Portugal Flight Info", "flytap.com"];
    private $body = ["Booking Reference", ["has been cancelled", "has been canceled"], "your flight"];
    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        if (stripos($from, $this->from[0]) !== false || stripos($from, $this->from[1]) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->from[0]) !== true && stripos($headers['from'], $this->from[1]) !== true) {
            return false;
        }

        return stripos($headers['subject'], $this->subjects);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($this->body as $reBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($reBody) . "]")->length === 0) {
                return false;
            }
        }

        return true;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $flight = $email->add()->flight();
        $confirmation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking Reference')]", null, true, '/\s([A-Z\d]{6})/');
        $flight->general()
            ->confirmation($confirmation, 'Booking Reference');

        $flightText = $this->http->FindSingleNode("//text()[" . $this->contains(['has been cancelled', 'has been canceled']) . "]");

        if (preg_match("/\b(?<name>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<number>\d{2,4})\s+(?<depCode>[A-Z]{3})\-(?<arrCode>[A-Z]{3})/", $flightText, $m)) {
            $segment = $flight->addSegment();

            $segment->extra()->cancelled();

            $segment->airline()
                ->number($m['number'])
                ->name($m['name']);

            $segment->departure()
                ->code($m['depCode'])
                ->noDate()
            ;
            $segment->arrival()
                ->code($m['arrCode'])
                ->noDate()
            ;
        } elseif (preg_match("/\b(?<name>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<number>\d{1,5})\s+(?<date>.+)\s*" . $this->preg_implode(['has been cancelled', 'has been canceled']) . "/", $flightText, $m)) {
            // your flight TP1548 26 Dec 20 has been canceled.

            $segment = $flight->addSegment();

            $segment->extra()->cancelled();

            $segment->airline()
                ->number($m['number'])
                ->name($m['name']);

            $segment->departure()
                ->noCode()
                ->noDate()
                ->date($this->normalizeDate($m['date']))
            ;
            $segment->arrival()
                ->noCode()
                ->noDate()
            ;
        }
        $email->setType('ReservationCancelled' . ucfirst($this->lang));

        return $email;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        $this->logger->debug('$date = ' . print_r($date, true));
        $in = [
            // 26 Dec 20
            "/^\s*(\d{1,2})\s+([[:alpha:]]+)\s+(\d{2})\s*$/iu",
        ];
        $out = [
            "$1 $2 20$3",
        ];
        $date = preg_replace($in, $out, $date);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $date = str_replace($m[1], $en, $date);
//        }

        return strtotime($date);
    }
}
