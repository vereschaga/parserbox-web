<?php

namespace AwardWallet\Engine\aleno\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "aleno/it-887394441.eml, aleno/it-887394093.eml, aleno/it-872441756.eml, aleno/it-886158115.eml, aleno/it-887121205.eml, aleno/it-888834299.eml, aleno/it-886483661-cancelled.eml, aleno/it-886707444-de.eml, aleno/it-886396017-de-cancelled.eml";

    private $subjects = [
        'de' => ['Reservierungsbestätigung', 'Reservierungserinnerung', 'Reservierungsstornierung'],
        'en' => ['Reservation confirmation', 'Reservation reminder', 'Reservation cancellation', 'Reservation cancelation'],
    ];

    public $lang = '';

    public static $dictionary = [
        'de' => [
            'langDetect' => ['Dank für', 'Beste Grüsse', 'begrüßen dürfen', 'Eine kurze Erinnerung'],
        ],
        'en' => [
            'langDetect' => ['Thank you', 'Your reservation at', 'We have', 'We are looking forward'],
        ],
    ];

    private $enDatesInverted = true; // true - because aleno is german company

    private $helloPhrases = ['Sehr geehrte/e', 'Sehr geehrte/r', 'Liebe(r)', 'Liebe(r )', 'Guten Tag', 'Sehr geehrter', 'Bonjour', 'Hallo', 'Mr. / Mrs.', 'Mrs. / Mr.', 'Dear', 'Hoi', 'Hello', 'Hi'];

    private $patterns = [
        'date' => '\b\d{1,2}[.\/]\d{1,2}[.\/]\d{2,4}\b', // 17.02.2025  |  16/03/2025
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52  |  (+351) 21 342 09 07  |  713.680.2992
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'badTravellerName' => '/^\s*Guest\s*$/i',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]aleno\.me$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array)$phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false)
                    return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $href = ['.aleno.me/', 'mytools.aleno.me'];

        if ($this->detectEmailFromProvider($parser->getCleanFrom()) !== true
            && $this->http->XPath->query("//a[{$this->contains($href, '@href')} or {$this->contains($href, '@originalsrc')}]")->length === 0
            && $this->http->XPath->query('//img[contains(@class,"restaurant-logo-center") and contains(@src,".aleno.me/")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"@aleno.me")]')->length === 0
        ) {
            return false;
        }
        return $this->findRoots()->length > 0;
    }

    private function findRoots(): \DOMNodeList
    {
        return $this->http->XPath->query("//hr[ preceding-sibling::*[normalize-space()][position()<4][local-name()='p'] ]/following-sibling::*[normalize-space() and not({$this->contains(['Add to calender', 'Add to Calendar', 'Reservierung stornieren'])})][1][local-name()='h6' and contains(.,'-')]");
    }

    private function getMainText(\DOMNode $root): ?string
    {
        $mainRows = [];
        $mainNodes = $this->http->XPath->query("preceding-sibling::node()[normalize-space() and not(self::comment() or self::ul or self::ol)] | preceding-sibling::*[self::ul or self::ol]/li[normalize-space()]", $root);

        foreach ($mainNodes as $row) {
            $mainRows[] = $this->htmlToText( $this->http->FindHTMLByXpath('.', null, $row) );
        }

        $mainText = implode("\n", $mainRows);

        if (preg_match("/^(?<start>[\s\S]*?)(?<time>\s{$this->patterns['time']})\s+(?<seatsPrefix>(?:[[:alpha:]]+\s+){1,3})?(?<seatsCount>\d{1,3})(?<end>[ ]*[.;!\n][\s\S]*|\s+)$/u", $mainText, $m)) {
            // at 12:15 2        ->    at 12:15 for 2 seats
            // at 12:15 for 2    ->    at 12:15 for 2 seats
            $mainText = $m['start'] . $m['time'] . ' ' . (empty($m['seatsPrefix']) ? 'for ' : $m['seatsPrefix']) . $m['seatsCount'] . ' seats' . $m['end'];
        }

        $mainText = preg_replace([
            "/^((?:[ ]*{$this->opt($this->helloPhrases)})+)(?-i)([[:upper:]][[:lower:]])/imu", // DearGreg Smucker  ->  Dear Greg Smucker
            "/(\d{1,2}:\d{2}[ ]*[AP])[ ]+([Mm]\b)/", // 12:15 P m  ->  12:15 Pm
            "/(?:\s+a[ ]+[Tt]ables?\b|\s+for[ ]+[Yy]our?\b|\s+einen[ ]+[Tt]isch|\s+für[ ]+[Ss]ie\b)/u", // garbage phrases (Langs: en, de)
        ], [
            '$1 $2',
            '$1$2',
            '',
        ], $mainText);

        $this->logger->info('MAIN TEXT:');
        $this->logger->debug($mainText);

        return $mainText;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        if ( empty($this->lang) ) {
            $this->logger->debug("Can't determine a language!");
        }
        $email->setType('YourReservation' . ucfirst($this->lang));

        $rootNodes = $this->findRoots();

        if ($rootNodes->length === 0) {
            $this->logger->debug('Root-nodes not found!');
        }

        foreach ($rootNodes as $root) {
            $ev = $email->add()->event();
            $ev->type()->restaurant();

            $traveller = $isNameFull = null;
            $guestCount = $status = null;
            $dateStart = $dateEnd = $timeStart = $timeEnd = null;
            $placeName = $address = $phone = null;
            $confirmation = $confirmationTitle = null;

            $mainText = $this->getMainText($root);

            if ( preg_match_all('/\b(\d{1,2})\s*\/\s*\d{1,2}\s*\/\s*\d{4}\b/', $mainText, $dateMatches) ) {
                foreach ($dateMatches[1] as $simpleDate) {
                    if ($simpleDate > 12) {
                        $this->enDatesInverted = true;
                        break;
                    }
                }
            }

            $this->patterns['seats'] = '(?:seats?|persons?|guests?|people|Plätze|Personen)'; // Langs: en, de
            $this->patterns['status'] = '(?:confirmed|confirm|reserved|cancell?ed|reserviert|storniert)'; // Langs: en, de
            $this->patterns['cancelledStatus'] = '(?:cancell?ed|storniert)'; // Langs: en, de
            $this->patterns['end'] = '\s*(?:[.;!\n]|$)';

            // pattern components (main)
            $reStart = "(?:Thank you reservation|Your reservation|We are pleased|We've got|We have|Bald ist es soweit|Sehr gerne haben wir|Ihre Reservierung|Wir haben|Du hast|Dein Tisch)"; // Langs: en, de
            $reDateStart = "(?<dateStart>{$this->patterns['date']})";
            $reTimeStart = "(?<timeStart>{$this->patterns['time']})(?:\s*Uhr\b|\s*h\b)?";
            $reTimeEnd = "(?<timeEnd>{$this->patterns['time']})(?:\s*Uhr\b|\s*h\b)?";
            $reSEATS = "(?<guests>\d{1,3})\s+{$this->patterns['seats']}";
            $reStatus = "(?<status>{$this->patterns['status']})";
            $reEND = "(?:{$this->patterns['end']}|[,\s]+und uns diese Bemerkung hinterlassen[ ]*:)"; // Langs: de

            // pattern components (secondary)
            $reDateStartPrefix = "(?:[[:alpha:]]+[,:\s]+){0,3}";
            $reTimeStartPREFIX = "(?:[[:alpha:]]+\s+){0,4}";
            $reTimeEndPrefix = "\s+[[:alpha:]]+\s+";
            $reSeatsPREFIX = "(?:[[:alpha:]]+\s+){1,3}";
            $reStatusPrefix = "(?:[[:alpha:]]+\s+){1,2}";
            $rePlaceName = "(?:at|in|im)[ ]+\S.*?\S"; // Langs: en, de
            $reGuestNAME = "auf den Namen[ ]+{$this->patterns['travellerName']}"; // Langs: de

            $mainPatterns = [
                // de
                // "/ /iu",

                // en
                'Kulm Country Club' => "/Thank you(?:[,!\s]+for informing us of your cancell?ation|[,!\s]+reservation)[,!\s]+(?<guestName>{$this->patterns['travellerName']})[ .:!]*[,\s]+On[ ]+{$reDateStart}[,\s]+For[ ]+{$reSEATS}[,\s]+at[ ]+{$reTimeStart}[,\s]+{$reStatus}(?:\s+reservation)?[,\s]+for[ ]+(?<placeName>Kulm Country Club){$reEND}/iu", // it-888939726-cancelled.eml

                // UNIVERSAL (general cases)

                // (?status <-> ?guestName <-> ?placeName) dateStart timeStart ?timeEnd (?guests <-> ?guestName <-> ?placeName)
                'uni-G-1' => "/{$reStart}[,:\s]+(?:{$reStatus}[,:\s]+|{$reStatusPrefix}{$reStatus}[,:\s]+|{$reGuestNAME}[,:\s]+|{$rePlaceName}[,:\s]+){0,3}{$reDateStartPrefix}{$reDateStart}[,\s]+{$reTimeStartPREFIX}{$reTimeStart}(?:{$reTimeEndPrefix}{$reTimeEnd})?(?:\s+{$reSEATS}|\s+{$reSeatsPREFIX}{$reSEATS}|\s+{$reGuestNAME}|\s+{$rePlaceName}){0,3}{$reEND}/iuJ",

                // (?guests <-> ?guestName <-> ?placeName) dateStart timeStart ?timeEnd (?status <-> ?guestName <-> ?placeName)
                'uni-G-2' => "/{$reStart}[,:\s]+(?:{$reSEATS}[,:\s]+|{$reSeatsPREFIX}{$reSEATS}[,:\s]+|{$reGuestNAME}[,:\s]+|{$rePlaceName}[,:\s]+){0,3}{$reDateStartPrefix}{$reDateStart}[,\s]+{$reTimeStartPREFIX}{$reTimeStart}(?:{$reTimeEndPrefix}{$reTimeEnd})?(?:\s+(?:{$reStatus}|{$reStatusPrefix}{$reStatus})|\s+{$reGuestNAME}|\s+{$rePlaceName}){0,3}{$reEND}/iuJ",

                // (?guests <-> ?status <-> ?guestName <-> ?placeName) dateStart timeStart ?timeEnd
                // it-887394441.eml
                'uni-G-3' => "/{$reStart}[,:\s]+(?:{$reSEATS}[,:\s]+|{$reStatus}[,:\s]+|{$reSeatsPREFIX}{$reSEATS}[,:\s]+|{$reStatusPrefix}{$reStatus}[,:\s]+|{$reGuestNAME}[,:\s]+|{$rePlaceName}[,:\s]+){0,4}{$reDateStartPrefix}{$reDateStart}[,\s]+{$reTimeStartPREFIX}{$reTimeStart}(?:{$reTimeEndPrefix}{$reTimeEnd})?{$reEND}/iuJ",

                // dateStart timeStart ?timeEnd (?guests <-> ?status <-> ?guestName <-> ?placeName)
                'uni-G-4' => "/{$reStart}[,:\s]+{$reDateStartPrefix}{$reDateStart}[,\s]+{$reTimeStartPREFIX}{$reTimeStart}(?:{$reTimeEndPrefix}{$reTimeEnd})?(?:\s+{$reSEATS}|\s+{$reStatus}|\s+{$reSeatsPREFIX}{$reSEATS}|\s+{$reStatusPrefix}{$reStatus}|\s+{$reGuestNAME}|\s+{$rePlaceName}){0,4}{$reEND}/iuJ",

                // placeName dateStart timeStart ?timeEnd (?guests <-> ?status <-> ?guestName)
                // it-886483661-cancelled.eml
                'uni-G-5' => "/{$reStart}[,:\s]+{$rePlaceName}[,:\s]+{$reDateStartPrefix}{$reDateStart}[,\s]+{$reTimeStartPREFIX}{$reTimeStart}(?:{$reTimeEndPrefix}{$reTimeEnd})?(?:\s+{$reSEATS}|\s+{$reStatus}|\s+{$reSeatsPREFIX}{$reSEATS}|\s+{$reStatusPrefix}{$reStatus}|\s+{$reGuestNAME}){0,3}{$reEND}/iuJ",

                // UNIVERSAL (special cases)

                // status
                // it-887394441.eml
                'uni-s-1' => "/{$reStart}(?i)[,:\s]+(?:{$reStatus}[,:\s]+|{$reStatusPrefix}{$reStatus}[,:\s]+)/uJ",

                // timeStart ?timeEnd guests
                // it-887394093.eml
                'uni-s-2' => "/[,\s]+{$reTimeStartPREFIX}{$reTimeStart}(?:{$reTimeEndPrefix}{$reTimeEnd})?\s+{$reSeatsPREFIX}(?<guests>\d{1,3}){$reEND}/iu",
            ];

            $results = $usedPatterns = [];

            foreach ($mainPatterns as $key => $pattern) {
                // if (true || $key === 0) {
                //     print_r($pattern . "\n\n");
                // }

                if (preg_match($pattern, $mainText, $m)) {
                    $it = [];

                    if (!empty($m['guestName'])) {
                        $it['guestName'] = $m['guestName'];
                    }

                    if (!empty($m['dateStart'])) {
                        $it['dateStart'] = strtotime($this->normalizeDate($m['dateStart']));
                    }

                    if (!empty($m['timeStart'])) {
                        $it['timeStart'] = $m['timeStart'];
                    }

                    if (!empty($m['timeEnd'])) {
                        $it['timeEnd'] = $m['timeEnd'];
                    }

                    if (array_key_exists('guests', $m) && $m['guests'] !== '') {
                        $it['guests'] = $m['guests'];
                    }

                    if (!empty($m['status'])) {
                        $it['status'] = $m['status'];
                    }

                    if (!empty($m['placeName'])) {
                        $it['placeName'] = $m['placeName'];
                    }

                    $this->logger->info('PATTERN: ' . $key . '; FIELDS COUNT: ' . count($it));
                    $this->logger->debug(print_r($it, true));
                    $usedPatterns[] = $key;
                    $results[] = $it;
                }
            }

            foreach ($results as $fields) {
                if (empty($placeName) && array_key_exists('guestName', $fields)) {
                    $traveller = $this->normalizeTraveller($fields['guestName']);
                }

                if (empty($dateStart) && array_key_exists('dateStart', $fields)) {
                    $dateStart = $fields['dateStart'];
                }

                if (empty($timeStart) && array_key_exists('timeStart', $fields)) {
                    $timeStart = $fields['timeStart'];
                }

                if (empty($timeEnd) && array_key_exists('timeEnd', $fields)) {
                    $timeEnd = $fields['timeEnd'];
                }

                if ($guestCount === null && array_key_exists('guests', $fields)) {
                    $guestCount = $fields['guests'];
                }

                if (empty($status) && array_key_exists('status', $fields)) {
                    $status = $fields['status'];
                }

                if (empty($placeName) && array_key_exists('placeName', $fields)) {
                    $placeName = $fields['placeName'];
                }
            }

            if (preg_match("/^[ ]*{$this->opt(['Name'])}\s*[:]+\s*({$this->patterns['travellerName']})[ ]*$/imu", $mainText, $m)
                && !preg_match($this->patterns['badTravellerName'], $m[1])
            ) {
                $traveller = $this->normalizeTraveller($m[1]);
                $isNameFull = true;
            } elseif (!$traveller && preg_match("/^(?:\s*{$this->opt($this->helloPhrases)})+[,!\s]+({$this->patterns['travellerName']})(?:\s*[,;:!?]|[ ]*\n|\s*$)/iu", $mainText, $m)
                && !preg_match($this->patterns['badTravellerName'], $m[1])
            ) {
                $traveller = $this->normalizeTraveller($m[1]);
            }

            if (!$dateStart && preg_match("/^[ ]*{$this->opt(['Date'])}\s*[:]+\s*({$this->patterns['date']})[ ]*$/im", $mainText, $m)) {
                $dateStart = strtotime($this->normalizeDate($m[1]));
            }

            if (!$timeStart && preg_match("/^[ ]*{$this->opt(['Time'])}\s*[:]+\s*(?<time1>{$this->patterns['time']})[- ]*(?<time2>{$this->patterns['time']})?[ ]*$/im", $mainText, $m)) {
                // Time: 18:30 - 20:00
                $timeStart = $m['time1'];

                if (!empty($m['time2'])) {
                    $timeEnd = $m['time2'];
                }
            }

            if (preg_match("/Dein Tisch steht dir[,:\s]+{$reDateStartPrefix}{$reTimeEnd}\s+zur Verfügung{$this->patterns['end']}/iu", $mainText, $m) // de
                || preg_match("/We will kindly request the return of your table[,:\s]+{$reDateStartPrefix}{$reTimeEnd}{$this->patterns['end']}/iu", $mainText, $m) // en
            ) {
                $timeEnd = $timeEnd ?? $m[1];
            }

            if ($guestCount === null && preg_match("/^[ ]*{$this->opt(['Number of guests', 'People'])}\s*[:]+\s*(\d{1,3})[ ]*$/im", $mainText, $m)) {
                $guestCount = $m[1];
            }

            if (!$confirmation && preg_match("/^[ ]*({$this->opt(['Reservation number (reference)'])})\s*[:]+\s*([-A-z\d]{3,})[ ]*$/im", $mainText, $m)) {
                // it-872441756.eml
                $confirmation = $m[2];
                $confirmationTitle = $m[1];
            }

            $footerText = $this->http->FindSingleNode('.', $root, true, "/^[-\s]*(.*?)[-\s]*$/");

            // remove email address
            $footerText = preg_replace('/^(.*?)(?:\s*-\s*)+\S+@\S+$/', '$1', $footerText);

            if (preg_match("/^(.*?)(?:\s*-\s*)?({$this->patterns['phone']})$/", $footerText, $m)) {
                $footerText = $m[1];
                $phone = $phone ?? $m[2];
            }

            if (in_array('Kulm Country Club', $usedPatterns) && strlen($footerText) > 2
                && !preg_match("/^[-\s]*Kulm Country Club\b/i", $footerText)
            ) {
                $address = $address ?? $footerText;
            } elseif (preg_match("/^(?<name>.{2,}?)\s+-\s+(?<address>.{3,})$/", $footerText, $m)) {
                $placeName = $placeName ?? $m['name'];
                $address = $address ?? $m['address'];
            }

            /* travellers */

            if ($traveller) {
                $ev->general()->traveller($traveller, $isNameFull);
            }

            /* dates */

            if ($dateStart && $timeStart) {
                $ev->booked()->start(strtotime($timeStart, $dateStart));
            }

            if (!empty($ev->getStartDate())) {
                if (preg_match("/your table is reserved for\s+(\d{1,3}\s+hours?)(?:\s+and can then be booked again|{$this->patterns['end']})/i", $mainText, $m) // en
                    || preg_match("/Thank you for releasing your table booked for the first seating after\s+(\d{1,3}\s+hours?){$this->patterns['end']}/i", $mainText, $m) // en
                ) {
                    $dateTimeEnd = strtotime('+' . $m[1], $ev->getStartDate());
                    
                    if ($dateTimeEnd) {
                        $dateEnd = strtotime(date('d.m.Y', $dateTimeEnd));
                        $timeEnd = date('H:i', $dateTimeEnd);
                    }
                }
            }

            if (!$dateEnd && $timeEnd) {
                $dateEnd = $dateStart;
            }

            if ($dateEnd && $timeEnd) {
                $ev->booked()->end(strtotime($timeEnd, $dateEnd));
            }

            if (!empty($ev->getStartDate()) && !$dateEnd && !$timeEnd) {
                $ev->booked()->noEnd();
            }

            /* extra */

            if ($guestCount !== null) {
                $ev->booked()->guests($guestCount);
            }

            if ($status) {
                $ev->general()->status($status);
            }

            if (preg_match("/^{$this->patterns['cancelledStatus']}$/i", $status)) {
                $ev->general()->cancelled();
            }

            /* place */

            $ev->place()->name($placeName)->address($address)->phone($phone, false, true);

            /* confNumber */

            if ($confirmation) {
                $ev->general()->confirmation($confirmation, $confirmationTitle);
            } elseif (!preg_match("/^[ ]*{$this->opt(['Reservation number (reference)'])}\s*:/im", $mainText)
                && !empty($ev->getStartDate()) && !empty($ev->getAddress())
            ) {
                $ev->general()->noConfirmation();
            }
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

    private function assignLang(): bool
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['langDetect']) ) {
                continue;
            }
            if ($this->http->XPath->query("//*[{$this->contains($phrases['langDetect'])}]")->length > 0) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
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

    /**
     * @param string|null $text Unformatted string with date
     * @return string
     */
    private function normalizeDate(?string $text): string
    {
        if ( !is_string($text) || empty($text) )
            return '';
        $in = [
            // 03/16/25  |  16/03/2025
            '/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/',
        ];
        $out[0] = $this->enDatesInverted === true ? '$2/$1/$3' : '$1/$2/$3';
        return preg_replace($in, $out, $text);
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MASTER|MSTR|MISS|MRS|MR|MS|DR|Herr)';

        return preg_replace([
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
        ], [
            '$1',
        ], $s);
    }
}
