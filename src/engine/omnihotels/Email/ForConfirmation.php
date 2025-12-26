<?php

namespace AwardWallet\Engine\omnihotels\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ForConfirmation extends \TAccountChecker
{
    public $mailFiles = "omnihotels/it-93353033.eml, omnihotels/it-93425037.eml, omnihotels/it-93431149.eml";
    public $subjects = [
        '/Information Regarding Your Upcoming Stay for Confirmation [#]/u',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Arriving'      => ['Arriving', 'ARRIVING'],
            'Departing'     => ['Departing', 'DEPARTING'],
            'Member Number' => ['Member Number', 'MEMBER NUMBER'],
            'Tier Level'    => ['Tier Level', 'TIER LEVEL'],
            'Select Guest'  => ['Select Guest', 'SELECT GUEST'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@em.omnihotels.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Omni Hotels')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('View Your Reservation'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Select Guest'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]em\.omnihotels\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $h = $email->add()->hotel();

        $confirmation = $confirmationTitle = null;

        if (preg_match("/({$this->opt($this->t('Confirmation #'))})[:\s]*([-A-z\d]{5,})(?: |[,.;(!?]|$)/i", $parser->getSubject(), $m)) {
            $confirmationTitle = $m[1];
            $confirmation = $m[2];
        } else {
            $confirmationTitle = $this->http->FindSingleNode("//*[count(node()[normalize-space()])=2]/node()[normalize-space()][1][{$this->starts($this->t('Confirmation #'))}][not(.//tr)]", null, true, '/^(.+?)[\s:：]*$/u');
            $confirmation = $this->http->FindSingleNode("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->starts($this->t('Confirmation #'))}][not(.//tr)] ]/node()[normalize-space()][2]", null, true, '/^[-A-z\d]{5,}$/');
        }
        $h->general()->confirmation($confirmation, $confirmationTitle);

        $traveller = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Account for:')]", null, true, "/{$this->opt($this->t('Account for:'))}\s*(\D+)/");

        if (!empty($traveller)) {
            $h->general()
                ->traveller($traveller);
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Phone:')]/preceding::text()[normalize-space()][2]"))
            ->address($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Phone:')]/preceding::text()[normalize-space()][1]"))
            ->phone($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Phone:')]/ancestor::*[1]", null, true, "/{$this->opt($this->t('Phone:'))}\s*([\d\-]+)/u"));

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[{$this->contains($this->t('Arriving'))}]/following::text()[normalize-space()][1]")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[{$this->contains($this->t('Departing'))}]/following::text()[normalize-space()][1]")));

        $account = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Member Number'))}]/following::text()[normalize-space()][1]");

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Tier Level'))}]/following::text()[normalize-space()][1]");

        if (!empty($status)) {
            $st = $email->add()->statement();
            $st->setNoBalance(true);
            $st->addProperty('Level', $status);
            $st->addProperty('Name', $traveller);
            $st->setNumber($account);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
