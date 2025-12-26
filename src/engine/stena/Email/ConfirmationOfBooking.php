<?php

namespace AwardWallet\Engine\stena\Email;

class ConfirmationOfBooking extends \TAccountCheckerExtended
{
    public $mailFiles = "stena/it-2694455.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && (
                stripos($headers['from'], 'Peter.Lindahl@t-online.de') !== false
                || stripos($headers['from'], 'info.nl@stenaline.com') !== false)
                && isset($headers['subject']) && (
                stripos($headers['subject'], 'Confirmation of booking no') !== false
                || stripos($headers['subject'], 'Buchungsbestätigung zu Ihrer Stena Line Buchung') !== false
                );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'We wish you a pleasant journey. Welcome aboard!') !== false
                || strpos($parser->getHTMLBody(), 'Bitte legen Sie diese Buchungsbestätigung am Check-in des jeweiligen Hafens vor.') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@stenaline.com') !== false || stripos($from, '@t-online.de') !== false;
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
                        return "C";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return node("//h2[contains(., 'BOOKING REFERENCE:') or contains(., 'BUCHUNGSNUMMER')]/following-sibling::h1");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return orval(cell('Name', +1), cell('Kundenname', +1));
                    },

                    "ShipName" => function ($text = '', $node = null, $it = null) {
                        return orval(cell('Ship', +1), cell('Fähre', +1));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(orval(cell('Total paid', +1), cell('Gesamtpreis:', +1)));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(str_replace('/', '.', orval(cell('Date', +1), cell('Datum:', +1))));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $ports = node("//table[contains(., 'Price breakdown and payments') or contains(., 'Preisinformation')]/following::table[1]/descendant::tr[1]");
                        $portDep = re('#^(.+)\s+-\s+.+$#iu', $ports);
                        $portArr = re('#^.+\s+-\s+(.+)$#iu', $ports);
                        $depDate = trim(orval(cell('Departure', +1), cell('Abfahrt', +1)));
                        $arrDate = trim(orval(cell('Arrival', +1), cell('Ankunft', +1)));

                        return [
                            [
                                'Port'    => $portDep,
                                'DepDate' => strtotime(str_replace('/', '.', preg_replace('/^\w+\s+/', '', $depDate))),
                                'ArrDate' => null,
                            ],
                            [
                                'Port'    => $portArr,
                                'DepDate' => null,
                                'ArrDate' => strtotime(str_replace('/', '.', preg_replace('/^\w+\s+/', '', $arrDate))),
                            ],
                        ];
                    },

                    "TripSegments" => [
                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return $node['Port'];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return $node['DepDate'];
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return $node['ArrDate'];
                        },
                    ],
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
        return ['en', 'de'];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
