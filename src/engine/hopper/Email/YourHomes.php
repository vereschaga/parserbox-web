<?php

namespace AwardWallet\Engine\hopper\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourHomes extends \TAccountChecker
{
    public $mailFiles = "hopper/it-527799658.eml, hopper/it-521471457.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'checkIn'        => ['Check In'],
            'checkOut'       => ['Check Out'],
            'statusPhrases'  => ['Your Homes booking is'],
            'statusVariants' => ['confirmed'],
            'confNumber'     => ['Hopper Confirmation ID:', 'Hopper Confirmation ID :'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@hopper.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/Your Hopper booking confirmation\s*:\s*/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".hopper.com/") or contains(@href,"go.hopper.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thanks for using Hopper") or contains(normalize-space(),"Hopper. All rights reserved")]')->length === 0
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
        $email->setType('YourHomes' . ucfirst($this->lang));

        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        ];

        $h = $email->add()->hotel();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $hotelName = $this->http->FindSingleNode("//*[ table[normalize-space()][3][{$this->starts($this->t('checkIn'))}] ]/table[normalize-space()][1]");
        $address = implode(' ', $this->http->FindNodes("//*[ table[normalize-space()][3][{$this->starts($this->t('checkIn'))}] ]/table[normalize-space()][2]/descendant::text()[normalize-space()]"));
        $h->hotel()->name($hotelName)->address($address)->house();

        // Monday, August 21, 2023 at 4:00 PM
        $patterns['dateTime'] = "(?<dateFull>.{3,}\b\d{4})\s+{$this->opt($this->t('at'))}\s+(?<time>{$patterns['time']})";

        $checkInVal = $this->http->FindSingleNode("//tr[{$this->eq($this->t('checkIn'))}]/following-sibling::tr[normalize-space()][1]");
        $checkOutVal = $this->http->FindSingleNode("//tr[{$this->eq($this->t('checkOut'))}]/following-sibling::tr[normalize-space()][1]");

        if (preg_match("/^{$patterns['dateTime']}/", $checkInVal, $matches)) {
            $checkIn = strtotime($matches['dateFull']);
            $h->booked()->checkIn(strtotime($matches['time'], $checkIn));
        }

        if (preg_match("/^{$patterns['dateTime']}/", $checkOutVal, $matches)) {
            $checkOut = strtotime($matches['dateFull']);
            $h->booked()->checkOut(strtotime($matches['time'], $checkOut));
        }

        $traveller = $this->http->FindSingleNode("//tr[ preceding-sibling::tr[{$this->eq($this->t('Guests'))}] and following-sibling::tr[{$this->starts($this->t('Booked for'))}] ]", null, true, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u");
        $h->general()->traveller($traveller, true);

        // Booked for 2 adults and 3 children
        $guestsVal = $this->http->FindSingleNode("//tr[preceding-sibling::tr[normalize-space()] and {$this->starts($this->t('Booked for'))}]");

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('adult'))}/", $guestsVal, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('children'))}/", $guestsVal, $m)) {
            $h->booked()->kids($m[1]);
        }

        $cancellation = implode(' ', $this->http->FindNodes("//*/tr[normalize-space()][1][{$this->eq($this->t('Cancellation Policy'))}]/following-sibling::tr[normalize-space()]"));
        $h->general()->cancellation($cancellation);

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Trip Total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $1,558.42
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Room'))} and {$this->contains($this->t('night'))}] ]/*[normalize-space()][2]");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $baseFare, $m)) {
                $h->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feeRows = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and preceding-sibling::tr[*[normalize-space()][1][{$this->starts($this->t('Room'))} and {$this->contains($this->t('night'))}]] and following-sibling::tr[*[normalize-space()][1][{$this->eq($this->t('Total Cost'))}]] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^(.+?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $h->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }

            $discountAmounts = [];
            $discountRows = $this->http->XPath->query("//tr[ not(.//tr) and count(*[normalize-space()])=2 and preceding::tr[*[normalize-space()][1][not(.//tr) and {$this->eq($this->t('Total Cost'))}]] and following::tr[*[normalize-space()][1][not(.//tr) and {$this->eq($this->t('Trip Total'))}]] ]");

            foreach ($discountRows as $dRow) {
                $dAmount = $this->http->FindSingleNode('*[normalize-space()][2]', $dRow, true, '/^\(\s*(.+?)\s*\)$/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $dAmount, $m)) {
                    $discountAmounts[] = PriceHelper::parse($m['amount'], $currencyCode);
                }
            }

            if (count($discountAmounts) > 0) {
                $h->price()->discount(array_sum($discountAmounts));
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
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['checkIn']) || empty($phrases['checkOut'])) {
                continue;
            }

            if ($this->http->XPath->query("//tr[{$this->eq($phrases['checkIn'])}]")->length > 0
                && $this->http->XPath->query("//tr[{$this->eq($phrases['checkOut'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
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
}
