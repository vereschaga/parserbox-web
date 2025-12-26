<?php

namespace AwardWallet\Engine\rapidrewards\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class SavedActivityPage4 extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/statements/it-693920587.eml, rapidrewards/statements/it-692049113.eml";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'A-List progress'         => ['A-List progress', 'Keep your A-List benefits'],
            'Companion Pass progress' => ['Companion Pass progress', 'Companion Pass achieved', 'Earn your next Companion Pass'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $patterns['flights'] = "/^\s*(\d[\d,]*)\s+out of\s+(\d[\d,]*)\s+flights/"; // 10 out of 20 flights
        $patterns['points'] = "/^\s*(\d[\d,]*)\s+out of\s+(\d[\d,]*)\s+points/"; // 10,956 out of 135,000 points

        $st = $email->createStatement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts('RR#')}]/preceding::text()[starts-with(normalize-space(),'Hi, ')][1]", null, false, "/Hi, ([[:alpha:]][[:alpha:]\W]*[[:alpha:]])$/u");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $st
            ->setNumber($this->http->FindSingleNode("//text()[{$this->starts('RR#')}]", null, true, "/^\s*RR#\s*(\d{5,})\s*$/"))
            ->setLogin($this->http->FindSingleNode("//text()[{$this->starts('RR#')}]", null, true, "/^\s*RR#\s*(\d{5,})\s*$/"))
        ;

        $xpath = "//*[count(*) = 2]"
            . "[ *[1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('A-List progress'))}] ]"
            . "[ *[2]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Companion Pass progress'))}] ]";

        // A-List progress

        if ($this->http->XPath->query($xpath)->length > 0) {
            $tierFlightsVal = $this->http->FindSingleNode($xpath . "/*[1]/descendant::text()[contains(normalize-space(),'out of') and contains(.,'flights') or contains(normalize-space(),'Flights - completed')]");
            $tierPointsVal = $this->http->FindSingleNode($xpath . "/*[1]/descendant::text()[contains(normalize-space(),'out of') and contains(.,'points') or contains(normalize-space(),'Points - completed')]");
        } else {
            $tierFlightsVal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('A-List progress'))}][1]/following::text()[normalize-space()][position()<10][contains(normalize-space(),'out of') and contains(.,'flights') or contains(normalize-space(),'Flights - completed')][following::text()[{$this->eq($this->t('Companion Pass progress'))}]]");
            $tierPointsVal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('A-List progress'))}][1]/following::text()[normalize-space()][position()<10][contains(normalize-space(),'out of') and contains(.,'points') or contains(normalize-space(),'Points - completed')][following::text()[{$this->eq($this->t('Companion Pass progress'))}]]");
        }

        if (preg_match($patterns['flights'], $tierFlightsVal, $m)) {
            $st->addProperty('TierFlights', str_replace(',', '', $m[1]));
        } elseif (!preg_match("/Flights - completed/i", $tierFlightsVal)) {
            $st->addProperty('TierFlights', null);
        }

        if (preg_match($patterns['points'], $tierPointsVal, $m)) {
            $st->addProperty('TierPoints', str_replace(',', '', $m[1]));
        } elseif (!preg_match("/Points - completed/i", $tierPointsVal)) {
            $st->addProperty('TierPoints', null);
        }

        // Companion Pass progress

        if ($this->http->XPath->query($xpath)->length > 0) {
            $CPFlightsVal = $this->http->FindSingleNode($xpath . "/*[2]/descendant::text()[contains(normalize-space(),'out of') and contains(.,'flights') or contains(normalize-space(),'Flights - completed')]");
            $CPPointsVal = $this->http->FindSingleNode($xpath . "/*[2]/descendant::text()[contains(normalize-space(),'out of') and contains(.,'points') or contains(normalize-space(),'Points - completed')]");
        } else {
            $CPFlightsVal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Companion Pass progress'))}][1]/following::text()[normalize-space()][position()<10][contains(normalize-space(),'out of') and contains(.,'flights') or contains(normalize-space(),'Flights - completed')][preceding::text()[{$this->eq($this->t('A-List progress'))}]]");
            $CPPointsVal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Companion Pass progress'))}][1]/following::text()[normalize-space()][position()<10][contains(normalize-space(),'out of') and contains(.,'points') or contains(normalize-space(),'Points - completed')][preceding::text()[{$this->eq($this->t('A-List progress'))}]]");
        }

        if (preg_match($patterns['flights'], $CPFlightsVal, $m)) {
            $st->addProperty('CPFlights', str_replace(',', '', $m[1]));
        } elseif (!preg_match("/Flights - completed/i", $CPFlightsVal)) {
            $st->addProperty('CPFlights', null);
        }

        if (preg_match($patterns['points'], $CPPointsVal, $m)) {
            $st->addProperty('CPPoints', str_replace(',', '', $m[1]));
        } elseif (!preg_match("/Points - completed/i", $CPPointsVal)) {
            $st->addProperty('CPPoints', null);
        }

        $points = $this->http->FindSingleNode("(//div[normalize-space()='Available Points'])[1]/following-sibling::div[1]",
            null, false, "/^(\d[,\d]*)\s*(?:$|Points)/i");
        $st
            ->setBalance(str_replace(",", '', $points));

        $credits = $this->http->FindSingleNode("(//div[normalize-space()='Available Credits'])[1]/following-sibling::div[1]",
            null, false, "/^\D+\d[\d\., ]*/i");

        if (!empty($credits)) {
            $st
                ->addProperty('Funds', $credits);
        }

        $activityNodes = $this->http->XPath->query("//button[count(descendant::text()[normalize-space()]) >2][.//*[name()='svg']]");
        $this->logger->debug("found " . $activityNodes->length . ' rows activity');

        foreach ($activityNodes as $root) {
            $cols = $this->http->FindNodes("./descendant::text()[normalize-space()]", $root);

            // $this->logger->debug('$cols = '.print_r( $cols,true));

            if (count($cols) !== 3 && count($cols) !== 4) {
                $st->addProperty('NeedBrokeParse', null);

                continue;
            }

            if (preg_match("/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/", $cols[1], $m)) {
                $row = [
                    "Posting Date" => strtotime($cols[1]),
                    "Category"     => $cols[0],
                    "Total Miles"  => str_replace(["−", ","], ['-', ''], $cols[2] . ($cols[3] ?? '')),
                ];
                $st->addActivityRow($row);
            }
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[@href[{$this->contains(['www.southwest.com'])}]]")->length > 0
            || $this->http->XPath->query("//*[{$this->contains(['Southwest Airlines Co.'])}]")->length > 0
        ) {
            if ($this->http->XPath->query("//*[count(*) = 2]"
                    . "[ *[1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('A-List progress'))}] ]"
                    . "[ *[2]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Companion Pass progress'))}] ]"
                )->length > 0
                || $this->http->XPath->query("//text()[{$this->starts('RR#')}]"
                    . "/following::text()[normalize-space()='Available Points']"
                    . "/following::text()[{$this->eq($this->t('A-List progress'))}]"
                    . "/following::text()[{$this->eq($this->t('Companion Pass progress'))}]"
                )->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }
}
