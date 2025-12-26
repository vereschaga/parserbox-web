<?php

namespace AwardWallet\Engine\aeroplan\Email\Statement;

use AwardWallet\Engine\RaRegistrationData;
use AwardWallet\Schema\Parser\Email\Email;

class ActivateYourAccount extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-889991913.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Here is your Aeroplan number:'                                           => 'Here is your Aeroplan number:',
            'Please click on the link to activate and access your Aeroplan® account.' => 'Please click on the link to activate and access your Aeroplan® account.',
            'Activate your account'                                                   => 'Activate your account',
        ],
    ];

    private $detectFrom = "info@communications.aeroplan.com";
    private $detectSubject = [
        // en
        'Activate your Aeroplan account now',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]communications\.aeroplan\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a/@href[{$this->contains(['.aircanada.com'])}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['your Aeroplan® account'])}]")->length === 0
        ) {
            return false;
        }
        // detect Format
        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Here is your Aeroplan number:']) && $this->http->XPath->query("//*[{$this->starts($dict['Here is your Aeroplan number:'])}]")->length > 0
                && !empty($dict['Please click on the link to activate and access your Aeroplan® account.']) && $this->http->XPath->query("//*[{$this->contains($dict['Please click on the link to activate and access your Aeroplan® account.'])}]")->length > 0
                && !empty($dict['Activate your account']) && $this->http->XPath->query("//a[{$this->eq($dict['Activate your account'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $code = $this->http->FindSingleNode("//a[{$this->eq($this->t('Activate your account'))}]/@href[contains(., 'gigya.com/accounts.verifyEmail?apiKey=')]");
        // https://accounts.us1.gigya.com/accounts.verifyEmail?apiKey=3_zA5TRSBDlwybsx_1k8EyncAfJ2b62DJnoxPW60q4X9MqmBDJh1v_8QYaOTG8kZ8S&ticket=v3_tk1.VZK_aIZ7B8U1rJYrtz6-yaOOlzK2wzoGugV3KBnZngo&lang=en
        if (!empty($code)) {
            $emailTo = $parser->getCleanTo();

            if (RaRegistrationData::isInAccountsEmails($emailTo) === true) {
                // https://redmine.awardwallet.com/issues/20608#note-293
                if (!empty($code)) {
                    $c = $email->add()->oneTimeCode();
                    $c->setCodeAttr("/^https\:\/\/accounts\.[a-z\d]{2,5}\.gigya\.com\/accounts\.verifyEmail\?apiKey=[A-z\d\-\_]+&ticket=v3_tk\d\.[A-z\d\-\_]+&lang=[a-z]{2}$/u",
                        2000);
                    $c->setCode($code);
                }
            } else {
                $email->setIsJunk(true);
            }
        }

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
        return 0;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Please click on the link to activate and access your Aeroplan® account."]) && !empty($dict["Activate your account"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Please click on the link to activate and access your Aeroplan® account.'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Activate your account'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods
    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
