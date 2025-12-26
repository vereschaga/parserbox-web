<?php

namespace AwardWallet\Engine\perfectdrive\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "perfectdrive/statements/it-140871530.eml";
    public $subjects = [
        'Budget: Your verification code',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@e.budget.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true) {
            return false;
        } elseif ($this->http->XPath->query("//text()[contains(normalize-space(), 'Budget Rent A Car System')]")->length > 0) {
            return $this->http->XPath->query("//a[contains(@href, 'https://www.budget.com/en/loyalty-profile/fastbreak/forgot-password')]/preceding::text()[normalize-space()][1][contains(normalize-space(), ' 15 ')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]e\.budget\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $code = $this->http->FindSingleNode("//text()[normalize-space()='Enter this code on the Verification code screen.']/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

        if (!empty($code)) {
            $email->add()->oneTimeCode()->setCode($code);
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
        return count(self::$dictionary);
    }
}
