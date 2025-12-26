<?php

namespace AwardWallet\Engine\national\Email;

class Itinerary1 extends \TAccountChecker
{
    public $processors = [];
    public $reFrom = '#reservations@nationalcar\.com#';
    public $reProvider = '#nationalcar\.com#';
    public $reSubject = "#National Car#";
    public $reText = null;
    public $reHtml = null;
    public $mailFiles = "national/it-1.eml, national/it-2.eml, national/it-3.eml, national/it-4.eml";

    public $xInstance = null;
    public $lastRe = null;

    public function __construct()
    {
        parent::__construct();

        // Define processors
        $this->processors = [
            // Parsed file "national/it-1.eml"
            "#Your On Request itinerary number is|Your confirmation number is#" => function (&$it, $parser) {
                $this->xBase($this->http); // helper

                $body = $this->http->Response['body']; // full html
                $text = $this->mkText($body); // text representation
                //$tabbed = $this->mkText($body, true); // text with tabs

                // @Handlers
                $it['Kind'] = 'L';

                $pickupNode = $this->http->XPath->query("//td/*[contains(text(), 'Pickup Information')]/../ancestor::tr[1]/following-sibling::tr/td/*[contains(text(), 'Location:')]/ancestor::td[1]/following-sibling::td[2]");
                $dropoffNode = $this->http->XPath->query("//td/*[contains(text(), 'Dropoff Information')]/../ancestor::tr[1]/following-sibling::tr");

                $it['Number'] = $this->re("#\b(Your confirmation number is|Your On Request itinerary number is):\s*([^\n]+)#", $text, 2);

                $it['RenterName'] = $this->xNode("//td//*[contains(text(), 'Name:')]/ancestor::td[1]/following-sibling::td[2]");

                $location = $this->http->XPath->query("//table//td//*[contains(text(), 'Location:')]/ancestor::td[1]/following-sibling::td[2]");
                $address = $this->http->XPath->query("//table//td//*[contains(text(), 'Address:')]/ancestor::td[1]/following-sibling::td[2]");
                $datetime = $this->http->XPath->query("//table//td//*[contains(text(), 'Date & Time:')]/ancestor::td[1]/following-sibling::td[2]");
                $phones = $this->http->XPath->query("//table//td//*[contains(text(), 'Phone:')]/ancestor::td[1]/following-sibling::td[2]");
                $fax = $this->http->XPath->query("//table//td//*[contains(text(), 'Fax:')]/ancestor::td[1]/following-sibling::td[2]");
                $hours = $this->http->XPath->query("//table//td//*[contains(text(), 'Hours:')]/ancestor::td[1]/following-sibling::td[2]");

                if ($value = $location->item(0)) {
                    $it['PickupLocation'] = trim($value->nodeValue);

                    if ($value = $address->item(0)) {
                        $it['PickupLocation'] .= ', ' . $this->glue($this->mkText($value->nodeValue));
                    }
                }

                if ($value = $location->item(1)) {
                    $it['DropoffLocation'] = trim($value->nodeValue);

                    if ($value = $address->item(1)) {
                        $it['DropoffLocation'] .= ', ' . $this->glue($this->mkText($value->nodeValue));
                    }
                }

                if ($value = $datetime->item(0)) {
                    $it['PickupDatetime'] = $this->mkDate($this->mkClear("#@#", trim($value->nodeValue), ', '));
                }

                if ($value = $datetime->item(1)) {
                    $it['DropoffDatetime'] = $this->mkDate($this->mkClear("#@#", trim($value->nodeValue), ', '));
                }

                if ($value = $phones->item(0)) {
                    $it['PickupPhone'] = $this->mkClear("#@#", trim($value->nodeValue), ', ');
                }

                if ($value = $phones->item(1)) {
                    $it['DropoffPhone'] = $this->mkClear("#@#", trim($value->nodeValue), ', ');
                }

                if ($value = $fax->item(0)) {
                    $it['PickupFax'] = $this->mkClear("#@#", trim($value->nodeValue), ', ');
                }

                if ($value = $fax->item(1)) {
                    $it['DropoffFax'] = $this->mkClear("#@#", trim($value->nodeValue), ', ');
                }

                if ($value = $hours->item(0)) {
                    $it['PickupHours'] = $this->glue($this->mkText($value->nodeValue));
                }

                if ($value = $hours->item(1)) {
                    $it['DropoffHours'] = $this->glue($this->mkText($value->nodeValue));
                }

                $it['CarType'] = $this->xNode("//td//*[contains(text(), 'Vehicle Type:')]/ancestor::td[1]/following-sibling::td[2]");

                $it['TotalCharge'] = $this->mkCost($this->re("#\nTotal\s+Estimate[^\d]+([^\n]+)\s+#", $text, 1));

                $it['Currency'] = $this->mkCurrency($this->re("#\s+Prices\s+\(([^\)]+)\)#", $text));

                $taxes = preg_split("#<br\s*/*>#ims", $this->re("#Taxes:\s*(.*?)Total\s+Estimate#ms", $body));
                $taxTotal = 0;

                foreach ($taxes as $tax) {
                    $t = explode(' ', $this->mkText($tax));
                    $taxTotal += $this->mkCost(end($t));
                }

                $it['TotalTaxAmount'] = $taxTotal;

                $taxes = preg_split("#<br\s*/*>#ims", $this->re("#Surcharges:\s*(.*?)Taxes:#ms", $body));
                $fees = [];

                foreach ($taxes as $tax) {
                    $name = trim($this->re("#^(.*?)\s+([^\s]+)$#", $this->mkText($tax)));
                    $cost = $this->mkCost($this->re(2));

                    if (!$name) {
                        continue;
                    }
                    $fees[] = ['Name' => $name, 'Charge' => $cost];
                }

                if ($fees) {
                    $it['Fees'] = $fees;
                }

                if ($this->re("#\nDiscount\s+([^\n]+)#", $text)) {
                    $it['Discount'] = $this->mkCost($this->re(1));
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->xBase($this->http);

        return ((isset($this->reText) && $this->reText) ? preg_match($this->reText, $this->mkText($this->http->Response['body'])) : false)
                || ((isset($this->reHtml) && $this->reHtml) ? preg_match($this->reHtml, $this->http->Response['body']) : false);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

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
