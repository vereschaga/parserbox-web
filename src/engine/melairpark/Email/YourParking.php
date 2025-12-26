<?php

namespace AwardWallet\Engine\melairpark\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourParking extends \TAccountChecker
{
    public $mailFiles = "melairpark/it-658957494.eml, melairpark/it-659163853.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'entry'          => ['Entry date:', 'Entry date :'],
            'exit'           => ['Exit date:', 'Exit date :'],
            'statusPhrases'  => ['Your car park booking is'],
            'statusVariants' => ['confirmed'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@melair.com.au') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your Melbourne Airport Parking booking confirmation') !== false
            || stripos($headers['subject'], 'Your Melbourne Airport Parking Booking Reminder') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".melbourneairport.com.au/") or contains(@href,"parking.melbourneairport.com.au")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"Australia Pacific Airports (Melbourne)")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('YourParking' . ucfirst($this->lang));

        $patterns = [
            'date' => '\b\d{1,2}\/\d{1,2}\/\d{2,4}\b', // 18/04/24
            'time' => '\b\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 19:30    |    7:30 PM
        ];

        $p = $email->add()->parking();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('BOOKING REF:'))}]");

        if (preg_match("/^({$this->opt($this->t('BOOKING REF:'))})[:\s]*([-A-Z\d]{5,})$/", $confirmation, $m)) {
            $p->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $p->general()->status($status);
        }

        $traveller = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Name:'))}] ]/*[normalize-space()][2]", null, true, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u");
        $p->general()->traveller($traveller);

        $carPark = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Car park:'))}] ]/*[normalize-space()][2]");
        $p->place()->location($carPark);

        $dateStartVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('entry'))}] ]/*[normalize-space()][2]");
        $dateEndVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('exit'))}] ]/*[normalize-space()][2]");

        $pattern = "/^(?<date>{$patterns['date']})\s+{$this->opt($this->t('at'))}\s+(?<time>{$patterns['time']}).*$/iu"; // 18/04/24 at 19:30

        if (preg_match($pattern, $dateStartVal, $m)) {
            $dateStart = strtotime($this->normalizeDate($m['date']));
            $timeStart = $m['time'];

            if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp](?:\.[ ]*)?[Mm]\.?$/', $timeStart, $m) && (int) $m[2] > 12) {
                // 23:00 PM -> 23:00
                $timeStart = $m[1];
            }
        } else {
            $dateStart = $timeStart = null;
        }

        if (preg_match($pattern, $dateEndVal, $m)) {
            $dateEnd = strtotime($this->normalizeDate($m['date']));
            $timeEnd = $m['time'];

            if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp](?:\.[ ]*)?[Mm]\.?$/', $timeEnd, $m) && (int) $m[2] > 12) {
                // 23:00 PM -> 23:00
                $timeEnd = $m[1];
            }
        } else {
            $dateEnd = $timeEnd = null;
        }

        if ($dateStart && $timeStart) {
            $p->booked()->start(strtotime($timeStart, $dateStart));
        }

        if ($dateEnd && $timeEnd) {
            $p->booked()->end(strtotime($timeEnd, $dateEnd));
        }

        $vehicleRegistration = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Vehicle Registration:'))}] ]/*[normalize-space()][2]");
        $p->booked()->plate($vehicleRegistration);

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Booking total (incl GST):'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $49.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $p->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        if (!empty($p->getStartDate()) && !empty($p->getEndDate())) {
            $p->place()->address('Melbourne Airport');
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
            if (!is_string($lang) || empty($phrases['entry']) || empty($phrases['exit'])) {
                continue;
            }

            if ($this->http->XPath->query("//tr/*[{$this->eq($phrases['entry'])}]")->length > 0
            && $this->http->XPath->query("//tr/*[{$this->eq($phrases['exit'])}]")->length > 0
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

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            '/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', // 18/04/24    |    18/04/2024
        ];
        $out = [
            '$2/$1/$3',
        ];

        return preg_replace($in, $out, $text);
    }
}
