<?php

namespace AwardWallet\Engine\jetstar\Email;

class Ticket extends \TAccountChecker
{
    public $mailFiles = "jetstar/it-3859427.eml, jetstar/it-5176779.eml, jetstar/it-7896302.eml, jetstar/it-7906553.eml, jetstar/it-9769695.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $text = $parser->getHtmlBody();
        $texts = implode("\n", $parser->getRawBody());

        if (substr_count($texts, 'Content-Type: text/html') > 1) {
            $texts = preg_replace("#------=_NextPart.*#", "\n", $texts);
            $texts = preg_replace("#\n--_\d{3}_.*#", "\n", $texts);
            $text = '';
            $posBegin1 = stripos($texts, "Content-Type: text/html");
            $i = 0;

            while ($posBegin1 !== false && $i < 50) {
                $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
                $str = substr($texts, $posBegin1, $posBegin - $posBegin1);

                $posEnd = stripos($texts, "Content-Type: ", $posBegin);
                $block = substr($texts, $posBegin, $posEnd - $posBegin);
                $posEnd = strripos($block, "\n\n");
                $block = substr($texts, $posBegin, $posEnd);

                if (preg_match("#filename=.*\.htm.*base64#is", $str)) {
                    $block = trim($block);
                    $block = htmlspecialchars_decode(base64_decode($block));

                    if (($blockBegin = stripos($block, '<blockquote')) !== false) {
                        $blockEnd = strripos($block, '</blockquote>', $blockBegin) + strlen('</blockquote>');
                        $block = substr($block, $blockBegin, $blockEnd - $blockBegin);
                    }
                    $text .= $block;
                } elseif (preg_match("#quoted-printable#s", $str)) {
                    $text .= quoted_printable_decode($block);
                } else {
                    $text .= htmlspecialchars_decode($block);
                }
                $posBegin1 = stripos($texts, "Content-Type: text/html", $posBegin);
                $i++;
            }

            if (stripos($text, 'charset=utf-8">') !== false) {
                $this->http->FilterHTML = true; // need for some emails!
                $this->http->SetBody($text);
            } else {
                $this->http->SetEmailBody($text, true);
            }
        }
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'Ticket',
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@jetstar.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], '@jetstar.com') !== false
            && stripos($headers['subject'], 'Jetstar Flight Itinerary for') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//a[contains(normalize-space(.), 'Jetstar.com') or contains(normalize-space(.), 'jetstar.com')]")->length > 0;
    }

    protected function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[contains(normalize-space(.),'Booking') and contains(normalize-space(.),'eference')]/following::text()[string-length(normalize-space(.))>1][1])[1]");
        $type = 1;
        $nodes = $this->http->FindNodes("//text()[normalize-space(.)='Passenger']/ancestor::tr[1]/following-sibling::tr/td[1]/descendant::text()[normalize-space(.)][1]");

        if ($nodes != null) {
            $it['Passengers'] = array_unique($nodes);
        } else {
            $it['Passengers'] = array_filter(array_unique($this->http->FindNodes("//text()[contains(normalize-space(.),'Passenger')]/ancestor::table[2]/following-sibling::table//table//table[1]//text()[1]")));

            if (empty($it['Passengers'])) {
                $it['Passengers'] = array_filter(array_unique($this->http->FindNodes("//text()[contains(normalize-space(.),'Passenger')]/ancestor::table[2]/following::table[1]/tbody/tr/descendant::text()[normalize-space(.)][1]")));
            }
            $type = 2;
        }
        $nodes = $this->http->FindSingleNode("//td[contains(translate(., 'ABCDEFGHJIKLMNOPQRSTUVWXYZ', 'abcdefghjiklmnopqrstuvwxyz'),'booking date')and not(.//td)]");
        $it['ReservationDate'] = strtotime(substr($nodes, strpos($nodes, ':') + 1) . ' 00:00');

        $total = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Payment of')]");

        if (strpos($total, ' ¥')) {
            $total = str_replace('円', 'JPY', $total);
        }

        if (preg_match("#[\D\s]+\s*\D{1}(\d+.\d+)\s*([A-Z]{3})#", $total, $mathec)) {
            $it['TotalCharge'] = $mathec[1];
            $it['Currency'] = $mathec[2];
        }
        $xpath = '//text()[starts-with(normalize-space(.),"Starter") and not(starts-with(normalize-space(.), "Starter fares"))]/ancestor::table[1]';
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $roots = $this->http->XPath->query('//text()[' . $this->contains(['Flight Duration:', 'Flight duration:']) . ']');
        }

        foreach ($roots as $root) {
            $seg = [];

            if ($type === 2) {
                $date = $this->http->FindNodes("./following::table[2]//td[normalize-space(.) and not(.//td)][1]/descendant::text()[string-length(normalize-space(.))>1][1]", $root);

                if (isset($date[1])) {
                    $seg['DepDate'] = strtotime($date[1] . ', ' . $this->http->FindSingleNode("./following::table[2]//td[normalize-space(.) and not(.//td)][1]/descendant::text()[string-length(normalize-space(.))>1][2]", $root, true, "#\d+:\d+\s*[ap]m#"));
                }
                $seg['DepName'] = $this->http->FindSingleNode("./following::table[2]//td[normalize-space(.) and not(.//td)][1]/descendant::text()[string-length(normalize-space(.))>1][3]", $root);

                $date = $this->http->FindNodes("./following::table[4]//td[string-length(normalize-space(.))>2 and not(.//td)][1]/descendant::text()[string-length(normalize-space(.))>1][1]", $root);

                if (isset($date[1])) {
                    $seg['ArrDate'] = strtotime($date[1] . ', ' . $this->http->FindSingleNode("./following::table[4]//td[string-length(normalize-space(.))>2 and not(.//td)][1]/descendant::text()[string-length(normalize-space(.))>1][2]", $root, true, "#(\d+:\d+\s*[ap]m)#"));
                }
                $seg['ArrName'] = $this->http->FindSingleNode("./following::table[4][1]//td[normalize-space(.) and not(.//td)][1]/descendant::text()[string-length(normalize-space(.))>1][3]", $root);
                $seg['Seats'] = array_values(array_filter($this->http->FindNodes("./ancestor::tr[2]/following-sibling::tr[1]//tr[string-length(normalize-space(.))>1]", $root, "#^\s*\((\d{1,3}[A-Z])\)\s*$#")));
            } else {
                if (count($this->http->FindNodes("./descendant::td", $root)) == 1) {
                    $seg['DepDate'] = strtotime($this->http->FindSingleNode("./following::td[normalize-space(.) and not(.//td)][1]/descendant::text()[normalize-space(.)][2]", $root) . ', ' . $this->http->FindSingleNode("./following::td[not(.//td)][1]/descendant::text()[normalize-space(.)][3]", $root, true, "#\d+:\d+\s+[ap]m#"));
                    $seg['DepName'] = $this->http->FindSingleNode("./following::td[normalize-space(.) and not(.//td)][1]/descendant::text()[normalize-space(.)][1]", $root);
                    $seg['ArrDate'] = strtotime($this->http->FindSingleNode("./following::td[normalize-space(.) and not(.//td)][2]/descendant::text()[normalize-space(.)][2]", $root) . ', ' . $this->http->FindSingleNode("./following::td[not(.//td)][2]/descendant::text()[normalize-space(.)][3]", $root, true, "#\d+:\d+\s+[ap]m#"));
                    $seg['ArrName'] = $this->http->FindSingleNode("./following::td[normalize-space(.) and not(.//td)][2]/descendant::text()[normalize-space(.)][1]", $root);
                } else {
                    $seg['DepDate'] = strtotime($this->http->FindSingleNode("./descendant::tr[1]/td[2]/descendant::text()[normalize-space(.)][2]", $root) . ', ' . $this->http->FindSingleNode("./descendant::tr[1]/td[2]/descendant::text()[normalize-space(.)][3]", $root, true, "#\d+:\d+\s+[ap]m#"));
                    $seg['DepName'] = $this->http->FindSingleNode("./descendant::tr[1]/td[2]/descendant::text()[normalize-space(.)][1]", $root);
                    $node = $this->http->FindSingleNode("./descendant::tr[1]/td[2]/descendant::text()[normalize-space(.)][4]", $root);

                    if (!empty($node)) {
                        $seg['DepName'] .= '. ' . $node;
                    }
                    $seg['ArrDate'] = strtotime($this->http->FindSingleNode("./descendant::tr[1]/td[3]/descendant::text()[normalize-space(.)][2]", $root) . ', ' . $this->http->FindSingleNode("./descendant::tr[1]/td[3]/descendant::text()[normalize-space(.)][3]", $root, true, "#\d+:\d+\s+[ap]m#"));
                    $seg['ArrName'] = $this->http->FindSingleNode("./descendant::tr[1]/td[3]/descendant::text()[normalize-space(.)][1]", $root);
                    $node = $this->http->FindSingleNode("./descendant::tr[1]/td[3]/descendant::text()[normalize-space(.)][4]", $root);

                    if (!empty($node)) {
                        $seg['ArrName'] .= '. ' . $node;
                    }
                }

                $seg['Seats'] = $this->http->FindNodes($q = "(//text()[normalize-space(.)='{$seg['DepName']} To {$seg['ArrName']}']/ancestor::tr[1]/following-sibling::tr)[position()>1]/td[2]", null, "#\((\d+\w)\)#");

                if (empty($seg['Seats'])) {
                    $depname = preg_replace("#(.+)\. .+#", '$1', $seg['DepName']);
                    $arrname = preg_replace("#(.+)\. .+#", '$1', $seg['ArrName']);
                    $seg['Seats'] = array_values(array_filter($this->http->FindNodes("//text()[normalize-space(.)='{$depname} To {$arrname}']/ancestor::tr[1]/following-sibling::tr[position()>1]", null, "#\((\d+\w)\)#")));
                }
            }

            if (preg_match("#(.+)(?: - |, | – |\. )(.*(?:Terminal|T).*)#u", $seg['DepName'], $mat)) {
                $seg['DepName'] = $mat[1];
                $seg['DepartureTerminal'] = $mat[2];
            }

            if (preg_match("#(.+)(?: - |, | – |\. )(.*(?:Terminal|T).*)(\n|$)#u", $seg['ArrName'], $mat)) {
                $seg['ArrName'] = $mat[1];
                $seg['ArrivalTerminal'] = $mat[2];
            }
            $seg['Duration'] = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(.), 'Starter')]/following::text()[normalize-space(.)][1]", $root, true, "#\s*Flight Duration:\s+(.+)#i");

            if (empty($seg['Duration'])) {
                $seg['Duration'] = $this->http->FindSingleNode(".", $root, true, "#\s*Flight Duration:\s+(.+)#i");
            }

            $seg['FlightNumber'] = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(.), 'Starter')]/preceding::text()[normalize-space(.)][2]", $root, true, "#[A-Z\d]{2}(\d{1,5})#");
            $seg['AirlineName'] = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(.), 'Starter')]/preceding::text()[normalize-space(.)][2]", $root, true, "#([A-Z\d]{2})\d{1,5}#");

            if (empty($seg['FlightNumber']) && empty($seg['AirlineName'])) {
                $seg['FlightNumber'] = $this->http->FindSingleNode("(.//preceding::img[contains(@src, 'jetstar-airways.gif') or (@width=13 and @height=10)]/preceding::text()[1])[last()]", $root, true, "#[A-Z\d]{2}(\d{1,5})#");
                $seg['AirlineName'] = $this->http->FindSingleNode("(.//preceding::img[contains(@src, 'jetstar-airways.gif') or (@width=13 and @height=10)]/preceding::text()[1])[last()]", $root, true, "#([A-Z\d]{2})\d{1,5}#");
            }
            $seg['Aircraft'] = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(.), 'Starter')]/preceding::text()[normalize-space(.)][1]", $root);

            if (empty($seg['Aircraft'])) {
                $seg['Aircraft'] = $this->http->FindSingleNode(".//preceding::text()[starts-with(normalize-space(.), '" . $seg['AirlineName'] . $seg['FlightNumber'] . "')]/following::text()[normalize-space(.)][1]", $root);
            }

            if (!empty($seg['DepName']) && !empty($seg['ArrName']) && !empty($seg['DepDate']) && !empty($seg['ArrDate'])) {
                $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }
            $finded = false;

            foreach ($it['TripSegments'] as $key => $trip) {
                if (isset($seg['AirlineName']) && $seg['FlightNumber'] && $seg['DepDate'] && $trip['AirlineName'] == $seg['AirlineName'] && $trip['FlightNumber'] == $seg['FlightNumber'] && $trip['DepDate'] == $seg['DepDate']) {
                    $finded = true;
                }
            }

            if ($finded == false) {
                $it['TripSegments'][] = $seg;
            }
        }

        return [$it];
    }
}
