<?php

namespace AwardWallet\Engine\parkbost\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ParkingReceipt extends \TAccountChecker
{
    public $mailFiles = "parkbost/it-13093765.eml, parkbost/it-13223682.eml, parkbost/it-45177866.eml";

    public $lang = '';
    public static $dict = [
        'en' => [
            'Transaction Number' => ['Transaction Number'],
            'Zone Name'          => ['Zone Name'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//img[contains(@src,"/parkboston/background.")]')->length > 0
            || $this->http->XPath->query("//node()[{$this->contains([
                'Parking Receipt - ParkBoston', 'with ParkBoston', 'ParkBoston Team',
            ])}]")->length > 0
        ) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Parking Receipt - ParkBoston') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'donotreply@gopassport.com') !== false;
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
        $r = $email->add()->parking();

        $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('a customer service representative'))}]",
            null, true, "#([+(\d][-. \d)(]{5,}[\d)])\.?\s*$#");

        if ($phone) {
            $r->program()->phone($phone, $this->t('a customer service representative'));
        }
        $r->place()
            ->location($this->http->FindSingleNode("//text()[{$this->starts($this->t('Zone Name'))}]", null, false,
                "#{$this->opt($this->t('Zone Name'))}[:\s]+(.+)#"))
            ->phone($phone, false, true);

        $transactionNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Transaction Number'))}]");

        if (preg_match("#({$this->opt($this->t('Transaction Number'))})[:\s]+(\d+)#", $transactionNumber, $m)) {
            $r->general()->confirmation($m[2], $m[1]);
        }

        $r->booked()
            ->spot($this->http->FindSingleNode("//text()[{$this->starts($this->t('Zone Number'))}]", null, false,
                "#{$this->opt($this->t('Zone Number'))}[:\s]+(\d+)#"))
            ->plate($this->http->FindSingleNode("//text()[{$this->starts($this->t('License Plate'))}]", null, false,
                "#{$this->opt($this->t('License Plate'))}[:\s]+(.+)#"))
            ->start(strtotime($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Start'))}])[1]", null,
                false, "#{$this->opt($this->t('Start'))}[:\s]+(.+)#")))
            ->end(strtotime($this->http->FindSingleNode("(//text()[{$this->starts($this->t('End'))}])[1]", null, false,
                "#{$this->opt($this->t('End'))}[:\s]+(.+)#")));
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Parking Fee'))}]",
            null, false, "#{$this->opt($this->t('Parking Fee'))}[:\s]+(.+)#"));

        if ($tot['Total'] !== '') {
            $r->price()
                ->cost($tot['Total'])
                ->currency($tot['Currency']);
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Convenience Fee'))}]",
            null, false, "#{$this->opt($this->t('Convenience Fee'))}[:\s]+(.+)#"));

        if ($tot['Total'] !== '') {
            $r->price()
                ->fee($this->t('Convenience Fee'), $tot['Total']);
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Fee'))}]",
            null, false, "#{$this->opt($this->t('Total Fee'))}[:\s]+(.+)#"));

        if ($tot['Total'] !== '') {
            $r->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }
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
        if (!isset(self::$dict, $this->lang)) {
            return false;
        }

        foreach (self::$dict as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Transaction Number']) || empty($phrases['Zone Name'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Transaction Number'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Zone Name'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
