<?php

namespace AwardWallet\Engine\hotels\Email;

class It2036599 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.]hotels[.]com#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]hotels[.]com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]hotels[.]com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "1";
    public $caseReference = "";
    public $upDate = "30.01.2015, 12:16";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "hotels/it-2036599.eml";
    public $re_catcher = "#.*?#";
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
                        return re("#\n\s*Hotel confirmation number\s*:\s*([A-Z\d\-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node("((//*[contains(text(), 'Hotel summary')]/ancestor::tr[2]/following-sibling::tr[1]//tr[1])[1]/following-sibling::tr[string-length(normalize-space(.))>1][1]//b[1])[1]");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['CheckIn' => 'in', 'CheckOut' => 'out'] as $key => $value) {
                            $s = re('#Check\s+' . $value . ':\s+\w+\s+(\w+-\d+-\d{4})#i');

                            if ($s) {
                                $s = str_replace('-', ' ', $s);
                            }
                            $res[$key . 'Date'] = strtotime($s);
                        }

                        return $res;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Check in:')]/ancestor::tr[1]/td[string-length(normalize-space(.))>1][1]");
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Tel\s*:\s*([\d\-+\(\) ]+)#");
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Fax\s*:\s*([\d\-+\(\) ]+)#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re("#\n\s*Reserved for\s*:\s*([^\n]+)#")];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+(\d+)\s+adult#i");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Cancellation or Change Policy')]/ancestor::tr[1]/following-sibling::tr[1]");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return nice(clear("#Non\-.+#", re("#\n\s*([^\n]+)\s+Special request:#")));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Taxes & Service Fees", +1, 0));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Amount charged for hotel reservation", +1, 0));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(cell("Amount charged for hotel reservation", +1, 0));
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
