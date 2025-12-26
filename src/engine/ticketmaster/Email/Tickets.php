<?php

namespace AwardWallet\Engine\ticketmaster\Email;

class Tickets extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "customer_support@ticketmaster.com";
    public $reBody = [
        'en' => ['THIS EMAIL IS NOT YOUR TICKET. YOUR TICKET(S) ARE ATTACHED TO THIS EMAIL', 'EVENT:'],
    ];
    public $reBodyPDF = [
        'en' => ['This is your ticket', 'Ticketmaster'],
    ];
    public $reSubject = [
        'Tickets Attached',
    ];
    public $lang = '';
    public $pdf;
    public $type;
    public $pdfNamePattern = "eTicket.*pdf";
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->type = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                }
            }
            $NBSP = chr(194) . chr(160);
            $this->pdf->SetBody(str_replace($NBSP, ' ', html_entity_decode($html)));
        }

        if (!$this->AssignLang() && isset($this->pdf)) {
            $this->AssignLang($this->pdf->Response['body']);
        }

        $its = $this->parseEmail();

        $class = explode('\\', __CLASS__);
        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($class) . $this->type . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'Ticketmaster')]")->length > 0) {
            return $this->AssignLang();
        }
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            return $this->AssignLang($text);
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
        $it = ['Kind' => 'E'];
        $it['ConfNo'] = CONFNO_UNKNOWN;
        $it['AccountNumbers'][] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Account ID')]", null, true, "#:\s+([A-Z\d]+)#");
        $node = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(.),'EVENT:')]/following::text()[normalize-space(.)][position()<10]"));
        $it['Guests'] = $this->re('#TOTAL TICKETS:\s+(\d+)#', $node);

        if (preg_match("#(.+)\s+AT\s+([^\n]+)\n\s*([^\n]+)\n\s*.+?=(.+?)\s+(?:Apply|Applies)\s*([^\n]+)#si", $node, $m)) {
            $it['Name'] = str_replace("\n", " ", $m[1]);
            $it['Address'] = $m[2];
            $it['StartDate'] = strtotime($this->normalizeDate($m[3]));

            if (!$it['StartDate']) {
                $it['StartDate'] = strtotime($this->normalizeDate($m[5]));
            }
            $tot = $this->getTotalCurrency($m[4]);

            if (!empty($tot['Total'])) {
                if (($c = (int) $it['Guests']) > 0) {
                    $it['TotalCharge'] = $c * $tot['Total'];
                } else {
                    $it['TotalCharge'] = $tot['Total'];
                }
                $it['Currency'] = $tot['Currency'];
            }
            $name = $it['Name'];
            $addr = $it['Address'];
        }
        $accNum = $it['AccountNumbers'];
        $it['EventType'] = EVENT_SHOW;
        $its1[] = $it;

        $its2 = [];

        if (isset($this->pdf)) {
            $xpath = "//text()[starts-with(normalize-space(.),'Present this entire page at the event')]";
            $nodes = $this->pdf->XPath->query($xpath);

            foreach ($nodes as $node) {
                $it = ['Kind' => 'E'];
                $it['ConfNo'] = str_replace(" ", "", $this->pdf->FindSingleNode("./following::text()[starts-with(normalize-space(.),'Section')][1]/following::text()[normalize-space(.)][1]", $node));
                $it['DinerName'] = $this->pdf->FindSingleNode("./following::text()[starts-with(normalize-space(.),'ISSUED TO')][1]", $node, true, "#ISSUED TO\s+(.+)#");
                $it['ReservationDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("./following::text()[starts-with(normalize-space(.),'ISSUED ON')][1]", $node, true, "#ISSUED ON\s+(.+)#")));
                $it['AccountNumbers'] = $accNum;
                $it['Guests'] = 1;

                if (isset($name) && isset($addr)) {
                    $it['Name'] = $name;
                    $it['Address'] = $addr;
                } else {
                    $str = implode("\n", $this->pdf->FindNodes("./following::text()[starts-with(normalize-space(.),'rights')][1]/following::text()[normalize-space(.) and not(starts-with(normalize-space(.),'.') or starts-with(normalize-space(.),'All'))][position()<5]", $node));

                    if (preg_match("#(.+)\s+AT\s+([^\n]+)\n#s", $str, $m)) {
                        $it['Name'] = str_replace("\n", " ", $m[1]);
                        $it['Address'] = $m[2];
                    }
                }

                $it['StartDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("./following::text()[contains(normalize-space(.),'All rights reserved')][1]/preceding::text()[string-length(.)>10][1]", $node)));
                $tot = $this->getTotalCurrency($this->pdf->FindSingleNode("./following::text()[starts-with(normalize-space(.),'Service Fee')][1]", $node, true, "#.+?=(.+?)\s+Apply\s*$#"));

                if (!empty($tot['Total'])) {
                    $it['TotalCharge'] = $tot['Total'];
                    $it['Currency'] = $tot['Currency'];
                }
                $it['EventType'] = EVENT_SHOW;
                $its2[] = $it;
            }
        }

        if (count($its2) > 0 && !empty($its2[0]['ConfNo'])) {
            $this->type = 'PDF';

            return $its2;
        } else {
            $this->type = 'Plain';

            return $its1;
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            //Monday, June 19, 2017 7:00PM
            '#^\s*\S\+\s+(\w+)\s+(\d+),\s+(\d+)\s+(\d+:\d+\s*(?:[ap]m)?)$\s*#i',
            '#(\d+)\/(\d+)\/(\d+)#',
        ];
        $out = [
            '$2 $1 $3 $4',
            '$3-$1-$2',
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

    private function AssignLang($body = null)
    {
        if ($body) {
            if (isset($this->reBodyPDF)) {
                foreach ($this->reBody as $lang => $reBody) {
                    if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }

            return false;
        }

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("$", "USD", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = str_replace(" ", "", $m['t']);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
