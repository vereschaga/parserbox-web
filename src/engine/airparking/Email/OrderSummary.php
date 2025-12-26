<?php

namespace AwardWallet\Engine\airparking\Email;

use AwardWallet\Schema\Parser\Email\Email;

class OrderSummary extends \TAccountChecker
{
    public $mailFiles = "airparking/it-722762666.eml, airparking/it-758314793.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'statusPhrases'  => ['Your spot at', 'Your booking'],
            'statusVariants' => ['confirmed'],
            'confNumber'     => ['Confirmation No. #', 'Confirmation Number'],
            'parkingAddress' => ['Entrance Address', 'Parking Lot Address'],
            'dateStart'      => ['Enter After', 'Check-in'],
            'dateEnd'        => ['Exit Before', 'Check-out'],
        ],
    ];

    private $subjects = [
        'en' => ['parking order'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]airportsparking\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ((!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true)
            && (!array_key_exists('subject', $headers) || strpos($headers['subject'], 'Airportsparking.com') === false)
        ) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && strpos($parser->getSubject(), 'Airportsparking.com') === false
            && $this->http->XPath->query('//a[contains(@href,".airportsparking.com/")]')->length === 0
            && $this->http->XPath->query('//tr[normalize-space()="help@airportsparking.com"]')->length === 0
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
        }
        $email->setType('OrderSummary' . ucfirst($this->lang));

        $patterns = [
            'date'          => '\b.{4,}?\b\d{4}\b', // Nov 01, 2023
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  3pm
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52  |  (+351) 21 342 09 07  |  713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $park = $email->add()->parking();

        $traveller = $this->http->FindSingleNode("//*[ count(*[normalize-space()])>1 and count(*[normalize-space()])<4 and *[normalize-space()][1][{$this->eq($this->t('Name'), "translate(.,':','')")}] ]/*[normalize-space()][last()]", null, true, "/^{$patterns['travellerName']}$/u");
        $isNameFull = true;

        if (!$traveller) {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
                $isNameFull = null;
            }
        }

        $park->general()->traveller($traveller, $isNameFull);

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]/ancestor::tr[1]", null, "/\s{$this->opt($this->t('is'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s+{$this->opt($this->t('and'))}\s|\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $park->general()->status($status);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{4,40}$/');
        $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');

        if (!$confirmation) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{4,40}$/');
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]", null, true, "/({$this->opt($this->t('confNumber'))})(?:\s+{$this->opt($this->t('is'))}|$)/");
        }

        $park->general()->confirmation($confirmation, $confirmationTitle);

        $orderDateVal = $this->http->FindSingleNode("//*[ count(*[normalize-space()])>1 and count(*[normalize-space()])<4 and *[normalize-space()][1][{$this->eq($this->t('Order Date'), "translate(.,':','')")}] ]/*[normalize-space()][last()]");

        if (preg_match("/^(?<date>{$patterns['date']})[,.\s]+(?<time>{$patterns['time']})/u", $orderDateVal, $m)) {
            $park->general()->date(strtotime($m['time'], strtotime($m['date'])));
        }

        $parkingName = $this->http->FindSingleNode("//*[ count(*[normalize-space()])>1 and count(*[normalize-space()])<4 and *[normalize-space()][1][{$this->eq($this->t('Parking Lot Name'), "translate(.,':','')")}] ]/*[normalize-space()][last()]");
        $parkingPhone = $this->http->FindSingleNode("//*[ count(*[normalize-space()])>1 and count(*[normalize-space()])<4 and *[normalize-space()][1][{$this->eq($this->t('Parking Lot Phone'), "translate(.,':','')")}] ]/*[normalize-space()][last()]", null, true, "/^{$patterns['phone']}$/");
        $parkingAddress = $this->http->FindSingleNode("//*[ count(*[normalize-space()])>1 and count(*[normalize-space()])<4 and *[normalize-space()][1][{$this->eq($this->t('parkingAddress'), "translate(.,':','')")}] ]/*[normalize-space()][last()]");
        $park->place()->location($parkingName)->phone($parkingPhone, false, true)->address($parkingAddress);

        $dateStart = $timeStart = null;
        $dateStartVal = $this->http->FindSingleNode("//*[ count(*[normalize-space()])>1 and count(*[normalize-space()])<4 and *[normalize-space()][1][{$this->eq($this->t('dateStart'), "translate(.,':','')")}] ]/*[normalize-space()][last()]");

        if (preg_match("/^(?<date>{$patterns['date']})[,.\s]+(?<time>{$patterns['time']})/u", $dateStartVal, $m)) {
            $dateStart = strtotime($m['date']);
            $timeStart = $m['time'];
        } elseif (preg_match("/^{$patterns['date']}$/u", $dateStartVal)) {
            $dateStart = strtotime($dateStartVal);
            $timeStart = $this->http->FindSingleNode("//*[ count(*[normalize-space()])>1 and count(*[normalize-space()])<4 and *[normalize-space()][1][{$this->eq($this->t('dateStart'), "translate(.,':','')")}] ]/*[normalize-space()][last()]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['time']}/");
        }

        if ($dateStart && $timeStart) {
            $park->booked()->start(strtotime($timeStart, $dateStart));
        }

        $dateEnd = $timeEnd = null;
        $dateEndVal = $this->http->FindSingleNode("//*[ count(*[normalize-space()])>1 and count(*[normalize-space()])<4 and *[normalize-space()][1][{$this->eq($this->t('dateEnd'), "translate(.,':','')")}] ]/*[normalize-space()][last()]");

        if (preg_match("/^(?<date>{$patterns['date']})[,.\s]+(?<time>{$patterns['time']})/u", $dateEndVal, $m)) {
            $dateEnd = strtotime($m['date']);
            $timeEnd = $m['time'];
        } elseif (preg_match("/^{$patterns['date']}$/u", $dateEndVal)) {
            $dateEnd = strtotime($dateEndVal);
            $timeEnd = $this->http->FindSingleNode("//*[ count(*[normalize-space()])>1 and count(*[normalize-space()])<4 and *[normalize-space()][1][{$this->eq($this->t('dateEnd'), "translate(.,':','')")}] ]/*[normalize-space()][last()]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['time']}/");
        }

        if ($dateEnd && $timeEnd) {
            $park->booked()->end(strtotime($timeEnd, $dateEnd));
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
            if (!is_string($lang) || empty($phrases['parkingAddress']) || empty($phrases['dateStart'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['parkingAddress'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['dateStart'])}]")->length > 0
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
