<?php

namespace AwardWallet\Engine\hiltongvc\Email;

class PDF extends \TAccountCheckerExtended
{
    public $reFrom = "#input@HGVC\.com#i";
    public $reProvider = "#HGVC\.com#i";
    public $rePlain = "#Thank\s+you\s+for\s+your\s+recent\s+reservation.*Hilton#is";
    public $rePlainRange = "2000";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return null; // covered by emailIt2476171Checker
                    $this->confNo = re('#Reservation\s+Confirmation\s+\#([\w\-]+)#');
                    $text = $this->setDocument('#pdf#i', 'text');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return $this->confNo;
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $res = [];
                        $regex = '#';
                        $regex .= '\n(?P<GuestNames>.+)\s+';
                        $regex .= '[\d\-]+\n';
                        $regex .= '[\d,]+\s+';
                        $regex .= '.+';
                        $regex .= '\n\d+\n';
                        $regex .= '(?P<CheckInDate>\w+\s+\d+,\s+\d+)(\s+(?P<CheckInTime>\d+:\d+\s*(?:am|pm)))?\n';
                        $regex .= '(?P<CheckOutDate>\w+\s+\d+,\s+\d+)(\s+(?P<CheckOutTime>\d+:\d+\s*(?:am|pm)))?\n';
                        $regex .= '(?P<HotelName>.+?)\n';
                        $regex .= '(?P<Address>.+?)\n';
                        $regex .= '(?P<Phone>[\d\-]+)\n';
                        $regex .= '(?P<RoomType>.+?)\n';
                        $regex .= '#si';

                        if (preg_match($regex, $text, $m)) {
                            foreach (['CheckIn', 'CheckOut'] as $pref) {
                                $s = $m[$pref . 'Date'];

                                if (isset($m[$pref . 'Time']) && $m[$pref . 'Time']) {
                                    $s = $m[$pref . 'Date'] . ', ' . $m[$pref . 'Time'];
                                }
                                $m[$pref . 'Date'] = strtotime($s);
                            }

                            $m['GuestNames'] = explode(' and ', nice($m['GuestNames']));
                            $m['Address'] = nice($m['Address']);

                            $keys = [
                                'GuestNames',
                                'CheckInDate',
                                'CheckOutDate',
                                'HotelName',
                                'Address',
                                'Phone',
                                'RoomType', ];
                            copyArrayValues($res, $m, $keys);
                        }
                        $res['HotelName'] = clear("#\s*@\s*#", get_or($res, 'HotelName'), ' ');

                        return $res;
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
