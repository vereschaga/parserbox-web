<?php

namespace AwardWallet\Engine\thaiair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Welcome extends \TAccountChecker
{
    public $mailFiles = "thaiair/it-78901510.eml";

    private $detectFrom = 'thaiairways.com';

    private $detectUniqueSubject = [
        // only subjects with provider name or rewards program name
        'Welcome to Royal Orchid Plus',
    ];

    private $detectBody = [
        "Thank you for joining Royal Orchid Plus.",
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectUniqueSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[" . $this->starts($this->detectBody) . "]")->length > 0) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();
        $name = $this->http->FindSingleNode("//text()[{$this->starts('Dear')}]/following::text()[normalize-space()][1]", null,
            false, "/(.+),$/");
        $st->addProperty('Name', $name);

        $number = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your membership number is')]/following::text()[normalize-space() and normalize-space() != ':'][1]");

        if (!empty($number)) {
            $st
                ->setLogin($number)
                ->setNumber($number)
                ->setNoBalance(true)
            ;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
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
}
