<?php

namespace AwardWallet\Engine\qantas\Email;

use AwardWallet\Schema\Parser\Email\Email;

class SeatSelectionReceipt extends \TAccountChecker
{
    public $mailFiles = "qantas/it-29758365.eml";

    public $reFrom = ["@yourbooking.qantas.com.au"];
    public $reBody = [
        'en' => ['Flight No', 'The seat requests have been'],
    ];
    public $reSubject = [
        'Qantas Seat Selection Receipt for your flight to',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];
    private $keywordProv = 'Qantas';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!$this->parseEmail($email)) {
            return null;
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'/yourbooking.qantas.com.au')] | //img[@alt='Qantas' or contains(@src,'.qantas.com')]")->length > 0) {
            return $this->assignLang();
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
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], $this->keywordProv) !== false
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
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->flight();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your booking reference is'))}]/following::text()[normalize-space()!=''][1]"))
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Flight No'))}]/ancestor::table[1]/preceding::text()[normalize-space()!=''][1]"));

        $xpath = "(//text()[{$this->eq($this->t('Flight No'))}]/ancestor::table[1])[1]/descendant::text()[{$this->eq($this->t('Flight No'))}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $r->addSegment();
            $flight = $this->http->FindSingleNode("./td[2]", $root);

            if (preg_match("/^([A-Z\d]{2})\s*(\d+)$/", $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1][{$this->starts($this->t('Flights'))}]/td[2]",
                $root);
            $texts = explode(" - ", $node);

            if (count($texts) !== 2) {
                $this->logger->debug('can\'t determine dep|arr-Name');

                return false;
            }
            $dayDep = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2][{$this->starts($this->t('Departure'))}]/td[2]",
                $root);
            $s->departure()
                ->name($texts[0])
                ->noCode()
                ->noDate()
                ->day(strtotime($dayDep));
            $s->arrival()
                ->name($texts[1])
                ->noCode()
                ->noDate();
            $seats = $this->http->FindNodes("//text()[{$this->eq($this->t('Flight No'))}]/ancestor::tr[1][./td[normalize-space()='{$flight}']]/following-sibling::tr[normalize-space()!=''][3][{$this->contains($this->t('Seat No'))}]/td[2]",
                null, "/^\d+[A-Z]$/");
            $s->extra()->seats($seats);
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
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
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
}
