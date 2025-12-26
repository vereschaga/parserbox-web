<?php

namespace AwardWallet\Engine\thrifty\Email;

class It2255053 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?thrifty#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#thrifty#i";
    public $reProvider = "#thrifty#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "thrifty/it-2255053.eml, thrifty/it-2268038.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text = $this->setDocument("plain")];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#Confirmation\s*\#\s*:\s*([A-Z\d-]+)#");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $addr = re("#\n\s*Pickup\s+Location:\s*(.*?)\s+Pickup\s+Time#is");

                        return [
                            'PickupPhone'    => detach("#\n\s*([\d\-\(\)\s]+)\s*$#", $addr),
                            'PickupLocation' => nice($addr),
                        ];
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Pickup Time\s*:\s*([^\n]+)#ix")));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $addr = re("#\n\s*Return\s+City:\s*(.*?)\n\s*Return:#is");

                        return [
                            'DropoffPhone'    => detach("#\n\s*([\d\-\(\)\s]+)\s*$#", $addr),
                            'DropoffLocation' => nice($addr),
                        ];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Return\s*:\s*([^\n]+)#ix")));
                    },

                    "DropoffFax" => function ($text = '', $node = null, $it = null) {
                        return re("#Thank you for doing business with ([^\n.;]+)#ix");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Renter's name\s*:\s*([^\n]+)#ix");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#reservation is (\w+)#ix");
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#reservation is cancel+ed#ix") ? true : false;
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
