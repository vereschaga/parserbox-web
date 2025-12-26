<?php

namespace AwardWallet\Engine\ratehawk\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AirBookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "ratehawk/it-665652524.eml, ratehawk/it-665653062.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public $subjects = [
        '[Air travel] Booking confirmation No.',
        '[Air travel] Booking cancellation No.',
    ];

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.ratehawk.com') !== false) {
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

            if (strpos($text, "airsupport@ratehawk.com") !== false
                && (strpos($text, 'TICKET №') !== false)
                && (strpos($text, 'BOOKING NUMBER (PNR)') !== false)
                && (strpos($text, 'FLIGHT') !== false)
                && (strpos($text, 'Payment information') !== false)
            ) {
                return true;
            }
        }

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Order No.') and contains(normalize-space(), 'is cancelled')]")->length > 0
            && $this->http->XPath->query("//text()[normalize-space()='Airline booking code']/ancestor::tr[1]/descendant::td[2]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.ratehawk\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        $locatorInfo = $this->re("/(PASSENGER.+[A-Z\d]{6})\n\n[ ]+\D+\s*[→]/su", $text);
        $locatorTable = $this->splitCols($locatorInfo);
        $travellers = explode("\n", $this->re("/PASSENGER\n+(.+)ID/su", $locatorTable[0]));

        $f->general()
            ->travellers(array_filter($travellers))
            ->confirmation($this->re("/BOOKING NUMBER \(PNR\)\n+([A-Z\d]{6})/", $locatorTable[3]));

        if (preg_match("/STATUS\n+(?<status>.+)\nBOOKING DATE\n+(?<bookingDate>[\d\.]+)/", $locatorTable[1], $m)) {
            $f->general()
                ->status($m['status'])
                ->date(strtotime($m['bookingDate']));
        }

        $priceText = $this->re("/\n+(\s+Payment information.+)\n\s+Attention\!/su", $text);
        $priceTable = $this->splitCols($priceText);

        if (preg_match("/Total amount\n+(?<total>[\d\.\,\s]+)\s+(?<currency>\D{1,3})$/", $priceTable[0], $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $segmentText = $this->re("/[A-Z\d]{6}\n\n([ ]+\D+\s*[→].+)\n+\s+Payment information/su", $text);
        $segments = array_filter(preg_split("/\n\n\n\n/", $segmentText));

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $singleSegment = $this->re("/^(\s+FLIGHT\s+DEPARTURE.+)/msu", $segment);
            $singleTable = $this->splitCols($singleSegment);

            if (preg_match("/FLIGHT\s*(?<aName>[A-Z\d]+)\-(?<fNumber>\d+)/", $singleTable[0], $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $operator = $this->re("/Operated by\s+(.+)/", $singleTable[0]);

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $duration = $this->re("/Travel time\s*(\d+.+)/", $singleTable[0]);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            if (preg_match("/DEPARTURE\n+(?<depDate>[\d\:]+\s+\d+.+\d{4})\n(?<depName>(?:.+\n*){1,5})\((?<depCode>[A-Z]{3})\)/", $singleTable[1], $m)) {
                $s->departure()
                    ->name(str_replace("\n", ", ", $m['depName']))
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($m['depDate']));

                $terminal = $this->re("/Terminal\s*(.+)/", $singleTable[1]);

                if (!empty($terminal)) {
                    $s->departure()
                        ->terminal($terminal);
                }
            }

            if (preg_match("/ARRIVAL\n+(?<arrDate>[\d\:]+\d+.+\d{4})\n(?<arrName>(?:.+\n*){1,5})\((?<arrCode>[A-Z]{3})\)/", $singleTable[2], $m)) {
                $arrName = preg_replace("/(Baggage.+)/u", "", $m['arrName']);
                $s->arrival()
                    ->name(str_replace("\n", ", ", $arrName))
                    ->code($m['arrCode'])
                    ->date($this->normalizeDate($m['arrDate']));

                $terminal = $this->re("/Terminal\s*(.+)/", $singleTable[2]);

                if (!empty($terminal)) {
                    $s->arrival()
                        ->terminal($terminal);
                }
            }

            if (preg_match("/FARE\n+(?<cabin>\D+)\s\((?<bookingCode>[A-Z])\)\n/", $singleTable[3], $m)) {
                $s->extra()
                    ->bookingCode($m['bookingCode'])
                    ->cabin($m['cabin']);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Order No.') and contains(normalize-space(), 'is cancelled')]")->length > 0) {
            $conf = $this->http->FindSingleNode("//text()[normalize-space()='Airline booking code']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*([A-Z\d]{6})\s*$/");

            if (!empty($conf)) {
                $f = $email->add()->flight();

                $f->general()
                    ->confirmation($conf)
                    ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Passenger']/ancestor::tr[1]/descendant::td[2]", null, true, "/^(.+)\s+\(/"))
                    ->cancelled();
            }
        }

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\,\s*(\w+)\s(\d+)\,\s*(\d{4})\,\s*([\d\:]+)$#u", //Sunday, October 16, 2022, 13:15
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'         => 'EUR',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            '₹'         => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
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
}
