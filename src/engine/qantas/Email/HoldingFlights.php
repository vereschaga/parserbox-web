<?php

namespace AwardWallet\Engine\qantas\Email;

use AwardWallet\Schema\Parser\Email\Email;

class HoldingFlights extends \TAccountChecker
{
    public $mailFiles = "qantas/it-30028495.eml";

    public $reFrom = ["@yourbooking.qantas.com.au"];
    public $reBody = [
        'en' => ['We’ll cancel it automatically', 'Payment for your booking was not completed'],
    ];
    public $reSubject = [
        'We are holding flights from',
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
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('booking reference is'))}]/following::text()[normalize-space()!=''][1]"))
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space()!=''][1]"))
            ->status($this->http->FindSingleNode("//text()[{$this->contains($this->t('Payment must be made by'))}]",
                null, false,
                "/({$this->opt($this->t('Payment must be made by'))} .+ {$this->opt($this->t('will be cancelled'))})/"));

        $xpath = "//text()[{$this->eq($this->t('Flight'))}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $r->addSegment();
            $flight = $this->http->FindSingleNode("./td[2]", $root);

            if (preg_match("/^([A-Z\d]{2})\s*(\d+)$/", $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $date = strtotime($this->http->FindSingleNode("./preceding-sibling::tr[normalize-space()!=''][1][{$this->starts($this->t('Date'))}]/td[2]/descendant::text()[normalize-space()!=''][1]",
                $root));
            $s->departure()
                ->name($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1][{$this->starts($this->t('From'))}]/td[2]/descendant::text()[normalize-space()!=''][1]",
                    $root))
                ->noCode()
                ->date(strtotime($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1][{$this->starts($this->t('From'))}]/td[2]/descendant::text()[normalize-space()!=''][2]",
                    $root), $date));
            $s->arrival()
                ->name($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2][{$this->starts($this->t('To'))}]/td[2]",
                    $root))
                ->noCode()
                ->noDate();
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
