<?php

namespace AwardWallet\Engine\premierinn\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "premierinn/it-105362364.eml, premierinn/it-34656070.eml, premierinn/it-34941034.eml, premierinn/it-67861388.eml";

    public $lang = '';
    public $text;

    public static $dictionary = [
        'en' => [
            'Check out'           => ['Check out', 'Check-out'],
            'Hotel details'       => ['Hotel details'],
            'adult'               => ['adult', 'adults'],
            'statusVariants'      => ['confirmed', 'cancelled', 'canceled'],
            'cancelledPhrases'    => [
                'your upcoming stay has been cancelled',
                'your upcoming stay has been canceled',
                'Your upcoming stay has been cancelled',
                'We’re really sorry your stay has been cancelled',
            ],
        ],
        'de' => [
            'Booking Reference'                                    => 'Buchungsnummer',
            'Enjoy your stay'                                      => 'Wir wünschen Ihnen einen angenehmen Aufenthalt',
            'Check in'                                             => ['Check in', 'Check-in'],
            'Check out'                                            => ['Check out', 'Check-out'],
            'Hotel details'                                        => ['Hotelangaben'],
            'adult'                                                => ['adult', 'adults', 'Erwachsener', 'Erwachsene'],
            'child'                                                => 'Kind',
            'Room details'                                         => 'Zimmer',
            'Telephone:'                                           => 'Telefon:',
            'Room total:'                                          => 'Gesamtpreis für das Zimmer:',
            'Total price'                                          => 'Gesamtpreis',
            'What happens if I need to cancel or amend my booking' => 'Wie kann ich meine Buchung ändern oder stornieren',
            'Booking'                                              => 'Buchung',
            'statusVariants'                                       => 'bestätigt',
            // 'cancelledPhrases' => '',
        ],
    ];

    private $subjects = [
        'en' => [
            'Your booking has been confirmed',
            'Your booking has been cancelled',
            'Your booking has been canceled',
        ],
        'de' => ['Premier Inn Buchung -'],
    ];

    private $detectors = [
        'en' => [
            'find your booking details below',
            'your upcoming stay has been cancelled',
            'your upcoming stay has been canceled',
            'Sit back, kick off your shoes and rest easy',
            'We’re glad you’ve decided to book with us. Sit back, relax and rest easy – you’re all booked in!',
            'We’re chuffed you’ve decided to stay with us',
            'Thanks for booking at hub by Premier Inn. You’re all booked in ',
            'in all our Premier Inn hotels in the UK and Ireland',
            'All your booking info is below but if you need anything else',
            'You’ll find all your booking info below',
        ],
        'de' => ['Ihre Buchungsangaben sind weiter unten aufgeführt'],
    ];

    private $patterns = [
        'time'  => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
        'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52  |  (+351) 21 342 09 07  |  713.680.2992
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Premier Inn') !== false
            || preg_match('/[.@]premierinn\.com/i', $from) > 0;
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
            && $this->http->XPath->query('//a[contains(@href,"www.premierinn.com") or contains(@href,"service.premierinn.com") or contains(@href,".premierinn.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Premier Inn policy") or contains(.,"@picomms.premierinn.com") or contains(.,"@hubcomms.premierinn.com") or contains(., "©Premier Inn")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->text = $parser->getBodyStr();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseEmail($email);
        $email->setType('YourBooking' . ucfirst($this->lang));

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

    private function parseEmail(Email $email): void
    {
        $xpathBold = "ancestor-or-self::*[contains(@style,'bold')] or ancestor-or-self::*[self::b or self::strong or self::h2]";

        $h = $email->add()->hotel();

        $status = $this->http->FindSingleNode("//h1[{$this->starts($this->t('Booking'))}]", null, true, "/^{$this->opt($this->t('Booking'))}\s+({$this->opt($this->t('statusVariants'))})[,.;:!? ]*$/");

        if ($status) {
            $h->general()->status($status);
        }

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            $h->general()->cancelled();
        }

        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmationNumber) {
            $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmationNumber, $confirmationNumberTitle);
        }

        $cancellationNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation Reference'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');
        $h->general()->cancellationNumber($cancellationNumber, false, true);

        $travellers = [];

        $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check in'))}]/preceding::text()[string-length(normalize-space())>1][1][{$xpathBold}]");
        $h->hotel()->name($hotelName);

        $checkIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check in'))}]/following::text()[normalize-space()][1]/ancestor::*[1]", null, true, "/^(?:{$this->opt($this->t('From'))}[:\s]+)?(.{6,})$/");
        $h->booked()->checkIn2($this->normalizeDate($checkIn));

        $checkOut = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check out'))}]/following::text()[normalize-space()][1]/ancestor::*[1]", null, true, "/^(?:{$this->opt($this->t('Before'))}[:\s]+)?(.{6,})$/");
        $h->booked()->checkOut2($this->normalizeDate($checkOut));

        $adults = 0;
        $child = 0;

        /*
           Michael Smith
           Twin room, non-smoking
           2 adults
         */
        $nights = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check out'))}]/following::text()[normalize-space()][2]", null, true, "#^\s*\(\s*(\d+) \w+\s*\)\s*$#u");
        $patterns['room'] = "/"
            . "\s*(?<traveller>[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*\n+"
            . "\s*(?<roomType>.{3,}?)[ ]*\n+"
            . "\s*(?<adults>\d{1,3})[ ]*\n?{$this->opt($this->t('adult'))}\b\s*(?:\,\s*(?<child>\d+)\s*{$this->opt($this->t('child'))})?"
            . "/u";

        $rooms = $this->http->XPath->query("//text()[{$this->starts($this->t('Room details'))} or {$this->starts($this->t('Cancelled room details'))}]/ancestor-or-self::*[{$xpathBold}][1]/following::text()[normalize-space()][1]/ancestor::div[1]");

        foreach ($rooms as $root) {
            $roomText = implode("\n",
                $this->http->FindNodes('./descendant::text()[normalize-space()!=""][position()>1]', $root));

            $roomsArray = array_filter(explode("{$this->t('Room details')}", $roomText));
            $roomCount = 0;
            $rateArray = $this->http->FindNodes("//text()[{$this->eq($this->t('Room total:'))}]/ancestor::tr[1]");

            foreach ($roomsArray as $i => $roomText) {
                if (preg_match($patterns['room'], $roomText, $m)) {
                    $room = $h->addRoom();
                    $travellers[] = $m['traveller'];

                    if (!empty($m['roomType'])) {
                        $room->setType($m['roomType']);
                        $roomCount++;
                    }

                    if (isset($m['child'])) {
                        $child += $m['child'];
                    }

                    $adults += $m['adults'];

                    if (!empty($nights) && preg_match("/{$this->opt($this->t('Room total:'))}\s+(.+)/us", $rateArray[$roomCount - 1], $m)) {
                        if ($nights > 1) {
                            if (preg_match("/^\s*(\D*?)[ ]*(\d[,.‘\'\d ]*?)[ ]*(\D*?)\s*$/u", $m[1], $mat)) {
                                $currency1 = $this->normalizeCurrency($mat[1]);
                                $currency2 = $this->normalizeCurrency($mat[3]);
                                $currency = empty($currency1) ? $currency2 : $currency1;
                                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                                $value = PriceHelper::parse($mat[2], $currencyCode);

                                if (!empty($value)) {
                                    $room->setRate('Average ' . $mat[1] . round((float) ($value / $nights),
                                            0) . $mat[3]);
                                }
                            }
                        } else {
                            $room->setRate($m[1]);
                        }
                    } else {
                        if (!empty($nights) && preg_match("/{$this->opt($this->t('Room total:'))}\s+(.+)/u", $rateArray[$roomCount - 1], $m)
                            && preg_match("/^\s*(\D*?)[ ]*(\d[,.‘\'\d ]*?)[ ]*(\D*?)\s*$/u", $m[1], $mat)) {
                            $currency1 = $this->normalizeCurrency($mat[1]);
                            $currency2 = $this->normalizeCurrency($mat[3]);
                            $currency = empty($currency1) ? $currency2 : $currency1;
                            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                            $value = PriceHelper::parse($mat[2], $currencyCode);

                            if (!empty($value)) {
                                $room->setRate((($nights > 1) ? 'Average ' : '') . $mat[1] . round((float) ($value / $nights), 0) . $mat[3]);
                            }
                        }
                    }
                }
            }
        }

        if ($roomCount > 0) {
            $h->booked()
                ->rooms($roomCount);
        }

        if (count($travellers)) {
            $h->general()->travellers(array_unique($travellers));
        }

        if ($adults > 0) {
            $h->booked()->guests($adults);
        }

        if ($child > 0) {
            $h->booked()->kids($child);
        }

        $payment = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total price'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        if (preg_match('/^(?<currency>[^\d)(]+)\s*(?<amount>\d[,.\'\d]*)/', $payment, $matches)) {
            // £ 125.00
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $h->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        if (!empty($h->getHotelName())) {
            $hotelNameVariants = [$h->getHotelName(), preg_replace('/^Premier Inn\s+(.{3,})$/i', '$1', $h->getHotelName())];
            $xpathAddress = "//tr[ count(*)=2 and *[1]/descendant-or-self::*[{$xpathBold}][{$this->contains($hotelNameVariants)}] and *[2][normalize-space()=''] ]";

            $address = $this->http->FindSingleNode($xpathAddress . "/*[1]/descendant::tr[ not(.//tr) and normalize-space() and preceding::*[{$xpathBold}][{$this->contains($hotelNameVariants)}] ]");

            if (empty($address)) {
                $address = $this->re("/Glasgow \(Milngavie\)\s*\n+(.+)\n[<]https\:\/\/www\.google\.com\/maps\/search/", $this->text);
            }

            if (empty($address)) {
                $address = $this->http->FindSingleNode("//*[{$this->eq($hotelNameVariants)}][{$xpathBold}]/following::text()[normalize-space()][1]/ancestor::*[position()<4][preceding-sibling::*[not(normalize-space()) and .//img[contains(@src, '.gif')]]][not(.//text()[{$this->eq($hotelNameVariants)}])]");
            }

            if (empty($address)) {
                $address = $this->http->FindSingleNode("//*[{$this->eq($hotelNameVariants)}][{$xpathBold}]/following::text()[normalize-space()][1]/ancestor::*[position()<4][preceding-sibling::*[not(normalize-space())]][not(.//text()[{$this->eq($hotelNameVariants)}])][following::text()[normalize-space()][1][{$this->starts($this->t('Telephone:'))}]]");
            }

            if (empty($address)) {
                $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel details'))}]/following::text()[normalize-space()][1][{$this->eq($hotelNameVariants)}][{$xpathBold}]/following::text()[normalize-space()][1][following::text()[normalize-space()][1][{$this->starts($this->t('Telephone:'))}]]");
            }

            if (empty($address) && !$this->http->FindSingleNode("//text()[{$this->starts($this->t('Telephone:'))}]")) {
                $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel details'))}]/following::text()[normalize-space()][1][{$this->eq($hotelNameVariants)}][{$xpathBold}]/following::text()[normalize-space()][1]");
            }

            $phone = $this->http->FindSingleNode($xpathAddress . "/ancestor::table[1]/following::table[normalize-space()][1][{$this->contains($this->t('Telephone:'))}]", null, true, "/{$this->opt($this->t('Telephone:'))}\s*({$this->patterns['phone']})/")
                ?? $this->http->FindSingleNode($xpathAddress . "/descendant::text()[{$this->contains($this->t('Telephone:'))}]", null, true, "/{$this->opt($this->t('Telephone:'))}\s*({$this->patterns['phone']})$/")
                ?? $this->http->FindSingleNode($xpathAddress . "/following::text()[normalize-space()][1][{$this->contains($this->t('Telephone:'))}]", null, true, "/{$this->opt($this->t('Telephone:'))}\s*({$this->patterns['phone']})/")
                ?? $this->re("/Telephone\s*:\s*({$this->patterns['phone']})/", $this->text)
            ;

            $h->hotel()->address($address)->phone($phone, false, true);
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('What happens if I need to cancel or amend my booking'))}]/following::text()[string-length(normalize-space())>1][1][not(ancestor::*[{$xpathBold}])]/ancestor::*[1]")
            ?? $this->http->FindSingleNode("//text()[{$this->starts('Your booking can be cancel')}]/ancestor::p[1]")
        ;

        if ($cancellation) {
            $h->general()->cancellation($cancellation);
            $this->detectDeadLine($h);
        }
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellation = $h->getCancellation())) {
            return;
        }

        if (preg_match("/(?:You can amend your booking .+ )?You must do this before (?<hour>{$this->patterns['time']}) on the day of your arrival/i", $cancellation, $m)
            || preg_match("/You can amend your booking on-line by logging into My Premier Inn or by selecting View, Amend or Cancel booking. You must do this before (?<hour>{$this->patterns['time']}) on the day of your arrival /i", $cancellation, $m)
            || preg_match("/^Your booking can be cancell?ed up until (?<hour>{$this->patterns['time']}) on the day of your arrival\s*(?:[.;!]|$)/i", $cancellation, $m)
        ) {
            $h->booked()->deadlineRelative('0 days', $m['hour']);
        } elseif (
               preg_match('/Please note you have booked a reservation that can only be amended up to \d+ days and\/or cancelled up to (\d+) days from your arrival./i', $cancellation, $m)
            || preg_match('/Please note you have booked a reservation that can only be cancelled up to (\d+) days prior to arrival./i', $cancellation, $m)
        ) {
            $h->booked()->deadlineRelative($m[1] . ' days');
        } elseif (preg_match('/Sie können diese Buchung bis (?<hour>\d+) Uhr am Anreisetag telefonisch ändern oder über/i', $cancellation, $m)) {
            $h->booked()->deadlineRelative('0 days', $m['hour'] . ':00');
        } else {
            $h->booked()
                ->parseNonRefundable('Please note you have booked a non-cancellable reservation.')
                ->parseNonRefundable('Please note you have booked a non-cancellable and non-amendable reservation.')
            ;
        }
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

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
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
            if (!is_string($lang) || empty($phrases['Check out']) || empty($phrases['Hotel details'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Check out'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Hotel details'])}]")->length > 0
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

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate(?string $text): string
    {
        if (empty($text)) {
            return '';
        }
        $text = str_ireplace(['&8203;', '​'], '', $text);
//        $this->logger->debug('$text = '.print_r( $text,true));

        $in = [
            // 12pm - Wed 5 Jun 2019
            "/^\s*({$this->patterns['time']})\s+-\s+[-[:alpha:]]+\s+(\d{1,2}\s+[[:alpha:]]{3,}\s+\d{2,4})\s*$/u",
            // Mon 12 Jun 2023 from 12pm  |  Mon 12 Jun 2023 - before 12pm
            "/^\s*[-[:alpha:]]+\s+(\d{1,2}\s+[[:alpha:]]{3,}\s+\d{2,4})\s+(?:-\s+)?(?:[[:alpha:] ]+\s+)?({$this->patterns['time']})\s*$/u",
            // 14 Uhr - Fr 6 August 2021
            "/^\s*(\d{1,2})\s*Uhr\s+-\s+[-[:alpha:]]+\s+(\d{1,2}\s*[[:alpha:]]+\s*\d{4})\s*$/iu",
        ];
        $out = [
            '$2, $1',
            '$1, $2',
            '$2, $1:00',
        ];
        $text = preg_replace($in, $out, $text);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $text, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $text = str_replace($m[1], $en, $text);
            }
        }

        return $text;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
