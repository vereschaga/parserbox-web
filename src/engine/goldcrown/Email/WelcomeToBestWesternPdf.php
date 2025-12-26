<?php

namespace AwardWallet\Engine\goldcrown\Email;

class WelcomeToBestWesternPdf extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "ghe.se";
    public $reBody = [
        'en' => ['Welcome to BEST WESTERN', 'Confirmation'],
    ];
    public $reSubject = [
        'Confirmation',
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = "Confirmation.*pdf";
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = text(\PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE))) !== null) {
                    $this->AssignLang($text);

                    $its[] = $this->parseEmail($text);
                } else {
                    return null;
                }
            }
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'WelcomeToBestWesternPdf' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
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

    private function parseEmail($textPDF)
    {
        $it = ['Kind' => 'R'];
        $it['ConfirmationNumber'] = $this->re("#Res\s+no[\s:](.+)#i", $textPDF);
        $it['HotelName'] = $this->re("#Welcome to BEST WESTERN\s+(.+)#i", $textPDF);

        if (!empty($it['HotelName'])) {
            $addr = $this->re("#{$it['HotelName']}\s+(?!Yours\s+sincerely)(.+)#i", $textPDF);

            if (preg_match("#(.+?)\s+([\+\- \d\(\)]+)$#", $addr, $m)) {
                $it['Address'] = $m[1];
                $it['Phone'] = $m[2];
            } else {
                $it['Address'] = $addr;
            }
        }
        $tot = $this->getTotalCurrency($this->re("#Total.+?Price[\s:](.+)#i", $textPDF));

        if (!empty($tot['Total'])) {
            $it['Total'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $it['GuestNames'] = explode("\n", $this->re("#Guestname[\s:]+(.+?)\s+Res\s+no#is", $textPDF));
        $it['Guests'] = $this->re("#Guests[\s:]+(\d+)\s+Adults#i", $textPDF);
        $it['Kids'] = $this->re("#Guests[\s:]+.+?(\d+)\s+Children#i", $textPDF);
        $it['RoomType'] = $this->re("#Roomtype[\s:](.+)#i", $textPDF);
        $it['CancellationPolicy'] = $this->re("#Cancellation\s+rules[\s:]+(.+)#i", $textPDF);
        $it['ReservationDate'] = strtotime($this->re("#Ludvika[\s:]+(.+)#i", $textPDF));
        $it['CheckInDate'] = strtotime($this->re("#Arrival[\s:](.+)#i", $textPDF));
        $it['CheckOutDate'] = strtotime($this->re("#Departure[\s:](.+)#i", $textPDF));

        return $it;
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

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
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
