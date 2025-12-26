<?php

namespace AwardWallet\Engine\jayride\Email;

use AwardWallet\Schema\Parser\Email\Email;

class TransferWith extends \TAccountChecker
{
    public $mailFiles = "jayride/it-691423493.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'travelDate'     => ['Travel Date'],
            'dropOff'        => ['Drop-off'],
            'statusPhrases'  => ['Your booking is'],
            'statusVariants' => ['confirmed'],
        ],
    ];

    private $subjects = [
        'en' => ['Booking Confirmed - transfer with'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]jayride\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".jayride.com/")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"Jayride Group Limited |")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Jayride Booking ID:") or contains(normalize-space(),"you have booked with jayride.com")]')->length === 0
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
        $email->setType('TransferWith' . ucfirst($this->lang));

        $patterns = [
            'date'          => '\b\d{1,2}[-,.\s]+[[:alpha:]]+[-,.\s]+\d{4}\b', // 27 Sep 2024
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $isTransferFromAirport = $isTransferToAirport = null;

        /*
        $f = $email->add()->flight();
        $fSeg = $f->addSegment();
        */

        $t = $email->add()->transfer();
        $tSeg = $t->addSegment();

        $otaConfirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Jayride Booking ID:'))}]");

        if (preg_match("/^({$this->opt($this->t('Jayride Booking ID:'))})[:\s]*([-A-z\d]{4,})$/", $otaConfirmation, $m)) {
            $email->ota()->confirmation($m[2], rtrim($m[1], ': '));
            /*
            $f->general()->noConfirmation();
            */
            $t->general()->noConfirmation();
        }

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $t->general()->status($status);
        }

        /*
        $company = $this->http->FindSingleNode("//text()[{$this->starts($this->t('You are booked with'))}]", null, true, "/^{$this->opt($this->t('You are booked with'))}\s+(.{3,})$/");
        $t->program()->keyword($company);
        */

        $carImageUrl = $this->http->FindSingleNode("//img[preceding::text()[{$this->starts($this->t('You are booked with'))}] and following::*[{$this->eq($this->t('Contact Details'))}] and normalize-space(@src)]/@src");
        $tSeg->extra()->image($carImageUrl, false, true);

        $marksText = implode("\n", $this->http->FindNodes("//text()[preceding::text()[{$this->starts($this->t('You are booked with'))}] and following::*[{$this->eq($this->t('Contact Details'))}] and normalize-space()]"));

        $carType = preg_match_all("/^(?:SEDAN|SUV|VAN)$/im", $marksText, $carTypeMatches) && count($carTypeMatches[0]) === 1 ? $carTypeMatches[0][0] : null;
        $tSeg->extra()->type($carType, false, true);

        $notes = $this->http->FindSingleNode("//*[{$this->eq($this->t('Meeting Instructions'))}]/ancestor-or-self::*[ following-sibling::node()[normalize-space()] ][1]/following-sibling::node()[normalize-space()]");

        if ($notes) {
            $t->general()->notes($notes);
        }

        $phonesVal = $this->http->FindSingleNode("//*[{$this->eq($this->t('Contact Details'))}]/following::*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('Phone Number'))}] ]/node()[normalize-space()][2]");
        $phonesTexts = preg_split("/\s+{$this->opt($this->t('or'))}\s+/", $phonesVal);

        foreach ($phonesTexts as $phText) {
            if (preg_match("/^(?<phone>{$patterns['phone']})\s*\(\s*(?<desc>[^)(]+?)\s*\)$/", $phText, $m)) {
                $t->program()->phone($m['phone'], $m['desc']);
            } elseif (preg_match("/^(?<phone>{$patterns['phone']})(?:\s*\(|$)?/", $phText, $m)) {
                $t->program()->phone($m['phone']);
            }
        }

        $travelDate = strtotime($this->http->FindSingleNode("//text()[preceding::text()[{$this->eq($this->t('Travel Date'))}] and following::text()[{$this->eq($this->t('Flight Time'))}] and normalize-space()]", null, true, "/^{$patterns['date']}$/"));
        $flightTime = $this->http->FindSingleNode("//text()[preceding::text()[{$this->eq($this->t('Flight Time'))}] and following::text()[{$this->eq($this->t('Flight Number'))}] and normalize-space()]", null, true, "/^{$patterns['time']}/");
        $flightNumberVal = $this->http->FindSingleNode("//text()[preceding::text()[{$this->eq($this->t('Flight Number'))}] and following::text()[{$this->eq($this->t('Passengers'))}] and normalize-space()]", null, true, "/.*\d.*/");

        /*
        if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flightNumberVal, $m)
            || preg_match("/^(?<name>.{2,}?)\s+(?<number>\d+)$/", $flightNumberVal, $m)
        ) {
            // AF 353    |    Lufthansa 1786
            $fSeg->airline()->name($m['name'])->number($m['number']);
        } elseif (preg_match("/^\d+$/", $flightNumberVal)) {
            // 353    |    1786
            $fSeg->airline()->number($m['number']);
        }
        */

        $adults = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passengers'))}]/following::text()[normalize-space()][1]", null, true, "/^\d{1,3}\b/");
        $tSeg->extra()->adults($adults, false, true);

        $pickUpLocation = $this->http->FindSingleNode("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('Pick-up'))}] ]/node()[normalize-space()][2]")
            ?? $this->http->FindSingleNode("//text()[preceding::text()[{$this->eq($this->t('Pick-up'))}] and following::text()[{$this->eq($this->t('Drop-off'))}] and normalize-space()]");
        $dropOffLocation = $this->http->FindSingleNode("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('Drop-off'))}] ]/node()[normalize-space()][2]")
            ?? $this->http->FindSingleNode("//text()[preceding::text()[{$this->eq($this->t('Drop-off'))}] and following::text()[{$this->eq($this->t('Passenger Details'))}] and normalize-space()]");

        // London Heathrow Airport (LHR), Terminal 3
        $patterns['nameCode'] = "/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)/";

        // The Montague on the Gardens (15 Montague St, London WC1B 5BJ, UK)
        $patterns['nameAddress'] = "/^(?<name>[^)(]{2,}?)\s*\(\s*(?<address>.{4,75}?)\s*\)$/";

        if (preg_match($patterns['nameCode'], $pickUpLocation, $m)) {
            $isTransferFromAirport = true;
            /*
            $fSeg->arrival()->name($m['name'])->code($m['code']);
            */
            $tSeg->departure()->name($m['name'])->code($m['code']);
        } elseif (preg_match($patterns['nameAddress'], $pickUpLocation, $m)) {
            $tSeg->departure()->name($m['name'] . ', ' . $m['address']);
        } else {
            $tSeg->departure()->name($pickUpLocation);
        }

        if (preg_match($patterns['nameCode'], $dropOffLocation, $m)) {
            $isTransferToAirport = true;
            /*
            $fSeg->departure()->name($m['name'])->code($m['code']);
            */
            $tSeg->arrival()->name($m['name'])->code($m['code']);
        } elseif (preg_match($patterns['nameAddress'], $dropOffLocation, $m)) {
            $tSeg->arrival()->name($m['name'] . ', ' . $m['address']);
        } else {
            $tSeg->arrival()->name($dropOffLocation);
        }

        $date = strtotime($flightTime, $travelDate);

        if ($isTransferFromAirport && !$isTransferToAirport) { // Airport -> (...)
            /*
            $fSeg->departure()->noCode()->noDate();
            $fSeg->arrival()->date($date);
            */

            $dateCorrection = '30 minutes';
            /*
            $tSeg->setDepFlightSegment($fSeg);
            $tSeg->setFlightDateCorrection($dateCorrection);
            */
            $tSeg->departure()->date(strtotime($dateCorrection, $date));
            $tSeg->arrival()->noDate();
        } elseif (!$isTransferFromAirport && $isTransferToAirport) { // (...) -> Airport
            /*
            $fSeg->departure()->date($date);
            $fSeg->arrival()->noCode()->noDate();
            */

            $dateCorrection = '-3 hours';
            /*
            $tSeg->setArrFlightSegment($fSeg);
            $tSeg->setFlightDateCorrection($dateCorrection);
            */
            $tSeg->departure()->noDate();
            $tSeg->arrival()->date(strtotime($dateCorrection, $date));
        } elseif ($isTransferFromAirport && $isTransferToAirport // Airport -> Airport
            || !$isTransferFromAirport && !$isTransferToAirport // (...) -> (...)
        ) {
            $this->logger->debug('Relationship between flight and transfer is not defined!');
        }

        $passengerName = $this->http->FindSingleNode("//text()[preceding::text()[{$this->eq($this->t('Passenger Name'))}] and following::text()[{$this->eq($this->t('Passenger Mobile'))}] and normalize-space()]", null, true, "/^{$patterns['travellerName']}$/u")
            ?? $this->http->FindSingleNode("//text()[preceding::text()[{$this->eq($this->t('Passenger Name'))}] and following::text()[{$this->eq($this->t('Email Address'))}] and normalize-space()]", null, true, "/^{$patterns['travellerName']}$/u")
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passenger Name'))}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u")
        ;
        /*
        $f->general()->traveller($passengerName, true);
        */
        $t->general()->traveller($passengerName, true);

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
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['travelDate']) || empty($phrases['dropOff'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->eq($phrases['travelDate'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->eq($phrases['dropOff'])}]")->length > 0
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
}
