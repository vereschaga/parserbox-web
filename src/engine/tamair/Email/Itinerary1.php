<?php

namespace AwardWallet\Engine\tamair\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "";
    public $processors = [];
    public $reFrom = "no-reply@tam.com.br";
    public $reSubject = "Booking confirmation";
    public $reBody = "ontact with TAM";
    public $reBody2 = "Confirmation of your booking";

    public $xInstance = null;
    public $lastRe = null;

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            // Parsing subject "tamair/it-1.eml"
            "#Thank you! your booking is completed#" => function (&$it) {
                $this->xBase($this->http); // helper

                $body = $this->http->Response['body']; // full html
                $text = $this->mkText($body); // text representation

                $it['Kind'] = "T";

                // @Begin {RecordLocator}
                $it['RecordLocator'] = $this->re("#\s+Booking reference:\s+([^\s]+)#ims", $text);
                // @End

                // Passengers
                $it['Passengers'] = $this->re("#\s+([ \w\d\-\,\.\(\)]+)\s+User:\s+([^\n]+)#ims", $text);

                // AccountNumbers
                $it['AccountNumbers'] = $this->re(2);

                // Cancelled
                // TotalCharge
                $it['TotalCharge'] = $this->mkCost($this->re("#\nTotal:\s+For all passengers including taxes and fees:([^\n]+)#", $text));

                // BaseFare
                $it['BaseFare'] = $this->mkCost($this->re("#\nTotal travelers:\s+([^\n=]+)#", $text));

                // Currency
                $it['Currency'] = $this->mkCurrency($this->re(1));

                // Tax
                $it['Tax'] = $this->mkCost($this->re("#Total fees:\s+([^\n=]+)#", $text));

                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory

                // Prepare segments data
                $it['TripSegments'] = [];

                $summary = $this->re("#\nFlight summary\n(.*?)\nPrice details\n#ms", $text);
                $bounds = preg_split("#(Bound\s+\d+\s+From[^\n]+)\n#ims", $summary, -1, PREG_SPLIT_DELIM_CAPTURE);

                for ($i = 0; $i < count($bounds) - 1; $i += 2) {
                    $title = $bounds[$i + 1];
                    $details = $bounds[$i + 2];

                    $r = preg_split("#(\w{3}\n\d{2}\n\w{3})\n#m", $details, -1, PREG_SPLIT_DELIM_CAPTURE);
                    $flightDetails = $r[2];

                    for ($j = 0; $j < count($r) - 1; $j += 2) {
                        $seg = [];
                        $info = $r[$j + 2];

                        // FlightNumber
                        $seg['FlightNumber'] = $this->re("#\nFlight Number:\s+([^\n]+)#", $info);

                        // DepCode
                        $seg['DepCode'] = TRIP_CODE_UNKNOWN;

                        // DepName
                        $seg['DepName'] = trim($this->re("#\nOutbound:([^\n]+)\s+Time:\s+(\d+:\d+)#", $text));

                        // DepDate
                        $seg['DepDate'] = $this->mkDate($this->glue($r[$j + 1], ' ') . ', ' . $this->re(2));

                        // ArrCode
                        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                        // ArrName
                        $seg['ArrName'] = trim($this->re("#\nArrival:([^\n]+)\s+Time:\s+(\d+:\d+)#", $text));

                        // ArrDate
                        $seg['ArrDate'] = $this->mkDate($this->glue($r[$j + 1], ' ') . ', ' . $this->re(2));

                        // AirlineName
                        $seg['AirlineName'] = trim($this->re("#\nOperated by:([^\n]+)#", $text));

                        // Aircraft
                        $seg['Aircraft'] = trim($this->re("#\nAircraft:([^\n]+)#", $text));

                        // TraveledMiles
                        // Class
                        // Cabin
                        $seg['Cabin'] = trim($this->re("#\nCabin:([^\n]+)#", $text));

                        // BookingClass
                        // Seats
                        // Duration
                        $seg['Duration'] = trim($this->re("#\nDuration[:\s]+([^\n]+)#", $text));

                        // Meal
                        // Smoking
                        // Stops

                        $it['TripSegments'][] = $seg;
                    }
                }
            },

            // Parsing subject "tamair/it-2.eml"
            "#Programa TAM Fidelidade#" => function (&$it) {
                $this->xBase($this->http); // helper

                $body = $this->http->Response['body']; // full html
                $text = $this->mkText($body); // text representation

                $it['Kind'] = "T";

                // RecordLocator
                $it['RecordLocator'] = trim($this->re("#CÓDIGO DA RESERVA:([^\n]+)#", $text));

                // Passengers
                // AccountNumbers
                // Cancelled
                // TotalCharge
                $it['TotalCharge'] = $this->mkCost($this->re("#TOTAL\n([^\n]+)#", $text));

                // BaseFare
                // Currency
                $it['Currency'] = $this->mkCurrency($this->re(1));

                // Tax
                $it['Tax'] = $this->mkCost($this->re("#Total[\s\n:]+(.*?)\nTOTAL#ms", $text));

                // Status
                // ReservationDate
                $it['ReservationDate'] = $this->mkDate($this->re("#\nData de emissão:([^\-]+)#ms", $text));

                // NoItineraries
                // TripCategory

                $it['TripSegments'] = [];

                // prepare year
                $year = date('Y', $it['ReservationDate']);

                $r = preg_split("#\n[^\n]+\n(Data:\n\d+\w{3})#", $text, -1, PREG_SPLIT_DELIM_CAPTURE);

                // clear junk
                array_shift($r);
                $r[count($r) - 1] = $this->mkClear("#\nBagagem:(.*?)$#s", end($r));

                for ($i = 0; $i < count($r); $i += 2) {
                    $seg = [];

                    $date = $this->re("#^Data:\s+([^\n]+)#", $r[$i]) . $year;
                    $info = $r[$i + 1];
                    $date . ' ' .

                    // FlightNumber
                    $seg['FlightNumber'] = $this->re("#\nVôo:\s+([^\n]+)#", $info);

                    // DepCode
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;

                    // DepName
                    $seg['DepName'] = $this->re("#\nSaída:\n([^\n]+)\n([^\n]+)#", $info, 2);

                    // DepDate
                    $seg['DepDate'] = $this->mkDate($date . ', ' . $this->re(1));

                    // ArrCode
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                    // ArrName
                    $seg['ArrName'] = $this->re("#\nChegada:\n([^\n]+)\n([^\n]+)#", $info, 2);

                    // ArrDate
                    $seg['ArrDate'] = $this->mkDate($date . ', ' . $this->re(1));

                    // AirlineName
                    $seg['AirlineName'] = $this->re("#\sOperado por\s+([^\n]+)#", $info);

                    // Aircraft
                    $seg['Aircraft'] = $this->re("#\sAeronave:\s+([^\n]+)#", $info);

                    // TraveledMiles
                    // Class
                    $seg['Cabin'] = $this->re("#\sClasse:\s+([^\n]+)#", $info);

                    // Cabin
                    // BookingClass
                    // Seats
                    // Duration
                    // Meal
                    // Smoking
                    // Stops

                    $it['TripSegments'][] = $seg;
                }
            },

            // Parsing subject "tamair/it-3.eml"
            "#Your Electronic Ticket Receipt#" => function (&$it) {
                $this->xBase($this->http); // helper

                $body = $this->http->Response['body']; // full html
                $text = $this->mkText($body); // text representation

                $it['Kind'] = "T";

                // RecordLocator
                $it['RecordLocator'] = trim($this->re("#RECORD LOCATOR:([^\n]+)#", $text));

                // Passengers
                // AccountNumbers
                // Cancelled
                // TotalCharge
                $it['TotalCharge'] = $this->mkCost($this->re("#TOTAL\n([^\n]+)#", $text));

                // BaseFare
                // Currency
                $it['Currency'] = $this->mkCurrency($this->re(1));

                // Tax
                $it['Tax'] = $this->mkCost($this->re("#Total[\s\n:]+(.*?)\nTOTAL#ms", $text));

                // Status
                // ReservationDate
                $it['ReservationDate'] = $this->mkDate($this->re("#\nIssue date:([^\-]+)#ms", $text));

                // NoItineraries
                // TripCategory

                $it['TripSegments'] = [];

                // prepare year
                $year = date('Y', $it['ReservationDate']);

                $r = preg_split("#\n[^\n]+\n(Date:\n\d+\w{3})#", $text, -1, PREG_SPLIT_DELIM_CAPTURE);

                // clear junk
                array_shift($r);
                $r[count($r) - 1] = $this->mkClear("#\nBaggage:(.*?)$#s", end($r));

                for ($i = 0; $i < count($r); $i += 2) {
                    $seg = [];

                    $date = $this->re("#^Date:\s+([^\n]+)#", $r[$i]) . $year;
                    $info = $r[$i + 1];
                    $date . ' ' .

                    // FlightNumber
                    $seg['FlightNumber'] = $this->re("#\nFlight:\s+([^\n]+)#", $info);

                    // DepName
                    $seg['DepName'] = $this->re("#\nDeparture:\n([^\n]+)\n([^\n]+)#", $info, 2);

                    // DepDate
                    $seg['DepDate'] = $this->mkDate($date . ', ' . $this->re(1));

                    // DepCode
                    if ($this->re("#^(.*?)\s+([\w\d]+),\s+terminal#", $seg['DepName'])) {
                        $seg['DepName'] = $this->re(1);
                        $seg['DepCode'] = $this->re(2);
                    } else {
                        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                    }

                    // ArrName
                    $seg['ArrName'] = $this->re("#\nArrival:\n([^\n]+)\n([^\n]+)#", $info, 2);

                    // ArrDate
                    $seg['ArrDate'] = $this->mkDate($date . ', ' . $this->re(1));

                    // ArrCode
                    if ($this->re("#^(.*?)\s+([\w\d]+),\s+terminal#", $seg['ArrName'])) {
                        $seg['ArrName'] = $this->re(1);
                        $seg['ArrCode'] = $this->re(2);
                    } else {
                        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                    }

                    // AirlineName
                    $seg['AirlineName'] = $this->re("#\sOperated by\s+([^\n]+)#", $info);

                    // Aircraft
                    $seg['Aircraft'] = $this->re("#\sAircraft:\s+([^\n]+)#", $info);

                    // TraveledMiles
                    // Class
                    $seg['Cabin'] = $this->re("#\nClass:\s+([^\n]+)#", $info);

                    // Cabin
                    // BookingClass
                    // Seats
                    // Duration
                    // Meal
                    // Smoking
                    // Stops

                    $it['TripSegments'][] = $seg;
                }
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["from"], $this->reFrom) !== false && strpos($headers["subject"], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@tam.com') !== false;
    }

    public function smartMatch($parser)
    {
        $body = $parser->getPlainBody();

        $isRe = preg_match("#[\|\(\)\[\].\+\*\!\^]#", $this->reText) ? true : false;

        if ($isRe) {
            return preg_match($this->reText, $body);
        } else {
            $find = preg_replace("#^\#([^\#]+)\#[imxse]*$#", '\1', $this->reText);

            if (stripos($body, $find) !== false) {
                return true;
            }
        }
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $itineraries['Kind'] = 'R';

        foreach ($this->processors as $re => $processor) {
            if (preg_match($re, $parser->getHTMLBody())) {
                $processor($itineraries);

                break;
            }
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$itineraries],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en", "pt"];
    }

    public function mkCost($value)
    {
        if (preg_match("#,#", $value) && preg_match("#\,#", $value)) { // like 1,299.99
            $value = preg_replace("#,#", '', $value);
        }

        $value = preg_replace("#,#", '.', $value);
        $value = preg_replace("#[^\d\.]#", '', $value);

        return is_numeric($value) ? (float) number_format($value, 2, '.', '') : null;
    }

    public function mkDate($date, $reltime = null)
    {
        if (!$reltime) {
            $check = strtotime($this->glue($this->mkText($date), ' '));

            return $check ? $check : null;
        }

        $unix = is_numeric($date) ? $date : strtotime($this->glue($this->mkText($date), ' '));

        if ($unix) {
            $guessunix = strtotime(date('Y-m-d', $unix) . ' ' . $reltime);

            if ($guessunix < $unix) {
                $guessunix += 60 * 60 * 24;
            } // inc day

            return $guessunix;
        }

        return null;
    }

    public function mkText($html, $preserveTabs = false, $stringifyCells = true)
    {
        $html = preg_replace("#&" . "nbsp;#uims", " ", $html);
        $html = preg_replace("#&" . "amp;#uims", "&", $html);
        $html = preg_replace("#&" . "quot;#uims", '"', $html);
        $html = preg_replace("#&" . "lt;#uims", '<', $html);
        $html = preg_replace("#&" . "gt;#uims", '>', $html);

        if ($stringifyCells && $preserveTabs) {
            $html = preg_replace_callback("#(</t(d|h)>)\s+#uims", function ($m) {return $m[1]; }, $html);

            $html = preg_replace_callback("#(<t(d|h)(\s+|\s+[^>]+|)>)(.*?)(<\/t(d|h)>)#uims", function ($m) {
                return $m[1] . preg_replace("#[\r\n\t]+#ums", ' ', $m[4]) . $m[5];
            }, $html);
        }

        $html = preg_replace("#<(td|th)(\s+|\s+[^>]+|)>#uims", "\t", $html);

        $html = preg_replace("#<(p|tr)(\s+|\s+[^>]+|)>#uims", "\n", $html);
        $html = preg_replace("#</(p|tr)>#uims", "\n", $html);

        $html = preg_replace("#\r\n#uims", "\n", $html);
        $html = preg_replace("#<br(/|)>#uims", "\n", $html);
        $html = preg_replace("#<[^>]+>#uims", ' ', $html);

        if ($preserveTabs) {
            $html = preg_replace("#[ \f\r]+#uims", ' ', $html);
        } else {
            $html = preg_replace("#[\t \f\r]+#uims", ' ', $html);
        }

        $html = preg_replace("#\n\s+#uims", "\n", $html);
        $html = preg_replace("#\s+\n#uims", "\n", $html);
        $html = preg_replace("#\n+#uims", "\n", $html);

        return trim($html);
    }

    public function xBase($newInstance)
    {
        $this->xInstance = $newInstance;
    }

    public function xHtml($path, $instance = null)
    {
        if (!$instance) {
            $instance = $this->xInstance;
        }

        return $instance->FindHTMLByXpath($path);
    }

    public function xNode($path, $instance = null)
    {
        if (!$instance) {
            $instance = $this->xInstance;
        }

        return $instance->FindSingleNode($path);
    }

    public function xText($path, $preserveCaret = false, $instance = null)
    {
        if ($preserveCaret) {
            return $this->mkText($this->xHtml($path, $instance));
        } else {
            return $this->xNode($path, $instance);
        }
    }

    public function mkImageUrl($imgTag)
    {
        if (preg_match("#src=(\"|'|)([^'\"]+)(\"|'|)#ims", $imgTag, $m)) {
            return $m[2];
        }

        return null;
    }

    public function glue($str, $with = ", ")
    {
        return implode($with, explode("\n", $str));
    }

    public function re($re, $text = false, $index = 1)
    {
        if (is_numeric($re) && $text == false) {
            return ($this->lastRe && isset($this->lastRe[$re])) ? $this->lastRe[$re] : null;
        }

        $this->lastRe = null;

        if (is_callable($text)) { // we have function
            // go through the text using replace function
            return preg_replace_callback($re, function ($m) use ($text) {
                return $text($m);
            }, $index); // index as text in this case
        }

        if (preg_match($re, $text, $m)) {
            $this->lastRe = $m;

            return $m[$index] ?? $m[0];
        } else {
            return null;
        }
    }

    public function mkNice($text, $glue = false)
    {
        $text = $glue ? $this->glue($text, $glue) : $text;

        $text = $this->mkText($text);
        $text = preg_replace("#,+#ms", ',', $text);
        $text = preg_replace("#\s+,\s+#ms", ', ', $text);
        $text = preg_replace_callback("#([\w\d]),([\w\d])#ms", function ($m) {return $m[1] . ', ' . $m[2]; }, $text);
        $text = preg_replace("#[,\s]+$#ms", '', $text);

        return $text;
    }

    public function mkCurrency($text)
    {
        if (preg_match("#\\$#", $text)) {
            return 'USD';
        }

        if (preg_match("#£#", $text)) {
            return 'EUR';
        }

        if (preg_match("#\bEUR\b#i", $text)) {
            return 'EUR';
        }

        if (preg_match("#\bUSD\b#i", $text)) {
            return 'USD';
        }

        if (preg_match("#\bBRL\b#i", $text)) {
            return 'BRL';
        }

        if (preg_match("#\bCHF\b#i", $text)) {
            return 'CHF';
        }

        if (preg_match("#\bHKD\b#i", $text)) {
            return 'HKD';
        }

        if (preg_match("#\bSEK\b#i", $text)) {
            return 'SEK';
        }

        if (preg_match("#\bZAR\b#i", $text)) {
            return 'ZAR';
        }

        return null;
    }

    public function arrayTabbed($tabbed, $divRowsRe = "#\n#", $divColsRe = "#\t#")
    {
        $r = [];

        foreach (preg_split($divRowsRe, $tabbed) as $line) {
            if (!$line) {
                continue;
            }
            $arr = [];

            foreach (preg_split($divColsRe, $line) as $item) {
                $arr[] = trim($item);
            }
            $r[] = $arr;
        }

        return $r;
    }

    public function arrayColumn($array, $index)
    {
        $r = [];

        foreach ($array as $in) {
            $r[] = $in[$index] ?? null;
        }

        return $r;
    }

    public function orval()
    {
        $array = func_get_args();
        $n = sizeof($array);

        for ($i = 0; $i < $n; $i++) {
            if (((gettype($array[$i]) == 'array' || gettype($array[$i]) == 'object') && sizeof($array[$i]) > 0) || $i == $n - 1) {
                return $array[$i];
            }

            if ($array[$i]) {
                return $array[$i];
            }
        }

        return '';
    }

    public function mkClear($re, $text)
    {
        return preg_replace($re, '', $text);
    }

    public function grep($pattern, $input, $flags = 0)
    {
        if (gettype($flags) == 'function') {
            $r = [];

            foreach ($input as $item) {
                $res = preg_replace_callback($pattern, $flags, $item);

                if ($res !== false) {
                    $r[] = $res;
                }
            }

            return $r;
        }

        return preg_grep($pattern, $input, $flags);
    }

    public function xPDF($parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');
        $pdf = "";

        foreach ($pdfs as $pdfo) {
            if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdfo), \PDF::MODE_SIMPLE)) !== null) {
                $pdf .= $html;
            }
        }

        return $pdf;
    }
}
