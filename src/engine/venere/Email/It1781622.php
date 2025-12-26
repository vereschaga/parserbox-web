<?php

namespace AwardWallet\Engine\venere\Email;

class It1781622 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@venere[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@]venere[.]com#i";
    public $reProvider = "#[@.]venere[.]com#i";
    public $xPath = "";
    public $mailFiles = "venere/it-1781622.eml, venere/it-1847236.eml, venere/it-1852210.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#reservation\s*code:\s*([\w-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = re('/PROPERTY:\s*(.+?)\s*ADDRESS:/is');

                        return nice($name);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re('/CHECK-IN\s*DATE:\s*(\d+\s*\w+\s*\d+)/i');

                        return strtotime(uberDateTime($date));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re('/CHECK-OUT\s*DATE:\s*(\d+\s*\w+\s*\d+)/i');

                        return strtotime(uberDateTime($date));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = re('/ADDRESS:\s*(.+?)\s*CITY\/PLACE:/is');
                        $city = re('/CITY\/PLACE:\s*(.+?)\s*WEB\s*PAGE:/is');

                        return nice("$city, $addr");
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $phone = re('/TELEPHONE:\s*([+\d-]+)/is');

                        return $phone;
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        $fax = re('/FAX:\s*([+\d-]+)/is');

                        return $fax;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = re('/NAME:\s*(.+)\s*EMAIL\s*ADDRESS:/is');

                        return [nice($name)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#ADULTS:\s*(\d+)#i");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $cancel = node("//h2[contains(text(), 'Cancellation Policy')]/following::*[1]");

                        return nice($cancel);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $type = node("//*[contains(text(), 'CHECK-IN DATE:')]/preceding::div[1]");

                        if (preg_match('/(\d+)\s*(.+)\s*[(]/is', $type, $ms)) {
                            return [
                                'Rooms'    => $ms[1],
                                'RoomType' => nice($ms[2]),
                            ];
                        }

                        return nice($type);
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        $desc = node("//*[contains(text(), 'CHECK-IN DATE:')]/preceding::div[1]");

                        return re('/\s*[(](.+)[)]\s*/', $desc);
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $cost = cell('Sales Tax Excluded', +1);

                        return cost($cost);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $tot = cell('Total Price', +1);

                        return total($tot, 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $pat = preg_replace('/\s+/', '\s*', 'Your booking at (.+?) was successful[.]');
                        $pat = "/$pat/i";

                        if (re($pat)) {
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
}
