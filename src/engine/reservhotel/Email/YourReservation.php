<?php

namespace AwardWallet\Engine\reservhotel\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Common\Parser\Util\PriceHelper;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "reservhotel/it-779247701.eml, reservhotel/it-656746689-es.eml, reservhotel/it-759707368-cancelled.eml, reservhotel/it-765577613-junk.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'confNumber' => ['Confirmación'],
            'checkInOut' => ['Llegada/Salida'],
            'Cancellation Policy' => 'Politicas De Cancelación',
            // 'cancelledPhrases' => '',
            // 'statusPhrases' => [''],
            // 'statusVariants' => [''],

            // 'Airline' => '',
            // 'From' => '',
            // 'cabinValues' => [''],

            'Grand Total' => 'Gran Total',
            'Amount' => 'Cantidad',
            'Description' => 'Descripción',
            'descriptionHotel' => 'Estancia del hotel, impuestos del hotel y extras',
            // 'descriptionFlight' => '',
        ],
        'en' => [
            'confNumber' => ['Confirmation'],
            'checkInOut' => ['Check in/Check Out'],
            // 'Cancellation Policy' => '',
            'cancelledPhrases' => ['Your reservation is cancelled.', 'Your reservation is canceled.'],
            'statusPhrases' => ['Your reservation is'],
            'statusVariants' => ['cancelled', 'canceled'],

            // 'Airline' => '',
            // 'From' => '',
            'cabinValues' => ['Economy'],

            // 'Grand Total' => '',
            // 'Amount' => '',
            // 'Description' => '',
            'descriptionHotel' => 'Hotel Stay, Hotel Taxes, and Extras',
            'descriptionFlight' => 'Airline Charges, Taxes and Security Fees',
        ]
    ];

    private $isJunk = false;
    private $travellers = [];

    private $xpath = [
        'bold' => '(self::b or self::strong or contains(translate(@style," ",""),"font-weight:bold"))',
    ];

    private $patterns = [
        'date' => '\b\d{1,2}\/\d{1,2}\/\d{2}\b', // 10/18/24
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    private function parseHotel(Email $email, array $json = [], array $charges): void
    {
        $this->logger->debug(__FUNCTION__);
        $h = $email->add()->hotel();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            $h->general()->cancelled();
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]/following::text()[normalize-space()][1][not({$this->eq($this->t('From'), "translate(.,':','')")})]", null, true, '/^[-A-Z\d]{4,40}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")} and not(following::text()[normalize-space()][1][{$this->eq($this->t('From'), "translate(.,':','')")}])]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $xpathLeftCol = "text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")} and not(following::text()[normalize-space()][1][{$this->eq($this->t('From'), "translate(.,':','')")}])]/ancestor::*[ descendant::text()[{$this->eq($this->t('checkInOut'), "translate(.,':','')")}] ][1]";

        $roomType = $this->http->FindSingleNode("//{$xpathLeftCol}/descendant::h3[normalize-space()][2][not(preceding::text()[{$this->eq($this->t('checkInOut'), "translate(.,':','')")}])]");

        $room = $h->addRoom();
        $room->setType($roomType);

        $leftColText = $this->htmlToText( $this->http->FindHTMLByXpath("//{$xpathLeftCol}") );

        $travellersText = !empty($roomType) && preg_match("/{$this->opt($roomType)}\s*([\s\S]+?)\n\n/", $leftColText, $m) ? $m[1] : '';
        $travellersRows = preg_split("/([ ]*\n[ ]*)+/", $travellersText);
        
        foreach ($travellersRows as $tRow) {
            if (preg_match("/^({$this->patterns['travellerName']})(?:\s*\(|$)/u", $tRow, $m)) {
                if (!in_array($m[1], $this->travellers)) {
                    $this->travellers[] = $m[1];
                }
            } else {
                $this->travellers = [];

                break;
            }
        }

        if (count($this->travellers) > 0) {
            $h->general()->travellers($this->travellers, true);
        }

        $xpathDates = "//text()[{$this->eq($this->t('checkInOut'), "translate(.,':','')")}]/following::text()[normalize-space()][1]/ancestor::tr[ *[2] ][1]";
        $checkInVal = $this->http->FindSingleNode($xpathDates . "/*[1]", null, true, "/^{$this->patterns['date']}$/");
        $checkOutVal = $this->http->FindSingleNode($xpathDates . "/*[2]", null, true, "/^{$this->patterns['date']}$/");

        if (array_key_exists('checkinDate', $json)) {
            $checkInVal = $json['checkinDate'];
        }

        if (array_key_exists('checkoutDate', $json)) {
            $checkOutVal = $json['checkoutDate'];
        }

        $h->booked()->checkIn(strtotime($checkInVal))->checkOut(strtotime($checkOutVal));

        $cancellationText = implode("\n", $this->http->FindNodes("//h2[{$this->eq($this->t('Cancellation Policy'), "translate(.,':','')")}]/following-sibling::p[normalize-space()]"));

        if (preg_match("/Cancell?ations (?i)received before\s+(?<date>.{4,20}\b\d{4})\s+have no penalty(?:\s*[.!]|$)/", $cancellationText, $m) // en
        ) {
            $date = strtotime($m['date']);
            $h->booked()->deadline(strtotime('-1 minutes', $date));
        } elseif (preg_match("/Cancell? (?i)for free up to\s+(?<prior>\d{1,2} days?)\s+prior to arrival(?:\s*[.!]|$)/", $cancellationText, $m) // en
            || preg_match("/If (?i)notice of cancell?ation or short stays is received more than\s+(?<prior>\d{1,2} days?)\s+before arrival\/check-in date, no penalties apply(?:\s*[.!]|$)/", $cancellationText, $m) // en
        ) {
            $h->booked()->deadlineRelative($m['prior'], '00:00');
        }

        $hotelName = $address = $phone = null;

        $hotelContactsText = $this->htmlToText( $this->http->FindHTMLByXpath("descendant::*[ descendant::text()[normalize-space()][2][not(contains(.,':'))] and descendant::text()[normalize-space()][1]/ancestor::*[{$this->xpath['bold']}] ][last()]") );

        if (preg_match("/^(?<name>.{2,}?)[ ]*\n+[ ]*(?<address>.{3,}?)(?:[ ]*\n+[ ]*(?:http|www\.)|$)/i", $hotelContactsText, $m)) {
            /*
                HYATT ZIVA CAP CANA TA
                Playa juanillo, bv. zona hotel, Punta cana, Puj, DO
                https://agentcashplus.com
            */
            $hotelName = $m['name'];
            $address = $m['address'];
        }

        if (array_key_exists('reservationFor', $json) && array_key_exists('name', $json['reservationFor'])
            && !empty($json['reservationFor']['name'])
        ) {
            $hotelName = $json['reservationFor']['name'];
        }

        if (array_key_exists('reservationFor', $json) && array_key_exists('address', $json['reservationFor'])
            && is_array($json['reservationFor']['address'])
        ) {
            $addressParts = [];

            if (array_key_exists('streetAddress', $json['reservationFor']['address'])
                && !empty($json['reservationFor']['address']['streetAddress'])
            ) {
                $addressParts[] = $json['reservationFor']['address']['streetAddress'];
            }

            if (array_key_exists('addressRegion', $json['reservationFor']['address'])
                && !empty($json['reservationFor']['address']['addressRegion'])
            ) {
                $addressParts[] = $json['reservationFor']['address']['addressRegion'];
            }

            if (array_key_exists('addressCountry', $json['reservationFor']['address'])
                && !empty($json['reservationFor']['address']['addressCountry'])
            ) {
                $addressParts[] = $json['reservationFor']['address']['addressCountry'];
            }

            $addressFull = implode(', ', $addressParts);

            if (mb_strlen($addressFull) > 2) {
                $address = $addressFull;
            }
        }

        if (array_key_exists('reservationFor', $json) && array_key_exists('telephone', $json['reservationFor'])
            && !empty($json['reservationFor']['telephone'])
        ) {
            $phone = $json['reservationFor']['telephone'];
        }

        $h->hotel()->name($hotelName)->address($address)->phone($phone, false, true);

        if ($this->isJunk && !empty($h->getHotelName()) && !empty($h->getCheckInDate())) {
            $email->clearItineraries();
            $email->setIsJunk(true);
            return;
        }

        $totalPrice = count($charges) === 1 ? $charges[0] : '';

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+?)$/u', $totalPrice, $matches)) {
            // 3,098.88USD
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);
        }
    }

    private function parseFlight(Email $email, array $charges): void
    {
        $segments = $this->http->XPath->query("//tr[ *[normalize-space()][1][{$this->eq($this->t('Airline'), "translate(.,':','')")}] and *[normalize-space()][3][{$this->eq($this->t('From'), "translate(.,':','')")}] ]/following-sibling::tr[ *[4] ]");
        
        if ($segments->length === 0) {
            return;
        }
        
        $this->logger->debug(__FUNCTION__);
        $f = $email->add()->flight();

        if (count($this->travellers) > 0) {
            $f->general()->travellers($this->travellers, true);
        }

        $PNRs = [];

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $airline = $this->http->FindSingleNode("*[2]", $root);

            if ( preg_match("/^(?<number>\d+)\s*(?<name>.{2,}?)(?:\s+-\s+[[:alpha:]]|$)/u", $airline, $m) ) {
                $s->airline()->number($m['number'])->name($m['name']);
            }

            if ( preg_match("/\s-\s+({$this->opt($this->t('cabinValues'))})$/i", $airline, $m) ) {
                $s->extra()->cabin($m[1]);
            }

            $confirmation = $this->http->FindSingleNode("*[3]", $root, true, "/^[A-Z\d]{5,10}$/");

            if ($confirmation) {
                $PNRs[] = $confirmation;
            }

            $fromText = $this->htmlToText( $this->http->FindHTMLByXpath("*[4]", null, $root) );
            $toText = $this->htmlToText( $this->http->FindHTMLByXpath("*[5]", null, $root) );

            $nameDep = preg_match("/^(.{2,}?)[ ]*(?:\n|$)/", $fromText, $m) ? $m[1] : '';
            $nameArr = preg_match("/^(.{2,}?)[ ]*(?:\n|$)/", $toText, $m) ? $m[1] : '';

            if (preg_match($pattern1 = "/^(?<code>[A-Z]{3})\s*-\s*(?<name>.{2,})$/", $nameDep, $m)) {
                $s->departure()->code($m['code']);
                $nameDep = $m['name'];
            }
            $s->departure()->name($nameDep);

            if (preg_match($pattern1, $nameArr, $m)) {
                $s->arrival()->code($m['code']);
                $nameArr = $m['name'];
            }
            $s->arrival()->name($nameArr);

            $patternsDate = "\b\d{1,2}[-\s]+[[:alpha:]]+[-\s]+\d{2}\b"; // 06-Nov-24
            $dateDep = $dateArr = $timeDep = $timeArr = '';

            if (preg_match($pattern2 = "/[ ]*(?<date>.{6,}?)?[ ]*(?<time>{$this->patterns['time']})/", $fromText, $m)) {
                if (!empty($m['date'])) {
                    $dateDep = preg_match("/^{$patternsDate}$/u", $m['date']) ? strtotime($m['date']) : 0;
                }

                $timeDep = $m['time'];
            }

            if (preg_match($pattern2, $toText, $m)) {
                if (!empty($m['date'])) {
                    $dateArr = preg_match("/^{$patternsDate}$/u", $m['date']) ? strtotime($m['date']) : 0;
                } else {
                    $dateArr = $dateDep;
                }

                $timeArr = $m['time'];
            }

            if ($dateDep && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $dateDep));
            }

            if ($dateArr && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $dateArr));
            }
        }

        foreach (array_unique($PNRs) as $pnr) {
            $f->general()->confirmation($pnr);
        }

        $totalPrice = count($charges) === 1 ? $charges[0] : '';

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+?)$/u', $totalPrice, $matches)) {
            // 1,384.48USD
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]reservhotel\.com$/i', $from) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider( rtrim($parser->getHeader('from'), '> ') ) !== true
            && $this->http->XPath->query('//a[contains(@href,".reservhotel.com/") or contains(@href,"www.reservhotel.com")]')->length === 0
            && $this->http->XPath->query('//tr/*[2][contains(.,"@reservhotel.com")]')->length === 0
        ) {
            return false;
        }
        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        if ( empty($this->lang) ) {
            $this->logger->debug("Can't determine a language!");
            return $email;
        }
        $email->setType('YourReservation' . ucfirst($this->lang));

        if (preg_match("/[A-Z\d] (?i)Payment Error$/", $parser->getSubject())
            && $this->http->XPath->query("//h2[normalize-space()='Please Review...' and not(.//h2[normalize-space()])]")->length === 1
        ) {
            $this->isJunk = true;
        }

        $JSONs = $jsonHotels = [];
        $jsonTextsAll = $this->http->FindNodes("//script[normalize-space(@type)='application/ld+json' and normalize-space()]");
        
        foreach ($jsonTextsAll as $jsonText) {
            $json = json_decode($jsonText);

            if (is_array($json)) {
                $JSONs = array_merge($JSONs, $json);
            } else {
                $JSONs[] = $json;
            }
        }
        
        foreach ($JSONs as $json) {
            $jsonArray = json_decode(json_encode($json), true);

            if (array_key_exists('@type', $jsonArray) && $jsonArray['@type'] === 'LodgingReservation') {
                $jsonHotels[] = $jsonArray;
            }
        }
        
        $jsonHotel = count($jsonHotels) === 1 ? $jsonHotels[0] : [];

        $hotelCharges = $flightCharges = [];

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Grand Total'), "translate(.,':','')")}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+?)$/u', $totalPrice, $matches)) {
            // 2,893.10USD
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $email->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);

            $feeRows = $this->http->XPath->query("//tr[ *[3][{$this->eq($this->t('Amount'))}] and *[4][{$this->eq($this->t('Description'))}] ]/following-sibling::tr[ *[4] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[3]', $feeRow, true, '/^(.*?\d.*?)\s*(?:\(|$)/');
                $feeName = $this->http->FindSingleNode('*[4]', $feeRow, true, '/^(.+?)[\s.:：]*$/u');
                $feeName = preg_replace('/^(.{2,}?)[\s.:：]*[*]+[^*]*[*]+$/u', '$1', $feeName ?? '');
                
                if (preg_match("/^{$this->opt($this->t('descriptionHotel'))}$/i", $feeName)) {
                    $hotelCharges[] = $feeCharge;

                    continue;
                } elseif (preg_match("/^{$this->opt($this->t('descriptionFlight'))}$/i", $feeName)) {
                    $flightCharges[] = $feeCharge;

                    continue;
                }

                if ( preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*' . preg_quote($matches['currency'], '/') . '$/u', $feeCharge, $m) ) {
                    $email->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
        }

        $this->parseHotel($email, $jsonHotel, $hotelCharges);

        if ($this->isJunk) {
            return $email;
        }

        $this->parseFlight($email, $flightCharges);

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

    private function assignLang(): bool
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['confNumber']) || empty($phrases['checkInOut']) ) {
                continue;
            }
            if ($this->http->XPath->query("//node()[{$this->eq($phrases['confNumber'], "translate(.,':','')")}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->eq($phrases['checkInOut'], "translate(.,':','')")}]")->length > 0
            ) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
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
}
