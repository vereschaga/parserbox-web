<?php

namespace AwardWallet\Engine\ryanair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Member extends \TAccountChecker
{
    public $mailFiles = "ryanair/statements/it-113616818.eml";

    private $detectBody = [
        'en' => [
            'To reset your myRyanair account password use this eight-digit security code',
            'To verify your myRyanair account please use this eight-digit security code',
            'Need to reset your password to get into your myRyanair account',
            'Welcome to myRyanair. We can’t wait to help you to plan some grand adventures',
            'Thanks for your request to change your myRyanair login email address',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'noreply@ryanair.com') !== false || strpos($from, 'myryanair@ryanair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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
        $st = $email->add()->statement();

        $st->setMembership(true);
        $st->setNoBalance(true);

        $name = $this->http->FindSingleNode("//text()[".$this->starts('Hi ')."]", null, true,
            "/^\s*Hi ([[:alpha:] \-]+),\s*$/");
        if (!empty($name)) {
            $st->addProperty('Name', $name);
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

}
