<?php

namespace AwardWallet\Engine\marriott\Email;

class It1682043 extends \TAccountCheckerExtended
{
    public $mailFiles = "marriott/it-1682043.eml, marriott/it-2265948.eml, marriott/it-2269090.eml, marriott/it-3793848.eml";

    public function detectEmailByHeaders(array $headers)
    {
        //isset($headers['from']) && stripos($headers['from'], '@marriott.com') !== FALSE &&
        return isset($headers['subject']) && (
                (stripos($headers['subject'], 'Reminder: Your stay at ') !== false)
                || (stripos($headers['subject'], 'Confirmación de la reserva ') !== false)
                || (stripos($headers['subject'], 'Reservierungsbestätigung') !== false)
                || preg_match('/Erinnerung: Ihr Aufenthalt im\s+/u', $headers['subject']));
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return (stripos($parser->getHTMLBody(), 'We have canceled your reservation. Please review and keep for your records.') !== false
                || stripos($parser->getHTMLBody(), 'See what\'s happening during your stay and plan your visit with us') !== false
                || stripos($parser->getHTMLBody(), 'Por favor repase los detalles de su reserva y guárdela para referencias futuras') !== false
                || stripos($parser->getHTMLBody(), 'Bitte prüfen Sie die Details Ihrer Reservierung und behalten Sie sie für Ihre Unterlagen.') !== false
                || stripos($parser->getHTMLBody(), 'Erfahren Sie, welche Events während Ihres Aufenthalts stattfinden,') !== false)
                && stripos($parser->getHTMLBody(), 'MARRIOTT') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@marriott.com') !== false;
    }

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
                        if (!empty(re("#\n\s*(?:CHECK\-IN\s+TIME)\s*([^\n]+)#"))) {//go to Parse
                            return null;
                        }
                        $node = orval(
                            re("#RESERVATION CANCELLED\s+([^\n]+)#"),
                            re("#RESERVATION CONFIRMATION\s*\n([^\n]+)#"),
                            re("#Confirmación de la reserva[:\s]+([A-Z\d\-]+)#ix"),
                            re("#Reservierungsbestätigung:?\s*([\w-]+)#i")
                        );

                        return trim($node);
                    },

                    "ConfirmationNumbers" => function ($text = '', $node = null, $it = null) {
                        return cell("SOCIO DEL PROGRAMA", +1, 0);
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $node = node("//img[contains(@src, '_logo_')]/ancestor::table[1]/following-sibling::table[1]/following-sibling::table[1]//b");

                        if (!$node) {
                            $node = node("//img[contains(@src, '_logo_')]/ancestor::table[1]/following-sibling::table[1]");
                        }

                        if (!$node) {
                            $node = node("//img[contains(@src, 'ico_map.png') or contains(@alt,'Hotel address') or contains(@alt,'Hoteladresse') or contains(@alt,'Dirección del hotel')]/ancestor::table[1]/preceding::table[1]");
                        }

                        return $node;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = en(uberDate(clear("#\bde\b#", re("#\n\s*(?:FECHA DE LLEGADA|CHECK\-IN\s+DATE|Anreisedatum)\s*([^\n]+)#"))));
                        $time = en(uberTime(clear("#\bhs\b#", re("#\n\s*(?:HORA DE LLEGADA|CHECK\-IN\s+TIME|Ankunftszeit)\s*([^\n]+)#"))));

                        return totime($date . " " . $time);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = en(uberDate(clear("#\bde\b#", re("#\n\s*(?:FECHA DE SALIDA|CHECK\-OUT\s+DATE|Abreisedatum)\s*([^\n]+)#"))));
                        $time = en(uberTime(clear("#\bhs\b#", re("#\n\s*(?:HORA DE SALIDA|CHECK\-OUT\s+TIME|Check-Out)\s*([^\n]+)#i"))));

                        return totime($date . " " . $time);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $node = node("(//img[contains(@src, 'ico_map') or contains(@alt,'Hotel address') or contains(@alt,'Hoteladresse') or contains(@alt,'Dirección del hotel')]/ancestor::tr[1]//span[1])[2]");

                        if (!$node) {
                            $node = node("//img[contains(@src, 'ico_map') or contains(@alt,'Hotel address') or contains(@alt,'Hoteladresse') or contains(@alt,'Dirección del hotel')]/ancestor::tr[1]");
                        }

                        $node = clear("#\[.+\]\s*$#", $node);

                        return $node;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return node("//img[contains(@src, 'ico_phone') or contains(@alt,'Telephone number') or contains(@alt,'Telefonnummer') or contains(@alt,'Número de teléfono')]/ancestor::tr[1]");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return orval(re("#\n\s*(?:Estimado/a|Dear|Sehr geehrte\(r\))\s+([^,]+)#"), re('/Für\s+(.+?)\s+Platin/'));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return orval(re("#HUÉSPEDES POR HABITACIÓN\s+(\d+)#"), re('/Gäste pro Zimmer\s+(\d+)\s+(?:Adult|Erwachsene)/'));
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:NÚMERO DE HABITACIONES|Anzahl der Zimmer)\s+(\d+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            cost(re("#([\d.,]+\s+[A-Z]{3})#", node("(//*[contains(text(), 'RATES ARE PER ROOM')]/ancestor::table[1]/following-sibling::table[1]//table[1])[3]//td[2]"))),
                            node("(//*[contains(text(), 'POR NOCHE')]/following::tr[1]//tr[last()])[contains(.,' noche')][last()]/td[last()]")
                        );
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return implode(';', nodes("//*[contains(text(), 'Información de tarifas y cancelaciones') or contains(text(), 'Detaillierte Informationen zu Tarifen und Stornierung')]/following::table[1]//text()[contains(., '•')]/ancestor::tr[1]/td[last()]"));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:Camas:|Zimmertyp)\s*([^\n]+)#");
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(orval(cell("TARIFAS E IMPUESTOS DEL GOBIERNO", +1, 0), cell("Steuern und Abgaben", +1, 0)));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(orval(cell("Total por estancia ", +1, 0), cell("für den Aufenthalt (alle Zimmer)", +1, 0)), 'Total');
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:POR NOCHE|PER NIGHT|Zimmer und Nacht\.)\s+\(([A-Z]{3})\)#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#we have (\w+) your reservation#"),
                            re("#Estamos encantados de ([^\s]+)#ix"),
                            re("#Ihre Reservierung ist (\w+)#u")
                        );
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#we have (\w+) your reservation#") == 'cancelled' ? true : false;
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'es', 'de'];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
