<?php

namespace AwardWallet\Engine\klm\Email;

// TODO: merge with parsers klm/BookingHtml2017Nl (in favor of klm/ConfirmationBillet)

class ConfirmationBillet extends \TAccountChecker
{
    use \DateTimeTools;

    public $mailFiles = "klm/it-5814597.eml";

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Confirmation billet prime Flying Blue') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//node()[contains(.,"L\'Equipe Flying Blue")]')->length > 0
            && $this->http->XPath->query('//node()[contains(.,"Vols choisis")]')->length > 0;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'ConfirmationBillet',
        ];
    }

    public static function getEmailLanguages()
    {
        return ['fr'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function ParseEmail()
    {
        $it = [];
        $it['Kind'] = 'T';
        $it['RecordLocator'] = $this->http->FindSingleNode('//td[contains(normalize-space(.),"Référence de votre dossier") and not(.//td)]//*[(name(.)="strong" or name(.)="b") and normalize-space(.)!=""][1]', null, true, '/([A-Z\d]{5,7})/');
        $it['Status'] = $this->http->FindSingleNode('//td[contains(normalize-space(.),"Statut") and not(.//td)]//*[(name(.)="strong" or name(.)="b") and normalize-space(.)!=""][1]');
        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query('//table[contains(normalize-space(.),"Vols choisis") and not(.//table)]/following::table[.//td[normalize-space(.)=">"] and not(.//table)]');

        foreach ($segments as $segment) {
            $seg = [];
            $date = $this->http->FindSingleNode('./preceding::*[(name(.)="strong" or name(.)="b") and string-length(normalize-space(.))>4][1]', $segment, true, '/(\d{1,2}\s+[^\s\d]+\s+\d{4})/');
            $separators = $this->http->XPath->query('.//td[normalize-space(.)=">"]', $segment);
            $separator = $separators->item(0);
            $pattern = '/(\d{2}:\d{2})[-\s]{1,}([^(]+)\s+\(([A-Z]{3})\)/';
            $departure = $this->http->FindSingleNode('./preceding-sibling::td[normalize-space(.)!=""][1]', $separator);

            if (preg_match($pattern, $departure, $matches)) {
                $timeDep = $matches[1];
                $seg['DepName'] = $matches[2];
                $seg['DepCode'] = $matches[3];
            }
            $arrival = $this->http->FindSingleNode('./following-sibling::td[normalize-space(.)!=""][1]', $separator);

            if (preg_match($pattern, $arrival, $matches)) {
                $timeArr = $matches[1];
                $seg['ArrName'] = $matches[2];
                $seg['ArrCode'] = $matches[3];
            }
            $flight = $this->http->FindSingleNode('(.//tr[1]/td[normalize-space(.)!=""])[last()]', $segment);

            if (preg_match('/^([A-Z\d]{2})([\d]+)/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            }

            if ($date && $timeDep && $timeArr) {
                $date = strtotime($this->dateStringToEnglish($date));
                $seg['DepDate'] = strtotime($timeDep, $date);
                $seg['ArrDate'] = strtotime($timeArr, $date);
            }
            $nextTables = $this->http->XPath->query('./following::table[not(.//table) and string-length(normalize-space(.))>10][1]', $segment);
            $nextTable = $nextTables->item(0);
            $seg['Cabin'] = $this->http->FindSingleNode('.//td[starts-with(normalize-space(.),"Classe") and not(.//td)]', $nextTable, true, '/Classe\s*:\s*(\b.{3,}\b)\s*$/i');
            $seg['Duration'] = $this->http->FindSingleNode('.//td[starts-with(normalize-space(.),"Durée de vol") and not(.//td)]', $nextTable, true, '/(\d{2}h\d{2})/i');
            $seg['Meal'] = $this->http->FindSingleNode('.//td[starts-with(normalize-space(.),"Repas à bord") and not(.//td)]', $nextTable, true, '/Repas\s+à\s+bord\s*:\s*(\b.{4,}\b)\s*$/i');
            $seg['Operator'] = $this->http->FindSingleNode('.//td[starts-with(normalize-space(.),"Effectué par") and not(.//td)]', $nextTable, true, '/Effectué\s+par\s*:\s*(\b.{2,}\b)\s*$/i');
            $seg['Aircraft'] = $this->http->FindSingleNode('.//td[starts-with(normalize-space(.),"Appareil") and not(.//td)]', $nextTable, true, '/Appareil\s*:\s*(\b.{3,}\b)\s*$/i');
            $seg['DepartureTerminal'] = $this->http->FindSingleNode('.//td[starts-with(normalize-space(.),"Terminal de départ") and not(.//td)]', $nextTable, true, '/Terminal\s+de\s+départ\s*:\s*([A-Z\d]{1,3})\s*$/i');
            $seg['ArrivalTerminal'] = $this->http->FindSingleNode('.//td[starts-with(normalize-space(.),"Terminal d\'arrivée") and not(.//td)]', $nextTable, true, '/Terminal\s+d\'arrivée\s*:\s*([A-Z\d]{1,3})\s*$/i');
            $it['TripSegments'][] = $seg;
        }
        $it['Passengers'] = $this->http->FindNodes('//table[contains(normalize-space(.),"Informations passagers") and not(.//table)]/following::table[contains(normalize-space(.),"Numéro(s) de billet") and not(.//table)]/preceding::table[normalize-space(.)!="" and not(.//table) and .//*[name(.)="strong" or name(.)="b"]][1]');
        $it['AccountNumbers'] = $this->http->FindNodes('//table[contains(normalize-space(.),"Informations passagers") and not(.//table)]/following::table[contains(normalize-space(.),"Numéro(s) de billet") and not(.//table)]//*[(name(.)="strong" or name(.)="b") and normalize-space(.)!=""][1]');
        $nodes = $this->http->XPath->query('//table[contains(normalize-space(.),"Paiement") and not(.//table)]');
        $it['SpentAwards'] = $this->http->FindSingleNode('./following::table[contains(normalize-space(.),"Coût du billet") and not(.//table)][1]//td[normalize-space(.)!=""][2]', $nodes->item(0), true, '/(\d+)\s+Miles/i');
        $payment = $this->http->FindSingleNode('./following::table[contains(normalize-space(.),"Montant total payé en ligne") and not(.//table)][1]//td[normalize-space(.)!=""][2]', $nodes->item(0));

        if (preg_match('/^([,.\d]+)\s*([^(,.\d]*)\s*/', $payment, $matches)) {
            $tax = $this->http->FindSingleNode('./following::table[starts-with(normalize-space(.),"Taxes") and not(.//table)][1]//td[normalize-space(.)!=""][2]', $nodes->item(0), true, '/^([,.\d]+)/');
            $tax = preg_replace('/\s+/', '', $tax);				// 11 507.00	->	11507.00
            $tax = preg_replace('/[,.](\d{3})/', '$1', $tax);	// 2,790		->	2790		or	4.100,00	->	4100,00
            $tax = preg_replace('/,(\d{2})$/', '.$1', $tax);	// 18800,00		->	18800.00
            $it['Tax'] = $tax;
            $totalCharge = preg_replace('/\s+/', '', $matches[1]);				// 11 507.00	->	11507.00
            $totalCharge = preg_replace('/[,.](\d{3})/', '$1', $totalCharge);	// 2,790		->	2790		or	4.100,00	->	4100,00
            $totalCharge = preg_replace('/,(\d{2})$/', '.$1', $totalCharge);	// 18800,00		->	18800.00
            $it['TotalCharge'] = $totalCharge;
            $it['Currency'] = $matches[2];
        }

        return $it;
    }
}
