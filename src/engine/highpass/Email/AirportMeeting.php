<?php

namespace AwardWallet\Engine\highpass\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AirportMeeting extends \TAccountChecker
{
    public $mailFiles = "highpass/it-702593040.eml, highpass/it-702593041.eml";
    public $subjects = [
        'HighPass Order Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@bjcvip.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'HighPass')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Service name:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Airport local time:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Meeting point:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]bjcvip\.comm$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEvent($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseEvent(Email $email)
    {
        $e = $email->add()->event();

        $e->setEventType(EVENT_MEETING);

        $e->general()
            ->travellers(explode(",", $this->http->FindSingleNode("//text()[normalize-space()='Other passengers:']/ancestor::tr[1]/following::tr[1]")))
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Order number:']/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/"));

        $passengerText = $this->http->FindSingleNode("//text()[normalize-space()='Passengers:']/following::text()[normalize-space()][1]");

        if (preg_match("/Adults?\:\s*(?<adult>\d+)/", $passengerText, $m)) {
            $e->setGuestCount($m['adult']);
        }

        $e->setName($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Service name:')]/ancestor::td[2]", null, true, "/{$this->opt($this->t('Service name:'))}\s*(.+)/"));

        $e->setAddress('Airport, ' . $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Terminal:')]/ancestor::td[2]", null, true, "/\(([A-Z]{3})\)/"));

        $startDate = $this->http->FindSingleNode("//text()[normalize-space()='Airport local time:']/following::text()[normalize-space()][1]");
        $e->setStartDate(strtotime(str_replace('/', '.', $startDate)));
        $e->setNoEndDate(true);

        $price = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Order amount:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Order amount:'))}\s*(.+)/");

        if (preg_match("/^(?<total>[\d\.\,\']+)\s*(?<currency>[A-Z]{3})/", $price, $m)) {
            $e->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
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
