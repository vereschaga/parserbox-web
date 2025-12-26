<?php

namespace AwardWallet\Engine\royalcaribbean\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "royalcaribbean/it-1.eml";
    public $processors = [];
    public $reFrom = "#documentation\@rccl\.com#";
    public $reProvider = "#rccl\.com#";
    public $reSubject = "#Choice Air#";
    public $reText = null;
    public $reHtml = "#From:(<[^>]+>|\s)*\w+@rccl.com#";

    public $xInstance = null;
    public $lastRe = null;

    public function __construct()
    {
        parent::__construct();

        // Define processors
        $this->processors = [
            // Parsed file "royalcaribbean/it-1.eml"
            "#Choice Air Confirmation#" => function (&$it, $parser) {
                $this->xBase($this->http); // helper

                $text = $this->mkText($this->xPDF($parser)); // text representation

                // Air
                $air = [];
                $air['Kind'] = 'T';
                $air['RecordLocator'] = $this->re("#\nRecord Locator\s*.[^\|]+\|([\w\d\-]+)#", $text);

                // @Handlers
                $cr = [];
                $cr['Kind'] = 'T';
                $cr['RecordLocator'] = $this->re("#\nReservation ID\s*([\d]+)#", $text);

                $cr['TripCategory'] = TRIP_CATEGORY_CRUISE;

                // cruise trip
                $cr['Passengers'] = $air['Passengers'] = beautifulName(trim($this->glue($this->re("#\nGuest Name\n(([^\n]+\n){1,2})Crown#", $text), ' ')));
                $cr['AccountNumbers'] = $air['AccountNumbers'] = $this->re("#\nCrown & Anchor Number\s+(\d+)#", $text);
                $cr['ShipName'] = $this->re("#\nShip\n([^\n]+)#", $text);
                $cr['CruiseName'] = $this->re("#\nItinerary\n([^\n]+)#", $text);
                $cr['RoomNumber'] = $this->re("#\nStateroom\n([^\n]+)#", $text);

                $air['TotalCharge'] = $this->re("#\s+Air Charge\n([^\n]+)#", $text);
                $air['Currency'] = $this->re("#\nCharges\n([^\n]+)#", $text);
                $air['ReservationDate'] = $this->mkDate($this->re("#\nIssue Date\s+([^\n]+)#", $text));

                $year = date('Y', $air['ReservationDate']);

                $cr['TripSegments'] = [];
                $air['TripSegments'] = [];

                $prev = null;

                $this->re("#\n(\d+ \w{3})\n([^\n]+)(\n\d+:\d+\s\w{2}|)(\n\d+:\d+\s\w{2}|)#ms", function ($m) use (&$cr, &$air, &$prev, &$year) {
                    $seg = [];

                    if ($prev) {
                        $flightIndex = count($air['TripSegments']) - 1;
                        $cruiseIndex = count($cr['TripSegments']) - 1;

                        // cruise
                        $index = count($cr['TripSegments']) - 1;

                        if ($index >= 0) {
                            // if previous was an airtrip unset last
                            if (!isset($prev['Port'])) {
                                if ($flightIndex > 0) {
                                    $air['TripSegments'][$flightIndex - 1] = null;
                                }
                                // CHECK: it seems it will not be affected anyway,
                                //        it will be removed lower in "... i += 2"
                                //        check in future and remove this "if" block
                            }

                            if ($m[2] !== 'CRUISING') {
                                $cr['TripSegments'][$index]['ArrDate'] = $this->correctByDate($this->mkDate($m[1] . " $year, " . trim($m[3] ? $m[3] : $m[4])), $year);
                            }
                        }

                        // flight
                        $index = count($air['TripSegments']) - 1;

                        if ($index >= 0) {
                            if (!isset($air['TripSegments'][$index]['ArrDate'])) {
                                if ($this->re("#^(.*?)\((\w{3})\)(.*?)$#", $m[2])) { // from flight
                                    $air['TripSegments'][$index]['ArrCode'] = $this->re(2);
                                    $air['TripSegments'][$index]['ArrName'] = $this->re("#^[^\-]+\-(.*?)$#", trim($this->re(1)) . trim($this->re(3)));
                                } else {
                                    $air['TripSegments'][$index]['ArrCode'] = TRIP_CODE_UNKNOWN;
                                    $air['TripSegments'][$index]['ArrName'] = $m[2];
                                }

                                $air['TripSegments'][$index]['ArrDate'] = $this->correctByDate($this->mkDate($m[1] . " $year, " . trim($m[3] ? $m[3] : $m[4])), $year);
                            }
                        }
                    }

                    if ($this->re("#^(.*?)\((\w{3})\)(.*?)$#", $m[2])) { // flight
                        $seg['DepCode'] = $this->re(2);
                        $seg['DepDate'] = $this->correctByDate($this->mkDate($m[1] . " $year, " . trim($m[4] ? $m[4] : $m[3])), $year);

                        $seg['FlightNumber'] = $this->re("#^([^\-]+)\-(.*?)$#", trim($this->re(1)) . trim($this->re(3)));
                        $seg['DepName'] = $this->re(2);

                        $prev = $seg;

                        $air['TripSegments'][] = $seg;
                    } else { // cruise
                        $seg['DepDate'] = $this->correctByDate($this->mkDate($m[1] . " $year, " . trim($m[4] ? $m[4] : $m[3])), $year);
                        $seg['Port'] = $m[2];

                        $prev = $seg;

                        if ($seg['Port'] == 'CRUISING') {
                            return;
                        }

                        $cr['TripSegments'][] = $seg;
                    }
                }, $text);

                $this->converter = new \CruiseSegmentsConverter();
                $cr['TripSegments'] = $this->converter->Convert($cr['TripSegments']);

                $new = [];

                for ($i = 0; $i < count($air['TripSegments']) - 1; $i += 2) {
                    if ($air['TripSegments'][$i]) {
                        $new[] = $air['TripSegments'][$i];
                    }
                }

                $air['TripSegments'] = $new;
                $it = [$air, $cr];
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

        return ((isset($this->reText) && $this->reText) ? preg_match($this->reText, $this->mkText($this->http->Response['body'])) : false)
                || ((isset($this->reHtml) && $this->reHtml) ? preg_match($this->reHtml, $this->http->Response['body']) : false);
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
                'Itineraries' => isset($itineraries[0]) ? $itineraries : [$itineraries],
            ],
        ];
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
