<?php

namespace AwardWallet\Engine\hertz\Email;

class It5141135 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;

    public $reFrom = "@adac.de";
    public $reSubject = [
        "de"=> "ADAC Mietwagenbuchung",
    ];
    public $reBody = 'Hertz';
    public $reBody2 = [
        "de"=> "Bitte drucken Sie diesen aus",
    ];

    public static $dictionary = [
        "de" => [],
    ];

    public $lang = "de";

    public function parsePdf(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "L";

        // Number
        $it['Number'] = $this->nextText("Booking No.:");
        // TripNumber
        // PickupDatetime
        $it['PickupDatetime'] = strtotime($this->re("#(\d+\.\d+\.\d{4})\s+-\s+\d+\.\d+.\d{4}#", $this->nextText("Rental Period:")) . ',' . $this->nextText("Arrival Time:"));

        // PickupLocation
        $it['PickupLocation'] = $this->pdf->FindSingleNode("//text()[normalize-space(.)='Pick Up:']/ancestor::td[1]/following-sibling::td[string-length(normalize-space(.))>1][1]") . ", " .
                        implode(", ", $this->pdf->FindNodes("//text()[normalize-space(.)='Pick Up:']/ancestor::tr[1]/following-sibling::tr[1]/td[string-length(normalize-space(.))>1]/descendant::text()[normalize-space(.)][position()<3]"));

        // DropoffDatetime
        $it['DropoffDatetime'] = strtotime($this->re("#\d+\.\d+\.\d{4}\s+-\s+(\d+\.\d+.\d{4})#", $this->nextText("Rental Period:")) . ',' . $this->nextText("Return Time:"));

        // DropoffLocation
        $it['DropoffLocation'] = $this->pdf->FindSingleNode("//text()[normalize-space(.)='Drop Off:']/ancestor::td[1]/following-sibling::td[string-length(normalize-space(.))>1][1]") . ", " .
                        implode(", ", $this->pdf->FindNodes("//text()[normalize-space(.)='Drop Off:']/ancestor::tr[1]/following-sibling::tr[1]/td[string-length(normalize-space(.))>1]/descendant::text()[normalize-space(.)][position()<3]"));

        // PickupPhone
        $it['PickupPhone'] = $this->pdf->FindSingleNode("//text()[normalize-space(.)='Pick Up:']/ancestor::tr[1]/following-sibling::tr[1]/td[string-length(normalize-space(.))>1]/descendant::text()[normalize-space(.)][3]");

        // PickupFax
        // PickupHours
        $it['PickupHours'] = $this->pdf->FindSingleNode("//text()[normalize-space(.)='Pick Up:']/ancestor::tr[1]/following-sibling::tr[1]/td[string-length(normalize-space(.))>1]/descendant::text()[normalize-space(.)][4]");

        // DropoffPhone
        $it['DropoffPhone'] = $this->pdf->FindSingleNode("//text()[normalize-space(.)='Drop Off:']/ancestor::tr[1]/following-sibling::tr[1]/td[string-length(normalize-space(.))>1]/descendant::text()[normalize-space(.)][3]");

        // DropoffHours
        $it['DropoffHours'] = $this->pdf->FindSingleNode("//text()[normalize-space(.)='Drop Off:']/ancestor::tr[1]/following-sibling::tr[1]/td[string-length(normalize-space(.))>1]/descendant::text()[normalize-space(.)][4]");

        // DropoffFax
        // RentalCompany
        $it['RentalCompany'] = $this->nextText("Local Partner:");

        // CarType
        $it['CarType'] = $this->re("#(.*?\s+z\.B\.\s+\w)#", $this->nextText("Car Group:"));

        // CarModel
        $it['CarModel'] = $this->re("#.*?\s+z\.B\.\s+\w\s+(.+)#", $this->nextText("Car Group:"));

        // CarImageUrl
        // RenterName
        $it['RenterName'] = $this->nextText("Customer:");

        // PromoCode
        // TotalCharge
        // Currency
        // TotalTaxAmount
        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // ServiceLevel
        // Cancelled
        // PricedEquips
        // Discount
        // Discounts
        // Fees
        // ReservationDate
        $it['ReservationDate'] = strtotime($this->nextText("Issue Date:"));

        // NoItineraries
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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
        $body = $parser->getPlainBody();

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
            return null;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
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
            "#^\w+,\s+(\d+)\s+(\w+)\s+(\d{2})$#",
        ];
        $out = [
            "$1 $2 20$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

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
        $pdfs = $parser->searchAttachmentByName('ADAC_Autovermietung_Voucher_\d+-\d+\.pdf');

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
                $text = implode("<br>", $this->pdf->FindNodes("./descendant::text()[normalize-space(.)]", $node));
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $cols[$left] = $left;
                $grid[$top][$left] = $text;
            }

            ksort($cols);

            // group rows by -8px;
            foreach ($grid as $row=>$c) {
                for ($i = $row - 8; $i < $row; $i++) {
                    if (isset($grid[$i])) {
                        foreach ($grid[$row] as $k=>$v) {
                            $grid[$i][$k] = $v;
                        }
                        unset($grid[$row]);

                        break;
                    }
                }
            }
            // group cols by -20px
            $translate = [];

            foreach ($cols as $left) {
                for ($i = $left - 8; $i < $left; $i++) {
                    if (isset($cols[$i])) {
                        $translate[$left] = $cols[$i];
                        unset($cols[$left]);

                        break;
                    }
                }
            }

            foreach ($grid as $row=>&$c) {
                foreach ($translate as $from=>$to) {
                    if (isset($c[$from])) {
                        $c[$to] = $c[$from];
                        unset($c[$from]);
                    }
                }
                ksort($c);
            }

            ksort($grid);

            $html .= "<table border='1'>";

            foreach ($grid as $row=>$c) {
                $html .= "<tr>";

                foreach ($cols as $col) {
                    $html .= "<td>" . ($c[$col] ?? "&nbsp;") . "</td>";
                }
                $html .= "</tr>";
            }
            $html .= "</table>";
        }
        $this->pdf->setBody($html);
        // echo $html;
        return true;
    }

    private function cell($node, $x = 0, $y = 0)
    {
        $n = count($this->pdf->FindNodes("./preceding-sibling::td", $node)) + 1;

        if ($y > 0) {
            return $this->pdf->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[" . abs($y) . "]/td[" . ($n + $x) . "]", $node);
        } elseif ($y < 0) {
            return $this->pdf->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[" . abs($y) . "]/td[" . ($n + $x) . "]", $node);
        } else {
            return $this->pdf->FindSingleNode("./ancestor::tr[1]/td[" . ($n + $x) . "]", $node);
        }
    }
}
