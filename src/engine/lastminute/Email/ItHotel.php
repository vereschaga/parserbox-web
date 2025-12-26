<?php

namespace AwardWallet\Engine\lastminute\Email;

class ItHotel extends \TAccountCheckerExtended
{
    public $reFrom = "#bookings@lastminute.com.au#i";
    public $reProvider = "#lastminute.com.au#i";
    public $rePlain = "#Thank you for booking with lastminute.com.au#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#lastminute.com.au Booking Confirmation#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "lastminute/it-1488749.eml, lastminute/it-1488755.eml, lastminute/it-1558410.eml, lastminute/it-1562052.eml, lastminute/it-1666503.eml, lastminute/it-1681602.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "";

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
                        return re("#booking confirmation number:\s+([\w\-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $s = join(' ', nodes("//text()[contains(., 'Hotel')]/ancestor::tr[1]/descendant::td[contains(., 'hotel')]//text()"));
                        $r = '#';
                        $r .= '(?P<HotelName>.*)\s+see hotel details\s+';
                        $r .= '(?P<Address>';
                        $r .= '(?P<AddressLine>.*),\s+(?P<CityName>[\w\s]+)\s+(?P<PostalCode>\d+)\s+(?P<StateProv>\w+)';
                        $r .= '|.*';
                        $r .= ').*\s+';
                        $r .= 'Ph:\s+(?P<Phone>.*)\s+Check in:';
                        $r .= '#';
                        $res = [];

                        if (preg_match($r, $s, $m)) {
                            foreach (['HotelName', 'Address', 'Phone'] as $key) {
                                $res[$key] = nice($m[$key]);
                            }
                            $res['HotelName'] = trim($res['HotelName'], '- ');

                            foreach (['AddressLine', 'CityName', 'PostalCode', 'StateProv'] as $key) {
                                if (isset($m[$key]) && $m[$key]) {
                                    $res['DetailedAddress'][$key] = $m[$key];
                                }
                            }
                        }

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = [];

                        foreach (['CheckIn' => 'Check\s+in', 'CheckOut' => 'Check\s+out'] as $key => $value) {
                            if (preg_match('#' . $value . ':\s+\w+,\s+(\d+)\s+(\w+)\s+(\d+)\s+\w+\s+(\d{1,2}:\d{2})\s+(am|pm)#i', $text, $m)) {
                                [$day, $month, $year, $time, $ampm] = array_slice($m, 1);
                                $res[$key . 'Date'] = strtotime("$day $month $year, $time $ampm");
                            }
                        }

                        return $res;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [node("//span[contains(text(), 'Traveller')]/following-sibling::*[1]")];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $res = [];

                        if (preg_match('#No. of guests:\s+(\d+)\s+adults?(?:,\s+(\d+)\s+child)?#', $text, $m)) {
                            $res['Guests'] = (int) $m[1];

                            if (isset($m[2])) {
                                $res['Kids'] = (int) $m[2];
                            }
                        }

                        return $res;
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#Cancellations and changes.*\n(.*)#"));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#Room type:\s+(.*)#");
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#About the room.*\n.*\n(.*)#"));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(node("//text()[contains(., 'Total GST')]/ancestor::th[1]/following-sibling::td[1]"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $s = node("//text()[contains(., 'Total cost')]/ancestor::*/following-sibling::td[1]");
                        $s = str_replace('$', '', $s);

                        return ['Total' => cost($s), 'Currency' => currency($s)];
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
