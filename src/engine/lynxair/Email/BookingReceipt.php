<?php

namespace AwardWallet\Engine\lynxair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingReceipt extends \TAccountChecker
{
    public $mailFiles = "lynxair/it-639541244.eml, lynxair/it-640195519.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";
    public $subjects = [
        'Booking Receipt Email #',
    ];

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@lynxair.com') !== false) {
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

            if (strpos($text, "Thank you for choosing Lynx Air") !== false
                && strpos($text, 'Lynx Air and the Canadian Transportation Agency') !== false
                && (strpos($text, 'FLIGHT DETAILS') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]lynxair\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text): void
    {
        $f = $email->add()->flight();

        $bookingDate = strtotime($this->re("/Booking Date\n\s*(.+\d{4})/", $text));

        if ($bookingDate) {
            $f->general()->date($bookingDate);
        }

        $conf = $this->re("/Booking Reference(?:.*\n){2}\D+[ ]{2,}([A-Z\d]{6})/u", $text);
        $f->general()
            ->confirmation($conf);

        $travellerText = $this->re("/(PASSENGERS\s*FARE TYPE\s*TAXES AND FEES\s*TOTAL.+)^\s*ADD-ONS/msu", $text);
        $travellerTable = $this->splitCols($travellerText);

        if (preg_match_all("/(.+)\n(?:NETTO|Adult|Child|Infant)/", $travellerTable[0], $m)) {
            $travellers = array_filter(array_unique(preg_replace("/([ ]{3,}.*)$/", "", $m[1])));
            $travellers = preg_replace("/^(?:MRS|MR|MS|MISS)/", "", $travellers);
            $f->general()
                ->travellers($travellers);
        }

        if (preg_match("/Total\:\s*(?<currency>[A-Z]{3})\s*(?<total>[\d\,\.]+)/", $text, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->re("/Airfare:\s*[A-Z]{3}\s*([\d\.\,]+)/", $text);

            if (!empty($cost)) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $tax = $this->re("/Tax\D*\:\s*[A-Z]{3}\s*([\d\.\,]+)/", $text);

            if (!empty($tax)) {
                $f->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }

            if (preg_match("/(?<feeName>Fees\D*)\:\s*[A-Z]{3}\s*(?<feeSumm>[\d\.\,]+)/", $text, $m)) {
                $f->price()
                    ->fee($m['feeName'], $m['feeSumm']);
            }

            if (preg_match("/(?<feeName>Add-Ons)\:\s*[A-Z]{3}\s*(?<feeSumm>[\d\.\,]+)/", $text, $m)) {
                $f->price()
                    ->fee($m['feeName'], $m['feeSumm']);
            }
        }

        $segmentText = $this->re("/([ ]{1,10}Outbound.+)IMPORTANT TRAVEL INFO/su", $text);
        $segments = $this->splitText($segmentText, "/([ ]{1,10}(?:Outbound|Inbound).*)/", true);

        if (count($segments) > 0) {
            foreach ($segments as $segment) {
                $flightText = $this->re("/(FLIGHT NO.+)\n\nPASSENGERS.+ADD-ONS/su", $segment);
                //it-640195519.eml
                $childSegments = array_filter($this->splitText($flightText, "/([ ]{0,10}[A-Z\d]{2}\s*\-\s*\d{1,4}.*)/", true));

                foreach ($childSegments as $childSegment) {
                    $s = $f->addSegment();
                    $rowTime = $this->re("/\n([ ]+\d+\:\d+\sa?p?m.+)/", $childSegment);
                    $pos = [0];

                    if (preg_match("/^(\s+)\d+\:\d+/", $rowTime, $m)) {
                        $pos[] = strlen($m[1]);
                    }

                    if (preg_match("/^(\s+\d+\:\d+.+[ ]{1,})\d+\:\d+/", $rowTime, $m)) {
                        $pos[] = strlen($m[1]);
                    }

                    if (preg_match("/^(\s+\d+\:\d+.+[ ]{1,}\d+\:\d+.*\s\d{4})/", $rowTime, $m)) {
                        $pos[] = strlen($m[1]) + 5;
                    }

                    if (count($pos) !== 4) {
                        $this->logger->debug('Column positions not found!!!');

                        return;
                    }

                    $flightTable = $this->splitCols($childSegment, $pos);

                    $this->setAirlineInfo($s, $flightTable[0]);
                    $this->setDepInfo($s, $flightTable[1]);
                    $this->setArrInfo($s, $flightTable[2]);
                    $this->setDuration($s, $flightTable[3]);

                    $extraInfo = $this->re("/([ ]{1,10}ADD-ONS.*)/s", $segment);

                    if (preg_match_all("/(?:Adult|Child|Infant).*\n\((?<cabin>\w+)\)/u", $extraInfo, $m)) {
                        $s->extra()
                            ->cabin(implode(',', array_filter(array_unique($m['cabin']))));
                    }

                    if (preg_match_all("/\s+(?<seats>\d{1,2}[A-Z])(?:\s*.*\n)(?:Adult|Child|Infant)/u", $extraInfo, $m)) {
                        $s->extra()
                            ->seats(array_filter(array_unique($m['seats'])));
                    }
                }
            }
        } elseif ($this->http->XPath->query("//text()[normalize-space()='FLIGHT NO']/ancestor::tr[1]")->length > 0) { //collection segments info from HTML
            $nodes = $this->http->XPath->query("//text()[normalize-space()='FLIGHT NO']/ancestor::tr[1]");

            foreach ($nodes as $root) {
                $s = $f->addSegment();

                $airlineInfo = $this->http->FindSingleNode("./following::tr[1]/descendant::td[1]", $root);
                $this->setAirlineInfo($s, $airlineInfo);

                $depInfo = implode("\n", $this->http->FindNodes("./following::tr[1]/descendant::td[2]/descendant::text()[normalize-space()]", $root));
                $this->setDepInfo($s, $depInfo);

                $arrInfo = implode("\n", $this->http->FindNodes("./following::tr[1]/descendant::td[3]/descendant::text()[normalize-space()]", $root));
                $this->setArrInfo($s, $arrInfo);

                $durationInfo = implode("\n", $this->http->FindNodes("./following::tr[1]/descendant::td[4]/descendant::text()[normalize-space()]", $root));
                $this->setDuration($s, $durationInfo);
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

    private function setAirlineInfo(FlightSegment $s, $text)
    {
        if (preg_match("/(?<aName>[A-Z\d]{2})\s*\-\s*(?<fNumber>\d{1,4})/", $text, $m)) {
            $s->airline()
                ->name($m['aName'])
                ->number($m['fNumber']);
        }
    }

    private function setDepInfo(FlightSegment $s, $text)
    {
        if (preg_match("/(?:DEPARTING\n*)?\s*(?<depName>.+)\s+(?<depTime>\d+\:\d+)\s*a?p?m\,?\s*(?<depDate>\d+.*\d{4})/s", $text, $m)) {
            $s->departure()
                ->noCode()
                ->name(preg_replace("/(\n\s*)/", " ", $m['depName']))
                ->date(strtotime($m['depDate'] . ', ' . $m['depTime']));
        }
    }

    private function setArrInfo(FlightSegment $s, $text)
    {
        $this->logger->error($text);

        if (preg_match("/(?:ARRIVING\n*)?\s*(?<arrName>.+)\s+(?<arrTime>\d+\:\d+)\s*a?p?m\,?\s*(?<arrDate>\d+.*\d{4})/s", $text, $m)) {
            $s->arrival()
                ->noCode()
                ->name(preg_replace("/(\n\s*)/", " ", $m['arrName']))
                ->date(strtotime($m['arrDate'] . ', ' . $m['arrTime']));
        }
    }

    private function setDuration(FlightSegment $s, $text)
    {
        $s->extra()
            ->duration($this->re("/(?:DURATION\n)?\s*(\d+\s*(?:H|M).*)/", $text));
    }

    private function re($re, $str, $c = 1): ?string
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\s*(\d+)\s*(\w+)\,\s*(\d{4})\,\s*([\d\:]+)$#u", //Thu 9 Mar, 2023, 16:40
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $currentPos = mb_strpos($row, $word, $lastpos, 'UTF-8');

            if ($currentPos > 10) {
                $currentPos -= 5;
            }
            $pos[] = $currentPos;
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
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
