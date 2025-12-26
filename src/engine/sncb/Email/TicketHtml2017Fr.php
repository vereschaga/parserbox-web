<?php

// bcdtravel

namespace AwardWallet\Engine\sncb\Email;

class TicketHtml2017Fr extends \TAccountChecker
{
    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (preg_match('#(\d+)/(\d+)/(\d{4})\s*$#', $parser->getSubject(), $matches)) {
            array_shift($matches);
            $this->date = strtotime(join('-', $matches));
        }

        $this->parseSegments();

        return [
            'emailType'  => '',
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@sncf.fr') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Vos voyages e-billet ') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'SNCF vous remercie d\'avoir choisi le e-billet') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@sncf.fr') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['fr'];
    }

    protected function parseSegments()
    {
        $this->result = ['Kind' => 'T', 'RecordLocator' => CONFNO_UNKNOWN, 'TripCategory' => TRIP_CATEGORY_TRAIN, 'TotalCharge' => 0];
        $this->result['Passengers'] = $this->http->FindNodes('//text()[contains(., "Détail du voyage de")]', null, '/voyage de\s+(.+)/');

        foreach ($this->http->FindNodes('//text()[contains(., "e-billet n°")]') as $ticket) {
            if (preg_match('/e-billet n° (\d+) d\'un montant de ([\d.,]+)\s*([A-Z]{3})/', $ticket, $matches)) {
                $this->result['TicketNumbers'][] = $matches[1];
                $this->result['TotalCharge'] += (float) str_replace(',', '', $matches[2]);
                $this->result['Currency'] = $matches[3];
            }
        }

        foreach ($this->http->XPath->query('//table[contains(., "Départ de") and contains(., "Arrivée à") and not(.//tr//tr)]') as $root) {
            $i = [];
            $i['DepCode'] = $i['ArrCode'] = TRIP_CODE_UNKNOWN;

            if (preg_match('#Départ de\s+(.+?)\s+le\s+(\d+/\d+.+)#us', $this->http->FindSingleNode('.//tr[1]', $root), $matches)) {
                $i['DepName'] = $matches[1];
                $i['DepDate'] = strtotime($this->normalizeDate($matches[2]));
            }

            if (preg_match('#([A-Z]+\d+)\s+(?:VOITURE\s+(\d+) - PLACE (\d+)\s*)?-\s*CLASSE (\d+)#us', $this->http->FindSingleNode('.//tr[2]', $root), $matches)) {
                $i['FlightNumber'] = $matches[1];

                if (!empty($matches[2])) {
                    $i['Type'] = $matches[2];
                }

                if (!empty($matches[3])) {
                    $i['Seats'][] = $matches[3];
                }

                if (!empty($matches[4])) {
                    $i['BookingClass'] = $matches[4];
                }
            }

            if (preg_match('#Arrivée à\s+(.+?)\s+le\s+(\d+/\d+.+)#us', $this->http->FindSingleNode('.//tr[contains(., "Arrivée à")]', $root), $matches)) {
                $i['ArrName'] = $matches[1];
                $i['ArrDate'] = strtotime($this->normalizeDate($matches[2]));
            }

            $this->result['TripSegments'][] = $i;
        }
    }

    protected function normalizeDate($subject)
    {
        $year = date('Y', $this->date);
        $pattern = [
            // 18/02 à 12h11
            '#^(\d+)/(\d+).*?(\d+)h(\d+)$#',
        ];
        $replacement = [
            $year . '-$2-$1, $3:$4',
        ];

        return preg_replace($pattern, $replacement, trim($subject));
    }
}
