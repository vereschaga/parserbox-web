<?php

namespace AwardWallet\Engine\preferred\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservConfirmation extends \TAccountChecker
{
    public $mailFiles = "preferred/it-3024718.eml, preferred/it-45197533.eml, preferred/it-86633090.eml";

    public $reBody = [
        'en' => ['Toll-Free Number', 'Toll Free Number', 'Reservation Inclusions'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'RESERVATION INFORMATION' => ['RESERVATION INFORMATION', 'Reservation Details', 'Confirmation Details', 'CONFIRMATION DETAILS'],
            'Arrival Date'            => 'Arrival Date',
            'Confirmation Number'     => ['Confirmation Number', 'Confirmation Number:', 'Confirmation #', 'Confirmation'],
            'Check-in time'           => ['Check-in time', 'Check-In Time', 'Check-In:'],
            'Check-in out'            => ['Check-in out', 'Check-Out:', 'Check-out time', 'Check-Out Time'],
            'CONTACT INFORMATION'     => ['CONTACT INFORMATION', 'Contact Information', 'CONTACT US', 'Contact Us'],
            'Main Number'             => ['Main Number', 'Telephone Number:', 'Hotel Direct'],
            'Nightly Rate'            => ['Nightly Rate', 'Nightly Room Rate', 'Room Rate:'],
            'Room Type Requested:'    => ['Room Type Requested:', 'Room Type', 'Accommodations', 'Room Name'],
            'Cancellation'            => ['Cancellation', 'Cancelling Your Reservation'],
            'startsWords'             => [
                'We are pleased to confirm your reservation at',
                'We are delighted that you chose the',
                'We are delighted that you chose The',
                'Thank you for selecting',
                'Thank you for choosing',
                'thank you for selecting',
            ],
            'endsWords' => [
                'We are pleased to confirm',
                'for your upcoming visit',
                'We look forward',
                'we look forward',
                'and look',
                'located',
            ],
        ],
    ];

    private static $providers = [
        'preferred' => [
            'from' => ['preferredhotels.com', '-iprefer.', '.iprefer.'],
            'subj' => [
                'The Pfister Hotel',
                'Enchantment Resort',
            ],
            'body' => [
                '//a[contains(@href,"preferredhotels.com")]',
                '//a[contains(@href,".iprefer.com")]',
                'The Pfister Hotel',
                'Enchantment Resort',
            ],
        ],
        'leadinghotels' => [
            'from' => ['merrionhotel.com'],
            'subj' => [
                'The Merrion Hotel',
                'The Rittenhouse',
            ],
            'body' => [
                '//a[contains(@href,"merrionhotel.com")]',
                'The Merrion Hotel',
                'The Setai, Miami Beach',
                'The Rittenhouse',
            ],
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$providers as $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom || $bySubj) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $code = $this->getProvider($parser);

        if ($code !== null) {
            $email->setProviderCode($code);
        }

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->getProvider($parser) !== null) {
            foreach ($this->reBody as $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
                }
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

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        foreach (self::$providers as $code => $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmail(Email $email): void
    {
        $patterns = [
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // 800.635.1042    |    (+1) 305 520 6000
        ];

        $r = $email->add()->hotel();

        $r->general()
            ->confirmation($this->getField($this->t('Confirmation Number'), true))
            ->traveller($this->getField($this->t('Guest Name')), true);

        $hotelName_temp = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('startsWords'))}]/ancestor::*[{$this->contains($this->t('endsWords'))}][1])[1]",
            null, false, "/{$this->opt($this->t('startsWords'))}\s*[.,; ]*(.{3,}?)[.,; ]*\s+{$this->opt($this->t('endsWords'))}/");

        if ($hotelName_temp && $this->http->XPath->query("//text()[{$this->contains($hotelName_temp)}]")->length > 1) {
            $r->hotel()->name($hotelName_temp);
        }

        $timeIn = $this->getField($this->t('Check-in time'));

        if (empty($timeIn)) {
            $timeIn = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-in time'))}]/ancestor::tr[1]",
                null, true, "#{$this->opt($this->t('Check-in time'))}[\s:]+(.+)#");
        }
        $timeOut = $this->getField($this->t('Check-in out'));

        if (empty($timeOut)) {
            $timeOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-in out'))}]/ancestor::tr[1]",
                null, true, "#{$this->opt($this->t('Check-in out'))}[\s:]+(.+)#");
        }
        $r->booked()
            ->checkIn(strtotime($timeIn, strtotime($this->getField(["Arrival Date", "Arrival Date:"]))))
            ->checkOut(strtotime($timeOut, strtotime($this->getField(["Departure Date", "Departure Date:"]))));

        // it-3024718.eml
        $phone = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('CONTACT INFORMATION'))}])[1]/following::tr[normalize-space()!=''][1]/descendant::text()[{$this->starts($this->t('Main Number'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");

        $hotelContactsSeparator = ['/ T.', '| P:'];
        $hotelContacts = $this->http->FindSingleNode("descendant::tr[not(.//tr) and {$this->contains($hotelContactsSeparator)}][last()]");

        if (preg_match("/^(?<address>.{10,}?)[ ]*{$this->opt($hotelContactsSeparator)}[ ]*(?<phone>{$patterns['phone']})/i", $hotelContacts, $m)
            && preg_match("/\d{2}/", $m['address']) && preg_match("/[[:alpha:]]{2}/u", $m['address']) && !preg_match("/:/", $m['address'])
        ) {
            // it-45197533.eml, it-86633090.eml
            $r->hotel()->address($m['address']);

            if (!$phone) {
                $phone = $m['phone'];
            }
        } else {
            $r->hotel()->noAddress();
        }

        $phone = str_replace(".", "-", $phone);
        $r->hotel()->phone($phone);

        $str = $this->getField($this->t('Adults/Children'));

        if (!empty($str) && preg_match("#(\d+)\s*\/\s*(\d+)#", $str, $m) > 0) {
            $r->booked()
                ->guests($m[1])
                ->kids($m[2]);
        } else {
            $r->booked()
                ->guests($this->getField($this->t('Number of Guests')), false, true);
        }

        $rate = $this->getField($this->t('Nightly Rate'));
        $room = $r->addRoom();
        $room->setRate($rate);

        $str = $this->getField($this->t('Cancellation'));

        if (empty($str)) {
            $str = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Cancellation Policy'))}]/following::text()[normalize-space(.)!=''][1]");
        }
        $r->general()
            ->cancellation($str);

        $room->setType($this->getField($this->t('Room Type Requested:')));
        $room->setDescription($this->getField($this->t('Room Type Description:')), false, true);

        $sum = $this->getField($this->t('Reservation Total'));
        $sum = $this->getTotalCurrency($sum);

        if (!empty($sum['Total'])) {
            $r->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);
        }

        $this->detectDeadLine($r);
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/can be canceled without penalty until (?<time>.+?) the day prior to your scheduled arrival/ui",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative('1 day', $m['time']);

            return;
        } elseif (preg_match("/You (?i)may cancell? your reservation, however reservations cancelled within\s*(?<prior>\d{1,3}\s*hours?)\s*prior to arrival are non-refundable/", $cancellationText, $m)
            || preg_match("/Must (?i)cancell?\s*(?<prior>\d{1,3}\s*days?)\s*prior to arrival date\./", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior']);

            return;
        } elseif (preg_match("/Guest room reservations can be canceled without penalty until (?<time>.+?) (?<pDays>\d+) days prior to your scheduled arrival/i",
            $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['pDays'] . ' days', $m['time']);

            return;
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
        foreach (self::$dict as $lang => $words) {
            if (isset($words['RESERVATION INFORMATION'], $words['Arrival Date'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['RESERVATION INFORMATION'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Arrival Date'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "$", "₹"], ["EUR", "GBP", "USD", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
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

    private function getField($field, $equal = false)
    {
        if ($equal) {
            return $this->http->FindSingleNode("(//text()[{$this->eq($field)}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1])[1]");
        } else {
            return $this->http->FindSingleNode("(//text()[{$this->starts($field)}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1])[1]");
        }
    }
}
