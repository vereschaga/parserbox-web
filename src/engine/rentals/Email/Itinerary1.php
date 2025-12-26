<?php

namespace AwardWallet\Engine\rentals\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "rentals/it-2.eml";

    public $xInstance = null;
    private $fromList = ["support@carrentals.com"];
    private $reSubject = "#CarRentals\.com Car Reservation#";
    private $reProvider = "#\@carrentals\.com$#i";

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->reProvider, $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return in_array($headers["from"], $this->fromList) || preg_match($this->reSubject, $headers['subject']);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader("date"));

        $text = $parser->getPlainBody();

        if (empty($text)) {
            $text = $this->mkText($this->http->Response['body']); // text representation
        }
        $it = $this->parseEmail($text);

        return [
            'emailType'  => 'Itinerary1',
            'parsedData' => [
                'Itineraries' => [$it],
            ],
        ];
    }

    public function mkCost($value)
    {
        $value = preg_replace("#,#", '.', $value);
        $value = preg_replace("#[^\d\.]#", '', $value);

        return is_numeric($value) ? number_format($value, 2) : null;
    }

    public function mkDate($date, $format = 'D, M d, Y')
    {
        if ($format == 'D, M d, Y') {
            $check = strtotime($this->glue($this->mkText($date), ' '));

            return $check ? $check : null;
        }

        if (gettype($date) == 'string') {
            // not parsed yet
            return $this->mkDate(date_parse_from_format($format, $this->mkText($date)));
        } else {
            // already parsed
            return mktime($date['hour'], $date['minute'], 0, $date['month'], $date['day'], $date['year']);
        }
    }

    public function mkText($html, $preserveTabs = false, $stringifyCells = true)
    {
        $html = preg_replace("#\h\}#ims", ' ', $html);

        $html = preg_replace("#&nbsp;#ims", " ", $html);
        $html = preg_replace("#&amp;#ims", "&", $html);
        $html = preg_replace("#&quot;#ims", '"', $html);
        $html = preg_replace("#&lt;#ims", '<', $html);
        $html = preg_replace("#&gt;#ims", '>', $html);

        if ($stringifyCells && $preserveTabs) {
            $html = preg_replace("#(</t(d|h)>)\s+#ims", '${1}', $html);

            $html = preg_replace_callback("#(<t(d|h)(\s+|\s+[^>]+|)>)(.*?)(<\/t(d|h)>)#ims", function ($m) {
                return $m[1] . preg_replace("#[\r\n\t]+#ms", ' ', $m[4]) . $m[5];
            }, $html);
        }

        $html = preg_replace("#<(td|th)(\s+|\s+[^>]+|)>#ims", "\t", $html);

        $html = preg_replace("#<(p|tr)(\s+|\s+[^>]+|)>#ims", "\n", $html);
        $html = preg_replace("#</(p|tr)>#ims", "\n", $html);

        $html = preg_replace("#\r\n#ims", "\n", $html);
        $html = preg_replace("#<br(/|)>#ims", "\n", $html);
        $html = preg_replace("#<[^>]+>#ims", ' ', $html);

        if ($preserveTabs) {
            $html = preg_replace("#[ \f\r]+#ims", ' ', $html);
        } else {
            $html = preg_replace("#[\t \f\r]+#ims", ' ', $html);
        }

        $html = preg_replace("#\n\s+#ims", "\n", $html);
        $html = preg_replace("#\s+\n#ims", "\n", $html);
        $html = preg_replace("#\n+#ims", "\n", $html);

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

    public function re($re, $text, $index = 1)
    {
        if (preg_match($re, $text, $m)) {
            return $m[1] ?? $m[0];
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
        $text = preg_replace("#([\w\d]),([\w\d])#ms", '${1}, ${2}', $text);
        $text = preg_replace("#[,\s]+$#ms", '', $text);

        return $text;
    }

    public function mkCurrency($text)
    {
        if (preg_match("#$[\d\.]+#", $text)) {
            return 'USD';
        }

        if (preg_match("#[\d\.]+$#", $text)) {
            return 'USD';
        }

        if (preg_match("#\bUSD\b#i", $text)) {
            return 'USD';
        }

        return null;
    }

    public function arrayTabbed($tabbed)
    {
        $r = [];

        foreach (explode("\n", $tabbed) as $line) {
            if (!$line) {
                continue;
            }
            $arr = [];

            foreach (explode("\t", $line) as $item) {
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

    /*
    public function dump($it, $in = false)
    {
        if (!preg_match("#192\.168\.56\.1#", $_SERVER['SSH_CLIENT'])) return;

        foreach($it as $key => $value){
            if (preg_match("#Date(|time)$#", $key)){
                $it[$key] .= " => ".date("d-M-Y h:m:i A", $it[$key]);
            }
        }
        print_r($it);
    }
    */
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

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseEmail($text)
    {
        // get year (it doesn't exists in dates)
        $it = [];

        $it['Kind'] = "L";

        // Number
        $it['Number'] = $this->re("#Confirmation \#:\s+([^\n]+)\n#ims", $text);

        // PickupDatetime
        $it['PickupDatetime'] = strtotime($this->re("#\sPick\-Up Date/Time:\s([^\n]+)\s+Drop\-Off#ms", $text), $this->date);

        // PickupLocation
        $it['PickupLocation'] = $this->mkNice($this->re("#\sPick\-Up Location:\n((.*\n){1,6})Drop-Off#", $text), ', ');

        // DropoffDatetime
        $it['DropoffDatetime'] = strtotime($this->mkNice($this->re("#\sDrop\-Off Date/Time:\s+(.*?)\nVehicle#ms", $text), ' '), $this->date);

        // DropoffLocation
        $it['DropoffLocation'] = $this->glue($this->mkText($this->re("#Drop-Off Location:\n((.*\n){1,6})Pick-Up#", $text)));

        // RentalCompany
        $it['RentalCompany'] = $this->mkText($this->re("#\nCar Vendor:\s+([^\n]+)\n#ms", $text));

        // CarType
        $it['CarType'] = $this->mkNice($this->re("#\nVehicle Type:\s([^\n]+)\n#ms", $text));

        // RenterName (Driver)
        $it['RenterName'] = $this->mkText($this->re("#\nName:\s+([^\n]+)\n#ms", $text));

        // TotalCharge
        $ChargeText = $this->re("#\nTOTAL ESTIMATED CHARGES:\s+([^\n]+)\n#ms", $text);
        $it['TotalCharge'] = $this->mkCost($ChargeText);

        // Currency
        $it['Currency'] = $this->mkCurrency($ChargeText);

        // TotalTaxAmount
        $it['TotalTaxAmount'] = $this->mkCost($this->re("#\nEstimated taxes and fees:\s+([^\n]+)#ims", $text));

        // Status (cancelled, confirmed)
        if ($this->re("#Thank you for booking#ms", $text)) {
            $it['Status'] = 'confirmed';
        }

        return $it;
    }
}
