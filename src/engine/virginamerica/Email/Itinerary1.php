<?php

namespace AwardWallet\Engine\virginamerica\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "virginamerica/it-4.eml, virginamerica/it-5.eml, virginamerica/it-6.eml";
    public $processors = [];
    public $fromList = ["\.virginamerica.com"];
    public $reSubject = "#Virgin America#";
    public $reProvider = "#\.virginamerica\.com$#i";

    public $xInstance = null;
    public $lastRe = null;

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            // Parsing subject "virginamerica/it-1.eml"
            "#We look forward to seeing you onboard|We can[^\s]+ wait to see you\!#" => function (&$it) {
                $this->xBase($this->http); // helper

                $body = $this->http->Response['body']; // full html
                $text = $this->mkText($body); // text representation
                $tabbed = $this->mkText($body, true); // text representation

                $it['Kind'] = "T";

                // RecordLocator
                $it['RecordLocator'] = $this->mkText($this->re("#Your Confirmation Code:\s+([^\n]+)\n#", $text));

                // TotalCharge
                $it['TotalCharge'] = $this->mkCost($this->re("#\n(Total:(?:\s+\([^\)]+\))|Grand Total:)\s+([^\s]+)#ims", $text, 2));

                // Currency
                $it['Currency'] = $this->mkCurrency($this->re(2));

                // BaseFare
                $it['BaseFare'] = $this->mkCost($this->re("#\nBase Far[^:\n]+:\s+([^\s]+)#ims", $text));

                // Tax
                $it['Tax'] = $this->mkCost($this->re("#\nFederal Tax:\s+([^\s]+)#ims", $text));

                // ReservationDate
                // NoItineraries

                /* TripSegments */
                $it['TripSegments'] = [];

                if (preg_match("#We look forward to seeing you onboard#", $text)) {
                    $nodes = $this->http->XPath->query("//*[text() = \"Where You're Going\"]/ancestor::table[2]/tbody/tr[2]//table[1]//tr");
                    $who = $this->http->XPath->query("//*[text() = \"Who's Flying\"]/ancestor::table[2]/tbody/tr[2]//table[1]//tr");
                } else {
                    $this->secondTypeTrip($it);

                    return;
                }

                $seats = [];
                $names = [];
                $numbers = [];

                for ($i = 1; $i < $who->length; $i++) {
                    $node = $who->item($i);
                    $seat = $this->http->FindSingleNode("td[3]", $node);
                    $number = $this->http->FindSingleNode("td[2]", $node);
                    $name = $this->http->FindSingleNode("td[1]", $node);

                    if ($seat) {
                        $seats[$seat] = 1;
                    }

                    if ($name) {
                        $names[$name] = 1;
                    }

                    if ($number) {
                        $numbers[$number] = 1;
                    }
                }

                if ($seats) {
                    $seats = implode(', ', array_keys($seats));
                }

                if ($names) {
                    $it['Passengers'] = implode(', ', array_keys($names));
                }

                if ($numbers) {
                    $it['AccountNumbers'] = implode(', ', array_keys($numbers));
                }

                for ($i = 1; $i < $nodes->length; $i++) {
                    $seg = [];
                    $node = $nodes->item($i);

                    // FlightNumber
                    $seg['FlightNumber'] = $this->http->FindSingleNode("td[2]", $node);

                    $dep = $this->http->FindSingleNode("td[3]", $node);
                    $arr = $this->http->FindSingleNode("td[4]", $node);

                    // DepCode
                    $seg['DepCode'] = $this->re("#^(.*?)\(([^\)]+)\)\s*(?:(\d+:\d+\s+\w+))#", $dep);

                    // DepName
                    $seg['DepName'] = $this->re(2);

                    // DepDate
                    $seg['DepDate'] = $this->mkDate($this->http->FindSingleNode("td[1]", $node) . ', ' . $this->re(3));

                    // ArrCode
                    $seg['ArrCode'] = $this->re("#^(.*?)\(([^\)]+)\)\s*(?:(\d+:\d+\s+\w+))#", $arr);

                    // ArrName
                    $seg['ArrName'] = $this->re(2);

                    // ArrDate
                    $seg['ArrDate'] = $this->mkDate($this->http->FindSingleNode("td[1]", $node) . ', ' . $this->re(3));

                    // AirlineName
                    // Aircraft
                    // TraveledMiles
                    // Class
                    // Cabin
                    // BookingClass
                    // Seats
                    if ($seats) {
                        $seg['Seats'] = $seats;
                    }

                    // Duration
                    // Meal
                    // Smoking
                    // Stops
                    $seg['Stops'] = intval($this->http->FindSingleNode("td[5]", $node));

                    $it['TripSegments'][] = $seg;
                }
            },
        ];
    }

    public function secondTypeTrip(&$it)
    {
        $nodes = $this->http->XPath->query("//*[contains(text(), \"Where You’re Going\")]/ancestor::table[2]");

        if (!$nodes->length) {
            return;
        }

        $text = $this->mkText($nodes->item(0)->nodeValue);

        $flights = preg_split("#\nDEPARTING:\s+#", $text);

        if (count($flights) <= 1) {
            return;
        }

        $names = [];

        array_shift($flights);

        for ($i = 0; $i < count($flights); $i++) {
            $seg = [];
            $r = $flights[$i];

            $seg['FlightNumber'] = $this->re("#\nFlight\s+([^\s]+)\s+#", $r);

            $seg['DepName'] = $this->re("#\nDepart:\s*([^\(]+)\((\w{3})\)\s+(\d{1,2}:\d{1,2}\s+\w{2})\s+on\s+([^\n]+)#ms", $r);
            $seg['DepCode'] = $this->re(2);
            $seg['DepDate'] = $this->mkDate($this->re(4) . ', ' . $this->re(3));

            $seg['ArrName'] = $this->re("#\nArrive:\s*([^\(]+)\((\w{3})\)\s+(\d{1,2}:\d{1,2}\s+\w{2})\s+on\s+([^\n]+)#ms", $r);
            $seg['ArrCode'] = $this->re(2);
            $seg['ArrDate'] = $this->mkDate($this->re(4) . ', ' . $this->re(3));

            $seg['Cabin'] = $this->re("#\nSeat Type:\s*([^\n]+)#", $r);
            $seg['Stops'] = $this->re("#\nStops:\s*([^\n]+)#", $r);

            $seg['Seats'] = $this->re("#Traveler\(s\)\s+Seat\s+[^\n]+\n([^\n]+)\n([^\n]*)#ms", $r, 2);
            $names[trim($this->re(1))] = 1;

            $it['TripSegments'][] = $seg;
        }

        if ($names) {
            $it['Passengers'] = implode(', ', array_keys($names));
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->reProvider, $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers["from"]) && in_array($headers["from"], $this->fromList))
                || ($headers['subject'] && preg_match($this->reSubject, $headers['subject']));
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
