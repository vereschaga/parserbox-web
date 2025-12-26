<?php

namespace AwardWallet\Engine\rentacar\Email;

use AwardWallet\Engine\MonthTranslate;

class ConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "rentacar/it-4980750.eml";

    public $reFrom = "noreply@ehi.com";
    public $reSubject = [
        "fr"=> "Votre confirmation de réservation chez Enterprise Rent-A-Car.",
    ];
    public $reBody = 'Rent-A-Car';
    public $reBody2 = [
        "fr"=> "CAR RENTAL VOUCHER",
    ];
    public $pdfPattern = "ReservationNum\d+.pdf";

    public static $dictionary = [
        "fr" => [],
    ];

    public $lang = "fr";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        $text = substr($text, strpos($text, "Nom du conducteur"));

        $it = [];

        $it['Kind'] = "L";

        // Number
        $it['Number'] = $this->re("#Numéro de réservation :\s+(\d+)#", $text);
        // TripNumber

        // PickupDatetime
        // PickupLocation
        // DropoffDatetime
        // DropoffLocation
        // PickupPhone
        // DropoffPhone
        // PickupHours
        // DropoffHours
        $table = $this->splitCols(preg_replace("#^Départ[^\n]+\n#", "", substr($text, $s = strpos($text, "Départ"), strpos($text, "Type de véhicule") - $s)));

        if (count($table) < 3) {
            $this->http->log("incorrect table parse");

            return;
        }

        foreach ($table as &$col) {
            $col = explode("\n", $col);
        }

        foreach ([1=>'Pickup', 2=>'Dropoff'] as $c=>$pref) {
            // times
            $it[$pref . 'Datetime'] = strtotime($this->normalizeDate(isset($table[$c][$s = array_search("Date de location :", $table[0])]) ? $table[$c][$s] : null));

            // location, phone
            if ($start = array_search("Durée :", $table[0]) && $end = array_search("Horaires d'ouverture", $table[0])) {
                $found = [];

                for ($r = $start + 1; $r < $end; $r++) {
                    if (!isset($table[$c][$r])) {
                        $this->http->log("incorrect rows count([{$c}][{$r}])");

                        return;
                    }
                    $found[] = $table[$c][$r];
                }
                $it[$pref . 'Location'] = trim(str_replace("\n", " ", $this->re("#(.*?)Tél :#ms", implode("\n", $found))));
                $it[$pref . 'Phone'] = $this->re("#Tél :\s*(.+)#", implode("\n", $found));
            }
            // hours
            $it[$pref . 'Hours'] = isset($table[$c][$s = array_search("Horaires d'ouverture", $table[0])]) ? $table[$c][$s] : null;
        }

        // CarType
        if (($s = array_search("Horaires d'ouverture", $table[0])) !== false && isset($table[1][$s + 1])) {
            $it['CarType'] = $table[1][$s + 1];
        }

        // PickupFax
        // DropoffFax
        // RentalCompany
        // CarModel
        // CarImageUrl
        // RenterName
        $it['RenterName'] = $this->re("#Nom du conducteur :\s+(.*?)\s{2,}#", $text);

        // PromoCode
        // TotalCharge
        // Currency
        // TotalTaxAmount
        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled
        // ServiceLevel
        // PricedEquips
        // Discount
        // Discounts
        // Fees
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
        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
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

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePdf($itineraries);
        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
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

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+\s+(\d+)/(\d+)/(\d{4})\s+à\s+(\d+:\d+)$#", //lundi 04/09/2017 à 11:00
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,\s](\d{3})#", "$1", $s));
    }
}
