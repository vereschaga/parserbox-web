<?php

namespace AwardWallet\Engine\saudisrabianairlin\Email;

use AwardWallet\Schema\Parser\Email\Email;

class JunkBookingConfExtras extends \TAccountChecker
{
    public $mailFiles = "saudisrabianairlin/it-42171297.eml, saudisrabianairlin/it-42198254.eml";

    public $reFrom = ["@saudiairlines.com"];
    public $reBody = [
        'en' => ['For none Saudi Arabian Airlines flights, the requested seats, meals and special assistance'],
    ];
    public $reSubject = [
        'Booking confirmation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Extras'                   => 'Extras',
            'Departure Flight Details' => 'Departure Flight Details',
        ],
    ];
    private $keywordProv = 'Saudi Arabian Airlines';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->checkFormatJunk()) {
            $this->logger->debug('not junk format');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $email->setIsJunk(true);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'bookonline.saudiairlines.com')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->checkFormatJunk();
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
                if (($fromProv || stripos($headers["subject"], $this->keywordProv))
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
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
        return 0;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function checkFormatJunk()
    {
        // detect lang at first
        if (!$this->assignLang()) {
            return false;
        }

        $condition1 = $this->http->XPath->query("//text()[{$this->eq($this->t('Booking Details'))}]/following::text()[normalize-space()!=''][1][{$this->eq($this->t('Booking date'))}]/following::text()[normalize-space()!=''][2][{$this->eq($this->t('Booking reference'))}]")->length > 0;
        $condition2 = $this->http->XPath->query("//text()[({$this->eq($this->t('Meal preference:'))}) or ({$this->eq($this->t('Assistance:'))})]/following::text()[normalize-space()!=''][2][{$this->eq($this->t('Extras'))}]/following::text()[normalize-space()!=''][1][{$this->eq($this->t('Departure Flight Details'))}]")->length > 0;
        $condition3 = $this->http->XPath->query("//text()[{$this->eq($this->t('Departure Flight Details'))}]/following::text()[normalize-space()!=''][1][{$this->contains($this->t(' to '))}]/following::text()[normalize-space()!=''][1][{$this->eq($this->t('Seat:'))}]")->length > 0;

        if ($condition1 && $condition2 && $condition3) {
            return true;
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Extras'], $words['Departure Flight Details'])) {
                if ($this->http->XPath->query("//*[{$this->eq($words['Extras'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->eq($words['Departure Flight Details'])}]")->length > 0
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
}
