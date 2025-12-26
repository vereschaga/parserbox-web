<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

class ReservationConfirmation2 extends \TAccountCheckerExtended
{
    public $rePlain = "#(?:Your Reservaton|A sua reserva).*?InterContinental\s+Hotels\s+Group#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "1000";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en, pt";
    public $typesCount = "1";
    public $reFrom = "#Crowne\s+Plaza\s+Hotels\s+&\s+Resorts|HolidayInn(?:Express)?@reservations\.ihg\.com|Reservations@InterContinental\.com#i";
    public $reProvider = "#ihg\.com|InterContinental\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "ichotelsgroup/it-1664739.eml, ichotelsgroup/it-1858100.eml, ichotelsgroup/it-1858105.eml, ichotelsgroup/it-1896769.eml, ichotelsgroup/it-1941621.eml, ichotelsgroup/it-2017552.eml";
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
                        return re('#(?:Your\s+(?:New\s+)?(?:Confirmation|Cancellation)\s+Number\s+is|O seu número de confirmação é)\s+([\d\w\-]+)#');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $r = filter(nodes("//img[contains(@src, 'hotelimages/')]/ancestor-or-self::td[1]/following-sibling::td[1]//text()"));
                        $hotelName = array_shift($r);
                        $info = implode("\n", $r) . "\n";

                        return [
                            'HotelName' => $hotelName,
                            'Address'   => nice(glue(re("#^(.+?)\n\s*([\d\-\(\)\+.]+)?$#ims", $info))),
                            'Phone'     => re(2),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = [];

                        foreach (['CheckIn' => 'Check-In', 'CheckOut' => 'Check-Out'] as $key => $value) {
                            $subj = re('#' . $value . ':\s+(.*)#i');

                            if (preg_match('#(\d+)\s+(\w+)\s+(\d+)\s+(\d+:\d+\s*(?:am|pm)?)#i', $subj, $m)) {
                                $res[$key . 'Date'] = strtotime($m[1] . ' ' . en($m[2]) . ' ' . $m[3] . ', ' . $m[4]);
                            } elseif (preg_match('#(\d+/\d+/\d+)\s+(\d+:\d+\s*(?:am|pm)?)#i', $subj, $m)) {
                                $res[$key . 'Date'] = strtotime($m[1] . ', ' . $m[2]);
                            }
                        }

                        return $res;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $guests = [cell(['Guest Name', 'Nome do hóspede'], +1)];
                        $subj = cell(['Number of Guests', 'Número de Hóspedes'], +1);

                        if (preg_match('#(\d+)\s+(?:adult\(s\)|Adulto\(s\))\s*(?:(\d+)\s+(?:Child|Criança))?#', $subj, $m)) {
                            $guestsCount = $m[1];
                            $kidsCount = (isset($m[2])) ? $m[2] : null;
                        } else {
                            $guestsCount = count($guests);
                            $kidsCount = null;
                        }

                        return ['GuestNames' => $guests, 'Guests' => $guestsCount, 'Kids' => $kidsCount];
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        $subj = cell(['Number of Rooms', 'Número de Quartos'], +1);

                        if ($subj) {
                            return $subj;
                        }
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        //return implode(', ', nodes("//text()[contains(., 'Room Rate')]/ancestor::td[1]/following-sibling::td"));
                        return nice(implode(" ", nodes("(//text()[contains(., 'Room Rate')]/ancestor::tr[1] | //text()[contains(., 'Room Rate')]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(./td[1])) <= 1])/td[position() >= 2]")));
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return cell(['Cancellation Policy', 'Política de cancelamento'], +1);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return cell(['Room Type', 'Tipo de Quarto'], +1);
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(cell(['Tax:', 'Imposto:'], +1));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $subj = cell(['Total Price', 'Preço total calculado'], +1);

                        return ['Total' => cost($subj), 'Currency' => currency($subj)];
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#Reservation\s+Has\s+Been\s+(\w+)#i'),
                            re('#We\'ve\s+(.*?)\s+Your\s+Reservation#i')
                        );
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#Reservation\s+Has\s+Been\s+Cancelled#i") ? true : null;
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
        return ["en", "pt"];
    }
}
