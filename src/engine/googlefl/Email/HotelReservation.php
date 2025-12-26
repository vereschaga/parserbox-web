<?php

namespace AwardWallet\Engine\googlefl\Email;

use AwardWallet\Schema\Parser\Email\Email;

class HotelReservation extends \TAccountChecker
{
    public $mailFiles = "googlefl/it-67436183.eml, googlefl/it-67964315.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Confirmation code', 'Reservation number'],
            'checkIn'    => ['Check in', 'Check In', 'Check-in', 'Check-In'],
            'checkOut'   => ['Check out', 'Check Out', 'Check-out', 'Check-Out'],
        ],
    ];

    private $subjects = [
        'en' => ['Hotel Reservation Confirmation'],
    ];

    private $detectors = [
        'en' => ['Thanks for booking on', 'Access your reservation'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'travel-support@google.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//img[contains(@src,"//www.gstatic.com/images/branding/googlelogo/") and contains(@src,"/googlelogo")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Thanks for booking on Google")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('HotelReservation' . ucfirst($this->lang));

        $this->parseHotel($email);

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

    private function parseHotel(Email $email): void
    {
        $email->ota(); // because Google is not hotel company

        $h = $email->add()->hotel();

        $xpathImg = "contains(@src,'//www.gstatic.com/travel-hotels/branding/icon_default.')";
        $xpathHeader = "//*[ tr[normalize-space()][1][not(.//tr)] and tr[normalize-space()][2] and tr/descendant::img[{$xpathImg}] ]";

        $hotelName = $this->http->FindSingleNode($xpathHeader . '/tr[normalize-space()][1]');
        $h->hotel()->name($hotelName);

        $address = $this->http->FindSingleNode($xpathHeader . "/tr[normalize-space()][2][ following-sibling::tr[normalize-space()][1]/descendant::img[{$xpathImg}] ]");

        if ($address) {
            $h->hotel()->address($address);
        } elseif ($this->http->FindSingleNode($xpathHeader . "/tr[normalize-space()][2]/descendant::img[{$xpathImg}]") !== null) {
            $h->hotel()->noAddress();
        }

        $phone = $this->http->FindSingleNode($xpathHeader . "/tr/descendant-or-self::tr[not(.//tr) and {$this->starts($this->t('Phone'))}]", null, true, "/^{$this->opt($this->t('Phone'))}[:•\s]*([+(\d][-. \d)(]{5,}[\d)])$/u");
        $h->hotel()->phone($phone, false, true);

        $guestName = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Guest name'))}]/following-sibling::*[normalize-space()]", null, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
        $h->general()->traveller($guestName);

        $guestCount = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Number of guests'))}]/following-sibling::*[normalize-space()]", null, true, '/^(\d{1,3})\b/');
        if (empty($guestCount)) {
            $guestCount = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Number of adults'))}]/following-sibling::*[normalize-space()]",
                null, true, '/^(\d{1,3})\b/');
        }
        $h->booked()->guests($guestCount);

        $kidsCount = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Number of children'))}]/following-sibling::*[normalize-space()]",
                null, true, '/^(\d{1,3})\b/');
        $h->booked()->kids($kidsCount, true, true);

        $checkIn = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('checkIn'))}]/following-sibling::*[normalize-space()]", null, true, '/^.{6,}$/');
        $checkOut = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('checkOut'))}]/following-sibling::*[normalize-space()]", null, true, '/^.{6,}$/');

        $h->booked()
            ->checkIn2($checkIn)
            ->checkOut2($checkOut);

        $confirmation = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('confNumber'))}]/following-sibling::*[normalize-space()]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr/*[ {$this->eq($this->t('confNumber'))} and following-sibling::*[normalize-space()] ]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $roomType = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Room type'))}]/following-sibling::*[normalize-space()]");
        $roomRate = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Nightly rate'))}]/following-sibling::*[normalize-space()]", null, true, '/^.*\d.*$/');

        $room = $h->addRoom();

        $room->setType($roomType)
            ->setRate($roomRate);

        $totalPrice = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Total'))}]/following-sibling::*[normalize-space()]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)) {
            // $958.58
            $h->price()
                ->currency($m['currency'])
                ->total($this->normalizeAmount($m['amount']));

            $m['currency'] = trim($m['currency']);
            $baseFare = $this->http->FindSingleNode("//tr/*[contains(.,'×') and {$this->contains($this->t('night'))}]/following-sibling::*[normalize-space()]");

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $baseFare, $matches)) {
                $h->price()->cost($this->normalizeAmount($matches['amount']));
            }

            $tax = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Taxes + fees'))}]/following-sibling::*[normalize-space()]");

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $tax, $matches)) {
                $h->price()->tax($this->normalizeAmount($matches['amount']));
            }
        }

        $cancellation = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Cancellation policy'))}]/following-sibling::tr[normalize-space()]");
        $h->general()->cancellation($cancellation);

        if ($h->getCheckInDate() && $cancellation) {
            if (preg_match("/^Reservations (?i)must be cancell?ed (?<prior>one weeks?) prior to arrival to avoid a penalty of the full amount of stay\./", $cancellation, $m) // en
            ) {
                $h->booked()->deadlineRelative('1 weeks');
            }
            elseif (preg_match("/^FREE cancellation before (?<date>.+?) - (?<time>\d{1,2}:\d{2}(?:\s*[ap]m))\b/i", $cancellation, $m) // en
            ) {
                $h->booked()->deadline(strtotime($m['date'].','.$m['time']));
            }
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
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['checkIn'])}]")->length > 0
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

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
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
