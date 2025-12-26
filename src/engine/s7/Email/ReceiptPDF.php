<?php

namespace AwardWallet\Engine\s7\Email;

class ReceiptPDF extends \TAccountChecker
{
    public $mailFiles = "s7/it-6531442.eml, s7/it-7294184.eml";

    public $reFrom = "s7.ru";

    public $reBody = [
        'ru' => ['Маршрутная квитанция', 'Мое бронирование'],
    ];

    public $reSubject = [
        'Подтверждение оплаты заказа на сайте www.s7.ru',
        'Подтверждение покупки на сайте www.s7.ru',
    ];

    public $lang = '';

    /** @var \HttpBrowser */
    public $pdf;

    public $pdfNamePattern = "eticket.*pdf";

    public static $dict = [
        'ru' => [],
    ];

    private $date;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                } else {
                    return null;
                }
            }
            $NBSP = chr(194) . chr(160);
            $this->pdf->SetBody(str_replace($NBSP, ' ', html_entity_decode($html)));
        } else {
            return null;
        }

        $body = $this->pdf->Response['body'];
        $this->AssignLang($body);
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ReceiptPDF' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (stripos($text, 'S7 Airlines') !== false) {
                return $this->AssignLang($text);
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->pdf->FindSingleNode("(//text()[contains(normalize-space(.),'PNR')]/following::p[normalize-space(.)][1])[1]", null, true, "#[A-Z\d]+#");

        $it['Passengers'] = array_filter(array_unique($this->pdf->FindNodes("//text()[starts-with(normalize-space(.), 'Mr') or starts-with(normalize-space(.), 'Ms') or starts-with(normalize-space(.), 'Mrs') or contains(normalize-space(.), 'Ребенок') or contains(normalize-space(.), 'Child')]", null, "#(.+?)\s*\/#")));

        $it['ReservationDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("(//text()[starts-with(normalize-space(.), 'Дата покупки')]/following::p[normalize-space(.)][1])[1]")));

        if ($it['ReservationDate']) {
            $this->date;
        }

        $it['Status'] = $this->pdf->FindSingleNode("//text()[normalize-space(.)='Статус']/following::p[normalize-space(.)][1]");

        $it['TicketNumbers'] = $this->pdf->FindNodes("//text()[starts-with(normalize-space(.),'Номер билета:')]", null, "#:\s*(\d{12,})\s*$#");

        $tot = $this->getTotalCurrency($this->pdf->FindSingleNode("//text()[starts-with(normalize-space(.),'Итого')]/following::p[normalize-space(.)][1]"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        $flights = array_filter($this->pdf->FindNodes("//p[normalize-space(translate(.,'0123456789','dddddddddd'))='dd:dd']/preceding::p[normalize-space(.)][position()<15]", null, "#^[A-Z\d]{2}\s*\d+$#"));

        $rule = implode(" or ", array_map(function ($s) {
            return "normalize-space(.)='{$s}'";
        }, $flights));
        $xpath = "//p[{$rule}]";

        //		$this->logger->info($xpath);

        $nodes = $this->pdf->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $node = $this->pdf->FindSingleNode(".", $root);

            if (preg_match("#^([A-Z\d]{2})\s*(\d+)$#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $i = 1;
            $node = $this->pdf->FindSingleNode("./following-sibling::p[normalize-space(.)][1]", $root);

            if (stripos($node, "Operated by") !== false) {
                $node = implode("\n", $this->pdf->FindNodes("./following::p[normalize-space(.)][{$i}]//text()", $root));

                if (preg_match("#Operated by\s+(.+)(?:\s+(.+))?#i", $node, $m)) {
                    $seg['Operator'] = $m[1];

                    if (isset($m[2]) && !empty($m[2])) {
                        $seg['Aircraft'] = $m[2];
                    }
                } else {
                    $i++;
                    $seg['Operator'] = $this->pdf->FindSingleNode("./following::p[normalize-space(.)][{$i}]", $root);
                    $i++;
                    $seg['Aircraft'] = $this->pdf->FindSingleNode("./following::p[normalize-space(.)][{$i}]", $root);
                    $i++;
                }
                $i++;
            } elseif ($node = $this->pdf->FindSingleNode("following-sibling::p[1]", $root, true, '/([A-Z\d\s]+)/i')) {
                $i++;
                $seg['Operator'] = $node;
                $i++;

                if ($this->pdf->FindSingleNode("following-sibling::p[" . $i . "]", $root, true, '/\d{1,2} \D+ \d{2,4} \d+:\d+/')) {
                    $i--;
                    $seg['Aircraft'] = $this->pdf->FindSingleNode("following-sibling::p[" . $i . "]", $root);
                } else {
                    $seg['Aircraft'] = $this->pdf->FindSingleNode("following-sibling::p[" . $i . "]", $root);
                }
                $i++;
            }

            $seg['DepDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("./following::p[normalize-space(.)][{$i}]", $root)));

            $i++;

            $seg['DepName'] = $this->pdf->FindSingleNode("./following::p[normalize-space(.)][{$i}]", $root);

            $i++;
            $node = $this->pdf->FindSingleNode("./following-sibling::p[normalize-space(.)][{$i}]", $root);

            if (stripos($node, $this->t("Терминал")) !== false) {
                $seg['DepartureTerminal'] = $node;
                $i++;
            }

            $seg['ArrDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("./following::p[normalize-space(.)][{$i}]", $root)));

            if (empty($seg['ArrDate'])) {
                $i++;
                $seg['ArrDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("./following::p[normalize-space(.)][{$i}]", $root)));
            }

            $i++;

            $seg['ArrName'] = $this->pdf->FindSingleNode("./following::p[normalize-space(.)][{$i}]", $root);

            if (!empty($seg['DepDate']) && !empty($seg['FlightNumber']) && !empty($seg['ArrDate'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $i++;
            $node = $this->pdf->FindSingleNode("./following-sibling::p[normalize-space(.)][{$i}]", $root);

            if (stripos($node, $this->t("Терминал")) !== false) {
                $seg['ArrivalTerminal'] = $node;
                $i++;
            }

            $j = $i + 1;

            $seg['Cabin'] = implode(" ", $this->pdf->FindNodes("./following-sibling::p[normalize-space(.)][position()={$i} or position()={$j}][not(contains(.,' до'))]//text()", $root));

            $it['TripSegments'][] = $seg;
            $it['TripSegments'] = array_map("unserialize", array_unique(array_map("serialize", $it['TripSegments'])));
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $year = '';

        if (!empty($this->date)) {
            $year = date("Y", $this->date);
        }
        $in = [
            '#^(\d+\s*\w+)$#u',
        ];
        $out = [
            '$1 ' . $year,
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        foreach ($this->reBody as $lang => $reBody) {
            if (is_string($reBody) && stripos($body, $reBody) !== false) {
                $this->lang = $lang;

                return true;
            } elseif (is_array($reBody)) {
                foreach ($reBody as $re) {
                    if (stripos($body, $re) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match('/(?<t>[\d\.\,]+)\s*additional\.currency\.(?<c>[A-Z]{3})/', $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = str_replace(" ", "", $m['t']);
            $m['t'] = preg_replace('#,(\d{3})$#', '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
