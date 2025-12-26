<?php

namespace AwardWallet\Engine\triprewards\Email;

class HotelRamadaPDF extends \TAccountChecker
{
    public $mailFiles = ""; // +1 bcdtravel(pdf)[en]

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'’<>?~`!@\#$%^&*\[\]=\(\)\-–{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);

            if (stripos($textPdf, 'Ramada Plaza') !== false && stripos($textPdf, 'Confirmation Number') !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach ($parser->searchAttachmentByName('.*pdf') as $pdf) {
            $htmlPdf = pdfHtmlHtmlTable(\PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX));
            $htmlPdf = str_replace(['&#160;', '&nbsp;', '  '], ' ', $htmlPdf);
            $htmlPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'’<>?~`!@\#$%^&*\[\]=\(\)\-{}£¥₣₤₧€\$\|]#imsu", ' ', $htmlPdf);
            $this->pdf = clone $this->http;
            $this->pdf->SetBody($htmlPdf);

            if ($it = $this->parsePdf()) {
                return [
                    'parsedData' => [
                        'Itineraries' => [$it],
                    ],
                    'emailType' => 'HotelRamadaPDF',
                ];
            }
        }

        return null;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function parsePdf()
    {
        $patterns = [
            'date' => '/^(\d{1,2}-[^-\d\s]{3}-\d{2,4})$/',
        ];

        $it = [];
        $it['Kind'] = 'R';

        $it['HotelName'] = $this->pdf->FindSingleNode('//td[starts-with(normalize-space(.),"Thank you for choosing")]', null, true, '/Thank\s+you\s+for\s+choosing\s+(.+?)\s+as\s+you/i');

        $guestNames = $this->pdf->FindSingleNode('//td[starts-with(normalize-space(.),"Guest Name")]/following-sibling::td[string-length(normalize-space(.))>1][1]');

        if ($guestNames) {
            $it['GuestNames'] = explode(',', $guestNames);
        }

        $it['ConfirmationNumber'] = $this->pdf->FindSingleNode('//td[starts-with(normalize-space(.),"Confirmation Number:")]/following-sibling::td[string-length(normalize-space(.))>1][1]', null, true, '/^(\d+)$/');

        $checkInDate = $this->pdf->FindSingleNode('//td[starts-with(normalize-space(.),"Check-in:")]/following-sibling::td[string-length(normalize-space(.))>1][1]', null, true, $patterns['date']);

        if ($checkInDate) {
            $it['CheckInDate'] = strtotime($checkInDate);
        }

        $checkOutDate = $this->pdf->FindSingleNode('//td[starts-with(normalize-space(.),"Check-out:")]/following-sibling::td[string-length(normalize-space(.))>1][1]', null, true, $patterns['date']);

        if ($checkOutDate) {
            $it['CheckOutDate'] = strtotime($checkOutDate);
        }

        $it['Rooms'] = $this->pdf->FindSingleNode('//td[starts-with(normalize-space(.),"Number of Room:")]/following-sibling::td[normalize-space(.)][1]', null, true, '/^(\d{1,3})$/');

        $it['RoomType'] = $this->pdf->FindSingleNode('//td[starts-with(normalize-space(.),"Room Type:")]/following-sibling::td[string-length(normalize-space(.))>1][1]');

        $guestCounts = $this->pdf->FindSingleNode('//td[starts-with(normalize-space(.),"Number of Adult")]/following-sibling::td[string-length(normalize-space(.))>1][1]');

        if (preg_match('/(\d{1,3})\s+ADULT/i', $guestCounts, $matches)) {
            $it['Guests'] = $matches[1];
        }

        if (preg_match('/(\d{1,3})\s+CHILD/i', $guestCounts, $matches)) {
            $it['Kids'] = $matches[1];
        }

        $it['Rate'] = $this->pdf->FindSingleNode('//td[starts-with(normalize-space(.),"Per Room/Night Rate:")]/following::td[string-length(normalize-space(.))>1][1]');

        $xpathFragment = '//td[starts-with(normalize-space(.),"Tel") and contains(.,"Fax")]/ancestor::tr[1]';

        $phoneFax = $this->pdf->FindSingleNode($xpathFragment);

        if (preg_match('/Tel\D*([\d\s]+)/i', $phoneFax, $matches)) {
            $it['Phone'] = $matches[1];
        }

        if (preg_match('/Fax\D*([\d\s]+)/i', $phoneFax, $matches)) {
            $it['Fax'] = $matches[1];
        }

        $address = $this->pdf->FindSingleNode($xpathFragment . '/preceding-sibling::tr[normalize-space(.)][1]');

        if (stripos($address, 'China') !== false) {
            $it['Address'] = $address;
        }

        return $it;
    }
}
