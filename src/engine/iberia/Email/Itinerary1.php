<?php

namespace AwardWallet\Engine\iberia\Email;

class Itinerary1 extends \TAccountChecker
{
    public $processors = [];
    public $reFrom = "#@.*\biberia\b#";
    public $reProvider = "#iberia\.com#";
    public $reSubject = "#(Travel itinerary)|(IBERIA \*+)|(Envío de itinerario)#";
    public $reText = "#Solicitud de compra en curso|Compra confirmada|iberiaexpress.com[> ]+wrote|http:\/\/www\.iberia\.com|Copyright © Iberia#";
    public $reHtml = null;
    public $mailFiles = "iberia/it-5467125.eml, iberia/it-6.eml, iberia/it-7.eml";

    public $lesEspaniollos = [
        'sep' => 'sep',
        'oct' => 'oct',
        'nov' => 'nov',
        'dic' => 'dec',
        'ene' => 'jan',
        'feb' => 'feb',
        'mar' => 'mar',
        'abr' => 'apr',
        'may' => 'may',
        'jun' => 'jun',
        'jul' => 'jul',
        'ago' => 'aug',
    ];

    public $xInstance = null;
    public $lastRe = null;

    public function __construct()
    {
        parent::__construct();

        // Define processors
        $this->processors = [
            // Parsed file "iberia/it-4.eml"
            "#Itinerary copy|Purchase confirmed|Copia del Itinerario#" => function (&$it, $parser) {
                $this->xBase($this->http); // helper

                $body = html_entity_decode($this->http->Response['body']); // full html
                $text = $this->mkText($body); // text representation

                $it = [];
                $it['Kind'] = 'T';
                $it['RecordLocator'] = $this->re("#(?:Reservation\s+code|Código\s+de\s+reserva)\s+([A-Z\d+]{5,6})#", $text);

                $total = $this->http->FindSingleNode("//*[text() = 'Total price'][last()]/following-sibling::span");

                if (is_numeric($total)) {
                    $it['TotalCharge'] = $total;
                }

                if (!isset($it['TotalCharge'])) {
                    $total = $this->xNode("//*[contains(text(),'TOTAL PRICE') or contains(text(), 'PRECIO TOTAL')]/following-sibling::*");
                    $it['TotalCharge'] = $this->mkCost($this->glue($total, ''));
                    $it['Currency'] = $this->mkCurrency($total);
                }

                // passengers
                $pass = [];
                $nodes = $this->http->XPath->query("//th[contains(text(), 'Passenger') or contains(text(), 'Pasajero')]/ancestor::tr[1]/following-sibling::tr/td[1]");

                for ($i = 0; $i < $nodes->length; $i++) {
                    $pass[trim($nodes->item($i)->nodeValue)] = 1;
                }

                if ($pass) {
                    $it['Passengers'] = implode(', ', array_keys($pass));
                }

                // flights
                $nodes = $this->http->XPath->query("//th[contains(text(), 'Departure') or contains(text(), 'Salida')]/ancestor::tr[1]/following-sibling::tr");
                $it['TripSegments'] = [];

                for ($i = 1; $i < $nodes->length; $i++) {
                    $tr = $nodes->item($i);
                    $seg = [];

                    if (!$this->http->FindSingleNode("td[3]", $tr)) {
                        continue;
                    } // checker

                    $td = $this->http->FindSingleNode("td[last()]", $tr);
                    //print $td."\n";
                    //					if (preg_match("#^([^\s]+)#", $td, $m)){
                    if (preg_match("#^([A-Z\d]{2})(\d+)#", $td, $m)) {
                        $seg['AirlineName'] = $m[1];
                        $seg['FlightNumber'] = $m[2];
                    } else {
                        continue;
                    }

                    if ($this->http->FindSingleNode("td[2]/img/@src", $tr)) {
                        $index = 2;
                    } else {
                        $index = 1;
                    }

                    for ($index = 1; $index <= 2; $index++) {
                        $td = $this->http->FindSingleNode("td[$index]", $tr);

                        if (preg_match("#^(.*?)\s+(\d{2}:\d{2})\w\s*/\s*(.*?)(\s+Seat:\s*(.*?)|)$#ms", $td, $m)) {
                            $seg['DepDate'] = strtotime($this->to_en($m[1] . ', ' . $m[2]));
                            $seg['DepName'] = $this->mkClear('# , #', $m[3], ', ');
                            $seg['DepCode'] = TRIP_CODE_UNKNOWN;

                            if (isset($m[5])) {
                                $seg['Seats'] = $m[5];
                            }

                            break;
                        }
                    }

                    $td = $this->http->FindSingleNode("td[" . ($index + 1) . "]", $tr);

                    if (preg_match("#^(.*?)\s+(\d{2}:\d{2})\w\s*/\s*(.*?)$#ms", $td, $m)) {
                        $seg['ArrDate'] = strtotime($this->to_en($m[1] . ', ' . $m[2]));
                        $seg['ArrName'] = $this->mkClear('# , #', $m[3], ', ');
                        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                    }

                    $it['TripSegments'][] = $seg;
                }
            },

            // Parsed files "iberia/it-3.eml", "iberia/it-1694393.eml"
            "#Booking confirmed|Compra confirmada#" => function (&$it, $parser) {
                $this->xBase($this->http); // helper

                $body = $this->http->Response['body']; // full html
                $text = text($body); // text representation

                $it = [];
                $it['Kind'] = 'T';

                $it['RecordLocator'] = $this->http->FindSingleNode("//*[text() = 'Reservation code' or text() = 'Código de reserva']/following-sibling::span");

                $subj = preg_replace('#\s*([.,])\s*#', '\1', nice(re('#(?:TOTAL\s+PRICE|PRECIO\s+TOTAL)\s*(.*?)\n\s*\n#s', $text))) . "\n";

                if (preg_match('#(?:(?P<Bonus>.*\s+Avios)\s+\+\s+)?(?P<Cash>.*)#', $subj, $m)) {
                    if (isset($m['Bonus']) and $m['Bonus']) {
                        $it['SpentAwards'] = $m['Bonus'];
                    }
                    $it['TotalCharge'] = cost($m['Cash']);
                    $it['Currency'] = currency($m['Cash']);
                }

                // passengers
                $pass = [];
                $nodes = $this->http->XPath->query("//th[contains(text(), 'Passenger') or contains(text(), 'Pasajero')]/ancestor::tr[1]/following-sibling::tr/td[1]");

                for ($i = 0; $i < $nodes->length; $i++) {
                    $pass[trim($nodes->item($i)->nodeValue)] = 1;
                }

                if ($pass) {
                    $it['Passengers'] = array_keys($pass);
                }

                // flights
                $xpath = "//th[contains(text(), 'Departure') or contains(text(), 'Salida')]/ancestor::tr[1]/following-sibling::tr[contains(., ':')]";
                $nodes = $this->http->XPath->query($xpath);
                $it['TripSegments'] = [];

                for ($i = 0; $i < $nodes->length; $i++) {
                    $tr = $nodes->item($i);
                    $seg = [];

                    $subj = $this->http->FindNodes('.//td[string-length(normalize-space(.)) > 1][3]//text()[string-length(normalize-space(.)) > 0]', $tr);

                    if (count($subj) == 2) {
                        $seg['FlightNumber'] = $subj[0];
                        $seg['Cabin'] = $subj[1];
                    } else {
                        $value = $this->http->FindSingleNode('(.//td[string-length(normalize-space(.)) > 1][3]//*[string-length(normalize-space(.)) > 0][1])[1]', $tr);

                        if (preg_match("#^([A-Z\d]{2})\s*(\d+)$#", $value, $m)) {
                            $seg['FlightNumber'] = $m[2];
                            $seg['AirlineName'] = $m[1];
                        }
                    }

                    foreach (['Dep' => 1, 'Arr' => 2] as $key => $value) {
                        $td = $this->http->FindSingleNode(".//td[string-length(normalize-space(.)) > 1][${value}]", $tr);

                        if (preg_match("#^\w+\s+(\d+)\s+de\s+(\w+)\s+de\s+(\d+)\s+(\d{2}:\d{2})\w\s*/\s*(.*?)(\s+Seat:\s*(.*?)|)$#ums", $td, $m)) {
                            // Spanish format
                            $seg["${key}Date"] = strtotime($m[1] . ' ' . en($m[2]) . ' ' . $m[3] . ', ' . $m[4]);
                            $seg["${key}Name"] = nice($m[5]);
                            $seg["${key}Code"] = TRIP_CODE_UNKNOWN;

                            if (isset($m[6]) and $m[6]) {
                                $seg['Seats'] = $m[6];
                            }
                        } elseif (preg_match('#\w+,\s+(\w+)\s+(\d+),\s+(\d+)\s+(\d+:\d+)h\s+/\s+(.*)#u', $td, $m)) {
                            // English format
                            $seg["${key}Date"] = strtotime($m[2] . ' ' . $m[1] . ' ' . $m[3] . ', ' . $m[4]);
                            $seg["${key}Name"] = nice($m[5]);
                            $seg["${key}Code"] = TRIP_CODE_UNKNOWN;
                        }
                    }

                    $it['TripSegments'][] = $seg;
                }
            },

            // Parsed file "iberia/it-3.eml"
            "#_IBERIA_REGULAR_EXPRESSION_#" => function (&$it, $parser) {
                $this->xBase($this->http); // helper

                $body = $this->http->Response['body']; // full html
                $text = $this->mkText($body); // text representation
                //$tabbed = $this->mkText($body, true); // text with tabs

                // @Handlers
                $it['Kind'] = 'R';
            },

            // Parsed file "iberia/it-2.eml"
            "#Solicitud de compra en curso#" => function (&$it, $parser) {
                $this->xBase($this->http); // helper

                $body = $this->http->Response['body']; // full html
                $text = $this->mkText($body); // text representation
                //$tabbed = $this->mkText($body, true); // text with tabs

                // @Handlers
                $it['Kind'] = 'T';
                $it['RecordLocator'] = $this->re("#\nCódigo de confirmación:\s*([^\n]+)#", $text);

                $it['Passengers'] = $this->glue($this->xNode("//th[contains(text(), 'Tarjeta de Fidelizaci')]/ancestor::table[1]/tbody/tr/td[1]"));

                $it['TotalCharge'] = $this->mkCost($this->xNode("//*[contains(text(), 'Precio Total')]/following-sibling::td[1]"));

                $it['Currency'] = $this->mkCurrency($this->re("#\nPrecio Total\s+(.*?)\nFactura#ms", $text));

                $it['TripSegments'] = [];

                $durations = [];
                $this->re("#Duración total del viaje:\s*([^\n]+)#ms", function ($m) use (&$durations) {
                    $durations[] = preg_replace("#(\d+)\s+(\w+)#", '\1\2', $m[1]);
                }, $text);

                $nodes = $this->http->XPath->query("//td[contains(text(), 'Llegada')]/ancestor::tr[1]/following-sibling::tr");
                $n = 0;

                for ($i = 0; $i < $nodes->length; $i++) {
                    $tr = $nodes->item($i);
                    $seg = [];

                    $seg['FlightNumber'] = $this->http->FindSingleNode("preceding-sibling::tr/td[2]", $tr);

                    $td = $this->http->FindSingleNode("td[2]", $tr);

                    if (preg_match("#^(\d{2}:\d{2})\w\s*,\s*(.*?\s*\d{4})\s+(.*?)\s+\(([^\)]+)\)\s+(.*?)$#ms", $td, $m)) {
                        $seg['DepDate'] = $this->mkDate($this->to_en($m[2] . ', ' . $m[1]));
                        $seg['DepName'] = $this->mkClear('# , #', $m[3] . ', ' . $m[5], ', ');
                        $seg['DepCode'] = $m[4];
                    }

                    $td = $this->http->FindSingleNode("td[3]", $tr);

                    if (preg_match("#^(\d{2}:\d{2})\w\s*,\s*(.*?\s*\d{4})\s+(.*?)\s+\(([^\)]+)\)\s+(.*?)$#ms", $td, $m)) {
                        $seg['ArrDate'] = $this->mkDate($this->to_en($m[2] . ', ' . $m[1]));
                        $seg['ArrName'] = $this->mkClear('# , #', $m[3] . ', ' . $m[5], ', ');
                        $seg['ArrCode'] = $m[4];
                    }

                    if (isset($durations[$n])) {
                        $seg['Duration'] = $durations[$n];
                    }

                    $it['TripSegments'][] = $seg;
                    $n++;
                }
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

    public function to_en($date)
    {
        $s1 = $this->re("#(\d+)\s+(\w{3})\w+\s+(\d+)([^\d].*?)$#", $this->mkClear("#\s+de\s+#", $date, ' '));

        if (isset($this->lesEspaniollos[strtolower($this->re(2))])) {
            $s2 = $this->lesEspaniollos[strtolower($this->re(2))];
        } else {
            $s2 = $this->re(2);
        }
        $s3 = $this->re(3) . $this->re(4);

        if (empty($s1)) {
            return $date;
        }

        return $s1 . ' ' . $s2 . ' ' . $s3;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->xBase($this->http);

        //		return ((isset($this->reText) && $this->reText)?preg_match($this->reText, $this->mkText($this->http->Response['body'])):false) ||
        //				((isset($this->reHtml) && $this->reHtml)?preg_match($this->reHtml, $this->http->Response['body']):false);
        return ((isset($this->reText) && $this->reText) ? $this->smartMatch($parser) : false)
            || ((isset($this->reHtml) && $this->reHtml) ? preg_match($this->reHtml, $this->http->Response['body']) : false);
    }

    public function smartMatch($parser)
    {
        $body = $parser->getPlainBody();

        if (!$body) {
            $body = $this->mkText($parser->getHTMLBody());
        }

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

    public function normalizeFloat($float)
    {
        return floatval(str_replace(',', '.', str_replace('.', '', $float)));
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $type = "";

        foreach ($this->processors as $re => $processor) {
            if (preg_match($re, $parser->getHTMLBody())) {
                $processor($itineraries, $parser);

                break;
            }
        }

        return [
            'emailType'  => 'Itinerary1',
            'parsedData' => [
                'Itineraries' => isset($itineraries[0]) ? $itineraries : [$itineraries],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 5;
    }

    public static function getEmailLanguages()
    {
        return ["en", "es"];
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
