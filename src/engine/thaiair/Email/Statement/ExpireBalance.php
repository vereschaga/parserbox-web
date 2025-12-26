<?php

namespace AwardWallet\Engine\thaiair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class ExpireBalance extends \TAccountChecker
{
    public $mailFiles = "thaiair/it-78833044.eml, thaiair/it-79013123.eml";

    private $detectFrom = 'thaiairways.com';

    private $detectSubject = [
        'Your Expiry Miles Notification',
    ];
    private $detectUniqueSubject = [
        // only subjects with provider name or rewards program name
        'Royal Orchid Plus Activities',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) !== false) {
            foreach ($this->detectSubject as $dSubject) {
                if (stripos($headers['subject'], $dSubject) !== false) {
                    return true;
                }
            }
        }

        foreach ($this->detectUniqueSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts('Dear')}]", null, true, "/Dear (.+),/");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts('Dear')}]/following::text()[normalize-space()][1][following::text()[normalize-space()][1][normalize-space()=',']]");
        }
        $st->addProperty('Name', $name);

        $info = $this->http->FindSingleNode("//text()[" . $this->contains([' expiring miles on ']) . "]");

        if (preg_match("/you will have (\d+) expiring miles on ([\d\.]+)\./", $info, $m)) {
            // Please be informed you will have 9982 expiring miles on 31.03.2020.
            $st
                ->setMembership(true)
                ->setNoBalance(true)
                ->addProperty("MilesToExpire", $m[1])
            ;
        }

        if (!empty($this->http->FindSingleNode("//text()[" . $this->contains(['your Royal Orchid Plus activities attached']) . "]"))) {
            $pdfs = $parser->searchAttachmentByName(".*\.pdf");

            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (preg_match("/www\.thaiairways\.com\/rop/", $text)) {
                    if (preg_match("/\n *Tier: *([A-Z]+) {3,}/", $text, $m)) {
                        $st->addProperty("Status", $m[1]);
                    }

                    if (preg_match("/\n *Statement Date: *(.+?) {3,}/", $text, $m)) {
                        $st->setBalanceDate(strtotime($m[1]));
                    }

                    if (preg_match("/\n *Current Miles: *(\d+) {3,}/", $text, $m)) {
                        $st->setBalance($m[1]);
                    }

                    if (preg_match("/ {3,}Points to Expire: *(\d+)\n/", $text, $m)) {
                        $st->addProperty("MilesToExpire", $m[1]);

                        if (preg_match("/ {3,}Points Expire Date: *(.+)/", $text, $mat)) {
                            $st->setExpirationDate(strtotime($mat[1]));
                        }
                    }
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
