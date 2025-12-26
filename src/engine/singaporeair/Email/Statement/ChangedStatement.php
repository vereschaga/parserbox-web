<?php

namespace AwardWallet\Engine\singaporeair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ChangedStatement extends \TAccountChecker
{
    public $mailFiles = "singaporeair/statements/it-62542410.eml, singaporeair/statements/it-62542424.eml, singaporeair/statements/it-62951477.eml, singaporeair/statements/it-72611008.eml, singaporeair/statements/it-73641866.eml, singaporeair/statements/it-77495721.eml, singaporeair/statements/it-99727422.eml";
    private $lang = '';

    private $reProvider = ['SingaporeAir', 'KrisFlyer', 'KrisPay', 'Kris+'];
    private $reSubject = [
        'Your KrisFlyer Account Password Has Been Changed',
        'Your KrisFlyer account has been locked',
        'Expiry Of Your KrisFlyer Account',
        'Reset your password',
        'Your password has been changed',
        'Your password has been reset',
        'Expiry Of Your KrisFlyer Account',
        'Expiry of your KrisFlyer account',
        'Status match extension request',
    ];
    private $reBody = [
        'en' => [
            'Last year, we launched KrisFlyer Miles of Good',
            'Your KrisFlyer Account Password Has Been Changed',
            'We have expanded our KrisFlyer',
            'Your password has been changed',
            'Your KrisFlyer account has been locked',
            'Your password has been changed',
            'We have received a request to reset the password of your account.',
            'Your password has been reset',
            'This is a gentle reminder that as a result of inactivity,',
            'Thank you for being a member of KrisFlyer',
            'We wish to share that we are currently extending members',
            'Previous KrisFlyer miles', // it-72611008.eml
            'CURRENT KRISFLYER MILES', // it-99727422.eml
            'convert your bank reward points to KrisFlyer',
            'our specially curated list of brand partners at KrisFlyer Spree',
            'each merchant page after logging in to KrisFlyer Spree',
            'KrisFlyer miles at your favourite online stores',
            'Meet our KrisPay Partners',
            'Specially for KrisFlyer members',
            'KrisShop Spectacular Deals',
            'KrisPay turns 1 this',
            'Shop and win in KrisShop\'s Great Weekly Giveaways',
            'KrisFlyer miles to benefit essential heroes',
            'The validity of your KrisFlyer miles have been extended successfully',
            'Convert your bank reward points to KrisFlyer miles',
            'shop at KrisShop.com this',
            'Celebrate KrisFlyer Spree',
            'Subscribe to the KrisFlyer',
            'share that KrisFlyer members',
            'KrisFlyer turns 21 this year and we are excited to celebrate this milestone with you',
            'Log in to your KrisFlyer account to use',
            'First time shopping on KrisFlyer Spree?',
            'Kris+ wishes you happy holidays',
            'Offers last for a limited time only, start shopping now!',
            'bonus KrisFlyer miles when you shop with',
            'You have successfully voted for everyday essential hero',
            'for the KrisFlyer Spree monthly eNewsletter',
            'Find the perfect gift for a loved one or treat yourself on KrisFlyer Spree',
            'Here at Kris+',
        ],
    ];
    private static $dictionary = [
        'en' => [
            'Tier status:'        => ['Tier status:', 'Tier Status:'],
            'Membership number:'  => ['Membership number:', 'Membership no:', 'KrisFlyer Membership Number:', 'Membership No.', 'membership no:', 'MEMBERSHIP NO:'],
            'balanceName'         => ['KrisFlyer miles remaining', 'CURRENT KRISFLYER MILES'],
            'Current Elite miles' => ['Current Elite miles', 'Current Elite Miles'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->assignLang() !== true) {
            $this->logger->error('Lang not found!');

            return false;
        }

        $xpathNoHide = "not(ancestor-or-self::*[contains(@style,'display:none') or contains(@style,'display: none')])";

        $st = $email->add()->statement();
        $number = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Membership number:'))}]/..)[1]", null, true, '/:\s*([\d\-]+)/');

        if (empty($number)) {
            $number = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Membership number:'))}])[1]/following::text()[normalize-space()][1]", null, true, '/^([\d]+)^/');
        }

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Membership No:'))}]", null, true,
                "/{$this->opt($this->t('Membership No:'))}\s*(\d+)$/");
        }

        if (!empty($number)) {
            $st
                ->addProperty('AccountNumber', $number)
                ->setNumber($number)
                ->setLogin($number)
            ;

            $name = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Dear '))}])[1]", null,
                false, "/{$this->opt($this->t('Dear '))}\w+\s+([\w\s\-]+),/");

            if (empty($name)) {
                $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Membership number:'))}]/following::text()[{$this->starts($this->t('Dear '))}][last()]", null,
                    false, "/^{$this->opt($this->t('Dear '))}\w+\s+([\w\s\-]+),?$/");
            }

            $st->addProperty('Name', $name);

            $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Tier status:'))}][{$xpathNoHide}]", null, true, "/{$this->opt($this->t('Tier status:'))}\s*(\D+)$/");

            if (!empty($status)) {
                $st->addProperty('CurrentTier', $status);
            }

            $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('balanceName'))}]/ancestor::tr[1]/*[2]", null, true, "/^\d+$/")
                ?? $this->http->FindSingleNode("//tr[{$this->eq($this->t('balanceName'))} and {$xpathNoHide}]/preceding-sibling::tr[normalize-space()][1]", null, true, "/^\d+$/") // it-99727422.eml
            ;

            if ($balance !== null) {
                $st->setBalance($balance);

                // it-99727422.eml
                $balanceDate = $this->http->FindSingleNode("//tr[{$this->eq($this->t('balanceName'))} and {$xpathNoHide}]/preceding::tr[not(.//tr) and {$this->starts($this->t('AS OF'))} and {$xpathNoHide}]", null, true, "/^{$this->opt($this->t('AS OF'))}\s+(.*\d.*)$/");

                if ($balanceDate) {
                    $st->parseBalanceDate($balanceDate);
                }
            } else {
                $st->setNoBalance(true);
            }

            $eliteMiles = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Current Elite miles'))}]/ancestor::tr[1]/*[2]", null, true, "/^\d+$/")
                ?? $this->http->FindSingleNode("//tr[{$this->eq($this->t('Current Elite miles'))} and {$xpathNoHide}]/preceding-sibling::tr[normalize-space()][1]", null, true, "/^\d+$/") // it-99727422.eml
            ;

            if ($eliteMiles !== null) {
                $st->addProperty('CurrentEliteMiles', $eliteMiles);
            }

            $currentPPSValue = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Current PPS Value'))}]/ancestor::tr[1]/*[2]", null, true, "/^\d+$/")
                ?? $this->http->FindSingleNode("//tr[{$this->eq($this->t('Current PPS Value'))} and {$xpathNoHide}]/preceding-sibling::tr[normalize-space()][1]", null, true, "/^\d+$/") // it-99727422.eml
            ;

            if ($currentPPSValue !== null) {
                $st->addProperty('CurrentPPSValue', $currentPPSValue);
            }
        } elseif ($this->http->XPath->query("(//text()[{$this->contains($this->t('Membership number:'))}]/..)[1]")->length == 0) {
            $name = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Dear '))}])[1]", null,
                false, "/{$this->opt($this->t('Dear '))}\w+\s+([\w\s\-]+)\,?/");

            if (empty($name)) {
                $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Membership number:'))}]/following::text()[{$this->starts($this->t('Dear '))}][last()]", null,
                    false, "/^{$this->opt($this->t('Dear '))}\w+\s+([\w\s\-]+),?$/");
            }

            if (!empty($name)) {
                $st->addProperty('Name', trim($name, ','));
                $st->setNoBalance(true);
            } else {
                $st->setMembership(true);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/@(?:email\.)?singaporeair\.com\b/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $value) {
            foreach ($value as $i => $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        if ($this->http->XPath->query("/descendant::img[1][contains(@src, 'http://m.email.singaporeair.com/res/img/0D7F941F1EE8FC299EFD5716F21D9FAD.jpg') " .
                "or contains(@src, 'http://www.singaporeair.com/saar5/images/local/ot/lmd/edm/2018templates/Krisshop/banner1.jpg')]/@src")->count() > 0) {
            return true;
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
