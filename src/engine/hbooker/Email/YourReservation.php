<?php

namespace AwardWallet\Engine\hbooker\Email;

// TODO: similar hbooker:It3714369
class YourReservation extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s+you\s+for\s+your\s+booking!.*?Your\s+hotelbooker\.org-Team|Vielen\s+Dank\s+für\s+Ihre\s+Buchung!.*?Ihr\s+hotelbooker\.org-Team#is";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#(?:Your\s+reservation|Ihre\s+Reservierung).*?\s+hotelbooker\.org#i";
    public $langSupported = "en,de";
    public $typesCount = "1";
    public $reFrom = "#hotelbooker#i";
    public $reProvider = "#hotelbooker#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "hbooker/it-1.eml, hbooker/it-1593577.eml, hbooker/it-1708046.eml, hbooker/it-1708177.eml, hbooker/it-1724610.eml, hbooker/it-1732420.eml, hbooker/it-1788640.eml, hbooker/it-1836278.eml, hbooker/it-2035514.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $email = orval(
                        node("//*[contains(text(), 'Angaben zum Auftraggeber:')]/ancestor-or-self::tr[1]/following-sibling::tr[contains(., 'E-Mail:') or contains(., 'E-mail:')]/td[2]"),
                        node("//*[contains(text(), 'Client information:')]/ancestor-or-self::tr[1]/following-sibling::tr[contains(., 'E-Mail:') or contains(., 'E-mail:')]/td[2]")
                    );

                    $this->parsedValue("userEmail", $email);

                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#[A-Z\-\d]+#", cell(["Buchungsnummer", "Reservation Number:"], +1, 0));
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return cell(["Hotelname:", "Name of hotel:"], +1, 0);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $arr = [
                            'CheckIn'  => ['Arrival:', 'Anreise:'],
                            'CheckOut' => ['Departure:', 'Abreise:'],
                        ];

                        foreach ($arr as $key => $value) {
                            $res[$key . 'Date'] = strtotime(cell($value, +1));
                        }

                        if (!$res['CheckInDate']
                                or !$res['CheckOutDate']
                                or abs($res['CheckInDate'] - $res['CheckOutDate']) > 60 * 60 * 24 * 30) {
                            // Fixing mess with dates cause sometimes d/d/d means day/month/year (e.g. in 1708046),
                            // sometimes month/day/year (e.g. in 2035514)
                            foreach ($arr as $key => $value) {
                                $res[$key . 'Date'] = strtotime(str_replace('/', '.', cell($value, +1)));
                            }
                        }

                        return $res;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*(?:Straße|Street)\s*:\s*([^\n]+)#") . ", " . cell(['PLZ/Ort:', 'Postal code/City:'], +1));
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:Telefon|Telephone)\s*:\s*([^\n]+)#");
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Fax\s*:\s*([^\n]+)#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $regex = '#';
                        $regex .= '(?:Client\s+information|Angaben\s+zum\s+Auftraggeber):\s+';
                        $regex .= '(?:Last\s+Name|Nachname):\s+(.*)\s+';
                        $regex .= '(?:First\s+Name|Vorname):\s+(.*)';
                        $regex .= '#i';

                        if (preg_match($regex, $text, $m)) {
                            return [$m[1] . ' ' . $m[2]];
                        }

                        $firstNames = [];
                        $lastNames = [];

                        re("#\n\s*(?:Nachname|Last Name)\s*:\s*([^\n]+)#", function ($m) use (&$firstNames) {
                            $firstNames[] = $m[1];
                        }, $text);

                        re("#\n\s*(?:Vorname|First Name)\s*:\s*([^\n]+)#", function ($m) use (&$lastNames) {
                            $lastNames[] = $m[1];
                        }, $text);

                        $i = 0;
                        $names = [];

                        foreach ($firstNames as $name) {
                            if (isset($firstNames[$i]) and isset($lastNames[$i])) {
                                $names[trim($firstNames[$i] . ' ' . $lastNames[$i])] = 1;
                            }
                            $i++;
                        }

                        return array_keys($names);
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return cell(["Personen:", "Persons:"], +1, 0);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        //return cell(["Anzahl Zimmer:","No. of Rooms:"], 0,+1);
                        $found = nodes("//*[contains(text(), 'Price per Room:') or contains(text(), 'Anzahl Zimmer:')]/ancestor::tr[1]/following-sibling::tr[1]/td[4]/preceding-sibling::td[2]");
                        $num = 0;

                        foreach ($found as $item) {
                            $num += $item;
                        }

                        return $num;
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            node("//*[contains(text(), 'Preis pro Zimmer:') or contains(text(), 'Durchschnittspreis pro Zimmer/Nacht:')]/ancestor-or-self::td[1]/following-sibling::td[contains(.,'Preis:')]/ancestor::tr[1]/following-sibling::tr[1]/td[last()-1]"),
                            node("//*[contains(text(), 'Price per Room:')]/ancestor-or-self::td[1]/following-sibling::td[contains(.,'Total:')]/ancestor::tr[1]/following-sibling::tr[1]/td[last()-1]")
                        );
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        //return re('#Cancel\s+by.*|Cancellations are not possible without.*|Eine kostenfreie Stornierung ist.*#i');

                        $nodes = nodes("//*[contains(text(), 'Booking and Cancellation Policy:') or (contains(text(), 'Buchungs') and contains(text(), 'und Stornierungsbedingungen:'))]/ancestor-or-self::tr[1]/following-sibling::tr");

                        foreach ($nodes as &$node) {
                            $node = clear("#^[^\d\w]+#", $node);
                        }

                        return glue($nodes);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $subj = node('//td[contains(., "Room") and contains(., "Description:") or contains(., "Zimmerbeschreibung:")]/following-sibling::td[1]');

                        if (preg_match('#(.*)\s*--.*?:\s*(.*)#', $subj, $m)) {
                            $res['RoomType'] = $m[1];
                            $res['RoomTypeDescription'] = $m[2];
                        } elseif (strlen($subj) < 50) {
                            $res['RoomType'] = nice($subj);
                            $res['RoomTypeDescription'] = null;
                        } else {
                            $res['RoomTypeDescription'] = nice($subj);
                            $res['RoomType'] = null;
                        }

                        return $res;
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $costs = nodes("//*[contains(text(), 'Price per Room:')]/ancestor-or-self::td[1]/following-sibling::td[contains(.,'Total:')]/ancestor::tr[1]/following-sibling::tr/td[5]");
                        $total = 0;

                        foreach ($costs as $cost) {
                            $total += cost($cost);
                        }

                        return orval(
                            cost(node("//*[contains(text(), 'Preis pro Zimmer:') or contains(text(), 'Durchschnittspreis pro Zimmer/Nacht:')]/ancestor-or-self::td[1]/following-sibling::td[contains(.,'Preis:')]/ancestor::tr[1]/following-sibling::tr[1]/td[last()]")),
                            $total
                        );
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(orval(
                            node("(//*[contains(text(), 'Preis pro Zimmer:') or contains(text(), 'Durchschnittspreis pro Zimmer/Nacht:')]/ancestor-or-self::td[1]/following-sibling::td[contains(.,'Preis:')]/ancestor::tr[1]/following-sibling::tr[1]/td[last()])[1]"),
                            node("(//*[contains(text(), 'Price per Room:')]/ancestor-or-self::td[1]/following-sibling::td[contains(.,'Total:')]/ancestor::tr[1]/following-sibling::tr[1]/td[last()])[1]")
                        ));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(cell(["Buchungsdatum", "Reservation Date:"], +1, 0));
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
        return ["en", "de"];
    }
}
