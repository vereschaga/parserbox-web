<?php

namespace AwardWallet\Engine\milleniumnclc\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Authentication extends \TAccountChecker
{
    public $mailFiles = "milleniumnclc/statements/it-229932590.eml, milleniumnclc/statements/it-227727834.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Millennium for Business Enquiry and Feedback') !== false
            || stripos($headers['subject'], 'New sign in from an unrecognised device on your My Millennium account') !== false
            ;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".millenniumhotels.com/") or contains(@href,"www.millenniumhotels.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Regards, Millennium Hotels and Resorts")]')->length === 0
        ) {
            return false;
        }

        return $this->getMembershipNumber() !== null || $this->isMembership();
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'noreply-reservations@millenniumhotels.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $firstName = $this->http->FindSingleNode("//*[ count(tr[normalize-space()])=2 and tr[normalize-space()][1][normalize-space()='First Name'] ]/tr[normalize-space()][2]", null, true, "/^{$patterns['travellerName']}$/u");
        $lastName = $this->http->FindSingleNode("//*[ count(tr[normalize-space()])=2 and tr[normalize-space()][1][normalize-space()='Last Name'] ]/tr[normalize-space()][2]", null, true, "/^{$patterns['travellerName']}$/u");
        $name = $firstName && $lastName ? ($firstName . ' ' . $lastName) : null;

        $login = $this->http->FindSingleNode("//*[ count(tr[normalize-space()])=2 and tr[normalize-space()][1][normalize-space()='Email'] ]/tr[normalize-space()][2]", null, true, '/^\S+@\S+$/');

        $number = $this->getMembershipNumber();

        if ($name || $login || $number) {
            $st = $email->add()->statement();

            if ($name) {
                $st->addProperty('Name', $name);
            }

            if ($login) {
                $st->setLogin($login);
            }

            if ($number) {
                $st->setNumber($number);
            }

            $st->setNoBalance(true);
        } elseif ($this->isMembership()) {
            $st = $email->add()->statement();
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(): bool
    {
        $phrases = [
            'A new device was used to sign in to your My Millennium Account.',
        ];

        foreach ($phrases as $phrase) {
            if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    private function getMembershipNumber(): ?string
    {
        return $this->http->FindSingleNode("//*[ count(tr[normalize-space()])=2 and tr[normalize-space()][1][normalize-space()='Membership Number'] ]/tr[normalize-space()][2]", null, true, '/^\d{8,}$/');
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
}
