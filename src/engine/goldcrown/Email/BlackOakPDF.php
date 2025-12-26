<?php

namespace AwardWallet\Engine\goldcrown\Email;

class BlackOakPDF extends \TAccountChecker
{
    public $mailFiles = "goldcrown/it-6588937.eml, goldcrown/it-6619368.eml";
    public $reFrom = "reservations@bwblackoak.com";
    public $reSubject = [
        '#The award winning Best Western PLUS Black Oak#',
    ];

    public $reBody = [
        'en' => ['BLACK OAK', 'www.bestwesternblackoak.com'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];
    private $pdf;
    private $patternFileName = ".*(?:Folio|Statement).*\.pdf";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->tablePdf($parser);

        if (!isset($this->pdf)) {
            return false;
        }

        $textPdf = implode("\n", $this->pdf->FindNodes('//text()'));

        $this->assignLang($textPdf);

        if (stripos($textPdf, 'Statement of Account') !== false) {
            $typeParser = 'Statement';
            $its = $this->parseEmailStatement();
        } else {
            $typeParser = 'Folio';
            $its = $this->parseEmailFolio();
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'BlackOakPDF' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->patternFileName);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            return $this->assignLang($text);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers['subject'])) {
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

    private function parseEmailFolio()
    {
        $it = ['Kind' => 'R'];
        $it['ConfirmationNumber'] = $this->nextCell($this->t('Conf #'));
        $it['HotelName'] = $this->pdf->FindSingleNode("(descendant::tr[1]//text())[1]");
        $it['Address'] = $this->trDown($it['HotelName'], 1);
        $it['Phone'] = $this->nextCell($this->t('Phone'));
        $it['Fax'] = $this->nextCell($this->t('Fax'));
        $it['GuestNames'][] = $this->nextCell('Guest :');
        $it['RoomTypeDescription'] = 'Room Number: ' . $this->nextCell('Room #');

        $tot = $this->getTotalCurrency($this->nextCell('Amount Paid'));

        if (!empty($tot['Total'])) {
            $it['Total'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $it['AccountNumbers'][] = $this->nextCell('Account');

        $dateArr = $this->pdf->FindSingleNode("descendant::tr/td[1][starts-with(.,'Date')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]");
        $dateDep = $this->pdf->FindSingleNode("descendant::tr/td[1][starts-with(.,'Balance')]/ancestor::tr[1]/preceding-sibling::tr[1]/td[1]");

        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateArr, $m)) {
            $dateArr = str_replace(" ", "", preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateArr));
        }

        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateDep, $m)) {
            $dateDep = str_replace(" ", "", preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateDep));
        }

        if ($this->identifyDateFormat($dateArr, $dateDep) === 1) {
            $dateArr = preg_replace("#(\d+)[\/\.\-](\d+)[\/\.\-](\d+)$#", "$2.$1.$3", $dateArr);
            $dateDep = preg_replace("#(\d+)[\/\.\-](\d+)[\/\.\-](\d+)$#", "$2.$1.$3", $dateDep);
        } else {
            $dateArr = preg_replace("#(\d+)[\/\.\-](\d+)[\/\.\-](\d+)$#", "$1.$2.$3", $dateArr);
            $dateDep = preg_replace("#(\d+)[\/\.\-](\d+)[\/\.\-](\d+)$#", "$1.$2.$3", $dateDep);
        }
        $it['CheckInDate'] = strtotime($dateArr);
        $it['CheckOutDate'] = strtotime($dateDep);

        return [$it];
    }

    private function parseEmailStatement()
    {
        $it = ['Kind' => 'R'];
        $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
        $it['HotelName'] = $this->trDown('Statement of Account', 1);
        $it['Address'] = $this->trDown($it['HotelName'], 1);
        $it['Phone'] = $this->nextCell($this->t('Phone'));
        $it['Fax'] = $this->nextCell($this->t('Fax'));
        $it['GuestNames'][] = $this->trDown('Contact:', 1);
        $node = $this->pdf->FindSingleNode("descendant::tr[starts-with(.,'Statement Includes')]/td[1]");

        if (preg_match("#Invoices\s+(.+?\d{4})\s*\-\s*(.+?\d{4})#", $node, $m)) {
            $it['CheckInDate'] = strtotime($m[1]);
            $it['CheckOutDate'] = strtotime($m[2]);
        }

        return [$it];
    }

    private function nextCell($field, $unteelTheEnd = false)
    {
        if ($unteelTheEnd) {
            return implode(" ", $this->pdf->FindNodes("(//text()[starts-with(normalize-space(.),'{$field}')])[1]/ancestor::td[1]/following-sibling::td[not(normalize-space(.)=':')]"));
        } else {
            return $this->pdf->FindSingleNode("(//text()[starts-with(normalize-space(.),'{$field}')])[1]/ancestor::td[1]/following-sibling::td[not(normalize-space(.)=':')][1]");
        }
    }

    private function trDown($field, $num)
    {
        return implode(" ", $this->pdf->FindNodes("(//text()[starts-with(normalize-space(.),'{$field}')])[1]/ancestor::tr[1]/following-sibling::tr[$num]/td[not(normalize-space(.)=':')]"));
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("$", "USD", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

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

    private function identifyDateFormat($date1, $date2)
    {
//    define("DATE_MONTH_FIRST", "1");
//    define("DATE_DAY_FIRST", "0");
//    define("DATE_UNKNOWN_FORMAT", "-1");

        if (preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date1, $m) && preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date2, $m2)) {
            if (intval($m[1]) > 12 || intval($m2[1]) > 12) {
                return 0;
            } elseif (intval($m[2]) > 12 || intval($m2[2]) > 12) {
                return 1;
            } else {
                //try to guess format
                $diff = [];

                foreach (['$3-$2-$1', '$3-$1-$2'] as $i => $format) {
                    $tempdate1 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date1);
                    $tempdate2 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date2);

                    if (($tstd1 = strtotime($tempdate1)) !== false && ($tstd2 = strtotime($tempdate2)) !== false && ($tstd2 - $tstd1 > 0)) {
                        $diff[$i] = $tstd2 - $tstd1;
                    }
                }
                $min = min($diff);

                return array_flip($diff)[$min];
            }
        }

        return -1;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function tablePdf($parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->patternFileName);

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
            return false;
        }
        $this->pdf = clone $this->http;
        $this->pdf->SetEmailBody($html);
        $html = "";

        $pages = $this->pdf->XPath->query("//div[starts-with(@id, 'page')]");

        foreach ($pages as $page) {
            $nodes = $this->pdf->XPath->query(".//p", $page);

            $cols = [];
            $grid = [];

            foreach ($nodes as $node) {
                $text = implode("<br>", $this->pdf->FindNodes(".//text()[normalize-space(.)]", $node));
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $grid[$top][$left] = $text;
            }

            ksort($grid);

            $html .= "<table border='1'>";

            foreach ($grid as $row => $c) {
                ksort($c);
                $html .= "<tr>";

                foreach ($c as $col) {
                    $html .= "<td>" . $col . "</td>";
                }
                $html .= "</tr>";
            }
            $html .= "</table>";
        }
        $this->pdf->SetEmailBody($html);

        return true;
    }
}
