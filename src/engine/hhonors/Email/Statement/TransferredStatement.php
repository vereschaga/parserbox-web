<?php

namespace AwardWallet\Engine\hhonors\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class TransferredStatement extends \TAccountChecker
{
    public $mailFiles = "hhonors/statements/it-63063716.eml, hhonors/statements/it-63071087.eml, hhonors/statements/it-69866507.eml";
    private $lang = '';
    private $reFrom = ['hiltonhonors@points-mail.com'];
    private $reProvider = ['Hilton Honors'];
    private $reSubject = [
        'Hilton Honors Points Have Been Transferred to You!',
        'Hilton Honors Points Transfer Receipt',
        'Hilton Honors Points Transfer Confirmation',
    ];

    private static $dictionary = [
        'en' => [
            'Recipient Name' => ['Recipient Name', 'Points Deposited to', 'Points Transferred To'],
        ],
    ];

    private $emailRecipient = false;
    private $emailTransfer = false;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]';

        $this->detectRecipient();
        $this->detectTransfer();

        $this->assignLang();
        $email->setType('TransferredStatement' . ucfirst($this->lang));

        $st = $email->add()->statement();

        if ($this->emailRecipient && !$this->emailTransfer) {
            // it-63063716.eml, it-63071087.eml
            $this->logger->debug('This email belongs to recipient side.');

            $name = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Recipient Name'))}]", null, false, "/{$this->opt($this->t('Recipient Name'))}\s*:\s*({$patterns['travellerName']})$/u")
                ?? $this->http->FindSingleNode("//div[not(.//tr) and {$this->starts($this->t('Recipient Name'))}]", null, false, "/{$this->opt($this->t('Recipient Name'))}\s*:\s*({$patterns['travellerName']})$/u");
            $st->addProperty('Name', $name);

            $number = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->contains($this->t('Hilton Honors Number'))}]", null, false, "/{$this->opt($this->t('Hilton Honors Number'))}\s*:\s*(\d+)\s*$/")
                ?? $this->http->FindSingleNode("//div[not(.//tr) and {$this->contains($this->t('Hilton Honors Number'))}]", null, false, "/{$this->opt($this->t('Hilton Honors Number'))}\s*:\s*(\d+)\s*$/");

            if ($number !== null) {
                $st->setNumber($number)
                    ->setLogin($number);
            }

            if ($name || $number !== null) {
                $st->setNoBalance(true);
            }
        } elseif ($this->emailTransfer && !$this->emailRecipient) {
            // it-69866507.eml
            $this->logger->debug('This email belongs to transfer side.');

            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/^{$this->opt($this->t('Dear'))}\s+({$patterns['travellerName']})(?:\s*[,.;:?!]|$)/u");

            if ($name) {
                $st->addProperty('Name', $name);
                $st->setNoBalance(true);
            }

            if (!$name) {
                $st->setMembership(true);
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        return ($this->detectRecipient() || $this->detectTransfer()) && $this->assignLang();
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function detectRecipient(): bool
    {
        $phrases = [
            'Congratulations! You have received',
            'just sent you',
        ];

        foreach ($phrases as $phrase) {
            if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                $this->emailRecipient = true;

                return true;
            }
        }

        return false;
    }

    private function detectTransfer(): bool
    {
        $phrases = [
            'Thank you for your recent transfer of Hilton Honors Points',
            "You've successfully transferred Hilton Honors Points",
        ];

        foreach ($phrases as $phrase) {
            if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                $this->emailTransfer = true;

                return true;
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
            if (!is_string($lang) || empty($phrases['Recipient Name'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Recipient Name'])}]")->length > 0) {
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
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

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
