<?php

namespace AwardWallet\Engine\resdiary\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "resdiary/it-183384895.eml, resdiary/it-190847660.eml, resdiary/it-191954944.eml, resdiary/it-192101341.eml, resdiary/it-200664394.eml, resdiary/it-262147803.eml, resdiary/it-305103070.eml, resdiary/it-367237221.eml, resdiary/it-391529828.eml, resdiary/it-394629055.eml, resdiary/it-439558280.eml, resdiary/it-439573811.eml, resdiary/it-678135607-pt.eml";

    public $lang = '';

    public static $dictionary = [
        'pt' => [
            'confNumber-starts'    => [
                'Ref:', 'Ref :',
            ],
            'confNumber-eq'        => ['Ref'],
            'date'                 => ['Data:', 'Data :', 'Data'],
            'time'                 => ['Hora:', 'Hora :', 'Hora'],
            'numberOfGuests'       => ['Visitantes:', 'Visitantes :', 'Nº Pessoas:', 'Nº Pessoas :', 'Nº Pessoas'],
            'leaveTime'            => ['Retorno de Mesa:', 'Retorno de Mesa :', 'Retorno de Mesa'],
            'leaveTimeBefore'      => ['Pedimos respeitosamente o retorno da mesa ás'],
            'eventNameContext'     => ['Confirmação de Reserva'],
            // 'eventNameAroundPairs' => [
            //     ['', ''],
            // ],
            // 'Here is your reminder of your reservation on' => [''],
            // 'eventNameAfter' => [''],
            // 'addressContext' => [''],
            'addressBefore'        => ['a reserva no'],
            'phone'                => ['Tel:'],
            // 'cancellationPhrases' => [''],
        ],
        'en' => [
            'confNumber-starts'    => [
                'Booking Reference:', 'Booking Reference :',
                'Reservation number:', 'Reservation number :',
                'Ref:', 'Ref :', 'Your Booking Reference Number is:',
                'Reservation Number:', 'Booking ID:',
            ],
            'confNumber-eq'        => ['Booking Reference', 'Reservation number', 'Ref', 'RESERVATION CODE'],
            'date'                 => ['Date:', 'Date :', 'DATE'],
            'time'                 => ['Time:', 'Time :', 'TIME'],
            'numberOfGuests'       => ['Guests:', 'Guests :', 'Number of guests:', 'Number of guests :'],
            'leaveTime'            => ['Leave Time:', 'Leave Time :', 'Leave Time'],
            'leaveTimeBefore'      => ['We respectfully require your table back by'],
            'eventNameContext'     => ['Reservation Confirmation', 'Reservation Reminder', 'Booking Confirmation', 'Reservation Cancellation', 'Reservation Cancelation'],
            'eventNameAroundPairs' => [
                ['Thank you for your reservation at', 'We are delighted to confirm your'],
                ['We are delighted to confirm your', ' reservation at '],
                [', your confirmed', 'reservation at the'],
                ['A kind reminder, your reservation at', 'for '],
                ['Thank you for your reservation at', ', your confirmed booking '],
                ['thank you for choosing our unique', '. Please find your reservation details below.'],
                ['To read the', 'Term & Conditions click'],
            ],
            'Here is your reminder of your reservation on' => ['Here is your reminder of your reservation on', 'A kind reminder, your reservation at', 'This is to confirm your booking'],
            'eventNameAfter'                               => ['This email is to confirm your booking on'],
            'addressContext'                               => ['We are delighted to confirm your', 'your party to'],
            'addressBefore'                                => ['reservation at', 'at'],
            'phone'                                        => ['Tel:', 'Phone:', 'TeI '],
            'cancellationPhrases'                          => [
                'This email is to confirm the cancellation of your reservation',
                'This email is to confirm the cancelation of your reservation',
            ],
        ],
    ];

    private $subjects = [
        'pt' => ['Confirmação de Reserva'],
        'en' => ['Booking Confirmation', 'Reminder of your reservation at', 'Reservation Confirmation for', 'Reservation Cancellation for', 'Reservation Cancelation for'],
    ];

    private $detectors = [
        'pt' => [
            'Detalhes de Reserva:', 'Detalhes de Reserva :',
        ],
        'en' => [
            'booking details are below',
            'booking details are as follows',
            'your reservation details below',
            'your reminder of your reservation on',
            'This email is to confirm the cancellation of your reservation',
            'This email is to confirm the cancelation of your reservation',
            'A kind reminder, your reservation at',
            'Your reservation details are below:',
            'We confirm your booking on',
            'Booking Details;',
            'This email confirms your restaurant reservation at the',
            'We are delighted to confirm your',
            'We are pleased to confirm your reservation at',
            'If by any chance you get delayed on the way to us by more than 15 minutes, please give us a call',
            'We confirm your booking on',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@resdiary.com') !== false;
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
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".resdiary.com/") or contains(@href,"www.resdiary.com")]')->length === 0
            && $this->http->XPath->query('//img[contains(@src,"resdiary.blob.core.windows.net")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->lang = 'en';
        }

        $email->setType('Reservation' . ucfirst($this->lang));

        $this->parseEvent($email);

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

    private function parseEvent(Email $email): void
    {
        $patterns = [
            // Saturday, September 3, 2022    |    domingo, 23 de julho de 2023    |    06 July 2024
            'date'          => '(?:\b[-[:alpha:]]+\s*,\s*[[:alpha:]]+\s*\d{1,2}\s*,\s*\d{4}\b|\b[-[:alpha:]]+\s*,\s*\d{1,2}(?:\s+de)?\s*[[:alpha:]]+\s*(?:de\s+)?\d{4}\b|\b\d{1,2}\s+[[:alpha:]]+\s+\d{4}\b)',
            // 4:19PM    |    2:00 p. m.    |    3pm
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
            // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]',
            // Mr. Hao-Li Huang
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];
        $date = $time = $time2 = null;
        $traveller = $isNameFull = null;

        $xpathNoEmpty = 'string-length(normalize-space())>1';

        $ev = $email->add()->event();
        $ev->place()->type(Event::TYPE_EVENT);

        $eventName = null;

        $eventNames = array_filter($this->http->FindNodes("//tr[not(.//tr) and {$this->contains($this->t('eventNameContext'))}]", null, "/^(.{3,75}?)\s+{$this->opt($this->t('eventNameContext'))}(?:\s*\||$)/"));

        if (count(array_unique($eventNames)) === 1) {
            $eventName = array_shift($eventNames);
        }

        if ($eventName === null) {
            // it-192101341.eml
            $eventNameAroundPairs = (array) $this->t('eventNameAroundPairs');

            if (count($eventNameAroundPairs) === 4) {
                $eventNames = array_filter($this->http->FindNodes("//*[not(.//tr) and {$this->contains($eventNameAroundPairs[0])} and {$this->contains($eventNameAroundPairs[1])}]", null, "/{$this->opt($eventNameAroundPairs[0])}\s*(.{3,75}?)\s*{$this->opt($eventNameAroundPairs[1])}/"));

                if (count(array_unique($eventNames)) === 1) {
                    $eventName = array_shift($eventNames);
                }
            }

            if (empty($eventName)) {
                foreach ($eventNameAroundPairs as $eventNameAroundPair) {
                    $eventNames = array_filter($this->http->FindNodes("//*[not(.//tr) and {$this->contains($eventNameAroundPair[0])} and {$this->contains($eventNameAroundPair[1])}]",
                        null,
                        "/{$this->opt($eventNameAroundPair[0])}\s*(.{3,75}?)\s*{$this->opt($eventNameAroundPair[1])}/"));

                    if (count(array_unique($eventNames)) === 1) {
                        $eventName = array_shift($eventNames);
                    }

                    if (!empty($eventName)) {
                        break;
                    }
                }
            }
        }

        if ($eventName === null) {
            // it-262147803.eml
            $eventName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('eventNameAfter'))}]", null, true, "/(?:^{$this->opt($this->t('eventNameAfter'))}|[ \s]+{$this->opt($this->t('at'))})[ \s]+(\D{3,75}?)(?:[.;!?]|$)/");
        }

        $resInfo = implode("\n", $this->http->FindNodes("//text()[{$this->starts('Thanks for booking at')}]/following::text()[{$this->starts('We confirm your booking on')}]/ancestor::*[{$this->contains('Dear')}][1]/*"));
        $re = "/Dear\s+(?<traveller>{$patterns['travellerName']})\s*[,]?\n\s*Thanks for booking at (?<name>.+?)\.?\s+We confirm your booking on\s*(?<date>.+)\s*at\s+(?<time>.+) for (?<guest>\d+) [[:alpha:]]+\.(\n.+)?\n\s*Booking Reference:\s*(?<conf>[A-Z\d]{5,})\.?(\n|$)/";

        if (preg_match($re, $resInfo, $m)) {
            // Thanks for booking at Imàgo
            // We confirm your booking on Friday, 23 June 2023
            // at 19:30 for 2 people.
            // Area: Window tables - First row
            // Booking Reference: BGK62HKP
            $eventName = $m['name'];
            $date = strtotime($m['date']);
            $time = $m['time'];
            $traveller = $m['traveller'];
            $ev->booked()->guests($m['guest']);
            $ev->general()->confirmation($m['conf']);
        }

        $contactsText = $this->htmlToText(
            $this->http->FindHTMLByXpath("descendant::text()[{$xpathNoEmpty}][last()]/preceding::tr[ *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->starts($this->t('phone'))}] ][1]")
            ?? $this->http->FindHTMLByXpath("descendant::text()[{$xpathNoEmpty}][last()]/ancestor::tr[ *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->starts($this->t('phone'))}] ][1]")
            ?? $this->http->FindHTMLByXpath("descendant::text()[string-length(normalize-space())>1][last()]/ancestor::tr[1][count(.//text()[normalize-space()]) < 10]/descendant::text()[normalize-space()]")
            ?? $this->http->FindHTMLByXpath("//text()[contains(normalize-space(), 'GET DIRECTIONS')]/preceding::text()[normalize-space()][1]/ancestor::*[1]")
        );

        $address = null;
        $phone = null;

        if (preg_match("/^[ ]*{$this->opt($this->t('phone'))}.*(?:\n+[ ]*\S+@\S+[ ]*)*\n+[ ]*([\s\S]{3,})\s*$/m", $contactsText, $m)
            || preg_match("/^\s*(?:[ ]*\S+@\S+[ ]*)*(.+\s[A-Z\d]{7})\s\d+\s+\d+$/", $contactsText, $m)
        ) {
            $address = preg_replace('/\s+/', ' ', $m[1]);
        }

        if (empty($address)) {
            $text1 = $this->http->FindSingleNode("(//node()[{$this->starts($this->t('phone'))}])[1]", null, true,
                "/^\s*{$this->opt($this->t('phone'))}\s*({$patterns['phone']})\s*\|\s*Email ?: ?\S+@\S+\s*$/iu");

            if (!empty($text1)) {
                $address = $this->http->FindSingleNode("(//node()[{$this->starts($this->t('phone'))}])[1]/following-sibling::*[1]");
            }
        }

        if (empty($address) && $eventName) {
            // it-192101341.eml
            $address = $this->http->FindSingleNode("//text()[{$this->contains($this->t('addressContext'))} and {$this->contains($eventName)}]", null, true, "/{$this->opt($this->t('addressContext'))}\s*{$this->opt($eventName)}\s+{$this->opt($this->t('addressBefore'))}\s+(.{3,75}?)(?:\s*[.;!?]|$)/iu");
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('phone'))}[ ]*({$patterns['phone']})[ ]*$/m", $contactsText, $m)) {
            $phone = $m[1];
        }

        if (preg_match("/^\s*{$eventName}\s*(?<phone>[+]\d+[\d\s]+)\S+[@]\S+\.[a-z]+(?<address>.+)/su", $contactsText, $m)
        || preg_match("/^\s*{$eventName}\s*(?<address>.+)/su", $contactsText, $m)) {
            $address = str_replace("\n", "", $m['address']);

            if (isset($m['phone'])) {
                $phone = $m['phone'];
            }
        }

        $ev->place()
            ->name($eventName)
            ->address($address)
            ->phone($phone, false, true);

        ini_set('pcre.backtrack_limit', '4000000');

        $eventText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->starts($this->t('confNumber-starts'))} or {$this->eq($this->t('confNumber-eq'))}]/ancestor::*[ descendant::text()[normalize-space()][4] and {$this->contains($this->t('date'))} and {$this->contains($this->t('time'))} ][1]"));
        ini_restore('pcre.backtrack_limit');
        $eventText2 = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->contains($this->t('Here is your reminder of your reservation on'))}]")); // it-190847660.eml
        $eventText3 = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->contains($this->t('This email is to confirm'))}]")); // it-200664394.eml
        $eventText4 = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->contains($this->t('Booking details:'))}]/ancestor::p[1]")); // it-439558280.eml
        // $traveller = $isNameFull = null;
        if (preg_match("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('Name:'))}[ ]*({$patterns['travellerName']})[ ]*$/im", $eventText, $m)
        || preg_match("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('Name:'))}[ ]*({$patterns['travellerName']})[ ]*{$this->opt($this->t('confNumber-starts'))}/", $eventText, $m)) {
            $traveller = $m[1];
            $isNameFull = true;
        } elseif (empty($traveller)) {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
                $isNameFull = null;
            }

            if (empty($traveller)) {
                $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Dear'))}]/ancestor::span/ancestor::p[1]", null, true, "/^{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u");
            }
        }
        $ev->general()->traveller($traveller, $isNameFull);

        if (empty(array_column($ev->getConfirmationNumbers(), 0))) {
            if (preg_match("/(?:^[ ]*|[ ]{2})({$this->opt($this->t('confNumber-starts'))})[ ]*(?-i)([-A-Z\d]{5,9})(?:[ ]{2}|[ ]*$)/im",
                $eventText, $m)) {
                $ev->general()->confirmation($m[2], trim($m[1], ': '));
            } elseif (!empty($conf = array_unique(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('confNumber-starts'))}]",
                null, "/{$this->opt($this->t('confNumber-starts'))}\s*([A-Z\d]{8})\s*\.?\s*$/u"))))) {
                $ev->general()
                    ->confirmation($conf[0]);
            } elseif (!empty($conf = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('confNumber-starts'))}]/following::text()[normalize-space()][1]",
                null, "/^\s*([A-Z\d]{8})\s*\s*$/u"))))) {
                $ev->general()
                    ->confirmation($conf[0]);
            }
        }

        if (preg_match("/Date\D*\s*(?<date>.+)\s*Time\D*\s*(?<time>[\d\:]+)\s*Guests.*\s*(?<guests>\d+)\s*Reservation Code\s*(?<conf>[A-Z\d]+)/iu", $eventText, $m)) {
            /*DATE Friday, 21 July 2023 TIME 19:30 GUESTS 5 RESERVATION CODE BGHJVHVR   PT Os n*/
            $ev->setStartDate(strtotime($m['date'] . ', ' . $m['time']))
                ->setGuestCount($m['guests']);

            if (!in_array($m['conf'], array_column($ev->getConfirmationNumbers(), 0))) {
                $ev->general()
                    ->confirmation($m['conf']);
            }
        } elseif (preg_match("/{$this->opt($this->t('date'))}(?:[ ]*[\/|]+[ ]*{$this->opt($this->t('date', 'en'))})?\s*(?<date>{$patterns['date']})\s*{$this->opt($this->t('time'))}(?:[ ]*[\/|]+[ ]*{$this->opt($this->t('time', 'en'))})?\s*(?<time>{$patterns['time']})/iu", $eventText, $m)) {
            $date = strtotime($m['date']);
            $time = $m['time'];
        } elseif (preg_match("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('date'))}(?:[ ]*[\/|]+[ ]*{$this->opt($this->t('date', 'en'))})?[ ]*(.*?\d.*?)(?:[ ]{2}|[ ]*$)/im", $eventText, $m)) {
            $date = strtotime($m[1]);
        } elseif (preg_match("/at\s*(?<time>[\d\:]+\s*A?P?M)\s*on\s*(?<date>.+\d{4})\./i", $eventText2, $m)) {
            // A kind reminder, your reservation at BLACK Bar & Grill for 4 guests is at 7:30 PM on Friday, 23 September 2022. Please reconfirm you booking below.
            $date = strtotime($m['date']);
            $time = $m['time'];
        } elseif (preg_match("/{$this->opt($this->t('Here is your reminder of your reservation on'))}\s+(?<date>.{0,22}?\d.{0,22}?)\s+{$this->opt($this->t('at'))}\s+(?<time>{$patterns['time']})(?:\s+{$this->opt($this->t('for'))}\s+|[ ]*[,.;:!?]+|[ ]*$)/i", $eventText2, $m)) {
            // Here is your reminder of your reservation on Saturday, September 3, 2022 at 6:30 PM for The Golf Club Dining Room.
            $date = strtotime($m['date']);
            $time = $m['time'];
        } elseif (preg_match("/^.+\s+{$this->opt($this->t('on'))}\s+(?<date>.{0,22}?\d.{0,22}?)\s+{$this->opt($this->t('at'))}\s+(?<time>{$patterns['time']})(?:\s+{$this->opt($this->t('for'))}\s+|[ ]*[,.;:!?]+|[ ]*$)/i", $eventText3, $m)) {
            // This email is to confirm the cancellation of your reservation at Monk's Lounge on Wednesday, 18 May 2022 at 7:30 PM.
            $date = strtotime($m['date']);
            $time = $m['time'];
        } elseif (preg_match("/Date\D*\:\s*(?<date>.+)\n\s*Time\D*\:\s*(?<time>[\d\:]+)\n\s*Guests.*\:\s*(?<guests>\d+)/i", $eventText4, $m)) {
            /*Booking details:
            Ref: BXPZ9FAJ
            Date/Data: domingo, 23 de julho de 2023
            Time/Hora: 19:30
            Guests/Visitantes: 5*/

            $ev->setStartDate($this->normalizeDate($m['date'] . ' ' . $m['time']))
                ->setGuestCount($m['guests']);
        }

        if (preg_match("/[ ]*{$this->opt($this->t('time'))}[ ]*(?<time1>{$patterns['time']})(?:[ ]+-[ ]+(?<time2>{$patterns['time']}))?(?:\s+{$this->opt($this->t('numberOfGuests'))}[ ]*(?<guests>\d{1,3}\b))?/im", $eventText, $m)) {
            // Time: 12:00 PM    |    Time: 12:00 PM - 1:45 PM  Guests: 5
            $time = $m['time1'];

            if (!empty($m['time2'])) {
                $time2 = $m['time2'];
            }

            if (array_key_exists('guests', $m) && $m['guests'] !== '') {
                $ev->booked()->guests($m['guests']);
            }
        }

        $leaveTime = preg_match("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('leaveTime'))}(?:[ ]*[\/|]+[ ]*{$this->opt($this->t('leaveTime', 'en'))})?[ ]*(.*?\d.*?)(?:[ ]{2}|[ ]*$)/im", $eventText, $m) ? $m[1] : null;

        if (!$time2 && preg_match("/{$this->opt($this->t('leaveTimeBefore'))}[:\s]+({$patterns['time']})(?:\s*\(|[\s.!?]*$)/u", $leaveTime, $m)) {
            // it-678135607-pt.eml
            $time2 = $m[1];
        }

        if (!empty($date)) {
            $ev->booked()
                ->start(($time !== null) ? strtotime($time, $date) : $date);
        }

        $diningTime = $this->http->FindSingleNode("//text()[{$this->contains($this->t('table is entitled to a'))}]", null, true, "/{$this->opt($this->t('table is entitled to a'))}\s+((?:[ ]*\d{1,3}[ ]*[hm]\b)+)/");

        if ($date && $time2) {
            // it-305103070.eml
            $ev->booked()->end(strtotime($time2, $date));
        } elseif (!empty($ev->getStartDate()) && $diningTime !== null) {
            // it-192101341.eml
            $diningTime = preg_replace(['/(\d+)\s*h(\W|\d|$)/i', '/(\d+)\s*m$/i'], [' $1 hours$2', ' $1 minutes'], $diningTime); // 2h 0m    ->    2 hours 0 minutes
            $ev->booked()->end(strtotime('+' . $diningTime, $ev->getStartDate()));
        } elseif (!empty($ev->getStartDate())) {
            $ev->booked()->noEnd();
        }

        if (preg_match("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('numberOfGuests'))}(?:[ ]*[\/|]+[ ]*{$this->opt($this->t('numberOfGuests', 'en'))})?[: ]*(\d{1,3})(?:[ ]{2}|[ ]*$)/im", $eventText, $m)) {
            $ev->booked()->guests($m[1]);
        } elseif (preg_match("/{$this->opt($this->t('for'))}\s*(\d+)\s*{$this->opt($this->t('guest'))}/", $eventText2, $m)) {
            $ev->booked()->guests($m[1]);
        }

        if (empty($eventText) && (!empty($eventText2) || !empty($eventText3)) && empty($ev->getConfirmationNumbers())) {
            // it-190847660.eml
            $ev->general()->noConfirmation();
        }

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancellationPhrases'))}]")->length > 0) {
            // it-200664394.eml
            $ev->general()->cancelled();
        }
    }

    private function assignLang(): bool
    {
        if (!isset($this->detectors, $this->lang)) {
            return false;
        }

        foreach ($this->detectors as $lang => $phrases) {
            if (!is_string($lang)) {
                continue;
            }

            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
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

    private function normalizeDate($str)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Confirmação em Português abaixo')]")->length > 0) {
            $this->lang = 'pt';
        }

        $in = [
            "#^\w+\,\s*(\d+)\s*de\s*(\w+)\s*de\s*(\d{4})\s*([\d\:]+\s*A?P?M?\s*)$#u", //domingo, 23 de julho de 2023 19:30
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
