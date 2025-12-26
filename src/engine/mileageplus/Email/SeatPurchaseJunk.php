<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class SeatPurchaseJunk extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-28497269.eml, mileageplus/it-28500824.eml";

    public $reFrom = ["unitedairlines@united.com"];
    public $reBody = [
        'en' => ['Purchase Summary', 'Confirmation Number'],
    ];
    public $reSubject = [
        'Seat Purchase Confirmation',
        'Premium Seats Confirmation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'www.united.com') or @alt='United Airlines'] | //a[contains(@href,'www.united.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
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
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number'))}]/following::text()[normalize-space()!=''][1]"));

        //checking for no date, and codes format is necessary to sure that format is really not have itineraries
        $ruleCodes = "translate(normalize-space(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','DDDDDDDDDDDDDDDDDDDDDDDDDD')),' ','')='DDD-DDD'";
        $ruleTime = "contains(translate(.,'0123456789','dddddddddd'),'d:dd')";
        $xpath = "//text()[{$ruleCodes}]/ancestor::*[{$this->contains($this->t('Total Price:'))}][1][not({$ruleTime})]/descendant::text()[{$ruleCodes}]";
        $this->logger->debug($xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $r->addSegment();
            $s->airline()
                ->noNumber()
                ->noName();
            $node = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^([A-Z]{3})\s*\-\s*([A-Z]{3})/", $node, $m)) {
                $s->departure()
                    ->noDate()
                    ->code($m[1]);
                $s->arrival()
                    ->noDate()
                    ->code($m[2]);
            }
        }
        $sum = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->contains($this->t('Total Price:'))}]/following::text()[normalize-space()!=''][1]"));
        $r->price()
            ->total($sum['Total'])
            ->currency($sum['Currency']);

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

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';
        $node = str_replace('$', 'USD', $node);

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }
}
