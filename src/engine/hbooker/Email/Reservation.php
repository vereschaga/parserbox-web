<?php

namespace AwardWallet\Engine\hbooker\Email;

class Reservation extends \TAccountCheckerExtended
{
    public $reFrom = "#Confirmations@hotel\.de#i";
    public $reProvider = "#hotel\.de#i";
    public $rePlain = "#Thank\s+you\s+for\s+your\s+booking!.*?Your\s+hotelbooker\.org-Team|Vielen\s+Dank\s+für\s+Ihre\s+Buchung!.*?Ihr\s+hotelbooker\.org-Team#is";
    public $rePlainRange = "";
    public $typesCount = "2";
    public $langSupported = "en, de";
    public $reSubject = "#(?:Your\s+reservation|Ihre\s+Reservierung).*?\s+hotelbooker\.org#i";
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
                    // Parser toggled off as it is duplicate of emailYourReservationChecker.php
                    return null;
                    $email = orval(
                        node("//*[contains(text(), 'Angaben zum Auftraggeber:')]/ancestor-or-self::tr[1]/following-sibling::tr[contains(., 'E-Mail:') or contains(., 'E-mail:')]/td[2]"),
                        node("//*[contains(text(), 'Client information:')]/ancestor-or-self::tr[1]/following-sibling::tr[contains(., 'E-Mail:') or contains(., 'E-mail:')]/td[2]")
                    );
                    $this->parsedValue("userEmail", $email);

                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return cell(["Buchungsnummer", "Reservation Number:"], +1, 0);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return cell(['Name of hotel:', 'Hotelname:'], +1);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $arr = [
                            'CheckIn'  => ['Departure:', 'Abreise:'],
                            'CheckOut' => ['Arrival:', 'Anreise:'],
                        ];

                        foreach ($arr as $key => $value) {
                            $res[$key . 'Date'] = strtotime(str_replace('/', '.', cell($value, +1, 0)));
                        }

                        return $res;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return nice(cell(['Straße:', 'Street:'], +1) . ', ' . cell(['PLZ/Ort:', 'Postal code/City:'], +1));
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return explode("\n", cell(['Telefon', 'Telephone'], +1))[0];
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return explode("\n", cell('Fax:', +1))[0];
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
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return cell(["Personen:", "Persons:"], +1);
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
                        return re('#Cancel\s+by.*|Cancellations are not possible without.*#i');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $subj = node('//td[contains(., "Room") and contains(., "Description:")]/following-sibling::td[1]');

                        if (preg_match('#(.*)\s*--.*?:\s*(.*)#', $subj, $m)) {
                            $res['RoomType'] = $m[1];
                            $res['RoomTypeDescription'] = $m[2];
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
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en", "de"];
    }
}
