<?php

namespace AwardWallet\Engine\westjet\Email;

use AwardWallet\Schema\Parser\Email\Email;

class It3273128 extends \TAccountCheckerExtended
{
    public $mailFiles = "westjet/it-3273128.eml, westjet/it-4813183.eml, westjet/it-57215777.eml";
    public $status;
    public $flightArray = [];

    public $reSubject = [
        "Important information about your flight",
        "Pre-purchase your meal",
        "Important changes to your upcoming WestJet flight",
    ];

    public static $dictionary = [
        "en" => [
            'reservation code is:' => ['on confirmation code:', 'reservation code is:'],
            'Hello '               => ['Hello ', 'Hi,'],
        ],
    ];

    public $lang = 'en';

    public function parseHtml(Email $email): void
    {
        $flight = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//*[normalize-space(text())='Reservation code:']/following::text()[1]");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('reservation code is:'))}]/following::text()[normalize-space(.)][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/");
        }

        if (stripos($this->status, 'changes') !== false) {
            $flight->general()
                ->status('changes');
        }

        $flight->general()
            ->confirmation($confirmation, 'reservation code');

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello '))}][1]", null, true, "/^\s*{$this->opt($this->t('Hello '))}\s*(.+?)[^\w\s]/u")
        ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello '))}][1]/ancestor::tr[1]", null, true, "/^\s*{$this->opt($this->t('Hello '))}\s*(.+?)[^\w\s]/u");

        $flight->general()
                ->traveller($traveller);

        $segments = $this->findSegments();

        foreach ($segments as $root) {
            $segment = $flight->addSegment();

            if ($operator = $this->http->FindSingleNode("td[normalize-space(.)][1]/descendant::text()[contains(., 'Operated by')]/following-sibling::node()[1]", $root)) {
                $segment->airline()
                    ->operator($operator);
            }

            if (preg_match('/Operated by\s*:\s*(.+)/', $this->http->FindSingleNode("./td[normalize-space(.)][1]", $root), $m)) {
                $segment->airline()
                    ->operator($m[1]);
            }

            $aName = $this->http->FindSingleNode("./td[normalize-space(.)][1]", $root, true, "#((?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*\d+#");

            if (empty($aName)) {
                $this->logger->error($operator);

                if (!empty($segment->getOperatedBy()) && stripos($segment->getOperatedBy(), 'JetBlue') !== false) {
                    $segment->airline()
                        ->name('B6');
                } else {
                    $segment->airline()
                        ->noName();
                }
            } else {
                $segment->airline()
                    ->name($aName);
            }

            $segment->airline()
                ->number($fNumber = $this->http->FindSingleNode("./td[normalize-space(.)][1]", $root, true, "#\s*(\d{2,4})#"));

            if (in_array($fNumber, $this->flightArray) === true) {
                $flight->removeSegment($segment);

                continue;
            } else {
                $this->flightArray[] = $fNumber;
            }

            $depCode = $this->http->FindSingleNode("./td[normalize-space(.)][2]", $root, true, "#\(([A-Z]{3})\)#");

            if (!empty($depCode)) {
                $segment->departure()
                    ->code($depCode);
            } else {
                $segment->departure()
                    ->noCode();

                $depName = $this->re("/^(.+)\s+\w+\s+\d+\s+\d+\:\d+/", $this->http->FindSingleNode("./td[normalize-space(.)][2]", $root));

                if (!empty($depName)) {
                    $segment->departure()
                        ->name($depName);
                }
            }

            $segment->departure()
                ->date(strtotime($this->http->FindSingleNode("(./td[normalize-space(.)][2]//text()[normalize-space(.)])[2]", $root) . ', ' . $this->http->FindSingleNode("(./td[normalize-space(.)][2]//text()[normalize-space(.)])[3]", $root)));

            $arrCode = $this->http->FindSingleNode("./td[normalize-space(.)][3]", $root, true, "#\(([A-Z]{3})\)#");

            if (!empty($arrCode)) {
                $segment->arrival()
                    ->code($arrCode);
            } else {
                $segment->arrival()
                    ->noCode();

                $arrName = $this->re("/^(.+)\s+\w+\s+\d+\s+\d+\:\d+/", $this->http->FindSingleNode("./td[normalize-space(.)][3]", $root));

                if (!empty($arrName)) {
                    $segment->arrival()
                        ->name($arrName);
                }
            }
            $segment->arrival()
                ->date(strtotime($this->http->FindSingleNode("(./td[normalize-space(.)][3]//text()[normalize-space(.)])[2]", $root) . ', ' . $this->http->FindSingleNode("(./td[normalize-space(.)][3]//text()[normalize-space(.)])[3]", $root)));

            $depTerminal = $this->http->FindSingleNode("td[normalize-space(.)][2]/descendant::tr[contains(., 'Terminal') and not(.//tr)]/descendant::text()[contains(., 'Terminal')]", $root, true, '/Terminal\s*:\s*([A-Z\d]{1,4})/');

            if (!empty($depTerminal)) {
                $segment->departure()
                    ->terminal($depTerminal);
            }

            $arrTerminal = $this->http->FindSingleNode("td[normalize-space(.)][3]/descendant::tr[contains(., 'Terminal') and not(.//tr)]/descendant::text()[contains(., 'Terminal')]", $root, true, '/Terminal\s*:\s*([A-Z\d]{1,4})/');

            if (!empty($arrTerminal)) {
                $segment->arrival()
                    ->terminal($arrTerminal);
            }

            $seats = array_filter($this->http->FindNodes("./td[normalize-space(.)][last()]//text()[normalize-space()]", $root, "/^\s*(\d{1,2}[A-Z])\s*$/"));

            if (!empty($seats)) {
                $segment->extra()
                    ->seats($seats);
            }
        }
    }

    private function findSegments(): \DOMNodeList
    {
        $xpath = "//text()[starts-with(normalize-space(),'Your new itinerary:') or starts-with(normalize-space(),'You are now rebooked on the following flight(s):')]/following::*[normalize-space(text())='Flights' or normalize-space(text())='Flight'][1]/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = "//*[normalize-space(text())='Flights']/ancestor::tr[1]/following-sibling::tr";
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length > 0) {
            $this->logger->debug('[XPath] Segments: ' . $xpath);
        }

        return $nodes;
    }

    public static function getEmailProviders()
    {
        return ['westjet', 'jetblue'];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@notifications.westjet.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $href = ['.westjet.com/', 'www.westjet.com'];

        if ($this->http->XPath->query("//a[{$this->contains($href, '@href')} or {$this->contains($href, '@originalsrc')}]")->length === 0
            && $this->http->XPath->query('//text()[starts-with(translate(.," ", ""),"©WestJet") or starts-with(normalize-space(),"©") and contains(normalize-space(),"WestJet")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"WestJet Rewards ID:") or contains(normalize-space(),"you have booked a flight with WestJet") or contains(normalize-space(),"Thanks for choosing to travel with JetBlue")]')->length === 0
        ) {
            return false;
        }

        return $this->findSegments()->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ((!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true)
            && (!array_key_exists('subject', $headers) || strpos($headers['subject'], 'WestJet') === false)
        ) {
            return false;
        }

        foreach ($this->reSubject as $phrase) {
            if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Thanks for choosing to travel with JetBlue'))}]")->length > 0) {
            $email->setProviderCode('jetblue');
        }
        $this->http->FilterHTML = false;
        $this->status = $parser->getSubject();
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        $this->parseHtml($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function re(string $re, ?string $str, $c = 1): ?string
    {
        if (preg_match($re, $str ?? '', $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }
}
