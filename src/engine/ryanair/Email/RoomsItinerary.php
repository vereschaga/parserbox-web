<?php

namespace AwardWallet\Engine\ryanair\Email;

use AwardWallet\Schema\Parser\Email\Email;

class RoomsItinerary extends \TAccountChecker
{
    public $mailFiles = "ryanair/it-61298778.eml";

    public $lang = '';

    public static $dictionary = [
        'nl' => [
            'confNumber'         => ['Reserveringsnummer'],
            'checkIn'            => ['Inchecken'],
            'checkOut'           => 'Uitchecken',
            'roomType'           => 'Kamertype',
            'guestName'          => 'Hoofdgast',
            'receipt'            => 'Ontvangstbewijs',
            'total'              => 'Totaal',
            'cancellationPolicy' => 'Annuleringsbeleid',
        ],
    ];

    private $subjects = [
        'nl' => ['Rooms Itinerary'],
    ];

    private $detectors = [
        'nl' => ['Je boeking beheren'],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Ryanair Marketing') !== false
            || stripos($from, '@ryanairemail.com') !== false;
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
        if ($this->http->XPath->query('//a[contains(@href,".ryanairemails.com/") or contains(@href,"service.ryanairemails.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(.,"@ryanairemail.com")]')->length === 0
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

        $this->parseHotel($email);
        $email->setType('RoomsItinerary' . ucfirst($this->lang));

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
        $h = $email->add()->hotel();

        $xpathAddress = "//td[ not(.//td) and descendant::img[contains(@src,'/city.')] ]/following-sibling::td[string-length(normalize-space())>2]";

        $hotelName_temp = $this->http->FindSingleNode($xpathAddress . "/preceding::tr[string-length(normalize-space())>2][1]");

        if ($this->http->XPath->query("//text()[{$this->contains($hotelName_temp)}]")->length > 1) {
            $h->hotel()->name($hotelName_temp);
        }

        $address = $this->http->FindSingleNode($xpathAddress);
        $phone = $this->http->FindSingleNode("//td[ not(.//td) and descendant::img[contains(@src,'/call.')] ]/following-sibling::td[string-length(normalize-space())>2]", null, true, '/^[+(\d][-. \d)(]{5,}[\d)]$/');
        $h->hotel()
            ->address($address)
            ->phone($phone);

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:]*$/');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $dateCheckIn = $this->http->FindSingleNode("//*[{$this->eq($this->t('checkIn'))}]/following-sibling::node()[normalize-space()]");
        $dateCheckOut = $this->http->FindSingleNode("//*[{$this->eq($this->t('checkOut'))}]/following-sibling::node()[normalize-space()]");
        $h->booked()
            ->checkIn2($dateCheckIn)
            ->checkOut2($dateCheckOut);

        $room = $h->addRoom();

        $roomType = $this->http->FindSingleNode("//*[{$this->eq($this->t('roomType'))}]/following-sibling::node()[normalize-space()][1][not({$this->contains($this->t('guestName'))})]");
        $room->setType($roomType);

        $guestName = $this->http->FindSingleNode("//*[{$this->eq($this->t('guestName'))}]/following-sibling::node()[normalize-space()]", null, true, '/^([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*(?:\(|$)/');
        $h->general()->traveller($guestName);

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('receipt'))}]/following::text()[{$this->starts($this->t('total'))}][1]", null, true, "/^{$this->opt($this->t('total'))}\s+(.*\d.*)$/");

        if (preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+)$/', $totalPrice, $m)) {
            // 302.00EUR
            $h->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($m['currency']);
        }

        $cancellation = $this->http->FindSingleNode("//tr[{$this->eq($this->t('cancellationPolicy'))}]/following-sibling::tr[normalize-space()]");

        if ($cancellation && !empty($roomType) && preg_match("/{$this->opt($roomType)}\s*[:]+\s*(.+)/", $cancellation, $m)) {
            $cancellation = $m[1];
        }
        $h->general()->cancellation($cancellation);

        if (preg_match("/^GRATIS annulering tot\s+(.{6,}?)\s*, na deze datum zal 100/i", $cancellation, $m)) {
            $h->booked()->deadline2($m[1]);
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
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
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
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
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
