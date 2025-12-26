<?php

namespace AwardWallet\Engine\vhotels\Email;

use AwardWallet\Schema\Parser\Email\Email;

class VirginHotels extends \TAccountChecker
{
    public $mailFiles = "vhotels/it-3321257.eml, vhotels/it-3321258.eml, vhotels/it-3514806.eml, vhotels/it-47933527.eml";
    private $subject;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
        $this->parseHotel($email);
        $email->setType('VirginHotels');

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/Here is your Virgin Hotel.{3,} Confirm/i', $headers['subject']) > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'virginhotels.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//tr/descendant::text()['
                . 'contains(normalize-space(),"So you chose Virgin Hotels for your stay ")'
                . 'or contains(normalize-space(),"Thank you for choosing Virgin Hotels for your Chicago stay")'
                . 'or contains(normalize-space(),"Thank you for choosing Virgin Hotels for your San Francisco stay")'
                . 'or contains(normalize-space(),"Thank you for choosing Virgin Hotels for your New Orleans stay")'
                . 'or contains(normalize-space(),"Thank you for choosing Virgin Hotels for your Nashville stay")'
                . ']')->length > 0;
    }

    private function parseHotel(Email $email): void
    {
        $mainContentHtml = $this->http->FindHTMLByXpath("//text()[contains(normalize-space(),'Arrival')]/ancestor::tr[ descendant::text()[contains(normalize-space(),'Departure')] ][1]");
        $mainContent = $this->htmlToText($mainContentHtml);

        $h = $email->add()->hotel();

        $h->general()
            ->traveller(preg_match("/^[> ]*Hello[ ]+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[,.;!? ]*$/m",
                $mainContent, $m) ? $m[1] : null)
            ->confirmation(preg_match("/^[> ]*Confirmation Number[: ]+([-A-Z\d]{4,})[ ]*$/m", $mainContent,
                $m) ? $m[1] : null);

        $xpathConfNo = "descendant::text()[contains(normalize-space(),'Confirmation Number')][1]";

        $hotelName_temp = preg_match("/Here is your\s+(.{3,}?)\s+Confirm/i", $this->subject, $m) ? $m[1] : null;

        if (empty($hotelName_temp)) {
            $hotelName_temp = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'This email was sent by')]",
                null, true, "/This email was sent by[:\s]+(.{3,})/");
        }

        if (empty($hotelName_temp)) {
            $hotelName_temp = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'This email was sent by')]/following::text()[normalize-space()][1]",
                null, true, "/^[:\s]+(.{3,})/");
        }
        $hotelName = $this->http->XPath->query($xpathConfNo . '/following::text()[contains(normalize-space(),"' . $hotelName_temp . '")]')->length > 1 ? $hotelName_temp : null;

        if (empty($hotelName)) {
            $hotelName_temp = preg_replace('/\b(Hotel)\b/i', '$1s', $hotelName_temp);
            $hotelName = $this->http->XPath->query($xpathConfNo . '/following::text()[contains(normalize-space(),"' . $hotelName_temp . '")]')->length > 1 ? $hotelName_temp : null;
        }

        // hard-code hotels & address
        $supportedHotels = [
            [
                'name'           => 'Virgin Hotels Chicago', // it-3321257.eml
                'addressDetects' => ['203 N. Wabash Ave. Chicago, Illinois, 60601, United States', '203 N. Wabash Ave. Chicago, Illinois 60601, United States'],
            ],
            [
                'name'           => 'Virgin Hotels San Francisco', // it-47933527.eml
                'addressDetects' => ['250 4th Street, San Francisco, California 94103, United States'],
            ],
            [
                'name'           => 'Virgin Hotels New Orleans',
                'addressDetects' => ['550 Baronne St, New Orleans, LA 70133, United States'],
            ],
            [
                'name'           => 'Virgin Hotels Nashville',
                'addressDetects' => ['1 Music Square W, Nashville, TN 37203, United States'],
            ],
        ];
        $address = null;

        if (!empty($hotelName)) {
            foreach ($supportedHotels as $hotel) {
                if (empty($hotel['name']) || !is_string($hotel['name']) || empty($hotel['addressDetects']) || !is_array($hotel['addressDetects'])) {
                    continue;
                }

                if ($hotel['name'] !== $hotelName) {
                    continue;
                }

                foreach ($hotel['addressDetects'] as $phrase) {
                    if ($this->http->XPath->query('//node()[contains(normalize-space(),"' . $phrase . '") or contains(normalize-space(),"' . strtoupper($phrase) . '")]')->length > 0) {
                        $address = $phrase;

                        break 2;
                    }
                }
            }
        }

        $h->hotel()
            ->name($hotelName)
            ->address($address);

        $h->booked()
            ->checkIn2(preg_match("/^[> ]*Arrival Date[: ]+(.{6,}?)[ ]*$/m", $mainContent, $m) ? $m[1] : null)
            ->checkOut2(preg_match("/^[> ]*Departure Date[: ]+(.{6,}?)[ ]*$/m", $mainContent, $m) ? $m[1] : null);

        $room = $h->addRoom();
        $room
            ->setType(preg_match("/^[> ]*Room Type[: ]+(.{2,}?)[ ]*$/m", $mainContent, $m) ? $m[1] : null)
            ->setRate(preg_match("/^[> ]*Average Daily Rate[: ]+(.*\d.*?)[ ]*$/m", $mainContent, $m) ? $m[1] : null);

        $cancellationPolicy = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Cancellation Policy:')]/following-sibling::span[1]");

        if (!empty($cancellationPolicy)) {
            $h->general()->cancellation($cancellationPolicy);
        }

        if (preg_match("/if you do have a change of plans, please be so kind as to let us know more than (?<prior>\d+ hours?) prior to arrival date to avoid a charge/i",
            $mainContent, $m)
            || preg_match("/If you let us know that you need to change or cancel your reservation by (?<time>\d+pm?), 2 days \((?<prior>\d+ hours?)\) prior to arrival, no sweat\./i",
                $mainContent, $m)) {
            if (!empty($m['time'])) {
                $h->booked()->deadlineRelative($m['prior'], $m['time']);
            } else {
                $h->booked()->deadlineRelative($m['prior']);
            }
        }
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
