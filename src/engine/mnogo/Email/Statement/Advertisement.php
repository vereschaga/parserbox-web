<?php

namespace AwardWallet\Engine\mnogo\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Advertisement extends \TAccountChecker
{
    public $mailFiles = "mnogo/statements/it-91172648.eml, mnogo/statements/it-91247697.eml";

    private $format = null;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@mnogo.ru') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".mnogo.ru/") or contains(@href,"www.mnogo.ru")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            $this->logger->debug('Root node not found!');

            return $email;
        }
        $root = $roots->item(0);

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $number = $name = $balance = null;

        $rootText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $root));

        if ($this->format === 1) {
            if (preg_match("/На вашем бонусном сч[ёе]те:\s*(\d[,.\'\d ]*)\s*бонус/imu", $rootText, $m)) {
                /*
                    На вашем бонусном счете:
                    51301 бонус
                */
                $balance = $m[1];
            }
            $number = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Изменение бонусного счёта по карте') or contains(normalize-space(),'Изменение бонусного счета по карте')]", null, true, "/Изменение бонусного сч[ёе]та по карте\s*([-A-Z\d]{5,})$/mu");
        } elseif ($this->format === 2) {
            if (preg_match("/№ сч[ёе]та:\s*([-A-Z\d]{5,})\s+Бонусов:\s*(\d[,.\'\d ]*)$/mu", $rootText, $m)) {
                /*
                    № счёта: 11939974   Бонусов: 155
                */
                $number = $m[1];
                $balance = $m[2];
            }
            $name = $this->http->FindSingleNode("//text()[contains(normalize-space(),'спасибо, что Вы в клубе')]", null, true, "/^({$patterns['travellerName']})\s*,\s*спасибо, что Вы в клубе/iu");
        }

        $st->setNumber($number)->setLogin($number);

        if ($name) {
            $st->addProperty('Name', $name);
        }

        if ($balance !== null) {
            $st->setBalance($this->normalizeAmount($balance));
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $this->format = 1; // it-91172648.eml
        $nodes = $this->http->XPath->query("//tr/*[ not(.//tr) and descendant::text()[normalize-space()][1][normalize-space()='На вашем бонусном счете:'] ]");

        if ($nodes->length === 0) {
            $this->format = 2; // it-91247697.eml
            $nodes = $this->http->XPath->query("//tr/*[ not(.//tr) and descendant::text()[normalize-space()][1][normalize-space()='№ счёта:'] and descendant::text()[normalize-space()='Бонусов:'] ]");
        }

        return $nodes;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
