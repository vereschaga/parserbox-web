<?php

namespace AwardWallet\Engine\taj\Email;

class It1898083 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@tajhotels[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@tajhotels[.]com#i";
    public $reProvider = "#[@.]tajhotels[.]com#i";
    public $xPath = "";
    public $mailFiles = "taj/it-1.eml, taj/it-1898083.eml";
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
                        $conf = re("#Confirmation\s*Number:\s*([\w-]+)#i");

                        if (!$conf) {
                            return;
                        }

                        return [
                            'ConfirmationNumber' => $conf,
                            'Status'             => 'confirmed',
                        ];
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node("//*[contains(text(), 'Thank you for choosing')]/following::span[1]");

                        return nice($name);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re('/Arrival\s*on:\s*(.*?)\s*Arrival\s*Details:/is');
                        $date = uberDateTime($date);

                        return strtotime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re('/Departure\s*on:\s*(.*?)\s*Departure\s*Details:/is');
                        $date = uberDateTime($date);

                        return strtotime($date);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = re("#Hotel\s*Details:\s*(.+?)\s*Tel:#is");

                        return nice($addr);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $tel = re("#Tel:\s*(.*?)\s*Fax:#is");

                        return nice($tel);
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        $fax = re("#Fax:\s*(.*?)\s*Email:#is");

                        return nice($fax);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = re('/Guest\s*Name:\s*(.*?)\s*Taj\s*InnerCircle\s*Number:/is');

                        return [nice($name)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#Number\s*of\s*Persons:\s*(\d+)#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#Number\s*of\s*Rooms:\s*(\d+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $rate = re("#Rate\s*per\s*Room:\s*(.+)\s*Rate\s*Information:#");

                        return nice($rate);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $cancel = re("#(Cancellation\s*Deadline\s*:\s*.+?)\s*Special\s*Requests:#is");

                        return nice($cancel);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $type = re('#Accommodation\s*Type:\s*(.*?)\s*Rate\s*&\s*Payment\s*Details#is');

                        if (preg_match('#(.+?)/(.+)#', $type, $ms)) {
                            return [
                                'RoomType'            => nice($ms[1]),
                                'RoomTypeDescription' => nice($ms[2]),
                            ];
                        }

                        return $type;
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $cost = re("#Total\s*Rate:\s*(.+?)\s*Guarantee\s*Method:#");

                        return [
                            'Cost'     => cost($cost),
                            'Currency' => currency($cost),
                        ];
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $acc = re("#Taj\s*InnerCircle\s*Number:\s*(.*?)\s*Confirmation\s*Number:#");

                        return nice($acc);
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
