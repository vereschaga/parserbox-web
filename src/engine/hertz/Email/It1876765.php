<?php

namespace AwardWallet\Engine\hertz\Email;

class It1876765 extends \TAccountCheckerExtended
{
    public $mailFiles = "";

    public $rePlain = "#cancelled your reservation.*?The\s+Hertz#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#hertz#i";
    public $reProvider = "#hertz#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $upDate = "28.12.2014, 00:12";
    public $crDate = "";
    public $xPath = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader('date'));

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'L';
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#cancellation\s+number\s+([A-Z\d\-]+)#");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*Pickup Location & Return Location\s+([^:]*?)\s+Phone\s+Number:\s*#i"), ', ');
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(uberDateTime(cell("Pickup Time", 0, 1)), $this->date);
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return $it['PickupLocation'];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(uberDateTime(cell("Return Time", 0, 1)), $this->date);
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return re('/\n\s*Phone Number\s*:?\s*([^\n]+)/');
                    },

                    "PickupFax" => function ($text = '', $node = null, $it = null) {
                        return re('/\n\s*Fax [Nn]umber\s*:?\s*([^\n]+)/');
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*Hours of Operation\s*:\s*([^:]*?)\s+Location Type:#ims"), ', ');
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        return $it['PickupPhone'];
                    },

                    "DropoffHours" => function ($text = '', $node = null, $it = null) {
                        return $it['PickupHours'];
                    },

                    "DropoffFax" => function ($text = '', $node = null, $it = null) {
                        return $it['PickupFax'];
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#The Hertz Corporation#i");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("/(?:\n|^)\s*([A-z][A-z ]*[A-z])\.?\s*We've\s+cancelled\s+your\s+reservation/i");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#We've (\w+) your reservation#i");
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#cancelled\s+your\s+reservation#i") ? true : false;
                    },
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/Hertz\s+Reservation\s+Cancellation\s+[-A-Z\d]{5,}/i', $headers['subject']);
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
