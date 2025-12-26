<?php

namespace AwardWallet\Engine\hhonors\Email;

class It1598193 extends \TAccountCheckerExtended
{
    public $reFrom = "";
    public $reProvider = "";
    public $rePlain = "#iPhone.*?@res\.hilton\.com|sign in to your HHonors account at|Hilton Hotel#ims";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "hhonors/it-1598193.eml";
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
                        $cn = re("#Confirmation:\s*([\w\d]+)#");

                        if ($cn == null) {
                            $cn = re("#Confirmation Number:\s*([\w\d]+)#");
                        }

                        return $cn;
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $hn = re("#Hotel Name:\s*(.*?)\s*Hotel Address:#is");

                        if ($hn == null) {
                            $hn = re("#Your Reservation Information:\n\s*(.*?)\n#ims");
                        }

                        if ($hn == null) {
                            $hn = node('(//font[@face="Arial"])[1]');
                        }

                        return $hn;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $ar = strtotime(re("#Arrival:\s*([^\n]+)#ims"));

                        if ($ar == null) {
                            $ar = totime(re("#Arrival Date:\s*(.*?)\n#ims") . ' ' . re("#Check-in Time:\s*(.*?)\n#ims"));
                        }

                        return $ar;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $ar = strtotime(re("#Departure:\s*([^\n]+)#ims"));

                        if ($ar == null) {
                            $ar = totime(re("#Departure Date:\s*(.*?)\n#ims") . ' ' . re("#Check-out Time:\s*(.*?)\n#ims"));
                        }

                        return $ar;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $ha = str_replace("\n", ' ', re("#Hotel Address:\s*(.*?)\s*Hotel Phone:#is"));

                        if ($ha == null) {
                            $ha = str_replace("\n", ' ', re("#Your Reservation Information:\n\s*.*?\n(.*?)\nTel:#ims"));
                        }

                        if ($ha == null) {
                            $ha = str_replace("\n", ' ', node('(//font[@face="Arial"])[1]/ancestor::tr[1]/following-sibling::tr[1]'));
                        }

                        return $ha;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $hp = re("#Hotel Phone:\s*([^\s]+)#is");

                        if ($hp == null) {
                            $hp = re('#Tel: (.*?)\n#');
                        }

                        if ($hp == null) {
                            $hp = node('(//font[@face="Arial"])[1]/ancestor::tr[1]/following-sibling::tr[2]', null, false, '/T:\s*([\d\w-]+)/ims');
                        }

                        return $hp;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $n = re("#Thank you for booking with us,\s*([^\n]*)#");

                        if ($n == null) {
                            $n = re('#Name: (.*?)\n#ims');
                        }

                        return $n;
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $g = re("#Information:.*?Clients:\s* (\d+)#ims");

                        if ($g == null) {
                            $g = re("#Guests:\s*(\d+)#ims");
                        }

                        return $g;
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#Rooms:\s*(\d+)#ims");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#Rate per night:\s*(.*)#i");
                    },

                    "RateType" => function ($text = '', $node = null, $it = null) {
                        return re("#Rate Type:\s*(.*)#i");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#Cancellation Policy:\s*(.*)\s*\[#ims");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return str_replace("\n", ' ', re("#Room Type:\s*(.*?)\s*Preferences:#ims"));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return re("#Total.*?Taxes\s*([\d,.]+)#ims");
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return re("#Total for Stay:\s*([\d,.]+)#is");
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return re("#Total for Stay:\s*[\d,.]+\s*([^\s]+)#is");
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
