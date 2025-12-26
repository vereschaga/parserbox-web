<?php

namespace AwardWallet\Engine\sonesta\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "sonesta/it-663848279.eml";

    public $detectSubjects = [
        // en
        // The Shelburne Sonesta New York Your Reservation Confirmation
        'Your Reservation Confirmation',
    ];

    public $lang = '';
    public static $dictionary = [
        'en' => [
            'Hotel Booking Number:' => 'Hotel Booking Number:',
            'Hotel Information:'    => 'Hotel Information:',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]sonesta\.com$/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && stripos($parser->getHeader('from'), '@stayshelburne.affinia.com') === false
            && $this->http->XPath->query('//a[contains(@href,".sonesta.com/") or contains(@href,".sonesta.com%2f") or contains(@href,"www.sonesta.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"SONESTA TRAVEL PASS") or contains(.,"@sonesta.com") or contains(.,"www.sonesta.com")]')->length === 0
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

        $this->parseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,})\s*$/"),
                trim($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number:'))}][1]"), ':'))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Name:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([[:alpha:] \-]{3,})\s*$/"), true)
        ;
        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Booking Number:'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*([A-Z\d]{5,})\s*$/");

        if (!in_array($conf, array_column($h->getConfirmationNumbers(), 0))) {
            $h->general()
                ->confirmation($conf,
                    trim($this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Booking Number:'))}][1]"), ':'));
        }
        $cancellationSegment = implode("\n",
            $this->http->FindNodes("//text()[{$this->eq($this->t('Cancellation Policy:'))}]/following::text()[normalize-space()][1]/ancestor::*[.//text()[{$this->eq($this->t('Cancellation Policy:'))}]][1]"));

        if (preg_match("/{$this->opt($this->t('Cancellation Policy:'))}\s+([\s\S]+?)(?:\n[[:alpha:]+ ]:\n|$)/", $cancellationSegment, $m)) {
            $h->general()
                ->cancellation(preg_replace('/\s+/', ' ', trim($m[1])));
        }

        // Hotel
        $hotelText = implode("\n", $this->http->FindNodes("//tr[*[1][{$this->starts($this->t('Hotel:'))}] and *[2][{$this->starts($this->t('Address:'))}]]//text()[normalize-space()]"));

        if (preg_match("/^\s*{$this->opt($this->t('Hotel:'))}(?<name>.{3,}?)\n\s*{$this->opt($this->t('Phone:'))}(?<phone>.{3,}?)\n\s*{$this->opt($this->t('Address:'))}(?<address>.{3,})\s*$/s", $hotelText, $m)) {
            $h->hotel()
                ->name(preg_replace('/\s+/', ' ', trim($m['name'])))
                ->address(preg_replace('/\s+/', ' ', trim($m['address'])))
                ->phone(preg_replace('/\s+/', ' ', trim($m['phone'])))
            ;
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival Date:'))}]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure Date:'))}]/following::text()[normalize-space()][1]")))
            ->guests($this->http->FindSingleNode("//text()[{$this->starts($this->t('No. of Guest(s):'))}]",
                null, true, "/{$this->opt($this->t('No. of Guest(s):'))}\s*(\d+)\s*$/"))
        ;

        // Rooms

        $type = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type:'))}]/following::text()[normalize-space()][1]");

        $ratesRows = $this->http->FindNodes("//text()[{$this->eq($this->t('Room Rate:'))}]/following::text()[normalize-space()][1]/ancestor::*[preceding::text()[{$this->eq($this->t('Room Rate:'))}]][last()]//tr[not(.//tr)]");
        $rates = [];

        foreach ($ratesRows as $rateRow) {
            if (preg_match("/^\s*\d{1,2}\\/\d{1,2}\\/\d{4}\s*(.+)/", $rateRow, $m)) {
                // 9/7/2023 $663.00
                $rates[] = $m[1];
            } else {
                $rates = [];

                break;
            }
        }

        if (!empty($type) || !empty($rates)) {
            $room = $h->addRoom();
            $room->setRates($rates);
            $room->setType($type);
        }

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Cost (Room Rate + Taxes):'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]",
            null, true, "/{$this->opt($this->t('Total Cost (Room Rate + Taxes):'))}(.+)/");

        if (preg_match('/^(?<cost>.+)\s*\+\s*(?<tax>.+)\s*\=\s*(?<total>.+)$/', $totalPrice, $m)) {
            if (preg_match('/^(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d ]*)$/', $m['total'], $mat)) {
                // USD 258.62
                $h->price()
                    ->currency($mat['currency'])
                    ->total($this->normalizeAmount($mat['amount'], $mat['currency']));
                $fee = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Facilities Fee:'))}]/following::text()[normalize-space()][1]");

                if (preg_match('/^(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d ]*)$/', $fee, $mat2)) {
                    $h->price()
                        ->total($h->getPrice()->getTotal() + $this->normalizeAmount($mat2['amount'], $mat2['currency']));
                    $h->price()
                        ->fee(trim($this->http->FindSingleNode("//text()[{$this->eq($this->t('Facilities Fee:'))}]"), ':'), $this->normalizeAmount($mat2['amount'], $mat2['currency']));
                }
            }

            if (preg_match('/^(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d ]*)$/', $m['tax'], $mat)) {
                // USD 258.62
                $h->price()
                    ->tax($this->normalizeAmount($mat['amount'], $mat['currency']));
            }

            if (preg_match('/^(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d ]*)$/', $m['cost'], $mat)) {
                // USD 258.62
                $h->price()
                    ->cost($this->normalizeAmount($mat['amount'], $mat['currency']));
            }
        }

        $this->detectDeadLine($h);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Reservations may be cancelled by (?<time>\d{1,2}[AP]M) standard hotel time (?<hours>\d+ hours) before arrival to avoid a penalty of 1 night stay plus taxes\./i", $cancellationText, $m)
        ) {
            $m['time'] = preg_replace('/^\s*(\d{1,2})([AP]M)\s*$/i', '$1:00 $2', $m['time']);
            $h->booked()->deadlineRelative($m['hours'], $m['time']);
        }
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Hotel Booking Number:']) || empty($phrases['Hotel Information:'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Hotel Booking Number:'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Hotel Information:'])}]")->length > 0
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

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 9/11/2023 - 4:00pm check-in
            "/^\s*(\d{1,2})\\/(\d{1,2})\\/(\d{4})\s*-\s*(\d{1,2}:\d{2}(?:[ap]m)?)\s*(?:\s+\D+)?\s*$/i",
        ];
        $out = [
            "$2.$1.$3, $4",
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('$date = '.print_r( $date,true));

        return strtotime($date);
    }

    private function normalizeAmount(string $amount, string $currency): ?float
    {
        if (empty($amount)) {
            return null;
        }

        $amount = PriceHelper::parse($amount, $currency);

        if (is_numeric($amount)) {
            return $amount;
        }

        return null;
    }
}
