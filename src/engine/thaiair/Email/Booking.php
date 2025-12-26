<?php

namespace AwardWallet\Engine\thaiair\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "thaiair/it-49395745.eml";
    public $From = '/.+[@]tg\.thaiairways\.com/';

    public $Subject = 'and Connect with THAI';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $flight = $email->add()->flight();

        $flight->addConfirmationNumber($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Booking Reference : ')]", null, true, '/[:]\s(.{6})/u'));

        $flight->general()->traveller($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Passenger')]/following::text()[normalize-space()][1]", null, true));

        if ($this->http->XPath->query("//text()[normalize-space()='Depart']/ancestor::*[1]")->length == 1) {
            $segment = $segment = $flight->addSegment();

            $segment->airline()
                ->number($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Flight')]/following::text()[normalize-space()][1]", null, true, '/\D+(\d+)/'))
                ->name($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Flight')]/following::text()[normalize-space()][1]", null, true, '/(\D+)/'));

            $dateDep = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Depart')]/following::text()[normalize-space()][1]", null, true, '/^.+\son\s(.+)\sat/u');
            $timeDep = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Depart')]/following::text()[normalize-space()][1]", null, true, '/\sat\s+(\d+:\d+\s\D+)/u');

            $segment->departure()
                ->date(strtotime($this->correctTimeString($dateDep . ' ' . $timeDep)))
                ->name($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Depart')]/following::text()[normalize-space()][1]", null, true, '/^(.+)\son/u'))
                ->noCode();

            $dateArriv = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Arrive')]/following::text()[normalize-space()][1]", null, true, '/^.+\son\s(.+)\sat/u');
            $timeArriv = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Arrive')]/following::text()[normalize-space()][1]", null, true, '/\sat\s+(\d+:\d+\s\D+)/u');

            $segment->arrival()
                ->date(strtotime($this->correctTimeString($dateArriv . ' ' . $timeArriv)))
                ->name($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Arrive')]/following::text()[normalize-space()][1]", null, true, '/^(.+)\son/'))
                ->noCode();
        } else {
            $this->logger->debug('More than one segments');
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (($this->http->XPath->query('//text()[contains(., "begins with THAI")]')->length > 0)
            and ($this->http->XPath->query('//text()[contains(., "Thank you for booking THAI")]')->length > 0)) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->From, $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return !empty($headers['subject']) && stripos($headers['subject'], $this->Subject) !== false;
    }

    private function correctTimeString($time)
    {
        if (preg_match("#(\d+)[:\.](\d+)\s*([ap]m)#i", $time, $m)) {
            if (($m[1] == 0 && stripos($m[3], 'am') !== false) || $m[1] > 12) {
                return preg_replace("#(\d+)[:\.](\d+)\s*([ap]m)#i", $m[1] . ":" . $m[2], $time);
            }
        } elseif (preg_match('/(\d+)\s+noon/i', $time, $m)) {
            return preg_replace('/(\d+)\s+noon/i', $m[1] . ':00', $time);
        }

        return $time;
    }
}
