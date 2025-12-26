<?php

namespace AwardWallet\Engine\hoggrob\Email;

class It1915880 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?HRGWORLDWIDE#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $reFrom = "#HRGWORLDWIDE#i";
    public $reProvider = "#HRGWORLDWIDE#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "hoggrob/it-1915880.eml, hoggrob/it-1926256.eml, hoggrob/it-1926263.eml, hoggrob/it-1937697.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->year = re('#\d{4}#i', $this->parser->getHeader('date'));

                    return xpath("//*[contains(text(), 'Mietwagen') or contains(text(), 'Flug')]/ancestor::table[1]");
                },

                "#^\s*Mietwagen#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Bestätigungsnr[.\s]*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $rows = filter(nodes("//*[contains(text(), 'Mietwagen')]/ancestor::tr[1]/td[2]//text()"));

                        return end($rows);
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(en(re("#\n\s*Abholung ab\s+([^\n]+)#")));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $rows = filter(nodes("//*[contains(text(), 'Mietwagen')]/ancestor::tr[1]/td[2]//text()"));

                        return end($rows);
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(en(re("#\n\s*Abgabe bis\s+([^\n]+)#")));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return node(".//*[contains(text(), 'Mietwagen')]/ancestor::tr[1]/td[2]//b[1]");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Wagenkl[./]+Typ[.\s]+([^\n]+)#");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return cell("Name", +1, 0);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Voraus.Gesamtpreis\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Buchungsstatus\s+([^\n]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(node("//*[contains(text(), 'Reisebestätigung')]/ancestor::tr[1]/following-sibling::tr[1]"));
                    },
                ],

                "#^\s*Flug#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Amadeus\-Code\s+([A-Z\d\-]+)#", $this->text());
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re("#\n\s*Reiseplan\s+für\s+([A-Z/ ]*?)\s+(Kundennummer|Kostenstelle)#", $this->text())];
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Reiseplan\s+für\s+[A-Z/ ]*?\s+Vielfliegernr[:.\s]+([A-Z\d\-]+)#", $this->text());
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*IHR FLUGPREIS IST\s*:\s*([^\n]+)#", $this->text()));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return cell("Status", +1, 0);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(node("//*[contains(text(), 'Reisebestätigung')]/ancestor::tr[1]/following-sibling::tr[1]"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$node];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $rows = nodes("(.//*[contains(text(), 'Flug')])[1]/ancestor::tr[1]/td[2]//text()");

                            return uberAir(end($rows));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepCode' => re("#\s+\d+\s+\w+\s+([A-Z]{3})\s*\-\s*([A-Z]{3})\s+{$it['AirlineName']}{$it['FlightNumber']}#", $this->text()),
                                'ArrCode' => re(2),
                            ];
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return clear("#\s+Terminal\s+\w+#", node(".//tr[contains(.,'von')][1]/td[2]"));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $s = re("#\n\s*von.*?\n\s*Datum/Uhrzeit\s*([^\n]+)#ism");

                            if (preg_match('#(\d+\s+\w+)\s+(\d+:\d+)#i', $s, $m)) {
                                $s = $m[1] . ' ' . $this->year . ', ' . $m[2];

                                return strtotime($s);
                            }
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return clear("#\s+Terminal\s+\w+#", cell("Ankunft", +1, 0));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $s = re("#\n\s*Ankunft.*?\n\s*Datum/Uhrzeit\s*([^\n]+)#ism");

                            if (preg_match('#(\d+\s+\w+)\s+(\d+:\d+)#i', $s, $m)) {
                                $s = $m[1] . ' ' . $this->year . ', ' . $m[2];

                                return strtotime($s);
                            }
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return cell("Flugzeugtyp", +1, 0);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return [
                                'Cabin'        => re("#^(.*?)\s+\(([A-Z])\)#", cell("Buchungsklasse", +1, 0)),
                                'BookingClass' => re(2),
                            ];
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return cell("Flugdauer", +1, 0);
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return cell("Stops", +1, 0);
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true, true);
                },
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
