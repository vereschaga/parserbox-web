<?php

namespace AwardWallet\Engine\spg\Email;

use AwardWallet\Engine\MonthTranslate;

class FolioPdf extends \TAccountChecker
{
    public $mailFiles = "spg/it-10298031.eml, spg/it-10356818.eml, spg/it-10413648.eml, spg/it-6221517.eml, spg/it-6280375.eml, spg/it-6302035.eml, spg/it-6659199.eml";

    public $reFrom = "@starwoodhotels.com";
    public $reSubject = [
        "en"=> "Your updated and final",
    ];
    public $reBody = ' SPG ';
    public $reBody2 = [
        "en"=> "Page Number",
    ];
    public $pdfPattern = "Folio.*A.*Attachment.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries): void
    {
        $text = $this->text;

        $it = [];
        $it['Kind'] = "R";

        // ConfirmationNumber
        if (!($it['ConfirmationNumber'] = $this->re("#Invoice Nbr\s*:\s*(.+)#", $text)) && $this->re("#(Page Number\s*:\s*\d+\n)#", $text)) {
            $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
        }

        //		$this->logger->debug($text);
        // AccountNumbers
        $number = $this->re("#(?:Club Account|AR Account|Marriott Bonvoy Number)[ ]*:[ ]+(.+)#", $text);

        if ($number) {
            $it['AccountNumbers'] = [$number];
        }

        // Hotel Name
        $it['HotelName'] = $this->re("#(.*?)\n#", $text);

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate(
            $this->re("/Arrive Date[ ]*:[ ]*(.{6,})\n/", $text)
            ?? $this->re("/Folio ID.*\n+[ ]*(.{6,})\n/", $text)
        ));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate(
            $this->re("/Depart Date[ ]*:[ ]*(.{6,})\n/", $text)
            ?? $this->re("/Folio ID.*\n+[ ]*.{6,}\n+[ ]*(.{6,})\n/", $text)
        ));

        if (!$it['CheckInDate'] && !$it['CheckOutDate'] && preg_match("#\n\s+([^\s\d]+-\d+-\d{2}\s+\d+:\d+)\n\s+([^\s\d]+-\d+-\d{2}\s+\d+:\d+)#", $text, $m)) {
            $it['CheckInDate'] = strtotime($this->normalizeDate($m[1]));
            $it['CheckOutDate'] = strtotime($this->normalizeDate($m[2]));
        } elseif (empty($it['CheckInDate']) && empty($it['CheckOutDate']) && preg_match('/\s+(\d+-[A-Z]+-\d{2}\s*(?:\d+:\d+)?)\n\s+(\d+-[A-Z]+-\d{2}\s+(?:\d+:\d+)?)/i', $text, $m)) {
            $it['CheckInDate'] = strtotime($this->normalizeDate($m[1]));
            $it['CheckOutDate'] = strtotime($this->normalizeDate($m[2]));
        }

        // Address
        $it['Address'] = preg_replace('/\s+/', ' ', $this->re('/\n[ ]*([\s\S]*?)\n[ ]*Tel:/', $text));

        // Phone
        $it['Phone'] = $this->re("/^[ ]*Tel[ ]*:[ ]*([+(\d][-. \d)(]{5,}[\d)])(?:[ ]+Fax|$)/m", $text);

        // Fax
        $fax = $this->re("/(?:^| )Fax[ ]*:[ ]*([+(\d][-. \d)(]{5,}[\d)])$/m", $text);

        if ($fax) {
            $it['Fax'] = $fax;
        }

        // GuestNames
        $it['GuestNames'] = [$this->re("#\n(.*?)\s{2,}Page Number#", $text)];

        // Guests
        $guests = $this->re("/(?:^[ ]*|[ ]{2})No\. Of Guest[ ]*:[ ]*(\d{1,3})$/m", $text)
            ?? $this->re("/Folio ID.*(?:\n+[ ]*.{6,}){2}\n+[ ]*(\d{1,3})\n/", $text);

        if ($guests !== null) {
            $it['Guests'] = $guests;
        }

        // RoomTypeDescription
        if (preg_match("/^[ ]*(Room Number[ ]*:[ ]*\d+)$/m", $text, $m)) {
            $it['RoomTypeDescription'] = preg_replace('/[ ]*[:]+[ ]*/', ': ', $m[1]);
        }

        // Total
        // Currency
        $totalPrice = $this->re("/(?:^|\*)[ ]*(?:Total|Total Charges)[ ]+(\d[,.\'\d]*)(?:[ ]{2}|$)/m", $text);

        if ($totalPrice !== null) {
            $it['Total'] = $totalPrice;
            $it['Currency'] = $this->re("/Charges[ ]*\([ ]*([A-Z]{3})[ ]*\)/", $text)
                ?? $this->re("/Charges\/Credits[ ]*\([ ]*([A-Z]{3})[ ]*\)/", $text);
        }

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

        if (stripos($text, $this->reBody) === false && stripos($text, 'Marriott') === false
            && stripos($text, 'Tell us about your stay. www.') === false
            && false === stripos($text, 'When you stay with us, we Go Beyond so you can too with thoughtful service, exceptional experiences and everything you seek when traveling')
        ) {
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
        $prov = null;

        if (false !== stripos($this->text, 'Marriott Bonvoy Number')) {
            $prov = 'marriott';
        }
        $result = [
            'emailType'  => 'FolioPdf' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        if (!empty($prov)) {
            $result['providerCode'] = $prov;
        }

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
    public static function getEmailProviders()
    {
        return ['spg', 'marriott'];
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
        $in = [
            // 01-19-2020 14:15
            '/^(\d{1,2})-(\d{1,2})-(\d{2,4})(?:\s+(\d{1,2}[:]+\d{2}))?$/',
            // 042820 19:49
            '/^(\d{2})(\d{2})(\d{2})(?:\s+(\d{1,2}[:]+\d{2}))?$/',
            // 05162019 14:19
            '/^(\d{2})(\d{2})(\d{4})(?:\s+(\d{1,2}[:]+\d{2}))?$/',
            "#^(\d+)/(\d+)/(\d{2})$#", //06/19/16
            "#^(\d+:\d+)\s+(\d+)/(\d+)/(\d{2})$#", //08:25 06/19/16
        ];
        $out = [
            '$1/$2/$3 $4',
            '$1/$2/20$3 $4',
            '$1/$2/$3 $4',
            "$2.$1.20$3",
            "$3.$2.20$4, $1",
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
}
