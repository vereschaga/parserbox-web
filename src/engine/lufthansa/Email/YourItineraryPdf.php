<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-100097891.eml, lufthansa/it-13335871.eml, lufthansa/it-13335909.eml";

    private $from = '/[\.@]lufthansa\.com/';

    private $detects = [
        'Your booking codes',
    ];

    private $lang = 'en';

    private $prov = ['Lufthansa', 'Austrian', 'SWISS'];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $body = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->detects as $detect) {
                if (stripos($body, $detect) !== false) {
                    $this->parseEmail($email, $body);

                    continue 2;
                }
            }
        }

        $pdfForTotal = $parser->searchAttachmentByName('INV.*pdf');

        if (0 < count($pdfForTotal)) {
            $body = \PDF::convertToHtml($parser->getAttachmentBody(array_shift($pdfForTotal)));

            if (!empty($body)) {
                $tot = clone $this->http;
                $tot->SetEmailBody($body);

                if (preg_match('/(\D)\s*([\d,.]+)/', $tot->FindSingleNode("(//p[starts-with(normalize-space(.), 'Total')]/following-sibling::p[1])[1]"), $m)) {
                    $email->price()
                        ->total(str_replace([','], [''], $m[2]))
                        ->currency(str_replace('$', 'USD', $m[1]))
                    ;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (0 < count($pdfs)) {
            foreach ($pdfs as $pdf) {
                $body = \PDF::convertToText($parser->getAttachmentBody($pdf));

                foreach ($this->prov as $prov) {
                    if (stripos($body, $prov) !== false) {
                        foreach ($this->detects as $detect) {
                            if (false !== stripos($body, $detect)) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) == 1;
    }

    private function parseEmail(Email $email, $text)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->re('/Your booking codes\s*:\s*(.+)/', $text));

        if (preg_match("/\n *Passengers\b.*\n([\s\S]+\n)\W*Your Flights\s+/", $text, $passengersText)) {
            if (preg_match_all("/(?:^|\n) *([[:alpha:]][[:alpha:]\-]*(?: [[:alpha:]][[:alpha:]\-]*){1,5}) {2,}.* {2,}(\d{3}-?\d{10})\s*(?=\n)/",
                $passengersText[1], $m)) {
                $f->general()
                    ->travellers(preg_replace("/^(Mr|Mrs|Ms|Miss) /i", '', $m[1]), true);

                $f->issued()
                    ->tickets($m[2], false);
            }
        }

        // Price
        $total = $this->re("/\n\s*Total price of your trip .+ {2,}(.+)(?:\n|$)/", $text);

        if (preg_match('/^\s*(?<currency>[^\d\s]+)\s*(?<amount>\d[\d,.]+)/', $total, $m)
            || preg_match('/^\s*(?<amount>\d[\d,.]+)\s*(?<currency>[^\d\s]+)/', $total, $m)
        ) {
            $f->price()
                ->total($this->amount($m['amount']))
                ->currency(str_replace('$', 'USD', $m['currency']))
            ;
        }

        // Segments
        $segmentsText = $this->findСutSection($text, 'Your Flights', 'Passengers');
        $seatReservation = $this->findСutSection($text, 'Seat Reservations');

        $segmentsbyDates = [];

        if (stripos($segmentsText, 'Flight on') !== false) {
            $segmentsbyDates = $this->split("/(?:^|\n) *(Flight on .+)/", $segmentsText);
        } elseif (stripos($segmentsText, 'on') !== false) {
            $segmentsbyDates = $this->split("/(?:^|\n|\D+)\s*(on .+)/", $segmentsText);
        }

        foreach ($segmentsbyDates as $sdtext) {
            $date = null;

            if (preg_match("/^\s*Flight on \w+, ([\d\.]+) from/", $sdtext, $m)
            || preg_match("/^on\s*(\w+\,\s*\d+\s*\w+\s*\d+)\n/", $sdtext, $m)) {
                $date = strtotime($m[1]);
            }

            $segments = $this->split("/\n( *\d{1,2}:\d{2}\s*(?:.*\n)+?\s*Duration:)/", $sdtext);

            foreach ($segments as $stext) {
                $s = $f->addSegment();

                $re = "/\s*(?<dtime>\d{1,2}:\d{2})(?<doverNight>\s*[+\-]\s*\d ?days?)?\s+(?<dname>.+)\((?<dcode>[A-Z]{3})\)(?<dterminal> *, *.*?)? {2,}(?<cabin>.+?) {2,}\S? *(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?<fn>\d{1,5})\s*" .
                    "\n\s*(?<atime>\d{1,2}:\d{2})(?<aoverNight> *[+\-] *\d ?days?)? +(?<aname>.+)\((?<acode>[A-Z]{3})\)(?<aterminal> *, *.*?)? {2,}/u";

                $re2 = "/\s*(?<dtime>\d{1,2}:\d{2})(?<doverNight>\s*[+\-]\s*\d ?days?)?\s+(?<dname>.+)\((?<dcode>[A-Z]{3})\)\,? {2,}(?<cabin>.+?) {2,}\S? *(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?<fn>\d{1,5})\s*\n\s*(?<dterminal>.*)?" .
                    "[ ]{3,}\s*.*\n*\s*(?<atime>\d{1,2}:\d{2})(?<aoverNight> *[+\-] *\d ?days?)? +(?<aname>.+)\((?<acode>[A-Z]{3})\)(?<aterminal> *, *.*?)?\n/u";

                if (preg_match($re, $stext, $m) || preg_match($re2, $stext, $m)) {
                    // Airline
                    $s->airline()
                        ->name($m['al'])
                        ->number($m['fn'])
                    ;

                    // Departure
                    $s->departure()
                        ->code($m['dcode'])
                        ->name($m['dname'])
                        ->date((!empty($date)) ? strtotime($m['dtime'], $date) : null)
                        ->terminal(trim(preg_replace("/\s*\bterminal\b\s*/i", '', $m['dterminal'] ?? ''), ' ,'), true, true)
                    ;

                    if (!empty($m['doverNight']) && !empty($s->getDepDate())) {
                        $s->departure()->date(strtotime($m['doverNight'], $s->getDepDate()));
                    }

                    // Arrival
                    $s->arrival()
                        ->code($m['acode'])
                        ->name($m['aname'])
                        ->date((!empty($date)) ? strtotime($m['atime'], $date) : null)
                        ->terminal(trim(preg_replace("/\s*\bterminal\b\s*/i", '', $m['aterminal'] ?? ''), ' ,'), true, true)
                    ;

                    if (!empty($m['aoverNight']) && !empty($s->getArrDate())) {
                        $s->arrival()->date(strtotime($m['aoverNight'], $s->getArrDate()));
                    }

                    if (preg_match('/^(.+)\s*\(([A-Z]{1,2})\)\s*$/', $m['cabin'], $mat)) {
                        $s->extra()
                            ->cabin($mat[1])
                            ->bookingCode($mat[2])
                        ;
                    } else {
                        $s->extra()
                            ->cabin($m['cabin'])
                        ;
                    }
                }

                // Extra
                if (preg_match('/\s+Duration *: *(.+?)(?: {2,}|\n|$)/', $stext, $m)) {
                    $s->extra()->duration($m[1]);
                }

                if (preg_match('/\s+Aircraft *: *(.+?)(?: {2,}|\n|$)/', $stext, $m)) {
                    $s->extra()->aircraft($m[1]);
                }

                if (preg_match('/\s+Operated by\:? *(.+?)(?: {2,}|\n|$)/', $stext, $m)) {
                    $s->airline()
                        ->operator($m[1]);
                }

                if (preg_match('/\s+(?:State|Status)\s*(\w+)/', $stext, $m)) {
//                    $it['Status'] = $m[3];
                    $s->extra()->status($m[1]);
                }

                if (!empty($seatReservation) && preg_match('/^\s*(\S+)( \S+)?/', $s->getDepName(), $dep)
                    && preg_match('/^\s*(\S+)( \S+)?/', $s->getArrName(), $arr)) {
                    if (preg_match("/(?:\n *" . $this->preg_implode([$dep[1], $dep[1] . ($dep[2] ?? '')]) . ')? - ' . $this->preg_implode([$arr[1], $arr[1] . ($arr[2] ?? '')]) . ' on .+([\s\S]+?)(?:.+ - .+ on .+ \d{2}\n|$)/', $seatReservation, $m)
                        && preg_match_all("/(?:^|\n| {2,})(\d{1,3}[A-Z]) {1,10}([[:alpha:]][[:alpha:]\-]*(?: [[:alpha:]][[:alpha:]\-]*){1,5})/", $m[1], $mat)
                    ) {
                        $s->extra()
                            ->seats($mat[1]);
                    }
                }
            }
        }

        return $email;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function findСutSection($input, $searchStart, $searchFinish = false)
    {
        $inputResult = null;

        if ($searchStart) {
            $left = mb_strstr($input, $searchStart);
        } else {
            $left = $input;
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }
}
