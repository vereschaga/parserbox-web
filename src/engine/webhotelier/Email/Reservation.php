<?php

namespace AwardWallet\Engine\webhotelier\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "webhotelier/it-181259034.eml, webhotelier/it-3493974.eml, webhotelier/it-63415721.eml, webhotelier/it-63708890.eml, webhotelier/it-640154020.eml, webhotelier/it-677903467.eml, webhotelier/it-678050080.eml";

    public $lang = '';

    public $detectLang = [
        'en' => ['Stay details'],
        'it' => ['Dettagli del soggiorno'],
        'de' => ['Aufenthaltsdetails'],
    ];

    public static $dictionary = [
        "en" => [
            //            'BOOKING HAS BEEN CANCELLED' => '',
            'Booking summary'          => ['Booking summary', 'Reservation Summary'],
            'Booking confirmation no.' => ['Booking Confirmation No.', 'Booking confirmation no.'],
            'Room'                     => ['Room', 'Villa', 'Suite', 'Suites', 'Rooms', 'House'],
            'Rate plan'                => ['Rate plan', 'Rate Plan', 'Rate category'],
            //            'Check-in' => '',
            //            'Check-out' => '',
            //            'Guests' => '',
            //            'adult' => '',
            //            'child' => '',
            //            'Check-in time:' => '',
            //            'Check-out time:' => '',
            'Booking total' => ['Booking total', 'reservation total'],
            //            'Excluded charges' => '',
            'Cancellation policy' => ['Cancellation policy:', 'Cancellation policy'],
            //            'First name' => '',
            //            'Last name' => '',
            'Accommodation & contact details' => ['Accommodation & contact details', 'Accommodation & Contact Details'],
            'Rate Information'                => ['Rate Information', 'Rate information'],
            //            'FAX:' => '',
        ],
        "it" => [
            //            'BOOKING HAS BEEN CANCELLED' => '',
            //'Booking summary'          => [''],
            'Booking confirmation no.' => ['N° Conferma Prenotazione'],
            'Room'                     => ['Camera'],
            'Rate plan'                => ['Tariffa'],
            'Check-in'                 => 'Arrivo',
            'Check-out'                => 'Partenza',
            'Guests'                   => ['Ospiti', 'Persone'],
            'adult'                    => 'adulti',
            //            'child' => '',
            'Check-in time:'  => 'Orario di arrivo:',
            'Check-out time:' => 'Orario di partenza:',
            'Booking total'   => ['totale prenotazione'],
            //            'Excluded charges' => '',
            'Cancellation policy'             => 'Condizioni di Cancellazione:',
            'First name'                      => 'Nome',
            'Last name'                       => 'Saccani',
            'Accommodation & contact details' => ['Sistemazione & Contatti'],
            'Rate Information'                => ['Rate Information', 'Rate information'],
            'FAX:'                            => 'Fax:',
        ],
        "de" => [
            //            'BOOKING HAS BEEN CANCELLED' => '',
            //'Booking summary'          => [''],
            'Booking confirmation no.' => ['Buchungsbestätigung Nr.'],
            'Room'                     => ['Suite'],
            'Rate plan'                => ['Preis/Ratenkategorie'],
            'Check-in'                 => 'Anreise',
            'Check-out'                => 'Abreise',
            'Guests'                   => ['Gäste'],
            'adult'                    => 'Erwachsene',
            'child'                    => 'Kind',
            'Check-in time:'           => 'Check-In Zeit:',
            'Check-out time:'          => 'Check-Out Zeit:',
            'Booking total'            => ['Gesamt Reservierung'],
            //            'Excluded charges' => '',
            'Cancellation policy'             => 'Stornierungsbedingungen:',
            'First name'                      => 'Vorname',
            'Last name'                       => 'Nachname',
            'Accommodation & contact details' => ['Unterkunft- & Kontaktdetails'],
            'Rate Information'                => ['Rate Information', 'Rate information'],
            'FAX:'                            => 'Fax:',
        ],
    ];

    private $detectFrom = 'info@reserve-online.net';
    private $detectSubject = [
        "en" => [" Booking Confirmation ", " Booking cancellation "],
        "it" => [" Conferma Prenotazione "],
        "de" => ["  Buchungsbestätigung "],
    ];

    private $detectBody = [
        'en'=> [
            'Click on the confirmation number above if you wish to modify or cancel',
            'Click on the reservation number above if you wish to modify or cancel',
            'The Travel Agency is responsible for collecting payment + any commission from the client.',
            'Accommodation & contact details',
        ],
        "it" => [
            'Modifica / Cancella Prenotazione',
        ],
        "de" => [
            'Reservierung ändern / stornieren',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $body = $this->http->Response['body'];

        if (empty($body)) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        $this->isDetectBody();

        // Travel Agency
        $email->obtainTravelAgency();
        $awards = array_sum(array_filter(str_replace(',', '', $this->http->FindNodes("//text()[" . $this->eq("Book again") . "]/following::td[" . $this->starts("+") . " and " . $this->contains("points") . "][1]", null, "#\+\s*([\d,]+)\s*points\b#"))));

        if (!empty($awards)) {
            $email->ota()
                ->earnedAwards($awards . ' points');
        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            foreach ($detectSubject as $dSubject) {
                if (stripos($headers["subject"], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        $body = $this->http->Response['body'];

        if (empty($body)) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        if ($this->http->XPath->query('//a[contains(@href,".reserve-online.net") or contains(@href,".reserve-2Donline.net_")]')->length === 0) {
            return false;
        }

        return $this->isDetectBody();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;
                }

                return true;
            }
        }

        return false;
    }

    private function parseHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking confirmation no.")) . "]/following::text()[normalize-space()][1]", null, true,
                "/^\s*(\d{5,})\s*$/"));

        // HOTEL

        // General
        $h->general()
            ->noConfirmation()
            ->traveller($this->getField($this->t("First name"))
                . " " . $this->getField($this->t("Last name")), true)
        ;

        $nextHeader = $this->http->FindSingleNode("//h4[" . $this->eq($this->t("Cancellation policy")) . "]/following::*[self::h3 or self::h4][1]");

        if (!empty($nextHeader)) {
            $cancellation = $this->http->FindNodes("//h4[" . $this->eq($this->t("Cancellation policy")) . "]/following-sibling::*[following::*[" . $this->eq($this->t($nextHeader)) . "]][normalize-space()]");

            if (count($cancellation) < 20) {
                $h->general()->cancellation(implode(' ', $cancellation));
            }
        }

        if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("BOOKING HAS BEEN CANCELLED")) . "])[1]"))) {
            $h->general()
                ->status('Cancelled')
                ->cancelled()
            ;
        }
        $date = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booked on")) . "][last()]", null, true,
            "/Booked on (.*\b\d{4}\b.*\d{1,2}:\d{2}(\s*[ap]m)?)\b/i");

        if (!empty($date)) {
            $h->general()->date($this->normalizeDate($date));
        }

        // Hotel
        $name = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking summary")) . "]/preceding::text()[normalize-space()][1]");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Booking summary")) . "]", null, true, "/(.+)\s[\-\–]\s" . $this->opt($this->t("Booking summary")) . "$/u");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Booking confirmation no.")) . "]/preceding::text()[normalize-space()][1]/ancestor::h2[count(descendant::text()[normalize-space()]) = 1]");
        }
        $h->hotel()
            ->name($name)
        ;
        $addressInfo = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Accommodation & contact details")) . "]/following::text()[normalize-space()][1]/ancestor::p[1][count(.//text()[normalize-space()]) > 1]//text()[normalize-space()]"));

        if (preg_match("/^(?<address>[\s\S]+?)\n(?:(?<phone>[\+\-\d \(\)]{5,})|\s*" . $this->opt($this->t("FAX:")) . "|\s*\S+@\S+\.\S+)(?:\n|$)/", $addressInfo, $m)) {
            $h->hotel()
                ->address(preg_replace("/\s+/", ' ', trim($m['address'])))
            ;

            if (!empty($m['phone'])) {
                $h->hotel()
                    ->phone(trim($m['phone']))
                ;
            }

            if (preg_match("/\n\s*" . $this->opt($this->t("FAX:")) . "([\+\-\d \(\)]{5,})\n/", $addressInfo, $m1)) {
                $h->hotel()
                    ->fax(trim($m1[1]));
            }
        }

        if (empty($addressInfo)) {
            $addressInfo = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Accommodation & contact details")) . "]/following::text()[normalize-space()][position()<=10]"));

            if (preg_match("/Postal Address:(.+?)Contact phone number:/su", $addressInfo, $m)) {
                $h->hotel()
                    ->address(preg_replace("/\s+/", ' ', trim($m[1])));
            }

            if (preg_match("/Contact phone number:\s*([\+\-\d \(\)]{5,})\s*($|\n)/u", $addressInfo, $m)) {
                $h->hotel()
                    ->phone(trim($m[1]));
            }

            if (preg_match("/\n\s*" . $this->opt($this->t("FAX:")) . "\s*([\+\-\d \(\)]{5,})\n/", $addressInfo, $m1)) {
                $h->hotel()
                    ->fax(trim($m1[1]));
            }
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->getField($this->t("Check-in"))))
            ->checkOut($this->normalizeDate($this->getField($this->t("Check-out"))))
        ;
        $guests = $this->getField($this->t("Guests"), "/(\d+) " . $this->opt($this->t("adult")) . "/i");
        $kids = $this->getField($this->t("Guests"), "/(\d+) " . $this->opt($this->t("child")) . "/i");

        if (empty($guests) && empty($kids)) {
            $guests = array_sum($this->getFields(preg_replace("/(.+)/", '$1 #%', $this->t("Room")), "/(\d+) " . $this->opt($this->t("adult")) . "/i", true));
            $kids = array_sum($this->getFields(preg_replace("/(.+)/", '$1 #%', $this->t("Room")), "/(\d+) " . $this->opt($this->t("child")) . "/i", true));
        }

        if (empty($guests) && empty($kids)) {
            $h->booked()
                ->guests(null);
        } else {
            $h->booked()
                ->guests($guests)
                ->kids($kids, true, true);
        }

        $time = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check-in time:")) . "]/following::text()[normalize-space()][1]", null, true,
            "/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/i");

        if (!empty($time) && !empty($h->getCheckInDate())) {
            $h->booked()->checkIn(strtotime($time, $h->getCheckInDate()));
        }

        $time = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check-out time:")) . "]/following::text()[normalize-space()][1]", null, true,
            "/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/i");

        if (!empty($time) && !empty($h->getCheckOutDate())) {
            $h->booked()->checkOut(strtotime($time, $h->getCheckOutDate()));
        }

        // Rooms
        $rates = [];
        $rows = $this->http->XPath->query("//text()[{$this->eq($this->t('Rate Information'))}]/following::table[1]/descendant::tr[1]/ancestor::*[1]/*");
        $i = 0;

        foreach ($rows as $ri => $row) {
            $td = $this->http->FindNodes("*", $row);

            if (count($td) == 1 && preg_match("/^\s*{$this->opt($this->t('Room'))} #\d\s*$/", $td[0])) {
                if ($ri !== 0) {
                    $i++;
                }
            } elseif (count($td) == 2
                && preg_match("/^\s*[[:alpha:]]+ \d{1,2}\s*$/", $td[0])
                && preg_match("/^\s*\D{0,5}\d[\d,. ]*\D{0,5}\s*$/", $td[1])
            ) {
                $rates[$i][] = $td[1];
            } else {
                $rates = [];

                break;
            }
        }

        $night = (int) $this->getField($this->t("Nights"));

        foreach ($rates as $roomRate) {
            if (count($roomRate) !== $night) {
                $rates = [];

                break;
            }
        }

        $type = $this->getField($this->t("Room"));
        $rate = $this->getField($this->t("Rate plan"));

        if (preg_match('/^\s*(\d+) × (.+)/', $type, $m)) {
            if (count($rates) !== (int) $m[1]) {
                $rates = [];
            }

            for ($i = 0; $i < $m[1]; $i++) {
                $r = $h->addRoom()
                    ->setType($m[2], true, true)
                    ->setRateType($rate);

                if (!empty($rates[$i])) {
                    $r->setRates($rates[$i]);
                }
            }
        } else {
            $r = $h->addRoom()
                ->setType($type, true, true)
                ->setRateType($rate);

            if (!empty($rates[0])) {
                $r->setRates($rates[0]);
            }
        }

        // Total
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking total")) . "]/following::text()[normalize-space()][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $amount = PriceHelper::parse($m['amount'], $m['curr']);
            $excluded = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking total")) . "]/following::text()[" . $this->eq($this->t("Excluded charges")) . "][1]/following::text()[normalize-space()][1]");

            if (empty($excluded)) {
                $excluded = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking total")) . "]/preceding::text()[" . $this->eq($this->t("Excluded charges")) . "][1]/following::text()[normalize-space()][1]");
            }

            if (preg_match("#^\s*" . $m['curr'] . "\s*(?<amount>\d[\d\., ]*)\s*$#", $excluded, $m1)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*" . $m['curr'] . "\s*$#", $excluded, $m1)) {
                $amount += $this->amount($m1['amount']);
            }
            $currency = $this->currency($m['curr']);
            $h->price()
                ->total(PriceHelper::parse($amount, $currency))
                ->currency($currency)
            ;
        }

        $this->detectDeadLine($h);

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        $cancellationText = $h->getCancellation();

        if (empty($cancellationText)) {
            return false;
        }

        if (
            preg_match("/This rate allows booking modifications or cancellation without charges up to (?<date>.*\b\d{4}\b.*) local time\./", $cancellationText, $m) // en
        ) {
            $h->booked()
                ->deadline($this->normalizeDate($m['date']));

            return true;
        }

        if (preg_match("/Free cancellation before (?<time>[\d\:]+) \(local property time\) on (?<date>\d+\s*\w+\s*\d{4})/", $cancellationText, $m)) {
            $h->booked()->deadline($this->normalizeDate($m['date'] . ', ' . $m['time']));
        }

        if (preg_match("/No free cancellation is allowed for this rate, special conditions apply\./", $cancellationText)
            || preg_match("/This is a last minute booking\. Last minute bookings cannot be modified or cancelled without penalty\./", $cancellationText)
            || preg_match("/Cancellazione gratuita non è disponibile/", $cancellationText)
            || preg_match("/Eine kostenlose Stornierung ist nicht möglich/", $cancellationText)
        ) {
            $h->booked()->nonRefundable();
        }

        return false;
    }

    private function isDetectBody()
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug($str);

        $in = [
            "#^\w+\,?\s*(\d+)\.?\s+(\w+)\s*(\d{4})$#u",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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

    private function getField($text, $regexp = null)
    {
        $result = $this->http->FindSingleNode("//text()[" . $this->eq($text) . "]/ancestor::*[self::td or self::th][1][ancestor::*[1][count(*[normalize-space()]) = 2]]/following-sibling::*[1]", null, true, $regexp);

        if (empty($result)) {
            $result = $this->http->FindSingleNode("//text()[" . $this->eq($text) . "][following::text()[{$this->eq($this->t('Rate Information'))}]]/following::text()[normalize-space()][1]", null, true, $regexp);
        }

        return $result;
    }

    private function getFields($text, $regexp = null, $replaceNumbers = false)
    {
        $replace = '';

        if ($replaceNumbers === true) {
            $replace = 'translate(normalize-space(), "123456789", "%%%%%%%%%")';
        }
        $result = $this->http->FindNodes("//text()[" . $this->eq($text, $replace) . "]/ancestor::*[self::td or self::th][1]/following-sibling::*[1]", null, $regexp);

        if (empty($result)) {
            $result = $this->http->FindNodes("//text()[" . $this->eq($text, $replace) . "]/following::text()[normalize-space()][1]", null, $regexp);
        }

        return $result;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'  => 'EUR',
            '$'  => 'USD',
            '£'  => 'GBP',
            'Rp' => 'IDR',
            '₱'  => 'PHP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
