<?php

namespace AwardWallet\Engine\hertz\Email;

class It1570475 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?hertz#i', 'blank', '1000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#hertz#i', 'us', ''],
    ];
    public $reProvider = [
        ['#hertz#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "es";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "15.02.2015, 18:45";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "";
    public $re_catcher = "#.*?#";
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
                        return re("#Confirmación es el siguiente:\s*([\d\w\-]+)#i");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//*[contains(text(), 'Oficina de recogida y de devolución')]/ancestor-or-self::td[1]"));
                        $text = clear("#^Oficina de recogida[^\n]+#s", $text);

                        if (!re("#(?:Horario|Número|Tipo)#", $text)) {
                            $text = text(xpath("//*[contains(text(), 'Oficina de recogida')]/ancestor-or-self::tr[1]/following-sibling::tr/td[1]"));
                            $text = clear("#^.*?Oficina de recogida:#ms", $text);
                        }

                        return [
                            'PickupLocation' => nice(glue(re("#^(.*?)\s+Tipo de oficina:#ms", $text))),
                            'PickupHours'    => re("#\n\s*Horario de la Oficina[:\s]*([^\n]+)#", $text),
                            'PickupPhone'    => re("#\n\s*Número de telefono[:\s]*([^\n]+)#", $text),
                            'PickupFax'      => re("#\n\s*Número de fax[:\s]*([^\n]+)#", $text),
                        ];
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $dates = text(xpath("(//*[contains(text(), 'Oficina de recogida')])[1]/ancestor-or-self::tr[1]/following-sibling::tr[1]"));
                        $dates = filter(preg_split("#\s*\n\s*#", clear("#,#", $dates, ' ')));

                        $from = reset($dates);
                        $to = end($dates);

                        return [
                            'PickupDatetime'  => totime(en(uberDate($from)) . ', ' . uberTime($from)),
                            'DropoffDatetime' => totime(en(uberDate($to)) . ', ' . uberTime($to)),
                        ];
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//*[contains(text(), 'Oficina de recogida y de devolución')]/ancestor-or-self::td[1]"));
                        $text = clear("#^Oficina de recogida[^\n]+#s", $text);

                        if (!re("#(?:Horario|Número|Tipo)#", $text)) {
                            $text = text(xpath("//*[contains(text(), 'Oficina de devolución')]/ancestor-or-self::tr[1]/following-sibling::tr/td[2]"));
                            $text = clear("#^.*?Oficina de devolución:\s*#ms", $text);
                        }

                        return [
                            'DropoffLocation' => nice(glue(re("#^(.*?)\s+Tipo de oficina:#ms", $text))),
                            'DropoffHours'    => re("#\n\s*Horario de la Oficina[:\s]*([^\n]+)#", $text),
                            'DropoffPhone'    => re("#\n\s*Número de telefono[:\s]*([^\n]+)#", $text),
                            'DropoffFax'      => re("#\n\s*Número de fax[:\s]*([^\n]+)#", $text),
                        ];
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return nodes("//img[contains(@src, 'hertz.')]/@src") ? 'Hertz' : null;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("//img[contains(@src, 'vehicles/')]/ancestor-or-self::td[1]/following::td[1]"));

                        return [
                            'CarType'  => nice(detach("#(?:^|\n)\s*([^\n]*?\s+o\s+similar)#is", $info)),
                            'CarModel' => nice($info),
                        ];
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        return node("//img[contains(@src, 'vehicles/')]/@src");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#\n\s*([^,]+),\s*Usted se ha registrado con éxito#"),
                            re("#Gracias por viajar a la velocidad de Hertz,\s*([^\n]+)#ix")
                        );
                    },

                    "PromoCode" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Código de la tarifa[:\s]*([\d\w\-]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(cell(['TOTAL ESTIMADO:', 'Cantidad total'], +1));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(cell(['Cargo Total Estimado', 'Cantidad total'], +1));
                    },

                    "ServiceLevel" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Service\s+Type:\s*([^\n]+)#");
                    },

                    "Discounts" => function ($text = '', $node = null, $it = null) {
                        $r = filter(preg_split("#\s*\n\s*#", re("#Your Vehicle.*?\n\s*Discounts:\s*(.*?)\s+(?:Total Estimate|Included in the rates)#ms")));
                        $array = [];

                        foreach ($r as $d) {
                            $items = preg_split('#\s*:\s*:\s*#', $d);

                            if (count($items) == 2) {
                                [$name, $value] = $items;
                                $array[] = ['Code' => $name, 'Name' => $value];
                            } else {
                                break;
                            }
                        }

                        return $array;
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
