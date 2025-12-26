<?php

namespace AwardWallet\Engine\monarch\Email;

class It3581309 extends \TAccountCheckerExtended
{
    public $mailFiles = "monarch/it-4379717.eml, monarch/it-4431777.eml, monarch/it-4456892.eml, monarch/it-5100935.eml, monarch/it-5932191.eml, monarch/it-8598265.eml, monarch/it-8631814.eml, monarch/it-8698040.eml, monarch/it-8764936.eml, monarch/it-8770254.eml"; // +1 bcdtravel(html)[en]

    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?monarch#i', 'us', ''],
    ];
    public $reHtml = "www.monarch.co.uk";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]monarch#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]monarch#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "it, es, de, en";
    public $typesCount = "4";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "";
    public $crDate = "";
    public $xPath = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public $lang = '';

    public $langDetectors = [
        'it' => ['Il tuo volo è stato prenotato', 'Grazie di aver effettuato il check-in'],
        'es' => ['El vuelo está reservado', 'Muchas gracias por facturar'],
        'de' => ['Vielen Dank färs Einchecken'],
        'en' => ['Your Flight is booked', 'Manage my booking', 'Thank You for checking in', 'Your changes have been updated'],
    ];

    public static $dict = [
        'it' => [
            'Record Locator - old' => 'Il tuo numero di conferma volo è:', //old
            'Record Locator'       => 'Il tuo numero di conferma volo è:',
            'Adult'                => 'Adulto',
            //			'Child' => '',
            'Charged'      => 'Totale',
            'Booking Date' => 'Data di prenotazione:',
            'Flying Out'   => 'Volo di andata -',
            'Flying Back'  => 'Volo di ritorno -',
            'Departing'    => 'Partenza',
            'Flight'       => 'Volo',
            'to'           => 'a',
            'Passengers'   => 'Passeggeri',
            'Confirmed'    => 'Confermato',
        ],
        'es' => [
            'Record Locator - old' => 'Tu número de confirmación es:', //old
            'Record Locator'       => 'Tu número de confirmación es:',
            'Adult'                => 'Adulto',
            //			'Child' => '',
            'Charged'      => 'Total',
            'Booking Date' => 'Fecha de reserva:',
            'Flying Out'   => 'Vuelo de salida',
            'Flying Back'  => 'Vuelo de regreso', //??? don't know. не было примера
            'Departing'    => 'Salida',
            'Flight'       => 'Vuelo',
            'to'           => 'a',
            'Passengers'   => 'Pasajeros',
            'Confirmed'    => 'Confirmado',
        ],
        'de' => [
            'Record Locator - old' => 'Ihre Flugbestätigungsnummer lautet:', //old
            'Record Locator'       => 'Ihre Flugbestätigungsnummer lautet:',
            'Adult'                => 'Erwachsener',
            //			'Child' => '',
            'Charged'      => 'Gesamt',
            'Booking Date' => 'Buchungsdatum:',
            'Flying Out'   => 'Abflug',
            'Flying Back'  => 'Rückflug', //??? don't know
            'Departing'    => 'Abflug',
            'Flight'       => 'Flug',
            'to'           => 'nach',
            'Passengers'   => 'Fluggäste',
            'Confirmed'    => 'Bestätigt',
        ],
        'en' => [
            'Record Locator - old' => 'Your flight confirmation number is:', //old
            'Record Locator'       => 'Your booking reference is:',
            'Adult'                => 'Adult',
            'Child'                => ['Child', 'Youth'],
            'Charged'              => 'Total',
            'Booking Date'         => 'Booking Date:',
            'Flying Out'           => 'Flying Out -',
            'Flying Back'          => 'Flying Back -',
            'Departing'            => 'Departing',
            'Flight'               => 'Flight',
            'to'                   => 'to',
            'Passengers'           => 'Passengers',
            'Confirmed'            => 'Confirmed',
        ],
    ];

    public function processors()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;
                }
            }
        }

        if (empty($this->lang)) {
            return false;
        }

        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $xpathFragment1 = $this->contains([$this->t('Record Locator - old'), $this->t('Record Locator')]);
                        $recordLocator = $this->http->FindSingleNode('//text()[' . $xpathFragment1 . ']/following::text()[normalize-space(.)][1]', null, true, '/^([A-Z\d]{5,})$/');

                        if (empty($recordLocator)) {
                            $recordLocator = $this->http->FindSingleNode('//td[contains(normalize-space(.),"Booking Reference:") and not(.//td)]', null, true, '/^[^:]+:\s*([A-Z\d]{5,})$/');
                        }

                        return $recordLocator;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return array_unique(nice(array_filter(nodes("//*[normalize-space(text()) = '" . $this->t('Adult') . "'  or " . $this->contains($this->t('Child')) . "]/preceding::td[1]"))));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $info = node("//*[normalize-space(text()) = '" . $this->t('Booking Date') . "']/following::td[1]");

                        $total = rew('(.\s*[\d.]+) $', $info);

                        if ($total == null) {
                            $total = cell($this->t('Charged'), +1);
                        }
                        $res = total($total);

                        $bookingDate = uberDate($info, 1);

                        if ($bookingDate) {
                            $res['ReservationDate'] = strtotime(en($bookingDate));
                        }

                        if (rew($this->t('Confirmed'), $info)) {
                            $res['Status'] = $this->t('Confirmed');
                        }

                        return $res;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpathFragment1 = 'contains(normalize-space(.),"' . $this->t('Passengers') . '") and contains(normalize-space(.),"' . $this->t('Departing') . '") and contains(normalize-space(.),"' . $this->t('Flight') . '")';

                        return $this->http->XPath->query('//tr[' . $xpathFragment1 . ' and ./preceding-sibling::tr[contains(normalize-space(.),"' . $this->t('to') . '")] and not(.//tr)]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = rew($this->t('Flight') . '\s*([A-Z\d]{2}\s*\d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $result = [];
                            $route = $this->http->FindSingleNode('./preceding-sibling::tr[contains(normalize-space(.),"' . $this->t('to') . '")][1]', $node);

                            if (preg_match('/(?:' . $this->t('Flying Out') . '|' . $this->t('Flying Back') . ')?\s*(?<DepName>\w.+\w)\s+' . $this->t('to') . '\s+(?<ArrName>\w.+\w)/su', $route, $matches)) {
                                $result['DepName'] = $matches['DepName'];
                                $result['ArrName'] = $matches['ArrName'];
                            }

                            return $result;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = en(uberDate(1));
                            $time1 = uberTime(1);
                            $time2 = uberTime(2);
                            $date = strtotime($date);
                            $dt1 = strtotime($time1, $date);
                            $dt2 = strtotime($time2, $date);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seats = $this->http->FindNodes('./ancestor::tr/following-sibling::tr[contains(normalize-space(.),"' . $this->t('Adult') . '")][1]/descendant::tr/td[normalize-space(.)="' . $this->t('Adult') . '" or ' . $this->contains($this->t('Child')) . ']/following-sibling::td[normalize-space(.)][1]', $node, '/\s(\d{1,2}[A-Z])$/');
                            $seatValues = array_values(array_filter($seats));

                            return $seatValues;
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
