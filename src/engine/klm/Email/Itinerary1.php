<?php

namespace AwardWallet\Engine\klm\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "klm/it-1.eml, klm/it-2.eml, klm/it-3.eml, klm/it-4.eml";
    public $processors = [];
    public $reFrom = "#@klm\.com#i";
    public $reText = "#From:\s+KLM Reservations|@KLM\.com#";
    public $reProvider = "#klm\.com#i";

    public $xInstance = null;
    public $lastRe = null;

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            // Parsing subject "klm/it-1.eml"
            "#^$#" => function (&$it, &$parser) {
                $this->xBase($this->http);
                $it['Kind'] = "T";

                $this->xAttachments($parser);
                $json = $this->re("#\{\".+\}$#ms", $this->http->Response['body']);
                $j = json_decode($json);
                //print_r($j);

                //				$it['ReservationDate'] = $this->mkDate($j->ItineraryInfo->CreateDate);
                $it['ReservationDate'] = strtotime(preg_replace("#(\d{1,2}:\d{2}):\d{2}#", '$1:00', $j->ItineraryInfo->CreateDate));
                $it['Currency'] = $j->ItineraryInfo->Currency;
                $it['RecordLocator'] = $j->ItineraryInfo->ItineraryNumber;
                $it['TripSegments'] = [];

                foreach ($j->Routes->SegmentInfoCollection->SegmentInfo as $segments) {
                    if (!isset($segments->DetailedFlightSegments)
                        || !isset($segments->DetailedFlightSegments->DetailedFlightSegment)) {
                        continue;
                    }

                    $flights = $segments->DetailedFlightSegments;

                    if (isset($flights->DetailedFlightSegment)) {
                        $flights = [$flights->DetailedFlightSegment];
                    }

                    foreach ($flights as $segment) {
                        $seg = [];
                        //print_r($segment);print("\n");
                        if (!isset($segment->FlightNo)) {
                            continue;
                        }

                        $seg['FlightNumber'] = $segment->FlightNo;
                        $seg['DepCode'] = $segment->DepartureAirport;
                        $seg['DepDate'] = $this->mkDate($segment->DepartureTime);
                        $seg['DepName'] = $segment->DepartureLocationName;

                        $seg['ArrCode'] = $segment->ArrivalAirport;
                        $seg['ArrDate'] = $this->mkDate($segment->ArrivalTime);
                        $seg['ArrName'] = $segment->ArrivalLocationName;

                        $seg['TraveledMiles'] = $segment->Mileage;
                        $seg['Aircraft'] = $segment->AircraftType;
                        $seg['AirlineName'] = $segment->OperatingCarrier->CarrierName;

                        $seg['Cabin'] = $segments->PrefferdClassName;

                        $it['TripSegments'][] = $seg;
                    }
                }

                $it['Passengers'] = $j->TravellerInfo->TravellerDetails->PaxName;
            },

            // Parsing subject "klm/it-5.eml"
            "#Your booking is confirmed#" => function (&$it) {
                $this->xBase($this->http); // helper

                $body = $this->http->Response['body']; // full html
                $text = $this->mkText($body); // text representation
                $tabbed = $this->mkText($body, true); // text tabbed representation

                $it['Kind'] = "T";

                // RecordLocator
                $it['RecordLocator'] = $this->re("#Booking code\s+([^\s]+)#ms", $text);

                // Passengers
                $r = [];
                $passText = $this->re("#\nPassengers\n(.*?)\nPrice specification#ms", $text);
                $passLines = explode("\n", $passText);

                foreach ($passLines as $line) {
                    if ($name = $this->re("#^(.*?)\s+(\(\w+\)\s+|)Please note#", $line)) {
                        $r[] = $name;
                    }
                }

                $it['Passengers'] = implode(", ", $r);

                // AccountNumbers
                $r = [];
                preg_replace_callback("#\nfrequent flyer number\s+([^\n]+)#msi", function ($m) use (&$r) {
                    $r[] = $m[1];
                }, $passText);
                $it['AccountNumbers'] = implode(", ", $r);

                // Cancelled

                // TotalCharge (full)
                $it['TotalCharge'] = $this->mkCost($this->re("#\nTotal price\s+([^\n]+)\nFlight and extra options#ims", $text));

                // TotalCharge (in case it first absent)
                if (!$it['TotalCharge']) {
                    $it['TotalCharge'] = $this->mkCost($this->re("#\nTotal price:\s+([^\n]+)#", $text));
                }

                // BaseFare

                // Currency
                $it['Currency'] = $this->mkCurrency($this->re("#\nTotal price:\s+([^\n]+)#", $text));

                // Tax

                // ReservationDate

                /* TripSegments */
                $it['TripSegments'] = [];

                // cut routes slice
                if (preg_match("#\nDeparture:\n(.*?)\nReturn:\n(.*?)\nPassengers\n#ms", $text, $m)) {
                    $r = preg_split("#(\w{3}\s+\d{1,2}\s+\w{3}\s+\d{1,2}\s+\d+:\d+)\n#m", $m[1] . "\n" . $m[2], -1, PREG_SPLIT_DELIM_CAPTURE);
                    array_shift($r); // remove first, shows only start+end
                } else {
                    $r = [];
                }

                for ($i = 0; $i < count($r); $i += 4) {
                    $seg = [];

                    // FlightNumber
                    $number = $seg['FlightNumber'] = $this->re("#Flight number:\s+([^\n]+)#ms", $r[$i + 3]);

                    // DepCode
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;

                    // DepName
                    $seg['DepName'] = $this->re("#^[^\n]+\n\(([^\n]+)\)#", $r[$i + 1]);

                    // DepDate
                    $seg['DepDate'] = $this->mkDate($r[$i + 0]);

                    // ArrCode
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                    // ArrName
                    $seg['ArrName'] = $this->re("#^[^\n]+\n\(([^\n]+)\)#", $r[$i + 3]);

                    // ArrDate
                    $seg['ArrDate'] = $this->mkDate($r[$i + 2]);

                    // AirlineName
                    $seg['AirlineName'] = $this->re("#\s+Operated by:\s+([^\n]+)#ms", $r[$i + 3]);

                    // Aircraft
                    $seg['Aircraft'] = $this->re("#\s+Aircraft type:\s+([^\n]+)#ms", $r[$i + 3]);

                    // TraveledMiles
                    // Class
                    $seg['Cabin'] = $this->re("#\nClass:\s+([^\n]+)#ms", $r[$i + 3]);

                    // Cabin
                    // BookingClass

                    // Seats
                    $seats = [];
                    $this->re("#Flight number:\s+$number\nDeparture:[^\n]+\nPassenger[^\w\n]+Seat number[^\w\n]+Seat type[^\w\n]+Price\n(([^\t\n]+\t[^\t\n]+[^\n]+\n){1,99})#msi", function ($m) use (&$seats) {
                        $info = $this->arrayTabbed($m[1]);
                        $seats = array_merge($seats, $this->arrayColumn($info, 1));
                    }, $tabbed);
                    $seg['Seats'] = implode(', ', $seats);

                    // Duration
                    // Meal
                    $meal = [];
                    preg_replace_callback("#Flight number:\s+$number\nDeparture:[^\n]+\nPassenger[^\w\n]+Meal[^\w\n]+Price\n(([^\t\n]+\t[^\t\n]+\t[^\t\n]+\n){1,99})#msi", function ($m) use (&$meal) {
                        $info = $this->arrayTabbed($m[1]);
                        $meal = array_merge($meal, $this->arrayColumn($info, 1));
                    }, $tabbed);

                    if ($meal) {
                        $seg['Meal'] = implode(', ', $meal);
                    }

                    // Smoking
                    // Stops

                    $it['TripSegments'][] = $seg;
                }
            },

            // Parsing subject "klm/it-6.eml"
            "#My Trip#" => function (&$it) {
                $this->xBase($this->http); // helper

                $body = $this->http->Response['body']; // full html
                $text = $this->mkText($body); // text representation
                $tabbed = $this->mkText($body, true); // text tabbed representation

                $it['Kind'] = "T";

                $it['RecordLocator'] = CONFNO_UNKNOWN;

                $it['Passengers'] = $this->re("#\nBooked by:\s+E-mail:\s+([^\n]+)#", $text);

                $it['TripSegments'] = [];

                $trip = $this->re("#\nDeparture flight:(.*?)\nReport misuse#ms", $text);

                $this->re("#Name:\s+From:\s+To:\s+Flight number:\s+(([^\n]+\n){8})#", function ($m) use (&$it) {
                    $in = explode("\n", $m[1]);
                    $seg = [];
                    $seg['FlightNumber'] = $in[7];
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                    $seg['DepName'] = $in[1];
                    $seg['ArrName'] = $in[4];
                    $seg['DepDate'] = $this->mkDate($in[2] . ' ' . $in[3]);
                    $seg['ArrDate'] = $this->mkDate($in[5] . ' ' . $in[6]);

                    $it['TripSegments'][] = $seg;
                }, $trip);
            },
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->reProvider, $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return ((isset($this->reFrom) && isset($headers['from'])) ? preg_match($this->reFrom, $headers["from"]) : false)
                || ((isset($this->reSubject) && isset($headers['subject'])) ? preg_match($this->reSubject, $headers["subject"]) : false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->xBase($this->http);

        //		return (isset($this->reText)?preg_match($this->reText, $this->mkText($this->http->Response['body'])):false) ||
        //				(isset($this->reHtml)?preg_match($this->reHtml, $this->http->Response['body']):false);
        return ((isset($this->reText) && $this->reText) ? $this->smartMatch($parser) : false)
            || ((isset($this->reHtml) && $this->reHtml) ? preg_match($this->reHtml, $this->http->Response['body']) : false);
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
                $processor($itineraries, $parser);

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
        return 3;
    }

    public function mkCost($value)
    {
        if (preg_match("#,#", $value) && preg_match("#\.#", $value)) { // like 1,299.99
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
        $html = preg_replace("#&nbsp;#uims", " ", $html);
        $html = preg_replace("#&amp;#uims", "&", $html);
        $html = preg_replace("#&quot;#uims", '"', $html);
        $html = preg_replace("#&lt;#uims", '<', $html);
        $html = preg_replace("#&gt;#uims", '>', $html);

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
        $nodes = $instance->FindNodes($path);

        return count($nodes) ? implode("\n", $nodes) : null; //$instance->FindSingleNode($path);
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
            return 'GBP';
        }

        if (preg_match("#€#", $text)) {
            return 'EUR';
        }

        if (preg_match("#\bCAD\b#i", $text)) {
            return 'CAD';
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

        if (preg_match("#\bIN(|R)\b#i", $text)) {
            return 'INR';
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

    public function mkClear($re, $text, $by = '')
    {
        return preg_replace($re, $by, $text);
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

    public function xAttachments($parser)
    {
        $all = [];

        for ($i = 0; $i < $parser->countAttachments(); $i++) {
            $a = $parser->getAttachment($i);
            $body = $parser->getAttachmentBody($i);

            $type = $a['headers']['content-disposition'];

            if (preg_match("#\.pdf$#i", $type)) {
                $all[] = \PDF::convertToHtml($body, \PDF::MODE_SIMPLE);

                continue;
            }

            if (preg_match("#\.xml$#i", $type)) {
                $xml = simplexml_load_string($body);
                $all[] = json_encode($xml);

                continue;
            }

            $all[] = $body;
        }

        $this->http->SetBody(implode("\n<!--delimeter begin-->\n<BR>\n<BR><!--delimeter end-->\n", $all));
    }

    public function xPDF($parser, $wildcard = null)
    {
        $pdfs = $parser->searchAttachmentByName($wildcard ? $wildcard : '.*pdf');
        $pdf = "";

        foreach ($pdfs as $pdfo) {
            if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdfo), \PDF::MODE_SIMPLE)) !== null) {
                $pdf .= $html;
            }
        }

        return $pdf;
    }

    public function correctByDate($date, $anchorDate)
    {
        // $anchorDate should be earlier than $date
        // not implemented yet
        return $date;
    }
}
