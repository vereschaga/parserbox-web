<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Schema\Parser\Email\Email;

class JunkReservationInfoFor extends \TAccountChecker
{
    public $mailFiles = "hertz/it-49332810.eml, hertz/it-49402897.eml";

    public $reFrom = ["@emails.hertz.com"];
    public $reBody = [
        'en' => ['Your Reservation Information for'],
        'fr' => ['Vos informations de réservation pour'],
    ];
    public $reSubject = [
        '/Your Reservation Information for [A-Z\d]{7,}$/',
        '/Vos informations de réservation pour [A-Z\d]{7,}$/',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Pickup Location:'    => 'Pickup Location:',
            'Pickup Information'  => 'Pickup Information',
            'We\'re here'         => 'We\'re here to help you get on the road faster.',
            'Hours of Operation:' => 'Hours of Operation:',
        ],
        'fr' => [
            'Pickup Location:'    => 'Lieu de retrait :',
            'Pickup Information'  => 'Informations sur le retrait',
            'We\'re here'         => 'Nous sommes là pour vous aider à prendre la route plus vite.',
            'Hours of Operation:' => 'Horaires d\'ouverture :',
        ],
    ];
    private $keywordProv = 'Hertz';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.emails.hertz.com/')] |//a[contains(@href,'.emails.hertz.com/')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $headers["subject"]) > 0)
                    && preg_match($reSubject, $headers["subject"])
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function parseEmail(Email $email)
    {
        if (stripos(text($this->http->Response['body']), 'Drop') !== false) {
            $this->logger->notice('check format. it could be not junk');

            return false;
        }
        $condition1 = $this->http->XPath->query("//*[{$this->contains($this->t('We\'re here'))}]")->length > 0;
        $condition2 = $this->http->XPath->query("//*[{$this->eq($this->t('Hours of Operation:'))}]")->length > 0;
        $condition3 = !empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup Information'))}]/following::text()[normalize-space()!=''][1][{$this->eq($this->t('Pickup Location:'))}]"));

        if ($condition1 && $condition2 && $condition3) {
            $email->setIsJunk(true);
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Pickup Location:"], $words["Pickup Information"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words["Pickup Location:"])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words["Pickup Information"])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
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
