<?php

namespace AwardWallet\Engine\lavoueu\Email;

class It2082189 extends \TAccountCheckerExtended
{
    public $rePlain = "#De:\s+Corporativo\s+-\s+Lávoueu\s+Viagens#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "pt";
    public $typesCount = "1";
    public $reFrom = "#@lavoueuviagens[.]com[.]br#i";
    public $reProvider = "#[@.]lavoueuviagens[.]com[.]br#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "lavoueu/it-2082189.eml";
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
                        $conf = re_white('CONFIRMAÇÃO DE RESERVA No[.] ([\w.]+)');
                        $conf = clear('/[.]/', $conf);

                        return $conf;
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node("//*[contains(text(), 'ENDEREÇO:')]/preceding::span[1]");
                        $name = nice($name);

                        return [
                            'HotelName' => $name,
                            'Address'   => $name,
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = between('CHECK IN:', 'CHECK OUT:');
                        $date = \DateTime::createFromFormat('d/m/Y', $date);
                        $date = $date ? $date->setTime(0, 0)->getTimestamp() : null;

                        return $date;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = between('CHECK OUT:', 'REGIME DE ALIMENTAÇÃO:');
                        $date = \DateTime::createFromFormat('d/m/Y', $date);
                        $date = $date ? $date->setTime(0, 0)->getTimestamp() : null;

                        return $date;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $tel = re_white('TELEFONE: ([()\s\d-]+)');

                        return nice($tel);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [between('NOME DO PAX:', 'CHECK IN:')];
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $rate = node("//*[normalize-space(text()) = 'DIÁRIA']/following::td[6]");
                        $cur = node("//*[normalize-space(text()) = 'DIÁRIA']/following::td[5]");

                        return trim("$rate $cur");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return between(
                            '* PRAZO PARA ALTERAÇÕES E CANCELAMENTO:',
                            '* POLITICA PARA NO SHOW:'
                        );
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $x = node("//*[normalize-space(text()) = 'DIÁRIA']/following::td[8]");

                        return nice($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('CONFIRMAÇÃO DE RESERVA')) {
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
        return ["pt"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
