<?php

namespace AwardWallet\Engine\tperk\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Trip extends \TAccountChecker
{
    public $mailFiles = "tperk/it-12289931.eml, tperk/it-12418264.eml, tperk/it-156651009.eml, tperk/it-162632935.eml, tperk/it-201694955.eml, tperk/it-284441906.eml, tperk/it-424796840.eml, tperk/it-657330576.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'statusVariants'   => ['confirmed'],
            'Total trip price' => ['Total trip price', 'Total Trip Price'],
            // Flight / Train
            'Depart from'     => ['Depart from'],
            'Arrive at'       => ['Arrive at'],
            'Flight duration' => ['Flight duration', 'Flight Duration'],
            'Passengers'      => ['Passenger', 'Passengers'],
            'New e-ticket'    => ['New e-ticket', 'New E-Ticket', 'NEW ETKT', 'NEW TKT', 'New tkt', 'New'],
            // Hotel
            'Check-in'  => ['Check-in'],
            'Check-out' => ['Check-out'],
            'until'     => ['until', 'to'],
            // Car
            'Pick-up'  => ['Pick-up', 'Pickup'],
            'Drop-off' => ['Drop-off', 'Dropoff'],
            'Loyalty:' => ['Loyalty:', 'Loyalty'],
        ],
        'es' => [
            'Trip'           => 'Viaje',
            'statusVariants' => ['confirmado'],
            // Flight / Train
            'Depart from'        => ['Salida desde'],
            'Arrive at'          => ['Llegar a'],
            'Flight duration'    => ['Duración del vuelo'],
            'Passengers'         => ['Pasajero', 'Pasajeros'],
            'Cabin class'        => 'Clase de cabina',
            'Ticket type'        => 'Tipo de billete',
            'Trip booking date:' => 'Fecha de la reserva:',
            'Your trip ID is'    => 'Tu ID de viaje es',
            'Your'               => 'Tu',
            'is'                 => 'es',
            'Ticket Info'        => 'Información de billete',
            'Ticket:'            => 'Número de billete:',

            'Booking Ref:'       => 'Referencia de reserva:',
            'Loyalty:'           => 'Fidelización:',
            'Check-in email'     => 'Información de billete',
            'Total trip price'   => 'Precio total del viaje',
            'Total flight price' => 'Precio total del vuelo',
        ],
        'fr' => [
            'Trip'           => 'Voyage',
            'statusVariants' => ['confirmé'],
            // Flight / Train
            'Depart from'                     => ['Départ de'],
            'Arrive at'                       => ['Arrivée à'],
            'Flight duration'                 => ['Durée de vol'],
            'Passengers'                      => ['Passager'],
            'Cabin class'                     => 'Classe de réservation',
            'Ticket type'                     => 'Type de billet',
            'Ticket Info'                     => 'Informations sur le billet',
            'Trip booking date:'              => 'Date de réservation du voyage :',
            'Your trip ID is'                 => 'La référence de votre voyage est',
            'Your'                            => 'La',
            'is'                              => 'est',
            'Ticket number'                   => 'Ticket number',
            'Seat'                            => 'Siège',

            'Booking Ref:'     => 'Réf. de réservation :',
            // 'Loyalty:'         => 'Fidelización:',
            // 'Check-in email'   => 'Información de billete',
            'Total trip price' => 'Prix total du voyage',

            // Hotel
            'Check-in'          => ['Arrivée'],
            'Check-out'         => ['Départ'],
            'from'              => ['de'],
            'until'             => ['à'],
            "Hotel's phone"     => "Téléphone de l'hôtel",
            'Rooms'             => 'Chambres',
            'Guests'            => 'Clients',
            'Cancellation'      => 'Annulation',
            'Confirmation #'    => 'Numéro de confirmation #',
            // Car
            // 'Pick-up'  => ['Pick-up', 'Pickup'],
            // 'Drop-off' => ['Drop-off', 'Dropoff'],
            // 'Loyalty:' => ['Loyalty:', 'Loyalty'],

            'Total flight price' => 'Prix total du vol',
            // 'Total train price' => '',
            'Total hotel price' => "Prix total de l'hôtel",
        ],
    ];

    private $subjects = [
        'en' => ['Booking confirmed #'],
        'es' => ['Referencia de reserva:'],
        'fr' => ['Réservation confirmée #'],
    ];

    private $xpath = [
        'headRow'    => "tr[ count(*)=2 and *[1][normalize-space()] and *[2][not(.//tr) and count(descendant::img)=1 and normalize-space()=''] ]",
        'imgLoyalty' => "contains(@src,'/LoyaltyIcon') or contains(@alt,'LoyaltyIcon')",
    ];

    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
    ];

    private $tripStatus = null;
    private $year = null;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@travelperk.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query('//a[contains(@href,".travelperk.com/") or contains(@href,"url.travelperk.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"@travelperk.com") or contains(., "TravelPerk S.L.U.")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('Trip' . ucfirst($this->lang));

        $tripStatuses = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Trip'))}]", null, "/^{$this->opt($this->t('Trip'))}\s+({$this->opt($this->t('statusVariants'))})[,.:!?\s]*$/"));

        if (count(array_unique($tripStatuses)) === 1) {
            $this->tripStatus = array_shift($tripStatuses);
        }

        $tripBookingDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Trip booking date:'))}]", null, true, "/{$this->opt($this->t('Trip booking date:'))}[:\s]*(.{6,})$/")));

        if ($tripBookingDate) {
            $this->year = date('Y', $tripBookingDate);
        }

        $tripIDText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your trip ID is'))}]/ancestor::*[self::tr or self::div][1]");

        if (preg_match("/({$this->opt($this->t('Your trip ID is'))})[:\s]+([-A-Z\d]{5,})(?:\s*[-—]|$)/", $tripIDText, $m)) {
            $email->ota()->confirmation($m[2], preg_replace(["/^{$this->opt($this->t('Your'))}\s+/i", "/\s+{$this->opt($this->t('is'))}$/i"], '', $m[1]));
        }

        $flights = $flights2 = $trains = $hotels = $hotels2 = $rentals = [];

        $tripSegments = $this->http->XPath->query("//{$this->xpath['headRow']}/ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] or following-sibling::tr[normalize-space()] ][1][ not(preceding-sibling::tr[normalize-space()]) and following-sibling::tr[normalize-space()] ]/..");

        if ($tripSegments->length == 0) {
            $tripSegments = $this->http->XPath->query("//{$this->xpath['headRow']}/ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] or following::tr[normalize-space()] ][1][ not(preceding-sibling::tr[normalize-space()]) and following::tr[normalize-space()] ]/../ancestor::table[2]");
        }

        foreach ($tripSegments as $sRoot) {
            if ($this->http->XPath->query("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Depart from'))}] and following-sibling::*[normalize-space()][1][{$this->eq($this->t('Arrive at'))}] and following-sibling::*[{$this->eq($this->t('Flight duration'))}] ]", $sRoot)->length > 0) {
                $pnr = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Booking Ref:'))}]", $sRoot, true, "/^{$this->opt($this->t('Booking Ref:'))}[:\s]*([A-Z\d]{5,})$/")
                    ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Booking Ref:'))}]/following::text()[normalize-space()][1]", $sRoot, true, '/^[A-Z\d]{5,}$/')
                ;

                if ($pnr === null) {
                    $email->add()->flight(); // for 100% fail

                    continue;
                }

                if (array_key_exists($pnr, $flights)) {
                    $flights[$pnr][] = $sRoot;
                } else {
                    $flights[$pnr] = [$sRoot];
                }
            } elseif ($this->http->XPath->query("./descendant::text()[{$this->starts($this->t('Flight duration'))}]", $sRoot)->length > 0) {
                $pnr = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Booking Ref:'))}]", $sRoot, true, "/^{$this->opt($this->t('Booking Ref:'))}[:\s]*([A-Z\d]{5,})$/")
                    ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Booking Ref:'))}]/following::text()[normalize-space()][1]", $sRoot, true, '/^[A-Z\d]{5,}$/')
                ;

                if ($pnr === null) {
                    $email->add()->flight(); // for 100% fail

                    continue;
                }

                if (array_key_exists($pnr, $flights2)) {
                    $flights2[$pnr][] = $sRoot;
                } else {
                    $flights2[$pnr] = [$sRoot];
                }
            } elseif ($this->http->XPath->query("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Depart from'))}] and following-sibling::*[normalize-space()][1][{$this->eq($this->t('Arrive at'))}] and following-sibling::*[{$this->eq($this->t('Train duration'))}] ]", $sRoot)->length > 0) {
                $pnr = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Booking Ref:'))}]", $sRoot, true, "/^{$this->opt($this->t('Booking Ref:'))}[:\s]*([A-Z\d\_]{5,})$/")
                    ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Booking Ref:'))}]/following::text()[normalize-space()][1]", $sRoot, true, '/^[A-Z\d_]{5,}$/')
                ;

                if ($pnr === null) {
                    $email->add()->train(); // for 100% fail

                    continue;
                }

                if (array_key_exists($pnr, $trains)) {
                    $trains[$pnr][] = $sRoot;
                } else {
                    $trains[$pnr] = [$sRoot];
                }
            } elseif ($this->http->XPath->query("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Check-in'))}] and following-sibling::*[normalize-space()][1][{$this->eq($this->t('Check-out'))}] ]", $sRoot)->length > 0) {
                $hotels[] = $sRoot;
            } elseif ($this->http->XPath->query("descendant::text()[normalize-space()='Check-in']", $sRoot)->length > 0) {
                $hotels2[] = $sRoot;
            } elseif ($this->http->XPath->query("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Pick-up'))}] and following-sibling::*[normalize-space()][1][{$this->eq($this->t('Drop-off'))}] ] | descendant::tr[{$this->starts($this->t('Pick-up'))}]/following-sibling::tr[normalize-space()][1][{$this->starts($this->t('Drop-off'))}]", $sRoot)->length > 0) {
                $rentals[] = $sRoot;
            }
        }

        foreach ($flights as $pnr => $fSegments) {
            $this->parseFlight($email, $pnr, $fSegments);
        }

        foreach ($flights2 as $pnr => $fSegments) {
            $this->parseFlight2($email, $pnr, $fSegments);
        }
        $flightPrice = null;

        if ((count($flights) + count($flights2)) === 1) {
            $flightPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total flight price'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');
        }

        foreach ($trains as $pnr => $tSegments) {
            $this->parseTrain($email, $pnr, $tSegments);
        }
        $trainPrice = null;

        if (count($trains) === 1) {
            $trainPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total train price'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');
        }

        foreach ($hotels as $hRoot) {
            $this->parseHotel($email, $hRoot);
        }

        foreach ($hotels2 as $hRoot) {
            $this->parseHotel2($email, $hRoot);
        }
        $hotelPrice = null;

        if ((count($hotels) + count($hotels2)) === 1) {
            $hotelPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total hotel price'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');
        }

        foreach ($rentals as $carRoot) {
            $this->parseCar($email, $carRoot);
        }
        $rentalPrice = null;

        if (count($rentals) === 1) {
            $rentalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total trip price'))}]/preceding::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Car rental'))}] ][1]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');
        }

        if (!empty($flightPrice) || !empty($rentalPrice)) {
            foreach ($email->getItineraries() as $it) {
                if ($it->getType() === 'flight' && !empty($flightPrice)) {
                    if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $flightPrice, $matches)
                        || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\-\d)(]+?)$/', $flightPrice, $matches)) {
                        // €207.47
                        $matches['currency'] = $this->currency($matches['currency']);
                        $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                        $it->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'],
                            $currencyCode));
                    }
                }

                if ($it->getType() === 'rental' && !empty($rentalPrice)) {
                    if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $rentalPrice, $matches)
                        || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\-\d)(]+?)$/', $rentalPrice, $matches)) {
                        // €207.47
                        $matches['currency'] = $this->currency($matches['currency']);
                        $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                        $it->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'],
                            $currencyCode));
                    }
                }

                if ($it->getType() === 'hotel' && !empty($hotelPrice)) {
                    if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $hotelPrice, $matches)
                        || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\-\d)(]+?)$/', $hotelPrice, $matches)) {
                        // €207.47
                        $matches['currency'] = $this->currency($matches['currency']);
                        $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                        $it->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'],
                            $currencyCode));
                    }
                }
            }
        }

        $totalTripPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total trip price'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalTripPrice, $matches)
        || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\-\d)(]+?)$/', $totalTripPrice, $matches)) {
            // €207.47
            $matches['currency'] = $this->currency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $email->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
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

    private function parseFlight(Email $email, string $pnr, array $segments): void
    {
        $this->logger->debug(__METHOD__);
        // it-156651009.eml
        $f = $email->add()->flight();

        if (!empty($this->tripStatus)) {
            $f->general()->status($this->tripStatus);
        }

        $f->general()->confirmation($pnr);

        $travellers = [];
        $accounts = [];
        $tickets = [];

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("descendant::{$this->xpath['headRow']}/*[1]/descendant::*[ *[normalize-space()][2] and not({$this->contains($this->t('Booking Ref:'))}) ][1]/*[normalize-space()][2]", $root);

            if (preg_match('/(?:^|\s+-\s+)(?:[A-Z][A-Z\d]\s|[A-Z\d][A-Z]\s)?(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            /*
                Merignac (BOD)
                Sat 21 May 2022 - 22:50
            */
            $pattern = "/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\n+(?<date>.*\d.*)$/";

            $departVal = implode("\n", $this->http->FindNodes("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Depart from'))}] and following-sibling::*[normalize-space()][1][{$this->eq($this->t('Arrive at'))}] ]/descendant::*[ *[normalize-space()][2] ][1]/*[normalize-space()]", $root));

            if (preg_match($pattern, $departVal, $m)) {
                $s->departure()->name($m['name'])->code($m['code']);

                if (preg_match("/^(.{6,}?)\s*-\s*({$this->patterns['time']})$/", $m['date'], $m2)) {
                    $s->departure()->date(strtotime($m2[2], strtotime($this->normalizeDate($m2[1]))));
                }
            }

            $arriveVal = implode("\n", $this->http->FindNodes("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Arrive at'))}] and following-sibling::*[normalize-space()][1][{$this->eq($this->t('Flight duration'))}] ]/descendant::*[ *[normalize-space()][2] ][1]/*[normalize-space()]", $root));

            if (preg_match($pattern, $arriveVal, $m)) {
                $s->arrival()->name($m['name'])->code($m['code']);

                if (preg_match("/^(.{6,}?)\s*-\s*({$this->patterns['time']})$/", $m['date'], $m2)) {
                    $s->arrival()->date(strtotime($m2[2], strtotime($this->normalizeDate($m2[1]))));
                }
            }

            $duration = $this->http->FindSingleNode("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Flight duration'))}] and following-sibling::*[normalize-space()][1][{$this->eq($this->t('Cabin class'))}] ]", $root, true, '/^\d.+/');
            $cabin = $this->http->FindSingleNode("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Cabin class'))}] and following-sibling::*[normalize-space()][1][{$this->eq($this->t('Ticket type'))}] ]", $root);
            $s->extra()
                ->duration($duration)
                ->cabin($cabin)
            ;

            $seats = $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Seat'))}]/following::text()[normalize-space()][1]",
                $root, "/^\s*:\s*(\d{1,3}[A-Z])\s*$/");

            if (empty($seats)) {
                $seats = $this->http->FindNodes("descendant::text()[{$this->starts($this->t('Seat'))}]",
                    $root, "/^\s*{$this->opt($this->t('Seat'))}\s+(\d{1,3}[A-Z])\s*$/");
            }

            if (!empty($seats)) {
                $s->extra()
                    ->seats($seats);
            }

            $accountArray = array_filter($this->http->FindNodes(
                "descendant::text()[{$this->starts($this->t('Loyalty:'))}]/ancestor::td[1]"
                . " | descendant::tr[ count(*)=2 and *[1][normalize-space()='']/descendant::img[{$this->xpath['imgLoyalty']}] ]/*[2][normalize-space()]", $root, "/\s(\d{8,}|[A-Z\d]{5,})$/"));

            if (count($accountArray) > 0) {
                $accounts = array_merge($accounts, $accountArray);
            }

            $passengers = array_filter($this->http->FindNodes("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Remarks'))}] and following-sibling::*[normalize-space()][1]]/descendant::text()[{$this->contains($this->t('Ticket:'))}]", $root, "/^({$this->patterns['travellerName']})\.\s*{$this->opt($this->t('Ticket:'))}/u"));

            if (count($passengers) === 0) {
                $passengers = array_filter($this->http->FindNodes("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Passengers'))}] and following-sibling::*[normalize-space()][1]]/descendant::text()[{$this->starts($this->t('Ticket Info'))}]/preceding::text()[normalize-space()][1]", $root, "/^({$this->patterns['travellerName']})\s+-$/u"));
            }

            if (count($passengers) === 0) {
                $passengers = array_filter($this->http->FindNodes("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Passengers'))}] and following-sibling::*[normalize-space()][1][{$this->eq($this->t('Check-in email'))}] ]/descendant::text()[normalize-space()]", $root, "/^({$this->patterns['travellerName']})\s+-$/u"));
            }

            if (count($passengers) === 0) {
                $passengers = array_filter($this->http->FindNodes("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Passengers'))}] and following-sibling::*[normalize-space()][1]]/descendant::text()[{$this->eq($this->t('Check-in email'))}]/preceding::text()[normalize-space()][1]", $root, "/^({$this->patterns['travellerName']})\s+-$/u"));
            }

            if (count($passengers) > 0) {
                $travellers = array_merge($travellers, $passengers);
            }

            $ticketArray = array_filter($this->http->FindNodes("descendant::text()[{$this->contains($this->t('Ticket:'))}]", $root, "/{$this->opt($this->t('Ticket:'))}[:\s]*({$this->patterns['eTicket']})$/"));

            if (count($ticketArray) === 0) {
                $ticketText = implode("\n", $this->http->FindNodes("descendant::text()[{$this->contains($this->t('Ticket number'))} or {$this->contains($this->t('New e-ticket'))}]", $root));
                $ticketRows = preg_split('/(\s*[\/\n]+\s*)+/', $ticketText);

                foreach ($ticketRows as $tktRow) {
                    if (preg_match("/^(?:(?:{$this->opt($this->t('Ticket number'))}|{$this->opt($this->t('New e-ticket'))})[-:\s]*)*({$this->patterns['eTicket']})$/i", $tktRow, $m)) {
                        $ticketArray[] = $m[1];
                    }
                }
            }

            if (count($ticketArray) > 0) {
                $tickets = array_merge($tickets, $ticketArray);
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers(array_unique($travellers), true);
        }

        if (count($accounts) > 0) {
            $f->setAccountNumbers(array_unique($accounts), false);
        }

        if (count($tickets) > 0) {
            $f->setTicketNumbers(array_unique($tickets), false);
        }
    }

    private function parseFlight2(Email $email, string $pnr, array $segments): void
    {
        $this->logger->debug(__METHOD__);
        // 	201694955
        $f = $email->add()->flight();

        if (!empty($this->tripStatus)) {
            $f->general()->status($this->tripStatus);
        }

        $f->general()->confirmation($pnr);

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Booking Ref:'))}][1]/preceding::text()[{$this->starts($this->t('Flight:'))}][1]", $root);

            if (preg_match('/(?:^|\s+-\s+)(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]+(?<number>\d+)$/', $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            /*
                Schiphol (AMS)
                Sun 16 Aug 2020 - 09:00
            */
            $pattern = "/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\n+(?<date>.*\d.*)$/";

            $departVal = implode("\n", $this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Depart from'))}]/ancestor::tr[1]/descendant::td[contains(normalize-space(), '(')][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern, $departVal, $m)) {
                $s->departure()->name($m['name'])->code($m['code']);

                if (preg_match("/^(.{6,}?)\s*-\s*({$this->patterns['time']})$/", $m['date'], $m2)) {
                    $s->departure()->date(strtotime($m2[2], strtotime($this->normalizeDate($m2[1]))));
                }
            }

            $arriveVal = implode("\n", $this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Arrive at'))}]/ancestor::tr[1]/descendant::td[contains(normalize-space(), '(')][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern, $arriveVal, $m)) {
                $s->arrival()->name($m['name'])->code($m['code']);

                if (preg_match("/^(.{6,}?)\s*-\s*({$this->patterns['time']})$/", $m['date'], $m2)) {
                    $s->arrival()->date(strtotime($m2[2], strtotime($this->normalizeDate($m2[1]))));
                }
            }

            $duration = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Flight Duration'))}]/following::text()[normalize-space()][1]", $root, true, '/^\d.+/');

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $cabin = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Cabin Class'))}]/following::text()[normalize-space()][1]", $root);

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }
        }

        $ticketNumbers = array_filter($this->http->FindNodes("./descendant::text()[{$this->starts($this->t('E-ticket:'))}]/following::text()[normalize-space()][1]", $root, "/([\d\-]+)/"));

        if (count($ticketNumbers) > 0) {
            $f->setTicketNumbers($ticketNumbers, false);
        }

        $travellers = $this->http->FindNodes("./descendant::text()[{$this->starts($this->t('E-ticket:'))}]/preceding::text()[normalize-space()][1]", $root, "/^({$this->patterns['travellerName']})$/u");

        if (count($travellers) > 0) {
            $f->general()->travellers(array_unique($travellers), true);
        }

        $accounts = array_filter($this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Loyalty:'))}]/following::text()[normalize-space()][1]", $root, "/\s(\d{8,})$/"));

        if (count($accounts) > 0) {
            $f->setAccountNumbers($accounts, false);
        }
    }

    private function parseTrain(Email $email, string $pnr, array $segments): void
    {
        $this->logger->debug(__METHOD__);

        // it-12289931.eml
        $t = $email->add()->train();

        if (!empty($this->tripStatus)) {
            $t->general()->status($this->tripStatus);
        }

        $t->general()->confirmation($pnr);

        $travellers = [];

        foreach ($segments as $root) {
            $s = $t->addSegment();

            $train = $this->http->FindSingleNode("descendant::{$this->xpath['headRow']}/*[1]/descendant::*[ *[normalize-space()][2] and not({$this->contains($this->t('Booking Ref:'))}) ][1]/*[normalize-space()][2]", $root);

            if (preg_match('/(?:^|\s+-\s+)(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z]|[A-Z]{3})[ ]*(?<number>\d+)$/', $train, $m)) {
                $s->extra()->number($m['number']);
            }

            /*
                Amsterdam Centraal
                Sat 4 Dec 2021 - 17:44
            */
            $pattern = "/^(?<name>.{2,})\n+(?<date>.*\d.*)$/";

            $departVal = implode("\n", $this->http->FindNodes("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Depart from'))}] and following-sibling::*[normalize-space()][1][{$this->eq($this->t('Arrive at'))}] ]/descendant::*[ *[normalize-space()][2] ][1]/*[normalize-space()]", $root));

            if (preg_match($pattern, $departVal, $m)) {
                $s->departure()->name($m['name']);

                if (preg_match("/^(.{6,}?)\s+-\s+({$this->patterns['time']})$/", $m['date'], $m2)) {
                    $s->departure()->date(strtotime($m2[2], strtotime($this->normalizeDate($m2[1]))));
                } else {
                    $s->departure()->date2($m['date']);
                }
            }

            $arriveVal = implode("\n", $this->http->FindNodes("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Arrive at'))}] and following-sibling::*[normalize-space()][1][{$this->eq($this->t('Train duration'))}] ]/descendant::*[ *[normalize-space()][2] ][1]/*[normalize-space()]", $root));

            if (preg_match($pattern, $arriveVal, $m)) {
                $s->arrival()->name($m['name']);

                if (preg_match("/^(.{6,}?)\s+-\s+({$this->patterns['time']})$/", $m['date'], $m2)) {
                    $s->arrival()->date(strtotime($m2[2], strtotime($this->normalizeDate($m2[1]))));
                } else {
                    $s->arrival()->date2($m['date']);
                }
            }

            $duration = $this->http->FindSingleNode("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Train duration'))}] and following-sibling::*[normalize-space()][1][{$this->eq($this->t('Ticket type'))}] ]", $root, true, '/^\d.+/');
            $s->extra()->duration($duration);

            $passengers = $this->http->FindNodes("descendant::*[preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Passengers'))}] and not(following-sibling::*[normalize-space()])]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Coach') or contains(normalize-space(), 'Seat'))]", $root, "/^{$this->patterns['travellerName']}$/u");

            $bookingCode = implode(", ", array_filter($this->http->FindNodes("descendant::*[preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Passengers'))}] and not(following-sibling::*[normalize-space()])]/descendant::text()[contains(normalize-space(), 'Coach')]/ancestor::td[1]", $root, "/{$this->opt($this->t('Coach'))}[\:\s]+([A-Z])/u")));

            if ($bookingCode) {
                $s->extra()
                    ->bookingCode($bookingCode);
            }

            $coach = array_filter($this->http->FindNodes("descendant::*[preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Passengers'))}] and not(following-sibling::*[normalize-space()])]/descendant::text()[contains(normalize-space(), 'Coach')]/ancestor::td[1]", $root, "/{$this->opt($this->t('Coach'))}[\:\s]+(\d+)/u"));

            if ($coach) {
                $s->extra()
                    ->car(implode(', ', $coach));
            }
            $seats = array_filter($this->http->FindNodes("descendant::*[preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Passengers'))}] and not(following-sibling::*[normalize-space()])]/descendant::text()[contains(normalize-space(), 'Seat')]/ancestor::td[1]", $root, "/{$this->opt($this->t('Seat'))}[\:\s]+(\d+)/u"));

            if ($seats) {
                $s->extra()
                    ->seats($seats);
            }

            if (count($passengers) > 0) {
                $travellers = array_filter(array_merge($travellers, $passengers));
            }
        }

        if (count($travellers) > 0) {
            $t->general()->travellers(array_unique($travellers), true);
        }
    }

    private function parseHotel(Email $email, \DOMNode $root): void
    {
        $this->logger->debug(__METHOD__);

        // it-657330576.eml, it-162632935.eml
        $h = $email->add()->hotel();

        if (!empty($this->tripStatus)) {
            $h->general()->status($this->tripStatus);
        }

        $xpathAddress = "descendant::{$this->xpath['headRow']}/*[1]/descendant::*[ *[normalize-space()][2] and not(*[normalize-space()][2][{$this->contains($this->t('Confirmation #'))}]) ][1]/*[normalize-space()]";

        $hotelName = $this->http->FindSingleNode($xpathAddress . '[1]', $root);

        if (preg_match("/at\s*(.+)/", $hotelName, $m)) {
            $hotelName = $m[1];
        }
        $address = $this->http->FindSingleNode($xpathAddress . '[2]', $root);
        $h->hotel()->name($hotelName)->address($address);

        $phone = $this->http->FindSingleNode("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t("Hotel's phone"))}] and following-sibling::*[normalize-space()][1][{$this->eq($this->t('Rooms'))}] ]", $root, true, "/^{$this->patterns['phone']}$/");

        if (!empty($phone)) {
            $h->hotel()
                ->phone($phone);
        }

        $confirmationVal = $this->http->FindSingleNode("descendant::tr[not(.//tr) and {$this->starts($this->t('Confirmation #'))}]", $root);

        if (preg_match("/^({$this->opt($this->t('Confirmation #'))})[:\s]*([-A-Z\d, ]{5,})$/", $confirmationVal, $m)) {
            $confs = preg_split("/ *, */", $m[2]);

            foreach ($confs as $conf) {
                $h->general()->confirmation($conf, $m[1]);
            }
        }

        $checkIn = $this->http->FindSingleNode("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Check-in'))}] and following-sibling::*[normalize-space()][1][{$this->eq($this->t('Check-out'))}] ]", $root, true, '/^.*\d.*$/');

        if (preg_match("/^(.{6,}?)\s+{$this->opt($this->t('from'))}\s+({$this->patterns['time']})(?:\s*to\s*[\d\:]+\s*A?P?M?)?$/", $checkIn, $m)) {
            $h->booked()->checkIn(strtotime($m[2], strtotime($this->normalizeDate($m[1]))));
        } elseif ($checkIn) {
            $h->booked()->checkIn2($checkIn);
        }

        $checkOut = $this->http->FindSingleNode("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Check-out'))}] and following-sibling::*[normalize-space()][1][{$this->eq($this->t("Hotel's phone"))}] ]", $root, true, '/^.*\d.*$/');

        if (empty($checkOut)) {
            $checkOut = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Check-out'))}]/following::text()[normalize-space()][1]/ancestor::div[1]", $root);
        }

        if (preg_match("/^(.{6,}?)\s+{$this->opt($this->t('until'))}\s+({$this->patterns['time']})$/u", $checkOut, $m)) {
            $h->booked()->checkOut(strtotime($m[2], strtotime($this->normalizeDate($m[1]))));
        } elseif ($checkOut) {
            $h->booked()->checkOut2($checkOut);
        }

        $rooms = [];
        $rooms = $this->http->FindNodes("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Rooms'))}] and following-sibling::*[normalize-space()][1][{$this->eq($this->t('Guests'))}] ][count(.//td) = 1]/descendant::td[1]/div/descendant::text()[normalize-space()][1]", $root);

        if (empty($rooms)) {
            $roomsVal = implode(' ',
                $this->http->FindNodes("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Rooms'))}] and following-sibling::*[normalize-space()][1][{$this->eq($this->t('Guests'))}] ]/descendant::text()[normalize-space() and not({$this->contains($this->t('Free room upgrade'))})]",
                    $root));

            if ($roomsVal) {
                foreach (preg_split('/\s*[,]+\s*/', $roomsVal) as $rName) {
                    $rooms[] = $rName;
                }
            }
        }

        foreach ($rooms as $rName) {
            $room = $h->addRoom();
            $room->setType($rName);
        }

        $guests = array_filter($this->http->FindNodes("descendant::*[{$this->eq($this->t('Guests'))}]/following-sibling::*[normalize-space()][1]/descendant::*[not(.//tr) and descendant::img[{$this->xpath['imgLoyalty']}] and normalize-space()='']/ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space() and not(descendant::img[{$this->xpath['imgLoyalty']}])]", $root, "/^{$this->patterns['travellerName']}$/u")); // it-657330576.eml

        if (count($guests) === 0) {
            $guests = $this->http->FindNodes("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Guests'))}] and following::text()[normalize-space()][1][{$this->eq($this->t('Cancellation'))}] ]//text()[normalize-space()]", $root);
        }

        if (count($guests) === 0) {
            $guestsVal = $this->http->FindSingleNode("descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t('Guests'))}] and following::text()[normalize-space()][1][{$this->eq($this->t('Perks'))}] ]", $root);

            if ($guestsVal) {
                $guests = [];

                foreach (preg_split('/(\s*[,]+\s*)+/', $guestsVal) as $gName) {
                    if (preg_match("/^{$this->patterns['travellerName']}$/u", $gName) > 0) {
                        $guests[] = $gName;
                    } else {
                        $guests = [];

                        break;
                    }
                }
            }
        }

        if (count($guests) > 0) {
            $h->general()->travellers($guests, true);
        }

        $accountsText = implode("\n", $this->http->FindNodes("descendant::tr[ count(*)=2 and *[1][normalize-space()='']/descendant::img[{$this->xpath['imgLoyalty']}] ]/*[2]/descendant::text()[normalize-space()]", $root));

        if (preg_match_all("/^(\d{8,}|[A-Z\d]{5,})$/m", $accountsText, $accMatches)) {
            $h->program()->accounts(array_unique($accMatches[1]), false);
        }

        $cancellation = $this->http->FindSingleNode("descendant::*[{$this->eq($this->t('Cancellation'))}][1]/following-sibling::*[normalize-space()][1]", $root);
        $h->general()->cancellation($cancellation);

        if (preg_match("/Free (?i)cancell?ation before (?<date>.+? \d{4}) at (?<time>{$this->patterns['time']})\s*(?:[.!(]|$)/", $cancellation, $m)
        ) {
            $h->booked()->deadline(strtotime($m['time'], strtotime($this->normalizeDate($m['date']))));
        }
        /* WTF?
        elseif (preg_match("//", $cancellation)) {
            $h->booked()
                ->nonRefundable();
        }
        */
    }

    private function parseHotel2(Email $email, \DOMNode $root): void
    {
        $this->logger->debug(__METHOD__);

        // it-201694955.eml
        $h = $email->add()->hotel();

        if (!empty($this->tripStatus)) {
            $h->general()->status($this->tripStatus);
        }

        $xpathAddress = "./descendant::text()[{$this->starts($this->t('Confirmation #'))}]/preceding::text()[normalize-space()]";

        $hotelName = $this->http->FindSingleNode($xpathAddress . '[2]', $root);

        if (preg_match("/\"((.+))\"/us", $hotelName, $m)
        || preg_match("/at\s*(.+)/us", $hotelName, $m)) {
            $hotelName = $m[1];
        }
        $address = $this->http->FindSingleNode($xpathAddress . '[1]', $root);
        $h->hotel()->name($hotelName)->address($address);

        $phone = $this->http->FindSingleNode("./descendant::*[ preceding-sibling::*[normalize-space()][1][{$this->eq($this->t("Hotel's phone"))}] and following-sibling::*[normalize-space()][1][{$this->eq($this->t('Rooms'))}] ]", $root, true, "/^{$this->patterns['phone']}$/");

        if (!empty($phone)) {
            $h->hotel()
                ->phone($phone);
        }

        $confirmationVal = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Confirmation #'))}]/ancestor::span[1]", $root);

        if (preg_match("/^({$this->opt($this->t('Confirmation #'))})[:\s]*([-A-Z\d]{5,})$/", $confirmationVal, $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        $checkIn = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Check-in'))}]/following::text()[normalize-space()][1]", $root, true, '/^.*\d.*$/');

        if (preg_match("/^(.{6,}?)\s+{$this->opt($this->t('from'))}\s+({$this->patterns['time']})(?:\s*to.*)?$/", $checkIn, $m)) {
            $h->booked()->checkIn(strtotime($m[2], strtotime($this->normalizeDate($m[1]))));
        } elseif ($checkIn) {
            $h->booked()->checkIn2($checkIn);
        }

        $checkOut = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Check-out'))}]/following::text()[normalize-space()][1]", $root, true, '/^.*\d.*$/');

        if (preg_match("/^(.{6,}?)\s+{$this->opt($this->t('until'))}\s+({$this->patterns['time']})$/u", $checkOut, $m)) {
            $h->booked()->checkOut(strtotime($m[2], strtotime($this->normalizeDate($m[1]))));
        } elseif ($checkOut) {
            $h->booked()->checkOut2($checkOut);
        }

        $rooms = [];
        $roomsVal = implode(' ', $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('Rooms'))}]/following::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()]", $root));

        if ($roomsVal) {
            foreach (preg_split('/\s*[,]+\s*/', $roomsVal) as $rName) {
                $rooms[] = $rName;
            }
        }

        foreach ($rooms as $rName) {
            $room = $h->addRoom();
            $room->setType($rName);
        }

        $guestNames = array_filter($this->http->FindNodes("./descendant::text()[{$this->eq($this->t('Guests'))}]/following::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()]", $root, "/^{$this->patterns['travellerName']}$/u"));

        if (count($guestNames) > 0) {
            $h->general()->travellers($guestNames, true);
        }

        $cancellation = $this->http->FindSingleNode("./descendant::*[{$this->eq($this->t('Cancellation'))}][1]/following-sibling::*[normalize-space()][1]", $root);
        $h->general()->cancellation($cancellation);

        if (preg_match("/Free (?i)cancell?ation before (?<date>.+? \d{4}) at (?<time>{$this->patterns['time']})\s*(?:[.!(]|$)/", $cancellation, $m)
        ) {
            $h->booked()->deadline(strtotime($m['time'], strtotime($this->normalizeDate($m['date']))));
        } elseif (preg_match("/Non-refundable at any time/", $cancellation)) {
            $h->booked()
                ->nonRefundable();
        }
    }

    private function parseCar(Email $email, \DOMNode $root): void
    {
        $this->logger->debug(__METHOD__);

        // it-156651009.eml
        $car = $email->add()->rental();

        if (!empty($this->tripStatus)) {
            $car->general()->status($this->tripStatus);
        }

        $carModel = $this->http->FindSingleNode("descendant::*[not(.//tr) and normalize-space()][1]", $root);
        $car->car()->model($carModel);

        $confirmationVal = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Confirmation #'))}]", $root);

        if (preg_match("/^({$this->opt($this->t('Confirmation #'))})[:\s]*([-A-Z\d]{5,})(?: [A-Z]{4})?$/", $confirmationVal, $m)) {
            $car->general()->confirmation($m[2], $m[1]);
        }

        $company = $this->http->FindSingleNode("descendant::*[ *[1][{$this->eq($this->t('Operator'))}] and *[2][normalize-space()=''] ]/*[2]/descendant::img/@alt", $root);

        if (($code = $this->normalizeProvider($company))) {
            $car->program()->code($code);
        } else {
            $car->extra()->company($company);
        }

        $xpathPickup = "descendant::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Pick-up'))}] ]/*[normalize-space()][2]/descendant-or-self::*[ *[normalize-space()][2] ][1]";

        $pickupDateText = $pickupDate = $pickupTime = null;
        $pickupDateVal = $this->http->FindSingleNode($xpathPickup . "/*[normalize-space()][1]", $root, true, '/^.*\d.*$/');
        $pickupLocation = $this->http->FindSingleNode($xpathPickup . "/*[normalize-space()][2]", $root);

        if (preg_match("/^(.{6,}?)\s+{$this->opt($this->t('at'))}\s+({$this->patterns['time']})$/", $pickupDateVal, $m)) {
            $pickupDateText = $m[1];
            $pickupTime = $m[2];
        } elseif ($pickupDateVal) {
            $pickupDateText = $pickupDateVal;
        }

        if ($pickupDateText && !preg_match('/\b\d{4}$/', $pickupDateText)
            && preg_match("/^(?<wday>[-[:alpha:]]+)\s*,\s*(?<date>\d{1,2}\s+[[:alpha:]]+|[[:alpha:]]+\s+\d{1,2})$/u", $pickupDateText, $m)
        ) {
            $weekDateNumber = WeekTranslate::number1($m['wday']);
            $pDateNormal = $this->normalizeDate($m['date']);

            if ($weekDateNumber && $pDateNormal && $this->year) {
                $pickupDate = EmailDateHelper::parseDateUsingWeekDay($pDateNormal . ' ' . $this->year, $weekDateNumber);
            }
        } else {
            $pickupDate = strtotime($this->normalizeDate($pickupDateText));
        }

        $car->pickup()->date(strtotime($pickupTime, $pickupDate))->location($pickupLocation);

        $xpathDropoff = "descendant::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Drop-off'))}] ]/*[normalize-space()][2]/descendant-or-self::*[ *[normalize-space()][2] ][1]";

        $dropoffDateText = $dropoffDate = $dropoffTime = null;
        $dropoffDateVal = $this->http->FindSingleNode($xpathDropoff . "/*[normalize-space()][1]", $root, true, '/^.*\d.*$/');
        $dropoffLocation = $this->http->FindSingleNode($xpathDropoff . "/*[normalize-space()][2]", $root);

        if (preg_match("/^(.{6,}?)\s+{$this->opt($this->t('at'))}\s+({$this->patterns['time']})$/", $dropoffDateVal, $m)) {
            $dropoffDateText = $m[1];
            $dropoffTime = $m[2];
        } elseif ($dropoffDateVal) {
            $dropoffDateText = $dropoffDateVal;
        }

        if ($dropoffDateText && !preg_match('/\b\d{4}$/', $dropoffDateText)
            && preg_match("/^(?<wday>[-[:alpha:]]+)\s*,\s*(?<date>\d{1,2}\s+[[:alpha:]]+|[[:alpha:]]+\s+\d{1,2})$/u", $dropoffDateText, $m)
        ) {
            $weekDateNumber = WeekTranslate::number1($m['wday']);
            $dDateNormal = $this->normalizeDate($m['date']);

            if ($weekDateNumber && $dDateNormal && $this->year) {
                $dropoffDate = EmailDateHelper::parseDateUsingWeekDay($dDateNormal . ' ' . $this->year, $weekDateNumber);
            }
        } else {
            $dropoffDate = strtotime($this->normalizeDate($dropoffDateText));
        }

        $car->dropoff()
            ->date(strtotime($dropoffTime, $dropoffDate))
            ->location($dropoffLocation);

        $cancellation = implode(' ', $this->http->FindNodes("descendant::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Cancellation'))}] ]/*[normalize-space()][2]/descendant::text()[normalize-space() and not(ancestor::a)]", $root));
        $car->general()->cancellation($cancellation);

        $mainDriver = $this->http->FindSingleNode("descendant::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Main driver'))}] ]/*[normalize-space()][2]", $root, true, "/^{$this->patterns['travellerName']}$/u");
        $car->general()->traveller($mainDriver, true);
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
            'avis' => ['Avis'],
            'sixt' => ['Sixt'],
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})\s+([[:alpha:]]+)$/u', $text, $m)) {
            // 8 Apr
            $day = $m[1];
            $month = $m[2];
            $year = '';
        } elseif (preg_match('/^([[:alpha:]]+)\s+(\d{1,2})$/u', $text, $m)) {
            // Apr 8
            $month = $m[1];
            $day = $m[2];
            $year = '';
        } elseif (preg_match('/^[[:alpha:]]+\.?\s*[,\s]\s*(\d+)\s*([[:alpha:]]+)\s*(\d{4})$/u', $text, $m)) {
            // ???
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

        return $text;
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

            if (!empty($phrases['Depart from']) && !empty($phrases['Arrive at'])
                && $this->http->XPath->query("//*[{$this->contains($phrases['Depart from'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Arrive at'])}]")->length > 0
                || !empty($phrases['Check-in']) && !empty($phrases['Check-out'])
                && $this->http->XPath->query("//*[{$this->contains($phrases['Check-in'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Check-out'])}]")->length > 0
                || !empty($phrases['Pick-up']) && !empty($phrases['Drop-off'])
                && $this->http->XPath->query("//*[{$this->contains($phrases['Pick-up'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Drop-off'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            'US$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = trim($s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return $s;
    }
}
