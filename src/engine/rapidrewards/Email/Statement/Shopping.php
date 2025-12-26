<?php

namespace AwardWallet\Engine\rapidrewards\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Shopping extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/statements/it-78981440.eml, rapidrewards/statements/it-79007103.eml, rapidrewards/statements/it-79011952.eml, rapidrewards/statements/it-79012737.eml, rapidrewards/statements/it-79104612.eml";

    private $detectFrom = "noreply@rapidrewardsshopping.com";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $class = explode('\\', __CLASS__);
        $email->setType('Statement' . end($class));

        $userEmail = $this->http->FindSingleNode("//text()[normalize-space() = 'This email was sent to']/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\S+@\S+\.[[:alpha:]]+)\s*$/");

        if (!empty($userEmail)) {
            $email->setUserEmail($userEmail);
        }

        $trXpath = "//tr[td[1][starts-with(normalize-space(), 'Hi,')] and td[last()][starts-with(normalize-space(), 'Total points earned')]]";
        $info = array_values(array_filter($this->http->FindNodes($trXpath . "/td")));

        if (!empty($info)) {
            // Hi, Tonya  |  Total points earned
            //  #***4936  |  5,723*
            $name = $info[0];

            if (!empty($name) && preg_match("/^\s*Hi,\s*([[:alpha:] \-]+?)\s*\#\*+(\d{4})$/", $name, $m)) {
                $st->addProperty('Name', trim($m[1]));
                $st->setNumber($m[2])->masked();
                $st->setLogin($m[2])->masked();
            }
            // Total points earned is NOT balance
            $st->setNoBalance(true);

            return $email;
        }

        $info = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hello ')]/ancestor::td[1][contains(., 'RR#')]");

        if (!empty($info)) {
            // Hello Robert | RR#***0742
            // Hello Hong | RR#20496848470
            if (preg_match("/^\s*Hello\s*([[:alpha:] \-]+?)\s*\|\s*RR\#\s*\*+(\d{4})$/", $info, $m)) {
                $st->addProperty('Name', trim($m[1]));
                $st->setNumber($m[2])->masked();
                $st->setLogin($m[2])->masked();

                $st->setNoBalance(true);
            } elseif (preg_match("/^\s*Hello\s*([[:alpha:] \-]+?)\s*\|\s*RR\#\s*(\d{5,})$/", $info, $m)) {
                $st->addProperty('Name', trim($m[1]));
                $st->setNumber($m[2]);
                $st->setLogin($m[2]);

                $st->setNoBalance(true);
            }

            return $email;
        }

        $info = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hi,')]/ancestor::td[1]");

        if (!empty($info)) {
            // Hi, Christopher
            //        #***8331
            if (preg_match("/^\s*Hi,\s*([[:alpha:] \-]+?)\s*\#\s*\*+(\d{4})$/", $info, $m)) {
                $st->addProperty('Name', trim($m[1]));
                $st->setNumber($m[2])->masked();
                $st->setLogin($m[2])->masked();

                $st->setNoBalance(true);
            }

            return $email;
        }

        $info = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hello,')]/ancestor::td[1][contains(., 'Rapid Rewards #')]");

        if (!empty($info)) {
            //           Hello, Susan
            // Rapid Rewards #***7573
            if (preg_match("/^\s*Hello,\s*([[:alpha:] \-]+?)\s*Rapid Rewards\s*\#\s*\*+(\d{4})$/", $info, $m)) {
                $st->addProperty('Name', trim($m[1]));
                $st->setNumber($m[2])->masked();
                $st->setLogin($m[2])->masked();

                $st->setNoBalance(true);
            }

            return $email;
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], $this->detectFrom) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }
}
