<?php

namespace AwardWallet\Engine\golair\Email;

class Issue extends \TAccountChecker
{
    public $mailFiles = "golair/it-12631528.eml, golair/it-12878488.eml";

    public $reBody = [
        'pt' => ['A Smiles deseja a você uma excelente viagem', 'Código localizador Smiles'],
    ];
    public $reSubject = [
        'Comprovante de emissão',
        'Bilhete Smiles',
    ];
    public $lang = '';
    public $tot;
    public static $dict = [
        'pt' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang($parser);

        $its = $this->parseEmail();

        if (count($its) === 1) {
            $its[0]['TotalCharge'] = $this->tot['Total'];
            $its[0]['Currency'] = $this->tot['Currency'];

            if (!empty($this->tot['SpentAwards'])) {
                $its[0]['SpentAwards'] = $this->tot['SpentAwards'];
            }

            return [
                'parsedData' => ['Itineraries' => $its],
                'emailType'  => "Issue" . ucfirst($this->lang),
            ];
        }
        $total['Amount'] = $this->tot['Total'];
        $total['Currency'] = $this->tot['Currency'];

        if (!empty($this->tot['SpentAwards'])) {
            $total['SpentAwards'] = $this->tot['SpentAwards'];
        }

        return [
            'parsedData' => ['Itineraries' => $its, 'TotalCharge' => $total],
            'emailType'  => "Issue",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'smiles.com')]")->length > 0) {
            return $this->AssignLang($parser);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "smiles.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $its = [];

        $xpath = "//text()[contains(.,'" . $this->t('DE') . "')]/ancestor::tr[1][contains(.,'" . $this->t('PARA') . "')]/ancestor::tr[1]/following-sibling::tr[.//img]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode("./preceding::text()[contains(normalize-space(.),'Código de Reserva Cia Aérea')][1]/ancestor::tr[1]/following-sibling::tr[1]", $root, true, "#[A-Z\d]{2}-([A-Z\d]+)#");

            if (!$rl) {
                $rl = $this->http->FindSingleNode("./preceding::text()[contains(normalize-space(.),'Código localizador Smiles')][1]/ancestor::tr[1]/following-sibling::tr[1]", $root, true, "#([A-Z\d]+)#");
            }

            if (!$rl) {
                $rl = $this->http->FindSingleNode("./preceding::text()[contains(normalize-space(.),'Código localizador Smiles')][1]/ancestor::tr[1]/ancestor::td[1]/following-sibling::td[1]", $root, true, "#([A-Z\d]+)#");
            }
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $roots) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['Passengers'] = $this->http->FindNodes("./following::text()[contains(.,'Passageiro')][1]/ancestor::tr[following-sibling::tr][1]/following-sibling::tr[normalize-space(.)]/descendant-or-self::tr[not(.//tr)][1]", $roots[0]);
            $it['AccountNumbers'] = $this->http->FindNodes("./following::text()[contains(.,'Passageiro')][1]/ancestor::tr[contains(., 'Número Smiles')]/following-sibling::tr[normalize-space(.)]/descendant-or-self::tr[not(.//tr) and contains(., 'Número Smiles')][1]", $roots[0], '/Número Smiles\s*:\s*(\d+)/');
            $it['TicketNumbers'] = array_unique($this->http->FindNodes("./following::text()[contains(.,'Bilhete')][1]/ancestor::tr[1]/following-sibling::tr", $roots[0], '/^[A-Z\s\d\-]+$/'));

            foreach ($roots as $root) {
                $seg = [];
                $seg['DepCode'] = $this->http->FindSingleNode("./descendant::tr[1]/descendant::tr[1]/td[normalize-space()][1]", $root, true, "#\(([A-Z]{3})\)#");
                $seg['ArrCode'] = $this->http->FindSingleNode("./descendant::tr[1]/descendant::tr[1]/td[normalize-space()][last()]", $root, true, "#\(([A-Z]{3})\)#");
                $seg['DepName'] = trim($this->http->FindSingleNode("./descendant::tr[1]/descendant::tr[1]/td[normalize-space()][1]", $root, true, "#(.+)\([A-Z]{3}\)#"));
                $seg['ArrName'] = trim($this->http->FindSingleNode("./descendant::tr[1]/descendant::tr[1]/td[normalize-space()][last()]", $root, true, "#(.+)\([A-Z]{3}\)#"));
                $seg['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./descendant::tr[1]/descendant::tr[2]/td[normalize-space()][1]", $root)));
                $seg['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./descendant::tr[1]/descendant::tr[2]/td[normalize-space()][last()]", $root)));

                if (!empty($this->http->FindSingleNode("./preceding-sibling::tr[last()]//text()[normalize-space()='DIRETO']", $root))) {
                    $seg['Duration'] = $this->http->FindSingleNode("./preceding-sibling::tr[last()]//text()[normalize-space()='DIRETO']/ancestor::tr[1]/following-sibling::tr[normalize-space()][last()]/td[2]", $root);
                }
                $node = $this->http->FindSingleNode(".//text()[contains(.,'Voo')][1]", $root);

                if (preg_match("#\s+([A-Z\d]{2})\s*-\s*(\d+)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $seg['Cabin'] = $this->http->FindSingleNode(".//text()[contains(.,'Cabine')][1]", $root, true, "#Cabine\s+(.+)#");

                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }
        $node = $this->http->FindSingleNode("//text()[contains(.,'total em dinheiro')]/ancestor::tr[1]/following-sibling::tr[1]/td[normalize-space(.)][2]");

        if (empty($node)) {
            $node = $this->http->FindSingleNode("//td[starts-with(normalize-space(.), 'Total') and not(.//td)]/following-sibling::td[normalize-space(.)][2]");
        }
        $node = str_replace("R$", "BRL", $node);
        $node = str_replace("$", "USD", $node);
        $this->tot = $this->getTotalCurrency($node);
        $rewards = $this->http->FindSingleNode("//td[starts-with(normalize-space(.), 'Total') and not(.//td) and ./ancestor::tr[1]/preceding-sibling::tr[normalize-space()][last()]/td[normalize-space()][2][contains(., 'MILHAS')]]/following-sibling::td[normalize-space(.)][1]");

        if (!empty($rewards)) {
            $this->tot['SpentAwards'] = $rewards . ' MILHAS';
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        //		$this->logger->info($date);
        $in = [
            '#(\d+)[\.\/]+(\d+)[\.\/]+(\d{4})\s+(\d+:\d+)#',
            '/(\d{1,2}:\d{2})\s+(\d{1,2})[\.\/]+(\d{1,2})[\.\/]+(\d{2,4})/', // 07:40 28/01/2018
        ];
        $out = [
            '$3-$2-$1, $4',
            '$2.$3.$4, $1',
        ];
        $str = preg_replace($in, $out, $date);

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (mb_stripos($body, $reBody[0]) !== false && mb_stripos($body, $reBody[1]) !== false) {
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

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
