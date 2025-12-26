<?php

namespace AwardWallet\Engine\golfnow\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationDetails extends \TAccountChecker
{
    public $mailFiles = "golfnow/it-93492887.eml, golfnow/it-93485040.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Confirmation #:'],
            'address'    => ['Golf Course Address:'],
        ],
    ];

    private $subjects = [
        'en' => ['Reservation Confirmation'],
    ];

    private $detectors = [
        'en' => ['Tee Time Details'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@email.golffacility.com') !== false;
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
        if ($this->http->XPath->query('//a[contains(@href,"/golfnow.com") or contains(@href,"www.golfnow.com")]')->length === 0
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
        $email->setType('ReservationDetails' . ucfirst($this->lang));

        $this->parseEvent($email);

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

    private function parseEvent(Email $email): void
    {
        $e = $email->add()->event();
        $e->place()->type(Event::TYPE_EVENT);

        $traveller = $this->http->FindSingleNode("//tr[ *[normalize-space()][1][{$this->eq($this->t('Group Name:'))}] ]/*[normalize-space()][2]", null, true, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s+-\s+|$)/u");
        $e->general()->traveller($traveller);

        $confirmation = $this->http->FindSingleNode("//tr[ *[normalize-space()][1][{$this->eq($this->t('confNumber'))}] ]/*[normalize-space()][2]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr/*[normalize-space()][1][{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $e->general()->confirmation($confirmation, $confirmationTitle);
        }

        $confirmation2 = $this->http->FindSingleNode("//tr[ *[normalize-space()][1][{$this->eq($this->t('Golf Course Confirmation #:'))}] ]/*[normalize-space()][2]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation2) {
            $confirmation2Title = $this->http->FindSingleNode("//tr/*[normalize-space()][1][{$this->eq($this->t('Golf Course Confirmation #:'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $e->general()->confirmation($confirmation2, $confirmation2Title);
        }

        $date = $this->http->FindSingleNode("//tr[ *[normalize-space()][1][{$this->eq($this->t('Date:'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if ($date) {
            $e->booked()->start2($date)->noEnd();
            $time = $this->http->FindSingleNode("//tr[ *[normalize-space()][1][{$this->eq($this->t('Tee Time:'))}] ]/*[normalize-space()][2]", null, true, '/^\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?$/');

            if ($e->getStartDate() && $time) {
                $e->booked()->start(strtotime($time, $e->getStartDate()));
            }
        }

        $name = $this->http->FindSingleNode("//tr[ *[normalize-space()][1][{$this->eq($this->t('Golf Course:'))}] ]/*[normalize-space()][2]");
        $address = $this->http->FindSingleNode("//tr[ *[normalize-space()][1][{$this->eq($this->t('Golf Course Address:'))}] ]/*[normalize-space()][2]", null, true, "/^(.{3,}?)(?:\s*\(\s*{$this->opt($this->t('Map it'))}\s*\)|$)/");
        $e->place()->name($name)->address($address);

        $players = $this->http->FindSingleNode("//tr[ *[normalize-space()][1][{$this->eq($this->t('Number of Players:'))}] ]/*[normalize-space()][2]", null, true, "/^\d{1,3}$/");
        $e->booked()->guests($players);

        $totalPrice = $this->http->FindSingleNode("//tr[ *[normalize-space()][1][{$this->eq($this->t('Grand Total:'))}] ]/*[normalize-space()][2]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $176.09
            $e->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency']);

            $matches['currency'] = trim($matches['currency']);
            $feeRows = $this->http->XPath->query("//tr[ following-sibling::tr[*[normalize-space()][1][{$this->eq($this->t('Grand Total:'))}]] and *[normalize-space()][2] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^(.+?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $e->price()->fee($feeName, $this->normalizeAmount($m['amount']));
                }
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

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
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
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['address'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['address'])}]")->length > 0
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
}
