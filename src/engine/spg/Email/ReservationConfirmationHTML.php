<?php

namespace AwardWallet\Engine\spg\Email;

class ReservationConfirmationHTML extends \TAccountCheckerExtended
{
    public $rePlain = "#Su\s+reserva\s+está\s+confirmada.*?Starwood\s+Hotels\s+&\s+Resorts\s+Worldwide,\s+Inc\.‎#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "es";
    public $typesCount = "1";
    public $reFrom = "#GCCUSTSERVICE@CONFIRM\.STARWOODHOTELS\.COM#i";
    public $reProvider = "#CONFIRM\.STARWOODHOTELS\.COM#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $fnDateFormat = "";
    public $xPath = "";
    public $mailFiles = "";
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
                        return re('#Confirmación:\s+([\w\-]+)#i');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#(.*)\s+(.*)\s+Teléfono:\s+(.*?)\s+Fax:\s+(.*)#', $text, $m)) {
                            return [
                                'HotelName' => nice($m[1]),
                                'Address'   => $m[2],
                                'Phone'     => $m[3],
                                'Fax'       => $m[4],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['CheckIn' => 'Llegada', 'CheckOut' => 'Salida'] as $key => $value) {
                            $subj = cell($value, +1);

                            if (preg_match('#(.*)\s+-\s+(\d+:\d+\s*(?:am|pm))#i', $subj, $m)) {
                                $res[$key . 'Date'] = strtotime($m[1] . ', ' . $m[2]);
                            }
                        }

                        return $res;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [cell('Nombre del Huésped', +1)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return cell('Número de Adultos', +1);
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return cell('Número de Niños', +1);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return cell('Número de Habitaciones', +1);
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re('#.*\s+por\s+noche#i');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $regex = '#Políticas de garantía y cancelación\s+((?s).*?)Servicios especiales para todas las habitacione#i';

                        return nice(re($regex));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $regex = '#Descripción de la Habitación\s+(.*)\s+((?s).*?)\s+Observaciones#i';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'RoomType'            => $m[1],
                                'RoomTypeDescription' => nice($m[2], ', '),
                            ];
                        }
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Su\s+reserva\s+está\s+(.*?)\.#i');
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
