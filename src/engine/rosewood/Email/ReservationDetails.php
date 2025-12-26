<?php

namespace AwardWallet\Engine\rosewood\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: move preferred/it-75193773.eml from preferred/HotelReservations to this parser

class ReservationDetails extends \TAccountChecker
{
    public $mailFiles = "rosewood/it-133182016.eml, rosewood/it-26496882.eml, rosewood/it-26516190.eml, rosewood/it-26615946.eml, rosewood/it-613803843.eml, rosewood/it-64852490.eml";

    public $reFrom = ["rosewoodhotels.com"];
    public $reBody = [
        'en' => [
            'Number of Nights:',
            'Room Type:',
            'Your reservation details are noted below',
            'The details of your reservation are noted below',
            'The details of your stay are noted below',
            'We currently have you confirmed for arrival',
            'We are delighted that you are coming back to',
            'Thank you for choosing Rosewood',
            'The details of your reservation cancellation are noted below',
            'The details of your reservation cancelation are noted below',
            'We look forward to welcoming you to our home',
            'as your residence during your upcoming trip to',
            'We are delighted to confirm your reservation at',
        ],
    ];
    public $lang = '';
    public $roomType = '';

    public $reSubject = [
        ': Confirmation #',
        ': Reservation Confirmation ',
        ': Reservation Cancellation',
    ];
    public static $dict = [
        'en' => [
            'Confirmation Number:' => ['Confirmation Number:', 'CONFIRMATION NUMBER:'],
            'Name:'                => ['Name:', 'NAME:'],
            'RESERVATION DETAILS'  => ['RESERVATION DETAILS', 'RESERVATION INFORMATION'],
            'Number of Guests:'    => ['Number of Guests:', 'GUESTS:'],
            'Adults'               => ['Adults', 'Adult'],
            'Child'                => ['Child', 'Children'],
            'T:'                   => ['T:', 'Tel:', 'Phone:'],
            'Nightly Rate:'        => ['Nightly Rate:', 'Nightly Rate including WiFi:', 'NIGHTLY ROOM RATE:'],
            'Room Type:'           => ['Room Type:', 'ROOM TYPE:'],
            'arrivalDate'          => ['Arrival:', 'Arrival', 'Arrival Date:', 'Arrival Date', 'ARRIVAL:'],
            'departureDate'        => ['Departure:', 'Departure', 'Departure Date:', 'Departure Date', 'DEPARTURE:'],
            'Check-In/Out Time:'   => ['Check-In/Out Time:', 'Check–In/Out Time:', 'CHECK IN / OUT TIME:'], //differents '–' & '-'
            'Cancel Policy:'       => ['Cancel Policy:', 'Cancellation Policy:', 'CANCELLATION POLICY:', 'CANCELLATION POLICY'],
            'nonRefundable'        => [
                'This reservation is not cancelable',
            ],
            'hotelName' => [
                'We are delighted that you have chosen',
                'We are delighted that you are coming back to',
                'We are pleased to confirm your reservation at',
                'thank you once again for choosing',
                'Thank you for choosing',
                'Thank you for selecting',
            ],
            'hotelNameRegExp' => [
                '#We are delighted that you have chosen (.+?) for your upcoming visit#',
                '/We are delighted that you are coming back to\s*(.+?)\s*[,.!]/',
                '/We are pleased to confirm your reservation at\s*(.+?)\s*[,.!]/',
                '/thank you once again for choosing\s+(.{3,}?)\./i',
                '/Thank you for selecting\s*(.+?)\s*as your residence during/',
                '/Thank you for choosing\s*(.+?)\s*as your residence during/',
                '/Thank you for choosing\s*(.+?)\s*for your upcoming travel/',
                '/Thank you for choosing\s+(.{3,}?)\./',
                '/Thank you for choosing\s+(.+)\s*as your residence during/iu',
            ],
            'cancellationPhrases' => [
                'The details of your reservation cancellation are noted below',
                'The details of your reservation cancelation are noted below',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $itineraries = $this->http->XPath->query("//*[{$this->eq($this->t('RESERVATION DETAILS'))}]/ancestor-or-self::*[ preceding-sibling::node()[normalize-space()] or following-sibling::node()[normalize-space()] ][1]/ancestor::table[1]");

        foreach ($itineraries as $itRoot) {
            $this->parseEmail($email, $itRoot);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'RosewoodHotels') or contains(@alt,'Rosewood')] | //a[contains(@href,'rosewoodhotelsandresorts') or (contains(@href, '.rosewoodhotels.com'))]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from'], $headers['subject'])) {
            if ($this->detectEmailFromProvider($headers['from']) === true
                || stripos($headers['subject'], 'Rosewood') !== false
            ) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email, \DOMNode $itRoot): void
    {
        $roots = $this->http->XPath->query("descendant::node()[{$this->eq($this->t('RESERVATION DETAILS'))}]/following::table[normalize-space()][1]", $itRoot);

        if ($roots->length !== 1) {
            $this->logger->debug('other format email!');

            return;
        }
        $root = $roots->item(0);

        if ($this->http->XPath->query("descendant::node()[{$this->eq($this->t('RESERVATION DETAILS'))}]/following::table[normalize-space()][1]//text()[normalize-space()]", $itRoot)->length < 5) {
            // it-133182016.eml
            $root = null;
        }
        $h = $email->add()->hotel();

        if ($this->http->XPath->query("descendant::*[{$this->contains($this->t('cancellationPhrases'))}]", $itRoot)->length > 0) {
            $h->general()->cancelled();
        }

        $conf = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Confirmation Number:'))}]/following::text()[normalize-space()!=''][1]", $root);

        if (false !== strpos($conf, ' and ')) {
            $conf = explode(' and ', $conf);
        }

        if (is_array($conf)) {
            foreach ($conf as $c) {
                $h->addConfirmationNumber($c);
            }
        } elseif (!empty($conf)) {
            $h->general()
                ->confirmation($conf);
        }

        $cancellationNumber = $this->http->FindSingleNode("//text()[normalize-space()='CANCELLATION NUMBER:']/following::text()[normalize-space()][1]");

        if (!empty($cancellationNumber)) {
            $h->general()
                ->cancelled()
                ->cancellationNumber($cancellationNumber);
        }

        // Mr. & Mrs. Duber
        $patterns['travellerName'] = '[[:alpha:]][-.&\'[:alpha:] ]*[[:alpha:]]';

        $guestName = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Name:'))}]/following::text()[normalize-space()][1]", $root, true, "/^{$patterns['travellerName']}$/u");

        if (!$guestName) {
            $guestName = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Dear'))}]", $itRoot, true, "/{$this->opt($this->t('Dear'))}\s+({$patterns['travellerName']})(?:\s*[,:;!?]|$)/u");
        }
        $travellers = array_filter(preg_split('/\s+(?:and|&)\s+/i', $guestName), function ($item) {
            return !preg_match('/^.+\.$/', $item);
        });
        $h->general()->travellers(preg_replace("/^(Mrs\.|Mr\.|Ms\.)/", "", $travellers));

        $h->booked()
            ->guests($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Number of Guests:'))}]/following::text()[normalize-space()!=''][1]/ancestor-or-self::node()[not({$this->contains($this->t('Number of Guests:'))})][last()]",
                $root, false, "#(\d+)\s+{$this->opt($this->t('Adults'))}#"), false, true)
            ->kids($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Number of Guests:'))}]/following::text()[normalize-space()!=''][1]/ancestor-or-self::node()[not({$this->contains($this->t('Number of Guests:'))})][last()]",
                $root, false, "#(\d+)\s+{$this->opt($this->t('Child'))}#i"), false, true);

        $hotelName = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Confirmation Number:'))}]/following::text()[normalize-space()][2]", $root); // it-26496882.eml, it-26516190.eml

        if (preg_match('/^.{2,}:$/', $hotelName) || preg_match('/[[:lower:]]/u', $hotelName)) {
            $hotelName = null;
        }

        if (empty($hotelName)) {
            $xpathWelcome = "descendant::text()[{$this->contains($this->t('hotelName'))}][not({$this->contains(['Thank you for choosing to stay with us for your'])})]/ancestor::*[2]";

            if ($this->http->XPath->query($xpathWelcome, $itRoot)->length === 0
                && $this->http->XPath->query($xpathWelcome)->length === 1
            ) {
                $itRoot = null;
            }

            $hotelNameStr = $this->http->FindSingleNode($xpathWelcome, $itRoot); // it-26615946.eml

            $regExps = (array) $this->t('hotelNameRegExp');

            foreach ($regExps as $regExp) {
                if (preg_match($regExp, $hotelNameStr, $m)) {
                    $hotelName = $m[1];

                    break;
                }
            }
        }

        if (empty($hotelName)) {
            // it-64852490.eml
            $xpathImg = "(descendant::tr[following::node()[{$this->eq($this->t('RESERVATION DETAILS'))}] and normalize-space()='']/descendant::a[normalize-space(@href)]/descendant::img[{$this->contains('Rosewood', '@alt')}])[last()]/@alt";

            if ($this->http->XPath->query($xpathImg, $itRoot)->length === 0
                && $this->http->XPath->query($xpathImg)->length === 1
            ) {
                $itRoot = null;
            }

            $hotelName = $this->http->FindSingleNode($xpathImg, $itRoot, true, "/^(?:(?:image|img)[-_\s]+)?(.{2,}?)(?:[-_\s]+(?:image|img))?$/i");
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[normalize-space()='View Map']/preceding::text()[normalize-space()][2]");
        }

        $textInfo = implode("\n",
            $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('Confirmation Number:'))}]/ancestor::td[1][{$this->contains($this->t('Directions'))}]//text()[normalize-space()!='']",
                $root));

        $hotelInfo = !empty($hotelName) && !empty($textInfo) ? $this->re("/{$this->opt($hotelName)}\s*(.+)/s",
            preg_replace("/{$this->opt($this->t('Email'))}.+/s", '', $textInfo)) : null;

        if (empty($hotelInfo)) {
            $hotelInfo = $this->http->FindSingleNode("descendant::img[@alt='Twitter' or @alt='twitter' or @alt='TWITTER']/ancestor::td[1]/following-sibling::td[normalize-space()][last()]", $itRoot);
        }

        if (empty($hotelInfo)) {
            // it-133182016.eml
            $xpathHotelInfo = "descendant::img[@alt='Instagram' or @alt='instagram' or @alt='INSTAGRAM' or @alt='Image removed by sender. instagram']/ancestor::*[self::td or self::th][count(.//*[self::td or self::th][normalize-space()])<5]/preceding-sibling::*[self::td or self::th][normalize-space()]";
            $xpathHotelInfoCells = "/descendant::*[self::td or self::th][not(.//td) and not(.//th)][normalize-space()]";

            if ($this->http->XPath->query($xpathHotelInfo, $itRoot)->length === 0
                && $this->http->XPath->query($xpathHotelInfo)->length === 1
            ) {
                $itRoot = null;
            }

            $hotelInfo = implode("\n", $this->http->FindNodes($xpathHotelInfo . $xpathHotelInfoCells, $itRoot));
        }

        if (empty($hotelInfo)) {
            $hotelInfo = implode("\n", $this->http->FindNodes("descendant::img[@alt='Instagram' or @alt='instagram' or @alt='INSTAGRAM']/ancestor::*[self::td or self::th][count(.//*[self::td or self::th][normalize-space()])<5]/preceding-sibling::*[self::td or self::th][normalize-space()]/descendant::div[not(.//div)][normalize-space()]", $itRoot));
        }

        if (empty($hotelInfo)) {
            $hotelInfo = implode("\n", $this->http->FindNodes("//text()[normalize-space()='View Map']/ancestor::table[1]/descendant::text()[normalize-space()]", $itRoot));
        }

        if (preg_match("#(.+)\s*{$this->opt($this->t('T:'))}\s+([^\n]+)#si", $hotelInfo, $m)
            || (!empty($hotelName) && preg_match("#{$this->opt($hotelName)}[®]*\s*\n\s*(.+?)\s+View Map\s+([\(\)\+ \- \d]+\d[\(\)\+ \- \d]+)(?:\n|$)#iu", $hotelInfo, $m))
        ) {
            $h->hotel()
                ->name($hotelName)
                ->address($this->nice($m[1]))
                ->phone($m[2]);
        }

        $roomType = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Room Type:'))}]/following::text()[normalize-space()][1]/ancestor::td[1]", $root);

        if (strlen($roomType) > 50) {
            $roomType = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Room Type:'))}]/following::text()[normalize-space()][1]", $root);
        }

        $roomRate = null;

        $rateInfo = $this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Nightly Rate:'))}]/following::table[1][./descendant::tr[1][count(.//td)=2]]//td[2]",
            $root, "#^\s*\S\s*([\d\.\,]+)$#");

        if (count($rateInfo) > 0) {
            $rateInfoCur = $this->http->FindSingleNode("(./descendant::text()[{$this->starts($this->t('Nightly Rate:'))}]/following::table[1][./descendant::tr[1][count(.//td)=2]]//td[2])[1]",
                $root, false, "#^\s*(\S)\s*[\d\.\,]+$#");
        }

        if (count($rateInfo) == 0) {
            $rateInfo = $this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Nightly Rate:'))}]/following::table[1][./descendant::tr[1][count(.//td)=3]]//td[3]",
                $root, "#^\s*([\d\.\,]+)\s*$#");
            $rateInfoCur = $this->http->FindSingleNode("(./descendant::text()[{$this->starts($this->t('Nightly Rate:'))}]/following::table[1][./descendant::tr[1][count(.//td)=3]]//td[2])[1]",
                $root, "#^\s*(\S)\s*$#");
        }

        foreach ($rateInfo as $i => $ri) {
            $rateInfo[$i] = $rateInfoCur . ' ' . $ri;
        }

        if (count($rateInfo) == 0) {
            $pattern = "/(?:\D|\b)((?<nights>\d{1,2})\s+[[:alpha:]]+\s+(?<charge>.*\d.*?))\s*$/u";
            $rateInfoStr = array_filter($this->http->FindNodes("descendant::text()[{$this->eq($this->t('Nightly Rate:'))}]/following::text()[normalize-space()][1]/ancestor::ul[1]/li/descendant::text()[normalize-space()][last()]", $root, '/^\s*(.*\d.*?)\s*$/'));

            if (count($rateInfoStr) === 0) {
                $rateInfoStr = array_filter($this->http->FindNodes("descendant::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Nightly Rate:'))}] ]/*[normalize-space()][2]/descendant::tr[not(.//tr) and normalize-space()]", $root, $pattern));
            }

            $rateInfo = [];

            foreach ($rateInfoStr as $ris) {
                if (preg_match($pattern, $ris, $m)) {
                    $rateInfo = array_merge($rateInfo, array_fill(0, $m['nights'], $m['charge']));
                }
            }
        }

        $freeNight = 0;

        foreach ($rateInfo as $ri) {
            $v = PriceHelper::cost($this->re("/^\D*(\d[\d ,. ]+)\D*$/", $ri));

            if ($v === 0.0) {
                $freeNight++;
            }
        }

        if ($freeNight > 0) {
            $h->booked()->freeNights($freeNight);
        }

        if ($roomType || $roomRate !== null) {
            if (preg_match("/suite/", $roomType) !== false) {
                $this->roomType = 'suite';
            } elseif (preg_match("/villa/", $roomType) !== false) {
                $this->roomType = 'villa';
            }
            $room = $h->addRoom();
            $room->setType($roomType, false, true)
                ->setRates($rateInfo, false, true);
        }

        $checkIn = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('arrivalDate'))}]/following::text()[string-length(normalize-space())>1][1]", $root, true, '/^[:\s]*(.{6,})$/u');

        if (preg_match("/\s\d{3}$/", $checkIn) || preg_match("/^[a-z]+$/", $checkIn)) {
            $checkIn = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('arrivalDate'))}]/following::text()[string-length(normalize-space())>1][1]/ancestor::tr[1]", $root, true, '/^[:\s]*(.{6,})$/u');
        }

        if (preg_match("/\s\d{3}$/", $checkIn) || preg_match("/^[A-z]+$/", $checkIn)) {
            $checkIn = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('arrivalDate'))}]/following::text()[string-length(normalize-space())>1][1]/ancestor::td[1]", $root, true, '/^[:\s]*(.{6,})$/u');
        }

        if (preg_match("/^(.+)\s*\–\s*Early.+/us", $checkIn, $m)) {
            $checkIn = $m[1];
        }

        $checkOut = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('departureDate'))}]/following::text()[string-length(normalize-space())>1][1]/ancestor::tr[1]", $root, true, '/^[:\s]*(.{6,})$/');

        if (empty($checkOut) || strlen($checkOut) > 50) {
            $checkOut = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('departureDate'))}]/following::text()[string-length(normalize-space())>1][1]", $root, true, '/^[:\s]*(.{6,})$/');
        }

        if (empty($checkOut)) {
            $checkOut = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('departureDate'))}]/following::text()[string-length(normalize-space())>1][1]/ancestor::tr[1]", $root, true, '/^[:\s]*(.{6,})$/u');
        }

        if (preg_match("/\s\d{3}$/", $checkOut) || preg_match("/^[A-z]+$/", $checkOut)) {
            $checkOut = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('departureDate'))}]/following::text()[string-length(normalize-space())>1][1]/ancestor::td[1]", $root, true, '/^[:\s]*(.{6,})$/');
        }

        $h->booked()
            ->checkIn(strtotime($checkIn))
            ->checkOut(strtotime($checkOut));

        $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Check-In/Out Time:'))}]/following::text()[normalize-space()!=''][1]",
            $root);

        if ($this->roomType === 'suite') {
            if (preg_match("/^(\d+a?p?m)\s*\/\s*(\d+a?p?m) for suites/iu", $node, $m)
            || preg_match("/^\s*(\d+\s*A?P?M)\s*\/\s*(\d+\s*A?P?M)/u", $node, $m)) {
                $h->booked()
                    ->checkIn(strtotime($m[1], $h->getCheckInDate()))
                    ->checkOut(strtotime($m[2], $h->getCheckOutDate()));
            }
        } elseif ($this->roomType === 'villa') {
            if (preg_match("/(\d+a?p?m)\s*\/\s*(\d+a?p?m) for villa/iu", $node, $m)) {
                $h->booked()
                    ->checkIn(strtotime($m[1], $h->getCheckInDate()))
                    ->checkOut(strtotime($m[2], $h->getCheckOutDate()));
            }
        } else {
            if (preg_match("#.*?(\d.+)(?:noon)?\s*\/\s*(.+?)\s*(?:noon)?\s*$#", $node, $m)) {
                $h->booked()
                    ->checkIn(strtotime($m[1], $h->getCheckInDate()))
                    ->checkOut(strtotime($m[2], $h->getCheckOutDate()));
            }
        }

        // Price
        $total = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('TOTAL ROOM RATE:'))}]/following::text()[string-length(normalize-space())>1][1]/ancestor::tr[1]", $root);

        if (preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*\D*(?<amount>\d[\d., ]*?)\s*$/", $total, $matches)
            || preg_match("/^\s*(?<amount>\d[\d., ]*?)\s*(?<currency>[^\d\s]{1,5})\s*$/", $total, $matches)
        ) {
            // $8,505.00
            if (isset($rateInfo[0]) && preg_match("/ ([A-Z]{3})\s*$/", $rateInfo[0], $mat)) {
                $currency = $mat[1];
            } else {
                $currency = $this->currency($matches['currency']) ?? $matches['currency'];
            }
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $h->price()->currency($currency)->cost(PriceHelper::parse($this->normalizeAmount($matches['amount'], $currency), $currencyCode));
        }

        // cancellation
        $cancellation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Cancel Policy:'))}]/following::text()[normalize-space()][1]", $root)
            ?? $this->http->FindSingleNode("following::table[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Cancel Policy:'))}]/following::text()[normalize-space()][1]", $root)
        ;

        if (!empty($cancellation)) {
            $h->general()->cancellation($cancellation);
        }

        // nonRefundable
        // deadline
        if (!empty($cancellation)) {
            if (preg_match("/{$this->opt($this->t('nonRefundable'))}/i", $cancellation)) {
                $h->booked()->nonRefundable();
            } else {
                $this->detectDeadLine($h, $cancellation);
            }
        }
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText): void
    {
        $patterns = [
            'prior' => '\s*(?<prior>\w{1,5}\s*(?:days?|hours?))\s*', // one day    |    72 hours
            'hour'  => '\s*(?<hour>\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?)(?:\s+local(?: hotel)? time)?\s*',
        ];

        $dayWords = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'];

        if (
            preg_match("/Reservations (?i)must(?: be)? cancell?(?:ed)?(?: by)?{$patterns['prior']}prior to arrival to avoid penalty/", $cancellationText, $m) // en
            || preg_match("/Reservations (?i)must(?: be)? cancell?(?:ed)?(?: by)?{$patterns['hour']}{$patterns['prior']}prior to arrival or pay one night's stay/", $cancellationText, $m) // en
            || preg_match("/Reservations (?i)must(?: be)? cancell?(?:ed)?(?: by)?{$patterns['hour']}{$patterns['prior']}prior to arrival to avoid penalty of 1 night/", $cancellationText, $m) // en
            || preg_match("/Reservations must be cancelled by {$patterns['hour']}(?:\s*local hotel time\,\s*)?{$patterns['prior']}prior to arrival/", $cancellationText, $m) // en
            || preg_match("/Reservation must be cancelled {$patterns['prior']} prior to arrival/", $cancellationText, $m) // en
            || preg_match("/Cancellations made later than {$patterns['prior']} prior to arrival/", $cancellationText, $m) // en
            || preg_match("/Reservations (?i)must(?: be)? cancell?(?:ed)?(?: by)?{$patterns['hour']}{$patterns['prior']}before arrival to avoid penalty of 1 night room and tax\./", $cancellationText, $m) // en
            || preg_match("/Cancell?{$patterns['prior']}prior to arrival to avoid penalty of/i", $cancellationText, $m) // en
            || preg_match("/Cancellations made within {$patterns['prior']} prior to arrival will be charged for the full amount of the stay and tax./i", $cancellationText, $m) // en
            || preg_match("/Must cancell?{$patterns['prior']}prior to arrival to avoid forfeiture of total deposit/i", $cancellationText, $m) // en
            || preg_match("/Must cancell? by{$patterns['hour']},{$patterns['prior']}prior to arrival to avoid penalty/i", $cancellationText, $m) // en
            || preg_match("/Must be cancel by {$patterns['hour']} local hotel time {$patterns['prior']} prior to arrival penalty/i", $cancellationText, $m) // en
            || preg_match("/Reservations cancelled or modified after {$patterns['hour']} local time, {$patterns['prior']} prior to arrival date/i", $cancellationText, $m) // en
        ) {
            $m['prior'] = str_ireplace($dayWords, array_keys($dayWords), $m['prior']);
            $hour = empty($m['hour']) ? '00:00' : $m['hour'];
            $h->booked()->deadlineRelative($m['prior'], $hour);
        } elseif (
            preg_match("/Reservations must be cancell?ed by{$patterns['hour']},{$patterns['prior']}prior to arrival to avoid penalty of 1 nights room charges/i", $cancellationText, $m) // en
        ) {
            $h->booked()->deadlineRelative($m['prior'] + 1 . ' days', $m['hour']);
        } elseif (!empty($h->getCheckInDate())
            && stripos($cancellationText, 'Cancellation charge of 1 night’s room and tax applies if the hotel is not notified on or prior to the arrival date') !== false
        ) {
            $h->booked()->deadline($h->getCheckInDate());
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                foreach ($reBody as $re) {
                    if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$re}')]")->length > 0) {
                        $this->lang = substr($lang, 0, 2);

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }

    private function currency($s): ?string
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'  => 'EUR',
            'R$' => 'BRL',
            '£'  => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    /**
     * @param string|null $s Unformatted string with amount
     * @param string|null $c String with currency
     */
    private function normalizeAmount(?string $s, ?string $c = null): ?string
    {
        if (in_array($c, ['€', 'EUR'])) {
            $s = preg_replace('/[^.\d]/', '', $s); // 11,565.0000  ->  11565.0000
            $s = (string) round($s, 2); // 11565.0000  ->  11565.00
        }

        return $s;
    }
}
