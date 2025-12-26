<?php

namespace AwardWallet\Engine\quandoo\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "quandoo/it-134021241.eml";
    public $subjects = [
        "You've cancelled your reservation at",
    ];

    public $lang = 'en';

    public $detectLang = [
        'en' => ['Your reservation'],
    ];

    public static $dictionary = [
        "en" => [
            'canceled'            => 'cancelled',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mail.quandoo.com') !== false) {
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
        $this->assignLang();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Quandoo')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Your reservation at'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation details'))}]")->length > 0
                && $this->http->XPath->query("//a[contains(normalize-space(), 'See more')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mail\.quandoo\.com$/', $from) > 0;
    }

    public function ParseEmail(Email $email)
    {
        $e = $email->add()->event();

        $e->setEventType(1);

        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your reservation at'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('has been'))}\s*(\D+)/");

        if ($status == $this->t('canceled')) {
            $e->general()
                ->cancelled()
                ->status($status);
        } elseif (!empty($status)) {
            $e->general()
                ->status($status);
        }

        $e->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/{$this->opt($this->t('Hi'))}\s*(\D+)\,/"), false)
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation details'))}]/following::text()[normalize-space()][1]"));

        $e->setName($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your reservation at'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Your reservation at'))}\s+(.+)\s+{$this->opt($this->t('has been'))}/"));
        $e->setAddress($this->http->FindSingleNode("//text()[{$this->eq($this->t('Address:'))}]/following::text()[normalize-space()][1]"));
        $e->setPhone($this->http->FindSingleNode("//text()[{$this->eq($this->t('Phone number:'))}]/following::text()[normalize-space()][1]"));

        $dateStart = $this->http->FindSingleNode("//text()[normalize-space()='Reservation details']/preceding::text()[contains(normalize-space(), ':')][1]/preceding::text()[normalize-space()][1]");
        $timeStart = $this->http->FindSingleNode("//text()[normalize-space()='Reservation details']/preceding::text()[contains(normalize-space(), ':')][1]");

        $e->booked()
            ->guests($this->http->FindSingleNode("//text()[normalize-space()='Reservation details']/preceding::text()[contains(normalize-space(), ':')][1]/preceding::text()[normalize-space()][2]", null, true, "/^\s*(\d+)\s*$/"))
            ->start(strtotime($dateStart . ', ' . $timeStart))
            ->noEnd();
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->ParseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($word)}]")->length > 0) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }
}
