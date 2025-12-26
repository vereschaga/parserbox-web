<?php

namespace AwardWallet\Engine\hotels\Email;

class ImportantInformationRegardingYourBooking extends \TAccountCheckerExtended
{
    public $rePlain = "";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Important\s+information\s+regarding\s+your\s+booking\s+with\s+Hotels\.com#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]hotels[.]com#i";
    public $reProvider = "#[@.]hotels[.]com#i";
    public $xPath = "";
    public $mailFiles = "hotels/it-1907206.eml";
    public $pdfRequired = "0";
    public $isAggregator = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath('//td[contains(., "Check-In Date:") and not(.//td)]/ancestor::table[2]');
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation\s+Number:\s+([\w\-]+)#');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $res['HotelName'] = node('.//a[ancestor::tr[1]/following-sibling::tr[1]//img[contains(@src, "stars")]]');
                        $nameForSearch = str_replace('ô', 'o', $res['HotelName']); // needed for correct search
                        $res['Address'] = $nameForSearch;

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-In\s+Date:\s+(.*)#i'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-Out\s+Date:\s+(.*)#i'));
                    },
                ],
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//text()[contains(.,"Hotels.com")]')->length > 0
            && $this->http->XPath->query('//td[contains(text(),"For additional information you can visit our FAQ page here")]')->length > 0;
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
