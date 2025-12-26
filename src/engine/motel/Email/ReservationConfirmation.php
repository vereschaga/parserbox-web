<?php

namespace AwardWallet\Engine\motel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $reProvider = "#motel6\.com#i";
    public $rePlain = ["#Motel\s+6\s+Reservation\s+(?:Confirmation|Cancellation)#i", "#Thank\s*you\s*for\s*choosing Motel\s*6#"];
    public $reSubject = "#Motel\s+6\s+Reservation\s+(?:Confirmation|Cancellation)#i";
    public $mailFiles = "motel/it-11236335.eml, motel/it-1813883.eml, motel/it-222893447.eml, motel/it-33799844.eml";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = text($this->http->Response['body']);

        $r = $email->add()->hotel();
        $r->general()
            ->confirmation($this->re('#Confirmation\s+Number:\s+([\w\-]+)#i', $text), 'Confirmation Number')
            ->traveller($this->re('#Guest\s+Name:\s+(.*?)(?:Arrival|Check|\n)#i', $text));

        $cancellation = $this->re('#Cancellation\s+Number:\s+([\w\-]+)#i', $text);

        if (!empty($cancellation)
            || preg_match("#Motel\s+6\s+Reservation\s+Cancellation\s+([\w\-]+)#", $parser->getSubject(), $m)
        ) {
            $r->general()->cancelled();

            if (empty($cancellation) && isset($m)) {
                $cancellation = $m[1];
            }

            $r->general()
                ->confirmation($cancellation, 'Cancellation Number', true);
        }
        $total = $this->getTotalCurrency($this->re('#Total(?:\s+w\/tax)?\*?:\s*([^:]+?\b[A-Z]{3}\b)#', $text));

        if (!empty($total['Total'])) {
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);

            $cost = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Subtotal:')]", null, true, "/Subtotal:\s*\D([\d\.\,]+)\s*[A-Z]{3}/");

            if (!empty($cost)) {
                $r->price()
                    ->cost($cost);
            }

            $tax = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Taxes:')]", null, true, "/Taxes:\s*\D([\d\.\,]+)\s*[A-Z]{3}/");

            if (!empty($tax)) {
                $r->price()
                    ->tax($tax);
            }
        }

        $r->booked()
            ->checkIn(strtotime($this->re('#(?:Arrival\s+Date|Check[ -]*in):\s+(.*?)(?:Check|\n)#i', $text)))
            ->checkOut(strtotime($this->re('#(?:Departure\s+Date|Check[ -]*out):\s+(.*?)(?:Guarantee type|\n)#i',
                $text)))
            ->guests($this->re('#Number\s+of\s+Adults:\s+(\d+)#i', $text))
            ->rooms($this->re('#Number\s+of\s+Rooms:\s+(\d+)#i', $text));

        $kids = $this->re("/Number\s*of\s*children\:\s*(\d+)/i", $text);

        if ($kids !== null) {
            $r->booked()
                ->kids($kids);
        }

        // it-1813883.eml
        $regex = '#';
        $regex .= '(.*)\s\#\d+';
        $regex .= '((?s).*)\s+';
        $regex .= 'Phone:\s+(.*)\s+';
        $regex .= 'Fax:\s+(.*)';
        $regex .= '#i';

        if (preg_match($regex, $text, $m)) {
            $r->hotel()
                ->name($this->nice($m[1]))
                ->address($this->nice($m[2], ','))
                ->phone($m[3])
                ->fax($m[4]);
        } else {
            // it-11236335.eml
            $contactsTexts = $this->http->FindNodes('//text()[normalize-space(.)="Confirmation Number:"]/ancestor::table[contains(@align,"right")][1]/../table[contains(@align,"left") and descendant::img]/descendant::text()[normalize-space(.) and not(contains(normalize-space(.),"Get Directions"))]');

            if (empty($contactsTexts)) {
                $contactsTexts = $this->http->FindNodes("//text()[normalize-space(.)='Confirmation Number:']/following::img[contains(@src, 'location')]/following::text()[string-length()>5][1]/ancestor::table[contains(normalize-space(), 'Get directions')][1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Get directions'))]");
            }
            $addressTexts = [];

            foreach ($contactsTexts as $key => $contactsText) {
                if ($key === 0) {
                    $r->hotel()->name($contactsText);

                    continue;
                }

                if (preg_match('/^\s*[+)(\d][-.\s\d)(]{5,}[\d)(]\s*$/', $contactsText)) {
                    $r->hotel()->phone($contactsText);

                    break;
                }
                $addressTexts[] = trim($contactsText);
            }
            $r->hotel()->address(implode(' ', $addressTexts));
        }

        $room = $r->addRoom();
        $rateValue = $this->re('#Average rate:\s*([^:]+?\b[A-Z]{3}\b)#', $text);

        if (!empty($rateValue)) {
            $room->setRate($rateValue . ' / night');
        }
        $room->setDescription($this->re('#Room\s+Description:\s+(.*?)(?:Guest comments|\n)#i', $text));

        $cancellationTexts = implode(' ',
            $this->http->FindNodes('//text()[contains(normalize-space(.),"Cancellation Policy")]/following::text()[normalize-space(.)][1]/ancestor::*[1][contains(.,"Cancel") or contains(.,"cancel") or contains(.,"CANCEL")]/descendant::text()[preceding::text()[contains(normalize-space(.),"Cancellation Policy")]]'));

        if (!empty($cancellationTexts)) {
            $r->general()->cancellation($cancellationTexts);
            $this->detectDeadLine($r, $cancellationTexts);
        }

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach ($this->rePlain as $body) {
            if (preg_match($body, $this->http->Response['body'])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match($this->reSubject, $headers['subject']) > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->reProvider, $from) > 0;
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        if (preg_match("/If cancellation is necessary, to avoid billing,? you must cancel and receive a cancel number prior to (?<time>\d+:\d+\s*[AaPp][mM]) .+? on the arrival date./",
            $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative('0 days', $m['time']);
        } elseif (preg_match("/Must cancel (?<days>\d+) days? prior to arrival If cancellation is necessary, to avoid billing, you must cancel and receive a cancel number prior to the number of hours or time on the cancellation policy./",
            $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['days'] . ' days');
        } elseif (preg_match("/If you must change plans, you can cancel for free until no later than (?<time>\d+A?P?M) local motel time on \w+\,\s+(?<date>\w+\s*\d+\,\s*\d{4})/",
            $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m['date'] . ', ' . $m['time']));
        }
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function glue($str, $with = ", ")
    {
        $source = is_array($str) ? $str : explode("\n", $str);

        return implode($with, $source);
    }

    private function nice($str, $with = ", ")
    {
        $source = trim($this->glue($str, $with));
        $source = preg_replace("/\s+/", ' ', $source);
        $source = preg_replace("/\s+,\s+/", ', ', $source);

        return $source;
    }
}
