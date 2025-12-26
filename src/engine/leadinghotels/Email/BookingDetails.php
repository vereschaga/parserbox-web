<?php

namespace AwardWallet\Engine\leadinghotels\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingDetails extends \TAccountChecker
{
    public $mailFiles = "leadinghotels/it-110900837.eml, leadinghotels/it-123346895.eml, leadinghotels/it-133994988.eml, leadinghotels/it-137267777.eml, leadinghotels/it-143865736.eml, leadinghotels/it-161116120.eml, leadinghotels/it-18100354.eml, leadinghotels/it-182708974.eml, leadinghotels/it-195715062.eml, leadinghotels/it-390729503.eml, leadinghotels/it-417284527.eml, leadinghotels/it-42462885.eml, leadinghotels/it-42845485.eml, leadinghotels/it-619632812.eml, leadinghotels/it-680784301.eml, leadinghotels/it-69624358.eml";

    private $bodyDetectors = [
        'en' => ['Departure Date:', 'Departure date', 'Departure Date'],
        'de' => ['Abreisedatum', 'Anreisedatum'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'Confirmation Number:'                            => ['Confirmation Number:', 'Confirmation number', 'Confirmation', 'Confirmation Number', 'Party confirmation Number', 'Confirmation Party Number'],
            'Cancellation number'                             => 'Cancellation number',
            'Arrival Date:'                                   => ['Arrival Date:', 'Arrival date', 'Arrival Date'],
            'Departure Date:'                                 => ['Departure Date:', 'Departure date', 'Departure Date'],
            // 'Reservation Phone' => '',
            'contactFieldNames'                               => ['Tel:', 'Fax:', 'www.OldCourseHotel.co.uk', 'Tel.', 'Téléphone'],
            'Guest Name:'                                     => ['Guest Name:', 'Guest name', 'Guest Name', 'Name:', 'Name'],
            'Thank you very much for making a reservation at' => [
                'Thank you for your reservation at',
                'Thank you for choosing to stay at',
                'Thank you for choosing',
                'Thank you very much for making a reservation at',
                'Thank you very much for updating your reservation at',
                'Many thanks for choosing',
                'We look forward to welcoming you to',
                'Thank you for choosing',
                'THANK YOU FOR CHOOSING',
                'Thank you for booking at',
                'Thank you for considering the',
                'Thank you for your interest in staying at',
                'Thank you for considering',
                'Thank you for selecting',
                'We look forward to welcoming you back to Stockholm and the',
            ],
            'guestSummary'                                    => ['Guest Summary', 'No. of Guests', 'No. of Persons', 'Number of Guests:', 'Adults / Children', 'Number of Guests', 'Number of guests', 'Number of persons'],
            'Room Details:'                                   => ['Room Details:', 'Room Type:', 'Room type', 'Room Type', 'Room Type Description', 'Room Name:'],
            'Our Check-in time is from'                       => ['Our Check-in time is from', 'You can check in to your room from', 'Check-in: from', 'Check In Time After:', 'Check-in :', 'Check-in : from'],
            'Check-out time until'                            => ['Check-out time until', 'Check-out:', 'Check Out Time Before:', 'Check-Out Time', 'Check-out : before'],
            'Child'                                           => ['Child', 'Children', 'Number of Children:', 'child'],
            'Number of Rooms:'                                => ['Number of Rooms:', 'Number of Rooms', 'Number of rooms'],
            'Arrival time'                                    => ['Arrival time', 'Check in from', 'Check-in', 'Check-In Time', 'Check-in from', 'Check in time:'],
            'Departure time'                                  => ['Departure time', 'Check out', 'Check-out', 'Check-Out Time', 'Check out time:'],
            'Adults'                                          => ['Adults', 'Number of Adults:', 'adults', 'adult'],
            'totalPrice'                                      => ['Cost of stay', 'Room Total', 'Total cost of the stay including city tax'],
            'Number of nights'                                => ['Number of nights', 'Number of Nights'],
            'Rate Details:'                                   => ['Rate Details:', 'Preferential Room Rate', 'Room Rate'],
            'Rate Includes'                                   => ['Rate Includes', 'Rate Information', 'Included in the rate', 'Rate type'],
            'Daily Rate'                                      => ['Daily Rate', 'Daily rate', 'Rate per Night', 'Rate per night', 'Rate per room'],
            'I am pleased to'                                 => ['I am pleased to', ', we are pleased to'],
            'Free cancellation'                               => ['Free cancellation', 'You can modify or cancel your reservation free of charge till'],
            'Contact'                                         => ['Contact', 'CONTACT'],
        ],
        'de' => [
            'Confirmation Number:'                            => 'Bestätigungsnr.',
            //            'Cancellation number'                             => '',
            'Arrival Date:'                                   => 'Anreisedatum',
            'Departure Date:'                                 => 'Abreisedatum',
            'Reservation Phone'                               => 'Telefonnummer für Buchungen',
            'contactFieldNames'                               => ['Telefonnummer für Buchungen', 'www.thechediandermatt.com'],
            'Guest Name:'                                     => 'Gast Name',
            'Thank you very much for making a reservation at' => 'Vielen Dank, dass Sie Ihren Aufenthalt im',
            // 'guestSummary' => '',
            'Room Details:'                                   => 'Zimmerkategorie',
            // 'Our Check-in time is from' => '',
            // 'Check-out time until' => '',
            // 'Child' => '',
            // 'Number of Rooms:' => '',
            'Arrival time'                                    => 'Check-In-Zeit',
            'Departure time'                                  => 'Check-Out-Zeit',
            'Adults'                                          => 'Anzahl Erwachsene',
            'totalPrice'                                      => 'Gesamtpreis Zimmer',
            // 'Number of nights' => '',
            // 'Rate Details:' => '',
            // 'Rate Includes' => '',
            // 'Daily Rate' => '',
            // 'Contact' => [],
        ],
    ];

    private $patterns = [
        'time'  => '\d{1,2}(?:[.:]+\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
        'phone' => '\(?[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
    ];

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->getProvider() === null) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('BookingDetails' . ucfirst($this->lang));

        $providerCode = $this->getProvider();

        if ($providerCode && $providerCode !== 'leadinghotels') {
            $email->setProviderCode($providerCode);
        }

        $hotelRoots = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Arrival Date:'))}] ]/ancestor::*[ descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Departure Date:'))}] ] ][1]");

        if ($hotelRoots->length < 2) {
            $this->parseHotel($email);
        } else {
            // it-390729503.eml
            foreach ($hotelRoots as $i => $hRoot) {
                $addXpathCond = "[count(preceding::text()[{$this->eq($this->t('Arrival Date:'))}]) = " . ($i + 1) . " and count(following::text()[{$this->eq($this->t('Arrival Date:'))}]) = " . ($hotelRoots->length - $i - 1) . "]";
                $this->parseHotel($email, $hRoot, $addXpathCond);
            }
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

    public static function getEmailProviders()
    {
        return ['leadinghotels', 'preferred', 'dorchester'];
    }

    private function parseHotel(Email $email, ?\DOMNode $root = null, $addXpathCond = ''): void
    {
        $h = $email->add()->hotel();

        $xpathFragmentNextCell = '/ancestor::*[../self::tr][1]/following-sibling::*[normalize-space()][1]';

        // hotelName
        $hotelName = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Hotel Name'))}] ]/*[normalize-space()][2]", $root); // it-133994988.eml

        if (!$hotelName) {
            foreach ((array) $this->t('Thank you very much for making a reservation at') as $phrase) {
                $hotelNames = array_filter($this->http->FindNodes("//text()[{$this->contains($phrase)}]", null, "/{$this->opt($phrase)}(?:\s+the)?\s+(.{2,}?)(?:\sin|, one of our legendary )/mu"));

                if (count($hotelNames) === 0) {
                    $hotelNames = array_filter($this->http->FindNodes("//text()[{$this->contains($phrase)}]", null, "/{$this->opt($phrase)}(?:\s+the)?\s+(.{2,}?)(?:\s*[,.!]|$)/mu"));
                }

                if (count($hotelNames) === 0) {
                    $hotelNames = array_filter($this->http->FindNodes("//text()[{$this->contains($phrase)}]/ancestor::td[1]", null, "/{$this->opt($phrase)}(?:\s+the)?\s+(.{2,}?)(?:\s*[,.!]|$)/mu"));
                }

                if (count($hotelNames) === 0) {
                    $hotelNames = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(), 'It is our pleasure to confirm your stay at')]", null, "/{$this->opt($this->t('It is our pleasure to confirm your stay at'))}(.+)\;/mu"));
                }

                if (count($hotelNames) === 0) {
                    $hotelNames = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Welcome to')]", null, "/{$this->opt($this->t('Welcome to'))}(.+){$this->opt($this->t(' at '))}/mu"));
                }

                if (count(array_unique($hotelNames)) === 1) {
                    $hotelName = array_shift($hotelNames);

                    break;
                }
            }
        }

        if ($hotelName) {
            $hotelName = str_replace('é', 'e', $hotelName);
            $h->hotel()->name($hotelName);
        }

        // -------------Hotel Bel-Air ------------------
        if (empty($h->getAddress()) && !empty($h->getHotelName())) {
            $hotelInfo = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Cancellation policy']/preceding::text()[{$this->contains($h->getHotelName())}][1]/ancestor::td[1]/descendant::text()[normalize-space()]"));

            if (preg_match("/{$this->opt($h->getHotelName())}\S*\s+(?<address>.+)Phone\:?(?<phone>[+][\d\-\s]+)/sui", $hotelInfo, $m)) {
                $h->hotel()
                    ->address(str_replace("\n", " ", $m['address']))
                    ->phone($m['phone']);
            }
        }

        if (empty($h->getAddress()) && !empty($h->getHotelName())) {
            $hotelInfo = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'T:')]/ancestor::tr[1]/descendant::text()[normalize-space()]"));

            if (preg_match("/{$this->opt($h->getHotelName())}\n(?<address>.+)T\:\s*(?<phone>[+\s\(\)\d]+)\n/sui", $hotelInfo, $m)) {
                $h->hotel()
                    ->address(str_replace("\n", " ", $m['address']))
                    ->phone($m['phone']);
            }
        }

        if (empty($h->getAddress()) && !empty($h->getHotelName())) {
            $hotelInfo = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Phone:')]/ancestor::tr[1]/descendant::text()[normalize-space()]"));

            if (preg_match("/{$this->opt($h->getHotelName())}[;]?\n(?<address>.+)Phone\:\s*(?<phone>[+\s\(\)\d]+)\n/sui", $hotelInfo, $m)) {
                $h->hotel()
                    ->address(str_replace("\n", " ", $m['address']))
                    ->phone($m['phone']);
            }
        }

        if (empty($h->getAddress()) && !empty($h->getHotelName())) {
            $hotelInfo = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), '/ T.')]/ancestor::tr[1]/descendant::text()[normalize-space()]"));

            if (preg_match("/^\s*(?<address>\d.+\s*,\s*[A-Z]{2}\s*,\s*\d{5})\s*\\/ T.\s*(?<phone>[+ \(\)\d\.]+)(?:\n|$)/u", $hotelInfo, $m)
                && strlen(preg_replace("/\D/", '', $m['phone'])) > 5) {
                $h->hotel()
                    ->address(str_replace("\n", " ", $m['address']))
                    ->phone($m['phone']);
            }
        }

        if (empty($h->getAddress()) && !empty($h->getHotelName()) && $email->getProviderCode() == 'dorchester') { // it-390729503.eml
            $hotelInfo = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'dorchestercollection.com')]/ancestor::tr[1]/descendant::text()[normalize-space()]"));
            /*
                The Beverly Hills Hotel
                9641 Sunset Boulevard
                Beverly Hills
                CA 90210, USA
                +1 424 421 0060
                dorchestercollection.com
            */
            if (preg_match("/(?:^|\n){$this->opt($h->getHotelName())}\n(?<address>(?:\d.+|.+\d|.*\bPark\b.*)(?:\n.+){0,3})\n(?<phone>{$this->patterns['phone']})\ndorchestercollection\.com(?:\n|$)/u", $hotelInfo, $m)
                || preg_match("/(?:^|\n){$this->opt($h->getHotelName())}(?<address>(?:\n.+){0,3})\n(?<phone>{$this->patterns['phone']})\ndorchestercollection\.com(?:\n|$)/u", $hotelInfo, $m) && strpos($m['address'], 'Dubai') !== false
            ) {
                $h->hotel()->address(preg_replace('/\s+/', ' ', trim($m['address'])))->phone($m['phone']);
            }
        }

        if (empty($h->getAddress()) && !empty($h->getHotelName())) {
            $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t('contactFieldNames'))}]/ancestor::tr[1]/descendant::text()[normalize-space()]"));

            if (preg_match("/{$this->opt($h->getHotelName())}\n(?<address>.+){$this->opt($this->t('contactFieldNames'))}\s*(?<phone>[+\s\(\)\d]+)/sui", $hotelInfo, $m)) {
                $h->hotel()
                    ->address(str_replace("\n", " ", $m['address']))
                    ->phone($m['phone']);
            }
        }

        if (empty($h->getAddress()) && !empty($h->getHotelName())) {
            $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Contact'))}]/following::text()[normalize-space()][1]/ancestor::td[2]/descendant::text()[normalize-space()]"));

            if (preg_match("/({$this->opt($h->getHotelName())}).*\n(?<address>(?:.+\n){1,5}){$this->opt($this->t('contactFieldNames'))}\s*(?<phone>[+\s\(\)\d\-]+)/ui", $hotelInfo, $m)) {
                $h->hotel()
                    ->address(str_replace("\n", " ", $m['address']))
                    ->phone($m['phone']);
            }
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Tel')]");

            if (preg_match("/Tel\S*\s*(?<phone>[+\d\s\/]+)\s*\S\s*Fax\S*\s*(?<fax>[+\d\s\/]+)$/u", $phone, $m)) {
                $h->hotel()
                    ->phone($m['phone'])
                    ->fax($m['fax']);
            }
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[normalize-space()='Main Number']/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^([+][\d\s\-]+)$/");

            if (!empty($phone)) {
                $h->hotel()
                    ->phone($phone);
            }
        }

        if (!empty($hotelName) && empty($address)) {
            $hotelNameTemp = mb_strtoupper($hotelName);

            $addressText = implode("\n", $this->http->FindNodes("//text()[{$this->starts($hotelNameTemp)}]/ancestor::div[2]/descendant::text()[normalize-space()]"));

            if (empty($addressText)) {
                $addressText = implode("\n", $this->http->FindNodes("//img[contains(@src, 'collection')]/following::text()[{$this->starts($hotelName)}]/ancestor::div[1]/descendant::text()[normalize-space()]"));
            }

            if (preg_match("/^(?<hotelName>$hotelNameTemp.+)\n(?<address>(?:.+\n*){1,4})$/iu", $addressText, $m)
            || preg_match("/^(?<address>(?:.+\n*){1,4})(?<hotelName>$hotelNameTemp.+)$/iu", $addressText, $m)) {
                $h->hotel()
                    ->address(str_replace("\n", " ", $m['address']))
                    ->name($m['hotelName']);
            }
        }

        if ($h->getHotelName() === 'Hotel Okura Amsterdam') {
            $h->hotel()
                ->address("Ferdinand Bolstraat 333, 1072 LH Amsterdam, The Netherlands");
        }

        if ($h->getHotelName() === 'Hotel Principe di Savoia') {
            $h->hotel()
                ->address("Piazza Della Repubblica 17, Stazione Centrale, 20124 Milan, Italy");
        }

        if ($h->getHotelName() === 'Hôtel Plaza Athenee') {
            $h->hotel()
                ->address("25 avenue Montaigne 75008 Paris, France");
        }

        if (empty($h->getAddress()) && in_array($h->getHotelName(), ['Hotel Bel-Air', 'Le Meurice', 'The Rittenhouse', '45 Park Lane', 'Coworth Park', 'The Beverly Hills Hotel'])) {
            $h->hotel()->noAddress();
        }

        // -------------Hotel Bel-Air ------------------

        // status
        $status = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('I am pleased to')) . ']', null, true, '/' . $this->opt($this->t('I am pleased to')) . '\s+(\w+)/u');

        if ($status) {
            $h->general()->status($status);
        }

        // Guests
        // Kids
        $guests = $child = null;

        foreach ((array) $this->t('guestSummary') as $phrase) {
            $guestSummary = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($phrase)}] ]/*[normalize-space()][2]");
            $guests = preg_match("/{$this->opt($this->t('Adults'))}\s*:\s*(\d{1,3})(?:\D{3}|$)/", $guestSummary, $m)
                || preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Adults'))}/", $guestSummary, $m)
                || preg_match("/^(\d{1,3})$/", $guestSummary, $m) ? $m[1] : null;
            $child = preg_match("/{$this->opt($this->t('Child'))}\s*:\s*(\d{1,3})(?:\D{3}|$)/", $guestSummary, $m)
                || preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Child'))}/", $guestSummary, $m) ? $m[1] : null;

            if (preg_match("/^(\d{1,3})\s*\/\s*(\d{1,3})$/", $guestSummary, $m)) {
                $guests = $m[1];
                $child = $m[2];
            }

            if ($guests !== null || $child !== null) {
                break;
            }
        }

        if ($guests === null && !empty($addXpathCond)) {
            $guestsAll = $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Adults'))}]" . $xpathFragmentNextCell . $addXpathCond, null, "/^\d{1,3}\b/");

            if (!empty($guestsAll)) {
                $guests = array_sum($guestsAll);
            }
        }

        if ($guests === null) {
            $guests = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Adults'))}]" . $xpathFragmentNextCell, $root, false, "/^\d{1,3}\b/");
        }
        $h->booked()->guests($guests, false, true);

        if ($child === null && !empty($addXpathCond)) {
            $childAll = $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Child'))}]" . $xpathFragmentNextCell . $addXpathCond, null, "/^\s*(\d{1,3})\s*$/");

            if (!empty($childAll)) {
                $child = array_sum($childAll);
            }
        }

        if ($child === null) {
            $child = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Child'))}]" . $xpathFragmentNextCell, $root, false, "/^\s*(\d{1,3})\s*$/");
        }

        if ($child !== null) {
            $h->booked()->kids($child);
        }

        // Rooms
        $h->booked()->rooms($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Number of Rooms:'))}]" . $xpathFragmentNextCell, $root), false, true);

        $travellers = [];

        $travellersText = str_replace(['Mr & Mrs', '&Family', 'Mr. & Mrs.', 'Mr. and Mrs.', 'Mr ', 'Mrs ', 'Mr. ', 'Ms. '], '', $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Guest Name:'))}]" . $xpathFragmentNextCell, $root));

        if (is_array($travellersText) == false) {
            $travellers = $this->travellerCollection($travellersText, $travellers);
        } else {
            foreach ($travellersText as $pax) {
                $travellers = $this->travellerCollection($pax, $travellers);
            }
        }

        $addTraveller = str_replace(['Mr & Mrs', '&Family', 'Mr. & Mrs.', 'Mr. and Mrs.', 'Mr ', 'Mrs ', 'Mr. '], '', $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Travelling with'))}][1]" . $xpathFragmentNextCell, $root));

        if (!empty($addTraveller)) {
            $travellers[] = $addTraveller;
        }

        $travellers = array_filter($travellers);

        if (empty($travellers) && !empty($root)) {
        } else {
            $h->general()->travellers($travellers);
        }

        // confirmation number
        $confirmationTitle = $this->http->FindSingleNode("(./descendant::text()[{$this->eq($this->t('Confirmation Number:'))}])", $root, true, '/^(.+?)[\s:：]*$/u');
        $confirmationValue = $this->htmlToText($this->http->FindHTMLByXpath("descendant::text()[{$this->eq($this->t('Confirmation Number:'))}]" . $xpathFragmentNextCell . "/descendant::text()[normalize-space()][not(contains(normalize-space(), ' '))]", null, $root));

        if (empty($confirmationValue)) {
            $confirmationValue = $this->htmlToText($this->http->FindHTMLByXpath("descendant::text()[{$this->eq($this->t('Confirmation Number:'))}]" . $xpathFragmentNextCell . "/descendant::text()[normalize-space()][contains(normalize-space(), ' - ')]", null, $root));
        }
        $confirmationRows = preg_replace("/\s+Party number$/m", "",
            preg_split('/[ ]*\s+[ ]*/', $confirmationValue));

        $confirmationRows = array_filter($confirmationRows);

        foreach ($confirmationRows as $confirmation) {
            if ($confirmation === '-') {
                continue;
            }
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        if (empty($confirmationRows)) {
            $conf = $this->htmlToText($this->http->FindHTMLByXpath("descendant::text()[{$this->eq($this->t('Cancellation number'))}]" . $xpathFragmentNextCell, null, $root));

            if (!empty($conf)) {
                $h->general()
                    ->noConfirmation()
                    ->cancellationNumber($conf)
                    ->status('Cancelled')
                    ->cancelled();
            }
        }

        // checkInDate
        $checkIn = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Arrival Date:'))}]" . $xpathFragmentNextCell, $root);

        if (empty($checkIn)) {
            $checkIn = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Arrival Date:'))}]" . $xpathFragmentNextCell, $root);
        }

        if ($email->getProviderCode() == 'preferred') {
            $h->booked()->checkIn2($this->ModifyDateFormat($checkIn));
        } else {
            $h->booked()->checkIn2($this->normalizeDate($checkIn));
        }

        // checkOutDate
        $checkOut = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Departure Date:'))}]" . $xpathFragmentNextCell, $root);

        if (empty($checkOut)) {
            $checkOut = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Departure Date:'))}]" . $xpathFragmentNextCell, $root);
        }

        if ($email->getProviderCode() == 'preferred') {
            $h->booked()->checkOut2($this->ModifyDateFormat($checkOut));
        } else {
            $h->booked()->checkOut2($this->normalizeDate($checkOut));
        }

        // checkInDate
        $checkInTime = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Our Check-in time is from'))}]", $root, false, "/{$this->opt($this->t('Our Check-in time is from'))}\s+({$this->patterns['time']})/i")
            ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Arrival time'))}]" . $xpathFragmentNextCell, $root, false, '/(' . $this->patterns['time'] . ')/i')
            ?? $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Arrival date'))}]", $root, false, '/(' . $this->patterns['time'] . ')/i')
            ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Check-in / Checkout'))}]/ancestor::tr[1]/descendant::td[2]", $root, false, '/(' . $this->patterns['time'] . ')\s*\//i')
        ;

        if (!empty($h->getCheckInDate()) && $checkInTime) {
            $h->booked()->checkIn(strtotime($this->normalizeTime($checkInTime), $h->getCheckInDate()));
        }

        // checkOutDate
        $checkOutTime = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Check-out time until'))}]", $root, false, '/' . $this->opt($this->t('Check-out time until')) . '\s+(' . $this->patterns['time'] . ')/i')
            ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Departure time'))}]" . $xpathFragmentNextCell, $root, false, '/(' . $this->patterns['time'] . ')/i')
            ?? $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Departure date'))}]", $root, false, '/(' . $this->patterns['time'] . ')/i')
            ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Check-in / Checkout'))}]/ancestor::tr[1]/descendant::td[2]", $root, false, '/\s*\/\s*(' . $this->patterns['time'] . ')/i')
            ?? $this->normalizeTime($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Check-out time until'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]", $root, false, '/\s*Noon\s*/i'))
        ;

        if (!empty($h->getCheckOutDate()) && $checkOutTime) {
            $h->booked()->checkOut(strtotime($this->normalizeTime($checkOutTime), $h->getCheckOutDate()));
        }

        if (empty($checkInTime) && empty($checkOutTime)) {
            $timeText = $this->http->FindSingleNode("descendant::text()[normalize-space()='Arrival and departure:']/following::text()[normalize-space()][1]", $root);

            if (preg_match("/from\s*(?<arrTime>[\d\:]+\s*a?p?m).+before\s*(?<depTime>(?:[\d\:]+\s*a?p?m?|noon))/", $timeText, $m)) {
                $m['depTime'] = str_replace('noon', '12:00', $m['depTime']);

                $h->booked()->checkIn(strtotime($this->normalizeTime($m['arrTime']), $h->getCheckInDate()));
                $h->booked()->checkOut(strtotime($this->normalizeTime($m['depTime']), $h->getCheckOutDate()));
            }
        }

        // r.type
        $types = $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Room Details:'))}]" . $xpathFragmentNextCell . $addXpathCond);

        if (count($types) == 1) {
            if (preg_match_all("/(?:with)?(?:^|\s|&|)?(\d\D+(?:[\d\s\-]+\s*sq\.\s*[a-z\)])?[a-z\)])/u", $types[0], $m)) {
                $types = $m[1];
            }
        }

        foreach ($types as $type) {
            $r = $h->addRoom();
            $r->setType($type);
        }

        if (!empty($roomDescription = $this->http->FindSingleNode("descendant::text()[normalize-space()='Informationen zur Zimmerrate']/ancestor::tr[1]/descendant::td[2]", $root))) {
            $r->setDescription($roomDescription);
        }

        // r.rate
        $freeNights = null;
        $rate = $this->http->FindSingleNode('descendant::text()[' . $this->eq($this->t('Rate Details:')) . "]{$xpathFragmentNextCell}{$addXpathCond}/descendant::text()[normalize-space()][1][" . $this->contains($this->t('per night')) . ']', null, true, '/^.*\d.*$/')
            ?? $this->http->FindSingleNode('descendant::text()[' . $this->eq($this->t('Room rate')) . ']' . $xpathFragmentNextCell . $addXpathCond, null, true, '/^.*\d.*$/')
            ?? $this->http->FindSingleNode('descendant::text()[' . $this->starts($this->t('Nightly Rate')) . ']' . $xpathFragmentNextCell . $addXpathCond, null, true, '/^.*\d.*$/')
            ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Daily Rate'))}]" . $xpathFragmentNextCell . $addXpathCond, null, true, '/^(?:\d[,.\'\d ]*[A-Z]{3}|[A-Z]{3}\s*\d[,.\'\d ]*|\d[,.\'\d ]*[^\-\d)(]+|[^\-\d)(]+\d[,.\'\d ]*)$/')
        ;

        if ($rate !== null) {
            $r->setRate($rate);
        } else {
            // it-133994988.eml
            $rateCurrencies = $rates = [];

            $dailyRate = '';
            $dailyRateRows = $this->http->XPath->query("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Daily Rate'))}] ]/*[normalize-space()][2]", $root);

            if ($dailyRateRows->length == 0) {
                $dailyRateRows = $this->http->XPath->query("descendant::text()[(normalize-space()='The following rates apply during your stay:')]/ancestor::tr[1]/following::table[1]/descendant::tr[not(contains(normalize-space(), 'Rate is inclusive'))]", $root);
            }

            foreach ($dailyRateRows as $drRow) {
                $dailyRate .= $this->htmlToText($this->http->FindHTMLByXpath('.', null, $drRow)) . "\n";
            }

            $dailyRateValues = preg_split('/[ ]*\n+[ ]*/', $dailyRate);

            $rates = [];

            foreach ($dailyRateValues as $drValue) {
                $drValue = str_replace('\\', '', $drValue);

                if (preg_match("/^.+\d{4}\s+(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*?)[ ]*$/", $drValue, $matches)
                    || preg_match("/^.+[:]+\s*(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*?)[ ]*$/", $drValue, $matches)
                    || preg_match("/^\w+\s*\d{2}\s+(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*?)[ ]*$/", $drValue, $matches)
                    || preg_match("/\b(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d ]*?)(?:\s+{$this->opt($this->t('for'))}\s+|$)/", $drValue, $matches)
                ) {
                    // Thursday, June 16, 2022    CHF 0.00
                    // June 12: €1,850
                    // CHF 1'783.00 for the Junior Suite
                    $rateCurrencies[] = $matches['currency'];
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;

                    $rateAmount = PriceHelper::parse($matches['amount'], $currencyCode);
                    $rates[] = $rateAmount . ' ' . $matches['currency'];

                    if ($rateAmount == 0) {
                        $freeNights++;
                    }
                }
            }

            $nights = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Number of nights'))}]" . $xpathFragmentNextCell, $root, true, "/^\d{1,3}\b/");

            if ($h->getRoomsCount() === 1) {
                if ($nights !== null && count($rates) === (int) $nights) {
                    // it-133994988.eml
                    $r->setRates($rates);
                } elseif (count($rates) > 0) {
                    // it-182708974.eml
                    $r->setRate(implode('; ', $rates));
                }
            } elseif (count($h->getRooms()) > 1) {
                if (count($rates) === count($h->getRooms())) {
                    foreach ($h->getRooms() as $i => $gt) {
                        $gt->setRate($rates[$i]);
                    }
                }
            }
        }

        if (count($h->getRooms()) > 0 && empty($r->getRate()) /*&& $h->getRoomsCount() > 1*/) {
            $rates = array_filter($this->http->FindNodes("descendant::text()[{$this->eq($this->t('Daily Rate'))}]" . $xpathFragmentNextCell . $addXpathCond . "/descendant::text()[normalize-space()]", null,
                '/^\s*(?:\d[,.\'\d ]*[A-Z]{3}\b|[A-Z]{3}\s*\d[,.\'\d ]*|\d[,.\'\d ]*[^\-\d)(]{1,6}|[^\-\d)(]{1,6}\d[,.\'\d ]*)(?: |$)/'));

            if (count($rates) === count($h->getRooms())) {
                foreach ($h->getRooms() as $i => $gt) {
                    $gt->setRate($rates[$i]);
                }
            }
        }

        $rateType = null;
        $rateTypeValues = [];
        $rateTypeRows = $this->http->XPath->query("descendant::text()[{$this->eq($this->t('Rate Includes'))}]" . $xpathFragmentNextCell, $root);

        foreach ($rateTypeRows as $rtRow) {
            $rateTypeValues[] = implode(' ', $this->http->FindNodes('descendant::text()[normalize-space()]', $rtRow));
        }

        if (count($rateTypeValues) === $h->getRoomsCount()) {
            if (count($h->getRooms()) === $h->getRoomsCount()) {
                foreach ($h->getRooms() as $i => $gt) {
                    $gt->setRateType($rateTypeValues[$i]);
                }
            } else {
                foreach ($rateTypeValues as $rt) {
                    $r = $h->addRoom();
                    $r->setRateType($rt);
                }
            }
        } elseif (count(array_unique($rateTypeValues)) === 1) {
            // it-390729503.eml
            $rateType = $rateTypeValues[0];
            $r->setRateType($rateType);
        }

        if ($h->getRoomsCount() === null
            && isset($rates)
            && count($rates) > 0
            && !isset($r)
        ) {
            $h->addRoom()->setRates($rates);
        }

        if ($freeNights !== null && $r->getRateType()
            && preg_match("/night free in one of our luxurious room/iu", $r->getRateType()) > 0
        ) {
            $h->setFreeNights($freeNights);
        }

        // address
        $phone = '';
        $address = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Hotel Address'))}] ]/*[normalize-space()][2]", $root); // it-133994988.eml

        if (empty($address)) {
            $info = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(), '| P:')]", $root);

            if (preg_match("/(?<address>.+){$this->opt($this->t('| P:'))}\s*(?<phone>.+)/", $info, $m)) {
                $address = $m['address'];
                $phone = $m['phone'];
            }
        }

        if ($address) {
            $h->hotel()->address($address);
        } elseif (!empty($h->getHotelName())) {
            // The Merrion Hotel, Upper Merrion Street, Dublin 2, Ireland
            $address = $this->http->FindSingleNode('descendant::text()[' . $this->starts($this->t('contactFieldNames')) . ']/preceding::text()[normalize-space()][1]', $root, true, '/' . preg_quote($h->getHotelName(), '/') . '[.,\s]+(.+)/u');

            if ($address) {
                $h->hotel()->address($address);
            } else {
                $text = join("\n", $this->http->FindNodes('descendant::a[' . $this->starts($this->t('contactFieldNames')) . ']/preceding-sibling::text()[normalize-space()]', $root));

                if (preg_match("/^(.+?)\n+({$this->patterns['phone']})/", $text, $m)) {
                    $h->hotel()->address($m[1]);
                    $h->hotel()->phone($m[2]);
                }
            }

            if (empty($h->getAddress())) {
                $contactsText = $this->htmlToText($this->http->FindHTMLByXpath("descendant::text()[starts-with(normalize-space(),'T.')]/ancestor::td[1]", null, $root));

                if (preg_match("/T\.\s*(?<phone>{$this->patterns['phone']})[\s\S]+(?i)\.COM\s+(?<address>.{3,}?)\s*\/\s*#KNICKOFTIME/", $contactsText, $m)) {
                    $h->hotel()->phone($m['phone'])->address($m['address']);
                }

                if (preg_match("/^{$h->getHotelName()}\s*(?<address>.+)\s+T\.\s*(?<phone>[+\d\s\(\)]+)/su", $contactsText, $m)) {
                    $h->hotel()
                        ->address(str_replace("\n", " ", $m['address']))
                        ->phone($m['phone']);
                }
            }

            if (empty($h->getAddress())) {
                // it-123346895.eml, it-143865736.eml
                $emailAddreses = ['resa@royal-riviera.com', 'reservations.her@dorchestercollection.com', 'Reservations.lmp@dorchestercollection.com'];
                $contactsText = $this->htmlToText($this->http->FindHTMLByXpath("descendant::text()[{$this->contains($emailAddreses)}]/ancestor::td[1]", null, $root));

                if (preg_match("/^(?:[\s\S]+\n[ ]*|\D+\|\s*)?(?<address>.{3,}?)[ ]*\n+[ ]*(?:{$this->opt($this->t('Reservations'))}\s+|[Tt]:\s*)?(?<phone>{$this->patterns['phone']})\s*(?:[·\|]|[Ee]:)\s*{$this->opt($emailAddreses)}/u", $contactsText, $m)) {
                    $h->hotel()->address($m['address'])->phone($m['phone']);
                }
            }
        }

        // phone
        // fax
        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Reservation Phone'))}] ]/*[normalize-space()][2]", $root, true, "/^({$this->patterns['phone']})(?:\s*\([^)(\d]|$)/") // it-69624358.eml, it-133994988.eml
                ?? $this->http->FindSingleNode("descendant::a[{$this->starts($this->t('Tel:'))}]", $root, true, "/^{$this->opt($this->t('Tel:'))}\s*({$this->patterns['phone']})$/")
        ;
        }

        if ($phone) {
            $h->hotel()->phone($phone);
        } elseif (!empty($h->getAddress())) {
            $contactsText = $this->htmlToText($this->http->FindHTMLByXpath('descendant::text()[' . $this->starts($this->t('contactFieldNames')) . '][ preceding::text()[normalize-space()][1][' . $this->contains($h->getAddress()) . '] ]/ancestor::*[1]', null, $root));

            $phone = preg_match('/' . $this->opt($this->t('Tel:')) . '\s*(' . $this->patterns['phone'] . ')/', $contactsText, $m) ? $m[1] : null;

            if ($phone) {
                $h->hotel()->phone($phone);
            }

            $fax = preg_match('/' . $this->opt($this->t('Fax:')) . '\s*(' . $this->patterns['phone'] . ')/', $contactsText, $m) ? $m[1] : null;

            if ($fax) {
                $h->hotel()->fax($fax);
            }
        }

        if (!$h->getHotelName()) {
            // it-42845485.eml
            $urls = $this->http->FindNodes("descendant::a[contains(@href,'mailto:')][./following-sibling::a]", $root, "/@(.+)/");

            foreach ($urls as $url) {
                if ($this->http->XPath->query("descendant::a[contains(@href,'mailto:') and contains(@href,'{$url}')]/following-sibling::a[contains(@href,'{$url}')]", $root)->length === 1) {
                    $rightUrls[] = $url;
                }
            }

            if (isset($rightUrls) && count($rightUrls) === 1) {
                $url = array_shift($rightUrls);
                $node = implode("\n", $this->http->FindNodes("descendant::a[contains(@href,'mailto:') and contains(@href,'{$url}')]/following-sibling::a[contains(@href,'{$url}')]/ancestor::td[1]/descendant::text()[normalize-space()]", $root));

                if (preg_match("#^(.+?)\s*\-\s*([^\-]+\s*\-\s*\d{5,}.+?\-.+)\n([\d\-\(\) \+]+)\s+.+@{$url}#", $node, $m)) {
                    $h->hotel()
                        ->name($m[1])
                        ->address($m[2])
                        ->phone(trim($m[3], "- "));
                }
            }
        }

        // it-69624358.eml
        if (empty($h->getAddress())) {
            $hotelName = str_replace("The ", "", $h->getHotelName());
            $address = $this->http->FindSingleNode("descendant::a[normalize-space()='Unsubscribe Now']/following::text()[contains(normalize-space(),'{$hotelName}')]", $root, true, "/\s*\.\s*(.+)$/");

            if (!empty($address)) {
                $h->hotel()
                    ->address($address);
            }
        }

        if (empty($h->getHotelName()) || empty($h->getAddress())) {
            $hotelName = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Thank you for choosing')]", $root, true, "/{$this->opt($this->t('Thank you for choosing'))}\s*(.{2,}?)\s*{$this->opt($this->t('for your upcoming vacation'))}/");

            if (!empty($hotelName)) {
                $hotelInfo = implode(" ", $this->http->FindNodes("descendant::text()[{$this->eq($this->t($hotelName))}]/ancestor::p[1]/descendant::text()[normalize-space()]", $root));

                if (preg_match("/{$this->opt($hotelName)}\s*(?<address>.{3,}?)\s+www\..+\.com\s*(?<phone>{$this->patterns['phone']})/", $hotelInfo, $m)) {
                    $h->hotel()->name($hotelName)->address($m['address'])->phone($m['phone']);
                }
            }
        }

        if (empty($h->getAddress()) && !empty($h->getHotelName())) {
            $address = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Welcome to')]", $root, true, "/{$this->opt($this->t('Welcome to'))}.+{$this->opt($this->t(' at '))}(.+)\.\s+{$this->opt($this->t('It is our pleasure'))}/mu");

            if (!empty($address)) {
                $h->hotel()
                    ->address($address);
            }
        }

        // Cancellation

        $cancellation = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Free cancellation'))} or normalize-space()='Cancellation policy']/ancestor::td[1]", $root)
            ?? $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Cancellation Policy:'))} or normalize-space()='Policies']" . $xpathFragmentNextCell . "/descendant::text()[normalize-space()][1]", $root)
        ;

        if ($cancellation) {
            $cancellation = str_replace('Your travel plans are changing ?', '', $cancellation);
        }

        if (strcasecmp($cancellation, 'Cancellation policy') === 0) {
            $cancellation = '';
        }

        if (!$cancellation) {
            $xpathClc = "[normalize-space()='Cancellation/Deposit Policy' or normalize-space()='Cancellation Detail' or normalize-space()='Cancellation policy' or normalize-space()='Cancellation:']/following::text()[normalize-space()][1]/ancestor::td[1]";
            $cancellation = $this->http->FindSingleNode("descendant::text()" . $xpathClc, $root)
                ?? $this->http->FindSingleNode("following::text()[normalize-space()][position()<10]" . $xpathClc, $root) // it-390729503.eml
            ;
        }

        if (!$cancellation) {
            $cancellation = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(),'if the reservation is cancelled more than')][1]", $root)
                ?? $this->http->FindSingleNode("descendant::text()[contains(normalize-space(),'Any cancellation or amendments made within')][1]", $root)
                ?? $this->http->FindSingleNode("descendant::text()[contains(normalize-space(),'Cancellations and changes must be received')][1]", $root, true, '/.{3,}[.!]/')
                ?? $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Cancellation Procedure:')]", $root, true, '/Cancellation Procedure:\s*(.+[.!])$/') // it-133994988.eml
                ?? $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Cancellation Policy:')]/following::text()[normalize-space()][1]", $root)
                ?? $this->http->FindSingleNode("descendant::text()[normalize-space()='Stornierung']" . $xpathFragmentNextCell, $root)
                ?? $this->http->FindSingleNode("descendant::text()[normalize-space()='Cancellations']" . $xpathFragmentNextCell, $root)
                ?? $this->http->FindSingleNode("descendant::*[ not(.//tr) and not(.//div) and descendant::text()[normalize-space()][1][normalize-space()='Cancellation'] ][1]", $root, true, "/^Cancellation\s*(.+)$/i")
                ?? $this->http->FindSingleNode("descendant::text()[normalize-space()='Cancellation Policy']/following::text()[normalize-space()][1]", $root)
            ;
        }

        if (!$cancellation) { // always last!
            // it-18100354.eml
            $cancellationTexts = $this->http->FindNodes("descendant::text()[ preceding::text()[{$this->eq($this->t('Rate Details:'))}] and following::text()[{$this->contains($this->t('contactFieldNames'))}] ][normalize-space()]", $root);
            $cancellationText = implode('. ', $cancellationTexts);

            if ($cancellationText && stripos($cancellationText, 'non-refundable') === false) {
                $cancellationTextParts = preg_split('/[.]+\s*\b/', $cancellationText);
                $cancellationTextParts = array_filter($cancellationTextParts, function ($item) {
                    return stripos($item, 'cancel') !== false;
                });
                $cancellation = implode('. ', $cancellationTextParts);
            }
        }

        if ($cancellation) {
            $cancellation = str_replace('Cancellation:', '', $cancellation);
            $cancellation = preg_replace("/\s(\d{1,2})\.(\d{2})\s+([ap]m)/i", ' $1:$2 $3', $cancellation);
        }

        $h->general()->cancellation($cancellation, false, true);

        // Deadline

        if ($cancellation) {
            $this->detectDeadLine($h, $cancellation);
        }

        // Price

        $totalPrice = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('totalPrice'))}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()][1]", $root, true, "/^.*\d.*$/")
            ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Rate Details:'))}]" . $xpathFragmentNextCell . "/descendant::text()[starts-with(normalize-space(),'Total:')]", $root, true, '/^Total:[:\s]*(.*\d.*)$/i');

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // CHF 1,350.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->cost(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellation): void
    {
        if (preg_match('/Any (?i)cancell?ation or amendments made within (\d{1,3} days?) of the date of arrival will be subject to a /', $cancellation, $m)
            || preg_match("/This (?i)reservation must be cancell?ed (\d{1,3} days?) prior to arrival to avoid the greater of a 3 night or/", $cancellation, $m) && !preg_match("/All refunds? subject to a \d+% fees?\s*(?:[.!]|$)/", $cancellation)
            || preg_match("/Must (?i)cancell? (\d+ days?) prior to arrival date/", $cancellation, $m)
            || preg_match("/Cancell? (?i)before (\d+ days?) prior to the event to avoid cancell?ation costs/", $cancellation, $m)
        ) {
            $h->booked()->deadlineRelative($m[1]);
        } elseif (preg_match("/Bookings (?i)can be cancell?ed up to\s*(?<hour>{$this->patterns['time']})\s*on the day of arrival\./", $cancellation, $m)
            || preg_match("/cancell?ation policy is (?<hour>\d+\s*hours?)(?: local time)? prior to arrival/", $cancellation, $m)
            || preg_match("/Cancell?ations (?i)must be made by (?<hour>{$this->patterns['time']})(?: local time)? one day before arrival/", $cancellation, $m)
        ) {
            $h->booked()->deadlineRelative('0 days', $m['hour']);
        } elseif (preg_match("/Cancell?ations (?i)received before noon, local time, the day prior to arrival are not charged/", $cancellation, $m)
            || preg_match("/Cancellations must be made 1 day prior to arrival 12 noon local time to avoid/", $cancellation, $m)
        ) {
            $h->booked()->deadlineRelative('1 day', '12:00');
        } elseif (preg_match("/Cancell?ations and changes must be received until (?<hour>{$this->patterns['time']}) (?<prior>\d{1,3} days?) prior to arrival\.\s*After this and in case of no-show .+ total cost of stay will be charged/i", $cancellation, $m)
            || preg_match("/Cancell? (?i)before\s*(?<hour>{$this->patterns['time']})(?: local time)?\s*,\s*(?<prior>\d{1,3} days?) prior to arrival to avoid a charge/", $cancellation, $m)
            || preg_match("/Cancell?ations must be received by\s*(?<hour>{$this->patterns['time']})(?:\s*EST)?\s*(?<prior>\d{1,3} hours?)\s*prior to arrival/i", $cancellation, $m)
            || preg_match("/Free cancell?ation until (?<prior>\d+\s*days?) prior arrival \((?<hour>\d+\s*A?a?P?p?M?m?)(?: local time)?\)/i", $cancellation, $m)
            || preg_match("/Cancell?ations (?i)must be made (?<prior>\d{1,3} days?) prior to arrival by (?<hour>{$this->patterns['time']})(?: local time)? to avoid one night's charge/", $cancellation, $m)
            || preg_match("/Should (?i)you wish to modify or cancell? this booking, please kindly contact us within (?<hour>{$this->patterns['time']}) hours? hotel time no later than (?<prior>\d{1,3} hours?) prior to the arrival date/", $cancellation, $m)
            || preg_match("/Should (?i)you wish to modify or cancell?, kindly inform us by (?<hour>{$this->patterns['time']})(?: local time)?, (?<prior>(?:\d{1,3}|[[:alpha:]]+) days?) prior to arrival to avoid a charge/u", $cancellation, $m)
            || preg_match("/Cancell?ation (?i)policy Please note that cancell?ations or modifications must be arranged by (?<hour>{$this->patterns['time']})(?: \(PST\))? (?<prior>\d{1,3} days?) prior to arrival to avoid/", $cancellation, $m)
            || preg_match("/Cancell?ation (?i)policy Should you wish to modify or cancell?, kindly inform us by (?<hour>{$this->patterns['time']})(?: local time)?, (?<prior>(?:\d{1,3}|[[:alpha:]]+) days?) prior to arrival in order to avoid/u", $cancellation, $m)
            || preg_match("/A (?i)cancell?ation free of charge is possible until (?<hour>{$this->patterns['time']}) (?<prior>(?:\d{1,3}|[[:alpha:]]+) days?) prior to arrival/u", $cancellation, $m)
            || preg_match("/In (?i)the event of cancell?ation or modification, please let us know (?<prior>(?:\d{1,3}|[[:alpha:]]+) days?) prior to arrival by (?<hour>{$this->patterns['time']})(?: local time)?, to avoid one night’s charge on the credit card provided/u", $cancellation, $m)
        ) {
            $m['prior'] = preg_replace(['/\bONE\b/i', '/\bTWO\b/i'], ['1', '2'], $m['prior']);
            $h->booked()->deadlineRelative($m['prior'], $m['hour']);
        } elseif (preg_match("/You (?i)can modify or cancell? your reservation free of charge till [-[:alpha:]]+[,\s]+(?<day>\d{1,2}\s*[[:alpha:]]+\s*\d{4})\s*,\s*(?<hours>{$this->patterns['time']})/u", $cancellation, $m)
        ) {
            $h->booked()->deadline(strtotime($m['hours'], strtotime($m['day'])));
        }

        if (preg_match("/^\d+ % penalty will be charged if the reservation is cancell?ed more than (\d+) days? prior to arrival. \d+ % penalty will be charged if the reservation is cancell?ed less than \\1 days? prior to arrival.?$/i", $cancellation)
            || preg_match("/^First night prepayment at time of booking is non-refundable/i", $cancellation)
            || preg_match("/this reservation cannot be amended or cancell?ed free of charge\s*(?:[.!]|$)/i", $cancellation)
        ) {
            $h->booked()->nonRefundable();
        }
    }

    private function normalizeTime($string): string
    {
        if (preg_match('/^12(?:\s*|\-)noon$/iu', $string)
        || preg_match('/^\s*noon\s*$/i', $string)) {
            return '12:00';
        }

        if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', $string, $m) && (int) $m[2] > 12) {
            $string = $m[1];
        } // 21:51 PM    ->    21:51
        $string = preg_replace('/^(0{1,2}[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', '$1', $string); // 00:25 AM    ->    00:25

        if (preg_match("/^(\d+)$/", $string, $m)) {
            $string = $m[1] . ':00';
        }

        return $string;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
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

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function detectBody(): bool
    {
        foreach ($this->bodyDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//*[contains(normalize-space(),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Confirmation Number:'], $words['Arrival Date:'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Confirmation Number:'])} or {$this->contains($words['Cancellation number'] ?? [])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Arrival Date:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getProvider(): ?string
    {
        if ($this->http->XPath->query('//a[contains(@href,"//www.lhw.com") or contains(@href,"//www.merrionhotel.com") or contains(@href,"sandoz-hotels.serenata-nethotel.com") or contains(@href,"www.excelsiorhotelernst.com") or contains(@href,"www.hotel-negresco-nice.com")]')->length > 0
            || $this->http->XPath->query('//img[contains(@src,"//www.merrionhotel.com") and contains(@src,"/leading_hotels.")]')->length > 0
            || $this->http->XPath->query('//img[contains(@src,"LeadingHotelsOfTheWorld") or contains(@src, "/LHW_Master_logo_")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Thank you very much for making a reservation at The Merrion Hotel") or contains(.,"@merrionhotel.com") or contains(.,"@chediandermatt.com") or contains(.,"THEKNICKERBOCKER.COM") or contains(.,"royal-riviera.com") or contains(.,"wymararesortandvillas.com") or contains(., "thesetaihotel.com") or contains(., "www.okura.nl")]')->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'Stockholm and the Grand Hôtel')]")->length > 0
        ) {
            return 'leadinghotels';
        } elseif ($this->http->XPath->query("//text()[contains(.,'the Old Course Hotel')]")->length > 0) {
            // it-42462885.eml
            return 'preferred';
        } elseif ($this->http->XPath->query("//a[contains(@href,'.dorchestercollection.com/') or contains(@href,'www.dorchestercollection.com')]")->length > 0) {
            // it-143865736.eml
            // it-143865736.eml
            return 'dorchester';
        }

        return null;
    }

    private function normalizeDate($str): string
    {
        $in = [
            "#^\w+\,\s(\d+)\.\s+(\w+)\s+(\d{4})$#", // Montag, 28. Juni 2021
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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

    private function travellerCollection($travellersText, $travellers): array
    {
        $travellersText = str_replace(" & Family", "", $travellersText);

        if (stripos($travellersText, ' and ') !== false) {
            $travellers = array_merge($travellers, explode(' and ', $travellersText));
        } else {
            $travellers = array_merge($travellers, explode('&', $travellersText));
        }

        return $travellers;
    }
}
