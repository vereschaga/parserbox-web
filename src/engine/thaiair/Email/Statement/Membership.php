<?php

namespace AwardWallet\Engine\thaiair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Membership extends \TAccountChecker
{
    public $mailFiles = "thaiair/it-78820578.eml";

    private $detectFrom = 'thaiairways.com';

    private $detectSubject = [
        'Click confirm to get reply or View Frequently Asked Questions:',
    ];
    private $detectUniqueSubject = [
        // only subjects with provider name or rewards program name
        //        '',
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

        if ($this->http->FindSingleNode("//text()[" . $this->contains(['Dear Valued Member,']) . "]")) {
            $st
                ->setMembership(true)
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
}
