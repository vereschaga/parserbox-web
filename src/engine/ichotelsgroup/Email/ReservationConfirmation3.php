<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

class ReservationConfirmation3 extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s+you\s+for\s+choosing\s+.*?InterContinental\s+Hotels\s+Group#ixs";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Your\s+Holiday\s+Inn.*Reservation\s+Confirmation#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#ihg\.#i";
    public $reProvider = "#ihg\.#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "ichotelsgroup/it-1877844.eml, ichotelsgroup/it-2148578.eml, ichotelsgroup/it-2168743.eml, ichotelsgroup/it-2192845.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#Your\s+confirmation\s+number\s+is\s+([\w\-]+)#i');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("//*[contains(text(), 'Hotel Info')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]"));

                        return [
                            'HotelName' => re("#^[^\n]+\s+([^\n]+)\n\s*(.*?)\n\s*([\d\-+\(\)\s]{4,})$#ims", $info),
                            'Address'   => nice(re(2), ','),
                            'Phone'     => nice(re(3)),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(cell("Check-In:")));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(cell("Check-In:"), 2));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*Guest Name\s*:\s*([A-Z /,.]+)(?![a-z]+)#x"));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#(\d+)\s+Adult\(s\),\s+(\d+)\s+Child\(ren\)#i', $text, $m)) {
                            return [
                                'Guests' => $m[1],
                                'Kids'   => $m[2],
                            ];
                        }
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re('#Number\s+of\s+Rooms:\s+(\d+)#i');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return cell("Rate Type:", +1, 0);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('#Canceling\s+your\s+reservation\s+(?:before|after).*#i', $text, $m)) {
                            return implode(' ', $m[0]);
                        }
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $subj = cell('Room Type', +1);

                        if (preg_match('#^(.*)?\s+-\s+(.*)#i', $subj, $m)) {
                            return [
                                'RoomType'            => $m[1],
                                'RoomTypeDescription' => $m[2],
                            ];
                        }
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Total Tax", +1));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(cell("Estimated Total Price", +1), 'Total');
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return cell("Points to be deducted for this stay", +1, 0);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Reservation (Confirmation)#ix", $this->parser->getSubject());
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
