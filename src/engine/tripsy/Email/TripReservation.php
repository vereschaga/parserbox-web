<?php

namespace AwardWallet\Engine\tripsy\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class TripReservation extends \TAccountChecker
{
    public $mailFiles = "tripsy/it-114595421.eml, tripsy/it-114636522.eml, tripsy/it-161020854.eml, tripsy/it-84992750.eml, tripsy/it-85023736.eml, tripsy/it-85929092.eml, tripsy/it-86112060.eml, tripsy/it-98777947.eml";

    public $lang = '';

    public static $dictionary = [
        'pt' => [ // it-86112060.eml
            // FLIGHT
            'Departure'  => ['Partida'],
            'Arrival'    => ['Chegada'],
            'Seat class' => 'Classe do Assento',
            // HOTEL
            'Phone'                  => 'Telefone',
            'Check-in'               => ["Check-in"],
            'Check-out'              => ['Check-out'],
            //'Seats' => [''],
            'startsFreeCancellation' => 'Você pode cancelar',
            // CAR
            // 'Pick-up' => [''],
            // 'Drop-out' => [''],
            // EVENT
            'Date'             => ['Data'],
            'Reservation code' => 'Código de Reserva',
        ],
        'de' => [ // it-86112060.eml
            // FLIGHT
            'Departure' => ['Abreise'],
            'Arrival'   => ['Anreise'],
            'Seats'     => [''],
            // HOTEL
            'Phone'                  => 'Telefon',
            'Check-in'               => ["Check-in"],
            'Check-out'              => ['Check-out'],
            'startsFreeCancellation' => 'Bis zu',
            // CAR
            // 'Pick-up' => [''],
            // 'Drop-out' => [''],
            // EVENT
            // 'Date' => [''],
            'Reservation code' => 'Reservierungsnr.',
        ],
        'fr' => [ // it-86112060.eml
            // FLIGHT
            // 'Departure' => [''],
            // 'Arrival' => [''],
            //'Seats' => [''],
            // HOTEL
            'Phone'                  => 'Téléphone',
            'Check-in'               => ["Heure d'arrivée"],
            'Check-out'              => ['Heure de départ'],
            'startsFreeCancellation' => 'Vous pourrez',
            // CAR
            // 'Pick-up' => [''],
            // 'Drop-out' => [''],
            // EVENT
            // 'Date' => [''],
            'Reservation code' => 'Code de la réservation',
        ],
        'en' => [
            // FLIGHT
            'Departure' => ['Departure'],
            'Arrival'   => ['Arrival'],
            // HOTEL
            'Check-in'               => ['Check-in'],
            'Check-out'              => ['Check-out'],
            //'Seats' => [''],
            'startsFreeCancellation' => 'Cancel before',
            // CAR
            'Pick-up'  => ['Pick-up'],
            'Drop-out' => ['Drop-out'],
            // EVENT
            'Date'             => ['Date'],
            'Reservation code' => 'Reservation code',
        ],
    ];

    private $subjects = [
        'pt' => ['atividades foram importadas em'],
        'fr' => ['Une nouvelle activité a été importée dans'],
        'en' => ['A new activity was imported into', 'new activities were imported into'],
        'de' => ['Eine neue Aktivität wurde per'],
    ];

    private $detectors = [
        'pt' => ['Uma nova viagem foi criada baseada em sua reserva', 'Uma nova atividade foi importada em sua viagem'],
        'fr' => ['Un nouveau voyage a été créé conformément à votre réservation', 'Une nouvelle activité a été importée à votre voyage'],
        'en' => ['A new trip was created based on your reservation', 'A new activity was imported into your trip', 'A new activity was imported into your trip'],
        'de' => ['Eine neue Aktivität wurde in deine Reise importiert', 'Aufgrund deiner Reservierung wurde eine neue Reise erstellt'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@tripsy.app') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], '(Tripsy Reservation)') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"//tripsy.app/") or normalize-space()="tripsy.app"]')->length === 0
            && $this->http->XPath->query('//img[contains(@src,"//s3.amazonaws.com/tripsy.resources/email-")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('TripReservation' . ucfirst($this->lang));

        $patterns = [
            'phone'         => '[+(\d][-. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'time'          => '\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?', // 7:30 PM
        ];

        // 02 Apr 2021 - 7:30
        $patterns['dateTime'] = "/^(?<date>.{6,}?)\s+-\s+(?<time>{$patterns['time']})\s*$/u";

        $travellerName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/^{$this->opt($this->t('Hi'))}\s+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u");

        // FLIGHT
        $flights = $this->http->XPath->query("//div[ descendant::node()[self::p[normalize-space()] or self::img][1][contains(@src,'/email-plane.')] and descendant::p[descendant::text()[{$this->starts($this->t('Departure'))}] and descendant::text()[{$this->starts($this->t('Arrival'))}]] ]");

        if ($flights->length > 0) {
            $f = $email->add()->flight();
            $f->general()->noConfirmation();

            if ($travellerName) {
                $f->general()->traveller($travellerName);
            }

            foreach ($flights as $fRoot) {
                $s = $f->addSegment();
                $flightTexts = [];
                $flightRows = $this->http->XPath->query("descendant::p[normalize-space()]", $fRoot);

                foreach ($flightRows as $fRow) {
                    $flightTexts[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $fRow));
                }
                $flightText = implode("\n", $flightTexts);

                if (preg_match("/^\s*(?<airlineFull>.+?)[ ]*\n+[ ]*(?<airline>[A-Z]{3}|[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)[ ]*\n+[ ]*(?<codeDep>[A-Z]{3})[✈ ]+(?<codeArr>[A-Z]{3})[ ]*(?:\n|$)/", $flightText, $m)) {
                    /*
                        Frontier Airlines
                        FFT44
                        ORD ✈ CUN
                    */
                    $s->airline()
                        ->name(preg_match('/^[A-Z]{3}$/', $m['airline']) > 0 ? $m['airlineFull'] : $m['airline'])
                        ->number($m['number']);
                    $s->departure()->code($m['codeDep']);
                    $s->arrival()->code($m['codeArr']);
                    $s->extra()
                        ->seats(explode(',', $this->re("/{$this->opt($this->t('Sitzplatz:'))}\s*(.+)/", $flightText)));
                }

                if (preg_match("/^[ ]*{$this->opt($this->t('Departure'))}[ ]*[:]+[ ]*(.{6,}?)[ ]*$/m", $flightText, $m)) {
                    if (preg_match($patterns['dateTime'], $m[1], $m2)) {
                        $dateDep = $this->normalizeDate($m2['date']);
                        $timeDep = $m2['time'];
                    } else {
                        $dateDep = $this->normalizeDate($m[1]);
                        $timeDep = null;
                    }

                    if ($dateDep) {
                        $s->departure()->date2($dateDep . ' ' . $timeDep);
                    }
                }

                if (preg_match("/^[ ]*{$this->opt($this->t('Arrival'))}[ ]*[:]+[ ]*(.{6,}?)[ ]*$/m", $flightText, $m)) {
                    if (preg_match($patterns['dateTime'], $m[1], $m2)) {
                        $dateArr = $this->normalizeDate($m2['date']);
                        $timeArr = $m2['time'];
                    } else {
                        $dateArr = $this->normalizeDate($m[1]);
                        $timeArr = null;
                    }

                    if ($dateArr) {
                        $s->arrival()->date2($dateArr . ' ' . $timeArr);
                    }
                }

                if (preg_match("/^[ ]*{$this->opt($this->t('Seat class'))}[ ]*[:]+[ ]*(.+)[ ]*$/m", $flightText, $m)) {
                    $s->extra()->cabin($m[1]);
                }

                if (preg_match("/^[ ]*({$this->opt($this->t('Reservation code'))})[ ]*[:]+[ ]*([-A-Z\d]{5,})[ ]*$/m", $flightText, $m)) {
                    $s->airline()->confirmation($m[2]);
                }
            }
        }

        // TRAIN
        $trains = $this->http->XPath->query("//div[ descendant::node()[self::p[normalize-space()] or self::img][1][contains(@src,'/email-train.')] and descendant::p[descendant::text()[{$this->starts($this->t('Departure'))}] and descendant::text()[{$this->starts($this->t('Arrival'))}]] ]");

        if ($trains->length > 0) {
            $train = $email->add()->train();
            $train->general()->noConfirmation();

            if ($travellerName) {
                $train->general()->traveller($travellerName);
            }

            foreach ($trains as $tRoot) {
                $s = $train->addSegment();
                $trainTexts = [];
                $trainRows = $this->http->XPath->query("descendant::p[normalize-space()]", $tRoot);

                foreach ($trainRows as $tRow) {
                    $trainTexts[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $tRow));
                }
                $trainText = implode("\n", $trainTexts);

                if (preg_match("/^(?<number>\d+)?\s*(?<nameDep>.{3,}?)[ ]*\n+[ ]*(?<nameArr>.{3,}?)[ ]*\n+[ ]*{$this->opt($this->t('Departure'))}/", $trainText, $m)) {
                    $s->departure()->name($m['nameDep']);
                    $s->arrival()->name($m['nameArr']);

                    if (isset($m['number']) && !empty($m['number'])) {
                        $s->setNumber($m['number']);
                    }
                }

                if (preg_match("/^[ ]*{$this->opt($this->t('Departure'))}[ ]*[:]+[ ]*(.{6,}?)[ ]*$/m", $trainText, $m)) {
                    if (preg_match($patterns['dateTime'], $m[1], $m2)) {
                        $dateDep = $this->normalizeDate($m2['date']);
                        $timeDep = $m2['time'];
                    } else {
                        $dateDep = $this->normalizeDate($m[1]);
                        $timeDep = null;
                    }

                    if ($dateDep) {
                        $s->departure()->date2($dateDep . ' ' . $timeDep);
                    }
                }

                if (preg_match("/^[ ]*{$this->opt($this->t('Arrival'))}[ ]*[:]+[ ]*(.{6,}?)[ ]*$/m", $trainText, $m)) {
                    if (preg_match($patterns['dateTime'], $m[1], $m2)) {
                        $dateArr = $this->normalizeDate($m2['date']);
                        $timeArr = $m2['time'];
                    } else {
                        $dateArr = $this->normalizeDate($m[1]);
                        $timeArr = null;
                    }

                    if ($dateArr) {
                        $s->arrival()->date2($dateArr . ' ' . $timeArr);
                    }
                }

                if (preg_match("/^[ ]*{$this->opt($this->t('Seat number'))}[ ]*[:]+[ ]*(\d[A-Z\d, ]*)[ ]*$/m", $trainText, $m)) {
                    $s->extra()->seats(preg_split('/\s*[,]+\s*/', $m[1]));
                }

                if (preg_match("/^[ ]*{$this->opt($this->t('Car'))}[ ]*[:]+[ ]*(.+)[ ]*$/m", $trainText, $m)) {
                    $s->extra()->car($m[1]);
                }

                if (preg_match("/^[ ]*{$this->opt($this->t('Train'))}[ ]*[:]+[ ]*$[\s\S]+^[ ]*{$this->opt($this->t('Type'))}[ ]*[:]+[ ]*(.+)[ ]*$/m", $trainText, $m)) {
                    $s->extra()->type($m[1]);
                }

                if (preg_match("/{$this->opt($this->t('Seat class:'))}\s*(.+)\n/", $trainText, $m)) {
                    $s->extra()
                        ->cabin($m[1]);
                }

                if (!empty($s->getDepName()) && !empty($s->getArrName()) && !empty($s->getDepDate()) && !empty($s->getArrDate()) && empty($s->getNumber())) {
                    $s->extra()->noNumber();
                }
            }
        }

        // HOTEL
        $hotels = $this->http->XPath->query("//div[ descendant::node()[self::p[normalize-space()] or self::img][1][contains(@src,'/email-lodging.')] and descendant::p[descendant::text()[{$this->starts($this->t('Check-in'))}] and descendant::text()[{$this->starts($this->t('Check-out'))}]] ]");

        if ($hotels->length == 0) {
            $hotels = $this->http->XPath->query("//div[ descendant::node()[self::p[normalize-space()] or self::img][1][contains(@src,'/email-lodging.')] and descendant::div[descendant::text()[(contains(normalize-space(),'Check-in'))] and descendant::text()[(contains(normalize-space(),'Check-out'))]] ]");
        }

        foreach ($hotels as $hRoot) {
            $h = $email->add()->hotel();

            if ($travellerName) {
                $h->general()->traveller($travellerName);
            }

            $freeCancellation = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('startsFreeCancellation'))}]", $hRoot);

            if (!empty($freeCancellation)) {
                $h->general()
                    ->cancellation($freeCancellation);
            }

            $hotelTexts = [];
            $hotelRows = $this->http->XPath->query("descendant::text()[normalize-space()]", $hRoot);

            foreach ($hotelRows as $hRow) {
                $hotelTexts[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $hRow));
            }
            $hotelText = implode("\n", $hotelTexts);

            if (preg_match("/^\s*(?<name>.{3,}?)[ ]*\n+[ ]*(?<address>[\s\S]{3,}?)[ ]*\n+[ ]*{$this->opt($this->t('Check-in'))}/", $hotelText, $m)) {
                if (stripos($m['address'], 'Send email to hotel') !== false) {
                    $this->logger->debug('NO ADDRESS');

                    return false;
                }

                $h->hotel()->name($m['name'])->address(preg_replace('/\s+/', ' ', $m['address']));
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Check-in'))}[ ]*[:\s]+[ ]*(.{6,}?)[ ]*$/m", $hotelText, $m)
                || preg_match("/[ ]*{$this->opt($this->t('Check-in'))}[ ]*[:\s]+[ ]*(.{6,}?)[ ]*{$this->opt($this->t('Check-out'))}/msu", $hotelText, $m)) {
                if (preg_match($patterns['dateTime'], $m[1], $m2)) {
                    $dateCheckIn = $this->normalizeDate($m2['date']);
                    $timeCheckIn = $m2['time'];
                } else {
                    $dateCheckIn = $this->normalizeDate(str_replace("\n", "", $m[1]));
                    $timeCheckIn = null;
                }

                if ($dateCheckIn) {
                    $h->booked()->checkIn2($dateCheckIn . ' ' . $timeCheckIn);
                }
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Check-out'))}[ ]*[:\s]+[ ]*(.{6,}?)[ ]*$/m", $hotelText, $m)
                || preg_match("/[ ]*{$this->opt($this->t('Check-out'))}[ ]*[:\s]+[ ]*\n\s*(.{6,}?)[ ]*\n/m", $hotelText, $m)) {
                if (preg_match($patterns['dateTime'], $m[1], $m2)) {
                    $dateCheckOut = $this->normalizeDate($m2['date']);
                    $timeCheckOut = $m2['time'];
                } else {
                    $dateCheckOut = $this->normalizeDate($m[1]);
                    $timeCheckOut = null;
                }

                if ($dateCheckOut) {
                    $h->booked()->checkOut2($dateCheckOut . ' ' . $timeCheckOut);
                }
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Phone'))}[ ]*[:]+[ ]*({$patterns['phone']})[ ]*$/m", $hotelText, $m)
                || preg_match("/^[ ]*{$this->opt($this->t('Phone'))}[ ]*[:]+\n\s*[ ]*({$patterns['phone']})[ ]*/m", $hotelText, $m)) {
                $h->hotel()->phone($m[1]);
            }

            if (preg_match("/^[ ]*({$this->opt($this->t('Reservation code'))})[ ]*[:]+[ ]*([-A-Z\d]{5,})[ ]*$/m", $hotelText, $m)
                || preg_match("/^[ ]*({$this->opt($this->t('Reservation code'))})[ ]*[:]+\n\s*[ ]*([-A-Z\d]{5,})[ ]*/m", $hotelText, $m)) {
                $h->general()->confirmation($m[2], trim($m[1], '.'));
            } else {
                $h->general()->noConfirmation();
            }

            if (preg_match("/Vous (?i)pourrez annuler gratuitement votre réservation jusqu'à\s*(?<prior>\d{1,3})\s*jour avant l'arrivée\./", $hotelText, $m) //fr
                || preg_match("/Bis zu (?<prior>\d{1,3}) Tage vor der Anreise können Sie kostenfrei stornieren\./", $hotelText, $m)// de
            ) {
                $h->booked()->deadlineRelative($m['prior'] . ' days');
            }

            if (preg_match("/Vous pourrez annuler gratuitement votre réservation jusqu'à (?<prior>\d+) jours avant l'arrivée/us", $hotelText, $m)) { //en
                $h->booked()->deadlineRelative($m['prior'] . ' hours');
            }

            if (preg_match("/Você pode cancelar até (?<day>[\d\/]+) às (?<hour>\d+)h(?<min>\d+)/u", $hotelText, $m)) {
                $h->booked()->deadline(strtotime(str_replace('/', '.', $m['day']) . ', ' . $m['hour'] . ':' . $m['min']));
            }

            $roomType = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('startsFreeCancellation'))}]/preceding::text()[normalize-space()][2][not(contains(normalize-space(), '" . $this->t('Reservation code') . "') and contains(normalize-space(), '" . $this->t('Phone') . "'))]", $hRoot);

            if (!empty($roomType) && !empty($h->getConfirmationNumbers()[0][0])) {
                if ($roomType === $h->getConfirmationNumbers()[0][0]) {
                    $roomType = preg_replace("/hosted by\D+/", '', $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('startsFreeCancellation'))}]/preceding::text()[normalize-space()][1]", $hRoot));
                }
            }

            if (!empty($roomType)) {
                $room = $h->addRoom();
                $room->setType($roomType);
            }
        }

        // CAR
        $cars = $this->http->XPath->query("//div[ descendant::node()[self::p[normalize-space()] or self::img][1][contains(@src,'/email-car.')] and descendant::p[descendant::text()[{$this->starts($this->t('Pick-up'))}] and descendant::text()[{$this->starts($this->t('Drop-out'))}]] ]");

        foreach ($cars as $carRoot) {
            $car = $email->add()->rental();

            if ($travellerName) {
                $car->general()->traveller($travellerName);
            }
            $carTexts = [];
            $hotelRows = $this->http->XPath->query("descendant::p[normalize-space()]", $carRoot);

            foreach ($hotelRows as $hRow) {
                $carTexts[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $hRow));
            }
            $carText = implode("\n", $carTexts);

            if (preg_match("/^\s*(?<company>.{3,}?)[ ]*\n+[ ]*(?<address>[\s\S]{3,}?)[ ]*\n+[ ]*{$this->opt($this->t('Pick-up'))}/", $carText, $m)) {
                if (($code = $this->normalizeProvider($m['company']))) {
                    $car->program()->code($code);
                } else {
                    $car->extra()->company($m['company']);
                }
                $car->pickup()->location(preg_replace('/\s+/', ' ', $m['address']));
                $car->dropoff()->same();
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Pick-up'))}[ ]*[:]+[ ]*(.{6,}?)[ ]*$/m", $carText, $m)) {
                if (preg_match($patterns['dateTime'], $m[1], $m2)) {
                    $datePickUp = $this->normalizeDate($m2['date']);
                    $timePickUp = $m2['time'];
                } else {
                    $datePickUp = $this->normalizeDate($m[1]);
                    $timePickUp = null;
                }

                if ($datePickUp) {
                    $car->pickup()->date2($datePickUp . ' ' . $timePickUp);
                }
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Drop-out'))}[ ]*[:]+[ ]*(.{6,}?)[ ]*$/m", $carText, $m)) {
                if (preg_match($patterns['dateTime'], $m[1], $m2)) {
                    $dateDropOff = $this->normalizeDate($m2['date']);
                    $timeDropOff = $m2['time'];
                } else {
                    $dateDropOff = $this->normalizeDate($m[1]);
                    $timeDropOff = null;
                }

                if ($dateDropOff) {
                    $car->dropoff()->date2($dateDropOff . ' ' . $timeDropOff);
                }
            }

            if (preg_match("/^[ ]*({$this->opt($this->t('Reservation code'))})[ ]*[:]+[ ]*([-A-Z\d]{5,})[ ]*$/m", $carText, $m)) {
                $car->general()->confirmation($m[2], $m[1]);
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Pickup Opening Hours'))}[ ]*[:]+[ ]*(.{4,}?)[ ]*$/m", $carText, $m)) {
                $car->pickup()->openingHours($m[1]);
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Dropoff Opening Hours'))}[ ]*[:]+[ ]*(.{4,}?)[ ]*$/m", $carText, $m)) {
                $car->dropoff()->openingHours($m[1]);
            }
        }

        // EVENT (Restaurant)
        $restaurants = $this->http->XPath->query("//div[ descendant::node()[self::p[normalize-space()] or self::img][1][contains(@src,'/email-restaurant.')] and descendant::p[descendant::text()[{$this->starts($this->t('Date'))}] and descendant::text()[{$this->starts($this->t('Reservation code'))}]] ]");

        foreach ($restaurants as $rRoot) {
            $restaurant = $email->add()->event();
            $restaurantTexts = [];
            $restaurantRows = $this->http->XPath->query("descendant::p[normalize-space()]", $rRoot);

            foreach ($restaurantRows as $rRow) {
                $restaurantTexts[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $rRow));
            }
            $restaurantText = implode("\n", $restaurantTexts);

            if (preg_match("/^\s*(?<name>[\s\S]+?)[ ]*\n+[ ]*{$this->opt($this->t('Date'))}[ ]*[:]+[ ]*(?<date>.{6,})\n+[ ]*(?<address>[\s\S]{3,}?)[ ]*\n+[ ]*{$this->opt($this->t('Phone'))}[ ]*[:]+[ ]*(?<phone>{$patterns['phone']})[ ]*(?:\n|$)/", $restaurantText, $m)) {
                /*
                    The Vault Uptown
                    Date: 07 Apr 2021 - 0:30
                    361 Forest Road Sedona, AZ 86336
                    Phone: (928) 203-5462
                */
                $restaurant->place()
                    ->type(Event::TYPE_RESTAURANT)
                    ->name(preg_replace('/\s+/', ' ', $m['name']))
                    ->address(preg_replace('/\s+/', ' ', $m['address']))
                    ->phone($m['phone']);

                if (preg_match($patterns['dateTime'], $m['date'], $m2)) {
                    $dateStart = $this->normalizeDate($m2['date']);
                    $timeStart = $m2['time'];
                } else {
                    $dateStart = $this->normalizeDate($m[1]);
                    $timeStart = null;
                }

                if ($dateStart) {
                    $restaurant->booked()->start2($dateStart . ' ' . $timeStart);
                }
                $restaurant->booked()->noEnd();
            }

            if (preg_match("/^[ ]*({$this->opt($this->t('Reservation code'))})[ ]*[:]+[ ]*([-A-Z\d]{5,})[ ]*$/m", $restaurantText, $m)) {
                $restaurant->general()->confirmation($m[2], $m[1]);
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Guests'))}[ ]*[:]+[ ]*(?<guests>\d{1,3})\b(?<travellers>(?:[ ]*\n+[ ]*-[ ]*{$patterns['travellerName']})+)?/m", $restaurantText, $m)) {
                $restaurant->booked()->guests($m['guests']);

                if (!empty($m['travellers'])) {
                    $restaurant->general()->travellers(preg_split('/[ ]*\n+[- ]+/', trim($m['travellers'], "- \n")));
                } elseif ($travellerName) {
                    $restaurant->general()->traveller($travellerName);
                }
            }
        }

        // EVENT (Event)
        $events = $this->http->XPath->query("//div[ descendant::node()[self::p[normalize-space()] or self::img][1][contains(@src,'/email-general.')] and descendant::p[descendant::text()[{$this->starts($this->t('Date'))}] and descendant::text()[{$this->starts($this->t('Reservation code'))}]] ]");

        foreach ($events as $rRoot) {
            $restaurant = $email->add()->event();
            $restaurantTexts = [];
            $restaurantRows = $this->http->XPath->query("descendant::p[normalize-space()]", $rRoot);

            foreach ($restaurantRows as $rRow) {
                $restaurantTexts[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $rRow));
            }

            $restaurantText = implode("\n", $restaurantTexts);

            if (preg_match("/^\s*(?<name>[\s\S]+?)[ ]*\n+[ ]*{$this->opt($this->t('Date'))}[ ]*[:]+[ ]*(?<date>.{6,})\n+[ ]*(?<address>[\s\S]{3,}?)[ ]*\n+[ ]*/", $restaurantText, $m)) {
                /*
                    Vedettes de Paris - Cruzeiro Panorâmico pelo Sena Bilhete de cruzeiro aberto
                    Data: 23 Out 2021 - 22:00
                    Vedettes de Paris - Port de Suffren, 75007 Paris
                */
                $restaurant->place()
                    ->type(Event::TYPE_EVENT)
                    ->name(preg_replace('/\s+/', ' ', $m['name']))
                    ->address(preg_replace('/\s+/', ' ', $m['address']));

                if (preg_match("/^(?<startDate>\d+\s*\w+\s*\d{4})[\s\-]+(?<startTime>[\d\:]+)[\s\➙]+(?<endDate>\d+\s*\w+\s*\d{4})[\s\-]+(?<endTime>[\d\:]+)\s*$/", $m['date'], $match)) {
                    $restaurant->booked()
                        ->start(strtotime($match['startDate'] . ', ' . $match['startTime']))
                        ->end(strtotime($match['endDate'] . ', ' . $match['endTime']));
                } elseif (preg_match($patterns['dateTime'], $m['date'], $m2)) {
                    $dateStart = $this->normalizeDate($m2['date']);
                    $timeStart = $m2['time'];
                } else {
                    $dateStart = $this->normalizeDate($m[1]);
                    $timeStart = null;
                }

                if ($dateStart) {
                    $restaurant->booked()->start2($dateStart . ' ' . $timeStart);
                }

                if (empty($restaurant->getEndDate())) {
                    $restaurant->booked()->noEnd();
                }
            }

            if (preg_match("/^[ ]*({$this->opt($this->t('Reservation code'))})[ ]*[:]+[ ]*([-A-Z\d]{5,})[ ]*$/m", $restaurantText, $m)) {
                $restaurant->general()->confirmation($m[2], $m[1]);
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Guests'))}[ ]*[:]+[ ]*(?<guests>\d{1,3})\b(?<travellers>(?:[ ]*\n+[ ]*-[ ]*{$patterns['travellerName']})+)?/m", $restaurantText, $m)) {
                $restaurant->booked()->guests($m['guests']);

                if (!empty($m['travellers'])) {
                    $restaurant->general()->travellers(preg_split('/[ ]*\n+[- ]+/', trim($m['travellers'], "- \n")));
                } elseif ($travellerName) {
                    $restaurant->general()->traveller($travellerName);
                }
            }
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach ($this->detectors as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[contains(normalize-space(), '$word')]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate($text)
    {
        if (preg_match('/^(\d{1,2})\s+([[:alpha:]]{3,})\s+(\d{4})$/u', $text, $m)) {
            // 07 Apr 2021
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    /**
     * @param string|null $string Provider keyword
     *
     * @return string|null Provider code
     */
    private function normalizeProvider(?string $string): ?string
    {
        $string = trim($string);
        $providers = [
            'avis'         => ['Avis'],
            'alamo'        => ['Alamo'],
            'perfectdrive' => ['Budget'],
            'dollar'       => ['Dollar'],
            'rentacar'     => ['Enterprise'],
            'europcar'     => ['Europcar'],
            'thrifty'      => ['Thrifty'],
        ];

        foreach ($providers as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (strcasecmp($string, $keyword) === 0) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
