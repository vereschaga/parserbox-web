<?php

namespace AwardWallet\Engine\silvercar\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourSilvercarHasBeen extends \TAccountChecker
{
    public $mailFiles = "silvercar/it-1.eml, silvercar/it-1642585.eml, silvercar/it-1701974.eml, silvercar/it-1709574.eml, silvercar/it-26959817.eml, silvercar/it-3186214.eml, silvercar/it-3192533.eml, silvercar/it-3694204.eml, silvercar/it-65065967.eml";

    public $reBody = [
        'en' => ['Your Silvercar Has Been Reserved', 'Your Reservation Has Been Updated', 'Reservation Booked using Promo Code', 'Your Silvercar Has Been Updated', 'Your Silvercar Booking Has Been Updated', 'Your Receipt', 'Your Silvercar Booking Has Been Cancelled', 'Your Silvercar Rental Agreement'],
    ];
    public $reSubject = [
        'Your Silvercar Has Been Reserved',
        'Your Reservation Has Been Updated',
        'Your Booking Has Been Updated',
        'Your Silvercar Receipt',
        'Your Silvercar Reservation Has Been Cancelled',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Total'         => ['Total', 'Total:', 'Estimated Total', 'Estimated Total:'],
            'RENTAL'        => ['RENTAL', 'Rental'],
            'DISCOUNT'      => ['DISCOUNT', 'Discount'],
            'feeHeaders'    => ['PROTECTION COVERAGE OPTION', 'Protection Coverage Option', 'FEES AND CONCESSIONS', 'Fees And Concessions', 'TAXES', 'Taxes'],
            'statusContext' => ['Your Silvercar Has Been', 'Your Reservation Has Been', 'Your Silvercar Booking Has Been'],
            "Name"          => ["Name", "Renter's Name"],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".silvercar.com/") or contains(@href,"www.silvercar.com") or contains(@href,"@silvercar.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Thanks for reserving with Silvercar") or contains(normalize-space(),"The Silvercar Team") or contains(.,"@silvercar.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@silvercar.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->rental();
        $conf = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.), "Confirmation#") or starts-with(normalize-space(.), "Confirmation #")]', null, true, "#\#\s+(.+)#");

        if (!$conf) {
            $conf = $this->nextText('Confirmation');
        }
        $r->general()->confirmation($conf);

        $memberNumber = $this->nextText('Member Number');

        if ($memberNumber) {
            $r->program()->account($memberNumber, false);
        }

        if (empty($node = $this->nextText('Pickup Location'))) {
            $node = $this->nextText('Location');
        }
        $r->pickup()->location($node);

        if (empty($node = $this->nextText('Scheduled Pick-Up'))) {
            $node = $this->nextText('Pick-Up');
        }

        if (empty($node)) {
            $node = $this->nextText('Pickup');
        }
        $r->pickup()->date(strtotime($node));

        if (empty($node = $this->nextText('Return Location'))) {
            $node = $this->nextText('Location');
        }
        $r->dropoff()->location($node);

        if (empty($node = $this->nextText('Scheduled Return'))) {
            $node = $this->nextText('Return');
        }
        $r->dropoff()->date(strtotime($node));

        $r->pickup()->openingHours($this->http->FindSingleNode('//text()[normalize-space(.)="Hours"]/ancestor::td[1]/following-sibling::td[1]'), false, true);
        $r->dropoff()->openingHours($this->http->FindSingleNode('//text()[normalize-space(.)="Hours"]/ancestor::td[1]/following-sibling::td[1]'), false, true);
        $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Name'))}]/following::text()[normalize-space(.)][1]/ancestor::*[1]", null, true, "/^[^@]+$/");

        if ($node != '[Unregistered Guest User]' and !empty($node)) {
            $r->general()->traveller($node);
        }

        if (!empty($node = $this->http->FindSingleNode("//text()[normalize-space()='Vehicle']/ancestor::td[1]/following-sibling::td[normalize-space()][1]"))) {
            $r->car()->model($node);
        } elseif (!empty($node = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'receive a premium')]"))) {
            $r->car()->model($this->re('#receive\s+a\s+premium\s+(.*?)\s+with\s+no\s+lines#', $node));
        }

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)) {
            $r->price()->currency($m['currency']);
            $r->price()->total($this->normalizeAmount($m['amount']));

            $rental = $this->http->FindSingleNode("//div[{$this->eq($this->t('RENTAL'))}]/following-sibling::div[normalize-space()][1]/descendant::td[normalize-space()]/following-sibling::td[normalize-space()][1]");

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d]*)$/', $rental, $matches)) {
                $r->price()->cost($this->normalizeAmount($matches['amount']));
            }

            $discount = $this->http->FindSingleNode("//div[{$this->eq($this->t('DISCOUNT'))}]/following-sibling::div[normalize-space()][1]/descendant::td[normalize-space()]/following-sibling::td[normalize-space()][1]");

            if (preg_match('/^-[ ]*(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d]*)$/', $discount, $matches)) {
                $r->price()->discount($this->normalizeAmount($matches['amount']));
            }

            $feeRows = $this->http->XPath->query("//div[{$this->eq($this->t('feeHeaders'))}]/following-sibling::div[normalize-space()][1]/descendant::tr[count(*[normalize-space()])=2]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow);

                if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d]*)$/', $feeCharge, $matches)) {
                    $feeName = implode(' ', $this->http->FindNodes('*[normalize-space()][1]/descendant::text()[normalize-space()]', $feeRow));
                    $r->price()->fee($feeName, $this->normalizeAmount($matches['amount']));
                }
            }
        }

        $xpathStatus = [];

        foreach ((array) $this->t('statusContext') as $s) {
            $xpathStatus[] = $this->starts(strtolower($s), "translate(normalize-space(),'" . strtoupper($s) . "','" . strtolower($s) . "')");
        }
        $statusText = $this->http->FindSingleNode('descendant::tr[not(.//tr) and (' . implode(' or ', $xpathStatus) . ')]');

        if (preg_match("/^{$this->opt($this->t('statusContext'))}\s+(\w+)[.,!?]*$/i", $statusText, $m)) {
            $r->general()->status($m[1]);

            if ($m[1] == 'Cancelled') {
                $r->general()
                    ->cancelled();
            }
        }

        $pointsEarn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Points Earn'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, '/^.*\d.*$/');

        if ($pointsEarn) {
            $r->program()->earnedAwards($pointsEarn);
        }
    }

    private function nextText($field, $regexp = null)
    {
        $w = (array) $this->t($field);
        $rule = implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.),'{$s}')";
        }, $w));

        return $this->http->FindSingleNode("//text()[{$rule}]/following::text()[normalize-space(.)][1]/ancestor::*[1]", null, true, $regexp);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            $body = $this->http->Response['body'];

            foreach ($this->reBody as $lang => $reBody) {
                foreach ($reBody as $re) {
                    if (stripos($body, $re) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
