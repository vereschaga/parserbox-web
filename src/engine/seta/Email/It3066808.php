<?php

namespace AwardWallet\Engine\seta\Email;

class It3066808 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.]agenciaseta[.]#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = [
        ['SETA AGENCIAMENTO#i', 'blank', '/1'],
    ];
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]agenciaseta[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]agenciaseta[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "pt";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "18.09.2015, 11:33";
    public $crDate = "18.09.2015, 11:14";
    public $xPath = "";
    public $mailFiles = "seta/it-11096873.eml, seta/it-3066808.eml, seta/it-3069385.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'complex');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return reni('(\w+)  N  Confirmação');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return reni('Para : (.+?) -');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = trim(rew('([\d\/\s]+) Data Entrada'));
                        $date = timestamp_from_format($date, 'd/m/Y|');

                        return $date;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = trim(rew('([\d\/\s]+) Data Saída'));
                        $date = timestamp_from_format($date, 'd/m/Y|');

                        return $date;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return reni('Para : .+? 
						Endereço : (.+?) \n');
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return reni('Tel : ([()\d\s-]+\d)');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = reni('Categoria dos Apartamentos.. : (\w.+?) \n');

                        return [$name];
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return reni('SGL (\d+)');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return reni('(\w.+?) $ Tipo $', $text, 1, 'imu');
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = trim(rew('Data Confirmação  ([\d\s\/]+)'));
                        $date = timestamp_from_format($date, 'd / m / Y|');

                        return $date;
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
        return ["pt"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
