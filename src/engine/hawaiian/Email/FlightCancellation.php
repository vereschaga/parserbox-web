<?php

namespace AwardWallet\Engine\hawaiian\Email;

use AwardWallet\Schema\Parser\Email\Email;

class FlightCancellation extends \TAccountChecker
{
    public $mailFiles = "hawaiian/it-57846938.eml, hawaiian/it-60024587.eml";
    public $reSubject = [
        'Confirmation of your itinerary cancellation', 'Confirmation of your itinerary cancelation',
        'Your upcoming flight has been cancelled', 'Your upcoming flight has been canceled',
        'URGENT: Important flight change notification',
    ];
    public $reFrom = ['HawaiianAirlines'];
    public $reBody = [
        'Cancelled Reservation Confirmation Code', 'Canceled Reservation Confirmation Code',
        'Your upcoming flight has been cancelled', 'Your upcoming flight has been canceled',
        'Flight Cancellation',
    ];
    public $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $refrom) {
            if (stripos($from, $refrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".hawaiianairlines.com/") or contains(@href,"www.hawaiianairlines.com") or contains(@href,"flightnotifications.hawaiianairlines.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"visit HawaiianAirlines") or contains(normalize-space(),"Hawaiian Airlines Reservations:") or contains(.,"@FlightNotifications.HawaiianAirlines")]')->length === 0
        ) {
            return false;
        }

        foreach ($this->reBody as $body) {
            if (strpos($this->http->Response["body"], $body) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = true;
        $flight = $email->add()->flight();
        $flight->general()->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Confirmation Code:')]", null, true, '/Confirmation Code:\s+([A-Z\d]+)$/'), 'Confirmation Code');

        $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Aloha')]", null, true, '/Aloha\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:[,;!?]|$)/u');

        if ($traveller) {
            $flight->general()->traveller($traveller, true);
        }

        if (($status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Your cancellation is confirmed') or starts-with(normalize-space(),'Your cancelation is confirmed')]", null, true, "/Your (cancell?ation is confirmed)\s*(?:[,.;!?]|$)/"))
            || ($status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Your upcoming flight has been cancelled') or starts-with(normalize-space(),'Your upcoming flight has been canceled')]", null, true, "/Your upcoming flight has been (cancell?ed)\s*(?:[,.;!?]|$)/"))
            || ($status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Flight') and contains(normalize-space(), 'has been canceled')]", null, true, "/has been (cancell?ed)\s*(?:[,.;!?]|$)/"))
        ) {
            $flight->general()
                ->status($status)
                ->cancelled();
        }

        $cancellationNumber = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Canceled Reservation Confirmation Code:')]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]+$/');
        $flight->general()->cancellationNumber($cancellationNumber, false, true);

        $tickets = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Ticket Number(s):')]/following::text()[normalize-space()][1]");

        if ($tickets) {
            $flight->issued()->tickets(preg_split('/\s*,\s*/', $tickets), false);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }
}
