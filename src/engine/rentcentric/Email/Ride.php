<?php

namespace AwardWallet\Engine\rentcentric\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Ride extends \TAccountChecker
{
    public $mailFiles = "rentcentric/it-642471514.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'pickupLocation' => ['Pickup Location:', 'Pickup Location :'],
            'pickupTime'     => ['Pickup Time:', 'Pickup Time :'],
            'statusPhrases'  => ['your rental is'],
            'statusVariants' => ['confirmed'],
        ],
    ];

    private $subjects = [
        'en' => ['Reservation Confirmation'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]rentcentric\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
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
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//*[contains(.,"@rentcentric.com")]')->length === 0
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
        $email->setType('Ride' . ucfirst($this->lang));

        $patterns = [
            'date' => '\w.{4,}[,\s]+\d{4}\b', // Fri, Jul 26, 2024
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
        ];

        $email->ota(); // because Rent Centric is software company

        $r = $email->add()->rental();

        $company = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Thank you for choosing to ride with'))}]", null, true, "/{$this->opt($this->t('Thank you for choosing to ride with'))}\s+(.{2,75}?)(?:\s*[,].+|\s*[.!?]+$)/");
        $r->extra()->company($company, false, true);

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|\s+{$this->opt($this->t('with'))}\s|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $r->general()->status($status);
        }

        $datesVal = $this->http->FindSingleNode("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('Dates:'))}] ]/node()[normalize-space()][2]");

        if (preg_match("/^({$patterns['date']})\s+[-–]+\s+({$patterns['date']})$/u", $datesVal, $m)) {
            $datePickup = strtotime($m[1]);
            $dateDropoff = strtotime($m[2]);
        } else {
            $datePickup = $dateDropoff = null;
        }

        $locationPickup = $this->http->FindSingleNode("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('pickupLocation'))}] ]/node()[normalize-space()][2]");
        $locationDropoff = $this->http->FindSingleNode("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('Return Location:'))}] ]/node()[normalize-space()][2]");
        $r->pickup()->location($locationPickup);
        $r->dropoff()->location($locationDropoff);

        $timePickup = $this->http->FindSingleNode("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('pickupTime'))}] ]/node()[normalize-space()][2]", null, true, "/^{$patterns['time']}/");
        $timeDropoff = $this->http->FindSingleNode("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('Return Time:'))}] ]/node()[normalize-space()][2]", null, true, "/^{$patterns['time']}/");

        if ($datePickup && $timePickup) {
            $r->pickup()->date(strtotime($timePickup, $datePickup));
        }

        if ($dateDropoff && $timeDropoff) {
            $r->dropoff()->date(strtotime($timeDropoff, $dateDropoff));
        }

        if (!empty($r->getPickUpDateTime()) && !empty($r->getDropOffLocation())) {
            $r->general()->noConfirmation();
        }

        $model = $this->http->FindSingleNode("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('MOTORCYCLE'))}] ]/node()[normalize-space()][2]");
        $r->car()->model($model);

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $1,004.85
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $r->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Sub Total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $baseFare, $m)) {
                $r->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feeRows = $this->http->XPath->query("//tr[ preceding-sibling::tr[*[1][{$this->eq($this->t('Sub Total'))}]] and following-sibling::tr[*[1][{$this->eq($this->t('Total'))}]] and *[1][normalize-space()] and *[4][normalize-space()] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[4]', $feeRow, true, '/^(.*?\d.*?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $r->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
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
            if (!is_string($lang) || empty($phrases['pickupLocation']) || empty($phrases['pickupTime'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['pickupLocation'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['pickupTime'])}]")->length > 0
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
