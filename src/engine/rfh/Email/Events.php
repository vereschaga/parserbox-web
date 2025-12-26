<?php

namespace AwardWallet\Engine\rfh\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Events extends \TAccountChecker
{
    public $mailFiles = "rfh/it-735966196.eml, rfh/it-743174157.eml";
    public $subjects = [
        'Your booking confirmation for',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.roccofortehotels.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'ROCCO FORTE HOTELS')]")->length > 0
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('Your reservation details are below:'))}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains($this->t('This reservation has been cancelled'))}]")->length > 0)
            && $this->http->XPath->query("//text()[normalize-space()='Address']")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.roccofortehotels\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEvents($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseEvents(Email $email)
    {
        $e = $email->add()->event();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('This reservation has been cancelled'))}]")->length > 0) {
            $e->general()
                ->cancelled();
        }

        $e->setEventType(Event::TYPE_RESTAURANT);

        $e->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservation Number:')]", null, true, "/^{$this->opt($this->t('Reservation Number:'))}\s*([A-Z\d]+)\s*$/"));

        $eventInfo = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'guests') and contains(normalize-space(), 'at')]");

        if (preg_match("/^(?<pax>[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*\|\s*(?<date>\w+\s+\d+\s*\w+\s*\d{4})\s+at\s+(?<time>\d+\:\d+)\s+\|\s+(?<guests>\d+)\s*guests$/", $eventInfo, $m)) {
            $e->general()
                ->traveller($m['pax']);

            $e->setStartDate(strtotime($m['date'] . ', ' . $m['time']))
                ->setNoEndDate(true);

            $e->setGuestCount($m['guests']);
        }

        $addressName = $this->http->FindSingleNode("//text()[normalize-space()='Address']/ancestor::td[1]/following::td[1]/descendant::text()[normalize-space()][1]");

        if (preg_match("/^(?<name>.+)\s*\|\s*(?<address>.+)$/", $addressName, $m)
        || preg_match("/^(?<address>.+)$/", $addressName, $m)) {
            if (isset($m['name']) && !empty($m['name'])) {
                $e->setName($m['name']);
            }

            $e->setAddress($m['address']);
        }

        if (empty($e->getName())) {
            $e->setName($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Team')]", null, true, "/^(.+)\s+{$this->opt($this->t('Team'))}/"));
        }

        $phone = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Phone')]/following::text()[normalize-space()][1]", null, true, "/^([\d\+\s]+)$/");

        if (!empty($phone)) {
            $e->setPhone($phone);
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
