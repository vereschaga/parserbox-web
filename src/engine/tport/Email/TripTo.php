<?php

namespace AwardWallet\Engine\tport\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Common\Rental;
use AwardWallet\Schema\Parser\Common\Train;
use AwardWallet\Schema\Parser\Email\Email;

class TripTo extends \TAccountChecker
{
    public $mailFiles = "tport/it-27089675.eml, tport/it-27144636.eml, tport/it-27182527.eml, tport/it-32676579.eml, tport/it-39869290.eml, tport/it-39883521.eml, tport/it-49540757.eml, tport/it-62974324.eml, tport/it-66735228.eml, tport/it-67027510.eml, tport/it-67222476.eml, tport/it-171469105-transavia.eml";

    private $langDetectors = [
        'ja' => ['出発：', 'チェックアウト'],
        'de' => ['Anreise'],
        'es' => ['Sale', 'Llega'],
        'pt' => ['Parte', 'Chega'],
        'fr' => ['Départ'],
        'en' => ['Arrives', 'Check-out'],
    ];
    private $lang = '';
    private static $dict = [
        'ja' => [
            // FLIGHT
            'Flight'   => 'フライト',
            'to'       => '終了',
            'Departs'  => '出発：',
            'Arrives'  => '到着',
            'Terminal' => 'ターミナル',
            'Duration' => '所要時間',
            //            'Seats' => '',
            // HOTEL
            'Accommodation' => '宿泊',
            'Check-in'      => 'チェックイン',
            'Check-out'     => 'チェックアウト',
            'Address'       => '住所',
            'Phone'         => '電話',
            // RAIL
            //            'Rail' => '',
            //            'Arrives by' => '',
            //            'Operated by' => '',
            // EVENT
            //            'Event Name:' => '',
            //            'Provider:' => '',
        ],
        'de' => [
            // FLIGHT
            'Flight'  => 'Flug',
            'to'      => 'nach',
            'Departs' => 'Start',
            'Arrives' => 'Anreise',
            //            'Terminal' => '',
            //            'Seats' => '',
            'Duration' => 'Dauer',
            // HOTEL
            //            'Accommodation' => '',
            //            'Check-in' => '',
            //            'Check-out' => '',
            //            'Address' => '',
            //            'Phone' => '',
            // RAIL
            //            'Rail' => '',
            //            'Arrives by' => '',
            //            'Operated by' => '',
            // EVENT
            //            'Event Name:' => '',
            //            'Provider:' => '',
        ],
        'es' => [
            // FLIGHT
            'Flight'  => 'Vuelo',
            'to'      => 'hasta',
            'Departs' => 'Sale',
            'Arrives' => 'Llega',
            //            'Terminal' => '',
            'Seats'    => 'Asientos',
            'Duration' => 'Duración',
            // HOTEL
            'Accommodation' => 'Alojamiento',
            'Participants'  => 'Participantes',
            'Check-in'      => ['Check-in'],
            'Check-out'     => 'Salida',
            'Address'       => 'Dirección',
            'Phone'         => 'Teléfono',
            // RAIL
            'Rail'        => 'Tren',
            'Arrives by'  => 'Llega',
            'Operated by' => 'Operado por',
            // EVENT
            //            'Event Name:' => '',
            'Origin City' => 'Ciudad de origen',
            //            'Provider:' => '',
        ],
        'pt' => [
            // FLIGHT
            'Flight'  => 'Voo',
            'to'      => 'até',
            'Departs' => 'Parte',
            'Arrives' => 'Chega',
            //            'Terminal' => '',
            'Seats'    => 'Lugares',
            'Duration' => 'Duração',
            // HOTEL
            'Accommodation' => 'Alojamento',
            'Participants'  => 'Participantes',
            //            'Check-in' => '',
            //            'Check-out' => '',
            'Address' => 'Endereço',
            'Phone'   => 'Telefone',
            // RAIL
            'Rail'        => 'Comboio',
            'Arrives by'  => 'Chega',
            'Operated by' => 'Operado por',
            // EVENT
            //            'Event Name:' => '',
            //            'Provider:' => '',
            'Origin City' => 'Cidade de origem',
        ],
        'fr' => [
            // FLIGHT
            'Flight'  => 'Vol',
            'to'      => 'à',
            'Departs' => 'Départ',
            'Arrives' => 'Arrive',
            // 'Terminal' => '',
            // 'Seats'    => '',
            'Duration' => 'Durée',
            // HOTEL
            // 'Accommodation' => '',
            // 'Participants'  => '',
            // 'Check-in' => '',
            // 'Check-out' => '',
            // 'Address' => '',
            // 'Phone'   => '',
            // RAIL
            // 'Rail'        => '',
            // 'Arrives by'  => '',
            // 'Operated by' => '',
            // EVENT
            // 'Event Name:' => '',
            // 'Provider:' => '',
            // 'Origin City' => '',
        ],
        'en' => [],
    ];
    private $code;
    // private $itsCheckOut;
    private static $providers = [
        'tport' => [
            'from' => ['@travelport.com', '@mttnow.com'],
            'subj' => [
                // ja
                'さんがあなたと旅程を共有しました',
                // de
                'hat eine gemeinsame Reiseroute mit Ihnen',
                // en
                'has shared a trip itinerary with you',
            ],
            'body' => [
                '//a[contains(@href,"twitter.com/tvptdigital")]',
                '//img[contains(@alt,"axess-logo") or contains(@src,"/axess-logo.png")]',
                'Travelport Digital',
            ],
        ],
        'transavia' => [
            'from' => ['transavia@ada.mttnow.com'],
            'subj' => [
                // fr
                'Un itinéraire de voyage a été partagé avec vous',
                // en
                'A trip itinerary has been shared with you',
            ],
            'body' => [
                '//a[contains(@href,"twitter.com/transavia")]',
                '//img[contains(@alt,"transavia_email_logo") or contains(@src,"/transavia_email_logo.")]',
                '//div[normalize-space()="Transavia Airlines C.V."]',
            ],
        ],
        'xlt' => [
            'from' => ['xlgo@addtrip.net'],
            'subj' => [
                // ja
                'さんがあなたと旅程を共有しました',
                // de
                'hat eine gemeinsame Reiseroute mit Ihnen',
                // en
                'has shared a trip itinerary with you',
            ],
            'body' => [
                '//a[contains(@href,"twitter.com/xltravelho")]',
                '//img[contains(@alt,"xl-logo") or contains(@src,"/xl-logo.png")]',
            ],
        ],
        'fiji' => [
            'from' => ['Fiji'],
            'subj' => [
                // en
                'has shared a trip itinerary with you ',
            ],
            'body' => [
                '//a[contains(@href,"https://web.facebook.com/fijiairways/")]',
                '//img[contains(@src,"fiji-logo.png?tenantId=fiji")]',
            ],
        ],
        'jtb' => [
            'from' => ['jtb@ada.mttnow.com', 'JTB Business Travel Solutions'],
            'subj' => [
                // en
                'has shared a trip itinerary with you ',
            ],
            'body' => [
                '//img[contains(@src,"jtb-logo") or contains(@alt,"jtb-logo")]',
            ],
        ],
    ];
    private $date = 0;
    private $patterns = [];

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$providers as $code => $arr) {
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
        if (null === $this->getProviderByBody()) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);
        $email->setType('TripTo' . ucfirst($this->lang));

        if (null !== ($code = $this->getProvider($parser))) {
            $email->setProviderCode($code);
        }

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
        $this->patterns = [
            'time'  => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon|\s*午[前後])?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon    |    3:10 午後
            'phone' => '[+(\d][-. \/\d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992    |    66 2/6592888
        ];

        $tripSegments = $this->http->XPath->query("//tr[not(.//tr) and ./*[2][string-length(normalize-space(.))>2]]/*[1]/descendant::img[contains(@src,'.mttnow.com/')]");

        foreach ($tripSegments as $key => $tripSegment) {
            $condition1 = $this->http->XPath->query("./@alt[{$this->eq(['Flight', 'Accommodation', 'Rail'])}]", $tripSegment)->length === 0;
            $condition2 = $this->http->XPath->query("./@src[{$this->contains(['/flight.', '/accommodation.', '/rail.'])}]", $tripSegment)->length === 0;
            $condition3 = $this->http->XPath->query("./following::td[normalize-space(.)][1][{$this->starts(array_merge((array) $this->t('Flight'), (array) $this->t('Accommodation'), (array) $this->t('Rail')))}]", $tripSegment)->length === 0;

            if ($condition1 && $condition2 && $condition3) {
                $this->logger->debug("Found unsupported format in trip segment-$key!");

                return;
            }
        }

        $this->parseFlight($email);
        $this->parseHotel($email);
        $this->parseRental($email);
        $this->parseRail($email);
        $this->parseEvent($email);
    }

    private function parseFlight(Email $email): void
    {
        $xpath = "//text()[{$this->starts($this->t('Flight'))}]/ancestor::tr[ ./following-sibling::tr[normalize-space(.)][1][contains(.,'→')] ][1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            return;
        }
        $f = $email->add()->flight();

        // confirmation number
        $f->general()->noConfirmation();

        $travellers = [];

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            // airlineName
            // flightNumber
            if (preg_match("/{$this->opt($this->t('Flight'))}\s+(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)/", $segment->nodeValue, $matches)) {
                $s->airline()
                    ->name($matches['airline'])
                    ->number($matches['flightNumber']);
            }

            // depName
            // arrName
            // depCode
            // arrCode
            $routeText = $this->http->FindSingleNode("./following-sibling::tr[ ./descendant::text()[{$this->eq($this->t('to'))}] ][1]", $segment);

            if (preg_match("/^(.{3,}?)\s+{$this->opt($this->t('to'))}\s+(.{3,})$/i", $routeText, $matches)) {
                // without replacing this symbol, the airport code may not be found
                $s->departure()
                    ->name(str_ireplace(html_entity_decode("&#8211;"), '-', $matches[1]))
                    ->noCode();
                $s->arrival()
                    ->name(str_ireplace(html_entity_decode("&#8211;"), '-', $matches[2]))
                    ->noCode();
            }

            // depDate
            $dateDepText = $this->http->FindSingleNode("./following-sibling::tr/descendant::td[not(.//td) and {$this->starts($this->t('Departs'))}]",
                $segment, true, "/^{$this->opt($this->t('Departs'))}\s*(.{6,})$/");
            $s->departure()->date($this->normalizeDate($dateDepText));

            // arrDate
            $dateArrText = $this->http->FindSingleNode("./following-sibling::tr/descendant::td[not(.//td) and {$this->starts($this->t('Arrives'))}]",
                $segment, true, "/^{$this->opt($this->t('Arrives'))}\s*(.{6,})$/");
            $s->arrival()->date($this->normalizeDate($dateArrText));

            // depTerminal
            $depTerminal = $this->http->FindSingleNode("./following-sibling::tr/descendant::td[not(.//td) and {$this->starts($this->t('Terminal'))}]", $segment, true, "/^{$this->opt($this->t('Terminal'))}\s*([A-z\d][A-z\d\s]*)$/");
            $s->departure()->terminal($depTerminal, false, true);

            // travellers
            $participantsHtml = $this->http->FindHTMLByXpath("following-sibling::tr/descendant::tr[not(.//tr)]/*[1][ descendant::text()[normalize-space()][1][{$this->eq($this->t('Participants'))}] ]", null, $segment);
            $participantsText = $this->htmlToText($participantsHtml);
            $nameRe = "[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]";

            if (preg_match("/^\s*{$this->opt($this->t('Participants'))}[ ]*((?:\n+[ ]*{$nameRe}(?: ?\/ ?{$nameRe})?[ ]*)+)\s*$/u", $participantsText, $m)) {
                $travellers = array_merge($travellers, preg_split('/[ ]*\n+[ ]*/', trim($m[1])));
            }

            // duration
            $duration = $this->http->FindSingleNode("./following-sibling::tr/descendant::td[not(.//td) and {$this->starts($this->t('Duration'))}]", $segment, true, "/^{$this->opt($this->t('Duration'))}\s*(\d.+)$/");
            $s->extra()->duration($duration, false, true);

            // seats
            $seat = $this->http->FindSingleNode("./following-sibling::tr/descendant::td[not(.//td) and {$this->starts($this->t('Seats'))}]", $segment, true, "/^{$this->opt($this->t('Seats'))}\s*(\d{1,3}[A-Z])$/");

            if (!empty($seat)) {
                $s->extra()->seat($seat);
            }
        }

        if (count($travellers)) {
            $f->general()->travellers(array_unique($travellers));
        }
    }

    private function parseHotel(Email $email): void
    {
        $hotelNodes = $this->http->XPath->query("//tr[ {$this->eq($this->t('Accommodation'))} and following-sibling::tr[position()<5][descendant::text()[{$this->starts($this->t('Check-in'))}] or descendant::text()[{$this->starts($this->t('Check-out'))}]] ]");

        foreach ($hotelNodes as $root) {
            // hotelName
            $hotelName = $this->http->FindSingleNode("following-sibling::tr[string-length(normalize-space())>2][1]", $root);

            // checkInDate
            $dateCheckInValue = $this->http->FindSingleNode("following-sibling::tr/descendant::td[not(.//td) and {$this->starts($this->t('Check-in'))}]",
                $root, true, "/^{$this->opt($this->t('Check-in'))}\s*(.{6,})$/");
            $dateCheckIn = $this->normalizeDate($dateCheckInValue);

            // checkOutDate
            $dateCheckOutValue = $this->http->FindSingleNode("following-sibling::tr/descendant::td[not(.//td) and {$this->starts($this->t('Check-out'))}]",
                $root, true, "/^{$this->opt($this->t('Check-out'))}\s*(.{6,})$/");
            $dateCheckOut = $this->normalizeDate($dateCheckOutValue);

            // address
            $address = $this->http->FindSingleNode("following-sibling::tr/descendant::td[not(.//td) and {$this->starts($this->t('Address'))}]", $root, true, "/^{$this->opt($this->t('Address'))}\s*(.{4,})$/");

            // phone
            $phone = $this->http->FindSingleNode("following-sibling::tr/descendant::td[not(.//td) and {$this->starts($this->t('Phone'))}]", $root, true, "/^{$this->opt($this->t('Phone'))}\s*({$this->patterns['phone']})$/");

            if ($dateCheckInValue === null && $dateCheckOutValue !== null) {
                // it-62974324.eml
                $its = $email->getItineraries();

                foreach (array_reverse($its) as $it) {
                    /** @var \AwardWallet\Schema\Parser\Common\Hotel $it */
                    if ($it->getType() === 'hotel' && $it->getHotelName() === $hotelName
                        && !empty($it->getCheckInDate()) && $it->getCheckOutDate() === null
                    ) {
                        $it->booked()->checkOut($dateCheckOut);
                        // $this->itsCheckOut = $dateCheckOut;

                        if (empty($it->getAddress())) {
                            $it->hotel()->address($address);
                        }

                        if (empty($it->getPhone())) {
                            $it->hotel()->phone($phone, false, true);
                        }

                        continue 2;
                    }
                }
            }

            $h = $email->add()->hotel();
            $h->general()->noConfirmation();

            $h->hotel()
                ->name($hotelName)
                ->address($address)
                ->phone($phone, false, true);

            $h->booked()->checkIn($dateCheckIn);

            if ($dateCheckOutValue !== null) {
                $h->booked()->checkOut($dateCheckOut);
//            } elseif (empty($dateCheckOut) && empty($this->itsCheckOut)) {
//                $h->booked()->noCheckOut();
            }
        }
    }

    private function parseRental(Email $email): void
    {
        $rentalNodes = $this->http->XPath->query("//tr[ {$this->eq($this->t('Car Rental'))} and following-sibling::tr[position()<5][descendant::text()[{$this->starts($this->t('Pick-up'))}] or descendant::text()[{$this->starts($this->t('Drop-off'))}]] ]");

        foreach ($rentalNodes as $root) {
            // rental company
            $rentalCompany = $this->http->FindSingleNode("following-sibling::tr[string-length(normalize-space())>2][1]", $root);

            // pickUpDate
            $datePickUpValue = $this->http->FindSingleNode("following-sibling::tr/descendant::td[not(.//td) and descendant::text()[normalize-space()][1][{$this->eq($this->t('Pick-up'))}]]",
                $root, true, "/^{$this->opt($this->t('Pick-up'))}\s*(.{6,})$/");
            $datePickUp = $this->normalizeDate($datePickUpValue);

            // droppOffDate
            $dateDropOffValue = $this->http->FindSingleNode("following-sibling::tr/descendant::td[not(.//td) and descendant::text()[normalize-space()][1][{$this->eq($this->t('Drop-off'))}]]",
                $root, true, "/^{$this->opt($this->t('Drop-off'))}\s*(.{6,})$/");
            $dateDropOff = $this->normalizeDate($dateDropOffValue);

            // location
            $locationPickUp = $this->http->FindSingleNode("following-sibling::tr/descendant::td[not(.//td) and {$this->starts($this->t('Pick-up Address'))}]", $root, true, "/^{$this->opt($this->t('Pick-up Address'))}\s*(.{4,})$/");
            $locationDropOff = $this->http->FindSingleNode("following-sibling::tr/descendant::td[not(.//td) and {$this->starts($this->t('Drop-off Address'))}]", $root, true, "/^{$this->opt($this->t('Drop-off Address'))}\s*(.{4,})$/");

            if ($datePickUpValue === null && $dateDropOff !== null) {
                $its = $email->getItineraries();

                foreach (array_reverse($its) as $it) {
                    /** @var Rental $it */
                    if ($it->getType() === 'rental' && $it->getCompany() === $rentalCompany
                        && !empty($it->getPickUpDateTime()) && $it->getDropOffDateTime() === null
                    ) {
                        $it->dropoff()
                            ->date($dateDropOff)
                            ->location($locationDropOff);

                        continue 2;
                    }
                }
            }

            $r = $email->add()->rental();
            $r->general()->noConfirmation();

            $r->pickup()
                ->date($datePickUp)
                ->location($locationPickUp)
            ;

            if (!empty($dateDropOff)) {
                $r->dropoff()
                    ->date($dateDropOff);
            }

            if (!empty($locationDropOff)) {
                $r->dropoff()
                ->location($locationDropOff)
            ;
            }

            $r->extra()->company($rentalCompany);

            $rentalProviders = [
                'avis' => ['Avis Rent A Car System'],
            ];

            foreach ($rentalProviders as $code => $names) {
                foreach ($names as $name) {
                    if (stripos($rentalCompany, $name) === 0) {
                        $r->program()->code($code);

                        break 2;
                    }
                }
            }
        }
    }

    private function parseRail(Email $email): void
    {
        $travellers = [];

        $xpath = "//text()[{$this->starts($this->t('Rail'))}]/ancestor::tr[ ./following-sibling::tr[normalize-space(.)][1][contains(.,'→')] ][1]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $segment) {
            // travellers
            $participantsHtml = $this->http->FindHTMLByXpath("following-sibling::tr/descendant::tr[not(.//tr)]/*[1][ descendant::text()[normalize-space()][1][{$this->eq($this->t('Participants'))}] ]", null, $segment);
            $participantsText = $this->htmlToText($participantsHtml);
            $nameRe = "[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]";

            if (preg_match("/^\s*{$this->opt($this->t('Participants'))}[ ]*((?:\n+[ ]*{$nameRe}(?: ?\/ ?{$nameRe})?[ ]*)+)\s*$/u", $participantsText, $m)) {
                $travellers = array_merge($travellers, preg_split('/[ ]*\n+[ ]*/', trim($m[1])));
            }

            $name = $this->http->FindSingleNode("./following-sibling::tr/descendant::td[not(.//td) and {$this->starts($this->t('Operated by'))}]", $segment, true, "/^{$this->opt($this->t('Operated by'))}\s*(.+)$/");
            $s = $this->findTrainSegment($email, $name, $travellers);

            // Departure
            $routeText = $this->http->FindSingleNode("./following-sibling::tr[descendant::text()[contains(., '→')]][1]", $segment);

            if (preg_match("/^\s*(.+)\s*→\s*(.+)$/i", $routeText, $m)) {
                $s->departure()
                    ->name($m[1]);
                $s->arrival()
                    ->name($m[2]);
            }

            // depDate
            $dateDepText = $this->http->FindSingleNode("./following-sibling::tr/descendant::td[not(.//td) and {$this->starts($this->t('Departs'))}]",
                $segment, true, "/^{$this->opt($this->t('Departs'))}\s*(.{6,})$/");
            $s->departure()->date($this->normalizeDate($dateDepText));

            // arrDate
            $dateArrText = $this->http->FindSingleNode("./following-sibling::tr/descendant::td[not(.//td) and {$this->starts($this->t('Arrives by'))}]",
                $segment, true, "/^{$this->opt($this->t('Arrives by'))}\s*(.{6,})$/");
            $s->arrival()->date($this->normalizeDate($dateArrText));

            // Extra
            $s->extra()
                ->noNumber()
                ->duration($this->http->FindSingleNode("./following-sibling::tr/descendant::td[not(.//td) and {$this->starts($this->t('Duration'))}]", $segment, true, "/^{$this->opt($this->t('Duration'))}\s*(\d.+)$/"), false, true)
            ;
            // seats
            $seat = $this->http->FindSingleNode("./following-sibling::tr/descendant::td[not(.//td) and {$this->starts($this->t('Seats'))}]", $segment, true, "/^{$this->opt($this->t('Seats'))}\s*(\d{1,3}[A-Z])$/");

            if (!empty($seat)) {
                $s->extra()->seat($seat);
            }
        }
    }

    private function findTrainSegment(Email $email, $name = '', $travellers)
    {
        if (strcasecmp("Japan Railways", $name) === 0) {
            $name = 'jrg';
        } elseif (preg_match("#\b(KEISEI)\b#i", $name)) {
            $name = 'keisei';
        } else {
            $name = '';
        }

        foreach ($email->getItineraries() as $value) {
            if ($value->getType() == 'train') {
                /** @var Train $train */
                $train = $value;

                if ((empty($name) && empty($train->getProviderCode()))
                    || (empty($name) && $name == $train->getProviderCode())) {
                    return $train->addSegment();
                }
            }
        }
        $t = $email->add()->train();

        if (!empty($name)) {
            $t->setProviderCode($name);
        }
        // General
        $t->general()
            ->noConfirmation();

        if (count($travellers)) {
            $t->general()->travellers(array_unique($travellers));
        }

        return $t->addSegment();
    }

    private function parseEvent(Email $email): void
    {
        $eventNodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Event Name:'))} or {$this->contains($this->t('Provider:'))}]/ancestor::*[{$this->contains($this->t('Origin City'))}][1]");

        foreach ($eventNodes as $root) {
            $ev = $email->add()->event();

            // General
            $conf = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Confirmation Number:'))}][1]", $root, true, "#{$this->opt($this->t('Confirmation Number:'))}\s*([\dA-Z]{5,})\b#");
            $ev->general()
                ->confirmation($conf)
                ->travellers($this->http->FindNodes(".//text()[{$this->contains($this->t('Participants'))}][1]/ancestor::td[1]//text()[normalize-space()][not({$this->contains($this->t('Participants'))})]", $root))
            ;

            // Place
            $name = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Event Name:'))}][1]", $root, true, "#{$this->opt($this->t('Event Name:'))}\s*([^:]+) {$this->opt($this->t('Start Time:'))}#");

            if (empty($name)) {
                $name = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Event Name:'))}][1]/following::text()[normalize-space()][1]", $root,
                    true, "#^\s*([^:]+?)\s*(?:{$this->opt($this->t('Start Time:'))}|$)#");
            }

            if (empty($name)) {
                $caption = $this->http->FindSingleNode("./preceding::text()[normalize-space()][2]", $root);
                $provider = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Provider:')]", $root, true, "/{$this->opt($this->t('Provider:'))}\s+(\D+)\s+{$this->opt($this->t('Confirmation Number:'))}/");
                $name = $caption . ', ' . $provider;
            }

            $ev->place()
                ->name($name)
                ->type(Event::TYPE_EVENT)
                ->address($this->http->FindSingleNode(".//text()[{$this->contains($this->t('Origin City'))}][1]/ancestor::td[1]", $root, true, "#{$this->opt($this->t('Origin City'))}\s*(.+)#"))
            ;

            // Booked
            $ev->booked()
                ->start(strtotime($this->http->FindSingleNode(".//text()[{$this->contains($this->t('Start Time:'))}][1]", $root, true, "#{$this->opt($this->t('Start Time:'))}\s*(.+?)\s*{$this->opt($this->t('End Time:'))}#")))
                ->end(strtotime($this->http->FindSingleNode(".//text()[{$this->contains($this->t('End Time:'))}][1]", $root, true, "#{$this->opt($this->t('End Time:'))}\s*(.+?)\s*(?:{$this->opt($this->t('Total Cost:'))}|{$this->opt($this->t('Provider:'))}|$)#")))
            ;

            // Price
            $total = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Total Cost:'))}][1]", $root, true, "#{$this->opt($this->t('Total Cost:'))} ([A-Z]{3} \d+ |\d+ [A-Z]{3} )\b#");

            if (preg_match("#(?<currency>[A-Z]{3}) (?<total>\d+)#", $total, $m) || preg_match("#(?<total>\d+) (?<currency>[A-Z]{3})#", $total, $m)) {
                $ev->price()
                    ->total($m['total'])
                    ->currency($m['currency'])
                ;
            }
        }
        $eventNodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Event Name:'))}]/ancestor::*[{$this->contains($this->t('Origin City'))}][1]");
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);

        if (empty($date)) {
            return null;
        }
        $in = [
            // 木 10 1月 11:55 午前
            '/^(\w+)\s+(\d{1,2})\s+(\d{1,2})\s*月\s+(\d+:\d+)\s*午前$/u',
            // 木 10 1月 3:10 午後
            '/^(\w+)\s+(\d{1,2})\s+(\d{1,2})\s*月\s+(\d+:\d+)\s*午後$/u',
            // zo 17 maart  8:36 AM    |    Sat 15 December 8:25 AM
            '/^([-[:alpha:]]+)[,.\s]+(\d{1,2})[.\s]+([[:alpha:]]{3,})[.\s]+(\d{1,2}:\d{2}(?:\s*[ap]m)?)$/ui',
            // Fri 1 January    |    mar. 19 juillet
            '/^([-[:alpha:]]+)[,.\s]+(\d{1,2})[.\s]+([[:alpha:]]{3,})[.\s]*$/u',
        ];
        $out = [
            $year . '-$3-$2, $4',
            $year . '-$3-$2, $4 pm',
            '$2 $3 ' . $year . ', $4',
            '$2 $3 ' . $year,
        ];
        $outWeek = [
            '$1',
            '$1',
            '$1',
            '$1',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            if ($this->lang === 'en' && $week === 'Monn') {
                $week = 'Mon';
            }
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));

            if (empty($weeknum)) {
                $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, 'nl'));
            }
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            } elseif ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, 'nl')) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
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

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        return $this->getProviderByBody();
    }

    private function getProviderByBody()
    {
        foreach (self::$providers as $code => $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }
}
