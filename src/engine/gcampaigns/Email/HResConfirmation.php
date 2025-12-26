<?php

namespace AwardWallet\Engine\gcampaigns\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

// parsers with similar formats: marriott/ReservationConfirmation (object), marriott/It2506177, mirage/It1591085, triprewards/It3520762, woodfield/It2220680, goldpassport/WelcomeTo

class HResConfirmation extends \TAccountChecker
{
    public $mailFiles = "gcampaigns/it-12135301.eml, gcampaigns/it-12191158.eml, gcampaigns/it-12191201.eml, gcampaigns/it-12191267.eml, gcampaigns/it-1645974.eml, gcampaigns/it-1786612.eml, gcampaigns/it-2422896.eml, gcampaigns/it-2976384.eml, gcampaigns/it-2987725.eml, gcampaigns/it-2999847.eml, gcampaigns/it-3.eml, gcampaigns/it-3017395.eml, gcampaigns/it-3017396.eml, gcampaigns/it-3542837.eml, gcampaigns/it-4.eml, gcampaigns/it-46126107.eml, gcampaigns/it-46206959.eml, gcampaigns/it-46316011.eml, gcampaigns/it-46318201.eml, gcampaigns/it-46340289.eml, gcampaigns/it-47165359.eml, gcampaigns/it-47225021.eml, gcampaigns/it-47545407.eml, gcampaigns/it-55549728.eml, gcampaigns/it-644232724.eml, gcampaigns/it-702618968.eml"; // +3 bcdtravel(html)[en]

    public $reBody = [
        'fr' => ['Suivez-nous sur les médias sociaux'],
        'en' => [
            'We are pleased to confirm your reservation',
            'We are pleased to confirm your hotel reservation',
            'Thank you for making your hotel reservation',
            'We look forward to welcoming you',
            'This is an automated acknowledgement from ',
            'Below is a summary of your reservation, and to the left you will find information about the area',
            'We look forward to seeing you',
            'RESERVATION MODIFICATION',
            'RESERVATION CONFIRMATION',
            ' Details of your current reservations are below',
            'Please review the details of your stay',
            'If you would like to upgrade or make changes to your reservation',
            'Reservation Information',
            'This notice is to confirm that we have received your request for a reservation at',
            'You will find details of your cancelled reservation below',
            'has been cancelled. Below you will find details',
            'The following is a reminder of your housing reservation',
            'and can\'t wait to welcome you',
            'The above rates do not reflect all possible fees',
            'we look forward to welcoming you in person',
            'Your room assignment for the',
            'Specific room types are requests only. We do our best to satisfy all guests and their requests',
            'Thank you for selecting Las Vegas',
            'Please note that your reservation has been cancelled',
        ],
    ];
    public $reSubject = [
        '/Reservation Cancellation\s*$/',
        '/Reservation(?:\s+Update)?\s+Confirmation\s*$/',
        '/Hotel\s+Reservation\s+Acknowledgement$/',
        '/Cancellation Confirmation$/',
        '/We look forward to your arrival/',
        '/Hotel Reservation Confirmation/',
        '/Your\s+Hotel\s+Reservation\s+-\s*[A-Z\d]+\s*$/', //Your Hotel Reservation - 32L63DPJ
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
            'Online Confirmation:'       => ['Online Confirmation Number:', 'Online Confirmation:', 'Online Confirmation',
                'Online Acknowledgement Number:', 'Online Acknowledgment Number:', 'AcknowledgementNumber:', 'Acknowledgement Number:',
                'Acknowledgement #:', 'Confirmation Number', 'Housing Acknowledgement Number:', 'Reservation Confirmation Number:',
                'CONFIRMATION #', 'Passkey Acknowledgement Number:', 'Passkey Acknowledgement:', 'Acknowledgement:', 'Acknowledgement number:',
                'Online Acknowledge Number:', 'Confirmation Number', 'Confirmation', 'Passkey Acknowledgement #:', 'Acknowledgment Number:', ],
            'Hotel Confirmation:'        => ['Marriott Confirmation Number:', 'Sheraton Confirmation:', 'Hotel Confirmation:', 'Hotel Confirmation', 'Hotel Confirmation Number:', 'Acknowledgement Number:'],
            'Cancellation Number:'       => ['Cancellation Number:', 'Hotel Cancellation Number'],
            'Your reservation at'        => ['Your reservation at', 'Please note that your reservation'],
            'your reservations at the'   => ['planning your stay at', 'your reservations at the', 'your reservations at', 'Your reservation at the', 'your reservation at', 'successfully booked your visit to', 'Your reservation at',
                'for a reservation at', 'confirm your reservation with the', ],
            'Thank you for choosing the' => ['Thank you for choosing the', 'We look forward to welcoming you to the',
                'We are pleased to confirm your reservation for the', 'We are pleased to confirm your reservations for the', 'Thank you for choosing', 'As our guest at',
            ],
            'here at'                    => ['here at', 'at'],
            'Hotel Name:'                => ['Hotel Name:', 'Hotel:', 'Hotel', 'Your hotel:', 'Your Hotel:'],
            'Arrival Date:'              => ['Arrival Date:', 'Arrival:', 'Arrival date:', 'Arrival Date', 'Check-in:', 'Check-In:', 'Check In', 'ARRIVAL:', 'Check-In Date'],
            'Departure Date:'            => ['Departure Date:', 'Departure:', 'Departure date:', 'Departure Date', 'Check-out:', 'Check-Out:', 'Check Out', 'DEPARTURE:', 'Check-Out Date'],
            'Guest Names:'               => ['Guest Names:', 'All Guest Name(s):', 'Additional guest(s) name(s):', 'Name of Guest', 'Guest Name'],
            'Reservation Name:'          => ['Reservation Name:', 'Guest name:', 'Guest Name:', 'Reservation Name', 'Primary Guest name:', 'Primary guest name:', 'GUEST NAME:', 'Name of the Guest:', 'Recipient Name:'],
            'Number of Guests:'          => ['Number of Guests:', 'Guests per room:', 'Guests:', 'Guests Per Room:', 'Guests per Room:', 'Number of Guests', 'Total Guests in Room:', 'No of Guest(s)', 'Number of Guest(s)', 'Guests per room',
                'Number of guests:', 'Number of Persons:', ],
            'Number of Rooms:'           => ['Number of rooms:', "Number of Rooms:", 'Number of Rooms'],
            'Cancel Policy:'             => ['Canceling your Reservation', 'Cancel Policy:', 'Cancellation Policy:', 'Cancellation Policy', 'Cancel Policy', 'Reservation Cancellation Policy:', 'Booking Fee & Cancellation Policy:', 'Cancellation policy:', 'Cancellation Policy', 'Cancellation and Modification Policy:'],
            'Room Type:'                 => ['Room Type:', 'Room type:', 'Room Type', 'Room', 'GUESTROOM TYPE:', 'Room Type', 'Room type', 'Room type assigned:'],
            'Nightly Rate'               => ['Night by Night Rate', 'Nightly Rate', 'Rate', 'Nightly Rates:', 'Single Occupancy Rate per Room:', 'Daily Room Rate'],
            'Total Charge:'              => ['Total Charge:', 'Total Charges:', 'Estimated Total Charge:', 'Total Room Charge', 'Total Charge', 'Estimated Cost', 'Total Room Charge:', 'Total room charge:', 'Total Room Charges', 'Subtotal:', 'Total Charge including'],
            'Total before tax/fees:'     => ['Total before tax/fees:', 'Room Charge (before taxes/fees)', 'Total Price exclusive of Tax', 'Total for Stay not including applicable taxes/fees:', 'Pre-Tax Total:'],
            'Date Booked:'               => ['Date Booked:', 'Date Booked'],
            'Address:'                   => ['Address', 'Address:', 'Location:', 'Hotel Information'],
            'Reservation Details'        => ['Reservation Details', 'Reservation Confirmation', 'Below is a summary of your booking'],
            'Taxes:'                     => ['Tax', 'Taxes:'],
        ],
        'fr' => [
            'Online Confirmation:' => ['Online reservation:'],
            //            'Hotel Confirmation:' => [''],
            'your reservations at the' => ['Nous sommes ravis de vous confirmer votre réservation au'],
            //            'Thank you for choosing the' => '',
            // 'here at' => '',
            'Arrival Date:'   => ['Arrival date:'],
            'Departure Date:' => ['Departure date:'],
            //            'Guest Names:' => '',
            'Reservation Name:' => ['Reservation name:'],
            'Number of Guests:' => ['Number of guests:'],
            'Cancel Policy:'    => ['Cancel policy:'],
            'Room Type:'        => ['Room type:', 'Room type:', 'Room Type'],
            // 'Nightly Rate'      => '',
            'Total Charge:'     => ['Total charge:', 'Estimated Total Charge:', 'Total Room Charge', 'Total Charge'],
            'Number of Rooms:'  => ['Number of rooms:', "Number of Rooms:"],
            //            'Total before tax/fees:' => [''],
        ],
    ];

    //TODO: need more examples for rewriting detect of provider
    private static $providers = [
        'marriott'          => ['Sheraton New York Times Square', 'Sheraton'], // owned by Marriott since 2016
        'triprewards'       => ['Wyndham'],
        'relais'            => ['The Weekapaug Inn'],
        'goldpassport'      => ['The Staff of Hyatt Regency', 'The Staff of the Park Hyatt', 'www.hyatt.com', '@hyatt.com'],
        'rwvegas'           => ['Las Vegas Hilton at Resorts World', 'Conrad Las Vegas', 'Resorts World Las Vegas', 'www.rwlasvegas.com'],
        'mandarinoriental'  => ['www.mandarinoriental.com', 'Mandarin Oriental'],
        'gcampaigns'        => [''], // all others
    ];

    private $currentSubject = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $htmlBody = $parser->getHTMLBody();
        $this->assignLang($htmlBody);

        $subjectEmail = $parser->getSubject();

        if ($subjectEmail) {
            $this->currentSubject = $subjectEmail;
        }

        if (
            null !== ($prov = $this->getProvider())
            && $prov !== 'gcampaigns'
            && stripos($parser->getCleanFrom(), 'groupcampaigns@pkghlrss.com') === false
        ) {
            $email->setProviderCode($prov);
        }

        $this->parseEmail($email);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $htmlBody = $this->http->Response['body'];

        if (stripos($htmlBody, '.passkey.com') === false && stripos($htmlBody, '.effreg.com') === false
                && stripos($htmlBody, 'groupcampaigns@pkghlrss.com') === false
                && stripos($htmlBody, 'wyndham.com') === false
                && stripos($htmlBody, 'weekapauginn.com') === false
                && stripos($htmlBody, 'saint-antoine.com') === false
                && stripos($htmlBody, 'rwlasvegas.com') === false
                && stripos($htmlBody, 'www.mandarinoriental.com') === false
                ) {
            return false;
        }

        return $this->assignLang($htmlBody);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && stripos($headers['from'], '@cvent.com') === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (preg_match($reSubject, $headers['subject'])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'groupcampaigns@pkghlrss.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function IsEmailAggregator()
    {
        return true;
    }

    private function parseEmail(Email $email): void
    {
        $patterns = [
            'PNR'        => '[A-Z\d\-]{5,}', // 32KX4J7L
            'tripNumber' => '\d{5,}', // 72582192
            'phone'      => '[+(\d][-. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
        ];

        $h = $email->add()->hotel();

        $xpathNoEmpty = 'string-length(normalize-space())>1';
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';
        $xpathFragment1 = $this->starts($this->t('Online Confirmation:'));
        $xpathFragment2 = $this->contains($this->t('Hotel Confirmation:'));
        $xpathFragment3 = "//td[not(.//tr) and $xpathFragment1 and $xpathFragment2]/following-sibling::td[{$xpathNoEmpty}][1]/descendant::text()[{$xpathNoEmpty}]";

        $onlineConfirmationNumberTitle = $this->http->FindSingleNode("//td[not(.//td) and $xpathFragment1 and not($xpathFragment2)][following-sibling::td[normalize-space()]]", null, true, '/^(.+?)[\s:：]*$/u');
        $onlineConfirmationNumber = $this->http->FindSingleNode("//td[not(.//td) and $xpathFragment1 and not($xpathFragment2)]/following-sibling::td[normalize-space(.)!=''][1]/descendant::text()[normalize-space(.)!='']", null, true, '/^(' . $patterns['PNR'] . ')$/');

        if (empty($onlineConfirmationNumber)) {
            $onlineConfirmationNumberTitle = $this->http->FindSingleNode("//td[not(.//tr) and $xpathFragment1 and $xpathFragment2][ following-sibling::td[{$xpathNoEmpty}] ]/descendant::text()[{$xpathNoEmpty}][1]", null, true, '/^(.+?)[\s:：]*$/u');
            $onlineConfirmationNumber = $this->http->FindSingleNode($xpathFragment3 . '[1]', null, true, "/^{$patterns['PNR']}$/");
        }

        if (empty($onlineConfirmationNumber)
            && preg_match("/^({$this->opt($this->t('Online Confirmation:'))})[:\s]*({$patterns['PNR']})$/", $this->http->FindSingleNode("//td[not(.//td) and $xpathFragment1]/descendant::text()[normalize-space()][1]"), $m)
        ) {
            $onlineConfirmationNumberTitle = rtrim($m[1], ': ');
            $onlineConfirmationNumber = $m[2];
        }

        if (empty($onlineConfirmationNumber)) {
            foreach ((array) $this->t('Online Confirmation:') as $phrase) {
                $onlineConfirmation = $this->http->FindSingleNode("//text()[{$this->starts($phrase)}]");

                if ($onlineConfirmation && preg_match("/^({$this->opt($phrase)})[:\s]*({$patterns['PNR']})$/", $onlineConfirmation, $m)) {
                    $onlineConfirmationNumberTitle = rtrim($m[1], ': ');
                    $onlineConfirmationNumber = $m[2];
                }

                if (empty($onlineConfirmationNumber)) {
                    $onlineConfirmation = $this->http->FindSingleNode("//text()[{$this->eq($phrase)}]/following::text()[normalize-space()][1]");

                    if ($onlineConfirmation && preg_match("/^({$patterns['PNR']})$/",
                            $onlineConfirmation, $m)) {
                        $onlineConfirmationNumberTitle = rtrim($phrase, ': ');
                        $onlineConfirmationNumber = $m[1];
                    }
                }
            }
        }

        if (empty($onlineConfirmationNumber)) {
            $onlineConfirmationNumber = $this->http->FindSingleNode("//text()[{$this->starts('Your acknowledgement Number is')}]",
                null, true, "/^\s*Your acknowledgement Number is\s+({$patterns['PNR']})\s*\.\s*$/");
            $onlineConfirmationNumberTitle = 'Acknowledgement Number';
        }

        if (empty($onlineConfirmationNumber)) {
            $onlineConfirmationNumber = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Reservation') and contains(normalize-space(), 'Number:')]/ancestor::tr[1]", null, true, "/\:\s*([A-Z\d]{6})/");
            $onlineConfirmationNumberTitle = 'Reservation  Number';
        }

        if (!empty($onlineConfirmationNumber)) {
            $h->general()->confirmation($onlineConfirmationNumber, $onlineConfirmationNumberTitle);
        }

        $hotelConfirmationNumberTitle = $this->http->FindSingleNode("//td[not(.//td) and $xpathFragment2 and not($xpathFragment1)][following-sibling::td[normalize-space()]]", null, true, '/^(.+?)[\s:：]*$/u');
        $hotelConfirmationNumber = $this->http->FindSingleNode("//td[not(.//td) and $xpathFragment2 and not($xpathFragment1)]/following-sibling::td[normalize-space(.)!=''][1]/descendant::text()[normalize-space(.)!='']",
            null, true, '/^(' . $patterns['tripNumber'] . ')$/');

        if (empty($hotelConfirmationNumber)) {
            $hotelConfirmationNumberTitle = $this->http->FindSingleNode("//td[not(.//tr) and $xpathFragment1 and $xpathFragment2][ following-sibling::td[normalize-space()]/descendant::text()[{$xpathNoEmpty}][2] ]/descendant::text()[{$xpathNoEmpty}][2]", null, true, '/^(.+?)[\s:：]*$/u');
            $hotelConfirmationNumber = $this->http->FindSingleNode($xpathFragment3 . '[2]', null, true,
                '/^(' . $patterns['tripNumber'] . ')$/');
        }

        if (empty($hotelConfirmationNumber)
            && preg_match("/^({$this->opt($this->t('Hotel Confirmation:'))})[:\s]*({$patterns['tripNumber']})$/", $this->http->FindSingleNode("//text()[$xpathFragment2]"), $m)
        ) {
            $hotelConfirmationNumberTitle = rtrim($m[1], ': ');
            $hotelConfirmationNumber = $m[2];
        }

        if (!empty($hotelConfirmationNumber)) {
            if (empty($onlineConfirmationNumber)) {
                $h->general()->confirmation($hotelConfirmationNumber, $hotelConfirmationNumberTitle);
            } else {
                $email->ota()->confirmation($hotelConfirmationNumber, $hotelConfirmationNumberTitle);
            }
        }

        if (empty($hotelConfirmationNumber)
            && empty($onlineConfirmationNumber)
            && $this->http->XPath->query("//text()[$xpathFragment1] | //text()[$xpathFragment2]")->length > 0
        ) {
            $h->general()->noConfirmation();
        }

        if ($this->http->XPath->query("//text()[{$this->starts($this->t('Your reservation at'))}][{$this->contains($this->t('has been cancelled'))}]")->length > 0) {
            $h->general()
                ->status('cancelled')
                ->cancelled();

            $cancellationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Number:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");

            if (!empty($cancellationNumber)) {
                $h->general()
                    ->cancellationNumber($cancellationNumber);
            }
        }

        // hotelName
        $hotelName = $this->http->FindNodes("(.//text()[{$this->eq($this->t('Hotel Name:'))}])[1]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]/descendant::text()[normalize-space()!='']");

        if (count($hotelName) == 1) {
            $hotelName = array_shift($hotelName);
        } elseif (count($hotelName) == 2) {
            $address = $hotelName[1];
            $hotelName = $hotelName[0];
        } else {
            $hotelName = null;
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->contains($this->t('your reservations at the'))}]", null, true,
                "/{$this->opt($this->t('your reservations at the'))}\s+([^.]+?)\s*(?:{$this->opt($this->t('and are looking'))}|has been |\.| for )/");
        }

        if (empty($hotelName) && preg_match('/The\s+(.+?)\s+[Rr]eservation(?:\s+Update)?\s+[Cc]onfirmation/', $this->currentSubject, $matches)) {
            $hotelName = $matches[1];
        }

        if (empty($hotelName) && preg_match('/^(.+)Reservation Confirmation$/', $this->currentSubject, $matches)) {
            $hotelName = $matches[1];
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->contains($this->t('RESERVATION MODIFICATION'))}]/ancestor::tr[1]/preceding-sibling::tr[2]/td/text()[1]");
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->contains($this->t('is looking forward to your'))}]", null, true,
                "/(?:{$this->opt($this->t('The staff of'))}\s+|^)([^.]+?)\s*{$this->opt($this->t('is looking forward to your'))}/");
        }

        if (empty($hotelName)) {
            /*
                We look forward to welcoming you to the CII Annual Conference 2019 here at Manchester Grand Hyatt San Diego.
                    or
                We are pleased to confirm your reservation for the FLASCO Spring Meeting 2021 at Gaylord Palms Resort & Convention Center.
            */
            $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank you for choosing the'))}]/ancestor::*[self::p or self::div or self::tr][1]", null, true, "/{$this->opt($this->t('Thank you for choosing the'))}.*?\s+{$this->opt($this->t('here at'))}\s+([^,.!]*?[[:upper:]][^,.!]*?)\s*(?:[,.!]|$)/m");
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank you for choosing the'))}]", null, true,
                "/{$this->opt($this->t('Thank you for choosing the'))}\s+([^\.!]+?)\s*(?:\.|!|and we look forward|with the)/");
        }

        if (empty($hotelName) && $this->http->XPath->query("//text()[contains(.,'renhotels.com')]/ancestor::a")->length > 0) {
            $hotelName = $this->http->FindSingleNode("//text()[contains(.,'Sincerely,')]/following::text()[normalize-space()!=''][1]",
                null, true, "/^.*\bResort\b.*/");
        }

        if (empty($hotelName)) {
            $hotelinfo = implode("\n", $this->http->FindNodes("//tr[not(.//tr)][td[1][normalize-space()='HOTEL'][.//strong[normalize-space()='HOTEL']]]/td[2]/node()[normalize-space()]"));

            if (preg_match("/^\s*(Harrah's.+)\n([\s\S]+?)\n([\d \-\+\(\)]{6,})(\n|$)/", $hotelinfo, $m)) {
                $hotelName = $m[1];
                $address = $m[2];
                $phone = $m[3];
            }
        }

        if (empty($hotelName) && preg_match('/Welcome to (.+): Your Confirmation$/', $this->currentSubject, $matches)) {
            $hotelName = $matches[1];
        }

        if (!empty($hotelName)) {
            $h->hotel()->name($hotelName);
        }

        // address
        // phone
        $phone = $phone ?? null;
        $fax = $fax ?? null;

        if (!isset($address)) {
            $address = $this->nextTd($this->t('Address:'));
        }

        if (!isset($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Address:'))}][following::text()[{$this->eq($this->t('GUEST INFORMATION'))}]]/ancestor::*[self::td or self::th][1][{$this->eq($this->t('Address:'))}]/following-sibling::td[normalize-space()][1]");

            if (!empty($address)) {
                $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Telephone:'))}][following::text()[{$this->eq($this->t('GUEST INFORMATION'))}]]/ancestor::*[self::td or self::th][1][{$this->eq($this->t('Telephone:'))}]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][1]");
                $fax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Fax:'))}][following::text()[{$this->eq($this->t('GUEST INFORMATION'))}]]/ancestor::*[self::td or self::th][1][{$this->eq($this->t('Fax:'))}]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][1]");
            }
        }

        if (empty($address)) {
            $addressText = implode("\n",
                $this->http->FindNodes("//text()[{$this->starts($this->t('Address and Phone:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]//text()"));

            if (preg_match("/([\s\S]+?)\n+({$patterns['phone']})\s*$/", $addressText, $m)) {
                $address = str_replace("\n", ', ', trim($m[1]));
                $phone = $m[2];
            }
        }

        if (empty($address) && !empty($hotelName)) {
            $addressText = implode("\n", $this->http->FindNodes("(//text()[{$this->eq($hotelName)}])[position()>1]/ancestor::*[1][not({$this->eq($hotelName)}) and ({$this->starts($hotelName)})]/descendant::text()[normalize-space()!=''][position()>1]"));

            if (empty($addressText)) {
                $addressText = $this->http->FindSingleNode("//text()[{$this->eq($hotelName)}]/ancestor::td[1]/descendant::text()[starts-with(normalize-space(), 'Phone:')]/ancestor-or-self::td[normalize-space()][1]");
            }

            if (empty($addressText) || mb_strlen($addressText) < 10) {
                $addressText = join("\n", $this->http->FindNodes("//text()[{$this->eq($hotelName)}]/ancestor::span[1]/following-sibling::span"));
            }

            if (empty($addressText)) {
                $addressText = join("\n", $this->http->FindNodes("//text()[{$this->eq('The ' . $hotelName . ' Team')}]/ancestor::tr[1]/following::tr[contains(normalize-space(), ',')][1]/descendant::text()[normalize-space()]"));
            }

            if (preg_match("/([\s\S]+?)\n+({$patterns['phone']})\s*$/", $addressText, $m)) {
                $address = str_replace("\n", ', ', trim($m[1]));
                $phone = $m[2];
            } elseif (preg_match("#([\s\S]+?\d{4,6})$#", $addressText, $m)) {
                $address = str_replace("\n", ', ', trim($m[1]));
            }

            if (preg_match("/^(?<hotelName>{$this->opt($hotelName)})\s+(?<address>.+?)\n*Phone:\s*(?<phone>{$patterns['phone']})\s*Fax:\s*(?<fax>{$patterns['phone']})$/", $addressText, $m)) {
                $address = $m['address'];
                $phone = $m['phone'];
            }

            if (preg_match("/^(?<address>.+)\n+(?<phone>[\d\-]+)\s*ou/u", $addressText, $m)) {
                $address = $m['address'];
                $phone = $m['phone'];
            }
        }

        if (!empty($address) && preg_match("/^\w+\s*\d+\,\s*\d{4}/", $address)) {
            $address = '';
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//p/img[contains(@src, 'http://groupmax.passkey.com/images')]/ancestor::tr[1]/following-sibling::tr[1]/td/text()[1]");

            if (!empty($address)) {
                $phone = $this->http->FindSingleNode("//p/img[contains(@src, 'http://groupmax.passkey.com/images')]/ancestor::tr[1]/following-sibling::tr[1]/td/text()[2]", null, false, "/t\s*:\s*({$patterns['phone']})/");
                $fax = $this->http->FindSingleNode("//p/img[contains(@src, 'http://groupmax.passkey.com/images')]/ancestor::tr[1]/following-sibling::tr[1]/td/text()[2]", null, false, "/f\s*:\s*({$patterns['phone']})/");
            } else {
                $address = $this->http->FindSingleNode('//img[@alt="Hilton HHonors"]/ancestor::td[1]/following-sibling::td[1]/descendant::span[1]/text()[2]', null, false, '/(.*?) T \d/is');
                $phone = $this->http->FindSingleNode('//img[@alt="Hilton HHonors"]/ancestor::td[1]/following-sibling::td[1]/descendant::span[1]/text()[2]', null, false, "/ T ({$patterns['phone']})/i");
            }
        }

        if (empty($phone)) {
            $phone = $this->nextTd($this->t('Tel'));
        }

        if (empty($address) && empty($hotelName)) {
            $nodes = $this->http->FindNodes("//text()[normalize-space()='HOTEL']/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space()!='']");

            if (count($nodes) === 3) {
                [$hotelName, $address, $phone] = $nodes;
                $h->hotel()->name($hotelName);
            }
        }

        if (empty($address) && !empty($hotelName)) {
            /*
                The Whitley, A Luxury Collection Hotel, Atlanta Buckhead

                3434 Peachtree Road NE
                Atlanta GA 30326

                View Weather Forecast
            */
            $yourHotelHtml = $this->http->FindHTMLByXpath("//tr[{$this->eq($this->t('Your Hotel'))}]/following-sibling::tr[normalize-space()]");

            if (preg_match("/{$this->opt($hotelName)}\n(?<address>(?:\n.{3,}){1,3}?)\n\n/", $this->htmlToText($yourHotelHtml), $m)) {
                $address = preg_replace('/\s+/', ' ', $m['address']);
            }
        }

        if (empty($address) && !empty($hotelName)) {
            /*
                Embassy Suites by Hilton Alexandria Old Town | 1900 Diagonal Road | Alexandria, VA 22314
                703-684-5900 | Hotel Website
            */
            $addressText = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Cancel Policy']/following::text()[contains(normalize-space(), 'Embassy Suites by Hilton Alexandria Old Town')]/ancestor::*[1]/descendant::text()[normalize-space()]"));

            if (preg_match("/{$this->opt($hotelName)}[\s\|]+(?<address>.+)\n(?<phone>[\d\-]+)[\s\|]+Hotel Website/", $addressText, $m)) {
                $address = preg_replace('/\s+/', ' ', str_replace('|', '', $m['address']));
                $phone = $m['phone'];
            }
        }

        if (empty($address)) {
            $text = implode("\n",
                $this->http->FindNodes("//text()[normalize-space()='About Your Hotel']/following::text()[normalize-space()][1][normalize-space()='Hotel:']/following::div[normalize-space()!=''][1]/descendant::text()[normalize-space()!='']"));

            if (preg_match("/{$this->opt($hotelName)}\n+(?<address>.+?)(?:\n+(?<phone>{$patterns['phone']}))?$/s", $text, $m)) {
                $address = preg_replace('/\s+/', ' ', $m['address']);

                if (!empty($m['phone'])) {
                    $phone = $m['phone'];
                }
            }
        }

        if (empty($address) && !empty($hotelName)) {
            /*
                The Breakers
                One South County Road, Palm Beach, FL 33480
                (844) 640-1227
            */
            $addressText = implode("\n", $this->http->FindNodes("(//text()[normalize-space()='" . preg_quote($hotelName) . "'])[last()]/ancestor::td[1]/descendant::text()[normalize-space()]"));

            if (preg_match("/{$this->opt($hotelName)}\s*\n\s*(?<address>.+)\n(?<phone>[\d\- \(\)\+]{5,})(?:\n|$)/", $addressText, $m)
                && strlen(preg_replace("/\D/", '', $m['phone'])) > 5) {
                $address = preg_replace('/\s+/', ' ', str_replace('|', '', $m['address']));
                $phone = $m['phone'];
            }
        }

        if (/*empty($hotelName) && */ empty($address) && empty($phone)) {
            $addressText = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Phone')]/ancestor::tr[1]/descendant::text()[string-length()>5]"));

            if (preg_match("/^(?<hotelName>\D+)\n(?<address>.+)\nPhone:\s*(?<phone>[+\d\-\s]+)\s*Fax:\s*(?<fax>[+\d\-\s]+)$/msu", $addressText, $m)) {
                $address = str_replace("\n", " ", $m['address']);
                $phone = $m['phone'];
                $fax = $m['fax'];

                $h->hotel()
                    ->name($m['hotelName']);
            }
        }

        if (empty($address) && empty($phone) && !empty($hotelName)) {
            $addressText = implode("\n", $this->http->FindNodes("//text()[contains(normalize-space(), 'Telephone:')]/ancestor::table[1]/descendant::text()[normalize-space()]"));

            if (preg_match("/Reservation Confirmation\n*(?<hotelName>.+)\nKM\s*[\d\.\,]+.*#\s*\d+\n*(?<address>(?:.+\n){1,5})\n*Telephone\:\s+(?<phone>[+][\d\(\)\s]+)Fax Number\:\s+(?<fax>[+][\d\(\)\s]+)/", $addressText, $m)) {
                $address = str_replace("\n", " ", $m['address']);
                $phone = $m['phone'];
                $fax = $m['fax'];
            }
        }

        if (!empty($address)) {
            $h->hotel()->address($address);
        } elseif (!empty($hotelName)) {
            $h->hotel()->noAddress();
        }

        $h->hotel()
            ->phone($phone, false, true)
            ->fax($fax, false, true)
        ;

        // checkInDate
        // checkOutDate
        // guestCount
        // roomsCount
        $checkin = $this->nextTd($this->t('Arrival Date:'));

        if (preg_match("/(.+?)\s*\(Check-In Time:\s*(.+?)\)/i", $checkin, $m)) {
            $checkin = strtotime($m[1] . ', ' . $m[2]);
        } else {
            $checkin = strtotime($checkin);
        }

        if (empty($checkin)) {
            $checkin = $this->normalizeDate($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Reservation Details'))}]/following::text()[{$this->starts($this->t('Arrival Date:'))}])[1]/following::text()[normalize-space()][1]"));
        }

        $time = null;

        $time = $this->normalizeTime($this->nextTd($this->t('Check-in Begins:')));

        if (empty($time)) {
            $time = $this->normalizeTime($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Reservation Details'))}]/following::text()[{$this->eq($this->t('Check-In:'))}])[last()]/following::text()[normalize-space()][1]"));
        }

        if ($time) {
            $checkin = strtotime($time, $checkin);
        }

        $checkout = $this->nextTd($this->t('Departure Date:'));

        if (preg_match("/(.+?)\s*\(Check-Out Time:?\s*(.+?)\)/i", $checkout, $m)) {
            $checkout = strtotime($m[1] . ', ' . $m[2]);
        } else {
            $checkout = strtotime($checkout);
        }

        if (empty($checkout)) {
            $checkout = $this->normalizeDate($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Reservation Details'))}]/following::text()[{$this->starts($this->t('Departure Date:'))}])[1]/following::text()[normalize-space()][1]"));
        }

        $time = null;

        $time = $this->normalizeTime($this->nextTd($this->t('Check-out By:')));

        if (empty($time)) {
            $time = $this->normalizeTime($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Reservation Details'))}]/following::text()[{$this->eq($this->t('Check-Out:'))}])[last()]/following::text()[normalize-space()][1]"));
        }

        if ($time) {
            $checkout = strtotime($time, $checkout);
        }

        $guests = $this->re("/^(\d{1,3})\s*(?:\D|$)/", $this->nextTd($this->t('Number of Guests:')));

        if (empty($guests)) {
            $guests = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Number of Guests:'))}]/following::text()[normalize-space()][1]");
        }
        $kids = $this->re("/^\d{1,3}\s*\D+\b(\d+)/", $this->nextTd($this->t('Number of Guests:')));

        if ($kids === null) {
            $kids = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Number of Persons:')]/following::text()[normalize-space()][1]/ancestor::tr[1]", null, true, "/\d{1,3}\s*\D+\b(\d+)/");
        }

        $rooms = $this->re("/^(\d+)/", $this->nextTd($this->t('Number of Rooms:')));

        if (empty($rooms)) {
            $rooms = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Number of Rooms:'))}]/following::text()[normalize-space()][1]");
        }

        $h->booked()
            ->checkIn($checkin)
            ->checkOut($checkout)
            ->guests($guests, false, true)
            ->kids($kids, false, true)
            ->rooms($rooms, false, true);

        if (!empty($h->getCheckInDate())) {
            if ($time = $this->http->FindSingleNode('//text()[normalize-space() = "Check-In Time"]/following::text()[normalize-space()][1]',
                null, false, '/^\s*(\d{1,2}:\d{2}[ ]?[ap]m)\b/i')
            ) {
                $h->booked()
                    ->checkIn(strtotime($time, $h->getCheckInDate()));
            } elseif ($time = $this->http->FindSingleNode('//text()[normalize-space() = "Check-In Time"]/following::text()[normalize-space()][1]',
                null, false, '/^Our check-in time is\s*(\d{1,2}(?::\d{2})?[ ]?[ap]m)\b/i')
            ) {
                $h->booked()
                    ->checkIn(strtotime($time, $h->getCheckInDate()));
            } elseif ($time = $this->http->FindSingleNode('//text()[contains(normalize-space(), "Check-In time is")]',
                null, false, '/Check-In time is (\d{1,2}:\d{2}[ ]?[ap]\.?m\.?)\b/i')
            ) {
                $h->booked()
                    ->checkIn(strtotime(preg_replace("/[\.\s]+/", '', $time), $h->getCheckInDate()));
            } elseif ($time = $this->http->FindSingleNode('//text()[contains(normalize-space(),"Check-In")]/ancestor::td[1]/following-sibling::td[normalize-space()!=""][1]',
                null, false, '/^(\d{1,2}:\d{2}[ ]?[ap]\.?m\.?)$/i')
            ) {
                $h->booked()
                    ->checkIn(strtotime(preg_replace("/[\.\s]+/", '', $time), $h->getCheckInDate()));
            }
        }

        if (!empty($h->getCheckOutDate())) {
            if ($time = $this->http->FindSingleNode('//text()[normalize-space() = "Check-Out Time"]/following::text()[normalize-space()][1]', null, false, '/^\s*(\d{1,2}:\d{2}[ ]?(?:[ap]m|noon))\b/i')) {
                $h->booked()
                    ->checkOut(strtotime(str_replace('noon', 'pm', $time), $h->getCheckOutDate()));
            } elseif ($time = $this->http->FindSingleNode('//text()[normalize-space() = "Check-Out Time"]/following::text()[normalize-space()][1]', null, false, '/^Our check-out time is\s*(\d{1,2}(?::\d{2})?[ ]?(?:[ap]m|noon))\b/i')) {
                $h->booked()
                    ->checkOut(strtotime(str_replace('noon', 'pm', $time), $h->getCheckOutDate()));
            } elseif ($time = $this->http->FindSingleNode('//text()[contains(normalize-space(), "Check-Out time is")]', null, false, '/Check-Out time is (\d{1,2}:\d{2}[ ]?[ap]\.?m\.?)\b/i')) {
                $h->booked()
                    ->checkOut(strtotime(preg_replace("/[\.\s]+/", '', $time), $h->getCheckOutDate()));
            } elseif ($time = $this->http->FindSingleNode('//text()[contains(normalize-space(), "Check-Out")]/ancestor::td[1]/following-sibling::td[normalize-space()!=""][1]', null, false, '/^(\d{1,2}:\d{2}[ ]?[ap]\.?m\.?)$/i')) {
                $h->booked()
                    ->checkOut(strtotime(preg_replace("/[\.\s]+/", '', $time), $h->getCheckOutDate()));
            }
        }

        // travellers
        $travellers = $this->http->FindNodes("(.//text()[{$this->eq($this->t('Guest Names:'))}])[1]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]//text()[normalize-space()!='']");

        if (count($travellers) > 0) {
            $h->general()
                ->travellers($travellers);
        } else {
            $traveller = $this->nextTd($this->t('Reservation Name:'));

            if (stripos($traveller, 'Guest Rate') !== false) {
                $traveller = null;
            }

            if (empty($traveller)) {
                $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*(?:,|$)/u");
            }

            if (empty($traveller)) {
                $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('GUEST INFORMATION'))}]//following::text()[normalize-space()][position()<6][{$this->eq($this->t('Name:'))}]/ancestor::*[self::td or self::th][1][{$this->eq($this->t('Name:'))}]/following-sibling::td[normalize-space()][1]");
            }

            if (empty($traveller)) {
                $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Additional Guests:'))}]//following::text()[normalize-space()][1]");
            }

            $h->general()
                ->traveller($traveller);
        }
        // cancellation

        $cancellation = $this->nextTd($this->t('Cancel Policy:'), null, false);

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Canceling your Reservation')]/following::text()[normalize-space()][1]/ancestor::*[1]");
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[normalize-space()='HOTEL CANCEL POLICY:']/ancestor::p[1]", null, true, "/{$this->opt($this->t('HOTEL CANCEL POLICY:'))}\s*(.+)/");
        }

        $h->general()
            ->cancellation($cancellation, false, true);

        $roomType = $this->nextTd($this->t('Room Type:'));

        if (empty($roomType)) {
            $roomType = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Type:'))}]/following::text()[normalize-space()][1]");
        }

        if (empty($roomType)) {
            $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type:'))}]/following::text()[normalize-space()][1]");
        }

        if (empty($roomType)) {
            $roomType = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Room Type:'))}]/ancestor::*[{$this->eq($this->t('Room Type:'))}]/following-sibling::*[normalize-space()][1])[1]");
        }

        if (!empty($roomType)) {
            $r = $h->addRoom();
            $r->setType($roomType);
            $roomRate = $this->http->FindSingleNode("//tr[ *[normalize-space()][1][{$this->starts($this->t('Nightly Rate'))}] ]/*[normalize-space()][2]/descendant::span[descendant::text()[normalize-space()][1][ancestor::*[{$xpathBold}]]][1]");

            if (empty($roomRate)) {
                // it-55549728.eml
                $roomRate = $this->http->FindSingleNode("//tr[ *[normalize-space()][2][starts-with(normalize-space(),'Cost per night per room')] ]/following-sibling::tr[normalize-space()][1]/*[normalize-space()][2]", null, true, '/^\d+[.]\d+$/');

                if (!empty($roomRate)) {
                    $roomRate = $roomRate . ' per night';
                }
            }

            $roomRates = $this->http->FindNodes("//text()[{$this->contains($this->t('Nightly Rate'))}]/ancestor::td[1]/following-sibling::td[1]/descendant::span[descendant::text()[normalize-space()][1][ancestor::*[{$xpathBold}]]][1]/descendant::text()[normalize-space()][not(contains(.,'Date') or contains(normalize-space(),'Please note'))]", null, '/[ ]+\d{1,3}[ ]+[[:alpha:]]+[ ]+\b(\d[,.\'\d]*)\b/u');

            if (count($roomRates) === 0) {
                $roomRates = $this->http->FindNodes("//text()[contains(normalize-space(), 'Nightly Room Rate:')]/ancestor::td[1]/following::td[1]/descendant::p[contains(normalize-space(), 'th ')][not(contains(normalize-space(), 'Daily'))]");
            }

            $roomRates = array_filter($roomRates);

            if (count($roomRates) && strlen($roomRate) > 40) {
                $min = min($roomRates);
                $max = max($roomRates);

                if ($min == $max) {
                    $roomRate = $min . ' per night';
                } else {
                    $roomRate = $min . '-' . $max . ' per night';
                }
            }

            if (!empty($roomRate) && preg_match("/.*\d.*/", $roomRate)) {
                $r->setRate($roomRate);
            } elseif (count($roomRates) > 0) {
                $r->setRates($roomRates);
            }
        }

        $totTexts = $this->http->FindNodes('//td[' . $this->eq($this->t('Total Charge:')) . ' and not(.//td)]/following-sibling::td[normalize-space(.)][1]/descendant::text()[normalize-space(.) and not(contains(.,"%")) and not(contains(.,"Please be advised that"))]');
        $totText = implode("\n", $totTexts);

        if (empty($totTexts)) {
            $totText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Charge including')]/ancestor::tr[normalize-space()][1]/descendant::td[normalize-space()][2]");
        }
        $tot = $this->getTotalCurrency($totText);

        if (!empty($tot['Total']) && preg_match("/^[\d\.\,]+$/", $tot['Total'])) {
            $h->price()->total($tot['Total']);

            if (!empty($tot['Currency'])) {
                $h->price()->currency($tot['Currency']);
            }
        }

        $cost = $this->getTotalCurrency($this->nextTd($this->t('Total before tax/fees:')));

        if (empty($cost['Total'])) {
            $cost = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total for stay')]/following::text()[normalize-space()][1]", null, true, '/([\d\,\.]+)/'));
        }

        if (empty($cost['Total'])) {
            $cost = $this->getTotalCurrency($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Total before tax/fees:')) . "]/following::text()[normalize-space()][1]", null, true, '/.*\d.*/'));
        }

        if (!empty($cost['Total'])) {
            $h->price()->cost($cost['Total']);
        }

        $tax = $this->getTotalCurrency($this->nextTd($this->t('Taxes:')));

        if (!empty($tax['Total']) && preg_match("/^[\d\.\,]+$/", $tax['Total'])) {
            $h->price()
                ->tax($tax['Total']);
        }

        // Currency
        if (empty($cost['Currency']) && !empty($cost['Total'])) {
            $currency = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Cost per night per room (')]", null, true, '/\(\s*([A-Z]{3})\s*\)/');

            if (!empty($currency)) {
                $h->price()->currency($currency);
            }
        }

        if (!empty($cost['Currency'])) {
            $h->price()->currency($cost['Currency']);
        }

        if ($this->http->XPath->query("//text()[{$this->starts($this->t('We are pleased to confirm your reservation'))}]")->length > 0) {
            $h->general()->status('confirmed');
        }

        $dateBooked = $this->nextTd($this->t('Date Booked:'));

        if (!empty($dateBooked)) {
            $h->general()->date(strtotime(str_replace(['}', '{'], '', $dateBooked)));
        }

        if (!empty($node = $h->getCancellation())) {
            $this->detectDeadLine($h, $node);
        }
    }

    private function nextTd($field, $root = null, $sibling = true)
    {
        if ($sibling) {
            return $this->http->FindSingleNode(".//text()[{$this->eq($field)}]/ancestor::*[self::td or self::th][1][{$this->starts($field)}]/following-sibling::td[normalize-space(.)][1]",
                $root);
        } else {
            return $this->http->FindSingleNode(".//text()[{$this->eq($field)}]/ancestor::*[self::td or self::th][1][{$this->starts($field)}]/following::td[normalize-space(.)][1]",
                $root);
        }
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(Hotel $h, string $cancellationText): void
    {
        if (
            preg_match("/Reservations (?i)must be cancell?ed (?<prior1>\d{1,3}) (?<prior2>days?) prior to arrival to avoid a cancell?ation penalty of one night room and tax/", $cancellationText, $m)
            || preg_match("/, please cancell? (?<prior1>\d{1,3}) (?<prior2>hours?) prior to your arrival date or your first night's room and tax will be retained\./i", $cancellationText, $m)
            || preg_match("/Should (?i)you need you cancell? your reservation, notice must be given to the Hotel at least (?<prior1>\d{1,3}) (?<prior2>days?) in advance of your actual arrival date and a valid cancellation number issued\./", $cancellationText, $m)
            || preg_match("/^Room reservations must be cancelled three [(](?<prior1>\d{1,3})[)] (?<prior2>days?) prior to arrival/i", $cancellationText, $m)
            || preg_match('/Please (?i)cancell? (?<prior1>\d{1,3}) (?<prior2>days?) prior to arrival for a full refund /', $cancellationText, $m)
            || preg_match('/Standard (?i)cancell?ation policy is (?<prior1>\d{1,3}) (?<prior2>hours?) before arrival\./', $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior1'] . ' ' . $m['prior2'], '00:00');
        } elseif (
            preg_match("/Cancellations (?i)made within \d+ hours?\s*\/\s*(?<prior>\d{1,3}\s*days?) prior to\s*(?<hour>\d{1,2}(?::\d{1,2})?(?:\s*[AP]M)?)\s*[A-Z]{2,3}\s*will forfeit one night's room and tax/", $cancellationText, $m)
            || preg_match("/Cancell? (?i)by\s*(?<hour>\d{1,2}(?::\d{1,2})?(?:\s*[AP]M)?)\s*[A-Z]{2,3}\s*(?<prior>\d{1,3}\s*Hours?) Prior to Arrival/", $cancellationText, $m)
            || preg_match("/Cancell?ations (?i)are required by (?<hour>\d{1,2}(?::\d{1,2})?(?:\s*[AP]M)?) (?<prior>\d{1,3}\s*hours?) prior to your arrival\./", $cancellationText, $m)
        ) {
            $this->parseDeadlineRelative($h, $m['prior'], $m['hour']);
        } elseif (
            preg_match("#Cancellations made after\s*(?<hour>\d{1,2}(?::\d{1,2})?(?:\s*[AP]M)?)\s*[A-Z]{2,3}\s*day of arrival will forfeit one night\'s room and tax#i", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative('1 day', $m['hour']);
        } elseif (
            preg_match("#Please cancel (?<prior>\d+ hours) prior to arrival for a full refund of 1st night room and tax#i", $cancellationText, $m)
            || preg_match("#Cancellations made within (?<prior>\d+ hours) prior to arrival will forfeit one night\'s room and tax#i", $cancellationText, $m)
            || preg_match("/Reservations cancelled within (?<prior>\d+ hours) prior to arrival will be charged one night's room and tax/i", $cancellationText, $m)
            || preg_match("/Cancell?ations (?i)must be received \d{1,3}-days? \((?<prior>\d{1,3} hours?)\) prior to confirmed arrival date to avoid penalty/", $cancellationText, $m)
            || preg_match("#Cancellations made within (?<prior>\d+ hours) of arrival will forfeit one night\'s room and tax#i", $cancellationText, $m)
            || preg_match("/Reservations must be cancelled at least (?<prior>\d+ hours?) prior to arrival(?: in order)? to avoid a one night's room and tax/i", $cancellationText, $m)
            || preg_match("/Please cancel (?<prior>\d+ hours) prior to arrival for a full refund\./i", $cancellationText, $m)
            || preg_match("/Cancellations made within (?<prior>\d+ hours) prior to scheduled arrival will incur a fee equal to/i", $cancellationText, $m)
            || preg_match("/ If you should need to cancel from the meeting this year, you must do so through cvent and passkey not later than (?<prior>\d+ hours?) prior to the start of the event so that your credit card will not be charged\./i", $cancellationText, $m)
            || preg_match('/To avoid a cancellation fee of one night room plus tax, reservations must be canceled (?<prior>\d{1,2} hours) prior to arrival/', $cancellationText, $m)
            || preg_match('/Cancellations made within (?<prior>\d{1,2} hours) prior to arrival date will be charged a cancellation fee equal/', $cancellationText, $m)
            || preg_match("/^Cancellations made within (?<prior>\d{1,2} hours) of arrival will forfeit one night's room rate./i", $cancellationText, $m)
            || preg_match("/Cancellation is free of charge if you cancel before 12.00 hours, (?<prior>\d{1,2} hours) before arrival./i", $cancellationText, $m)
            || preg_match("/Cancellations must be received (?<prior>\d+ days) prior to the day of arrival/i", $cancellationText, $m)
            || preg_match("/Reservations must be cancelled (?<prior>\d+ days) prior to arrival/i", $cancellationText, $m)
            || preg_match("/Reservations cancelled within (?<prior>\d+ days) of the scheduled arrival date/i", $cancellationText, $m)
            || preg_match("/(?<prior>\d+\s*hours?) notice is required to avoid a penalty equivalent to one night stay with taxes/i", $cancellationText, $m)
            || preg_match("/In the event that you need to modify or cancel this reservation, please notify us (?<prior>\d+\s*hours?) prior to the check-in time of your Arrival Date in order to avoid a cancellation fee/i", $cancellationText, $m)
            || preg_match("/In the event that you need to modify or cancel this reservation, please notify us (?<prior>\d+\s*hours?) prior to the check-in time of your Arrival Date in order to avoid a cancellation fee/i", $cancellationText, $m)
            || preg_match("/(?<prior>\d+\s*days) Prior Your To Your Arrival Date/i", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior']);
        } elseif (
            preg_match('/CXL\s*(?<prior>\d+)\s*HRS PRIOR TO ARRIVAL TO AVOID/i', $cancellationText, $m)
            || preg_match("/The hotel requires a (?<prior>\d{1,2})-hour cancellation policy prior to the arrival date and the guest may then cancel the reservation with no penalties./i", $cancellationText, $m)
            || preg_match("/Free changes or cancellations until (?<prior>\d{1,2}) hours before arrival for individual bookings/i", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'] . ' hours');
        } elseif (
            preg_match('/Cancellations made after (?<hour>\d{1,2}(?::\d{1,2})?(?:\s*[AP]M)?) one day prior to arrival will forfeit one night/i', $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative('1 day', $m['hour']);
        } elseif (
            preg_match('/Cancellations made after (\d{1,2})([AP]M) EST, (?<prior>\d+ hours?) of arrival will forfeit one night\'s room and tax/i', $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'], $m[1] . ':00 ' . $m[2]);
        }

        if (preg_match("#^[A-Z]{3} [\d\.\,]+ for cancellations made on or after (\d+\/\d+\/\d{4})\.#", $cancellationText, $m)
        || preg_match("#^A \w+-night non-refundable deposit will be charged on (.+?\d{4}). Cancellations made on/after \\1,? will result in forfeiture of your entire hotel stay and all room nights reserved#", $cancellationText, $m)) {
            // on/after
            $h->booked()->deadline(strtotime("-1 minute", strtotime($m[1])));
        } elseif (preg_match("#Reservations may be canceled without penalty on, or before, (?<date>.+?\d{4})\.#", $cancellationText, $m)
        ) {
            // on/before
            $h->booked()->deadline(strtotime($m['date'] . ' 23:59'));
        } elseif (preg_match("#No charge if rooms are released by\s*(\w+\s*\d+\,\s*\d{4})#us", $cancellationText, $m)
        ) {
            $h->booked()->deadline(strtotime("-1 minute", $this->normalizeDate($m[1])));
        } elseif (preg_match("/For confirmed reservations, please note that full length of stay will be chargeable for non-arrivals, cancellations and booking amendments made on and after (?<date>.+ \d{4})\./", $cancellationText, $m)) {
            // on/after
            $h->booked()->deadline(strtotime($m['date']));
        }

        if (preg_match_all("#Cancellation of an entire reservation on or after (.+?\d{4}),? will be charged a#", $cancellationText, $dateMatches)) {
            foreach ($dateMatches[1] as $d) {
                if ($date = strtotime("-1 minute", strtotime($d))) {
                    $dates[] = $date;
                } else {
                    $dates = null;

                    break;
                }
            }

            if (isset($dates)) {
                $d = min($dates);
                $h->booked()->deadline($d);
            }
        }
    }

    private function parseDeadlineRelative(Hotel $h, $prior, $hour = null): bool
    {
        $checkInDate = $h->getCheckInDate();

        if (empty($checkInDate)) {
            return false;
        }

        if (empty($hour)) {
            $deadline = strtotime('-' . $prior, $checkInDate);
            $h->booked()->deadline($deadline);

            return true;
        }

        $base = strtotime('-' . $prior, $checkInDate);

        if (empty($base)) {
            return false;
        }
        $deadline = strtotime($hour, strtotime(date('Y-m-d', $base)));

        if (empty($deadline)) {
            return false;
        }
        $priorUnix = strtotime($prior);

        if (empty($priorUnix)) {
            return false;
        }
        $priorSeconds = $priorUnix - strtotime('now');

        while ($checkInDate - $deadline < $priorSeconds) {
            $deadline = strtotime('-1 day', $deadline);
        }
        $h->booked()->deadline($deadline);

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($text): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $phrases) {
                foreach ($phrases as $phrase) {
                    if (stripos($text, $phrase) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (
            preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
            || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#^(?<c>[^\d\s]{1,5})(?<t>\d[\.\d\,\s]*)\s*$#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        } else {
            $tot = str_replace(',', '', $node);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function htmlToText($s, $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode('|', array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function getProvider()
    {
        foreach (self::$providers as $provider => $values) {
            foreach ((array) $values as $search) {
                if ($this->http->XPath->query("//text()[contains(normalize-space(.),'{$search}')]")->length > 0) {
                    return $provider;
                }
            }
        }

        return null;
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            "#^(\w+)\.?\s*(\d+)[,]\s*(\d{4})\s*[(]Check[-]\D+\s*time:\s*([\d\:]+\s*(?:AM|PM))[)]$#", //Mar 30, 2020 (Check-in time: 4:00 PM)
            "#^(\w+)\s*(\d+)\,\s*(\d{4})$#", //June 15, 2022
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeTime($time)
    {
        $in = [
            '#^\s*(\d+)\s*([ap]m)\b$#i', //3 pm
        ];
        $out = [
            '$1:00 $2',
        ];
        $time = preg_replace($in, $out, $time);

        if (preg_match("/^\s*\d{1,2}:\d{1,2}(\s*[ap]m)?\s*$/i", $time)) {
            return $time;
        }

        return null;
    }
}
