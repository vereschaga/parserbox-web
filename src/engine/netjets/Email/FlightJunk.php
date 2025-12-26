<?php

namespace AwardWallet\Engine\netjets\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightJunk extends \TAccountChecker
{
    public $mailFiles = "netjets/it-858540018.eml";
    public $subjects = [
        ' NetJets itinerary: ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@netjets.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'fly.netjets.com')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Reservation:')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Request:')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Please arrive') and contains(normalize-space(), 'prior to your departure')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Requested Aircraft:')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Lead Passenger:')]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]netjets\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $depCode4Symbol = array_filter($this->http->FindNodes("//img/ancestor::table[2]/descendant::tr[1]/descendant::td[1]/descendant::table[1]/descendant::tr[last()]", null, "/^([A-Z]{4})$/"));

        if (count($depCode4Symbol) > 0) {
            $email->setIsJunk(true);
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
