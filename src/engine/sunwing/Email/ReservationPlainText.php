<?php

namespace AwardWallet\Engine\sunwing\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationPlainText extends \TAccountChecker
{
    public $mailFiles = "sunwing/it-22898839.eml, sunwing/it-23263059.eml, sunwing/it-24178785.eml, sunwing/it-39142949.eml, sunwing/it-42660884.eml, sunwing/it-434625737.eml, sunwing/it-589412546.eml, sunwing/it-671567139.eml, sunwing/it-676846146.eml, sunwing/it-760962115.eml";

    private $date;
    private $lang = '';
    private static $detectProvider = [
        'sunwing' => [
            'from' => '@sunwing.ca',
            'body' => ['Sunwing Vacations', 'SUNWING VACATIONS'],
        ],
        'maritime' => [
            'from' => '@maritimetravel.ca',
            'body' => ['Maritime Travel'],
        ],
        'airtransat' => [
            // 'from' => '@sunwing.ca',
            'body' => ['Air Transat', 'Transat'],
        ],
        'westjet' => [
            // 'from' => '@sunwing.ca',
            'body' => ['Westjet Vacations'],
        ],
        'mta' => [
            // 'from' => '@sunwing.ca',
            'body' => ['Gogo Vacations'],
        ],
        'aeroplan' => [
            'from' => ['@vacancesaircanada.com', '@aircanadavacations.com'],
            'body' => ['Air Canada Vacations', 'Vacances Air Canada'],
        ],
    ];
    private static $dict = [
        'en' => [
            'Travel provided by'              => ['Travel provided by'],
            'your travel arrangements with'   => ['your travel arrangements with', 'you for booking with'],
            'Confirmation Date'               => ['Confirmation Date', 'ConfirmationDate', 'Confirmation date', 'Confirmationdate'],
            'BOOKING DETAILS'                 => ['BOOKING DETAILS', 'Booking Details'],
            'ITINERARY DETAILS'               => ['ITINERARY DETAILS', 'FLIGHT DETAILS', 'Flight Details', 'Flight itinerary'],
            'fSegmentsSplitter'               => ['DEPARTING', 'RETURNING'],
            'Confirmation number'             => ['Confirmation number', 'Booking number'],
            'Class'                           => ['Class', 'class'],
            'PASSENGER INFORMATION'           => ['PASSENGER INFORMATION', 'Passenger Details'],
            'Check-in Date'                   => ['Check-in Date', 'Check in date'],
            'Check-out Date'                  => ['Check-out Date', 'Check out date'],
            'Room'                            => ['Room Type', 'Room'],
        ],
        'fr' => [
            'your travel arrangements with'   => ["effectué votre réservation avec"],
            'Travel provided by'              => ['Prestations fournies par'],
            'Confirmation Date'               => ['Date de confirmation'],
            'BOOKING DETAILS'                 => ['DÉTAILS DE LA RÉSERVATION'],
            'ITINERARY DETAILS'               => ["DÉTAILS DE L'ITINÉRAIRE"],
            'fSegmentsSplitter'               => ['DÉPART', 'RETOUR'],
            'Confirmation number'             => ['Numéro de confirmation', 'Numéro de réservation'],
            'PASSENGER INFORMATION'           => ['INFORMATION DE(S) PASSAGER(S)', 'INFORMATIONS DES PASSAGERS'],
            'Address'                         => 'Adresse',
            // 'Hotel Name' => '',
            // 'Grand total price' => '',
            'Total'          => 'Montant',
            'Booking Status' => 'Statut de la réservation',
            'Class'          => ['Classe', 'class'],
            'Flight'         => 'Vol',
            'Departure'      => 'Départ',
            'Arrival'        => 'Arrivée',
            'PAYMENT'        => 'PAIEMENT',
            // 'Hotel name' => '',
            // 'Address' => '',
            // 'City' => '',
            // 'Check-in Date' => '',
            // 'Check-out Date' => '',
            // 'Departing date' => '',
            // 'Return date' => '',
            // 'Room' => '',
        ],
    ];
    private $travellers = [];
    private $providerCode = '';

    private $patterns = [
        'travellerName' => '[A-Z][-.\'A-z ]*[A-z]', // MR. HAO-LI HUANG  |   Mrs Margherita  Seconnino
    ];

    // Standard Methods
    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectProvider as $code => $prov) {
            if (empty($prov['from'])) {
                continue;
            }

            if ($this->containsText($from, $prov['from']) === true) {
                $this->providerCode = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ((!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true)
            && (!array_key_exists('subject', $headers) || strpos($headers['subject'], 'Sunwing') === false)
        ) {
            return false;
        }

        return strpos($headers['subject'], 'Reservation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = $parser->getHTMLBody();
        }
        $textBody = str_replace(['&nbsp;', chr(194) . chr(160), '&#160;'], ' ', $textBody);
        $textBody = preg_replace("#[\r\n\t]+#ums", "\n", $textBody);
        $textBody = $this->htmlToText($textBody, false);

        return $this->assignLang($textBody);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = $parser->getHTMLBody();
        }

        $textBody = str_replace(['&nbsp;', chr(194) . chr(160), '&#160;'], ' ', $textBody);
        $textBody = preg_replace("#[\r\n\t]+#ums", "\n", $textBody);
        $textBody = $this->htmlToText($textBody, false);

        $this->assignLang($textBody);

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        if (empty($this->providerCode)) {
            foreach (self::$detectProvider as $code => $prov) {
                if (empty($prov['from'])) {
                    continue;
                }

                if ($this->containsText($parser->getCleanFrom(), $prov['from']) !== false) {
                    $this->providerCode = $code;

                    return true;
                }
            }
        }

        $email->setProviderCode($this->providerCode);
        $email->setType('ReservationPlainText' . ucfirst($this->lang));

        $this->date = strtotime($parser->getDate());

        $email->obtainTravelAgency();

        // ta.confirmationNumbers
        if (preg_match("/({$this->opt($this->t('Confirmation number'))})\s*[:]+\s*(?-i)([-A-Z\d]{5,35})\s/i", $textBody, $matches)) {
            $email->ota()->confirmation($matches[2], $matches[1]);
        }

        // travellers
        $travellersText = preg_match('/^[> ]*' . $this->opt($this->t('PASSENGER INFORMATION')) . '\s+(.+?)^[> ]*' . $this->opt($this->t('Address')) . '\s*:\s*/ms', $textBody, $m) ? $m[1] : '';
        // Passenger #1 : Mrs Margherita Seconnino
        if (preg_match_all("#^.+?\#?\s*\d{1,3}\s*:\n*\s*(.+?)\s*(?:\(|$)#m", $travellersText, $travellerMatches)) {
            $travellers = [];

            foreach ($travellerMatches[1] as $travellerText) {
                if (preg_match("/^({$this->patterns['travellerName']})(?:\s+\d{1,3})?,?\s+(?:[^\d\W]{3,}\s+\d{1,2}\s+\d{2,4}|\d{4}-\d{2}-\d{2})$/", $travellerText, $m)) {
                    // MS EVA MARIE B MISHEV 7 Sep 21 2008
                    // MRS TILKIE DAI SINGH 1960-04-09
                    $travellers[] = $m[1];
                } elseif (preg_match("/^({$this->patterns['travellerName']})\s*[Aa][Gg][Ee]\s*[:]+\s*\d{0,3}$/", $travellerText, $m)) {
                    // Mrs Margherita Seconnino Age:35
                    $travellers[] = $m[1];
                } elseif (preg_match("/^\s*({$this->patterns['travellerName']})\s*$/", $travellerText, $m)) {
                    // MS EVDOKIYA UALID YAKUB
                    $travellers[] = $m[1];
                }
            }
            $travellers = preg_replace("/^\s*(Mme|M)\.?\s+/", '', $travellers);
            $this->travellers = array_map([$this, "nice"], $travellers);
        }

        if (preg_match("#{$this->opt($this->t('ITINERARY DETAILS'))}#", $textBody)) {
            $this->parseFlightPlainText($email, $textBody);
        }

        if (preg_match("#{$this->opt($this->t('Hotel Name'))}#i", $textBody)) {
            $this->parseHotelPlainText($email, $textBody);
        }
        // price
        $totalPrice = preg_match("#^[>\s]*{$this->opt($this->t('Grand total price'))}\s*:\s*(.+)#m", $textBody, $m) ? $m[1] : '';

        if (!$totalPrice) {
            $totalPrice = preg_match("#^[>\s]*{$this->opt($this->t('Total'))}\s*:?\s*(.*\d+.*)#m", $textBody, $m) ? $m[1] : '';
        }

        if (preg_match("#(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})(?:[^A-Z]|\b)#", $totalPrice, $matches)) {
            // $2262.00 CAD
            if (count($email->getItineraries()) === 1) {
                $email->getItineraries()[0]->price()
                    ->total($this->normalizeAmount($matches['amount']))
                    ->currency($matches['currency']);
            }
            $email->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency']);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 2; // flight | hotel
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function assignLang($text): bool
    {
        foreach (self::$dict as $lang => $dict) {
            foreach (self::$detectProvider as $code => $prov) {
                if ($this->providerCode) {
                    break;
                }

                if (empty($prov['body'])) {
                    continue;
                }

                if (!empty($dict['Travel provided by']) && preg_match("/\n\s*{$this->opt($dict['Travel provided by'])}\s*[:]+\s*{$this->opt($prov['body'])}/iu", $text)
                    || !empty($dict['your travel arrangements with']) && preg_match("/{$this->opt($dict['your travel arrangements with'])}\s+{$this->opt($prov['body'])}\s*[.;!]/iu", $text)
                ) {
                    $this->providerCode = $code;
                }
            }

            if ($this->providerCode && !empty($dict['Travel provided by'])
                && $this->containsText($text, $dict['Travel provided by']) === true
                && (
                    (!empty($dict['ITINERARY DETAILS']) && $this->containsText($text, $dict['ITINERARY DETAILS']) === true)
                    || (!empty($dict['BOOKING DETAILS']) && $this->containsText($text, $dict['BOOKING DETAILS']) === true)
                )
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseFlightPlainText(Email $email, string $text): void
    {
        $this->logger->debug(__FUNCTION__);

        $f = $email->add()->flight();

        // status
        $bookingStatus = preg_match('/' . $this->opt($this->t('Booking Status')) . '\s*:\s*(.+)/i', $text, $m) ? $m[1] : '';
        $f->general()->status($bookingStatus);

        // travellers
        if (!empty($this->travellers)) {
            $f->general()->travellers($this->travellers, true);
        }

        // reservationDate
        $confirmationDate = preg_match('/' . $this->opt($this->t('Confirmation Date')) . '\s*:\s*(.+)/', $text, $m) ? $m[1] : '';
        $f->general()->date($this->normalizeDate($confirmationDate));
        $this->date = $this->normalizeDate($confirmationDate);

        // WS #418 (class Y) Economy
        $patterns['flight1'] = '(?<airline>[^\#]{2,}?)\s*#?\s*(?<flightNumber>\d+)\s+\(' . $this->opt($this->t('Class')) . '\s+(?<class>[A-Z]{1,2})\)\s*(?<cabin>.+)';
        // Sunwing Airlines #601    Class : P
        $patterns['flight'] = '(?<airline>[^\#]{2,}?)\s*#?\s*(?<flightNumber>\d+)(?:\s+' . $this->opt($this->t('Class')) . '\s*:\s*(?<class>[A-Z]{1,2}))?\b';
        // # AC1810 Economy Class (F)
        $patterns['flight2'] = '\#\s*(?<airline>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<flightNumber>\d+)\s+(?<cabin>.+)\s+\((?<class>[A-Z]{1,2})\)';
        // Ottawa (YOW) - Sun Nov 19 2017 6:55 PM via Cayo Coco
        $patterns['airportDate'] = '(?<name>.{3,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\s*-\s*(?<dateTime>.{6,})';
        // YYZ - AUG-14-2019 10:00
        $patterns['airportDate1'] = '(?<code>[A-Z]{3})\s*-\s*(?<dateTime>.{6,})';

        $flightText = preg_match("/^[> ]*{$this->opt($this->t('ITINERARY DETAILS'))}\s+(.+?)\s+^[> ]*(?:{$this->opt($this->t('PASSENGER INFORMATION'))}|{$this->opt($this->t('PAYMENT'))})/ms", $text, $m) ? $m[1] : '';
        $segments = $this->splitter("#\n[> ]*({$this->opt($this->t('Flight'))}\s*[:\#]\s*)#u", "\n\n" . $flightText);

        foreach ($segments as $segment) {
            if (empty(trim($segment, ": "))) {
                continue;
            }

            $s = $f->addSegment();

            // airlineName
            // flightNumber
            // bookingCode
            if (preg_match('/' . $this->opt($this->t('Flight')) . '\s*:\s*' . $patterns['flight1'] . '/', $segment, $matches)) {
                $s->airline()
                    ->name($matches['airline'])
                    ->number($matches['flightNumber']);

                if (!empty($matches['class'])) {
                    $s->extra()->bookingCode($matches['class']);
                }

                if (!empty($matches['cabin'])) {
                    $matches['cabin'] = preg_replace('/(^\s*' . $this->opt($this->t('Class')) . '\s+|\s+' . $this->opt($this->t('Class')) . '\s*)/', '', $matches['cabin']);
                    $s->extra()->cabin($matches['cabin']);
                }
            } elseif (preg_match('/' . $this->opt($this->t('Flight')) . '\s*:\s*' . $patterns['flight'] . '/', $segment, $matches)) {
                $s->airline()
                    ->name($matches['airline'])
                    ->number($matches['flightNumber']);

                if (!empty($matches['class'])) {
                    $s->extra()->bookingCode($matches['class']);
                }
            } elseif (preg_match('/' . $this->opt($this->t('Flight')) . '\s*:\s*' . $patterns['flight2'] . '/', $segment, $matches)) {
                $s->airline()
                    ->name($matches['airline'])
                    ->number($matches['flightNumber']);
                $matches['cabin'] = preg_replace('/(^\s*' . $this->opt($this->t('Class')) . '\s+|\s+' . $this->opt($this->t('Class')) . '\s*)/', '', $matches['cabin']);
                $s->extra()
                        ->cabin($matches['cabin'])
                        ->bookingCode($matches['class']);
            }

            // depName
            // depDate
            if (preg_match('/' . $this->opt($this->t('Departure')) . '\s*:\s*' . $patterns['airportDate'] . '/', $segment, $matches)
                || preg_match('/' . $this->opt($this->t('Departure')) . '\s*:\s*' . $patterns['airportDate1'] . '/', $segment, $matches)) {
                if (isset($matches['name'])) {
                    $s->departure()
                    ->name($matches['name']);
                }
                $s->departure()
                    ->code($matches['code'])
                    ->date($this->normalizeDate($matches['dateTime']));
            }

            // arrName
            // arrDate
            if (preg_match('/' . $this->opt($this->t('Arrival')) . '\s*:\s*' . $patterns['airportDate'] . '/', $segment, $matches)
            || preg_match('/' . $this->opt($this->t('Arrival')) . '\s*:\s*' . $patterns['airportDate1'] . '/', $segment, $matches)) {
                if (isset($matches['name'])) {
                    $s->arrival()
                    ->name($matches['name']);
                }
                $s->arrival()
                    ->code($matches['code'])
                    ->date($this->normalizeDate($matches['dateTime']));
            }

            if (preg_match('/' . $this->opt($this->t('Operated by')) . '\s*:\s*(.+)/', $segment, $matches)) {
                $s->airline()
                    ->operator($matches[1]);
            }

            if (empty($s->getFlightNumber())) {
                $re = "/{$this->opt($this->t('Flight'))}\s*\#\s*(?<flightNumber>\d+)\s+\(\s*{$this->opt($this->t('Class'))}\s+(?<class>[A-Z]{1,2})\)\s*(?<cabin>.+?)\s*"
                    . "(?:\s*{$this->opt($this->t('Operated by'))}\s*:\s*(?<operator>.+))?"
                    . "(?: {3,}|\n)\s*(?<dName>.+?)\s*\(\s*(?<dCode>[A-Z]{3})\s*\)\n(?<dDate>.+)\n(?<dTime>\d{1,2}:\d{2})\s*"
                    . "(?: {3,}|\n)(?<aName>.+?)\s*\(\s*(?<aCode>[A-Z]{3})\s*\)\n(?<aDate>.+)\n(?<aTime>\d{1,2}:\d{2})\s*(?:\n|$)"
                    . "/u";
                // $this->logger->debug('$re = '.print_r( $re,true));
                if (preg_match($re, $segment, $m)) {
                    $s->airline()
                        ->noName()
                        ->number($m['flightNumber']);

                    if (!empty($m['operator'])) {
                        $s->airline()
                            ->operator($m['operator']);
                    }

                    $s->extra()
                        ->bookingCode($m['class']);

                    $m['cabin'] = preg_replace("/\s*{$this->opt($this->t('Operated by'))}\s*:\s*.+/s", '', $m['cabin'] ?? '');

                    if (!empty(trim($m['cabin']))) {
                        $s->extra()
                            ->cabin($m['cabin']);
                    }

                    $s->departure()
                        ->name($m['dName'])
                        ->code($m['dCode'])
                        ->date($this->normalizeDate($m['dDate'] . ', ' . $m['dTime']));

                    $s->arrival()
                        ->name($m['aName'])
                        ->code($m['aCode'])
                        ->date($this->normalizeDate($m['aDate'] . ', ' . $m['aTime']));
                }
            }
        }

        // confirmationNumbers
        $f->general()->noConfirmation();
    }

    private function parseHotelPlainText(Email $email, string $text): void
    {
        $this->logger->debug(__FUNCTION__);

        $r = $email->add()->hotel();

        // status
        $bookingStatus = preg_match("#{$this->opt($this->t('Booking Status'))}\s*:\s*(.+)#i", $text, $m) ? $m[1] : '';
        $r->general()->status($bookingStatus);

        // travellers
        if (!empty($this->travellers)) {
            $r->general()->travellers($this->travellers, true);
        }

        // reservationDate
        $confirmationDate = preg_match("#{$this->opt($this->t('Confirmation Date'))}\s*:\s*(.+)#", $text, $m) ? $m[1] : '';
        $r->general()->date($this->normalizeDate($confirmationDate));

        // confirmationNumbers
        $r->general()->noConfirmation();

        $textHotel = $this->re("#^(.+?)\s+(?:{$this->opt($this->t('PASSENGER INFORMATION'))}|{$this->opt($this->t('PAYMENT'))}|$)#s", $text);
        // hotel info
        $hotelName = $this->re("/\n[> ]*{$this->opt($this->t('Hotel name'))}\s*:\s*(\S.+)/i", $textHotel);
        $address = $this->re("/\n[> ]*{$this->opt($this->t('Address'))}\s*:\s*(\S.{2,})/i", $textHotel) . ', ' . $this->re("/\n[> ]*{$this->opt($this->t('City'))}\s*:\s*(\S.+)/", $textHotel);

        if (!empty(trim($address, ' ,'))) {
            $r->hotel()
                ->name($hotelName)
                ->address($address);
        } else {
            $hotelName = $this->re("/\n[> ]*{$this->opt($this->t('Hotel name'))}\s*:\s*(.+?)[>\s]+(?:{$this->opt($this->t('Room'))}|{$this->opt($this->t('Occupancy'))})/is", $textHotel);
            $r->hotel()->noAddress()->name($this->nice($hotelName));
        }

        // check-in, check-out
        $checkIn = $this->re("#^[> ]*{$this->opt($this->t('Check-in Date'))}\s*:\s*(.+)#m", $textHotel);
        $checkOut = $this->re("#^[> ]*{$this->opt($this->t('Check-out Date'))}\s*:\s*(.+)#m", $textHotel);

        if ((empty($checkIn) || empty($checkOut)) && !empty($r->getHotelName())) {
            $checkIn = $this->re("#^[> ]*{$this->opt($this->t('Departing date'))}\s*:\s*(.+)#m", $textHotel);
            $checkOut = $this->re("#^[> ]*{$this->opt($this->t('Return date'))}\s*:\s*(.+)#m", $textHotel);
        }
        $r->booked()
            ->checkIn2($checkIn)
            ->checkOut2($checkOut);

        // room type
        $room = $r->addRoom();
        $roomType = $this->nice($this->re("#\n[> ]*{$this->opt($this->t('Room'))}\s*:\s*(.+?)\n[> ]*(?:{$this->t('Occupancy')}|\n)#s", $textHotel));

        if (empty($roomType)) {
            if (preg_match("#^([\w \-]+?)\s+([A-Z\- ]+)$#u", $r->getHotelName(), $m) && preg_match("#[a-z]#", $m[1])) {
                $r->hotel()->name($m[1]);
                $roomType = $m[2];
            } else {
                $email->removeItinerary($r);

                return;
            }
        }
        $room->setType($roomType);
    }

    private function normalizeDate(string $string)
    {
        // $this->logger->debug('$string 1 = '.print_r( $string,true));
        $year = date("Y", $this->date);
        $in = [
            // Wed 08 Aug 18:12:37 2018
            '/^[^\d\W]{2,}\s+(\d{1,2}\s+[^\d\W]{3,})\s+(\d{1,2}:\d{1,2}):\d{1,2}\s+(\d{4})$/u',
            // Saturday 05 October, 2024, 11:15
            // Samedi, 01 Juin 2024 - 10 h 23
            '/^[^\d\W]{2,},?\s+(\d{1,2})\s+([^\d\W]{3,})\s*[,\s]\s*(\d{4})\s*[,\-]\s*(\d{1,2})(?::| h )(\d{1,2})\s*$/u',
            // Tue Jun 11 12:13:15 2019
            '/^[^\d\W]{2,}\s+([^\d\W]{3,})\s+(\d{1,2})\s+(\d{1,2}:\d{1,2}):\d{1,2}\s+(\d{4})$/u',
            // Sat Dec 17 2016, 1:49:11 EST
            '/^[^\d\W]{2,}?\,?\s+([^\d\W]{3,})\s+(\d{1,2})\,?\s+(\d{4})[,\s\-]+(\d{1,2}:\d{1,2}):\d{1,2}\s*(?:EST|EDT)?\s*$/u',
            // MAI-14-2024 12:20
            '/^\s*([[:alpha:]]+)-(\d{1,2})-(\d{4})\s+(\d{1,2}:\d{1,2}(?:\s*[AaPp][Mm])?)\s*$/ui',
            // February 22 8:43 PM
            '/^\s*([[:alpha:]]+)\s+(\d+)\s+(\d{1,2}:\d{2}( *[AP]M)?)$/ui',
            // 22 February 8:43 PM; 29 juillet 08 h 00
            '/^\s*(\d+)\s+([[:alpha:]]+)\s+(\d{1,2})(?::| h )(\d{2}( *[AP]M)?)$/ui',

            // Monday, November 06, 2023 - 8:53 AM; Sun Aug 05 2018  7:55 PM; Fri. Dec 21, 2018 06:15
            // Sun Nov 19 2017 6:55 PM via Cayo Coco
            '/^\s*[[:alpha:]]+\s*[,.\s]\s*([[:alpha:]]+)\s+(\d{1,2})\s*[,\s]\s*(\d{4})\s*[,\s\-]\s*(\d{1,2}:\d{2}( *[AP]M)?)(?:\s+via\s+.{3,})?$/ui',
            // December 28, 2018 14:20
            '/^\s*([[:alpha:]]+)\s+(\d{1,2})\s*[,\s]\s*(\d{4})\s*[,\s\-]\s*(\d{1,2}:\d{2}( *[AP]M)?)$/ui',
        ];

        // $year - for date without year and with week
        // %year% - for date without year and without week

        $out = [
            '$1 $3, $2',
            '$1 $2 $3, $4:$5',
            '$2 $1 $4, $3',
            '$2 $1 $3, $4',
            '$2 $1 $3, $4',
            '$2 $1 %year%, $3',
            '$1 $2 %year%, $3:$4',

            '$2 $1 $3, $4',
            '$2 $1 $3, $4',
        ];

        $string = preg_replace($in, $out, trim($string));

        // $this->logger->debug('$string 2 = '.print_r( $string,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%year%)#", $string, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $string = str_replace($m[1], $en, $string);
            }
        }

        if (!empty($this->date) && $this->date > strtotime('01.01.2000') && strpos($string, '%year%') !== false && preg_match('/^\s*(?<date>\d+ \w+) %year%(?:\s*,\s(?<time>\d{1,2}:\d{1,2}.*))?$/', $string, $m)) {
            // $this->logger->debug('$date (no week, no year) = '.print_r( $m['date'],true));
            $string = EmailDateHelper::parseDateRelative($m['date'], $this->date);

            if (!empty($string) && !empty($m['time'])) {
                return strtotime($m['time'], $string);
            }

            return $string;
        } elseif ($year > 2000 && preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $string, $m)) {
            // $this->logger->debug('$date (week no year) = '.print_r( $string,true));
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/^\d+ [[:alpha:]]+ \d{4}(,\s*\d{1,2}:\d{2}(?: ?[ap]m)?)?$/ui", $string)) {
            // $this->logger->debug('$date (year) = '.print_r( $str,true));
            return strtotime($string);
        } else {
            return null;
        }

        return null;
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
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

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (mb_stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && mb_stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
