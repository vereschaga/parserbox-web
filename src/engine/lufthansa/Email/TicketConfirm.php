<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class TicketConfirm extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-28658039.eml, lufthansa/it-308953673.eml, lufthansa/it-31541924.eml, lufthansa/it-4487589.eml, lufthansa/it-536330967.eml"; // +1 bcdtravel(html)[it]

    public $subj;

    public $reFrom = 'lufthansa.com';
    public $reSubject = [
        'en' => 'You are checked-in', // + for other lang
        'de' => 'Sie sind eingecheckt',
        'it' => 'Boarding Pass',
    ];

    public $lang = '';

    public $reBody = 'Lufthansa';
    public $reBody2 = [
        'de'  => 'Buchungscode',
        'en'  => 'Booking code',
        'it'  => 'Codice di prenotazione',
        'es'  => 'Código de reserva',
        'ru'  => 'Код бронирования',
        'pl'  => 'Numer rezerwacji',
        'fr'  => 'Code de réservation',
        'pt'  => 'Código da reserva',
        'ja'  => '予約番号',
        'ko'  => '항공편 상세 정보',
        'en2' => 'Lufthansa wishes you a pleasant flight',
        'en3' => 'View flight status',
    ];

    public static $dictionary = [
        'de' => [
            //            'Coach' => '', // need to translate
        ],
        'en' => [
            'Buchungscode' => 'Booking code',
            'Datum'        => 'Date',
            'Abflug'       => 'Departure',
            'Flug'         => 'Flight',
            'Flugdetails'  => 'Flight details',
            'Ticketnummer' => 'Ticket number',
            'Klasse'       => 'Class',
            'Sitz'         => 'Seat',
            'Name'         => 'Passenger name',
            'Coach'        => 'Coach',
        ],
        'it' => [
            'Buchungscode' => 'Codice di prenotazione',
            'Datum'        => 'Date',
            'Abflug'       => 'Partenza',
            'Flug'         => 'Volo',
            'Flugdetails'  => 'Dati del volo',
            'Ticketnummer' => 'Numero del biglietto',
            'Klasse'       => 'Class',
            'Sitz'         => 'Seat',
            'Name'         => 'Passenger name',
            //            'Coach' => '',
        ],
        'es' => [
            'Buchungscode' => 'Código de reserva',
            'Datum'        => 'Date',
            'Abflug'       => 'Salida',
            'Flug'         => 'Vuelo',
            'Flugdetails'  => 'Datos del vuelo',
            'Ticketnummer' => 'Número de billete',
            'Klasse'       => 'Class',
            'Sitz'         => 'Seat',
            'Name'         => 'Passenger name',
            //            'Coach' => '',
        ],
        'ru' => [
            'Buchungscode' => 'Код бронирования',
            'Datum'        => 'Date',
            'Abflug'       => 'Вылет',
            'Flug'         => 'Рейс',
            'Flugdetails'  => 'Сведения о перелёте',
            'Ticketnummer' => 'Номер билета',
            'Klasse'       => 'Class',
            'Sitz'         => 'Seat',
            'Name'         => 'Passenger name',
            //            'Coach' => '',
        ],
        'pl' => [
            'Buchungscode' => 'Numer rezerwacji',
            'Datum'        => 'Date',
            'Abflug'       => 'Wylot',
            'Flug'         => 'Rejs',
            'Flugdetails'  => 'Szczegóły podróży',
            'Ticketnummer' => 'Numer biletu',
            'Klasse'       => 'Class',
            'Sitz'         => 'Seat',
            'Name'         => 'Passenger name',
            //            'Coach' => '',
        ],
        'fr' => [
            'Buchungscode' => 'Code de réservation',
            'Datum'        => 'Date',
            'Abflug'       => 'Départ',
            'Flug'         => 'Vol',
            'Flugdetails'  => 'Détails du vol',
            'Ticketnummer' => 'Numéro du billet',
            'Klasse'       => 'Class',
            'Sitz'         => 'Seat',
            'Name'         => 'Passenger name',
            //            'Coach' => '',
        ],
        'pt' => [
            'Buchungscode' => 'Código da reserva',
            'Datum'        => 'Date',
            'Abflug'       => 'Partida',
            'Flug'         => 'Voo',
            'Flugdetails'  => 'Dados do voo',
            'Ticketnummer' => 'Número do bilhete',
            'Klasse'       => 'Class',
            'Sitz'         => 'Seat',
            'Name'         => 'Passenger name',
            //            'Coach' => '',
        ],
        'ja' => [
            'Buchungscode' => '予約番号',
            'Datum'        => 'Date',
            'Abflug'       => '出発',
            'Flug'         => '運航航空会社:',
            'Flugdetails'  => 'フライトの詳細',
            'Ticketnummer' => '航空券番号',
            'Klasse'       => 'Class',
            'Sitz'         => 'Seat',
            'Name'         => 'Passenger name',
            'Coach'        => 'Coach',
        ],
        'ko' => [
            'Buchungscode' => '예약 코드',
            'Datum'        => 'Date',
            'Abflug'       => '출발',
            'Flug'         => '운항사',
            'Flugdetails'  => '항공편 상세 정보',
            'Ticketnummer' => '티켓 번호',
            'Klasse'       => 'Class',
            'Sitz'         => 'Seat',
            'Name'         => 'Passenger name',
            'Coach'        => 'Coach',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subj = $parser->getHeader('subject');

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response['body'], $re) !== false
                || $this->http->XPath->query("//text()[{$this->contains($re)}]")->length > 0
            ) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }
        $email->setType('TicketConfirm' . ucfirst($this->lang));

        $this->parseEmail($email);

        $bpAttachments = $parser->searchAttachmentByName('boardingpass_[a-z0-9]+\.gif');

        if (count($bpAttachments) > 0 && count($email->getItineraries()) > 0 && $email->getItineraries()[0]->getType() === 'flight') {
            $name = $parser->getAttachmentHeader($bpAttachments[0], 'Content-Type');

            if ($name && preg_match('/name="?(?<name>boardingpass_[a-z0-9]+\.gif)"?/i', $name, $m)) {
                $this->ParseBP($email, $email->getItineraries()[0], $m['name']);
            }
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'],$headers['subject'])) {
            return false;
        }

        if (strpos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(Email $email): void
    {
        if (empty($this->http->FindSingleNode("//text()[normalize-space()='{$this->t('Sitz')}']/preceding::text()[normalize-space()][position() < 6][normalize-space()='{$this->t('Coach')}']"))) {
            $isTrain = false;
            $f = $email->add()->flight();
        } else {
            $isTrain = true;
            $f = $email->add()->train();
        }

        $confirmation = $this->http->FindSingleNode("//*[normalize-space(text()) = '{$this->t('Buchungscode')}']/preceding::*[normalize-space(.)!=''][1]", null, true, "#[A-Z\d]{5,7}#");

        if ($confirmation) {
            $f->general()->confirmation($confirmation);
        } elseif (empty($this->http->FindSingleNode("(//*[normalize-space(text()) = '{$this->t('Buchungscode')}'])[1]"))
            && empty($this->http->FindSingleNode("(//*[normalize-space(text())='{$this->t('Ticketnummer')}'])[1]"))
        ) {
            $f->general()->noConfirmation();
        }

        $traveller = $this->http->FindSingleNode("//*[contains(text(), '{$this->t('Name')}')]/preceding::*[normalize-space(.)!=''][1]");
        $infant = false;

        if (preg_match("/^\s*(.+?)\s*\(\s*infant\s*\)\s*$/i", $traveller, $m)) {
            $infant = true;
            $traveller = $m[1];
        }
        $traveller = preg_replace("/^\s*([^,]+?)\s*,\s*([^,]+?)\s*$/", '$2 $1',
            preg_replace("/ (MR|MRS|MS)\s*$/i", '', $traveller));

        if ($infant) {
            $f->general()->infant($traveller);
        } else {
            $f->general()->traveller($traveller);
        }

        $ticket = $this->http->FindSingleNode("//*[normalize-space(text())='{$this->t('Ticketnummer')}']/preceding::*[normalize-space(.)!=''][1]", null, true, "/(\d{6,})/");

        if (!empty($ticket)) {
            //$f->issued() works only for flight, not train
            $f->addTicketNumber($ticket, false);
        }

        $s = $f->addSegment();

        $flight = $this->http->FindSingleNode("//text()[normalize-space()='{$this->t('Flugdetails')}']/following::text()[normalize-space()!=''][1]");

        if (preg_match('/(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)/', $flight, $m)) {
            if ($isTrain === true) {
                $s->extra()->service($m['name'])->number($m['number']);
            } else {
                $s->airline()->name($m['name'])->number($m['number']);
            }
        }

        $depDate = $this->http->FindSingleNode("//*[text()='{$this->t('Datum')}']/preceding::*[normalize-space()][1]", null, true, "/^.*\d.*$/");
        $depTime = $this->http->FindSingleNode("//*[text()='{$this->t('Abflug')}']/preceding::*[normalize-space()][1]", null, true, "/^.*\d.*$/");

        if (preg_match('/(?<Day>\d{2})\s*((?<Month>[[:alpha:]]+)|(?<Month_num>\d+)[月월])\s*(?<Year>\d{2,4})/u', $depDate, $math)) {
            if ($en = MonthTranslate::translate($math['Month'], $this->lang)) {
                $math['Month'] = $en;
            }

            if (!empty($math['Month_num'])) {
                $math['Month'] = date('F', mktime(0, 0, 0, $math['Month_num'], 10));
            }

            if (strlen($math['Year']) === 2) {
                $math['Year'] = '20' . $math['Year'];
            }
            $date = $math['Day'] . ' ' . $math['Month'] . ' ' . $math['Year'];

            if (empty($depTime)) {
                $s->departure()->day2($date)->noDate();
            } else {
                $s->departure()->date2($date . ' ' . $depTime);
            }
            $s->arrival()->noDate();
        }

        $depName = $this->getDepArr(1);

        if (!empty($depName) && is_array($depName)) {
            $s->departure()->code($depName['Code'])->name($depName['Name']);
        }

        $terminalDep = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Terminal:')]", null, true, "#Terminal:\s*(.+)#");

        if ($terminalDep && $isTrain === false) {
            $s->departure()->terminal($terminalDep);
        }

        $arrName = $this->getDepArr(3);

        if (!empty($arrName) && is_array($arrName)) {
            $s->arrival()->code($arrName['Code'])->name($arrName['Name']);
        }

        if ($isTrain === false && (empty($s->getDepCode()) || empty($s->getArrCode())) && !empty($s->getAirlineName()) && !empty($s->getFlightNumber())) {
            if (preg_match("/" . $s->getAirlineName() . $s->getFlightNumber() . ",\s*([A-Z]{3})\s*-\s*([A-Z]{3})/", $this->subj, $m)) {
                $s->departure()->code($m[1]);
                $s->arrival()->code($m[2]);
            }
        }
        $s->extra()->cabin($this->http->FindSingleNode("//*[normalize-space(text())='{$this->t('Klasse')}']/preceding::*[normalize-space(.)!=''][1]", null, false));

        $seat = $this->http->FindSingleNode("//*[normalize-space(text())='{$this->t('Sitz')}']/preceding::*[normalize-space(.)!=''][1]");

        if (!empty($seat)) {
            if ($isTrain === true && preg_match('/' . $this->t('Coach') . ' (?<coach>[\dA-Z]{1,4})\s*\/\s*(?<seat>[\dA-Z]{1,4})\s*$/', $seat, $m)
                || $isTrain === false && preg_match('/^\s*(?<seat>\d{1,3}[A-Z])\s*$/', $seat, $m)
            ) {
                $s->extra()->seat($m['seat']);

                if (!empty($m['coach'])) {
                    $s->extra()->car($m['coach']);
                }
            }
        }
    }

    private function ParseBP(Email $email, Flight $f, $name): void
    {
        $bp = $email->add()->bpass();

        if (count($f->getSegments()) === 0) {
            return;
        }
        $s = $f->getSegments()[0];

        $bp->setFlightNumber($s->getAirlineName() . ' ' . $s->getFlightNumber());
        $bp->setDepCode($s->getDepCode());
        $bp->setDepDate($s->getDepDate() ?? $s->getDepDay());
        $bp->setAttachmentName($name);

        foreach ($f->getTravellers() as $traveller) {
            $bp->setTraveller($traveller[0]);

            break;
        }

        foreach ($f->getConfirmationNumbers() as $number) {
            $bp->setRecordLocator($number[0]);

            break;
        }
    }

    /**
     * find td by position $xOffset.
     *
     * @param int $xOffset
     *
     * @return array|null
     */
    private function getDepArr($xOffset)
    {
        $str = $this->http->FindSingleNode("//*[text()='{$this->t('Datum')}']/following::tr[3]/descendant::td[{$xOffset}]");

        if (preg_match('#\b([A-Z]{3})\s+(.+)#', $str, $m)) {
            return [
                'Code' => $m[1],
                'Name' => $m[2],
            ];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
