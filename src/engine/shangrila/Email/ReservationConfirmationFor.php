<?php

namespace AwardWallet\Engine\shangrila\Email;

// use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmationFor extends \TAccountCheckerExtended
{
    public $mailFiles = "shangrila/it-121951980.eml, shangrila/it-163882188.eml, shangrila/it-2085055.eml, shangrila/it-448318864.eml, shangrila/it-57471927.eml"; // +2 bcdtravel(html)[en]

    public $reSubject = [
        'en' => ['Reservation Confirmation for', 'Reservation confirmation at'],
    ];

    public $lang = '';

    public $subject;

    public $langDetectors = [
        'en' => ['Check Out Date', 'Departure Date'],
    ];

    public static $dictionary = [
        'en' => [
            'Confirmation Number' => ['Confirmation', 'Confirmation No', 'Confirmation Number', 'Booking Number'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Shangri-La Hotel') !== false
            || stripos($from, '@shangri-la.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Shangri-La Hotel") or contains(normalize-space(.),"Shangri-la hotel") or contains(.,"www.shangri-la.com") or contains(.,"@shangri-la.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.shangri-la.com") or contains(@href,"@shangri-la.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }

        $this->subject = $parser->getSubject();

        $this->parseEmail($email);
        $email->setType('ReservationConfirmationFor' . ucfirst($this->lang));

        return $email;
    }

    protected function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    protected function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    /* public function ParsePlanEmail(\PlancakeEmailParser $parser)
     {
         if ($this->assignLang() === false) {
             $this->logger->notice("Can't determine a language!");
             return false;
         }

         $this->subject = $parser->getSubject();
 //        $result = parent::ParsePlanEmail($parser);
         $result = $this->parserEmail($parser);
         $result['emailType'] = 'ReservationConfirmationFor' . ucfirst($this->lang);
         return $result;
     }*/

    private function parseEmail(Email $email): void
    {
        $xpath = "//text()[{$this->eq($this->t('Confirmation Number'))}]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = "//text()[{$this->starts($this->t('Confirmation Number'))}]/following::text()[normalize-space()][1]/ancestor::table[{$this->contains($this->t("Departure Date"))}][1][not(.//text()[{$this->starts($this->t('Confirmation Number'))}])]";
            $nodes = $this->http->XPath->query($xpath);
        }

        foreach ($nodes as $mainRoot) {
            $h = $email->add()->hotel();

            // General
            $conf = $this->nextTd(['Confirmation', 'Confirmation No', 'Confirmation Number', 'Booking Number'], $mainRoot, "#^\s*(\d{5,})\s*$#");

            if (empty($conf)) {
                $conf = $this->http->FindSingleNode("preceding::text()[normalize-space()][1]", $mainRoot, true, "/{$this->opt($this->t('Confirmation Number'))}:?\s*(\d{5,})\s*$/");
            }
            $h->general()
                ->confirmation($conf);
            $guestNameText = $this->nextTd(['Guest Name', 'Guest'], $mainRoot);
            $guestNameText = preg_replace("/^\s*Mr\.\/Ms\.\s*/", '', $guestNameText);
            $names = array_filter(array_map('trim', explode("/", $guestNameText)));
            $h->general()->travellers($names);

            if ($this->http->FindSingleNode("./preceding::text()[" . $this->eq("Reservation Confirmation") . "]", $mainRoot)) {
                $h->general()->status('confirmed');
            } elseif ($this->http->FindSingleNode("./descendant::text()[" . $this->starts("Reservation Confirmation") . "]", $mainRoot)) {
                $h->general()->status('confirmed');
            }

            if ($this->http->XPath->query("//text()[normalize-space()='Reservation Cancellation']")->length > 0) {
                $h->general()
                    ->status('cancelled')
                    ->cancelled();
            }

            /*$cancellationPolicy = '';
            $cancellationPolicyNodes = $this->http->XPath->query('./following::text()[ ./preceding::text()[normalize-space(.)="Cancellation Policy"] and ./following::text()[normalize-space(.)="Check-In/Out Time"] ][normalize-space(.)]', $mainRoot);
            foreach ($cancellationPolicyNodes as $root) {
                if ($this->http->XPath->query('./ancestor::*[self::b or self::strong]', $root)->length > 0) {
                    $cancellationPolicy = '';
                    break;
                } else {
                    $cancellationPolicy .= $this->http->FindSingleNode('.', $root);
                }
            }
            */
            $cancellationPolicy = $this->http->FindSingleNode('./following::text()[normalize-space()="Cancellation Policy"][1]/following::text()[normalize-space()][1]', $mainRoot);

            if (empty($cancellationPolicy)) {
                $cancellationPolicy = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number'))}]/following::text()[starts-with(normalize-space(), 'Cancellation Policy')][1]/following::text()[normalize-space()][string-length()>3][1]/ancestor::tr[1]",
                    null, true, "/^(?:\s*Cancellation Policy\s*)?(.+)/");
            }

            if (!empty($cancellationPolicy)) {
                $h->general()->cancellation($cancellationPolicy, false, true);
            }

            // Program
            $account = $this->nextTd(['Shangri-La Circle Membership', 'Golden Circle', 'Golden Circle Member', 'Golden Circle Membership Number', 'Golden Circle Number'], $mainRoot, "#\b(\d[-X\d\/ ]{10,}[X\d])\b#i");

            if (!empty($account)) {
                $h->program()
                    ->account($account, (preg_match("#x{3}#i", $account)) ? true : false);
            }

            // Price
            $total = $this->nextTd(['Total Charge'], $mainRoot);

            if (empty($total)) {
                $total = $this->nextTd(['Total Charges', 'Total Cash to Pay'], $mainRoot);
            }
            $currency = null;

            if (preg_match("#^\s*(?<curr>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[A-Z]{3})\s*$#", $total, $m)) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $m['curr']) ? $m['curr'] : null;
                $h->price()->currency($m['curr'])->total(PriceHelper::parse($m['amount'], $currencyCode));
                $currency = $m['curr'];
            }

            $cost = $this->nextTd(['Room Charges'], $mainRoot);

            if (preg_match("#^\s*(?<curr>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $cost, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[A-Z]{3})\s*$#", $cost, $m)) {
                if (empty($currency) || $m['curr'] === $currency) {
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $m['curr']) ? $m['curr'] : null;
                    $h->price()->currency($m['curr'])->cost(PriceHelper::parse($m['amount'], $currencyCode));
                    $currency = $m['curr'];
                }
            }
            $tax = $this->nextTd(['Service Charge and Tax(if applicable)', 'Service Charge and Tax', 'Service Charges and Tax'], $mainRoot);

            if (empty($tax)) {
                $tax = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Service Charge and Tax')]/ancestor::tr[1]/descendant::td[last()]");
            }

            if (preg_match("#^\s*(?<curr>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $tax, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[A-Z]{3})\s*$#", $tax, $m)) {
                if (empty($currency) || $m['curr'] === $currency) {
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $m['curr']) ? $m['curr'] : null;
                    $h->price()->currency($m['curr'])->tax(PriceHelper::parse($m['amount'], $currencyCode));
                }
            }

            $spentAwards = $this->nextTd(['Total Points Redeemed'], $mainRoot);

            if (!empty($spentAwards)) {
                $h->price()
                    ->spentAwards($spentAwards);
            }

            // Hotel
            $hotelName = null;
            $hotelContacts = $this->http->FindSingleNode("(//a[contains(@href,'//www.shangri-la.com/') or contains(@href,'www.shangri-')]/descendant::img/@alt)[1][not(contains(normalize-space(), 'Image'))]", $mainRoot);

            if (!preg_match("/Shangri/", $hotelContacts)) {
                $hotelContacts = null;
            }

            if (!$hotelContacts) {
                $hotelName = $this->re("/Online Reservation Confirmation\s*:\s*(.+)\s+-\s+.+, \d+/i", $this->subject)
                    ?? $this->re("/Your Reservation at\s+(.{2,50}?)\s+has been (?:confirmed|cancell?ed)【/i", $this->subject)
                    ?? $this->re("/Your Reservation at\s+(.{2,50}?)\s+requires your payment【/i", $this->subject)
                ;

                if (empty($hotelName)) {
                    $hotelName = $this->http->FindSingleNode("//text()[normalize-space()='Resort']/ancestor::tr[1]/descendant::td[2]");
                }

                if (empty($hotelName)) {
                    $hotelName = $this->http->FindSingleNode("./preceding::text()[contains(normalize-space(),'Thank you for your reservation at')][1]",
                        $mainRoot, true, "/Thank you for your reservation at\s+(.*?)\./u");
                }

                if (empty($hotelName) && preg_match("#Reservation confirmation at (.+?) for #", $this->subject, $m)) {
                    $hotelName = $m[1];
                }

                /* WTF?
                if ($hotelName) {
                    $hotelName = preg_replace('/^([^,]{3,})\s*,\s*[^,]+$/', '$1', $hotelName);
                }
                */

                if (empty($hotelName)) {
                    $hotelName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'General Phone')]/ancestor::table[1]/descendant::text()[normalize-space()][1]");
                }

                $hotelContactsTexts = [];

                if ($hotelName) {
                    $hotelContactsTexts = $this->http->FindNodes(
                        '//td[' . $this->eq('Hotel Details') . ']/following::*[normalize-space()][1]/descendant::text()[' . $this->contains($hotelName) . ']['
                        . './/text()[starts-with(normalize-space(),"t:") or starts-with(normalize-space(),"ph-") or starts-with(normalize-space(),"phone")]'
                        . '][last()]/descendant::text()[normalize-space(.)]'
                    );

                    if (empty($hotelContactsTexts)) {
                        $hotelContactsTexts = $this->http->FindNodes(
                            "./following::text()[starts-with(normalize-space(), 'T:') or starts-with(normalize-space(), 't:')][1]/ancestor::table[1]", $mainRoot
                        );
                    }

                    if (empty($hotelContactsTexts)) {
                        $hotelContactsTexts = $this->http->FindNodes(
                            "//text()[normalize-space()='Confirmation Number']/following::text()[starts-with(normalize-space(), 'Shangri-La Hotel')]/ancestor::tr[1]/descendant::text()[normalize-space()]"
                        );
                    }

                    if (empty($hotelContactsTexts)) {
                        $hotelContactsTexts = $this->http->FindNodes(
                            "//text()[{$this->contains($hotelName)}]/following::text()[starts-with(normalize-space(), 'General Phone')]/ancestor::table[1]");
                    }
                }

                if (empty($hotelContactsTexts) && $hotelName) {
                    $hotelContactsTexts = $this->http->FindNodes('descendant::text()[' . $this->contains($hotelName) . '][last()]/ancestor::*[self::td or self::th or self::div][1][not(contains(normalize-space(),"Thank you for your reservation"))]/descendant::text()[normalize-space()]', $mainRoot);
                }

                if (!empty($hotelContactsTexts)) {
                    $hotelContactsTexts[0] .= '.';
                }
                $hotelContacts = implode(' ', $hotelContactsTexts);
            }

            $patterns['phone'] = '[+(\d][-. \d)(]{5,}[\d)]';

            if (empty($hotelName)) {
                $regexp = "(?<name>.+?)[.]\s*(?<addr>.+?)\s*(?:ph-|phone|t:)\s*(?P<tel>{$patterns['phone']})(?:\s*\|\s*f:\s*(?<fax>{$patterns['phone']}))?";
            } else {
                $regexp = "(?<name>{$this->opt($hotelName)}),?\s*(?<addr>.+?)\s*(?:ph-|phone|t:)\s*(?P<tel>{$patterns['phone']})(?:\s*\|\s*f:\s*(?<fax>{$patterns['phone']}))?";
                $regexp2 = "(?<name>{$this->opt($hotelName)}),?\s*(?<addr>.+?)\s*{$this->opt('General Phone')}\s*:\s*(?P<tel>{$patterns['phone']})(?:\s*\|\s*{$this->opt('General Fax')}\s*:\s*(?<fax>{$patterns['phone']}))?";
                $regexp3 = "^(?<name>.+)\s+\–\s*Booking Confirmation";
            }

            $hotelContacts = str_replace('&nbsp', '', $hotelContacts);

            if (preg_match("/$regexp/isu", $hotelContacts, $ms)
                || preg_match("/$regexp2/", $hotelContacts, $ms)
                || preg_match("/$regexp3/", $hotelContacts, $ms)
            ) {
                $h->hotel()
                    ->name(preg_replace("#\s+#", ' ', trim($ms['name'])));

                if (isset($ms['addr'])) {
                    $h->hotel()
                        ->address(preg_replace("#\s+#", ' ', trim($ms['addr'])));
                } else {
                    $h->hotel()
                        ->noAddress();
                }

                if (isset($ms['tel'])) {
                    $h->hotel()
                        ->phone(preg_replace("#\s+#", '', trim($ms['tel'])));
                }

                if (!empty($ms['fax'])) {
                    $h->hotel()->fax(preg_replace('/\s+/', '', $ms['fax']));
                }
            } elseif ($hotelContacts) {
                $h->hotel()
                    ->name($hotelContacts)
                    ->noAddress();
            } else {
                $hotelName = $hotelName ?? $this->http->FindSingleNode("preceding::text()[contains(normalize-space(),'Thank you for choosing to stay at')][1]", $mainRoot, true, "/Thank you for choosing to stay at\s+(.{2,50}?)\./");

                if ($hotelName) {
                    $h->hotel()->name($hotelName)->noAddress();
                }
            }

            $patterns['time'] = '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?'; // 4:19PM    |    2:00 p. m.    |    3pm

            // Booked
            $checkIn = $this->normalizeDate($this->nextTd(['Check In Date', 'Arrival Date'], $mainRoot));

            if (!empty($checkIn)) {
                $time = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Arrival Details'))}] ]/*[normalize-space()][2]", $mainRoot, true, "/\bAt\s+({$patterns['time']})/i");

                $checkInTimeVariants = ['Check-in is at', 'check-in time is', 'check in time is', 'Check-in Time is'];

                if (empty($time)) {
                    $time = str_replace('.', '', $this->http->FindSingleNode('following::text()[' . $this->contains($checkInTimeVariants) . '][1]', $mainRoot, true, '/' . $this->opt($checkInTimeVariants) . '\s*(' . $patterns['time'] . ')/i'));
                }

                if (empty($time)) {
                    $time = str_replace('.', '', $this->http->FindSingleNode('./descendant::text()[' . $this->contains($checkInTimeVariants) . '][1]', $mainRoot, true,
                        '/' . $this->opt($checkInTimeVariants) . '\s*(' . $patterns['time'] . ')/i'));
                }

                if (!empty($time)) {
                    $h->booked()->checkIn(strtotime($time, $checkIn));
                } else {
                    $h->booked()->checkIn($checkIn);
                }
            }

            $checkOut = $this->normalizeDate($this->nextTd(['Check Out Date', 'Departure Date'], $mainRoot));

            if (!empty($checkOut)) {
                $time = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Departure Details'))}] ]/*[normalize-space()][2]", $mainRoot, true, "/\bAt\s+({$patterns['time']})/i");

                $checkOutTimeVariants = ['Check out is', 'Check-out is at', 'check-out time is', 'check out time is', 'Check-out Time is'];

                if (empty($time)) {
                    $time = str_replace('.', '', $this->http->FindSingleNode('following::text()[' . $this->contains($checkOutTimeVariants) . '][1]', $mainRoot, true, '/' . $this->opt($checkOutTimeVariants) . '\s*(' . $patterns['time'] . ')/i'));
                }

                if (empty($time)) {
                    $time = str_replace('.', '', $this->http->FindSingleNode('./descendant::text()[' . $this->contains($checkOutTimeVariants) . '][1]', $mainRoot, true,
                        '/' . $this->opt($checkOutTimeVariants) . '\s*(' . $patterns['time'] . ')/i'));
                }

                if (!empty($time)) {
                    $h->booked()->checkOut(strtotime($time, $checkOut));
                } else {
                    $h->booked()->checkOut($checkOut);
                }
            }

            $guests = $this->nextTd(['No of Persons', 'Number of Guests', 'Number of Guest(s)'], $mainRoot);
            $h->booked()
                ->guests($this->re('#(\d+)\s*Adult#', $guests), true, true)
                ->kids($this->re('#(\d+)\s*Child#', $guests), true, true);

            // Room
            $roomsType = $this->nextTd(['Room Type'], $mainRoot);

            if (strlen($roomsType) >= 250 && stripos($roomsType, '.') !== false) {
                $roomsType = $this->re("/^(.+)\./", $roomsType);
            }

            if (preg_match("/^\s*(\d{1,2})(?: x| ×)? (.+)/isu", $roomsType, $ms)) {
                $h->booked()->rooms($ms[1]);
                $r = $h->addRoom();

                if (preg_match('/^(.+)\s*(Personalized check.+)/su', $ms[2], $match)) {
                    $ms[2] = $match[1];
                }
                $r->setType($ms[2]);
            } elseif (!empty($roomsType)) {
                $r = $h->addRoom();
                $r->setType($roomsType);
            }

            $rateInfo = $this->nextTd(['Daily Rate'], $mainRoot);

            if (
                preg_match_all('/\b(?:\d{1,2}\/\d{1,2}\/\d{2,4})\s+(?<amount>\d[,.\d\s]*)(?<currency>[A-Z]{3})(?:[^A-z]|\b)/', $rateInfo, $rateMatches) // example: 22/07/18 - 22/07/18 170.00 USD
                || preg_match_all('/-\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\d\s]*)\s/i', $rateInfo, $rateMatches) // example: From Nov 05 - PHP 12,700.00
                || preg_match_all('/-\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\d\s]*)(?:\D|\b)/i', $rateInfo, $rateMatches) // example: From Nov 05 - PHP 12,700.00
            ) {
                if (count(array_unique($rateMatches['currency'])) === 1) {
                    /*$rateMatches['amount'] = array_map(['tris', 'amount']
//                function ($item) {
//                    return PriceHelper::parse($item);
//                }
                        , $rateMatches['amount']);*/

                    $rateMin = min($rateMatches['amount']);
                    $rateMax = max($rateMatches['amount']);

                    if (!isset($r)) {
                        $r = $h->addRoom();
                    }

                    if ($rateMin === $rateMax) {
                        $r->setRate($rateMatches['amount'][0] . ' ' . $rateMatches['currency'][0] . ' / night');
                    } else {
                        $r->setRate($rateMin . '-' . $rateMax . ' ' . $rateMatches['currency'][0] . ' / night');
                    }
                }
            } elseif (preg_match('#(.+?) per room#', $rateInfo, $m)) {
                if (!isset($r)) {
                    $r = $h->addRoom();
                }
                $r->setRate(trim($m[1], ' /'));
            } elseif (!empty($rateInfo)) {
                if (!isset($r)) {
                    $r = $h->addRoom();
                }
                $r->setRate(trim($rateInfo, ' /'));
            }

            if ($cancellationPolicy) {
                if (preg_match("/Room cancell?ed after\s*(?<date>\d{1,2}\s*[[:alpha:]]+\s*\d{4})\s*(?<time>{$patterns['time']})(?:\s+local(?: hotel)? time)?\s+will be charged/u", $cancellationPolicy, $m)
                    || preg_match("/Room cancelled after\s+(?<time>{$patterns['time']})(?:\s+local(?: hotel)? time)?\s+on\s+(?<date>\d{1,2}\s*[[:alpha:]]+\s*\d{4})\s+will be subject to \d+ night\(s\) room charge/", $cancellationPolicy, $m)
                ) {
                    $h->booked()->deadline(strtotime($m['time'], strtotime($m['date'])));
                }
            }
        }
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

    private function nextTd($field, $root = null, $regexp = null)
    {
        return $this->http->FindSingleNode(".//td[{$this->eq($field)} and not(.//td)]/following-sibling::td[normalize-space()][1]", $root, true, $regexp);
    }

    private function normalizeDate($str)
    {
        //$this->logger->error($str);
        if (empty($str)) {
            return false;
        }
        $in = [
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})$#", // Sunday, April 28, 2013
            "#^(\d+)/(\d+)/(\d{2})$#", // 06/05/17
        ];
        $out = [
            "$2 $1 $3",
            "$1.$2.20$3",
        ];
        $str = preg_replace($in, $out, $str);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
//                $str = str_replace($m[1], $en, $str);
//            }
//        }
        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
