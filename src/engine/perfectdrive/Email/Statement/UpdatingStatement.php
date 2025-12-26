<?php

namespace AwardWallet\Engine\perfectdrive\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class UpdatingStatement extends \TAccountChecker
{
    public $mailFiles = "perfectdrive/statements/it-63113831.eml, perfectdrive/statements/it-63360502.eml, perfectdrive/statements/it-63474969.eml, perfectdrive/statements/it-65603165.eml, perfectdrive/statements/it-65501725.eml";
    private $lang = '';
    private $reFrom = ['@e.budget.com'];
    private $reProvider = ['Budget'];
    private $reSubject = [
        ', thank you for updating your Budget profile!',
        ', urgent update. Your credit card is about to expire.',
        'Your Profile Update',
        '- activate your account',
        'Please activate your account',
        'Your account has not been activated',
        'Your Account Has Been Locked',
        'Change Password Confirmation',
    ];
    private $reBody = [
        'en' => [
            [
                ['The following changes have been made to your profile and your pending reservation', 'Thank you for keeping your Budget profile up to date.'],
                'Thank you for choosing Budget.',
            ],
            [
                'Your credit card is about to expire. Please take a second to',
                'update your card today',
            ],
            [
                'This is confirmation that your email address has been successfully changed.',
                'Your security and privacy are important to us',
            ],
            [
                'This is confirmation that your password has been successfully changed',
                'If you did not change your password',
            ],
            [
                ['Your Username', 'Did you know you are closer to getting Fastbreak benefits?'],
                ['Your RapidRez Number', 'Your Fastbreak ID'],
            ],
            [
                'Complete your profile on Budget',
                'Please activate your account',
            ],
            [
                'your account has been locked out',
                'to unlock your account',
            ],
            [
                'Your membership benefits',
                'Thank you for joining Fastbreak',
            ],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'yourNumber'   => ['Your RapidRez Number', 'Your Fastbreak ID'],
            'isMembership' => [
                'This is confirmation that your email address has been successfully changed.',
                'your account has been locked out',
                'This is confirmation that your password has been successfully changed',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('UpdatingStatement' . ucfirst($this->lang));

        $st = $email->add()->statement();

        $username = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][{$this->eq($this->t('Your Username'))}] ]/*[2][normalize-space()]");

        $accountNumber = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][{$this->eq($this->t('yourNumber'))}] ]/*[2][normalize-space()]");

        if (!$accountNumber) {
            $accountNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your membership number is'))}]", null, true, "/{$this->opt($this->t('Your membership number is'))}\s*([*A-Z\d]{5,})(?:\s*[.,;!]|$)/");
        }

        if ($accountNumber) {
            $st->addProperty('AccountNumber', $accountNumber);
        }

        // ***60C
        $patterns['numberMaskedLeft'] = '/^[*]{3,}([A-Z\d]{2,})$/';

        if ($username) {
            if (preg_match($patterns['numberMaskedLeft'], $username, $m)) {
                $st->setLogin($m[1])->masked();
            } else {
                $st->setLogin($username);
            }
        } elseif ($accountNumber) {
            if (preg_match($patterns['numberMaskedLeft'], $accountNumber, $m)) {
                $st->setLogin($m[1])->masked();
            } else {
                $st->setLogin($accountNumber);
            }
        }

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null,
            true, "/{$this->opt($this->t('Dear '))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u");

        if ($name) {
            $st->addProperty('Name', $name);
        }

        $isMembership = $this->http->FindSingleNode("//text()[{$this->contains($this->t('isMembership'))}]");

        if ($isMembership) {
            $st->setMembership(true);
        }

        if ($username || $accountNumber || $name || $isMembership) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
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
