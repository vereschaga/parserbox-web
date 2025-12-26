<?php

namespace AwardWallet\Engine\rentacar\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "rentacar/it-61023326.eml, rentacar/it-61299885.eml";
    public $reFrom = '@enterprisecarclub.co.uk';

    public $reSubject = [
        'Enterprise Car Club - CHANGED Reservation Confirmation',
        'Enterprise Car Club - NEW Reservation Confirmation',
        'Enterprise Car Club - RECONFIRMED Reservation Confirmation',
    ];
    public $reBody = [
        ["Your reservation details are listed below", "The following reservation has been cancelled"],
    ];

    public $langDetect = [
        "en" => "Reservation ID",
    ];

    private $lang = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody as $re) {
            if (stripos($body, $re[0]) !== false || stripos($body, $re[1]) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->rental();

        $confirmation = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Reservation ID:')]/following::text()[normalize-space()][1]");
        $confDesc = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Reservation ID:')]");
        $r->general()
            ->confirmation($confirmation, trim($confDesc, ':'))
            ->traveller($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Hi')]", null, true, '/Hi\s+(\D+)\,/'), false);

        $model = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Vehicle:')]/following::text()[normalize-space()][1]", null, true, "/^.+\s*\-\s*(.+)$/");

        if (!empty($model)) {
            $r->car()
                ->model($model);
        }

        $location = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Location:')]/following::text()[normalize-space()][1]");

        if (!empty($location)) {
            $r->pickup()
                ->location($location);
        }

        $phone = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'low rate number')]", null, true, "/low\s+rate\s+number\s+([\d\s]+)\./");

        if (!empty($phone)) {
            $r->pickup()
                ->phone($phone);
        }

        $hours = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'low rate number')]", null, true, "/hours\s+are\s+(.+)\.\s+Alternatively/");

        if (!empty($hours)) {
            $r->pickup()
                ->openingHours($hours);
        }

        $datePickUp = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Reservation start:')]/following::text()[normalize-space()][1]");

        if (!empty($datePickUp)) {
            $r->pickup()
                ->date($this->normalizeDate($datePickUp));
        }

        $dateDropOff = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Reservation end:')]/following::text()[normalize-space()][1]");

        if (!empty($dateDropOff)) {
            $r->dropoff()
                ->date($this->normalizeDate($dateDropOff));
        }

        $r->dropoff()
            ->same();

        if (!empty($this->http->FindSingleNode("//text()[contains(normalize-space(), 'following reservation has been cancelled')]"))) {
            $r->general()
                ->status('cancelled')
                ->cancelled();
        }
    }

    private function assignLang()
    {
        foreach ($this->langDetect as $lang => $option) {
            if (strpos($this->http->Response["body"], $option) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }
    }

    private function normalizeDate($date)
    {
        $this->logger->debug('IN-' . $date);
        $in = [
            '#^([\d\:]+)\s*\w+\,\s*(\d+\s*\w+\s*\d{4})$#', //04:30 Sunday, 24 February 2019
        ];
        $out = [
            '$2, $1',
        ];
        $str = preg_replace($in, $out, $date);

        $this->logger->debug('OUT-' . $str);

        return strtotime($str);
    }
}
