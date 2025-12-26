<?php

namespace AwardWallet\Engine\spg\Email;

class It5040960 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;

    public $reFrom = "confirmacion@fourpointsmonterrey.com.mx";
    public $reSubject = [
        "en"=> "HOTEL FOUR POINTS",
    ];
    public $reBody = 'FOUR POINTS BY';
    public $reBody2 = [
        "es"=> "Llegada",
    ];

    public static $dictionary = [
        "es" => [],
    ];

    public $lang = "es";

    public function parsePdf(&$itineraries)
    {
        $text = implode("\n", $this->pdf->FindNodes("/descendant::text()"));

        $it = [];

        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->re("#SU NÚMERO DE CONFIRMACIÓN:\s+(\w+)#", $text);

        // TripNumber
        // ConfirmationNumbers

        // Hotel Name
        $it['HotelName'] = preg_replace("#\s+#", " ", $this->re("#próxima estancia en el Hotel\s+(.*?),#ms", $text));

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#Llegada\s+(\d+-\d+-\d{2}\s+\d+\.\d+\s+[ap]m)#", $text)));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#Salida\s+(\d+-\d+-\d{2}\s+\d+\.\d+\s+[ap]m)#", $text)));

        // Address
        if (!empty($it['HotelName'])) {
            $it['Address'] = $this->pdf->FindSingleNode("(//text()[starts-with(normalize-space(.), '" . $it['HotelName'] . "') and contains(., 'Tel:')])[1]", null, true, "#" . $it['HotelName'] . "(.*?)Col\.#");
        }

        // DetailedAddress

        // Phone
        $it['Phone'] = trim($this->re("#Tel:\s+([\d\s\(\)]+)#", $text));

        // Fax
        $it['Fax'] = trim($this->re("#Fax:\s+([\d\s\(\)]+)#", $text));

        // GuestNames
        $it['GuestNames'] = array_filter([$this->re("#Estimado\s+(.*?),#", $text)]);

        // Guests
        $it['Guests'] = $this->re("#Número de Huéspedes\s+(\d+)\s+Adulto#", $text);

        // Kids
        $it['Kids'] = $this->re("#Número de Huéspedes\s+\d+\s+Adulto\s+/\s+(\d+)\s+Niños#", $text);

        // Rooms
        $it['Rooms'] = $this->re("#Número de habitaciónes\s+(\d+)#", $text);

        // Rate
        // RateType
        // CancellationPolicy
        // RoomType
        // RoomTypeDescription
        // Cost
        // Taxes
        // Total
        $it['Total'] = preg_replace("#[.,](\d{3})#", "$1", $this->re("#Total de Estancia\s+([\d\.,]+)#ms", $text));

        // Currency
        $it['Currency'] = $this->re("#Total de Estancia\s+[\d\.,]+\s+([A-Z]{3})#ms", $text);

        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled
        // ReservationDate
        // NoItineraries
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['subject'],$headers['from'])) {
            return false;
        }

        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($body = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
            return false;
        }

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        if (!$this->tablePdf($parser)) {
            return false;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->pdf->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }
        $this->parsePdf($itineraries);

        $result = [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->pdf->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[string-length(.)>1][1]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)-(\d+)-(\d{2})\s+(\d+)\.(\d+)\s+([ap]m)$#",
        ];
        $out = [
            "$1.$2.20$3, $4:$5",
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function tablePdf($parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
            return false;
        }
        $this->pdf = clone $this->http;
        $this->pdf->SetBody($html);
        $html = "";

        $pages = $this->pdf->XPath->query("//div[starts-with(@id, 'page')]");

        foreach ($pages as $page) {
            $nodes = $this->pdf->XPath->query(".//p", $page);

            $cols = [];
            $grid = [];

            foreach ($nodes as $node) {
                $text = $this->pdf->FindSingleNode(".", $node);
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $grid[$top][$left] = $text;
            }

            ksort($grid);

            $html .= "<table border='1'>";

            foreach ($grid as $row=>$c) {
                ksort($c);
                $html .= "<tr>";

                foreach ($c as $col) {
                    $html .= "<td>" . $col . "</td>";
                }
                $html .= "</tr>";
            }
            $html .= "</table>";
        }
        $this->pdf->setBody($html);

        return true;
    }
}
