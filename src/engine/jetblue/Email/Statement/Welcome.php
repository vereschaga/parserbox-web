<?php

namespace AwardWallet\Engine\jetblue\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Welcome extends \TAccountChecker
{
    public $mailFiles = "jetblue/it-62722881.eml, jetblue/it-62782858.eml, jetblue/it-62869088.eml, jetblue/it-62955511.eml, jetblue/statements/it-103611675.eml";

    public static $dictionary = [
        'en' => [
            'Dear ' => ['Dear ', 'Hello,', 'Hi,'],
        ],
    ];

    private $detectFrom = "jetblueairways@email.jetblue.com";
    private $detectSubjects = [
        "Welcome to TrueBlue!",
        "Your TrueBlue Password",
        "TrueBlue & You:",
        "Happy Birthday from TrueBlue!",
    ];

    private $detectBody = [
        "The TrueBlue team ",
        "Welcome to TrueBlue",
        "thanks again for being a TrueBlue member",
        "It's been too long since your last JetBlue trip",
    ];
    private $detectBodyAlt = [
        "Welcome to TrueBlue.",
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubjects) {
            if (stripos($headers['subject'], $dSubjects) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($this->detectBodyAlt as $detectBodyAlt) {
            if ($this->http->XPath->query("//img[contains(@alt, '" . $detectBodyAlt . "')]/@src")->length > 0) {
                return true;
            }
        }

        if ($this->http->XPath->query("//*[" . $this->contains($this->detectBody) . "]")->length > 0) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailFromProvider($parser->getCleanFrom()) === false || $this->detectEmailByBody($parser) === false) {
            return false;
        }

        $xpathNoHide = "not(ancestor-or-self::*[contains(@style,'display:none') or contains(@style,'display: none')])";

        $st = $email->add()->statement();

        // Login
        $login = $this->http->FindSingleNode("//text()[normalize-space()='This e-mail was sent to' and {$xpathNoHide}]/following::text()[1]", null, true,
            "#^\s*(.+@.+\.[^\d\W]+)\s*$#u");

        if (empty($login) && stripos($parser->getSubject(), 'password') !== false) {
            $st->setNoBalance(true);
            $st->setMembership(true);
        } else {
            $st->setNoBalance(true);
            $st->setMembership(true);
            $st->setLogin($login);
        }

//        // Name
        $name = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]", null, true,
            "/^\s*{$this->preg_implode($this->t("Dear "))}\s*([^\d\W]+(?: [^\d\W]+){0,4})\s*[,.]\s*$/u");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $class = explode('\\', __CLASS__);
        $email->setType('Statement' . end($class));

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
