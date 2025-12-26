<?php

namespace AwardWallet\Engine\spg\Email;

class ReservationConfirmation2 extends \TAccountCheckerExtended
{
    public $rePlain = "#Starwood Hotels & Resorts Worldwide, Inc.#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "es";
    public $typesCount = "1";
    public $reFrom = "#reservassheraton@e-masivos\.com#i";
    public $reProvider = "#e-masivos\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "spg/it-1991086.eml";
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
                        return re('#CONFIRMACION\s+DE\s+RESERVA\s+No\.\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $res['HotelName'] = re('#Nos complace confirmar su proximo alojamiento en el\s+Hotel\s+(.*?)\.#i');
                        $res['Address'] = $res['HotelName'];

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $arr = ['CheckIn' => ['Llegada:', 'Check-In:'], 'CheckOut' => ['Salida:', 'Check-Out:']];

                        foreach ($arr as $key => $value) {
                            $date = cell($value[0], +1);
                            $time = re('#\d+:\d+\s*(?:am|pm)?#i', cell($value[1], +1));

                            if ($date and $time) {
                                $res[$key . 'Date'] = strtotime($date . ', ' . $time);
                            }
                        }

                        return $res;
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return cell('Adultos:', +1);
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return cell('Niños:', +1);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return cell('Cantidad de Hab:', +1);
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return cell('Tarifa:', +1);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#POLITICA\s+DE\s+CANCELACION\s*:\s+(.*?)\s+EARLY\s+CHECK\s+IN#is'));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return cell('Tipo de Habitacion:', +1);
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
