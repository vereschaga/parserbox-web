<?php

namespace AwardWallet\Engine\hotels\Email;

class It1878001 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?\"hotels[.]com\"#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]hotels[.]com#i";
    public $reProvider = "#[@.]hotels[.]com#i";
    public $xPath = "";
    public $mailFiles = "hotels/it-1878001.eml";
    public $pdfRequired = "0";
    public $isAggregator = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        if (preg_match("#booking\s*confirmation\s*([\w-]+)\s*-\s*(.+?)\s*-#is", $text, $ms)) {
                            return [
                                'ConfirmationNumber' => $ms[1],
                                'HotelName'          => nice($ms[2]),
                            ];
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re("#Check\s*in\s*(.+?)\s*date#is");

                        return strtotime(uberDateTime($date));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re("#Check\s*out\s*(.+?)\s*date#is");

                        return strtotime(uberDateTime($date));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('/Hotel\s*contact\s*details\s*(.+?)\s*([+]?\d{5,})\s*Check-in/is', $text, $ms)) {
                            return [
                                'Address' => nice($ms[1]),
                                'Phone'   => $ms[2],
                            ];
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('/Occupancy\s*(.+),\s*(\d+)\s*adults/is', $text, $ms)) {
                            $guests = explode(',', $ms[1]);

                            return [
                                'GuestNames' => $guests,
                                'Guests'     => $ms[2],
                            ];
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('/Cancellation\s*(.+?)\s*policy\s*(.+?)\[blank\]/is', $text, $ms)) {
                            return nice("{$ms[1]}. {$ms[2]}");
                        }
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $type = re("#[.]\s*Room\s*(.+?)\s*details#is");

                        return nice($type);
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        $details = re('/\s{2,}details\s*(.+?[.])\s*Room/is');

                        return nice($details);
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $cost = re("#Tax\s*recovery\s*charges\s*and\s*service\s*fees\s*(.+?)Total#is");

                        return cost($cost);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $tot = re('/Total\s*(.+?)Your\s*contact\s*details/is');

                        return total($tot, 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re("#This\s*booking\s*is\s*guaranteed[.]#")) {
                            return 'confirmed';
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

    public function IsEmailAggregator()
    {
        return true;
    }
}
