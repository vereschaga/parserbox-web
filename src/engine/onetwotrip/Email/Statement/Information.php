<?php

namespace AwardWallet\Engine\onetwotrip\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Information extends \TAccountChecker
{
    public $mailFiles = "onetwotrip/statements/it-66823335.eml, onetwotrip/statements/it-79786132.eml";
    private $lang = 'ru';
    private $reFrom = ['@onetwotrip.com'];
    private $reSubject = [
        'Открыты новые страны!',
        'Дарим путешествие ко Дню туризма!',
        'Вам начислены трипкоины',
    ];

    private static $dictionary = [
        'ru' => [],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            if ($this->isMembership()) {
                $st->setMembership(true);
            }

            return $email;
        }
        $root = $roots->item(0);

        $status = $this->http->FindSingleNode("ancestor::span[1]/following-sibling::img[{$this->contains(['nl_anons_bonus', 'email/icons/status-'], '@src')}]/@src", $root, true, "/(?:\/nl_anons_bonus\/|email\/icons\/status-)(classic|pro|premium)\.png/i");

        if (!empty($status)) {
            /*switch ($status) {
                case 'classic':
                case 'pro':
                    $st->addProperty("Status", "Basic");
                    break;
                case 'premium':
                    $st->addProperty("Status", "Premium");
                    break;
            }*/

            $balance = $this->http->FindSingleNode("ancestor::a[1]/preceding-sibling::a/span", $root, true, self::BALANCE_REGEXP);
            $st->setBalance($balance);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".onetwotrip.com/")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Команда OneTwoTrip.") or contains(normalize-space(),"Ваш OneTwoTrip!")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1 || $this->isMembership();
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $this->logger->debug('$date = '.print_r( "//text()[{$this->eq($this->t('ваш статус'))}]",true));
        return $this->http->XPath->query("//text()[{$this->eq($this->t('ваш статус'))}]");
    }

    private function isMembership(): bool
    {
        return $this->http->FindSingleNode('descendant::*[contains(normalize-space(),"Мы начислили вам") and contains(normalize-space(),"за покупку авиабилета")][last()]', null, true, "/Мы начислили вам \d+ трипкоин(?:а|ов)? за покупку авиабилета [A-Z]{3}[-—\s]+[A-Z]{3}/i") !== null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
