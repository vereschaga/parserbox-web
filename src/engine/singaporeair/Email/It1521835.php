<?php

namespace AwardWallet\Engine\singaporeair\Email;

class It1521835 extends \TAccountCheckerExtended
{
    public $reFrom = "#singaporeair#i";
    public $reProvider = "#singaporeair#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?singaporeair#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = re("#^(.*?)\s+Additional\s+information#msi", $text);

                    return splitter("#(\n\s*Flight\s+\d+)#", $text);
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#\n\s*Booking\s+reference\s*:\s*([\w\d\-]+)#", $this->text()),
                            re("#itinerary from Singaporeair.com\s*\-\s*([\w\d\-]+)#", $this->text()),
                            re("#Referencia de reserva:\s*([\w\d\-]+)#", $this->text()),
                            re("#Référence de la réservation:\s*([\w\d\-]+)#", $this->text()),
                            re("#Buchungsreferenz:\s*([\w\d\-]+)#", $this->text()),
                            re("#has shared#", $this->text()) ? CONFNO_UNKNOWN : null
                        );
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[
							contains(text(), 'Passengers') or
							contains(text(), 'Pasajeros') or
							contains(text(), 'Passagers') or
							contains(text(), 'Passagiere')
						]/ancestor-or-self::tr[1]/following-sibling::tr");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total\s+Cost\s+([A-Z]{3}\s+[\d,.]+)#", $this->parent()));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total\s+fare\s+([A-Z]{3}\s+[\d,.]+)#", $this->parent()));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Total\s+Cost\s+([A-Z]{3}\s+[\d,.]+)#", $this->parent()));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            cost(re("#\n\s*Airport/government\s+taxes\s+([A-Z]{3}\s+[\d,.]+)#i", $this->parent())),
                            cost(re("#\n\s*Tasas\s*de\s*aeropuerto/administrativas\s+([A-Z]{3}\s+[\d,.]+)#i", $this->parent())),
                            cost(re("#\n\s*Taxes\s*d'aéroport\s*/\s*gouvernementales\s+([A-Z]{3}\s+[\d,.]+)#i", $this->parent())),
                            cost(re("#\n\s*Flughafen[-/\s]*staatliche\s*Steuern\s+([A-Z]{3}\s+[\d,.]+)#i", $this->parent()))
                        );
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#(\n\s*[A-Z\d]{2}\d+\s+\w+)#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\n\s*([A-Z\d]{2})(\d+)#"),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $r = [];
                            $text = clear("#\s*Operated\s*by[^\n]*?#ims", $text);

                            foreach (filter(preg_split("#[ \n]*?\t+[ \n]*?#ms", $text)) as $item) {
                                $r[] = trim($item);
                            }

                            if (count($r) != 7) {
                                return null;
                            }

                            $date = uberDate($this->parent());

                            $dep = strtotime($date . ' ' . clear("#[APM]{2}#", $r[5]));
                            $arr = strtotime($date . ' ' . clear("#[APM]{2}#", $r[6]));

                            if ($dep > $arr) {
                                $arr = strtotime('+1 day', $arr);
                            }

                            return [
                                'DepName'      => $r[1],
                                'ArrName'      => $r[2],
                                'DepDate'      => $dep,
                                'ArrDate'      => $arr,
                                'Stops'        => preg_match("#non-stop#i", $r[3]) ? 0 : null,
                                'Cabin'        => trim(re("#(.*?)\(([A-Z])\)#", $r[4])),
                                'BookingClass' => re(2),
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return uniteAirSegments($it);
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
        return ["en"];
    }
}
