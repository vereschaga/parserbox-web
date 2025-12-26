<?php

namespace AwardWallet\Engine\tablethotels\Email;

class It1948867 extends \TAccountCheckerExtended
{
    public $rePlain = "#Tablet\s+Inc[.]\s+All\s+rights\s+reserved[.]#i";
    public $rePlainRange = "-500";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@tablethotels[.]com#i";
    public $reProvider = "#[@.]tablethotels[.]com#i";
    public $caseReference = "8602";
    public $xPath = "";
    public $mailFiles = "tablethotels/it-1948867.eml, tablethotels/it-1973369.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    // more selective
                    $q = whiten('Thanks for booking with Tablet');

                    if (!re("#$q#i")) {
                        return;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $q = whiten('CONFIRMATION NUMBER ([\w-]+)');

                        return re("#$q#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $q_name = whiten('and we hope you love (.+?) as much as we do');
                        $name = re("/$q_name/i");

                        $info = node("//*[normalize-space(text()) = 'Hotel']/following::p[1]");
                        $phone = node("//*[normalize-space(text()) = 'Hotel']/following::p[1]/text()[last()]");
                        $addr = between($name, $phone, $info);

                        return [
                            'HotelName' => nice($name),
                            'Address'   => $addr,
                        ];
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $phone = node("//*[normalize-space(text()) = 'Hotel']/following::p[1]/text()[last()]");
                        $phones = preg_split('/\s+/', $phone);

                        if (sizeof($phones) === 2) {
                            return [
                                'Phone' => $phones[0],
                                'Fax'   => $phones[1],
                            ];
                        }

                        return nice($phone);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = node("//*[normalize-space(text()) = 'Guests & Dates']/following::p[1]/span[1]");
                        $names = [nice($name)];
                        $info = node("//*[normalize-space(text()) = 'Guests & Dates']/following::p[1]");

                        $q_dates = whiten("$name (.+?) - (.+?) \d+ Night");

                        if (!preg_match("/$q_dates/is", $info, $ms)) {
                            return $names;
                        }
                        $date1 = $ms[1];
                        $date2 = $ms[2];

                        return [
                            'GuestNames'   => $names,
                            'CheckInDate'  => strtotime($date1),
                            'CheckOutDate' => strtotime($date2),
                        ];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $info = node("//*[normalize-space(text()) = 'Guests & Dates']/following::p[1]");

                        return re('/(\d+)\s*Adult/i', $info);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $pol = nodes("//*[normalize-space(text()) = 'Cancellation Policy']/following-sibling::p");
                        $pol = implode(' ', $pol);

                        return nice($pol);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $x = node("//*[normalize-space(text()) = 'Room Type']/following::p[1]");

                        return nice($x);
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        $x = node("//*[normalize-space(text()) = 'Room Type']/following::p[2]");

                        return nice($x);
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $cost = cell('Subtotal', +1);

                        return [
                            'Cost'     => cost($cost),
                            'Currency' => currency($cost),
                        ];
                    },

                    "EarnedAwards" => function ($text = '', $node = null, $it = null) {
                        $q = whiten("you'll earn (\d+) Tablet Credits,");

                        return re("#$q#i");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $q = whiten('Thanks for booking with Tablet');

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
}
