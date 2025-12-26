<?php

namespace AwardWallet\Engine\omnihotels\Email;

// TODO: merge with parsers goldpassport/InvoicePDF, carlson/FolioPDF (in favor of goldpassport/InvoicePDF)

class FolioForPDF extends \TAccountChecker
{
    public $mailFiles = "omnihotels/it-22272920.eml, omnihotels/it-6023405.eml, omnihotels/it-6023406.eml";

    public $reFrom = "omnihotels.com";
    public $reBody = [
        'en' => ['Room No', 'Arrival'],
    ];
    public $reSubject = [
        '/folio\s+from\s+the.+(?:\bHotel\b|\bResort\b)/i',
    ];
    public $lang = '';
    public $subject = '';
    public $pdf;
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->subject = $parser->getSubject();

        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                } else {
                    return null;
                }
            }
            $this->pdf->SetBody($html);
        } else {
            return null;
        }

        $this->assignLang($html);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "FolioForPDF",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            $this->logger->debug($text);

            if (stripos($parser->getSubject(), ' Omni ') !== false
                || stripos($text, $this->reFrom) !== false
                || stripos($text, 'Thank you for staying at Omni ') !== false
            ) {
                return $this->assignLang($text);
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
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

    private function parseEmail(): array
    {
        $patterns = [
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        ];

        $text = strip_tags(str_replace('&#160;', ' ', $this->pdf->Response['body']));

        $it = ['Kind' => 'R'];

        $it['ConfirmationNumber'] = $this->re("#Conf\.\s+No\.\s+:\s+([A-Z\d-]+)\s*\n#", $text);

        $it['HotelName'] = $this->re("#folio\s+from\s+the\s+(.+)#", $this->subject);

        $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#" . $this->t('Arrival') . "\s+:\s+(.+?)\s*\n#", $text)));

        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#" . $this->t('Departure') . "\s+:\s+(.+?)\s*\n#", $text)));

        $it['Address'] = implode(' ', $this->pdf->FindNodes("//text()[contains(.,'Total')]/following::b[last()]/following::text()[normalize-space(.) and not(contains(.,'www.omnihotels.com') or contains(.,'Phone')  or contains(.,'Telephone'))]"));

        if (empty($it['Address'])) {
            $it['Address'] = preg_replace('/\s+/', ' ', $this->re("/Thank you for staying at the {$it['HotelName']}\.\s*(.+)Tel\:/us", $text));
        }

        $it['Phone'] = $this->pdf->FindSingleNode("//text()[contains(.,'Total')]/following::b[last()]/following::text()[contains(.,'Phone') or contains(.,'Telephone')]", null, true, "/(?:Phone|Telephone):\s*({$patterns['phone']})(?:\s+Fax|$)/");

        if (empty($it['Phone'])) {
            $it['Phone'] = $this->re("/\bTel\s*:\s*({$patterns['phone']})/i", $text);
        }

        $it['Fax'] = $this->pdf->FindSingleNode("//text()[contains(.,'Total')]/following::b[last()]/following::text()[contains(.,'Phone')]", null, true, "/Phone:\s*.+?\s+Fax\s*:\s*({$patterns['phone']})/");

        if (empty($it['Fax'])) {
            $it['Fax'] = $this->re("/\bFax\s*:\s*({$patterns['phone']})/i", $text);
        }

        if (preg_match("#^\s*(?:.*[\/_].*\s+)*([\w+\-\. ]{5,}?)(?:\s{2,}|\n)#", $text, $m)) {
            $it['GuestNames'][] = $m[1];
        }

        $it['RoomTypeDescription'] = $this->re("#(Room\s+No\.\s+:\s+.+?)\s*\n#", $text);

        $it['Rate'] = $this->re("#Room\s+Rate\s+(.+?)\s*\n#", $text);

        if (preg_match("#\n\s*Total\s*(\d[\d\,\.]*)\s+#", $text, $m)) {
            $it['Total'] = $m[1];
        }

        return [$it];
    }

    private function normalizeDate($date): string
    {
        $in = [
            '#(\d+)-(\d+)-(\d+)#',
        ];
        $out = [
            '$1/$2/$3 00:00',
        ];

        return preg_replace($in, $out, $date);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body): bool
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return preg_replace("#\s+#", " ", $m[$c]);
        }

        return null;
    }
}
