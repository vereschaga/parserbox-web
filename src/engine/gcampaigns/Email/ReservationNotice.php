<?php

namespace AwardWallet\Engine\gcampaigns\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservationNotice extends \TAccountChecker
{
    public $mailFiles = "gcampaigns/it-47292674.eml";

    public $reBody = [
        'en' => ['Please print this page and note the reservation'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Reservation Number:' => 'Reservation Number:',
            'Room Type:'          => 'Room Type:',
        ],
    ];

    private $code;
    private static $providers = [
        'gcampaigns' => [
            'from' => ['@pkghlrss.com'],
            'subj' => [
                'Disneyland® Resort Reservation Notice',
            ],
            'body' => [
                '//a[contains(@href,".passkey.com")]',
                '//img[contains(@src,".passkey.com")]',
            ],
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$providers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (null !== ($code = $this->getProvider($parser))) {
            $email->setProviderCode($code);
        }
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (null !== $this->getProviderByBody()) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        return $this->getProviderByBody();
    }

    private function getProviderByBody()
    {
        foreach (self::$providers as $code => $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->hotel();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation Number:'))}]/following::text()[normalize-space()!=''][1]"))
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Add\'l Guest(s):'))}]/ancestor::*[self::td or self::th][1]/following-sibling::*[self::td or self::th][normalize-space()!=''][1]/descendant::text()[normalize-space()!='']"));

        $r->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Itinerary Details'))}]/following::text()[normalize-space()!=''][1]"))
            ->noAddress();

        $room = $r->addRoom();
        $room->setType($this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Type:'))}]", null, false,
            "/{$this->opt($this->t('Room Type:'))}\s*(.+)/"));

        $node = $this->http->FindSingleNode("//text()[({$this->starts($this->t('Arriving on'))}) and ({$this->contains($this->t('departing on'))})]");

        if (preg_match("/{$this->opt($this->t('Arriving on'))}\s+(.+),\s+{$this->opt($this->t('departing on'))}\s+(.+)/",
            $node, $m)) {
            $r->booked()
                ->checkIn(strtotime(trim($m[1])))
                ->checkOut(strtotime(trim($m[2])));
        }
        $timeOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check out is by'))}]", null, false,
            "/{$this->opt($this->t('Check out is by'))}\s*(\d+(?::\d+)?\s*[ap]m)\./i");

        if (!empty($timeOut) && $r->getCheckOutDate()) {
            $r->booked()->checkOut(strtotime($timeOut, $r->getCheckOutDate()));
        }

        $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Adult(s):'))}]");

        if (preg_match("/{$this->opt($this->t('Adult(s):'))}\s*(\d+);\s*{$this->opt($this->t('Child(ren):'))}\s*(\d+)/",
            $node, $m)) {
            $r->booked()
                ->guests($m[1])
                ->kids($m[2]);
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CANCELLATION POLICY'))}]/following::*[normalize-space()!=''][1]");
        $cancellationExt = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CANCELLATION POLICY'))}]/following::*[normalize-space()!=''][1]/following-sibling::*[normalize-space()!=''][position()<=3][{$this->contains($this->t('If you need to cancel your reservation'))}][1]");

        if (!empty($cancellationExt)) {
            $cancellation .= ' ' . $cancellationExt;
        }

        if (!empty($cancellation)) {
            $r->general()->cancellation($cancellation);
        }

        $this->detectDeadLine($r);

        return true;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/We have a (\d+) days? change and cancel policy at our Resort./i",
            $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m[1] . ' days');
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Reservation Number:'], $words['Room Type:'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Reservation Number:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Room Type:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
