<?php

// Careful with defects!

namespace AwardWallet\Engine\viator\Email;

class Itinerary2 extends \TAccountChecker
{
    public $mailFiles = "viator/it-3005190.eml, viator/it-3903469.eml, viator/it-4455263.eml, viator/it-4455264.eml, viator/it-5959133.eml";
    
    public $lang = '';
    public static $dictionary = [
        'en' => [
            'Booking'             => 'Booking Reference:',
            'Itinerary'           => 'Itinerary Number:',
            'Lead'                => 'Lead Traveler:',
            'Travel Date'         => 'Travel Date:',
            'Location'            => 'Location:',
            'Number of Travelers' => 'Number of Travelers:',
            'Total Price'         => 'Total Price:',
        ],
    ];
    private $tot = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();

        $its = $this->parseEmail($this->tot);

        if (count($its) === 1) {
            if ($this->tot != null) {
                $its[0]['TotalCharge'] = $this->tot['TotalCharge'];
                $its[0]['Currency'] = $this->tot['Currency'];
            }
        } elseif ($this->tot != null) {
            return [
                'parsedData' => ['Itineraries' => $its,
                    'TotalCharge'              => [
                        'Amount'   => $this->tot['TotalCharge'],
                        'Currency' => $this->tot['Currency'],
                    ],
                ],
                'emailType' => 'Itinerary',
            ];
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "Itinerary",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $href = ['.viator.com/', 'www.viator.com'];

        if ($this->http->XPath->query("//a[{$this->contains($href, '@href')} or {$this->contains($href, '@originalsrc')}]")->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thanks for using Viator") or contains(normalize-space(),"Happy travels,Viator")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        return array_key_exists('subject', $headers)
            && stripos($headers['subject'], 'Your Viator Booking Confirmation') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "viator.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(&$tot): array
    {
        $it_out = [];
        $TripNo = $this->http->FindSingleNode("//table//p[contains(normalize-space(text()),'" . $this->t('Itinerary') . "')]/strong");
        $xpath = "(//table//table[{$this->contains($this->t('Booking'))}])";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = ['Kind' => 'E'];
            $it['ConfNo'] = $this->http->FindSingleNode("descendant::p[{$this->contains($this->t('Booking'))}]", $root, true, "/{$this->opt($this->t('Booking'))}[:\s]*([^:\s].+)$/");

            $SUBNODE_ONE_TOUR_LPART = ".//text()[contains(.,'Lead Travel')]/ancestor::td[1]";
            //			$SUBNODE_ONE_TOUR_LPART = ".//p[contains(text(),'Lead Travel')]/ancestor::div[1]";
            $SUBNODE_ONE_TOUR_RPART = ".//a[contains(normalize-space(text()),'View voucher') or img[@alt='View voucher']]/ancestor::td[1]";

            $it['Name'] = $this->http->FindSingleNode($SUBNODE_ONE_TOUR_LPART . "//a/text()", $root);
            $it['DinerName'] = $this->http->FindSingleNode($SUBNODE_ONE_TOUR_LPART . "//text()[contains(.,'" . $this->t('Lead') . "')]", $root, true, "#" . $this->t('Lead') . "\s+(.+)#");
            $it['TripNumber'] = $TripNo;
            $it['StartDate'] = strtotime(trim($this->http->FindSingleNode($SUBNODE_ONE_TOUR_LPART . "//text()[contains(.,'" . $this->t('Travel Date') . "')]", $root, true, "#" . $this->t('Travel Date') . ".+?\s+(.+)#")) . " 00:00");
            $it['Address'] = $this->http->FindSingleNode($SUBNODE_ONE_TOUR_LPART . "//text()[contains(.,'" . $this->t('Location') . "')]", $root, true, "#" . $this->t('Location') . "\s+(.+)#");
            $it['Guests'] = $this->http->FindSingleNode($SUBNODE_ONE_TOUR_LPART . "//text()[contains(.,'" . $this->t('Number of Travelers') . "')]", $root, true, "#" . $this->t('Number of Travelers') . "\s+(\d+)\s*#");
            $it['Status'] = $this->http->FindSingleNode($SUBNODE_ONE_TOUR_RPART . "//p[2]", $root);

            if (empty($it['Status'])) {
                $it['Status'] = $this->http->FindSingleNode('//text()[contains(., "Your booking is confirmed")]') ? "confirmed" : null;
            }

            $node = $this->http->FindSingleNode($SUBNODE_ONE_TOUR_RPART . "//p[1]", $root);

            if (empty($node)) {
                $node = $this->http->FindSingleNode(".//text()[contains(., 'Price:')]", $root);
            }

            if (!empty($node)) {
                if (preg_match("#\s*(?<CurText>[A-Z]*)\s*(?<CurSign>\D?)\s*(?<Summ>[\d\,\.]+)#", trim($node), $m)) {
                    $it['TotalCharge'] = $m['Summ'];

                    if (isset($m['CurText']) && !empty($m['CurText'])) {
                        $it['Currency'] = trim($m['CurText']);
                    } else {
                        $it['Currency'] = currency($m['CurSign']);
                    }
                }
            }

            $it_out[] = $it;
        }

        $node = $this->http->FindSingleNode("//table//td[contains(text(),'" . $this->t('Total Price') . "')]");

        if ($node != null) {
            if (preg_match("#Total Price:\s*(?<CurText>[A-Z]*)\s*(?<CurSign>\D?)\s*(?<Summ>[\d\,\.]+)#", trim($node), $m)) {
                $tot['TotalCharge'] = $m['Summ'];
            }

            if (isset($m['CurText'])) {
                $tot['Currency'] = trim($m['CurText']);
            } else {
                $tot['Currency'] = currency($m['CurSign']);
            }
        }

        return $it_out;
    }

    private function assignLang(): bool
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['Booking']) ) {
                continue;
            }
            if ($this->http->XPath->query("//*[{$this->contains($phrases['Booking'])}]")->length > 0) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
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

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
