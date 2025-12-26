<?php

namespace AwardWallet\Engine\tablethotels\Email;

class It1913454 extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s*you\s*for\s*booking\s*on\s*Tablet\s*Hotels#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#Tablet\s*Hotels#i";
    public $reProvider = "#[@.]tablethotels[.]com#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "tablethotels/it-1913454.eml, tablethotels/it-2167081.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $q = whiten('Thank you for booking on Tablet Hotels');

                    if (!re("#$q#i")) {
                        return;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#Reservation\s*Confirmation\s*\#:\s*([\w-]+)#i");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node("//h3[contains(text(), 'Hotel') or contains(text(), 'HOTEL')]/following::strong[1]");
                        $addr = between("HOTEL $name", 'Tel:');

                        return [
                            'HotelName' => nice($name),
                            'Address'   => nice($addr),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $dates = node("//h3[contains(text(), 'Dates & Guests') or contains(text(), 'DATES & GUESTS')]/following::p[1]");

                        if (preg_match('/\s*(?P<date1>.+?)\s*[-]\s*(?P<date2>.+?)\s*,\s*(?P<year>\d+)/', $dates, $ms)) {
                            $dt1 = "{$ms['date1']}, {$ms['year']}";
                            $dt2 = "{$ms['date2']}, {$ms['year']}";

                            $dt1 = uberDateTime($dt1);
                            $dt2 = uberDateTime($dt2);

                            return [
                                'CheckInDate'  => strtotime($dt1),
                                'CheckOutDate' => strtotime($dt2),
                            ];
                        }
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $tel = re_white('Tel: ([\d-.]+)');

                        return nice($tel);
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        $fax = re("#Fax:\s*(.+?)\s*View#is");

                        return nice($fax);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = node("//h3[contains(text(), 'Billing')]/preceding::p[1]/text()[1]");

                        return [nice($name)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#Night\s*(\d+)\s*Adult#is");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('(\w+.\d+[.]\d+) per (?:day|night)');

                        return nice($x);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $policy = re('/Cancellation\s*Policy:\s*(.+?)\s*Hotel\s*Policy:/is');

                        return nice($policy);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							ROOM & RATE \n
							(?P<RoomType> .+?) \n
							.*?
							(?P<RoomTypeDescription> This room .+?)?
							(?: Rate description | Best Available Rate)
						');

                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $cost = cell('Subtotal', +1);

                        return cost($cost);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $tot = cell('Total (including tax)', +1);

                        return total($tot, 'Total');
                    },

                    "EarnedAwards" => function ($text = '', $node = null, $it = null) {
                        $awards = re("/Upon\s*completion\s*of\s*this\s*stay\s*you\s*will\s*have\s*earned\s*(.+?)\s*that/is");

                        return nice($awards);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $q = whiten('Thank you for booking on Tablet Hotels');

                        if (re("#$q#i")) {
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
        return false;
    }
}
