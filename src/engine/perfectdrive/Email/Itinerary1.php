<?php

namespace AwardWallet\Engine\perfectdrive\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "perfectdrive/it-1.eml, perfectdrive/it-1383027.eml, perfectdrive/it-1394069.eml, perfectdrive/it-1442249.eml, perfectdrive/it-2.eml, perfectdrive/it-3.eml, perfectdrive/it-4.eml";

    public $xInstance = null;
    public $lastRe = null;

    public function carRentalConfirmation(&$it)
    {
        $this->xBase($this->http); // helper

        $body = $this->http->Response['body']; // full html
        $text = $this->mkText($body); // text representation

        // Number
        if (preg_match("#Confirmation Number:\s+([^\s]+)#", $text, $m)) {
            $it['Number'] = $m[1];
        }

        // Pickup
        if (preg_match("#\nPick-up\n(([^\n]+\n){6})#ms", $text, $m)) {
            $m = explode("\n", $m[1]);
            $it['PickupDatetime'] = strtotime(preg_replace("# at #", "", $m[0]));
            $it['PickupLocation'] = $m[1] . ', ' . $m[2];
            $it['PickupPhone'] = $m[5];
        }

        // Dropoff
        if (preg_match("#\nReturn\n(([^\n]+\n){6})#ms", $text, $m)) {
            $m = explode("\n", $m[1]);
            $it['DropoffDatetime'] = strtotime(preg_replace("# at #", "", $m[0]));
            $it['DropoffLocation'] = $m[3] . ', ' . $m[4];
            $it['DropoffPhone'] = $m[5];
        }

        // PickupFax
        // $it['PickupFax'] = not specified

        // PickupHours
        // $it['PickupHours'] = not specified

        // DropoffHours
        // $it['DropoffHours'] = not specified

        // DropoffFax
        // $it['DropoffFax'] = not specified

        // RentalCompany
        // $it['RentalCompany'] = not specified

        // CarModel & CarType (ex: Mid-Size Economy)
        if (preg_match("#\nCar Information\n([^\n]+)\n#ms", $text, $m)) {
            [$it['CarType'], $it['CarModel']] = explode(" - ", $m[1]);
        }

        // CarImageUrl
        $it['CarImageUrl'] = $this->mkImageUrl($this->xHtml("//img[@alt='Vehicle Image'][1]"));

        // RenterName (Driver)
        if (preg_match("#\nName\n([^\n]+)\n#ms", $text, $m)) {
            $it['RenterName'] = $m[1];
        }

        // PromoCode
        // $it['PromoCode'] = not specified

        // TotalCharge & Currency
        if (preg_match("#\nEstimated Total\n([^ ]+) ([^\n]+)\n#ms", $text, $m)) {
            $it['TotalCharge'] = $this->mkCost($m[1]);
            $it['Currency'] = $m[2];
        }

        // TotalTaxAmount
        if (preg_match("#\nTaxes and Surcharges\n([^ ]+) ([^\n]+)\n#ms", $text, $m)) {
            $it['TotalTaxAmount'] = $this->mkCost($m[1]);
        }

        // AccountNumbers
        // $it['AccountNumbers'] = not specified

        // Status (cancelled, confirmed)
        // ServiceLevel (gold, platinum, etc)
        // $it['ServiceLevel'] = not specified

        // Cancelled
        // PricedEquips (array)
        // $it['PricedEquips'] = not specified

        // Discount
        // $it['Discount'] = not specified

        // Discounts (array)
        // $it['Discounts'] = not specified

        // Fees (array)
        if (isset($it['Currency']) and preg_match("#\n[^\n]+ Fee\s+([^ ]+) {$it['Currency']}\s+#ms", $text, $m)) {
            foreach (explode(" {$it['Currency']}", $m[0]) as $fee) {
                if (preg_match("#^(.*?)\s([^\s]+)$#", $this->mkText($fee), $m)) {
                    if (!isset($it['Fees'])) {
                        $it['Fees'] = [];
                    }
                    $it['Fees'][$m[1]] = $this->mkCost($m[2]);
                }
            }
        }

        // ReservationDate
        // $it['ReservationDate'] = not specified

        // NoItineraries
        // $it['NoItineraries'] = not specified
    }

    public function carRentalReservation(&$it)
    {
        $this->xBase($this->http); // helper

        $body = $this->http->Response['body']; // full html
        $text = $this->mkText($body); // text representation

        // Number
        $it['Number'] = $this->re("#\nConfirmation\s+number\s+([^\n]+)#", $text);

        // Pickup and dropoff common data
        foreach (['Pickup' => 'pick-up', 'Dropoff' => 'return'] as $key => $value) {
            $data = join(' ', $this->http->FindNodes("//*[text() = '" . $value . "']/ancestor::tr[1]/following-sibling::tr"));
            $data .= ' ' . join(' ', $this->http->FindNodes("//*[text() = '" . $value . "']/ancestor::tr[2]/following-sibling::tr[descendant::strong[contains(text(), 'phone')]]"));
            $data = preg_replace('#(\s*Rental counter in terminal\.\s+Cars next to terminal\.\s*)$#', '', $data); // Throw away garbage from $data
            $regex = '#';
            $regex .= '\w+,\s+(?P<Date>\w+\s+\w+,\s+\d+)';
            $regex .= '(?P<Time>\s+\d+:\d+\s*(?:am|pm)?|\s+@\s+\d{4})';
            $regex .= '(?P<Location>.*?)';
            $regex .= '(?:hours\s+(?P<Hours>.*))*\s+';
            $regex .= '(?:phone\s+(?P<Phone>[\d\-\s]+?))*';
            $regex .= '$#si';

            if (preg_match($regex, $data, $m)) {
                $m = array_map('trim', $m);

                $datetimeStr = str_replace(',', '', $m['Date']);

                if (isset($m['Time']) and !empty($m['Time'])) {
                    if (stripos($m['Time'], '@') !== false) {
                        $m['Time'] = preg_replace('#@\s+(\d{2})(\d{2})#', '\1:\2', $m['Time']);
                    }
                    $datetimeStr .= ', ' . $m['Time'];
                }

                $it[$key . 'Datetime'] = strtotime($datetimeStr);

                if ($it[$key . 'Datetime'] == false) {
                    // Sometimes year in letter is wrong (like 0001 in perfectdrive/it-1.eml), fix it
                    $datetimeStr = preg_replace('#\d{4}#', '', $datetimeStr);
                    $it[$key . 'Datetime'] = strtotime($datetimeStr, $this->date);
                }

                $it[$key . 'Location'] = $m['Location'];

                if (isset($m['Phone']) and !empty($m['Phone'])) {
                    $it[$key . 'Hours'] = $m['Hours'];
                }

                if (isset($m['Phone']) and !empty($m['Phone'])) {
                    $it[$key . 'Phone'] = $m['Phone'];
                }
            }
        }

        // PickupFax
        // not specified

        // DropoffFax
        // not specified

        // RentalCompany
        // not specified

        // CarType (ex: Mid-Size Economy)
        //$CarType = $this->xText("//b[contains(text(),'car')]/../following-sibling::span[1]", true);
        //$it['CarType'] = preg_replace("#\n#", ', ', $CarType);
        $it['CarType'] = $this->http->FindSingleNode("(//*[text() = 'car'])[1]/ancestor::node()[1]/following-sibling::node()/text()[1]");

        // CarModel
        $CarModel = $this->xText("//*[text() = 'car']/ancestor::td[1]/following-sibling::td[1]//*[contains(text(),'similar')]/ancestor::td[1]", true);
        $it['CarModel'] = preg_replace("#\n#", ', ', $CarModel);

        // CarImageUrl
        $it['CarImageUrl'] = $this->mkImageUrl($this->xHtml("//*[text() = 'car']/ancestor::td[1]/following-sibling::td[1]//*[contains(text(),'similar')]/ancestor::td[1]//img[1]"));

        // RenterName (Driver)
        if (preg_match("#personal information\n([^\n]+)\n#ims", $text, $m)) {
            $it['RenterName'] = $m[1];
        }

        // PromoCode
        if (preg_match("#\ncoupon\s([^\n]+)\n#ims", $text, $m)) {
            if ($m[1] != 'none') {
                $it['PromoCode'] = $m[1];
            }
        }

        // TotalCharge & Currency
        if (preg_match("#\nrental total ([^\s]+)\n([^\n]+)\n#ims", $text, $m)) {
            $it['TotalCharge'] = $this->mkCost($m[2]);
            $it['Currency'] = $m[1];
        }

        // TotalTaxAmount
        if (preg_match("#\ntaxes\n([^\s]+)\n#ims", $text, $m)) {
            $it['TotalTaxAmount'] = $this->mkCost($m[1]);
        }

        // AccountNumbers
        // $it['AccountNumbers'] = not specified

        // Status (cancelled, confirmed)

        // ServiceLevel (gold, platinum, etc)
        // $it['ServiceLevel'] = not specified

        // Cancelled

        // PricedEquips (array)
        // $it['PricedEquips'] = not specified

        // Discount
        // $it['Discount'] = not specified

        // Discounts (array)
        // $it['Discounts'] = not specified

        // Fees (array)
        // $it['Fees'] = not specified

        // ReservationDate
        // $it['ReservationDate'] = not specified

        // NoItineraries
        // $it['NoItineraries'] = not specified
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("#\@budget(group|)\.com$#i", $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers["from"]) && in_array($headers["from"], ["BudgetConfirmations@budgetgroup.com", "no-reply@budget.com"]))
            || (isset($headers["subject"]) && preg_match("#Budget (Reservation|Rental) (Confirmation|Modification)#", $headers['subject']));
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader("date"));
        $itineraries = [];
        $itineraries['Kind'] = 'L';

        if (preg_match('/YOUR RENTAL IS RESERVED/', $parser->getHTMLBody()) or preg_match("#We've\s+changed\s+your\s+reservation#", $parser->getPlainBody())) {
            $this->carRentalReservation($itineraries);
        } elseif (preg_match('/Reservation Confirmation Number/', $parser->getHTMLBody())) {
            $this->carRentalConfirmation($itineraries);
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$itineraries],
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

    public static function getEmailTypesCount()
    {
        return 2;
    }
}
