<?php

namespace AwardWallet\Engine\triprewards\Email;

class ReservationConfirmation extends \TAccountCheckerExtended
{
    public $reFrom = "#VdelaCruz@wyndham\.com#i";
    public $reProvider = "#wyndham\.com#i";
    public $rePlain = "#Según\s+sus\s+indicaciones,\s+le\s+confirmo\s+la\s+reserva\s+de\s+la\s+siguiente manera.*?reservas@wyndham\.com#is";
    public $rePlainRange = "/1";
    public $typesCount = "1";
    public $langSupported = "es";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "triprewards/it-1758534.eml, triprewards/it-1746344.eml";
    public $rePDF = "";
    public $rePDFRange = "";
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
                        return cell('CONFIRMACION:', +1);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        if (preg_match('#Web:\s*http://www\.wyndham\.com/hotels/PTYPC/main\.wnt#i', $text)) {
                            $res['HotelName'] = 'Wyndham Garden Panama City';
                        }

                        if (isset($res['HotelName'])) {
                            $res['Address'] = $res['HotelName'];
                        }

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $arr = [
                            'CheckIn' => [
                                'Date' => 'FECHA IN',
                                'Time' => 'Check In', ],
                            'CheckOut' => [
                                'Date' => 'FECHA OUT',
                                'Time' => 'Check Out',
                            ],
                        ];

                        foreach ($arr as $key => $value) {
                            $subj = cell($value['Date'], +1);
                            $regex = '#(\d+)\s+de\s+(\w+)\s+(?:de\s+)?(\d+)#';

                            if (preg_match($regex, $subj, $m)) {
                                $dateStr = $m[1] . ' ' . en($m[2]) . ' ' . $m[3];
                                $timeStr = re('#' . $value['Time'] . '\s+(\d+:\d+\s*[ap]\.m\.)#i');
                                $res[$key . 'Date'] = strtotime($dateStr . ', ' . $timeStr);
                            }
                        }

                        return $res;
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return cell('TARIFA:', +1);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#POLITICAS\s+DE\s+CANCELACIÒN\s+\d+\.\s*(.*)#i'));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return cell('TIPO HAB', +1);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $regex = '#Según\s+sus\s+indicaciones,\s+le\s+confirmo\s+\w+\s+reserva\s+de\s+la\s+siguiente\s+manera#i';

                        if (preg_match($regex, $text)) {
                            return 'Confirmed';
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
        return ["es"];
    }
}
