<?php

namespace AwardWallet\Engine\malaysia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parsers aviancataca/Air(object), flyerbonus/TripReminder(object), thaiair/Cancellation(object), rapidrewards/Changes(object), mabuhay/FlightChange(object), lotpair/FlightChange(object) (in favor of malaysia/FlightRetiming)

class FlightRetiming extends \TAccountChecker
{
    public $mailFiles = "malaysia/it-10129200.eml, malaysia/it-10215341.eml, malaysia/it-10285033-luxair.eml, malaysia/it-10291060-egyptair.eml, malaysia/it-10291061-egyptair.eml, malaysia/it-12047546-china.eml, malaysia/it-12360020-aviancataca.eml, malaysia/it-129886333-mabuhay.eml, malaysia/it-13200140.eml, malaysia/it-13777738-egyptair.eml, malaysia/it-14290557-china.eml, malaysia/it-701286485-saudisrabianairlin.eml, malaysia/it-70165007-lotpair-pl.eml, malaysia/it-7458637-china.eml, malaysia/it-7513147-egyptair.eml, malaysia/it-7680961-luxair.eml, malaysia/it-7806781.eml, malaysia/it-845149665.eml, malaysia/it-93451213-lotpair.eml";

    public $reBody = [
        'es'  => ['Referencia de la reserva', 'Salida'],
        'zh'  => ['訂位代號', '出發'],
        'fr'  => ['Référence de réservation', 'Départ'],
        'pl'  => ['Numer rezerwacji', 'Wyjazd'],
        'en'  => ['Booking reference', 'Departure'],
        'en2' => ['PNE.AACC.AMADEUS.EML.LABEL.BOOKINGREFERENCE', 'PNE.AACC.AMADEUS.EML.LABEL.DEPARTURE'],
        'ar'  => [':مرجع الحجز', 'من'],
    ];
    public $lang = '';
    public static $dict = [
        'es' => [
            'Booking reference:' => 'Referencia de la reserva:',
            'Dear'               => 'Hola',
            'segmentsPairs'      => [
                ['start' => 'Información sobre el itinerario', 'end' => 'Algunos vuelos podrían presentar modificaciones'],
                ['start' => 'Información del itinerario', 'end' => 'Para gestionar tu vuelo'],
            ],
            'statusPhrases'  => ['ha sido'],
            'statusVariants' => ['modificado'],
            'statusChanged'  => ['ha sido modificado.'],
            // 'cancelledPhrases' => [''],
            'Frequent flyer' => 'Viajero frecuente',
            // 'Passenger name' => '',
            // 'Ticket number' => '',
            'Departure' => 'Salida',
            'Flight'    => 'Vuelo',
        ],
        'zh' => [
            'Booking reference:' => '訂位代號:',
            // 'Dear' => '',
            'segmentsPairs' => [
                ['start' => '新航班資訊', 'end' => '感謝您的支持與搭乘'],
            ],
            // 'statusPhrases' => [''],
            // 'statusVariants' => [''],
            // 'statusChanged' => '',
            // 'cancelledPhrases' => [''],
            'Frequent flyer' => '飛行常客',
            // 'Passenger name' => '',
            // 'Ticket number' => '',
            'Departure' => '出發',
            'Flight'    => '航班',
        ],
        'fr' => [
            'Booking reference:' => 'Référence de réservation:',
            'Dear'               => 'Cher',
            //            'segmentsPairs' => [
            //                ['start' => '', 'end' => ''],
            //            ],
            // 'statusPhrases' => [''],
            // 'statusVariants' => [''],
            'statusChanged'  => 'SITE WEB D\'ENREGISTREMENT EN LIGNE',
            // 'cancelledPhrases' => [''],
            'Frequent flyer' => ['Frequent flyer', 'Frequent Flyer'],
            // 'Passenger name' => '',
            // 'Ticket number' => '',
            'Departure'      => 'Départ',
            // 'Flight' => '',
        ],
        'pl' => [
            'Booking reference:' => 'Numer rezerwacji:',
            'Dear'               => ['Dzień dobry', 'Drogi'],
            'segmentsPairs'      => [
                ['start' => 'NOWY CZAS WYLOTU', 'end' => 'POPRZEDNI CZAS WYLOTU'],
                ['start' => 'Informacje o planie podróży', 'end' => 'W celu dokonania zmian'],
            ],
            // 'statusPhrases' => [''],
            'statusVariants' => ['zmianie'],
            'statusChanged'  => ['ZMIANA HARMONOGRAMU', 'Informujemy, że Twoja podróż uległa zmianie.'],
            // 'cancelledPhrases' => [''],
            'Frequent flyer' => ['Uczestnik programu Frequent Flyer', 'Frequent flyer', 'Frequent Flyer'],
            // 'Passenger name' => '',
            // 'Ticket number' => '',
            'Departure'      => ['Wyjazd', 'Wyjazd (wyl', 'Wyjazd (wylot)'],
            'Flight'         => 'Przelot',
        ],
        'en' => [
            'Booking reference:' => ['Booking reference:', 'PNE.AACC.AMADEUS.EML.LABEL.BOOKINGREFERENCE:'],
            'Dear'               => ['Dear', 'Hello'],
            'segmentsPairs'      => [
                ['start' => 'Your Itinerary Summary', 'end' => 'For further information'],
                ['start' => 'Your Itinerary Summary', 'end' => ', please contact'],
                ['start' => 'Your Itinerary Summary', 'end' => ', please do not hesitate to contact'],
                ['start' => 'Active flight information', 'end' => 'Let’s Keep You on Track'],
                ['start' => 'Active flight information', 'end' => "Let's Keep You on Track"],
                ['start' => 'Active flight information', 'end' => 'Security & Documents Check'],
                ['start' => 'ACTIVE FLIGHT INFORMATION', 'end' => 'Before You Fly:'],
                ['start' => 'ACTIVE FLIGHT INFORMATION', 'end' => 'Thank you for choosing'],
                ['start' => 'ACTIVE FLIGHT INFORMATION', 'end' => 'If you wish to make changes'],
                ['start' => 'New Flight Details', 'end' => 'Original Flight Details'],
                ['start' => 'NEW DEPARTURE TIME', 'end' => 'PREVIOUS DEPARTURE TIME'],
                ['start' => 'NEW DEPARTURE TIME', 'end' => 'PREVIOUS FLIGHT'],
                ['start' => 'YOUR NEW FLIGHT', 'end' => 'PREVIOUS FLIGHT'],
                ['start' => 'NEW FLIGHT INFORMATION', 'end' => 'PREVIOUS FLIGHT INFORMATION'],
                ['start' => 'New (active) schedule', 'end' => 'Previous schedule'],
                ['start' => 'New flight', 'end' => 'Previous flight'],
                ['start' => 'Active flight', 'end' => 'Previous flight'],
                ['start' => 'Active flight', 'end' => 'For further information, please contact'],
                ['start' => 'New seat details', 'end' => 'apologizes for inconvenience caused'],
                ['start' => 'New Seat details', 'end' => 'apologize for the inconvenience this may cause'],
                ['start' => 'New Seat Details', 'end' => 'For assistance, you may'],
                ['start' => 'New Seat Details', 'end' => 'Get in touch with us'],
                ['start' => 'Seat details', 'end' => 'For further information, please contact'],
                ['start' => 'Itinerary information', 'end' => 'For further information, please contact'],
                ['start' => 'Itinerary information', 'end' => 'Some flights may be modified'],
                ['start' => 'Itinerary information', 'end' => 'You may also be interested in'],
                ['start' => 'Here is your reservation information', 'end' => 'Some flights may be modified'],
                ['start' => 'PNE.AACC.AMADEUS.EML.LABEL.ITINERARYTITLE', 'end' => 'Baggage information'],
                ['start' => 'PNE.AACC.AMADEUS.EML.LABEL.ITINERARYTITLE', 'end' => 'Please visit this link to see baggage allowance'],
                ['start' => 'Your Flight Details', 'end' => 'Online Check-In Advantages:'],
                ['start' => 'Flight Details', 'end' => 'Note:'],
                ['start' => 'Flight Details', 'end' => 'IMPORTANT REMINDERS'],
                ['start' => 'Cancelled flight information', 'end' => 'to reschedule your new itinerary'],
                ['start' => 'Itinerary', 'end' => 'We do apologize for any inconvenience caused'],
                ['start' => 'Previous Flight', 'end' => 'We do apologize for any inconvenience caused'],
            ],
            'statusPhrases'  => ['has been'],
            'statusVariants' => ['changed', 'delayed', 'rescheduled', 'retimed', 'issued', 'cancelled', 'canceled'],
            'statusChanged'  => [
                'Operational reasons have led us to modify your flights/schedule',
                'TRAVEL DETAILS HAVE CHANGED', 'your travel details have changed.',
            ],
            'cancelledPhrases' => ['has been cancelled.', 'has been canceled.'],
            'Frequent flyer'   => ['Frequent flyer', 'Frequent Flyer'],
            'Passenger name'   => ['Passenger name', 'PNE.AACC.AMADEUS.EML.LABEL.PASSENGERNAME'],
            'Ticket number'    => ['Ticket number', 'PNE.AACC.AMADEUS.EML.LABEL.TICKETNUMBER'],
            'Departure'        => ['Departure', 'PNE.AACC.AMADEUS.EML.LABEL.DEPARTURE'],
            'Flight'           => ['Flight', 'PNE.AACC.AMADEUS.EML.LABEL.FLIGHT'],
        ],
        'ar' => [
            'Booking reference:' => ':مرجع الحجز',
            // 'Dear'               => ['Dzień dobry', 'Drogi'],
            'segmentsPairs'      => [
                ['start' => 'معلومات عن مسار الرحلة', 'end' => 'لمزيد من المعلومات، يُرجى الاتصال بمكتب الخدمة الخاص بنا'],
            ],
            // 'statusPhrases' => [''],
            // 'statusVariants' => ['zmianie'],
            // 'statusChanged'  => ['ZMIANA HARMONOGRAMU', 'Informujemy, że Twoja podróż uległa zmianie.'],
            // 'cancelledPhrases' => [''],
            // 'Frequent flyer' => ['Uczestnik programu Frequent Flyer', 'Frequent flyer', 'Frequent Flyer'],
            'Passenger name' => 'اسم المسافر',
            'Ticket number'  => 'رقم التذكرة',
            'Departure'      => ['من'],
            'Flight'         => 'رحلة الطيران',
        ],
    ];

    private $code = '';
    private $status;
    private $airlineIataByCode = [
        'mabuhay'  => 'PR',
        'luxair'   => 'LG',
        'china'    => 'CI',
        'malaysia' => 'MH',
        'egyptair' => 'MS',
    ];
    private $bodies = [
        // only xpath criteria
        'flyerbonus' => [
            '//a[contains(@href,".bangkokair.com/") or contains(@href,"www.bangkokair.com") or contains(@href,"flyerbonus.bangkokair.com")]',
            '//*[contains(normalize-space(),"Bangkok Airways gladly welcome you to our service")]',
        ],
        'saudisrabianairlin' => [
            '//a[contains(@href,".saudia.com/") or contains(@href,"www.saudia.com")]',
            '//*[contains(normalize-space(),"manage my booking as saudia.com") or contains(.,"www.saudia.com")]',
        ],
        'lotpair' => [
            '//a[contains(@href,".lot.com/") or contains(@href,"www.lot.com")]',
            '//*[contains(normalize-space(),"LOT Polish Airlines Team") or contains(.,"www.lot.com")]',
        ],
        'mabuhay' => [
            '//a[contains(@href,".philippineairlines.com/") or contains(@href,"www.philippineairlines.com")]',
            '//*[contains(.,"www.philippineairlines.com")]',
        ],
        'luxair' => [
            '//a[contains(@href,"luxair.lu")]',
            '//text()[contains(.,"LUXAIR")]',
            '//node()[contains(normalize-space(.),"Dear Luxair Passenger") or contains(normalize-space(.),"Your Luxair team")]',
        ],
        'aviancataca' => [
            '//a[contains(@href,".avianca.com/") or contains(@href,"www.avianca.com")]',
            '//*[contains(normalize-space(),"status of your ticket on Avianca.com") or contains(normalize-space(),"Enjoy your flight! Avianca")]',
            '//node()[contains(normalize-space(.),"Thank you for flying Avianca") or contains(normalize-space(.),"Avianca team") or contains(.,"@notificacionesavianca.com")]',
        ],
        'malaysia' => [
            '//a[contains(@href,"malaysiaairlines.com")]',
            '//text()[contains(.,"Malaysia Airlines")]',
        ],
        'china' => [
            '//a[contains(@href,".china-airlines.com/") or contains(@href,"www.china-airlines.com")]',
            '//node()[contains(normalize-space(),"Best regards, China Airlines") or contains(.,"www.china-airlines.com")]',
            '//node()[contains(normalize-space(),"NOTICE FROM CHINA AIRLINES")]',
        ],
        'egyptair' => [
            '//a[contains(@href,"egyptair.com")]',
            '//text()[contains(.,"EGYPTAIR")]',
        ],
        'amadeus' => [ // always last!
            '//a[contains(@href,"mediasolutions.amadeus.net")]',
            '//a[contains(@originalsrc,"mediasolutions.amadeus.net")]',
            '//img[contains(@src,"amadeus.net")]',
        ],
    ];
    private static $headers = [
        'flyerbonus' => [
            'from' => ['@bangkokair.com'],
            'subj' => [
                'Trip reminder for Booking',
                'Notify Seats Change of Booking',
                'Notify Schedule Change of Booking',
            ],
        ],
        'saudisrabianairlin' => [
            'from' => ['@saudia.com'],
            'subj' => [
                'Your electronic ticket has been issued',
            ],
        ],
        'lotpair' => [
            'from' => ['no-reply@lot.com'],
            'subj' => [
                'Important information for travellers',
                'Zmiana godziny wylotu/przylotu', // pl
            ],
        ],
        'mabuhay' => [
            'from' => ['no-reply@philippineairlines.com'],
            'subj' => [
                'Has Been Changed',
                'The online check-in of your flight is open',
                'You can now check-in for your Flight',
                'Some changes occurred to your flight',
                'Your seat has changed',
                'The departure time / arrival time of your flight has changed',
                'is now open for online check-in up to 1 hour before your departure time',
            ],
        ],
        'luxair' => [
            'from' => ['do-not-reply@luxair.lu'],
            'subj' => [
                'Your Online Check-In Invitation',
                'Flight delay',
            ],
        ],
        'aviancataca' => [
            'from' => ['notificacionesavianca.com'],
            'subj' => [
                'Your Online Check-In Invitation',
                'Flight delay',
                'Se han producido algunos cambios en el vuelo', // es
            ],
        ],
        'malaysia' => [
            'from' => ['@malaysiaairlines.com'],
            'subj' => [
                'Check-In Open Notification',
                'Flight Retiming Notification',
            ],
        ],
        'egyptair' => [
            'from' => ['no-reply@egyptair.com'],
            'subj' => [
                'Egyptair',
                'Dear',
            ],
        ],
        'china' => [
            'from' => ['no-reply@amadeus.com'],
            'subj' => [
                'Schedule Change Notice from China Airlines Group',
                'China airlines Re-seating Notice',
                'Seat assignment change notice from China Airlines',
            ],
        ],
        'amadeus' => [ // always last!
            'from' => ['no-reply@amadeus.com'],
            'subj' => [],
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    public function detectEmailFromProvider($from)
    {
        if (isset(self::$headers['malaysia']) && isset(self::$headers['malaysia']['from'])) {
            foreach (self::$headers['malaysia']['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $this->status = $headers['subject'];

        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (null !== $this->getProviderByFrom($parser->getCleanFrom()) || null !== $this->getProviderByBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('FlightRetiming' . ucfirst($this->lang));

        if (empty($this->code = $this->getProvider($parser))) {
            $this->logger->debug("Can't determine a provider!");
        }
        $email->setProviderCode($this->code);

        $this->parseEmail($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email): void
    {
        $xpathTime = 'contains(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $f = $email->add()->flight();

        $otaConfirmation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Booking reference:'))}][1]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,10}\s*-\s*(\d+)$/')
            ?? $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Booking reference:'))}][1]", null, true, "/{$this->opt($this->t('Booking reference:'))}[:\s]*[A-Z\d]{5,10}\s*-\s*(\d+)$/");

        if ($otaConfirmation) {
            $f->ota()->confirmation($otaConfirmation);
        }

        $PNRs = $PNRs_temp = [];

        $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Booking reference:'))}][1]/following::text()[normalize-space()][1]", null, true, '/^([A-Z\d]{5,10})(?:\s*-\s*\d+)?$/');

        if ($confirmation && !in_array($confirmation, $PNRs_temp)) {
            $confirmationTitle = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Booking reference:'))}][1][ following::text()[normalize-space()] ]", null, true, '/^(.+?)[\s:：]*$/u');
            $PNRs[] = [$confirmation, $confirmationTitle];
            $PNRs_temp[] = $confirmation;
        } elseif (preg_match("/({$this->opt($this->t('Booking reference:'))})[:\s]*([A-Z\d]{5,10})(?:\s*-\s*\d+)?$/", $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Booking reference:'))}][1]"), $m) && !in_array($m[2], $PNRs_temp)) {
            $PNRs[] = [$m[2], rtrim($m[1], ': ')];
            $PNRs_temp[] = $m[2];
        }

        $airlineConfirmation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Airline Booking reference:'))}][1]/following::text()[normalize-space()][1]", null, true, '/^[,\/ A-Z\d]{5,}$/');

        if ($airlineConfirmation) {
            $confirmationTitle = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Airline Booking reference:'))}][1][ following::text()[normalize-space()] ]", null, true, '/^(.+?)[\s:：]*$/u');
            $airlineConfNumbers = preg_split('/\s*[,]+\s*/', $airlineConfirmation);

            foreach ($airlineConfNumbers as $number) {
                if (preg_match('/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\/\s*([A-Z\d]{5,})$/', $number, $m)) {
                    // UA/D2EN0M
                    if (!in_array($m[2], $PNRs_temp)) {
                        $PNRs[] = [$m[2], $confirmationTitle . ' (' . $m[1] . ')'];
                        $PNRs_temp[] = $m[2];
                    }
                } elseif (!in_array($number, $PNRs_temp)) {
                    // D2EN0M
                    $PNRs[] = [$number, $confirmationTitle];
                    $PNRs_temp[] = $number;
                }
            }
        }

        foreach ($PNRs as $pnr) {
            $f->general()->confirmation($pnr[0], $pnr[1]);
        }

        $areNamesFull = null;
        $travellers = $ffNumbers = [];
        $ffNumberTexts = $this->http->FindNodes("//tr/*[{$this->starts($this->t('Frequent flyer'))}]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()]");

        foreach ($ffNumberTexts as $ffNumberValue) {
            if (preg_match("/^({$patterns['travellerName']})\s*\/\s*([-A-Z\d]{5,})$/u", $ffNumberValue, $m)) {
                // DJAFFARMR HADJI / 311845796
                $m[1] = $this->normalizeTraveller($m[1]);

                if (!in_array($m[1], $travellers)) {
                    $travellers[] = $m[1];
                    $areNamesFull = true;
                }

                if (!in_array($m[2], $ffNumbers)) {
                    $f->program()->account($m[2], false, $m[1]);
                    $ffNumbers[] = $m[2];
                }
            } elseif (preg_match("/\/\s*([-A-Z\d]{5,})$/", $ffNumberValue, $m)) {
                $f->program()->account($m[1], false);
                $ffNumbers[] = $m[1];
            }
        }

        $tickets = [];
        $ticketRows = $this->http->XPath->query("//tr[ *[normalize-space()][1][{$this->eq($this->t('Passenger name'))}] and *[normalize-space()][2][{$this->eq($this->t('Ticket number'))}] ]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()]");

        foreach ($ticketRows as $tktRow) {
            $passengerName = $this->normalizeTraveller($this->http->FindSingleNode("descendant::tr[not(.//tr[normalize-space()]) and normalize-space()]/*[normalize-space()][1]", $tktRow, true, "/^{$patterns['travellerName']}$/u"));

            if ($passengerName && !in_array($passengerName, $travellers)) {
                $travellers[] = $passengerName;
                $areNamesFull = true;
            }

            $ticket = $this->http->FindSingleNode("descendant::tr[not(.//tr[normalize-space()]) and normalize-space()]/*[normalize-space()][2]", $tktRow, true, "/^{$patterns['eTicket']}$/");

            if ($ticket && !in_array($ticket, $tickets)) {
                $f->issued()->ticket($ticket, false, $passengerName);
                $tickets[] = $ticket;
            }
        }

        if (count($travellers) === 0) {
            // Dear Luxair Passenger,
            $stopNames = ['Passenger', 'customer', 'Pasażerze', 'Luxair'];

            $travellerNames = array_filter(
                $this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,.;:!?]|$)/u"),
                function ($item) use ($stopNames) {
                    return !empty($item) && !preg_match("/{$this->opt($stopNames)}/iu", $item);
                }
            );

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = $this->normalizeTraveller(array_shift($travellerNames));
                $travellers = [$traveller];
                $areNamesFull = null;
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers($travellers, $areNamesFull);
        }

        // Status
        if (count($PNRs) === 1 && !empty($PNRs[0]) && !empty($PNRs[0][0])
            && ($status = $this->http->FindSingleNode("//text()[{$this->contains($PNRs[0][0])} and {$this->contains($this->t('statusPhrases'))}]", null, true, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"))
        ) {
            // it-12360020.eml
            $f->general()->status($status);
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('Flight Retiming Notification'))}]")->length > 0) {
            $f->general()->status('retimed');
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('statusChanged'))}]")->length > 0
            || stripos($this->status, 'Change') !== false
        ) {
            $f->general()->status('changed');
        }

        $xpathSegmentsAll = "//tr[ *[{$this->eq($this->t('Departure'))}] and *[{$this->eq($this->t('Flight'))}] ]/following::tr[count(*) > 7 and (count(*[normalize-space()])=5 or count(*[normalize-space()])=6) and not({$this->contains($this->t('Departure'))})]";
        $xpathSegmentsAll2 = "//tr[ *[{$this->eq($this->t('Departure'))}] and *[{$this->eq($this->t('Flight'))}] ]/following::tr[*[3][{$xpathTime}] and *[4][{$xpathTime}] and not({$this->contains($this->t('Departure'))})]";

        // Filtering segments: step 1 (by before/after text)
        foreach ((array) $this->t('segmentsPairs') as $pair) {
            $xpathSegmentsFilters = [];

            if (is_array($pair) && !empty($pair['start'])) {
                $xpathSegmentsFilters[] = "preceding::tr[not(.//tr) and {$this->starts($pair['start'])}]";
                $xpathSegmentsFilters[] = "not(following::tr[not(.//tr) and {$this->starts($pair['start'])}])";
            }

            if (is_array($pair) && !empty($pair['end'])) {
                $xpathSegmentsFilters[] = "following::tr[not(.//tr) and {$this->contains($pair['end'])}]";
                $xpathSegmentsFilters[] = "not(preceding::tr[not(.//tr) and {$this->contains($pair['end'])}])";
            }

            $xpathSegmentsFilter = (count($xpathSegmentsFilters) ? '[' . implode(' and ', $xpathSegmentsFilters) . ']' : '');

            $segments = $this->http->XPath->query($xpathSegmentsAll . $xpathSegmentsFilter);

            if ($segments->length === 0) {
                $segments = $this->http->XPath->query($xpathSegmentsAll2 . $xpathSegmentsFilter);
            }

            if ($segments->length > 0) {
                $this->logger->debug('START SEGMENTS PHRASE: ' . $pair['start']);
                $this->logger->debug('END SEGMENTS PHRASE: ' . $pair['end']);

                break;
            }
        }

        if ($segments->length === 0) {
            $this->logger->debug('Segments not found!');

            return;
        }

        $uniq = [];

        foreach ($segments as $root) {
            $flightNumber = $airline = null;
            $noArrival = false;

            // Filtering segments: step 2 (by flight number)
            $node = $this->http->FindSingleNode("./td[normalize-space(.)!=''][5]", $root);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)$/', $node, $matches)) {
                $flightNumber = $matches['flightNumber'];
                $airline = $matches['airline'];
            } elseif ($this->http->XPath->query("//td[{$this->eq($this->t('Flight'))} and count(preceding-sibling::td[normalize-space()])=3]")->length > 0
                && $this->http->XPath->query("//td[{$this->eq($this->t('Flight'))} and count(preceding-sibling::td[normalize-space()])=3]/preceding-sibling::td[{$this->contains($this->t('Arrival'))}]")->length === 0
            ) {// it-13777738-egyptair.eml
                $noArrival = true;
                $node = $this->http->FindSingleNode("./td[normalize-space(.)!=''][4]", $root);

                if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)$/', $node, $matches)) {
                    $flightNumber = $matches['flightNumber'];
                    $airline = $matches['airline'];
                }
            } else {
                continue;
            }

            //not collect previous info
            if (in_array($flightNumber, $uniq)) {
                continue;
            } else {
                array_push($uniq, $flightNumber);
            }

            $s = $f->addSegment();

            if (!empty($airline)) {
                $s->airline()->name($airline);
            } elseif (!empty($this->code) && !empty($this->airlineIataByCode[$this->code])) {
                $s->airline()->name($this->airlineIataByCode[$this->code]);
            }
            $s->airline()->number($flightNumber);

            $dateDep = $timeDep = $dateArr = $timeArr = null;

            /*
                Oct 10, 2022
                17:25
            */
            $patterns['dateTime'] = "/^\s*(?<date>.*\d.*?)[ ]*\n+[ ]*(?<time>{$patterns['time']})\s*$/";

            /*
                17:25
                Oct 10, 2022
            */
            $patterns['dateTime2'] = "/^\s*(?<time>{$patterns['time']})[ ]*\n+[ ]*(?<date>.*\d.*?)\s*$/";

            $departureVal = $this->htmlToText($this->http->FindHTMLByXpath('*[normalize-space()][3]', null, $root));

            if (preg_match($patterns['dateTime'], $departureVal, $m) || preg_match($patterns['dateTime2'], $departureVal, $m)) {
                $dateDep = $m['date'];
                $timeDep = $m['time'];
            } else {
                $dateDep = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/*[normalize-space()][1]", $root, true, '/^.*\d.*$/');
                $timeDep = $this->http->FindSingleNode("*[normalize-space()][3]", $root, true, "/^{$patterns['time']}$/");
            }

            $s->departure()
                ->name($this->http->FindSingleNode("./td[normalize-space(.)!=''][1]", $root))
                ->date(strtotime($timeDep, $this->normalizeDate($dateDep)));

            if ($noArrival) {
                $s->arrival()
                    ->noDate();
                $node = $this->http->FindSingleNode("./td[normalize-space(.)!=''][5]", $root, true, '/^([A-z]{1,2})$/');

                if (!empty($node)) {
                    $s->extra()
                        ->bookingCode($node);
                }
            } else {
                $arrivalVal = $this->htmlToText($this->http->FindHTMLByXpath('*[normalize-space()][4]', null, $root));

                if (preg_match($patterns['dateTime'], $arrivalVal, $m) || preg_match($patterns['dateTime2'], $arrivalVal, $m)) {
                    $dateArr = $m['date'];
                    $timeArr = $m['time'];
                } else {
                    $dateArr = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/*[normalize-space()][2]", $root, true, '/^.*\d.*$/');
                    $timeArr = $this->http->FindSingleNode("*[normalize-space()][4]", $root, true, "/^{$patterns['time']}$/");
                }

                $s->arrival()
                    ->name($this->http->FindSingleNode("./td[normalize-space(.)!=''][2]", $root))
                    ->date(strtotime($timeArr, $this->normalizeDate($dateArr)));

                $node = $this->http->FindSingleNode("./td[normalize-space(.)!=''][6]", $root, true, '/^([A-z]{1,2})$/');

                if (!empty($node)) {
                    $s->extra()
                        ->bookingCode($node);
                } elseif ($this->http->XPath->query("//td[{$this->eq($this->t('New seat'))} and count(preceding-sibling::td[normalize-space()])=5]")->length > 0) {
                    $seat = $this->http->FindSingleNode("td[normalize-space()][6]", $root, true, '/^\d+[A-z]$/');

                    if (!empty($seat)) {
                        $passengerName = count($travellers) === 1 ? array_shift($travellers) : null;
                        $s->extra()->seat($seat, false, false, $passengerName);
                    }
                }
            }
            // DepCode
            // ArrCode
            // not to be confused with the city code!
            $s->departure()->noCode();
            $s->arrival()->noCode();
        }

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            // it-12360020.eml
            $f->general()->cancelled();
        }
    }

    private function getProviderByFrom($from): ?string
    {
        foreach (self::$headers as $code => $arr) {
            if (!array_key_exists('from', $arr) || !is_array($arr['from']) || count($arr['from']) === 0) {
                continue;
            }

            foreach ($arr['from'] as $f) {
                if (empty($f)) {
                    continue;
                }

                if (stripos($from, $f) !== false) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function getProviderByBody(): ?string
    {
        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ($this->http->XPath->query($search)->length > 0) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function getProvider(\PlancakeEmailParser $parser): ?string
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (!empty($this->code)) {
            return $this->code;
        } else {
            return $this->getProviderByBody();
        }
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MSTR|MISS|MRS|MR|MS)';

        return preg_replace([
            "/^(.{2,}?)\s+{$namePrefixes}[.\s]*$/i",
            "/^{$namePrefixes}[.\s]+(.{2,})$/i",
        ], [
            '$1',
            '$1',
        ], $s);
    }

    private function normalizeDate($str)
    {
        $in = [
            // May 25, 2016
            "/^([[:alpha:]]+)\s+(\d{1,2})[,\s]+(\d{4})\b/u",
            // 10 févr. 2017
            '/^(\d{1,2})[,.\s]+([[:alpha:]]+)[,.\s]*(\d{4})\b/u',
        ];
        $out = [
            "$2 $1 $3",
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $str));

        return strtotime($str);
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dict, $this->lang) || empty(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody[0])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($reBody[1])}]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
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
}
