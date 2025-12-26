<?php

namespace AwardWallet\Engine\hertz\Email;

class It1547912 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?hertz#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $reFrom = "#hertz#i";
    public $reProvider = "#hertz#i";
    public $caseReference = "6681";
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
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Reservierungsnummer lautet:\s*([\d\w\-]+)#i"),
                            re("#Nummer Ihrer Stornierung\s+([\d\w\-]+)#i")
                        );
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//*[
							contains(text(), 'Ort der Anmietung und Ort der Rückgabe') or 
							contains(text(), 'Anmietstation')
						]/ancestor-or-self::td[1]"));

                        return [
                            'PickupLocation' => glue([
                                nice(glue(re("#Anmietstation & Rückgabestation[.\s]+(.*?)\s+(?:Telefonnummer|Adresse)#ms", $text))),
                                re("#\n\s*Adresse:*\s*([^\n]+)#", $text),
                            ]),
                            'PickupHours' => re("#\n\s*Öffnungszeiten[:\s]*([^\n]+)#", $text),
                            'PickupPhone' => re("#\n\s*Telefonnummer[:\s]*([^\n]+)#", $text),
                            'PickupFax'   => re("#\n\s*Fax Nummer[:\s]*([^\n]+)#", $text),

                            // the same in this email
                            'DropoffLocation' => glue([
                                nice(glue(re("#Anmietstation & Rückgabestation[.\s]+(.*?)\s+(?:Telefonnummer|Adresse)#ms", $text))),
                                re("#\n\s*Adresse:*\s*([^\n]+)#", $text),
                            ]),
                            'DropoffHours' => re("#\n\s*Öffnungszeiten[:\s]*([^\n]+)#", $text),
                            'DropoffPhone' => re("#\n\s*Telefonnummer[:\s]*([^\n]+)#", $text),
                            'DropoffFax'   => re("#\n\s*Fax Nummer[:\s]*([^\n]+)#", $text),
                        ];
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $date = clear('#,#', cell('Anmietung', 0, +1), ' ');

                        return totime(en(uberDate($date)) . ', ' . uberTime($date));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $date = clear('#,#', cell('Rückgabe', 0, +1), ' ');

                        return totime(en(uberDate($date)) . ', ' . uberTime($date));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Thanks for Travel+ing at the Speed of\s+([^,\n]+),\s*([^\n]+)#"),
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
                        return re("#\n\s*Tarifcode[:\s]*([\d\w\-]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(cell('Voraussichtliche Kosten', +1));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(cell('Voraussichtliche Kosten', +1));
                    },

                    "ServiceLevel" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Service\s*:\s*([^\n]+)#");
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#wurde storniert#i") ? true : false;
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
        return ["de"];
    }
}
