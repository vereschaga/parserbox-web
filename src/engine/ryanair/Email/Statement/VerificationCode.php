<?php

namespace AwardWallet\Engine\ryanair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "ryanair/statements/it-113616818.eml";

    private $detectSubject = [
        'Verification code',
    ];
    private $detectBody = [
        'en' => [
            'enter the below code to complete the authorisation process',
        ],
    ];

    public static $dictionary = [
        'en' => [
            'enter the below code to complete the authorisation process' => 'enter the below code to complete the authorisation process',
            'Hi ' => 'Hi ',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'myryanair@ryanair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (strpos($headers["from"], 'myryanair@ryanair.com') === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//text()[".$this->contains($detectBody)."]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $dict) {
            if (!isset($dict['enter the below code to complete the authorisation process'])) {
                continue;
            }
            $code = $this->http->FindSingleNode("//text()[".$this->contains($dict['enter the below code to complete the authorisation process'])."]/following::*[normalize-space()][1]",
                null, true, "/^\s*([a-z\d]{8})\s*$/");
            if (!empty($code)) {

                $email->add()->oneTimeCode()
                    ->setCode($code);

                $st = $email->add()->statement();

                $st->setMembership(true);
                $st->setNoBalance(true);

                $name = $this->http->FindSingleNode("//text()[".$this->starts($dict['Hi '] ?? '')."]", null, true,
                    "/^\s*" .$this->opt(($dict['Hi '] ?? ''))."([[:alpha:] \-]+),\s*$/");
                if (!empty($name)) {
                    $st->addProperty('Name', $name);
                }

            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode('|', array_map(function ($s) {
                return preg_quote($s, '/');
            }, $field)) . ')';
    }

}
