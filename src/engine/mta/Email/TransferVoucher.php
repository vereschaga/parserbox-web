<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TransferVoucher extends \TAccountChecker
{
    public $mailFiles = "mta/it-209348651.eml, mta/it-683036553-savenio.eml";
    public $subjects = [
        'Transfer Voucher - Ref ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    private $providerCode = '';

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'mtatravel.com.au') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[not(.//tr) and {$this->eq($this->t('Transfer From'))}]")->length === 0
            || $this->http->XPath->query("//*[not(.//tr) and {$this->eq($this->t('Accommodation'))}]")->length === 0
        ) {
            return false;
        }

        return $this->assignProvider($parser->getHeaders());
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mtatravel\.com\.au$/', $from) > 0;
    }

    public function ParseTransferFlightHotel(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $t = $email->add()->transfer();

        $provPhone = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Supplier Contact')]/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/(?:^|:\s*)({$patterns['phone']})(?:\s+Whats App|$)/i");

        if (!empty($provPhone)) {
            $t->addProviderPhone($provPhone);
        }

        $travellers = [];
        $travellerVal = $this->http->FindSingleNode("//text()[normalize-space()='Guest Name(s)']/ancestor::tr[1]/descendant::td[normalize-space()][2]");
        $travellerParts = array_filter(array_map(function ($item) {
            return preg_replace('/^(?:Miss|Mrs|Mr|Ms)\b[.\s]*/i', '', $item);
        }, preg_split('/(\s*&\s*)+/', $travellerVal)));

        foreach ($travellerParts as $tPart) {
            if (preg_match("/^\d{1,3}\s*More$/i", $tPart)) {
                continue;
            } elseif (preg_match("/^{$patterns['travellerName']}$/u", $tPart)) {
                $travellers[] = $tPart;
            } else {
                $travellers = [];

                break;
            }
        }

        $t->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^([A-Z\d]{7,})$/"), 'Confirmation Number')
            ->travellers($travellers);

        $notes = implode('; ', $this->http->FindNodes("//text()[{$this->contains($this->t('Journey'))} and {$this->contains($this->t('Information'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]"));

        if (!empty($notes)) {
            $t->setNotes($notes);
        }

        $xpathSegments = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][{$this->starts($this->t('Journey ∆'), 'translate(normalize-space(),"0123456789","∆∆∆∆∆∆∆∆∆∆")')} and {$this->eq($this->t('Journey'), 'translate(normalize-space(),"0123456789 ","")')}] ]";
        $segments = $this->http->XPath->query($xpathSegments);
        $this->logger->debug('$xpathSegments = ' . $xpathSegments);

        if ($segments->length < 1 || $segments->length > 2) {
            $this->logger->debug('Incorrect segments count!');

            return;
        }

        $transferFrom = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Transfer From'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]");
        $accommodation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Accommodation'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]");
        $duration = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Estimated Transfers Time'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, '/^\d.*/');

        foreach ($segments as $i => $root) {
            $s = $t->addSegment();

            $airportCode = preg_match("/\(\s*([A-Z]{3})\s*\)/", $transferFrom, $m) ? $m[1] : null;

            if ($i === 0) {
                $s->departure()->name($transferFrom);
                $s->arrival()->name($accommodation);

                if ($airportCode) {
                    $s->departure()->code($airportCode);
                }
            } else {
                $s->departure()->name($accommodation);
                $s->arrival()->name($transferFrom);

                if ($airportCode) {
                    $s->arrival()->code($airportCode);
                }
            }

            $s->extra()->duration($duration, false, true);
            $s->arrival()->noDate();

            $dateDepVal = $this->http->FindSingleNode("*[normalize-space()][2]", $root);

            if (preg_match("/^(?<date>.{3,}?\b\d{4}\b).*\(\s*{$this->opt($this->t('Pick up'))}\s*(?<time>{$patterns['time']})\s*\)$/i", $dateDepVal, $m)) {
                $s->departure()->date(strtotime($m['time'], strtotime($m['date'])));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignProvider($parser->getHeaders());
        $email->setProviderCode($this->providerCode);

        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Prepaid Voucher - ')]", null, true, "/\-\s*([A-Z\d]{8,})$/"));

        $this->ParseTransferFlightHotel($email);

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

    public static function getEmailProviders()
    {
        return ['mta', 'savenio'];
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function assignProvider($headers): bool
    {
        if (!array_key_exists('from', $headers)) {
            $headers['from'] = '';
        }

        if (!array_key_exists('subject', $headers)) {
            $headers['subject'] = '';
        }

        if (stripos($headers['from'], '@savenio.com.au') !== false
            || $this->http->XPath->query("//tr/*[starts-with(normalize-space(),'Savenio')]")->length > 0
        ) {
            $this->providerCode = 'savenio';

            return true;
        }

        if (stripos($headers['from'], '@mtatravel.com.au') !== false
            || $this->http->XPath->query("//tr/*[starts-with(normalize-space(),'MTA Travel')]")->length > 0
        ) {
            $this->providerCode = 'mta';

            return true;
        }

        return false;
    }
}
