<?php

namespace AwardWallet\Engine\sandals\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "sandals/it-831130359.eml";
    public $subjects = [
        'Courtesy Payment Reminder on Your Reservation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Total Amount:' => ['Total Amount:', 'Total Vacation Price:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@uniquetravel.messages5.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Sandals as')]")->length === 0
        && $this->http->XPath->query("//text()[contains(normalize-space(), 'Beaches as')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('BOOKING INFORMATION'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Booking Number:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Resort:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Arrival Date:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Nights:'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]uniquetravel\.messages5\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Booking Number:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\d+)$/"));

        $pax = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Dear'))}\s+(\w+)\,$/");

        if (empty($pax)) {
            $pax = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Please be advised that your client')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('reservation'))}\s+([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*with/");
        }

        if (!empty($pax)) {
            $h->general()
                ->traveller($pax);
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='Resort:']/ancestor::tr[1]/descendant::td[2]"))
            ->noAddress();

        $nights = $this->http->FindSingleNode("//text()[normalize-space()='Nights:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\d+)$/");

        $inDate = strtotime($this->http->FindSingleNode("//text()[normalize-space()='Arrival Date:']/ancestor::tr[1]/descendant::td[2]"));
        $h->booked()
            ->checkIn($inDate)
            ->checkOut(strtotime('+' . $nights . ' day', $inDate));

        $guests = $this->http->FindSingleNode("//text()[normalize-space()='Adults:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\d+)$/");

        if (!empty($guests)) {
            $h->booked()
                ->guests($guests);
        }

        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Amount:'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\D{1,3}[\d\.\,\']+)$/");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,\']+)$/", $total, $m)) {
            $h->price()
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }
}
