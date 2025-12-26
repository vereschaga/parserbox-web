<?php

namespace AwardWallet\Engine\priceline\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourItinerary2 extends \TAccountChecker
{
    public $mailFiles = "priceline/it-88098273.eml, priceline/it-88453310.eml, priceline/it-89291648.eml, priceline/it-89524481.eml, priceline/it-95357567.eml, priceline/it-96369757.eml, priceline/it-97212170.eml, priceline/it-109236855.eml, priceline/it-390506888.eml";
    public $lang = '';
    public $tripStatus;
    public $patterns;
    public $xpathTime;

    public static $dictionary = [
        'en' => [
            'airConfNumber'      => ['Airline Confirmation Number'],
            'passengers'         => ['Passengers'],
            'checkIn'            => ['Check-in', 'CHECK-IN:'],
            'checkOut'           => ['Check-out', 'CHECK-OUT:'],
            'hotelAddress'       => ['Hotel Address', 'HOTEL ADDRESS:'],
            'hotelPhoneNumber'   => ['Hotel Phone Number', 'HOTEL PHONE NUMBER:'],
            'numberOfRooms'      => ['Number of Rooms', 'NUMBER OF ROOMS:', 'NUMBER OFROOMS:'],
            'reservationName'    => ['Reservation Name', 'RESERVATION NAME:', 'RESERVATIONNAME:'],
            'hotelConfNumber'    => ['Confirmation Number', 'CONFIRMATION NUMBER:'],
            'roomType'           => ['Room Type', 'ROOM TYPE:'],
            'cancellationPolicy' => ['Cancellation Policy', 'CANCELLATION POLICY:'],
            'statusPhrases'      => ['Congrats, your trip from', 'Congrats, your trip on'],
            'statusVariants'     => ['confirmed'],
            'Trip Number:'       => ['Priceline Trip Number:', 'Trip Number:'],
            'pickUp'             => ['Pick-up'],
            'dropOff'            => ['Drop-off'],
            'tax'                => ['TAXES & FEES:'],
            'cost'               => ['ROOM SUBTOTAL:'],
            'totalPrice'         => ['TOTAL COST:', 'Total Cost', 'Total Charged', 'TOTAL:'],
        ],
        'fr' => [
            //            'airConfNumber'      => [''],
            //            'passengers'         => [''],
            //            'checkIn'            => [''],
            //            'checkOut'           => [''],
            //            'hotelAddress'       => [''],
            //            'hotelPhoneNumber'   => [''],
            //            'numberOfRooms'      => [''],
            //            'reservationName'    => [''],
            //            'hotelConfNumber'    => [''],
            //            'roomType'           => [''],
            //            'cancellationPolicy' => [''],
            'statusPhrases'            => ['Félicitations, votre voiture de location'],
            'is'                       => ['est'],
            'Trip Number:'             => ['Priceline Trip Number:'],
            'pickUp'                   => ['Ramassage:'],
            'dropOff'                  => ['Retour:'],
            'location'                 => ['Lieu de location:'],
            'rentalConfNumber'         => ['Car Numéro de confirmation:'],
            'driver'                   => ['Nom du conducteur:'],
            'statusVariants'           => ['confirmée'],
            'tax'                      => ['Taxes & Frais:'],
            'cost'                     => ['Sous-total:'],
            'totalPrice'               => ['Total estimé:'],
            'Prices are in'            => ['Les prix sont en'],
        ],
        'pt' => [
            //            'airConfNumber'      => [''],
            //            'passengers'         => [''],
            //            'checkIn'            => [''],
            //            'checkOut'           => [''],
            //            'hotelAddress'       => [''],
            //            'hotelPhoneNumber'   => [''],
            //            'numberOfRooms'      => [''],
            //            'reservationName'    => [''],
            //            'hotelConfNumber'    => [''],
            //            'roomType'           => [''],
            //            'cancellationPolicy' => [''],
            'statusPhrases'            => ['Parabéns, seu aluguel de carro para'],
            'is'                       => ['está'],
            'Trip Number:'             => ['Priceline Trip Number:'],
            'pickUp'                   => ['Retirada:'],
            'dropOff'                  => ['Devolução:'],
            'location'                 => ['Local do aluguel:'],
            'model'                    => ['Tipo de carro:'],
            'rentalConfNumber'         => ['Número de confirmação:'],
            'driver'                   => ['Nome do condutor:'],
            'statusVariants'           => ['confirmado'],
            'tax'                      => ['Impostos & Taxas:'],
            'cost'                     => ['Subtotal:'],
            'totalPrice'               => ['Total estimado:'],
            'Prices are in'            => ['Os preços estão em'],
        ],
    ];

    private $providerCode = '';

    private $subjects = [
        'en' => ['Your itinerary for ', 'Your Priceline.com itinerary for', 'Your priceline itinerary for', 'Make the most of your stay in'],
        'fr' => ['Votre itinéraire Priceline pour'],
        'pt' => ['Seu itineário Priceline para'],
    ];

    private $detectors = [
        'en' => ['Summary of charges', 'Congrats, your trip on', 'Make the most of your upcoming trip'],
        'fr' => ['Total estimé'],
        'pt' => ['Local do aluguel', 'Nome do condutor'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@travel.priceline.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Priceline.com') === false) {
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
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Format and Language
        return $this->detectBody() && $this->assignLang();
    }

    public function ParseHotels(Email $email): void
    {
        $hotels = $this->http->XPath->query("//tr[ descendant::text()[normalize-space()][1][{$this->eq($this->t('checkIn'))}] and following-sibling::tr/descendant::text()[normalize-space()][1][{$this->eq($this->t('checkOut'))}] ]");

        foreach ($hotels as $hRoot) {
            $h = $email->add()->hotel();

            if ($this->tripStatus) {
                $h->general()->status($this->tripStatus);
            }

            $hotelName = $this->http->FindSingleNode("preceding-sibling::tr/descendant-or-self::tr[ count(*)=2 and *[1][normalize-space()='']/descendant::img and *[2][normalize-space()] ]/*[2]/descendant::tr[not(.//tr) and normalize-space()][1]", $hRoot);

            if (!$hotelName) {
                // it-95357567.eml
                $hotelName = $this->http->FindSingleNode("preceding-sibling::tr/descendant-or-self::tr[not(.//tr)]/descendant::text()[normalize-space()][1][ following::text()[normalize-space()][1][{$this->contains($this->t('Night'))}] ]", $hRoot);
            }

            if (!$hotelName) {
                $hotelName = $this->http->FindSingleNode("preceding-sibling::tr/descendant::text()[normalize-space()][1]", $hRoot);
            }

            $checkIn = $this->http->FindSingleNode("descendant::*[{$this->eq($this->t('checkIn'))}]/following-sibling::*[normalize-space()]", $hRoot);

            if (preg_match("/^(?<date>.{6,}?)\s*\(\s*(?<time>{$this->patterns['time']}).+$/", $checkIn, $m)) {
                // Saturday, May 01, 2021 ( 12:00 PM )
                $h->booked()->checkIn2($m['date'] . ' ' . $m['time']);
            } elseif (preg_match("/^\w+\,\s*(?<month>\w+)\s*(?<day>\d+)\,\s*(?<year>\d{4})$/u", $checkIn, $m)) {
                $h->booked()->checkIn(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year']));
            }

            $checkOut = $this->http->FindSingleNode("following-sibling::tr/descendant::*[{$this->eq($this->t('checkOut'))}]/following-sibling::*[normalize-space()]", $hRoot);

            if (preg_match("/^(?<date>.{6,}?)\s*\([^)(]*?(?<time>{$this->patterns['time']})\s*\)$/", $checkOut, $m)) {
                // Monday, October 04, 2021 (14:00 - 15:00)
                $h->booked()->checkOut2($m['date'] . ' ' . $m['time']);
            } elseif (preg_match("/^\w+\,\s*(?<month>\w+)\s*(?<day>\d+)\,\s*(?<year>\d{4})$/u", $checkOut, $m)) {
                $h->booked()->checkOut(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year']));
            }

            $address = implode(' ', $this->http->FindNodes("following-sibling::tr/descendant::*[{$this->eq($this->t('hotelAddress'))}]/following-sibling::*[normalize-space()]/descendant::text()[normalize-space()]", $hRoot));

            $phone = $this->http->FindSingleNode("following-sibling::tr/descendant::*[{$this->eq($this->t('hotelPhoneNumber'))}]/following-sibling::*[normalize-space()]", $hRoot, true, "/^{$this->patterns['phone']}$/");

            $h->hotel()->name($hotelName)->address($address)->phone($phone, false, true);

            $roomsCount = $this->http->FindSingleNode("following-sibling::tr/descendant::*[{$this->eq($this->t('numberOfRooms'))}]/following-sibling::*[normalize-space()]", $hRoot, true, "/^(\d{1,3})\s*{$this->opt($this->t('Room'))}/i");
            $h->booked()->rooms($roomsCount);

            $guests = $this->http->FindSingleNode("./following::text()[{$this->contains($this->t('Adult'))}]", $hRoot, true, "/\s(\d+)\s*{$this->opt($this->t('Adult'))}/");

            if (!empty($guests)) {
                $h->booked()
                    ->guests($guests);
            }

            $travellers = [];
            $reservationName = implode("\n", $this->http->FindNodes("following-sibling::tr/descendant::*[{$this->eq($this->t('reservationName'))}]/following-sibling::*[normalize-space()]/descendant::tr[not(.//tr) and normalize-space()]", $hRoot));

            if (empty($reservationName)) {
                $reservationName = implode("\n", $this->http->FindNodes("following-sibling::tr/descendant::*[{$this->eq($this->t('reservationName'))}]/following-sibling::*[normalize-space()]/descendant::text()[normalize-space()]", $hRoot));
            }

            if (preg_match_all("/{$this->opt($this->t('Room'))}\s*\d{1,3}\s*:\s*({$this->patterns['travellerName']})(?:\s+{$this->opt($this->t('For'))}\s+\d{1,3}\s+{$this->opt($this->t('Adult'))}|$)/imu", $reservationName, $nameMatches)) {
                // Room 1 : Reginald Johnson For 2 Adult(s)
                $travellers = $nameMatches[1];
            } elseif (preg_match("/^{$this->patterns['travellerName']}$/u", $reservationName)) {
                // Reginald Johnson
                $travellers[] = $reservationName;
            }

            if (count($travellers) > 0) {
                $h->general()->travellers(array_unique($travellers));
            }

            $confirmations = [];

            $confirmationNumber = implode("\n", $this->http->FindNodes("following-sibling::tr/descendant::*[{$this->eq($this->t('hotelConfNumber'))}]/following-sibling::*[normalize-space()]/descendant::tr[not(.//tr) and normalize-space()]", $hRoot));

            if (empty($confirmationNumber)) {
                $confirmationNumber = implode("\n", $this->http->FindNodes("following-sibling::tr/descendant::*[{$this->eq($this->t('hotelConfNumber'))}]/following-sibling::*[normalize-space()]/descendant::text()[normalize-space()]", $hRoot));
            }

            if (empty($confirmationNumber)) {
                $confirmationNumber = $this->http->FindSingleNode("//text()[normalize-space()='Hotel confirmation number:']/following::text()[normalize-space()][1]");
            }

            if (preg_match_all("/^({$this->opt($this->t('Room'))}\s*\d{1,3})\s*:\s*([-A-Z\d]{5,})/m", $confirmationNumber, $numberMatches, PREG_SET_ORDER)) {
                // Room 1 : 388158340
                foreach ($numberMatches as $m) {
                    if (array_key_exists($m[2], $confirmations)) {
                        $confirmations[$m[2]][] = $m[1];
                    } else {
                        $confirmations[$m[2]] = [$m[1]];
                    }
                }
            } elseif (preg_match("/^[-A-Z\d]{5,}$/", $confirmationNumber)) {
                // 388158340
                $confirmationNumberTitle = $this->http->FindSingleNode("following-sibling::tr/descendant::*[{$this->eq($this->t('hotelConfNumber'))} and following-sibling::*[normalize-space()]]", $hRoot, true, '/^(.+?)[\s:：]*$/u');

                if (array_key_exists($confirmationNumber, $confirmations)) {
                    $confirmations[$confirmationNumber][] = $confirmationNumberTitle;
                } else {
                    $confirmations[$confirmationNumber] = [$confirmationNumberTitle];
                }
            }

            foreach ($confirmations as $number => $descriptions) {
                $h->general()->confirmation($number, implode(' & ', array_unique($descriptions)));
            }

            $roomTypes = [];
            $roomType = implode("\n", $this->http->FindNodes("following-sibling::tr/descendant::*[{$this->eq($this->t('Room Type'))}]/following-sibling::*[normalize-space()]/descendant::tr[not(.//tr) and normalize-space()]", $hRoot));

            if (empty($roomType)) {
                $roomType = implode("\n", $this->http->FindNodes("following-sibling::tr/descendant::*[{$this->eq($this->t('roomType'))}]/following-sibling::*[normalize-space()]/descendant::text()[normalize-space()]", $hRoot));
            }

            if (preg_match_all("/^{$this->opt($this->t('Room'))}\s*\d{1,3}\s*:\s*(.{2,})$/m", $roomType, $roomMatches)) {
                // Room 1 : This quadruple room features air conditioning. Max 2 guests.
                $roomTypes = array_merge($roomTypes, $roomMatches[1]);
            } elseif ($roomType) {
                $roomTypes[] = $roomType;
            }

            foreach ($roomTypes as $rType) {
                $room = $h->addRoom();

                if (mb_strlen($rType) > 200) {
                    $room->setDescription($rType);
                } else {
                    $room->setType($rType);
                }
            }

            $cancellation = $this->http->FindSingleNode("following-sibling::tr/descendant::*[{$this->eq($this->t('cancellationPolicy'))}]/following-sibling::*[normalize-space()]", $hRoot);

            if ($cancellation && $this->http->XPath->query("//text()[{$this->eq($cancellation)}]/ancestor::a")->length === 0) {
                $h->general()->cancellation(preg_replace("/^(.{2,}?)\s*{$this->opt($this->t('Cancel This Booking'))}$/", '$1', $cancellation));
                $h->booked()
                    ->parseNonRefundable("This booking is Non-Refundable and cannot be amended or modified.")
                    ->parseNonRefundable("For the room type and rate that you've selected, you are not allowed to change or cancel your reservation.")
                ;
            }

            $this->detectDeadLine($h);
        }
    }

    public function ParseFlights(Email $email): void
    {
        $xpathFlightSegment = "descendant::tr[ *[1]/descendant::tr[not(.//tr) and normalize-space()][1][{$this->xpathTime}] and *[1]/descendant::tr[not(.//tr) and normalize-space()][2][string-length(normalize-space())=3] and *[2][normalize-space()=''] and *[3]/descendant::tr[not(.//tr) and normalize-space()][1][{$this->xpathTime}] and *[3]/descendant::tr[not(.//tr) and normalize-space()][2][string-length(normalize-space())=3] ]";
        $flights = $this->http->XPath->query("//tr[ descendant::tr[{$this->starts($this->t('airConfNumber'))}] ]/following-sibling::tr[{$xpathFlightSegment}]/..");

        foreach ($flights as $fRoot) {
            $f = $email->add()->flight();
            $f->general()->noConfirmation();

            $dateStartValue = $this->http->FindSingleNode("tr/descendant::tr[not(.//tr) and {$this->contains($this->t('Ticket(s)'))}]/preceding-sibling::tr[normalize-space()][1]", $fRoot, true, "/^(.{6,}?)\s+-\s+.{6,}$/");
            $dateStart = strtotime($dateStartValue);

            $airConfNumbers = [];
            $confNumberRows = $this->http->XPath->query("tr/descendant::tr[{$this->starts($this->t('airConfNumber'))}]/following-sibling::tr[normalize-space()]", $fRoot);

            foreach ($confNumberRows as $row) {
                $airline = $this->http->FindSingleNode("descendant::tr[not(.//tr) and count(*)=2 and normalize-space()]/*[1]", $row);
                $number = $this->http->FindSingleNode("descendant::tr[not(.//tr) and count(*)=2 and normalize-space()]/*[2]", $row);

                if (empty($airline) || empty($number)) {
                    break;
                }
                $airConfNumbers[$airline] = $number;
            }

            $travellers = $tickets = [];
            $passengerRows = $this->http->XPath->query("tr/descendant::tr[*[1][{$this->eq($this->t('Passengers'))}] and *[2][normalize-space()] and count(*)=2]/*[2]/descendant::tr[not(.//tr) and normalize-space()]", $fRoot);

            foreach ($passengerRows as $pRow) {
                $rowValue = $this->http->FindSingleNode('.', $pRow);

                if (preg_match("/^[A-Z]{3}\s*->\s*[A-Z]{3}\s*:\s*({$this->patterns['eTicket']})$/", $rowValue, $m)
                    || preg_match("/^{$this->opt($this->t('Ticket Number'))}\s*:\s*({$this->patterns['eTicket']})$/", $rowValue, $m)
                ) {
                    // IAH -> DEN: 0167623845728    |    Ticket Number: 0067624249124
                    $tickets[] = $m[1];
                } elseif (preg_match("/^{$this->patterns['travellerName']}$/u", $rowValue)) {
                    $travellers[] = $rowValue;
                } else {
                    break;
                }
            }

            if (count($travellers)) {
                $f->general()->travellers(array_unique($travellers));
            }

            if (count($tickets)) {
                $f->issued()->tickets(array_unique($tickets), false);
            }

            $segments = $this->http->XPath->query("tr/" . $xpathFlightSegment, $fRoot);

            foreach ($segments as $sRoot) {
                $s = $f->addSegment();

                $xpathTopRows = "ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()]";

                $date = 0;
                $dateValue = $this->http->FindSingleNode($xpathTopRows . "[not(.//tr) and count(descendant::text()[normalize-space()])=1][1]", $sRoot);

                if (preg_match("/^(?<wday>[-[:alpha:]]+)\s*,\s*(?<date>[[:alpha:]]+\s+\d{1,2})$/u", $dateValue, $m)) {
                    $date = EmailDateHelper::parseDateRelative($m['date'], $dateStart, true, '%D%, %Y%');
                }

                $airline = $this->http->FindSingleNode($xpathTopRows . "[1][descendant::img]", $sRoot);

                if ($airline) {
                    $s->airline()->name($airline)->noNumber();

                    if (!empty($airConfNumbers[$airline])) {
                        $s->airline()->confirmation($airConfNumbers[$airline]);
                    }
                }

                $timeDep = $this->http->FindSingleNode("*[1]/descendant::tr[not(.//tr) and normalize-space()][1]", $sRoot, true, "/^{$this->patterns['time']}$/");

                if ($date && $timeDep) {
                    $s->departure()->date(strtotime($timeDep, $date));
                }

                $timeArr = $this->http->FindSingleNode("*[3]/descendant::tr[not(.//tr) and normalize-space()][1]", $sRoot, true, "/^{$this->patterns['time']}$/");

                if ($date && $timeArr) {
                    $s->arrival()->date(strtotime($timeArr, $date));
                }

                $codeDep = $this->http->FindSingleNode("*[1]/descendant::tr[not(.//tr) and normalize-space()][2]", $sRoot, true, "/^[A-Z]{3}$/");
                $codeArr = $this->http->FindSingleNode("*[3]/descendant::tr[not(.//tr) and normalize-space()][2]", $sRoot, true, "/^[A-Z]{3}$/");
                $s->departure()->code($codeDep);
                $s->arrival()->code($codeArr);

                $xpathRightCell = "ancestor::tr[count(*[normalize-space()])=2][1]/*[normalize-space()][2]/descendant::tr[not(.//tr) and normalize-space()]";

                $stops = $this->http->FindSingleNode($xpathRightCell . "[1]", $sRoot);

                if (preg_match("/^(\d{1,3})\s*{$this->opt($this->t('Stop'))}/i", $stops, $m)) {
                    // 1 Stop
                    $s->extra()->stops($m[1]);
                }

                if (preg_match("/Non[- ]*stop/i", $stops)) {
                    $s->extra()->stops(0);
                }

                $duration = $this->http->FindSingleNode($xpathRightCell . "[2]", $sRoot, true, "/^\d[\d. hm]+$/i");
                $s->extra()->duration($duration);
            }
        }
    }

    public function ParseCars(Email $email): void
    {
        $this->logger->error('ParseCars');
        $cars = $this->http->XPath->query("//tr[ descendant::text()[normalize-space()][1][{$this->eq($this->t('pickUp'))}] and following-sibling::tr/descendant::text()[normalize-space()][1][{$this->eq($this->t('dropOff'))}] ]");

        foreach ($cars as $carRoot) {
            $car = $email->add()->rental();

            if ($this->tripStatus) {
                $car->general()->status($this->tripStatus);
            }

            $xpathCarHeader = "preceding-sibling::tr/descendant-or-self::tr[ count(*)=2 and *[1][normalize-space()='']/descendant::img and *[2][normalize-space()] ]/*";

            $company = $this->http->FindSingleNode($xpathCarHeader . "[1]/descendant::img[contains(@alt,'VENDOR')]/@src", $carRoot, true, "/.+\/([-_A-z\d]+)\.[A-z]/");

            if (empty($company)) {
                $xpathCarHeader = "preceding-sibling::tr/descendant-or-self::tr[ count(*)=2 and *[1][normalize-space()='']/descendant::img ]/*";
                $company = $this->http->FindSingleNode($xpathCarHeader . "[1]/descendant::img[contains(@alt,'VENDOR')]/@src", $carRoot, true, "/.+\/([-_A-z\d]+)\.[A-z]/");
            }

            if (($code = $this->normalizeProvider($company))) {
                $car->program()->code($code);
            } else {
                $car->extra()->company($company);
            }

            $carImageUrl = $this->http->FindSingleNode($xpathCarHeader . "[1]/descendant::img[contains(@alt,'CAR_TYPE')]/@src", $carRoot);

            if (empty($carImageUrl)) {
                $carImageUrl = $this->http->FindSingleNode($xpathCarHeader . "/descendant::img[contains(@alt,'CAR_TYPE')]/@src", $carRoot);
            }
            $carType = $this->http->FindSingleNode($xpathCarHeader . "[2]/descendant::tr[not(.//tr) and normalize-space()][1]", $carRoot);

            if (empty($carType)) {
                $carType = $this->http->FindSingleNode($xpathCarHeader . "/preceding::text()[normalize-space()][1]", $carRoot);
            }
            $car->car()->image($carImageUrl)->type($carType);

            $carModel = $this->http->FindSingleNode($xpathCarHeader . "[2]/descendant::tr[not(.//tr) and normalize-space()][2][not(descendant::a)]", $carRoot);

            if (empty($carModel)) {
                $carModel = $this->http->FindSingleNode($xpathCarHeader . "/following::text()[{$this->eq($this->t('model'))}]/following::text()[normalize-space()][1]", $carRoot);
            }

            if (!empty($carModel)) {
                $car->car()->model($carModel);
            }

            /*
                Saturday, April 24, 2021 - 12:00 PM
                Philadelphia Intl Airport (PHL)
            */
            $pattern = "/^\s*(?<date>.{6,}?)[ ]+-[ ]+(?<time>{$this->patterns['time']})[ ]*\n+[ ]*(?<location>.{3,}?)\s*$/";
            $pattern2 = "/^\s*(?<date>.{6,}?)[ ]+-[ ]+(?<time>{$this->patterns['time']})[ ]*\n+[ ]*(?<location>.{3,})$/ms";

            $pickUpText = implode("\n", $this->http->FindNodes("descendant::*[{$this->eq($this->t('pickUp'))}]/following-sibling::*[normalize-space()]/descendant::tr[not(.//tr) and normalize-space()]", $carRoot));

            if (empty($pickUpText)) {
                $pickUpText = implode("\n", $this->http->FindNodes("descendant::*[{$this->eq($this->t('pickUp'))}]/following::*[normalize-space()][1]/descendant::text()[normalize-space()]", $carRoot));
            }

            if (preg_match($pattern, $pickUpText, $m) || preg_match($pattern2, $pickUpText, $m)) {
                $car->pickup()->date($this->normalizeDate($m['date'] . ' ' . $m['time']))->location(str_replace("\n", " ", $m['location']));
            }

            $dropOffText = implode("\n", $this->http->FindNodes("following-sibling::tr/descendant::*[{$this->eq($this->t('dropOff'))}]/following-sibling::*[normalize-space()]/descendant::tr[not(.//tr) and normalize-space()]", $carRoot));

            if (empty($dropOffText)) {
                $dropOffText = implode("\n", $this->http->FindNodes("following::*[{$this->eq($this->t('dropOff'))}]/following::*[normalize-space()][1]/descendant::text()[normalize-space()]", $carRoot));
            }

            if (preg_match($pattern, $dropOffText, $m) || preg_match($pattern2, $dropOffText, $m)) {
                $car->dropoff()->date($this->normalizeDate($m['date'] . ' ' . $m['time']))->location(str_replace("\n", " ", $m['location']));
            }

            $phone = $this->http->FindSingleNode("following-sibling::tr/descendant::*[{$this->eq($this->t('Phone Number'))}]/following-sibling::*[normalize-space()]", $carRoot, true, "/^{$this->patterns['phone']}$/");

            if (!empty($phone)) {
                $car->program()->phone($phone);
            }

            $confirmation = $this->http->FindSingleNode("following-sibling::tr/descendant::*[{$this->eq($this->t('Confirmation Number'))}]/following-sibling::*[normalize-space()]", $carRoot, true, '/^[-A-Z\d]{5,}$/');

            if (empty($confirmation)) {
                $confirmation = $this->http->FindSingleNode("./ancestor::table[2]/descendant::text()[{$this->contains($this->t('rentalConfNumber'))}]/ancestor::tr[1]/descendant::td[2]", $carRoot);
            }

            if ($confirmation) {
                $confirmationTitle = $this->http->FindSingleNode("following-sibling::tr/descendant::*[{$this->eq($this->t('Confirmation Number'))} and following-sibling::*[normalize-space()]]", $carRoot, true, '/^(.+?)[\s:：]*$/u');
                $car->general()->confirmation($confirmation, $confirmationTitle);
            }
        }
    }

    public function ParseCars2(Email $email): void
    {
        $this->logger->error('public function ParseCars2');
        $nodes = $this->http->XPath->query("//tr[ descendant::text()[normalize-space()][1][{$this->eq($this->t('pickUp'))}] and following-sibling::tr/descendant::text()[normalize-space()][1][{$this->eq($this->t('dropOff'))}] ]/ancestor::table[1]/descendant::text()[{$this->eq($this->t('location'))}]");

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            $r->general()
                ->confirmation($this->http->FindSingleNode("./ancestor::table[2]/descendant::text()[{$this->contains($this->t('rentalConfNumber'))}]/ancestor::tr[1]/descendant::td[2]", $root))
                ->traveller($this->http->FindSingleNode("./ancestor::table[2]/descendant::text()[{$this->contains($this->t('driver'))}]/ancestor::tr[1]/descendant::td[2]", $root), true);

            if (!empty($this->tripStatus)) {
                $r->general()
                    ->status($this->tripStatus);
            }

            $r->pickup()
                ->date($this->normalizeDate($this->http->FindSingleNode("./ancestor::table[2]/descendant::text()[{$this->contains($this->t('pickUp'))}]/ancestor::tr[1]/descendant::td[2]", $root)));

            $r->dropoff()
                ->date($this->normalizeDate($this->http->FindSingleNode("./ancestor::table[2]/descendant::text()[{$this->contains($this->t('dropOff'))}]/ancestor::tr[1]/descendant::td[2]", $root)));

            $r->pickup()
                ->location($this->http->FindSingleNode("./ancestor::table[2]/descendant::text()[{$this->contains($this->t('location'))}]/ancestor::tr[1]/descendant::td[2]", $root));

            $r->dropoff()
                ->same();

            $r->car()
                ->image($this->http->FindSingleNode("./ancestor::table[2]/descendant::img[contains(@alt,'CAR_TYPE')]/@src", $root))
                ->type($this->http->FindSingleNode("./ancestor::table[2]/descendant::img[contains(@alt,'CAR_TYPE')]/@src/preceding::text()[normalize-space()][1]", $root));
        }
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        // Detecting Language
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourItinerary2' . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        $this->patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
            'phone'         => '[+(\d][-. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $this->xpathTime = 'contains(translate(normalize-space(),"0123456789：","dddddddddd:"),"d:dd")';

        $this->tripStatus = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('statusPhrases'))}]", null, true, "/{$this->opt($this->t('statusPhrases'))}\s+.+\s+{$this->opt($this->t('is'))}\s+({$this->opt($this->t('statusVariants'))})(?:[ ]*[,.;:!?]|$)/i");

        $tripNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Trip Number:'))}][not({$this->eq($this->t('Trip Number:'))})]");

        if (preg_match("/(?<name>{$this->opt($this->t('Trip Number:'))})\s*(?<value>[-A-Z\d]{5,})$/", $tripNumber, $m)
            || preg_match("/(?<name>{$this->opt($this->t('Trip Number:'))})\s*(?<value>[-A-Z\d]{5,})\s*\)/", $parser->getSubject(), $m)
        ) {
            $email->ota()->confirmation($m['value'], rtrim($m['name'], ': '));
        }

        $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('cost'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D*\s*(\d[,.\'\d ]*)(?:\(|$|\D)/");

        if ($cost !== null) {
            $email->price()->cost(PriceHelper::parse($cost));
        }

        $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('tax'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D*\s*(\d[,.\'\d ]*)(?:\(|$|\D)/");

        if ($tax !== null) {
            $email->price()->tax(PriceHelper::parse($tax));
        }

        $totalCharged = $this->http->FindSingleNode("//tr[ descendant::text()[normalize-space()][1][{$this->eq($this->t('Summary of charges'))}] ]/following::tr[ count(*)=2 and *[1][{$this->eq($this->t('totalPrice'))}] and *[2][normalize-space()] ][last()]/*[2]");

        if ($totalCharged === null) {
            $totalCharged = $this->http->FindSingleNode("//text()[{$this->eq($this->t('totalPrice'))}]/ancestor::tr[1]/descendant::td[2]");
        }

        if (preg_match('/^(?<currency>[^\d)(]+?)?[ ]*(?<amount>\d[,.\'\d ]*)(?:\(|$|\D+)/', $totalCharged, $matches)) {
            // $1,128.99    |    89.24 (EUR 60.00)
            $email->price()->total(PriceHelper::parse($matches['amount']));

            if (!empty($matches['currency'])) {
                $email->price()->currency($this->normalizeCurrency($matches['currency']));
            }
        }

        if ($currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Prices are in'))}]", null, true, "/{$this->opt($this->t('Prices are in'))}\s*([A-Z]{3})/")) {
            $email->price()
                ->currency($currency);
        }

        $xpathFlightSegment = "descendant::tr[ *[1]/descendant::tr[not(.//tr) and normalize-space()][1][{$this->xpathTime}] and *[1]/descendant::tr[not(.//tr) and normalize-space()][2][string-length(normalize-space())=3] and *[2][normalize-space()=''] and *[3]/descendant::tr[not(.//tr) and normalize-space()][1][{$this->xpathTime}] and *[3]/descendant::tr[not(.//tr) and normalize-space()][2][string-length(normalize-space())=3] ]";

        if ($this->http->XPath->query("//tr[ descendant::tr[{$this->starts($this->t('airConfNumber'))}] ]/following-sibling::tr[{$xpathFlightSegment}]/..")->length > 0) {
            $this->ParseFlights($email);
        }

        if ($this->http->XPath->query("//tr[ descendant::text()[normalize-space()][1][{$this->eq($this->t('checkIn'))}] and following-sibling::tr/descendant::text()[normalize-space()][1][{$this->eq($this->t('checkOut'))}] ]")->length > 0) {
            $this->ParseHotels($email);
        }

        if ($this->http->XPath->query("//tr[ descendant::text()[normalize-space()][1][{$this->eq($this->t('pickUp'))}] and following-sibling::tr/descendant::text()[normalize-space()][1][{$this->eq($this->t('dropOff'))}] ]/ancestor::table[1]/descendant::text()[{$this->eq($this->t('location'))}]")->length > 0) {
            $this->ParseCars2($email);
        } elseif ($this->http->XPath->query("//tr[ descendant::text()[normalize-space()][1][{$this->eq($this->t('pickUp'))}] and following-sibling::tr/descendant::text()[normalize-space()][1][{$this->eq($this->t('dropOff'))}] ]")->length > 0) {
            $this->ParseCars($email);
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

    public static function getEmailProviders()
    {
        return ['frosch', 'priceline', 'royalcaribbean', 'mileageplus', 'jetblue', 'aa', 'delta'];
    }

    private function assignProvider($headers): bool
    {
        if (stripos($headers['from'], '@frosch.com') !== false
            || stripos($headers['subject'], 'Your Frosch.com itinerary') !== false
            || $this->http->XPath->query('//h1[normalize-space()="Frosch" or normalize-space()="Frosch.com"]')->length > 0
        ) {
            // it-109236855.eml
            $this->providerCode = 'frosch';

            return true;
        }

        if ($this->http->XPath->query('//a[contains(@href,".priceline.com/") or contains(@href,"//priceline.com/") or contains(@href,"travel.priceline.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"This is a transactional email from priceline")]')->length > 0
        ) {
            $this->providerCode = 'priceline';

            return true;
        }

        if ($this->http->XPath->query('//*[contains(normalize-space(),"Map/Directions")]')->length > 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Summary of charges")]')->length > 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Customer Care")]')->length > 0
        ) {
            $this->providerCode = 'royalcaribbean';

            return true;
        }

        if ($this->http->XPath->query('//*[contains(normalize-space(),"United Airlines")]')->length > 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Airline Contact Information")]')->length > 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"www.united.com")]')->length > 0
        ) {
            $this->providerCode = 'mileageplus';

            return true;
        }

        if ($this->http->XPath->query('//*[contains(normalize-space(),"JetBlue Airways")]')->length > 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Airline Contact Information")]')->length > 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"www.jetblue.com")]')->length > 0
        ) {
            $this->providerCode = 'jetblue';

            return true;
        }

        if ($this->http->XPath->query('//*[contains(normalize-space(),"Delta Air Lines")]')->length > 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Airline Contact Information")]')->length > 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"www.delta.com")]')->length > 0
        ) {
            $this->providerCode = 'delta';

            return true;
        }

        if ($this->http->XPath->query('//*[contains(normalize-space(),"American Airlines")]')->length > 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Airline Contact Information")]')->length > 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"www.aa.com")]')->length > 0
        ) {
            $this->providerCode = 'aa';

            return true;
        }

        return false;
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
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang)) {
                continue;
            }

            if (!empty($phrases['airConfNumber']) && !empty($phrases['passengers'])
                && $this->http->XPath->query("//*[{$this->contains($phrases['airConfNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['passengers'])}]")->length > 0
                || !empty($phrases['checkIn']) && !empty($phrases['checkOut'])
                && $this->http->XPath->query("//*[{$this->contains($phrases['checkIn'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['checkOut'])}]")->length > 0
                || !empty($phrases['pickUp']) && !empty($phrases['dropOff'])
                && $this->http->XPath->query("//*[{$this->contains($phrases['pickUp'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['dropOff'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
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

    /**
     * @param string|null $string Provider keyword
     *
     * @return string|null Provider code
     */
    private function normalizeProvider(?string $string): ?string
    {
        $string = trim($string);
        $providers = [
            'perfectdrive' => ['BU', 'Budget'],
            'hertz'        => ['HZ', 'Hertz', 'Hertz Corporation'],
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

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/You (?i)may cancell? free of charge until (?<hour>[\d:]+) on the day of arrival/', $cancellationText, $m)) {
            $h->booked()->deadlineRelative('1 day', $m['hour']);
        } elseif (preg_match("/You (?i)may cancell? free of charge until (?<prior>\d{1,3} days?) before arrival\./", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'], '00:00');
        }

        if (preg_match('/and\s*\w+\,\s*(?<month>\w+)\s*(?<day>\d+)nd\,\s*(?<year>\d{4})\s*at\s*(?<time>[\d\:]+a?p?m)\s*local hotel time\s*\(.*\).*you may cancel your reservation for a full refund/', $cancellationText, $m)) {
            $h->booked()->deadline(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['time']));
        }
    }

    private function normalizeDate($str)
    {
        //$this->logger->warning('In ='. $str);
        $in = [
            "#^\w+\.?\,\s*(\w+)\s*(\d+)\,\s*(\d{4})[\s\-]+([\d\:]+\s*A?P?M)$#", //dim., juillet 18, 2021 - 11:00 AM
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        //$this->logger->warning('Out ='. $str);
        return strtotime($str);
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'BRL' => ['R$'],
            '$'   => ['$'],
            'INR' => ['Rs.', 'Rs'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
