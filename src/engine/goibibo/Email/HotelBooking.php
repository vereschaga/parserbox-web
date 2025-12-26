<?php

namespace AwardWallet\Engine\goibibo\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class HotelBooking extends \TAccountChecker
{
    public $mailFiles = "goibibo/it-15896230.eml, goibibo/it-55155599.eml, goibibo/it-55682302.eml, goibibo/it-56461645.eml, goibibo/it-56461893.eml";

    public $reFrom = "noreply@goibibo.com";
    public $reBody = [
        'en'  => ['Your hotel booking is confirmed', 'Booking ID'],
        'en2' => ['Your booking is confirmed', 'Booking ID'],
        'en3' => ['We are holding the booking for you', 'Booking ID'],
        'en4' => ['Please find below summary of your booking:', 'Booking ID'],
    ];
    public $reSubject = [
        'Your Hotel Booking is Confirmed at',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Hotel Booking Id'      => ['Hotel Booking Id', 'Booking PNR', 'PNR'],
            'Your hotel booking is' => ['Your hotel booking is', 'Your booking is', 'Your hotel reservation is'],
            'statusVariants'        => ['confirmed', 'on hold with us pending the payment'],
            'hotelContacts'         => ['Phone', 'Email', 'Getting there'],
            'You saved'             => ['You saved', 'You will save'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        if (!$this->parseEmail($email)) {
            return null;
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".goibibo.com/") or contains(@href,"www.goibibo.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Thank you for choosing goibibo") or contains(normalize-space(),"Book with goibibo mobile") or contains(normalize-space(),"visit goibibo.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email): bool
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking ID:'))}]/following::text()[normalize-space(.)!=''][1]",
                null, false, "#^\s*[\w\-]+\s*$#"), trim($this->t('Booking ID:'), " :"));

        $h = $email->add()->hotel();

        if (!empty($this->http->FindSingleNode("(//text()[{$this->starts($this->t('We are holding the booking for you'))}])[1]"))) {
            $h->general()->noConfirmation();
        } else {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Booking Id'))}]/ancestor::td[1]",
                    null, true, "#{$this->opt($this->t('Hotel Booking Id'))}\s*([A-Z\d]{7,})$#")
                ?? $this->http->FindSingleNode("//td[{$this->eq($this->t('Hotel Booking Id'))}]/following-sibling::td[normalize-space()][1]",
                    null, true, "/^[A-Z\d]{7,}$/");

            $h->general()
                ->confirmation($confirmation);
        }
        $h->general()
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booked on'))}]",
                null, false, "#{$this->opt($this->t('Booked on'))}:\s+(.+)#")))
            ->travellers($this->http->FindNodes("//text()[{$this->starts($this->t('Guest Name'))}]/ancestor::tr[1]//text()[normalize-space(.)!=''][not({$this->contains($this->t('Guest Name'))})]"));
        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your hotel booking is'))}]",
            null, false,
            "#{$this->opt($this->t('Your hotel booking is'))}\s*({$this->opt($this->t('statusVariants'))})#i");

        if (!empty($status)) {
            $h->general()->status($status);
        }

        $totalAmount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Amount'))}]/ancestor::td[1]",
                null, true, "#{$this->opt($this->t('Total Amount'))}\s*(.*\d.*)#")
            ?? $this->http->FindSingleNode("//td[{$this->eq($this->t('Total Amount'))}]/following-sibling::td[normalize-space()][1]",
                null, true, "/^.*\d.*$/");

        $tot = $this->getTotalCurrency($totalAmount);

        if ($tot['Total'] !== null) {
            $h->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        $totalSavings = $this->http->FindSingleNode("//text()[{$this->starts($this->t('You saved'))} and {$this->contains($this->t('on this booking'))}]");

        if ($totalSavings === null) {
            $totalSavings = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Savings'))}]/ancestor::td[1]",
                null, true, "#{$this->opt($this->t('Total Savings'))}\s*(.*\d.*)#");
        }

        if ($totalSavings === null) {
            $totalSavings = $this->http->FindSingleNode("//td[ descendant::text()[normalize-space()][2][{$this->eq($this->t('Total Savings'))}] ]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][2]",
                null, true, "/^.*\d.*$/");
        }
        $tot = $this->getTotalCurrency($totalSavings);

        if ($tot['Total'] !== null) {
            $h->price()
                ->discount($tot['Total']);
        }

        /*
            Hotel Mint Downtown, Koramangala
            Site no 426,8th main 4th Block, Koramangala, Bengaluru, India
            Phone: 7777091409
        */
        $hotelContacts = implode("\n",
            $this->http->FindNodes("//*[count(table)=2 and table[1]/descendant::img]/table[2][{$this->contains($this->t('hotelContacts'))}]/descendant::text()[normalize-space()]"));

        if (preg_match("/^(?<name>.{3,})\n(?<address>.{3,})\n{$this->opt($this->t('hotelContacts'))}/", $hotelContacts,
            $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address($m['address']);
        }

        if (preg_match("/^{$this->opt($this->t('Phone'))}\s*:\s*(?<phone>[+(\d][-,. \d)(]{5,}[\d)])$/m", $hotelContacts,
            $m)) {
            $h->hotel()->phone($m['phone']);
        }

        $checkIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check In'))}]/ancestor::td[1]",
                null, true, "#{$this->opt($this->t('Check In'))}\s*(.{6,})#")
            ?? $this->http->FindSingleNode("//td[{$this->eq($this->t('Check In'))}]/following-sibling::td[normalize-space()][1]");

        $checkOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check Out'))}]/ancestor::td[1]",
                null, true, "#{$this->opt($this->t('Check Out'))}\s*(.{6,})#")
            ?? $this->http->FindSingleNode("//td[{$this->eq($this->t('Check Out'))}]/following-sibling::td[normalize-space()][1]");

        $h->booked()
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut));

        if (!empty($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Cancellation Policy'))}])[1]"))) {
            $h->setCancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Policy'))}]/ancestor::td[1]/following::td[1]"));
            $this->detectDeadLine($h);
        }
        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of Guests'))}]/ancestor::td[1]/following::td[1]");

        if (preg_match_all("#Room\s+(\d+):\s+Adults?\s*-\s*(\d+),\s*Childs?\s*-\s*(\d+)#", $node, $m, PREG_SET_ORDER)
        ) {
            $h->booked()
                ->rooms(max(array_column($m, 1)))
                ->guests(array_sum(array_column($m, 2)))
                ->kids(array_sum(array_column($m, 3)));
        }

        $r = $h->addRoom();

        $roomType = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Type'))}]",
                null, true, "#{$this->opt($this->t('Room Type'))}:\s*(.{2,})#")
            ?? $this->http->FindSingleNode("//td[{$this->eq($this->t('Room Type'))}]/following-sibling::td[normalize-space()][1]");

        $r->setType($roomType);

        return true;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^Booking is Non-Refundable(?:\.|$)/i", $cancellationText)) {
            $h->booked()->nonRefundable();
        }

        if (preg_match("/Free cancellation until (.+?) hours\./i", $cancellationText, $m)) {
            $h->booked()->deadline($this->normalizeDate($m[1]));
        }
        // Free cancellable(100% refund) till 15 April 2020 18:00:00(destination local time).
        if (preg_match("/Free cancellable\(100% refund\) till (\d+ \w+ \d{4} \d+:\d+:\d+)\(destination local time\)\./i", $cancellationText, $m)) {
            $h->booked()->deadline2($m[1]);
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            // April 17, 2018 02:00 PM    |    Feb. 27, 2020, 2:05 p.m. IST
            '/^([[:alpha:]]{3,})[.\s]+(\d{1,2})\s*,\s*(\d{2,4})[,\s]+(\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)(?:\s*[A-Z]{3,})?$/u',
        ];
        $out = [
            '$2 $1 $3 $4',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
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
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("Rs.", "INR", $node);
        $node = str_replace("Rs", "INR", $node);
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[.\d,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[.\d,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>-*?)(?<t>\d[.\d,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
