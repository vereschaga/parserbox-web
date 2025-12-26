<?php

namespace AwardWallet\Engine\hertz\Email;

class It1561832 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?hertz#i";
    public $rePlainRange = "";
    public $reHtml = "#(?:<[^>]+>|\s+)*?From\s*:(?:<[^>]+>|\s+)*?hertz#i";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "pt";
    public $typesCount = "1";
    public $reFrom = "#hertz#i";
    public $reProvider = "#hertz#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $upDate = "28.12.2014, 10:04";
    public $crDate = "";
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
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#número de confirmação é\s*:\s*([\d\w\-]+)#i");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//*[
							contains(text(), 'Loja de Retirada:')
						]/ancestor-or-self::td[1]"));

                        return [
                            'PickupLocation' => nice(glue([
                                re("#Loja de Retirada[.:\s]+(.*?)\s+(?:Telefonnummer|Endereço)#ms", $text),
                                glue(re("#\n\s*Endereço:*\s*(.*?)\s+Tipo de loja#ms", $text)),
                            ])),
                            'PickupHours' => re("#\n\s*Horário comercial[:\s]*([^\n]+)#", $text),
                            'PickupPhone' => re("#\n\s*Tel[:\s]*([^\n]+)#", $text),
                            'PickupFax'   => re("#\n\s*Número de fax[:\s]*([^\n]+)#", $text),
                        ];
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $date = clear('#,#', cell(['Retirada', 'Recogida'], 0, +1), ' ');

                        return totime(en(uberDate($date)) . ', ' . uberTime($date));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//*[
							contains(text(), 'Loja de Devolução:')
						]/ancestor-or-self::td[1]"));

                        return [
                            'DropoffLocation' => nice(glue([
                                re("#Loja de Devolução[.:\s]+(.*?)\s+(?:Telefonnummer|Endereço)#ms", $text),
                                glue(re("#\n\s*Endereço:*\s*(.*?)\s+Tipo de loja#ms", $text)),
                            ])),
                            'DropoffHours' => re("#\n\s*Horário comercial[:\s]*([^\n]+)#", $text),
                            'DropoffPhone' => re("#\n\s*Tel[:\s]*([^\n]+)#", $text),
                            'DropoffFax'   => re("#\n\s*Número de fax[:\s]*([^\n]+)#", $text),
                        ];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $date = clear('#,#', cell(['Devolução', 'Devolución'], 0, +1), ' ');

                        return totime(en(uberDate($date)) . ', ' . uberTime($date));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Thanks for Travel+ing at the Speed of\s+([^,\n]*?)\s*,\s*([^\n]+)#"),
                            re("#The Hertz Corporation#i") ? 'Hertz' : null
                        );
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $r = filter(explode("\n", text(xpath("//img[contains(@src, 'vehicles/')]/ancestor-or-self::td[1]/following-sibling::td[1]"))));

                        return [
                            'CarType'  => orval(reset($r), null),
                            'CarModel' => orval(nice(end($r)), null),
                        ];
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        return node("//img[contains(@src, 'vehicles/')]/@src");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Thanks for Travel+ing at the Speed of\s+[^,\n]+,\s*([^\n]+)#"),
                            re("#\n\s*([A-Z\s\d]+)\.\s*Ihre Reservierung wurde storniert#i")
                        );
                    },

                    "PromoCode" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Código da tarifa[:\s]*([\d\w\-]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(cell('Total', +1));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(cell('Total', +1));
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
