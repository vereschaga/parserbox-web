<?php

namespace AwardWallet\Engine\sncf\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Program extends \TAccountChecker
{
    public $mailFiles = "sncf/statements/it-124145381.eml";

    private $detectFrom = "programmevoyageur@info.sncf.com";
    private $detectBody = [
        // fr
        'Vous recevez ce message car vous ГЄtes inscrite au programme de fidГ©litГ© SNCF.',
        'Vous recevez ce message car vous êtes inscrite au programme de fidélité SNCF.',
    ];

    public $lang;
    public static $dictionary = [
        'fr' => [
//            '> MON ESPACE PERSONNEL' => '',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return $this->detectEmailFromProvider($headers['from']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getCleanFrom()) !== true) {
            return false;
        }

        if ($this->http->XPath->query("//*[".$this->contains($this->detectBody)."]")->length > 0) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $number = $this->http->FindSingleNode("//text()[".$this->eq($this->t('> MON ESPACE PERSONNEL'))."]/preceding::text()[normalize-space()][1]",
            null, true, "/^\s*(\d{10,})\s*$/");
        $name = $this->http->FindSingleNode("//text()[".$this->eq($this->t('> MON ESPACE PERSONNEL'))."]/preceding::text()[normalize-space()][2]",
            null, true, "/^\s*([[:alpha:]]+([ \-][[:alpha:]]+){0,5})\s*$/u");

        if (!empty($number) && !empty($name)) {
            $st = $email->add()->statement();
            $st
                ->setNumber($number)
                ->addProperty('Name', $name)
                ->setNoBalance(true)
            ;

        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return ['fr'];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function t($phrase)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }


    private function eq($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }


    private function starts($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }


    private function opt($field, $delimiter = '/')
    {
        $field = (array)$field;
        if (empty($field)) {
            $field = ['false'];
        }
        return '(?:' . implode("|", array_map(function ($s) use($delimiter) {
                return str_replace(' ', '\s+', preg_quote($s, $delimiter));
            }, $field)) . ')';
    }


}