<?php

namespace AwardWallet\Engine\seta\Email;

class It3351961 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['Seta Viagens#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
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
    public $upDate = "18.12.2015, 15:25";
    public $crDate = "18.12.2015, 14:55";
    public $xPath = "";
    public $mailFiles = "seta/it-3351961.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = rew('(Localizador da Locadora .+)');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return reni('Localizador da Locadora : (\w+)');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $city = ure("/CIDADE:\s+(\w.+?)\n/i", 1);
                        $place = ure("/LOJA:\s+(\w.+?)\n/i", 1);
                        $addr = ure("/ENDEREÇO:\s+(\w.+?)\n/i", 1);
                        $address = sprintf('%s, %s, %s', $city, $place, $addr);

                        return nice($address);
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $q_date = white('(\d+ \/ \d+ \/ \d+)');
                        $date = ure("/$q_date/isu", 1);
                        $q_time = white('HORA : (\d+ : \d+)');
                        $time = ure("/$q_time/isu", 1);

                        $dt = timestamp_from_format($date, 'd / m / y');
                        $dt = strtotime($time, $dt);

                        return $dt;
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $city = ure("/CIDADE:\s+(\w.+?)\n/i", 2);
                        $place = ure("/LOJA:\s+(\w.+?)\n/i", 2);
                        $addr = ure("/ENDEREÇO:\s+(\w.+?)\n/i", 2);
                        $address = sprintf('%s, %s, %s', $city, $place, $addr);

                        return nice($address);
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $q_date = white('(\d+ \/ \d+ \/ \d+)');
                        $date = ure("/$q_date/isu", 2);
                        $q_time = white('HORA : (\d+ : \d+)');
                        $time = ure("/$q_time/isu", 2);

                        $dt = timestamp_from_format($date, 'd / m / y');
                        $dt = strtotime($time, $dt);

                        return $dt;
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							H. ATEND. :
							tel : (?P<PickupPhone> \w.+?) \n
							fax : (?P<PickupFax> \w.+?) \n
							(?P<PickupHours> .+?)
							H. ATEND. :
						');
                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							H. ATEND. : .+?
							H. ATEND. :
							tel : (?P<DropoffPhone> \w.+?) \n
							fax : (?P<DropoffFax> \w.+?) \n
							(?P<DropoffHours> .+?)
							DATA :	
						');
                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return reni('VEÍCULO : (.+?) \n');
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $name = reni('NOME : (.+?) \n');

                        return $name;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = reni('Total S \/ comissão : (.+?) \/');

                        return total($x);
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        $s = reni('MOEDA : (.+?) \n');

                        return currency($s);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('ESSA CONFIRMAÇÃO')) {
                            return 'confirmed';
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $s = rew('Reserva aceita em : (.+?) por');
                        $date = timestamp_from_format($s, '|d / m / y');

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
