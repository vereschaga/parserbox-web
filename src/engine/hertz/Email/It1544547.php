<?php

namespace AwardWallet\Engine\hertz\Email;

class It1544547 extends \TAccountCheckerExtended
{
    public $reFrom = "#hertz#i";
    public $reProvider = "#hertz#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?hertz#i";
    public $typesCount = "1";
    public $langSupported = "pt";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "hertz/it-1544547.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$this->setDocument("plain")];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmação da reserva\s*\#\s*([A-Z\d]+)#");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(glue(re("#Loja de Retirada[\s:]+(.*?)\s+Tipo de loja#ms")));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(glue(re("#Loja de Devolução[\s:]+(.*?)\s+Tipo de loja#ms")));
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return re("#Loja de Retirada.*?\n\s*Horário comercial:\s*([^\n]+)#ms");
                    },

                    "DropoffHours" => function ($text = '', $node = null, $it = null) {
                        return re("#Loja de Devolução.*?\n\s*Horário comercial:\s*([^\n]+)#ms");
                    },

                    "DropoffFax" => function ($text = '', $node = null, $it = null) {
                        return re("#Loja de Devolução.*?\n\s*Número de fax[:\s]*([^\n]+)#ms");
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#Loja de Devolução.*?\n\s*Tel[:\s]*([^\n]+)#ms");
                    },

                    "PickupFax" => function ($text = '', $node = null, $it = null) {
                        return re("#Loja de Retirada.*?\n\s*Número de fax[:\s]*([^\n]+)#ms");
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#Loja de Retirada.*?\n\s*Tel[:\s]*([^\n]+)#ms");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $date = clear('#,#', re("#\n\s*Retirada[:\s]+([^\n]+)#"), ' ');

                        return totime(en(uberDate($date), 'pt') . ', ' . uberTime($date));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $date = clear('#,#', re("#\n\s*Devolução[:\s]+([^\n]+)#"), ' ');

                        return totime(en(uberDate($date), 'pt') . ', ' . uberTime($date));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return [
                            'CarModel' => re("#\n\s*Veículo\s+([^\n]+)\s+([^\n]+)#"),
                            'CarType'  => re(2),
                        ];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total\s+([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Total\s+([^\n]+)#"));
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
}
