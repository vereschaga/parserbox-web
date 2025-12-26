<?php

namespace AwardWallet\Engine\goibibo\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class HotelBookingCancelled extends \TAccountChecker
{
    public $mailFiles = "goibibo/it-56461632.eml, goibibo/it-56511092.eml";

    public $reFrom = "@goibibo.com";
    public $reSubject = [
        'Pay at Hotel : Cancellation Acknowledgement - ',
        'Refund Notification for Your Booking',
    ];
    public $reBody = [
        'en' => [
            'Your hotel cancellation request has been processed',
            'We have successfully processed your refund.',
            'Your hotel booking has been cancelled successfully',
        ],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Booked on'       => ['Booked on', 'Booking date'],
            'detectCancelled' => [
                'Your hotel cancellation request has been processed',
                'We have successfully processed your refund.',
                'Your hotel booking has been cancelled successfully',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
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
        if ($this->http->XPath->query('//a[contains(@href,"goibibo.com/") or contains(@href,"www.goibibo.com")]')->length === 0
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

        $h->general()
            ->noConfirmation()
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booked on'))}]",
                null, false, "#{$this->opt($this->t('Booked on'))}:\s+(.+)#")))
            ->traveller($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Dear '))}])[1]", null, true, "#{$this->opt($this->t('Dear'))} (.+),#"), false);

        if ($this->http->FindSingleNode("//text()[{$this->contains($this->t('detectCancelled'))}]")) {
            $h->general()
                ->cancelled(true);
        }

        $totalAmount = $this->http->FindSingleNode("//td[{$this->eq($this->t('Booking Amount'))}]/following-sibling::td[normalize-space()][1]",
                null, true, "/^.*?\d.*?$/");

        if (!$totalAmount) {
            $totalAmount = $this->http->FindSingleNode("//div[{$this->eq($this->t('Booking Amount'))}]/following-sibling::div[normalize-space()][1]",
                null, true, "/^.*?\d.*?$/");
        }

        $tot = $this->getTotalCurrency($totalAmount);

        if ($tot['Total'] !== null) {
            $h->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        $hotelContacts = implode("\n",
            $this->http->FindNodes("//text()[" . $this->eq($this->t("Check In")) . "]/preceding::td[1]/descendant::text()[normalize-space()]"));

        if (preg_match("#^(?<name>.{3,})\n(?<address>.{3,})#", $hotelContacts, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address($m['address']);
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

        return true;
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
        foreach ($this->reBody as $lang => $reBody) {
            foreach ($reBody as $re) {
                if ($this->http->XPath->query("//text()[{$this->contains($re)}]")->length > 0) {
                    $this->lang = $lang;

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
        //$node = str_replace("$", "USD", $node);
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
