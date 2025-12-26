<?php

namespace AwardWallet\Engine\gcampaigns\Email;

use AwardWallet\Schema\Parser\Email\Email;

class JunkResCancelled extends \TAccountChecker
{
    public $mailFiles = "gcampaigns/it-46279134.eml";

    public $reFrom = ["groupcampaigns@pkghlrss.com"];
    public $reBody = [
        'en' => ['This is a record of your hotel cancellation for', 'Your reservation has been successfully cancelled'],
    ];
    public $reSubject = [
        'Hotel reservation cancelled:',
        'Your reservation has been cancelled',
        'Hotel Cancellation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Your cancellation number is:' => [
                'Your cancellation number is:',
                'Cancellation confirmation number:',
                'Cancellation Confirmation Number:',
                'CONFIRMATION #',
            ],

            'Your reservation was cancelled on' => [
                'Your reservation was cancelled on',
                'Your reservation has been successfully cancelled.',
                'Your reservation has been cancelled',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->logger->debug('email has attach. can\'t mark as junk');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.passkey.com')] | //a[contains(@href,'.passkey.com')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if ($fromProv && stripos($headers["subject"], $reSubject) !== false) {
                    return true;
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

    private function parseEmail(Email $email)
    {
        $r = $email->add()->hotel();

        $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your cancellation number is:'))}]", null, false, "/{$this->opt($this->t('Your cancellation number is:'))}\s*([\w\-]+)/");

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your cancellation number is:'))}]/following::text()[normalize-space()][1]", null, false, "/([\w\-]+)/"); // confirmation for detect
        }

        $r->general()
            ->cancelled()
            ->status('cancelled')
            ->cancellationNumber($number)
            ->confirmation($number); // confirmation for detect

        if ($email->checkValid()) {
            $email->removeItinerary($r);
            $email->setIsJunk(true);
        }

        return true;
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
            if (isset($words['Your cancellation number is:'], $words['Your reservation was cancelled on'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Your cancellation number is:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Your reservation was cancelled on'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field)) . ')';
    }
}
