<?php

namespace AwardWallet\Engine\shangrila\Email;

class ConfirmationPDF extends \TAccountCheckerExtended
{
    public $rePlain = "";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Confirmation\s*\#.*Shangri-La#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#reservations@shangri-la\.com#i";
    public $reProvider = "#shangri-la\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "shangrila/it-1985719.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'text');

                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#CONFIRMATION\s+NUMBER\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Hotel\s+Name\s*:\s+(.*)\s+((?s).*?)\s+Telephone#i', $text, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2], ','),
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Arrival\s+Date\s*:\s+(.*)#i'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Departure\s+Date\s*:\s+(.*)#i'));
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['Phone' => 'Telephone', 'Fax' => 'Fax'] as $key => $value) {
                            $res[$key] = str_replace([' /', '*', '#'], [',', '', ''], re('#\n\s*' . $value . '\s*:\s+(.*)#i'));
                        }

                        return $res;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re('#Guest\s+Name\s*:\s+(.*)#i')];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#No\.\s+of\s+Adult\(s\)\s*:\s+(\d+)\s+/\s+Children:\s+(\d+)#i', $text, $m)) {
                            return [
                                'Guests' => $m[1],
                                'Kids'   => $m[2],
                            ];
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#Cancellation\s+made.*#i');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re('#No\.\s+Room\s+/\s+Room\s+Type\s*:\s+\d+\s+/\s+(.*)#i');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re('#We\s+are\s+delighted\s+to\s+confirm\s+the\s+following\s+reservation#i')) {
                            return 'Confirmed';
                        }
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }
}
