<?php

namespace AwardWallet\Engine\lufthansa\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class YourMileageBalance2024 extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-655675245.eml, lufthansa/it-664942417.eml, lufthansa/it-665100182.eml";

    public $lang = '';

    public $subjects = [
        // en
        'Your current mileage balance in',
        // de
        'Ihr aktueller Meilenstand im ',
    ];
    public static $dictionary = [
        'en' => [
            'travellerTitle'        => ['Mr.', 'Ms.', 'Dr.'], // from subject
            'Status valid until'    => 'Status valid until',
            'Miles'                 => 'Miles',
            'eVoucher'              => 'eVoucher',
            'Mileage balance'       => 'Mileage balance',
            'Account balance from:' => 'Account balance from:',
        ],
        'de' => [
            'travellerTitle'        => ['Herr Dr.', 'Herr', 'Frau'], // from subject
            'Status valid until'    => 'Status gültig bis',
            'Miles'                 => 'Meilen',
            'eVoucher'              => 'eVoucher',
            'Mileage balance'       => 'Meilenkonto',
            'Account balance from:' => 'Kontostand vom:',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@mailing.milesandmore.com') !== false;
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
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".miles-and-more.com/") or contains(@href,".miles-and-more.com%2F")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && $this->findRoot()->length === 1;
    }

    public function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $phrases) {
            if (empty($phrases['Mileage balance']) || empty($phrases['Account balance from:'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Mileage balance'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Account balance from:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function findRoot(): \DOMNodeList
    {
        $visible = "[not(ancestor::*[contains(@style, 'display: none') or contains(@style, 'display:none')])]";
        $xpath = "//text()[{$this->eq($this->t('Mileage balance'))}]{$visible}/ancestor::tr[.//img/@src[{$this->contains('status-images')}]][1][count(*[normalize-space()]) = 1 or count(*[normalize-space()]) = 2]"
            . "[following::text()[normalize-space()]{$visible}[1][{$this->starts($this->t('Account balance from:'))}]]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        return $this->http->XPath->query($xpath);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourMileageBalance' . ucfirst($this->lang));

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);

        $visible = "[not(ancestor::*[contains(@style, 'display: none') or contains(@style, 'display:none')])]";

        $st = $email->add()->statement();

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        if (preg_match("/, {$this->opt($this->t('travellerTitle'))}\s*(?<name>\b{$patterns['travellerName']})\s*$/u", $parser->getSubject(), $m)) {
            // Your current mileage balance in March, Mr. Wang
            $st->addProperty('Name', $m['name']);
        }

        $date = $this->http->FindSingleNode("following::text()[normalize-space()]{$visible}[1][{$this->starts($this->t('Account balance from:'))}]",
            $root, true, "/{$this->opt($this->t('Account balance from:'))}\s*(.*)$/u");
        $st->setBalanceDate($this->normalizeDate($date));

        $balance = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Miles'))}]{$visible}/following::text(){$visible}[normalize-space()][1]",
            $root, true, "/^\s*(\d[,.\d ]*)\s*$/");
        $st->setBalance($this->amount($balance));

        $status = $this->http->FindSingleNode(".//img[@src[contains(., 'status-images')]]/@alt[not(contains(., 'Image removed'))]");
        $st->addProperty('Status', $status);

        $imgs = implode("\n", array_map('urldecode', $this->http->FindNodes(".//img/@src[contains(., 'miles-and-more')]", $root)));

        if (preg_match("/label=Points&percentage=[\d.]+&amount=(?<value>\d[\d\.,]*)&threshold=(?<needValue>\d[\d\.,]*)(&.*)?\s*$/m", $imgs, $m)) {
            // https://www.miles-and-more.com/lh_shared/mmg/circleimage.png?version=2&label=Points&percentage=0.00&amount=0&threshold=650
            $m = $this->amount($m);
            $st
                ->addProperty('Points', $m['value'])
                ->addProperty('PointsNeededToNextLevel', ($m['needValue'] > $m['value']) ? $m['needValue'] - $m['value'] : 0)
            ;
        } else {
            // for error
            $st->setBalance(null);
        }

        if (preg_match("/label=Qualifying Points&percentage=[\d.]+&amount=(?<value>\d[\d\.,]*)&threshold=(?<needValue>\d[\d\.,]*)(&.*)?\s*$/m", $imgs, $m)) {
            // https://www.miles-and-more.com/lh_shared/mmg/circleimage.png?version=2&label=Qualifying%20Points&percentage=0.00&amount=0&threshold=325
            $m = $this->amount($m);
            $st
                ->addProperty('QualifyingPoints', $m['value'])
                ->addProperty('QualifyingPointsNeededToNextLevel', ($m['needValue'] > $m['value']) ? $m['needValue'] - $m['value'] : 0)
            ;
        } else {
            // for error
            $st->setBalance(null);
        }

        $eVoucher = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('eVoucher'))}]{$visible}/following::text(){$visible}[normalize-space()][1]",
            $root, true, "/^\s*(\d[,.\'\d ]*)\s*$/");

        if ($eVoucher !== null) {
            $st
                ->addProperty('EVouchers', $eVoucher);
        }

        $statusDate = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Status valid until'))}]{$visible}",
            $root, true, "/{$this->opt($this->t('Status valid until'))}\s+(.*)$/");

        if ($statusDate !== null) {
            $st
                ->addProperty('Statusvalidityuntil', $statusDate);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function amount($s)
    {
        return preg_replace('/\W+/', '', $s);
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function normalizeDate($date)
    {
        $in = [
            // 14.03.24
            "/^\s*(\d{1,2})\.(\d{2})\.(\d{2})\s*$/",
            // 3/14/24
            "/^\s*(\d{1,2})\/(\d{1,2})\/(\d{2})\s*$/",
        ];
        $out = [
            "$1.$2.20$3",
            "20$3-$1-$2",
        ];
        $date = preg_replace($in, $out, $date);

        return strtotime($date);
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
