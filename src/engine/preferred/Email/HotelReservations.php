<?php

namespace AwardWallet\Engine\preferred\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: move it-75193773.eml from this parser to rosewood/ReservationDetails

class HotelReservations extends \TAccountChecker
{
    public $mailFiles = "preferred/it-10136956.eml, preferred/it-173298903.eml, preferred/it-200150623.eml, preferred/it-201741170.eml, preferred/it-20538262.eml, preferred/it-20996717.eml, preferred/it-23049698.eml, preferred/it-3066565.eml, preferred/it-32583006.eml, preferred/it-33394336.eml, preferred/it-35410679.eml, preferred/it-35413885.eml, preferred/it-36556725.eml, preferred/it-44418681.eml, preferred/it-45122782.eml, preferred/it-46693434.eml, preferred/it-47182421.eml, preferred/it-64512920.eml, preferred/it-75193773.eml, preferred/it-8024881.eml, preferred/it-82102034.eml, preferred/it-87881160.eml"; // +3 bcdtravel(html)[en]

    private $lang = '';
    private $emailSubject = '';

    private static $dict = [
        'en' => [
            'confirmation-starts'           => ['Confirmation Number', 'CONFIRMATION NUMBER', 'Confirmation #', 'Confirmation:', 'Confirmation No:', 'Hotel Confirmation Number:', 'PROPERTY CONFIRMATION NUMBER:', 'Confirmation number', 'Hotel Engine #:', 'Confirmation', 'Your booking #', 'Your Booking #', 'YOUR BOOKING #', 'Confirmation', 'CONFIRMATION:',
                'Itinerary Confirmation Number:', 'Confirmation Number:', ],
            'confirmation-eq'               => ['Itinerary Confirmation Number:', 'Confirmation number:', 'Confirmation number', 'Confirmation #:', 'Confirmation:', 'Confirmation', 'Reservation number', 'Booking number', 'Confirmation Number:'],
            'Guest Name'                    => ['Guest Name', 'GUEST NAME', 'NAME:', 'Name:', 'Name'],
            'guestInformation'              => ['Guest Information', 'GUEST INFORMATION'],
            'propertyInformation'           => ['Property Information', 'PROPERTY INFORMATION'],
            'tollFreeNumber'                => ['Toll-Free Number', 'Toll Free Number'],
            'numberAdults'                  => ['Number of Adults', 'Number of Guests', 'Adults/Children', '# of Adults', '# OF ADULTS', "Adult(s):", "NUMBER OF ADULTS"],
            'numberChildren'                => ['Number of Children', '# of Children', '# OF CHILDREN', "Child(ren):", "NUMBER OF CHILDREN"],
            'numberOfGuests'                => ['Number of Guest(s)', 'NUMBER OF GUESTS', 'Guests:', 'GUESTS:', 'Total Guests:', 'Adults', 'adults/children', 'No. of Persons', 'NUMBER OF PERSONS', 'N. OF GUESTS'],
            'Adult'                         => ['Adult', 'adult', 'Adults', 'Adults:'],
            'Child'                         => ['Child', 'child', 'Children'],
            'roomType'                      => ['Room Type', 'room type', 'ROOM TYPE', 'Room:', 'ACCOMMODATIONS:', 'Accommodations:', 'Type of Room', 'TYPE OF ROOM', 'Accommodation:', 'ACCOMMODATION', 'Accommodation', 'ROOM', 'Room Category:', 'Room type'],
            'roomVariants'                  => ['Room', 'King', 'Bed', 'Suite', 'Accommodation'],
            'roomAndRateDescription'        => ['Room and Rate Description', 'ROOM AND RATE DESCRIPTION', 'Room & Rate Description'],
            'ratePlan'                      => ['Rate Plan', 'Rate Plan:', 'Rate Name', 'Rate Name:', 'Rate Code:', 'Rate Description', 'RATE DESCRIPTION:', 'RATE PLAN:', 'NIGHTLY ROOM RATE:', 'Rate per Night', 'RATE NAME', 'Rate Per Night', 'Average Rate'],
            'rateSingleline'                => ['Room Rate:', 'Average Nightly Rate:', 'Average Nightly Rate (', 'Daily Rate with ', 'Nightly Rate', 'NIGHTLY RATE', 'Rate', 'Daily Rate With Tax:', 'Average Nightly Rate', 'AVERAGE DAILY RATE', 'Nightly rate:'],
            'roomTotal'                     => ['Total Room Rate', 'TOTAL ROOM RATE:', 'Total Rate Amount:', 'Estimated Total w/ Taxes:', 'Room Total:', 'Total Stay Amount', 'Total for Your Stay:', 'Total Charge:', 'Total Charges:', 'TOTAL CHARGES:', 'GRAND TOTAL:', 'Estimated Total:', 'Total Amount Including Taxes & Daily Resort Fee:', 'TOTAL STAY AMOUNT:', 'Total Cost With Tax:', 'Total Charges', 'TOTAL STAY AMOUNT *'],
            'rooms'                         => ['Rooms:', 'Number of Rooms', 'Room Booked', 'Rooms'],
            'taxes'                         => ['Total Taxes:', 'TAXES & FEES:', 'Taxes & Fees:', 'Taxes and Fees:', 'Government Taxes:', 'Room Tax:', 'Taxes', 'TAXES & RESORT FEE'],
            'Cost'                          => ['Room Subtotal:', 'Group Room Subtotal:', 'Before Tax:', 'Amount Before Tax:', 'Room:', 'SUBTOTAL*', 'SUBTOTAL*:'],
            'Discount'                      => 'Reward Points Applied:',
            'cancellationPolicy'            => ['Cancellation Policy', 'Policies and Terms', 'CANCELLATION OR CHANGES', 'Cancel Policy', 'Cancellation', 'CANCELLATION OR SHORTENED STAY:', 'CANCELLATION', 'Cancellation Policy:', 'Cancellation policy:'],
            'Cancellation must be received' => ['Cancellation must be received', 'Cancellation must be recieved', 'reservation may be cancel', 'Reservations must be cancelled'],
            'checkin/outTime'               => ['Check-In/Out Time', 'CHECK IN / OUT TIME:', 'Check in / Check out', 'Check In / Check Out'],
            'checkInTime-starts'            => ['Check In Time', 'Check-In Time', 'Check-in Time', 'CHECK-IN TIME', 'Check in', 'Check-in', 'Check-In', 'check in', 'Check-in time:', 'Check in begins at', 'Your room will be available', 'Your room will be available from', 'CHECK IN:', 'Note: Check-in time is'],
            'checkInTime-contains'          => ['Check-in Time:', '/ Check In', '/Check In', 'check in after', 'Check-in time begins at'],
            'checkOutTime-starts'           => ['Check Out Time', 'Check-Out Time', 'Check-out Time', 'CHECK-OUT TIME', 'CHECK OUT:', 'Check out', 'Check-out', 'check out', 'Check-Out', 'Check-out time:', 'Checkout Time:'],
            'checkOutTime-contains'         => ['Checkout Time:', 'Check out:', '/ Check Out', '/Check Out', 'Check out time on the day of departure is', 'Check-out time is at', 'Check out is due at', 'Check-out time is scheduled for', 'Our check-out time is'],
            'Nightly Rate per Room:'        => ['Nightly Rate per Room:', 'Nightly Rate per Room', 'Nightly rate'],
        ],
    ];

    public static function getEmailProviders()
    {
        return ['preferred', 'leadinghotels', 'relais', 'triprewards', 'wynnlv', 'hardrock', 'goldpassport', 'rosewood', 'hengine', 'ichotelsgroup',
            'designh', 'panpacific', 'loews', ];
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, 'preferredhotelgroup.com') === false && stripos($body, 'preferredhotels.com') === false
            && stripos($body, 'Preferred Hotel') === false && stripos($body, 'Prefered Hotel') === false
            && $this->http->XPath->query('//img[' . $this->contains(['/iPrefer.', '/iprefer_new.'], '@src') . ']')->length === 0
            && stripos($body, '//opalcollectionhotels.') === false
            && stripos($body, '//www.peppermillreno.com') === false
            && stripos($body, '//parklanenewyork.com') === false && stripos($body, 'www.parklanenewyork.com') === false && stripos($body, '@parklanenewyork.com') === false
            && stripos($body, '//gracebayresorts.com') === false && stripos($body, 'www.gracebayclub.com') === false && stripos($body, '@gracebayresorts.com') === false
            && stripos($body, 'thejouledallas.com') === false
            && stripos($body, 'alohilaniresort.com') === false
            && stripos($body, 'www.acqualina.com') === false
            && stripos($body, 'www.oceanhouseri.com') === false
            && stripos($body, 'www.thehermitagehotel.com') === false
            && stripos($body, 'wedgewoodhotel.com') === false
            && stripos($body, 'thepfisterhotel.com') === false
            && stripos($body, 'thebetsyhotel.com') === false
            && stripos($body, 'metropole.ch') === false
            && stripos($body, '@rosewoodhotels.com') === false
            && stripos($body, 'navislinks.hotelbennett.com') === false
            && stripos($body, '@acqualina.com') === false
            && stripos($body, '@sunsetkeycottages.com') === false && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing Sunset Key Cottages")]')->length === 0
            && stripos($body, 'hotelwailea.com') === false
            && stripos($body, 'wynnlasvegas.com') === false
            && stripos($body, 'grandpacifichotel.com.fj') === false
            && stripos($body, 'Thank you for choosing Wynn') === false
            && stripos($body, 'Thank you for choosing Wyndham Santa Monica') === false
            && stripos($body, 'Thank you for choosing Wyndham Grand Jupiter') === false
            && stripos($body, 'We are delighted you have included Hotel Wailea, Relais') === false
            && $this->http->XPath->query('//img[contains(@src,"wynn_las_vegas") or contains(normalize-space(@alt),"Wynn Las Vegas")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"Halekulani")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"halfmoon.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,".intercontinental.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing The Charles Hotel")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing the Rimrock") or contains(.,"@rimrockresort.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing Gild Hall")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for selecting Fifteen Beacon") or contains(.,"@xvbeacon.com") or contains(.,"@XVBeacon.com")]')->length === 0
            && $this->http->XPath->query("//*[contains(normalize-space(),'We look forward to welcoming you to Shutters on the Beach')]")->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"THANK YOU FOR MAKING YOUR RESERVATION AT THE OJAI VALLEY INN") or contains(.,"@ojaivalleyinn.com")] | //a[contains(@href,".ojaivalleyinn.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing Surf & Sand Resort for your upcoming stay in")]')->length === 0
            && $this->http->XPath->query("//*[contains(normalize-space(),'are delighted to confirm the following reservation and look forward to welcoming you to Regent Singapore')]")->length === 0
            && $this->http->XPath->query("//*[contains(normalize-space(),'Thank you for choosing Trump International Beach Resort')]")->length === 0
            && $this->http->XPath->query("//*[contains(normalize-space(),'Hard Rock Hotel')]")->length === 0
            && $this->http->XPath->query("//*[contains(normalize-space(),'Schlosshotel Berlin by Patrick Hellmann')]")->length === 0
            && $this->http->XPath->query("//*[contains(normalize-space(),'Salamander Resort & Spa')]")->length === 0
            && $this->http->XPath->query("//*[contains(normalize-space(),'Boston Park Plaza Hotel')]")->length === 0
            && $this->http->XPath->query("//*[contains(normalize-space(),'Thank you for choosing Halekulani for your')]")->length === 0
            && $this->http->XPath->query("//*[contains(normalize-space(),'Thanks for choosing Hotel Engine')]")->length === 0
            && $this->http->XPath->query("//*[contains(normalize-space(),'WELCOME TO EAST, MIAMI')]")->length === 0
            && $this->http->XPath->query("//*[contains(normalize-space(),'that you have chosen Miraval')]")->length === 0
            && $this->http->XPath->query("//*[contains(normalize-space(),'Eau Palm Beach Resort & Spa')]")->length === 0
            && $this->http->XPath->query("//*[contains(normalize-space(),\"L'Auberge \")]")->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Mountain Shadows")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"The Dewberry")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Dunton Hot Springs")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Esperanza Resort")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Miraval Berkshires")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Seminole Hard Rock Hotel and Casino")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"confirm your upcoming stay at Hawks Cay Resort")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing The Stafford London")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Kind regards, The Jefferson, DC Reservations Team")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing Boston Harbor Hotel for")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing The Chanler")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Wequassett Resort and Golf Club")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"The Newbury Boston")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"The Kahala Hotel & Resort")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Hammock Beach Golf Resort & Spa")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Rancho Bernardo")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"The Wigwam")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"with Hotel Wailea, Relais & Chateaux")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Hotel Riviera Maya")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing The Ludlow for your upcoming stay to New York City")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"La Réserve Genève")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"The Broadmoor")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Pan Pacific")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Loews Hotels")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"The Ritz")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"BAGLIONI HOTELS IS MEMBER OF")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Kempinski")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing Delray")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing Opal")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing the Jupiter")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"We can\'t wait to welcome you to Lake Nona")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing The Ryder")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Welcome to Casa del Mar")]')->length === 0
            && $this->http->XPath->query('//*[contains(@href,".reservations-client.com/")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"We are delighted to have you stay at Park Lane New York")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"welcoming you to The Lanesborough")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),", a Leading Hotels of the World member.")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"choosing Aurora ")]')->length === 0
        ) {
            return false;
        }
        $phrases = [
            'Thank you for choosing ',
            'We look forward to welcoming you to',
            'We await your arrival',
            'We understand that you have made adjustments to your reservation',
            "We're looking forward to your arrival",
            'We are pleased to confirm your reservation',
            'we are delighted that you have chosen Condado Vanderbilt Hotel',
            'We are delighted that you chose',
            'We are delighted to have you as our',
            'Details of your reservation have been updated',
            'Below are the details of your stay',
            'Reservation Confirmation',
            'We are delighted you have included',
            'We are delighted that you have included',
            'The Team at Hard Rock Hotel',
            're excited to welcome you to your',
            'offers Valet Parking for',
            'the welfare and safety of our guests and colleagues is a top priority',
            ' that you have chosen ',
            'On behalf of my team here at',
            'FROSCH HOTEL COLLECTION PROMOTION',
            'We are pleased to confirm your reservation at',
            'Thank you for choosing Dunton Hot Springs for your Mountain Escape',
            'be staying with us at Esperanza',
            'that you have chosen Miraval Berkshires',
            'Seminole Hard Rock Hotel and Casino',
            'Reservation Cancelled!',
            'Reservation Confirmed!',
            'Itinerary Details',
            'We are pleased to confirm your reservation details as follows',
            'Our Chanler Guest Services Team will be reaching out to you',
            'We hope that your stay will be filled with luxury and magic',
            'We are delighted that you have chosen to stay at',
            'At The Kahala Hotel & Resort, we are diligently taking action to prevent the spread',
            'We are excited to welcome you to',
            'Thank you for choosing Rancho Bernardo Inn for your upcoming stay in San Diego',
            'Thank you for choosing The Wigwam as the host of your Arizona getaway',
            'This reservation cannot be transferred or modified without the written consent of Unico Hotel Riviera Maya',
            'modification to your reservation with Hotel Wailea, Relais & Chateaux',
            'Thank you for choosing InterContinental London Park Lane',
            'Half Moon Foundation',
            'Thank you for choosing The Ludlow for your upcoming stay to New York City',
            'We already to look forward to welcoming you to La Réserve Genève',
            'We thank you for choosing The Broadmoor and look forward to welcoming you to our grand resort',
            'thank you for choosing Loews Hotels',
            'Thank you for choosing The Ritz as your hotel',
            'Further to your request, we thank you for the interest you have shown in',
            'We can\'t wait to welcome you to',
            'Thank you for choosing The Ryder',
            'It is our pleasure to confirm the following details for your stay',
            'We hope your stay will instill a sense of relaxation whether you are with us on business or leisure',
            'We are delighted to confirm the following reservation and look forward to',
        ];

        foreach ($phrases as $phrase) {
            if (stripos($body, $phrase) !== false
                || $this->http->XPath->query('//*[contains(normalize-space(),"' . $phrase . '")]')->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $html = str_ireplace(['&zwnj;', '&8203;', '​'], '', $this->http->Response['body']);
        $this->http->SetEmailBody($html);

        $this->lang = 'en';
        $email->setType('HotelReservations' . ucfirst($this->lang));

        $this->emailSubject = $parser->getSubject();

        $this->parseHtml($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict) * 4;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function parseHtml(Email $email): void
    {
        $nodesToStip = $this->http->XPath->query('//*[contains(@class,"Apple-converted-space") and not(.//*)]');

        foreach ($nodesToStip as $nodeToStip) {
            $nodeToStip->parentNode->removeChild($nodeToStip);
        }

        $xpathFragmentNoTitle = 'not(ancestor::title)';
        $xpathFragmentBold = '(self::strong or self::b)';

        // Travel Agency
        if (!empty($this->http->FindSingleNode("(//text()[contains(normalize-space(), 'Thanks for choosing Hotel Engine')])[1]"))) {
            $email->obtainTravelAgency();
            $email->setProviderCode('hengine');
        }

        $h = $email->add()->hotel();

        /*
         *  General
         */

        $patterns['confNumber'] = '[\-A-Z\d]{5,}'; // 1198637147-6    |    M5GPQK

        // ConfirmationNumber

        $confNumber = $confNumberTitle = null;

        foreach ((array) $this->t('confirmation-starts') as $phrase) {
            $re = "/(({$this->opt($phrase)})[:\s\#]*({$patterns['confNumber']}))$/";
            $confNumbers = array_filter($this->http->FindNodes("//text()[{$this->starts($phrase)} and $xpathFragmentNoTitle]", null, $re));

            if (count(array_unique($confNumbers)) === 1 && preg_match($re, array_shift($confNumbers), $m)) {
                $confNumberTitle = $m[2];
                $confNumber = $m[3];

                break;
            }
        }

        if (!($confNumber)) {
            $confNumber = $this->http->FindSingleNode("//text()[normalize-space()='Reservation Number']/ancestor::tr[1]/descendant::td[last()]", null, true, "/^\s*[\-A-Z\d &]{5,}\s*$/");
            $confNumberTitle = 'Reservation Number';

            if (!$confNumber) {
                $confNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confirmation-starts'))} and $xpathFragmentNoTitle]/following::text()[normalize-space()][1]/ancestor::*[{$this->starts($this->t('confirmation-starts'))}][1]",
                    null, true, '/^\s*' . $this->opt($this->t('confirmation-starts')) . '\s*:?\s*(' . $patterns['confNumber'] . '(?:\s*\&\s*' . $patterns['confNumber'] . ')+)\s*$/');
                $confNumberTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confirmation-starts'))} and $xpathFragmentNoTitle]",
                    null, true, '/(' . $this->opt($this->t('confirmation-starts')) . ')/');
            }

            if (stripos($confNumber, '&')) {
                $arrayNumber = explode('&', $confNumber);

                if (count($arrayNumber) > 0) {
                    foreach ($arrayNumber as $confNumber) {
                        $h->general()->confirmation($confNumber, trim($confNumberTitle, ':'));
                    }
                }
            }
        }

        $xpathTdThTable = "(name()='td' or name()='th' or name()='table')";

        if (!($confNumber)) {
            $phrases = [];

            foreach ((array) $this->t('confirmation-eq') as $p) {
                $phrases[] = $p;
                $phrases[] = strtoupper($p);
                $phrases[] = ucwords($p);
            }

            foreach ($phrases as $p) {
                $confNumber = $this->http->FindSingleNode("//text()[{$this->eq($p)} and $xpathFragmentNoTitle]/ancestor::*[{$xpathTdThTable}][ following-sibling::*[{$xpathTdThTable}][normalize-space()] ][1]/following-sibling::*[{$xpathTdThTable}][normalize-space()][1]", null, true, "/^\s*({$patterns['confNumber']})\s*$/");

                if ($confNumber) {
                    $confNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($p)} and $xpathFragmentNoTitle]");

                    break;
                }
            }

            if (empty($confNumber)) {
                foreach ($phrases as $p) {
                    $confNumber = $this->http->FindSingleNode("//text()[{$this->eq($p)} and $xpathFragmentNoTitle]/ancestor::div[ {$this->eq($p)} and following-sibling::div[normalize-space()] ][1]/following-sibling::div[normalize-space()][1]", null, true, "/^\s*({$patterns['confNumber']})\s*$/");

                    if ($confNumber) {
                        $confNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($p)} and $xpathFragmentNoTitle]");

                        break;
                    }
                }
            }
        }

        if (!$confNumber) {
            $confNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confirmation-starts'))} and $xpathFragmentNoTitle]/following::text()[normalize-space()][1]", null, true, '/(' . $patterns['confNumber'] . ')/');
            $confNumberTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confirmation-starts'))} and $xpathFragmentNoTitle]", null, true, '/(' . $this->opt($this->t('confirmation-starts')) . ')/');
        }

        if (!$confNumber) {
            $confNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Itinerary Details'))}]/following::text()[{$this->starts($this->t('confirmation-starts'))}][1]", null, true, '/(' . $patterns['confNumber'] . ')/');
            $confNumberTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Itinerary Details'))}]/following::text()[{$this->starts($this->t('confirmation-starts'))}][1]", null, true, '/(' . $this->opt($this->t('confirmation-starts')) . ')/');
        }

        if (empty($confNumber) && (
                preg_match("/:\s*(?<title>Confirmation)\s+(?-i)(?<number>[-A-Z\d]{5,})\b/i", $this->emailSubject, $m) > 0
                || preg_match("/\b(?<title>Confirmation\s+(?:No?\.?|[№#]))\s+(?-i)(?<number>[-A-Z\d]{5,})\s+-.+/i", $this->emailSubject, $m) > 0
            )
        ) {
            $confNumberTitle = $m['title'];
            $confNumber = $m['number'];
        }

        if (!($confNumber)) {
            $confNumber = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation')][not(contains(normalize-space(), 'Confirmation Number'))]/ancestor::tr[1]", null, true, '/\s+(' . $patterns['confNumber'] . ')\s*$/');
            $confNumberTitle = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation')][not(contains(normalize-space(), 'Confirmation Number'))]");
        }

        if (!($confNumber)) {
            $confNumber = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation Number:')]/ancestor::tr[1]", null, true, '/\s+(' . $patterns['confNumber'] . ')\s*$/');
            $confNumberTitle = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation Number:')]");
        }

        if (!($confNumber)) {
            $confNumber = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservation Number')]/ancestor::td[1]", null, true, '/^\s*Reservation Number\s*(' . $patterns['confNumber'] . ')\s*$/');
            $confNumberTitle = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservation Number')]");
        }

        if (!($confNumber)) {
            $confNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confirmation-eq'))}]/following::text()[string-length()>4][not({$this->contains($this->t('Guest Name'))})][1]", null, true, '/^(' . $patterns['confNumber'] . ')$/');
            $confNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confirmation-eq'))}]");
        }

        if ($email->getProviderCode() != 'hengine' && !empty($confNumber) && empty($h->getConfirmationNumbers()[0][0])) {
            $h->general()->confirmation($confNumber, rtrim($confNumberTitle, ': '));
        }

        if ($email->getProviderCode() == 'hengine') {
            $confs = array_filter(explode(',', str_replace(['[', ']', '"', ' '], '', $this->http->FindSingleNode("//text()[normalize-space() = 'Confirmation #:']/ following::text()[normalize-space()][1]"))));
            $h->general()
                ->noConfirmation();

            foreach ($confs as $conf) {
                $email->ota()
                    ->confirmation($conf);
            }

            if (count($confs) == 0 && !empty($confNumber)) {
                $email->ota()
                    ->confirmation($confNumber, rtrim($confNumberTitle, ': '));
            }
        }

        $otaConf = $this->http->FindSingleNode("//text()[normalize-space()='Online Reservation Number']/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\d+)$/");

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf, 'Online Reservation Number');
        }

        //Booking Date
        $bookingDate = $this->http->FindSingleNode("//text()[normalize-space()='Booking Date:']/ancestor::tr[1]/descendant::td[2]");

        if (!empty($bookingDate)) {
            $h->general()
                ->date(strtotime($bookingDate));
        }

        $patterns['guestName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]';

        // GuestNames
        $guestName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Guest Name'))}]/following::text()[normalize-space()][1]", null, true, '/^[^}{%]{2,}$/');

        if (preg_match("/^(\d+\-\d+)/", $guestName)) {
            $guestName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Guest Name'))}]/following::text()[normalize-space()][2]", null, true, '/^[^}{%]{2,}$/');
        }

        if (empty($guestName)) {
            $guestName = $this->http->FindSingleNode("//tr[{$this->starts($this->t('guestInformation'))}]/following::text()[normalize-space()][1][ not(contains(.,':')) and ancestor::*[$xpathFragmentBold] ]", null, true, "/^{$patterns['guestName']}$/u");
        }

        if (empty($guestName)) {
            $guestName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}\s+({$patterns['guestName']})\s*,/u");
        }

        if (empty($guestName)) {
            $guestName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('guestInformation'))}]/following::text()[normalize-space()][string-length()> 2][1][ not(contains(.,':')) and ancestor::*[$xpathFragmentBold] ]", null, true, "/^{$patterns['guestName']}$/u");
        }

        if (empty($guestName)) {
            $guestName = $this->http->FindSingleNode("//text()[normalize-space()='Letter of confirmation for']/following::text()[normalize-space()][1]", null, true, "/^{$patterns['guestName']}$/u");
        }

        $r = '[[:alpha:]\-]{2,}';

        if (preg_match("/^({$r}( {$r})+) *(, *({$r}( {$r})+))+$/u", $guestName)) {
            $guestName = array_map('trim', preg_split("/\s*,\s*/", trim($guestName)));
        } elseif (preg_match("/^\s*(\w+\.\s*)?({$r}( {$r})+) *(\\/ *({$r}( {$r})+))+$/u", $guestName)) {
            $guestName = array_map('trim', preg_split("/\s*\\/\s*/", trim($guestName)));
        } elseif (!empty($guestName)) {
            $guestName = [$guestName];
        }

        if (!empty($guestName)) {
            $h->general()->travellers(preg_replace(["/^[ :]*/", "/^\s*(Mr\.? (?:\&|and) Mrs\.?|Ms\.) /i", "/Senhor\s*/", "/^(Mr\s)/"], "", $guestName));
        }

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->eq(["Reservation Cancelled!"]) . "])[1]"))) {
            $h->general()
                ->cancelled()
                ->status('Cancelled')
            ;
        }

        /*
         *  Hotel
         */

        // hard-code hotels & address
        $supportedHotels = [
            [
                'name'           => 'The Sanctuary at Kiawah Island Golf Resort', // it-20996717.eml
                'nameDetects'    => ['It is our pleasure to confirm your reservation at The Sanctuary at Kiawah Island Golf Resort'],
                'addressDetects' => ['One Sanctuary Beach Drive, Kiawah Island, SC 29455'],
            ],
            [
                'name'           => 'The Houstonian Hotel Club & Spa',
                'nameDetects'    => ['to confirm your reservation at The Houstonian Hotel, Club & Spa'],
                'addressDetects' => ['111 North Post Oak Lane, Houston, Texas 77024'],
            ],
            [
                'name'        => 'Jupiter Beach Resort & Spa', // it-3066565.eml
                'nameDetects' => ['Thank you for choosing the Jupiter Beach Resort & Spa for your'],
                //                'addressDetects' => [''],
            ],
            [
                'name'           => 'Condado Vanderbilt Hotel',
                'nameDetects'    => ['we are delighted that you have chosen Condado Vanderbilt Hotel for your'],
                'addressDetects' => ['1055 ASHFORD AVENUE SAN JUAN, PUERTO RICO 00907'],
            ],
            [
                'name'        => 'The Sagamore Resort',
                'nameDetects' => ['Thank you for choosing The Sagamore Resort'],
                //                'addressDetects' => [''],
            ],
            [
                'name'           => 'The Stafford London', // it-20538262.eml
                'nameDetects'    => ['Thank you for choosing The Stafford London', 'Thank you very much for choosing The Stafford London'],
                'addressDetects' => ["16-18 St James's Place, London, SW1A 1NJ, UK", '16‑18 St James’s Place London SW1A 1NJ'],
            ],
            [
                'name'           => 'The Jefferson, Washington, DC',
                'nameDetects'    => ['We are pleased to confirm your reservation at The Jefferson, Washington, DC', 'We look forward to welcoming you to The Jefferson, Washington, DC'],
                'addressDetects' => ['1200 16th St NW Washington, DC 20036'],
            ],
            [
                'name'        => 'Rosen Shingle Creek',
                'nameDetects' => ['Thank you for choosing Rosen Shingle Creek'],
                //                'addressDetects' => [''],
            ],
            [
                'name'           => 'Peppermill Resort Spa Casino',
                'nameDetects'    => ['Thank you for choosing Peppermill Resort Spa Casino'],
                'addressDetects' => ['2707 South Virginia Street, Reno, NV, 89502', '2707 S. Virginia St., Reno NV 89502'],
            ],
            [
                'name'        => 'Hotel Skt. Petri',
                'nameDetects' => ['Thank you for choosing Hotel Skt. Petri'],
                //                'addressDetects' => [''],
            ],
            [
                'name'           => 'The Joule in Dallas',
                'nameDetects'    => ['your reservation at The Joule in Dallas'],
                'addressDetects' => ['1530 Main Street Dallas, Texas 75201'],
            ],
            [
                'name'           => 'Alohilani Resort Waikiki Beach',
                'nameDetects'    => ['Thank you for choosing ‘Alohilani Resort Waikiki Beach'],
                'addressDetects' => ['2490 KALAKAUA AVENUE, HONOLULU, HAWAII 96815'],
            ],
            [
                'name'        => 'The Rimrock Resort Hotel', // it-32583006.eml
                'nameDetects' => ['Thank you for choosing the Rimrock Resort Hotel', 'Thank you for choosing The Rimrock Resort Hotel'],
                //                'addressDetects' => [''],
            ],
            [
                'name'           => 'Fifteen Beacon Hotel', // it-33394336.eml
                'nameDetects'    => ['Thank you for selecting Fifteen Beacon Hotel'],
                'addressDetects' => ['15 BEACON STREET, BOSTON MA 02108'],
            ],
            [
                'name'        => 'Sandpearl Resort',
                'nameDetects' => ['Thank you for choosing the Sandpearl Resort for your upcoming travel plans'],
                //                'addressDetects' => [''],
            ],
            [
                'name'           => 'Ojai Valley Inn', // it-35410679.eml
                'nameDetects'    => ['THANK YOU FOR MAKING YOUR RESERVATION AT THE OJAI VALLEY INN'],
                'addressDetects' => ['905 COUNTRY CLUB ROAD, OJAI, CA 93023'],
            ],
            [
                'name'        => 'The Beaumont', // it-23049698.eml
                'nameDetects' => ['Thank you for choosing The Beaumont'],
                //'addressDetects' => ['Brown Hart Gardens'],
            ],
            [
                'name' => 'Shutters on the Beach',
                //                'nameDetects' => [''],
                'addressDetects' => ['1 Pico Boulevard | Santa Monica, CA 90405'],
            ],
            [
                'name'           => 'Hotel Captain Cook',
                'nameDetects'    => ['Thank you for choosing to stay with us at the Hotel Captain Cook', 'We look forward to welcoming you to the Hotel Captain Cook'],
                'addressDetects' => ['939 W. 5TH AVENUE, ANCHORAGE, ALASKA 99501'],
            ],
            [
                'name'           => 'The Charles Hotel',
                'nameDetects'    => ['Thank you for choosing The Charles Hotel as your home away from home'],
                'addressDetects' => ['Harvard Square | One Bennett Street | Cambridge, MA 02138'],
            ],
            [
                'name'           => 'The Peabody Memphis',
                'nameDetects'    => ['of The Peabody Memphis are eager to provide you with a memorable hotel stay'],
                'addressDetects' => ['The Peabody 149 Union Ave Memphis TN, US 38103'],
            ],
            [
                'name'           => 'Gild Hall',
                'nameDetects'    => ['Thank you for choosing Gild Hall'],
                'addressDetects' => ['15 Gold Street, New York, NY 10038'],
            ],
            [
                'name'           => 'Hotel Arista at CityGate Centre',
                'nameDetects'    => ['Thank you for choosing Hotel Arista at CityGate Centre'],
                'addressDetects' => ['2139 CityGate Lane Naperville, IL 60563'],
            ],
            [
                'name'           => 'Acqualina Resort & Spa on the Beach',
                'nameDetects'    => ['Thank you for choosing Acqualina Resort & Spa on the Beach'],
                'addressDetects' => ['17875 Collins Avenue | Sunny Isles Beach, Fl 33160'],
                'providerCode'   => 'leadinghotels',
            ],
            [
                'name'           => 'Surf & Sand Resort',
                'nameDetects'    => ['Thank you for choosing Surf & Sand Resort for your upcoming stay in'],
                'addressDetects' => ['1555 South Coast Highway Laguna Beach, CA United States 92651'],
            ],
            [
                'name'        => 'Park Lane',
                'nameDetects' => ['We are delighted to have you stay at the Park Lane for you'],
                //                'addressDetects' => ['']
            ],
            [
                'name'           => 'Regent Singapore',
                'nameDetects'    => ['are delighted to confirm the following reservation and look forward to welcoming you to Regent Singapore'],
                'addressDetects' => ['Regent Singapore, 1 Cuscaden Road, Singapore 249715'],
            ],
            [
                'name'           => 'The Pfister Hotel',
                'nameDetects'    => ['We are delighted that you chose The Pfister Hotel'],
                'addressDetects' => ['424 E Wisconsin Ave, Milwaukee, WI 53202'],
            ],
            [
                'name'           => 'Hawks Cay Resort',
                'nameDetects'    => ['re happy to officially confirm your upcoming stay at Hawks Cay Resort!'],
                'addressDetects' => ['Hawks Cay Resort, 61 Hawks Cay Blvd., Duck Key, FL, 33050 United States'],
            ],
            [
                'name'           => 'Ocean House',
                'nameDetects'    => ['We are pleased to confirm your reservation at Ocean House'],
                'addressDetects' => ['1 Bluff Avenue Watch Hill, RI United States 02891'],
                'providerCode'   => 'relais',
            ],
            [
                'name'        => 'Hotel Metropole Geneve',
                'nameDetects' => ['Thank you for choosing the Hotel Metropole Geneve'],
                //                'addressDetects' => ['Quai du Général Guisan 34  |   1204 Geneva, Switzerland'],
            ],
            [
                'name'           => 'Wedgewood Hotel & Spa',
                'nameDetects'    => ['Thank you for choosing the Wedgewood Hotel & Spa'],
                'addressDetects' => ['845 Hornby Street Vancouver, BC V6Z 1V1'],
                'providerCode'   => 'relais',
            ],
            [
                'name' => 'EAST, MIAMI',
                // 'nameDetects' => [''],
                'addressDetects' => ['788 Brickell Plaza | Miami, Florida 33131'],
            ],
            [
                'name'           => 'Park Central Hotel New York',
                'nameDetects'    => ['Thank you for choosing to stay at the Park Central Hotel New York'],
                'addressDetects' => ['870 SEVENTH AVENUE, NEW YORK, NY 10019'],
            ],
            [
                'name'           => 'The Betsy - South Beach',
                'nameDetects'    => ['Thank you for choosing The Betsy - South Beach'],
                'addressDetects' => ['1440 Ocean Drive, Miami Beach, FL 33139'],
            ],
            [
                'name'           => 'Hotel Monteleone',
                'nameDetects'    => ['Thank you for choosing Hotel Monteleone'],
                'addressDetects' => ['214 Royal Street, New Orleans, LA 70130-2201'],
            ],
            [
                'name'           => 'Windsor Court Hotel',
                'nameDetects'    => ['Thank you for choosing Windsor Court Hotel'],
                'addressDetects' => ['300 Gravier Street, New Orleans, LA 70130'],
            ],
            [
                'name'           => 'Boston Harbor Hotel',
                'nameDetects'    => ['Thank you for your reservation at the Boston Harbor Hotel'],
                'addressDetects' => ['70 Rowes Wharf, Boston MA 02110'],
            ],
            [
                'name'           => 'Halekulani',
                'nameDetects'    => ['Thank you for choosing to stay with us at Halekulani'],
                'addressDetects' => ['2199 Kalia Road, Honolulu, Hawaii 96815'],
            ],
            [
                'name'           => 'Wyndham Santa Monica',
                'nameDetects'    => ['Thank you for choosing Wyndham Santa Monica'],
                'addressDetects' => ['120 Colorado Avenue, Santa Monica, CA 90401'],
                'providerCode'   => 'triprewards',
            ],
            [
                'name'           => 'Wyndham Grand Jupiter',
                'nameDetects'    => ['Thank you for choosing Wyndham Grand Jupiter'],
                'addressDetects' => ['122 Soundings Ave, Jupiter, FL 33477'],
                'providerCode'   => 'triprewards',
            ],
            [
                'name'        => 'Wynn Las Vegas',
                'nameDetects' => ['Thank you for choosing Wynn Las Vegas'],
                //                'addressDetects' => ['3131 S Las Vegas Blvd, Las Vegas, NV 89109'],
                'providerCode' => 'wynnlv',
            ],
            [
                'name'           => 'Hotel Wailea, Relais & Chateaux',
                'nameDetects'    => ['We all look forward to the pleasure of welcoming you to Hotel Wailea, Relais & Châteaux'],
                'addressDetects' => ['555 Kaukahi St., Wailea, Maui, HI 96753'],
                'providerCode'   => 'relais',
            ],
            [
                'name'           => 'Trump International Beach Resort',
                'nameDetects'    => ['Thank you for choosing Trump International Beach Resort'],
                'addressDetects' => ['18001 Collins Avenue Sunny Isles Beach, FL United States 33160'],
            ],
            [
                'name'           => 'Seminole Hard Rock Hotel & Casino in Hollywood',
                'nameDetects'    => ['Thank you for your reservation at Seminole Hard Rock Hotel & Casino in Hollywood'],
                'addressDetects' => ['1 Seminole Way, Hollywood FL 33314'],
                'providerCode'   => 'hardrock',
            ],
            [
                'name'        => 'Hard Rock Hotel & Casino Atlantic City',
                'nameDetects' => [
                    'Your upcoming stay at Hard Rock Hotel & Casino Atlantic City',
                    'all set, and the details of your stay with us at Hard Rock Hotel & Casino Atlantic City',
                ],
                'addressDetects' => ['1000 Boardwalk, Atlantic City, NJ 08401'],
                'providerCode'   => 'hardrock',
            ],
            [
                'name'           => 'Hard Rock Hotel Los Cabos',
                'nameDetects'    => ['You\'re all set, and the details of your stay with us at Hard Rock Hotel Los Cabos'],
                'addressDetects' => ['Fraccionamiento Diamante Cabo San Lucas, Baja California Sur 23473 México'],
                'providerCode'   => 'hardrock',
            ],
            [
                'name'        => 'Hard Rock Hotel Sioux City',
                'nameDetects' => [
                    'Thank you for choosing the Hard Rock Hotel Sioux City.',
                    'To ensure you receive future Hard Rock Hotel Sioux City email',
                ],
                'addressDetects' => ['111 3rd St. | Sioux City, IA 51101'],
                'providerCode'   => 'hardrock',
            ],
            [
                'name'           => 'Salamander Resort & Spa',
                'nameDetects'    => ['Salamander Resort & Spa, it is our mission to ensure our guests experience'],
                'addressDetects' => ['500 NORTH PENDLETON STREET MIDDLEBURG, VA 20117'],
                'phone'          => ['844.303.2723'],
            ],
            [
                'name'           => 'Schlosshotel Berlin by Patrick Hellmann',
                'nameDetects'    => ['We are looking forward to welcoming you soon at Schlosshotel Berlin by Patrick Hellmann'],
                'addressDetects' => ['Schlosshotel Berlin by Patrick Hellmann. Brahmsstraße 10, Berlin 14193'],
            ],
            [
                'name'           => 'Halekulani',
                'nameDetects'    => ['Thank you for choosing Halekulani for your next visit to'],
                'addressDetects' => ['2199 Kalia Road, Honolulu, Hawaii 96815'],
            ],
            [
                'name'           => 'South’s Grand Hotel',
                'nameDetects'    => ['Thank you for selecting the '],
                'addressDetects' => ['The Peabody Memphis, 149 Union Ave, Memphis, TN, USA, 38103'],
            ],
            [
                'name'           => 'Miraval Austin',
                'nameDetects'    => ['that you have chosen Miraval Austin'],
                'addressDetects' => ['13500 FM 2769 | Austin, TX 78726'],
                'providerCode'   => 'goldpassport',
            ],
            [
                'name'           => 'Miraval Arizona',
                'nameDetects'    => ['that you have chosen Miraval Arizona'],
                'addressDetects' => ['Via Estancia | Tucson, AZ 85739'],
                'providerCode'   => 'goldpassport',
            ],
            [
                'name'           => 'The Henderson',
                'nameDetects'    => ['We look forward to welcoming you to The Henderson'],
                'addressDetects' => ['200 HENDERSON RESORT WAY DESTIN, FL 32541'],
            ],
            [
                'name'           => 'Eau Palm Beach Resort & Spa',
                'nameDetects'    => ['On behalf of my team here at Eau Palm Beach Resort & Spa'],
                'addressDetects' => ['100 South Ocean Blvd. Manalapan, FL 33462'],
            ],
            [
                'name'           => 'Eau Palm Beach Resort & Spa',
                'nameDetects'    => ['On behalf of my team here at Eau Palm Beach Resort & Spa'],
                'addressDetects' => ['100 South Ocean Blvd. Manalapan, FL 33462'],
            ],
            [
                'name'           => 'Cerulean Tower Tokyu Hotel',
                'nameDetects'    => ['Thank you for choosing Cerulean Tower Tokyu Hotel'],
                'addressDetects' => ['26 1 Sakuragaokacho Tokyo, Tokyo 150-8512 Japan'],
            ],
            [
                'name'           => 'Las Ventanas al Paraíso, A Rosewood Resort',
                'nameDetects'    => ['thank you once again for choosing Las Ventanas al Paraíso'],
                'addressDetects' => ['KM 19.5 Ctra. Transpeninsular, San Jose del Cabo, Baja California Sur 23400, Mexico'],
                'providerCode'   => 'rosewood',
            ],
            [
                'name'           => 'Rosewood Mayakoba',
                'nameDetects'    => ['Thank you for choosing to stay with us for your forthcoming visit to Riviera Maya'],
                'addressDetects' => ['Ctra. Federal Cancún-Playa del Carmen KM 298 Solidaridad, Q. Roo, CP 77710 Mexico'],
                'providerCode'   => 'rosewood',
            ],
            [
                'name'           => 'Acqualina Resort & Residences on the Beach',
                'nameDetects'    => ['Thank you for choosing Acqualina Resort & Residences on the Beach'],
                'addressDetects' => ['17875 Collins Avenue, Sunny Isles Beach, FL 33160'],
            ],
            [
                'name'           => 'Mountain Shadows',
                'nameDetects'    => ['This email was sent by: Mountain Shadows'],
                'addressDetects' => ['5445 East Lincoln Drive, Paradise Valley, AZ 85253'],
            ],
            [
                'name'           => 'The Dewberry',
                'nameDetects'    => ['We are pleased to confirm your reservation at The Dewberry and look forward'],
                'addressDetects' => ['334 Meeting Street | Charleston, SC 29403'],
            ],
            [
                'name'           => 'Hotel Bennett',
                'nameDetects'    => ['Thank you for choosing Hotel Bennett for your upcoming stay'],
                'addressDetects' => ['404 KING ST CHARLESTON, SC 29043'],
            ],
            [
                'name'           => 'Dunton Hot Springs',
                'nameDetects'    => ['Thank you for choosing Dunton Hot Springs for your'],
                'addressDetects' => ['52068 Rd 38, Dolores, CO 81323'],
            ],
            [
                'name'           => 'The Hermitage Hotel',
                'nameDetects'    => ['Thank you for choosing The Hermitage Hotel'],
                'addressDetects' => ['231 Sixth Avenue North, Nashville, TN 37219'],
                'phone'          => ['615-244-3121'],
            ],
            [
                'name'           => 'Esperanza Resort',
                'nameDetects'    => ['be staying with us at Esperanza'],
                'addressDetects' => ['Carret. Transp. KM7 Cabo San Lucas, MX Mexico 23410'],
                'phone'          => ['877.870.0439'],
            ],
            [
                'name'           => 'Seminole Hard Rock Hotel and Casino',
                'nameDetects'    => ['Your upcoming stay at Seminole Hard Rock Hotel & Casino Tampa is almost here'],
                'addressDetects' => ['5223 Orient Road Tampa, FL 33610'],
                'phone'          => ['1-866-388-4263'],
            ],
            [
                'name'            => 'Miraval Berkshires',
                'nameDetects'     => ['that you have chosen Miraval Berkshires for your upcoming getaway'],
                'addressDetects'  => ['55 LEE RD  |   LENOX, MA 01240'],
                'addressDetects2' => ['55 LEE RD'],
                'phone'           => ['877.644.9465'],
            ],
            [
                'name'            => 'Chatham Bars Inn',
                'nameDetects'     => ['welcoming you to Chatham Bars Inn.'],
                'addressDetects'  => ['297 Shore Road, Chatham, Cape Cod, MA 02633'],
                'phone'           => ['508.945.0096'],
            ],
            [
                'name'           => 'Boston Harbor Hotel',
                'nameDetects'    => ['Thank you for choosing Boston Harbor Hotel for your upcoming'],
                'addressDetects' => ['70 ROWES WHARF, BOSTON, MA 02110'],
                'phone'          => ['617.439.7000'],
            ],
            [
                'name'           => 'The Chanler at Cliff Walk',
                'nameDetects'    => ['Thank you for choosing The Chanler at Cliff Walk'],
                'addressDetects' => ['117 Memorial Boulevard, Newport, Rhode Island 02840'],
                'phone'          => ['(401) 847-1300'],
            ],
            [
                'name'           => 'Wequassett Resort and Golf Club',
                'nameDetects'    => ['We hope that your stay will be filled with luxury and magic'],
                'addressDetects' => ['On Pleasant Bay, Chatham MA 02650'],
            ],
            [
                'name'           => 'The Newbury Boston',
                'nameDetects'    => ['We are delighted that you have chosen to stay at The Newbury Boston'],
                'addressDetects' => ['One Newbury Street | Boston, MA 02116'],
            ],
            [
                'name'           => 'Innisbrook Resort & Golf Club',
                'nameDetects'    => ['This email was sent by: Innisbrook Resort & Golf Club'],
                'addressDetects' => ['36750 US Hwy 19 N Palm Harbor, FL 34684'],
                'phone'          => ['855.461.6191'],
            ],
            [
                'name'           => 'The Kahala Hotel & Resort',
                'nameDetects'    => ['At The Kahala Hotel & Resort, we are diligently taking action'],
                'addressDetects' => ['5000 Kahala Avenue Honolulu, Hawaii 96816'],
            ],

            [
                'name'           => 'Hammock Beach Golf Resort & Spa',
                'nameDetects'    => ['We are excited to welcome you to Hammock Beach Golf Resort & Spa'],
                'addressDetects' => ['200 OCEAN CREST DR, PALM COAST, FL 32137'],
            ],

            [
                'name'           => 'Rancho Bernardo',
                'nameDetects'    => ['Thank you for choosing Rancho Bernardo Inn for your upcoming stay in San Diego'],
                'addressDetects' => ['17550 Bernardo Oaks Drive San Diego, CA United States 92128'],
            ],
            [
                'name'           => 'The Wigwam',
                'nameDetects'    => ['Thank you for choosing The Wigwam as the host of your Arizona getaway'],
                'addressDetects' => ['300 East Wigwam Blvd., Litchfield Park, Arizona 85340'],
            ],
            [
                'name'           => 'Eau Palm Beach Resort & Spa',
                'nameDetects'    => ['On behalf of my team here at Eau Palm Beach Resort & Spa'],
                'addressDetects' => ['100 South Ocean Boulevard, Manalapan, FL 33462'],
            ],
            [
                'name'           => 'Hotel de Paris Saint Tropez',
                'nameDetects'    => ['Thank you for choosing Hotel de Paris Saint Tropez'],
            ],
            [
                'name'                  => 'InterContinental Hayman Island Resort',
                'nameDetects'           => ['for choosing InterContinental Hayman Island Resort'],
                'addressDetects'        => ['1 Raintree Avenue, Hayman Island, Queensland 4801, Australia'],
                'providerCode'          => 'ichotelsgroup',
            ],
            [
                'name'                  => 'UNICO 20°87° Hotel Riviera Maya',
                'nameDetects'           => ['This reservation cannot be transferred or modified without the written consent of Unico Hotel Riviera Maya'],
                'addressDetects'        => ['Carretera Federal 307 Chetumal-Puerto Juárez KM 256+100 Solidaridad, Quintana Roo 77710 Mexico'],
            ],
            [
                'name'                  => 'THE BILTMORE',
                'nameDetects'           => ['Biltmore Membership'],
                'addressDetects'        => ['1200 Anastasia Avenue Coral Gables, FL 33134'],
            ],
            [
                'name'                  => 'La Réserve Genève',
                'nameDetects'           => ['We already to look forward to welcoming you to La Réserve Genève'],
                'addressDetects'        => ['Route de Lausanne, 301 · Geneva · Switzerland'],
            ],
            [
                'name'                  => 'The Broadmoor',
                'nameDetects'           => ['We thank you for choosing The Broadmoor and look forward to welcoming you to our grand resort'],
                'addressDetects'        => ['1 Lake Avenue, Colorado Springs, CO 80906'],
            ],
            [
                'name'                  => 'Aurora Anguilla Resort & Golf Club',
                'nameDetects'           => ['Thank you choosing Aurora Anguilla Resort & Golf Club'],
                'addressDetects'        => ['RENDEZVOUS BAY, ANGUILLA AI-2640'],
            ],
            [
                'name'                  => 'The Ritz',
                'nameDetects'           => ['WWW.THERITZLONDON.COM'],
                'addressDetects'        => ['150 Piccadilly, London, W1J 9BR'],
            ],
            [
                'name'                  => 'The Windsor Court',
                'nameDetects'           => ['Thank you for choosing The Windsor Court'],
                'addressDetects'        => ['300 Gravier Street New Orleans, LA 70130'],
            ],
            [
                'name'           => 'L\'Auberge Carmel',
                'nameDetects'    => ['included L\'Auberge Carmel'],
                'addressDetects' => ['Monte Verde at Seventh, Carmel-by-the-Sea, California 93921'],
                'phone'          => ['831 624 8578'],
                'providerCode'   => 'relais',
            ],
            [
                'name'           => 'InterContinental London Park Lane',
                'nameDetects'    => ['Thank you for choosing InterContinental London Park Lane again'],
                'addressDetects' => ['One Hamilton Place, Park Lane London, W1J 7QY, United Kingdom'],
                'phone'          => ['+44 (0)2 074093131'],
                'providerCode'   => 'ichotelsgroup',
            ],
            [
                'name'           => 'The Ludlow',
                'nameDetects'    => ['Thank you for choosing The Ludlow for your upcoming stay to New York City'],
                //                'addressDetects' => [''],
                //                'phone'          => [''],
                'providerCode'   => 'designh',
            ],
            [
                'name'           => 'Pan Pacific Vancouver',
                'nameDetects'    => ['Thank you for choosing the Pan Pacific Vancouver'],
                'addressDetects' => ['300 – 999 Canada Place Vancouver, BC, V6C 3B5 | Canada'],
                'phone'          => ['+1-604-662-8111'],
                'providerCode'   => 'panpacific',
            ],
            [
                'name'           => 'Loews Chicago O\'Hare Hotel',
                'nameDetects'    => ['Loews Chicago O\'Hare Hotel'],
                'addressDetects' => ['5300 N. River Road Rosemont, Illinois 60018'],
                'phone'          => ['847-544-5300'],
                'providerCode'   => 'loews',
            ],
            [
                'name'           => 'Loews Vantana Canyon Resort',
                'nameDetects'    => ['Loews Vantana Canyon Resort'],
                'addressDetects' => ['7000 N. Resort Drive Tucson, Arizona 85750'],
                'phone'          => ['520-299-2020'],
                'providerCode'   => 'loews',
            ],
            [
                'name'           => 'Loews Miami Beach',
                'nameDetects'    => ['Loews Miami Beach'],
                //                'addressDetects' => ['7000 N. Resort Drive Tucson, Arizona 85750'],
                //                'phone'          => ['520-299-2020'],
                'providerCode'   => 'loews',
            ],
            [
                'name'           => 'Loews Philadelphia',
                'nameDetects'    => ['Loews Philadelphia'],
                'addressDetects' => ['1200 Market Street Philadelphia, Pennsylvania 19107'],
                'phone'          => ['215-627-1200'],
                'providerCode'   => 'loews',
            ],
            [
                'name'           => 'Loews Vanderbilt Hotel',
                'nameDetects'    => ['Loews Vanderbilt Hotel'],
                'addressDetects' => ['2100 West End Avenue Nashville, Tennessee 37203'],
                'phone'          => ['615-320-1700'],
                'providerCode'   => 'loews',
            ],
            [
                'name'           => 'Baglioni Hotel Luna',
                'nameDetects'    => ['Baglioni Hotel Luna'],
                'addressDetects' => ['San Marco, 1243 30124 Venice, Italy'],
                'phone'          => ['+39 041 5289840'],
                'providerCode'   => 'leadinghotels',
            ],
            [
                'name'           => 'Lake Nona Wave Hotel',
                'nameDetects'    => ['welcome you to Lake Nona Wave Hotel'],
                'addressDetects' => ['6100 Wave Hotel Drive, Orlando, FL 32827'],
            ],
            [
                'name'           => 'The Ryder',
                'nameDetects'    => ['Thank you for choosing The Ryder'],
                'addressDetects' => ['237 Meeting Street, Charleston, South Carolina 29401'],
                'phone'          => ['843-723-7451'],
            ],
            [
                'name'           => 'Hotel Casa del Mar',
                'nameDetects'    => ['Welcome to Casa del Mar'],
                'addressDetects' => ['1910 Ocean Way Santa Monica, CA United States 90405'],
            ],
            [
                'name'           => 'Biltmore Hotel',
                'nameDetects'    => ['Thank you for choosing The Biltmore, Miami'],
                'addressDetects' => ['1200 Anastasia Avenue, Coral Gables, FL, 33134, USA'],
            ],
            [
                'name'           => 'Villa Dagmar',
                'nameDetects'    => ['Thank you for choosing to stay at Villa Dagmar'],
                'addressDetects' => ['Nybrogatan 25-27 114 39 Stockholm, Sweden'],
            ],
            [
                'name'           => 'Park Lane New York',
                'nameDetects'    => ['We are delighted to have you stay at Park Lane New York'],
                'addressDetects' => ['36 Central Park South New York, NY 10019'],
            ],
            [
                'name'           => 'The Lanesborough',
                'nameDetects'    => ['welcoming you to The Lanesborough'],
                'addressDetects' => ['Hyde Park Corner, London SW1X 7TA'],
            ],
            [
                'name'           => 'Villa Dubrovnik',
                'nameDetects'    => ['Thank you for choosing Villa Dubrovnik, a Leading Hotels of the World member', '@villa-dubrovnik.hr'],
                'providerCode'   => 'leadinghotels',
            ],
            /* [
                 'name' => 'Holiday Inn Express and Suites Greenville',
                 'nameDetects' => ['3090 Highway 82 East, Greenville, MS, 38702'],
                 'addressDetects' => ['3090 Highway 82 East, Greenville, MS, 38702'],
             ],
             [
                 'name' => 'Quality Inn Effingham',
                 'nameDetects' => ['1304 W Evergreen Drive, Effingham, IL, 62401'],
                 'addressDetects' => ['1304 W Evergreen Drive, Effingham, IL, 62401'],
             ],*/
        ];

        // HotelName
        $hotelName = '';

        foreach ($supportedHotels as $hotel) {
            if (empty($hotel['name']) || !is_string($hotel['name']) || empty($hotel['nameDetects']) || !is_array($hotel['nameDetects'])) {
                continue;
            }

            foreach ($hotel['nameDetects'] as $phrase) {
                if ($this->http->XPath->query("//node()[{$this->contains([$phrase, strtoupper($phrase), ucwords(strtolower($phrase))])}]")->length > 0) {
                    $hotelName = $hotel['name'];

                    if (!empty($hotel['providerCode'])) {
                        $email->setProviderCode($hotel['providerCode']);
                    }

                    break 2;
                }
            }
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[normalize-space() = 'Thanks for choosing' or normalize-space() = 'Thank you for choosing']/following::text()[normalize-space()][1]");
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode('(//a//img[contains(@src, "logo") or contains(@src, "Logo") or contains(@src, "/HOTEL/")][1]/@alt)', null, true, "#^\s*([A-Z\d][^_]+)$#");
        }

        if (empty($hotelName) && preg_match("/\b(Rosewood .+):\s*Reservation\s*Confirmation\s\w{5,}\s*$/u", $this->emailSubject, $m)) {
            $hotelName = $m[1];
            $address = $this->http->FindSingleNode("//text()[(normalize-space(.)='" . $hotelName . "')]/ancestor::*[(normalize-space(.)='" . $hotelName . "')][following-sibling::*[normalize-space()][2][normalize-space()= 'View Map']]/following-sibling::*[normalize-space()][1]");
        }

        if ($hotelName == 'Hotel Engine Logo' || $email->getProviderCode() == 'hengine') {
            $hotelName = $this->http->FindSingleNode("//text()[normalize-space() = 'View Itinerary']/ancestor::*[preceding-sibling::*[.//img and normalize-space()='']][1][descendant::text()[normalize-space() = 'View Itinerary']]/descendant::text()[normalize-space()][1]");
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('propertyInformation'))}]/following::text()[normalize-space()][1]/ancestor-or-self::strong");
        } // it-8024881.eml

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("descendant::text()[{$this->starts(['Welcome to', 'WELCOME TO'])}][last()]", null, true, "/Welcome to (.+)/i");

            if (mb_strlen($hotelName) > 70) {
                $hotelName = preg_replace('/^(.+?)(?:\s+-\s+|[,.;!?\-]).*/', '$1', $hotelName);
            }
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'We look forward to welcoming you to')]", null, true, '/We look forward to welcoming you to\s+(\D{2,}?)(?:\s*[.;!]|$)/')
                ?? $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Thank you for reserving your stay at')]/ancestor::tr[1]", null, true, '/Thank you for reserving your stay at\s+(\D{2,}?)\s*[.;!]\s*Please check/')
                ?? $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Further to your request, we thank you for the interest you have shown in')]/ancestor::tr[1]", null, true, '/the interest you have shown in (?:the )?(.+?)\./')
                ?? $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Thank you for choosing')]/ancestor::tr[1]", null, true, '/Thank you for choosing (?:the )?(.+?) for your upcoming (?:travel plans|stay)/')
                ?? $this->http->FindSingleNode("//text()[normalize-space()='Hotel Name']/following::text()[normalize-space()][1]");
        }

        $contactFieldNames = ['Toll Free:', 'Toll free:', 'Phone:', 'PHONE:', 'For reservations:', 'www.', 'WWW.'];
        $patterns['phone'] = '[+(\d][-.\s\d)(]{5,}[\d)]'; // +377 (93) 15 48 52    |    713.680.2992

        // Address
        $address = $address ?? '';

        if (empty($address)) {
            $addressTexts = $hotelName ? $this->http->FindNodes("//text()[{$this->eq(['Confirmation number:', 'Confirmation Number:', 'CONFIRMATION NUMBER:'])} or {$this->starts(['Your booking #', 'Your Booking #', 'YOUR BOOKING #'])}]/following::text()[{$this->eq([$hotelName, strtoupper($hotelName), ucwords(strtolower($hotelName))])}]/following::text()[normalize-space() and not({$this->contains($contactFieldNames)})][ following::text()[{$this->starts($contactFieldNames)}] ]") : []; // it-3066565.eml, it-32583006.eml
            $addressValues = array_map(function ($s) {
                return trim($s, ', ');
            }, $addressTexts);

            if (!empty($addressValues[0])) {
                $address = implode(', ', $addressValues);
            }
        }

        $phone = '';

        if (empty($address) && !empty($hotelName)) {
            foreach ($supportedHotels as $hotel) {
                if (empty($hotel['name']) || !is_string($hotel['name']) || empty($hotel['addressDetects']) || !is_array($hotel['addressDetects'])) {
                    continue;
                }

                if ($hotel['name'] !== $hotelName) {
                    continue;
                }

                foreach ($hotel['addressDetects'] as $phrase) {
                    if ($this->http->XPath->query("//node()[{$this->contains([$phrase, strtoupper($phrase), ucwords(strtolower($phrase))])}]")->length > 0) {
                        $address = str_replace(' | ', ', ', $phrase);

                        break;
                    } elseif (isset($hotel['addressDetects2'])) {
                        foreach ($hotel['addressDetects2'] as $phrase2) {
                            if ($this->http->XPath->query("//text()[{$this->starts([$phrase, strtoupper($phrase2), ucwords(strtolower($phrase2))])}]")->length > 0) {
                                $address = str_replace(' | ', ', ', $phrase);

                                break 2;
                            }
                        }
                    }
                }

                if (isset($hotel['phone'])) {
                    foreach ($hotel['phone'] as $phrase) {
                        if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                            $phone = $phrase;

                            break;
                        }
                    }
                }

                if (!empty($address)) {
                    break;
                }
            }
        }

        if (empty($address) && !empty($hotelName)) {
            $contacts = implode(' ', $this->http->FindNodes("//text()[{$this->starts($hotelName)}]/ancestor::td[1][{$this->contains(['Phone', 'P:'])}]/descendant::text()[normalize-space()]"));

            if (preg_match("/{$this->opt($hotelName)}[\s\|]+(?<address>.{3,}?)[\s\|]+{$this->opt(['Phone', 'P:'])}\s+(?<phone>{$patterns['phone']})/", $contacts, $m)) {
                $address = $m['address'];
                $phone = $m['phone'];
            }

            if (empty($address)) {
                // it-46693434.eml
                $address = $this->http->FindSingleNode("//a[{$this->contains($hotelName . ' - Google Maps', '@title')}]", null, true, '/^[,.\'\/\s\w]{3,}$/u');
            }

            if (empty($address)) {
                $address = $this->http->FindSingleNode("//text()[{$this->contains('This email was sent by: ' . $hotelName)}]/following-sibling::text()[string-length(normalize-space())>10]");
            }
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode('//text()[normalize-space(.)="view directions"]/following::text()[normalize-space()][1]', null, true, "#^[^a-z]+$#");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thanks for choosing')]/following::text()[normalize-space()][2]");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[normalize-space()='Hotel Address']/following::text()[normalize-space()][1]/ancestor::span[1]");
        }

        if (empty($address)) {
            $address = implode(', ', $this->http->FindNodes("//text()[normalize-space()='Address']/following::text()[normalize-space()][1]/ancestor::p[1]/descendant::text()[normalize-space()][not({$this->contains(['Address', 'View on Google Map', 'View on Map'])})]"));
        }

        if (empty($address) && $hotelName) {
            // it-8024881.eml
            $addressTexts = $this->http->FindNodes("//tr[{$this->starts($this->t('propertyInformation'))}]/following::text()[normalize-space(.)][1][{$this->contains($hotelName)}]/ancestor::tr[1]/descendant::text()[normalize-space()][position()>1]");
            $addressValues = [];

            foreach ($addressTexts as $addressText) {
                $addressText = trim($addressText, ', ');

                if (preg_match("/^{$patterns['phone']}$/", $addressText)) { // 456-321-7823
                    $phone = $addressText;

                    continue;
                } elseif (preg_match('/[A-z]{2,}\.[A-z]{2,}$/', $addressText)) { // www.hotel.com
                    continue;
                }
                $addressValues[] = $addressText;
            }

            if (!empty($addressValues[0])) {
                $address = implode(', ', $addressValues);
            }
        }

        if (empty($address) && !empty($hotelName)) {
            $addressText = implode(' ', $this->http->FindNodes("//text()[normalize-space()='Reservations Team']/following::text()[{$this->eq($hotelName)}]/ancestor::td[1]/descendant::text()[normalize-space()]"));

            if (preg_match("/{$hotelName}\s*(.+)\s*Contact\:\s*([\d\-]+)/", $addressText, $m)) {
                $address = $m[1];
                $phone = $m[2];
            }
        }

        if (empty($address) && !empty($hotelName)) {
            $addressText = $this->http->FindNodes("//text()[{$this->eq($hotelName)}][last()]/ancestor::td[1]/descendant::text()[normalize-space()]");

            if (count($addressText) < 5 && preg_match("/^\s*{$hotelName}\n([\s\S]+,\s*[A-Z]{2} \d{5})\n([\d\-\(\.\)\+]{5,})\s*$/", implode("\n", $addressText), $m)) {
                $address = preg_replace(["/\|/", "/\s+/", "/\s*,\s*/"], [',', ' ', ', '], $m[1]);
                $phone = $m[2];
            } elseif (count($addressText) < 5 && preg_match("/^\s*{$hotelName}\n(.+)$/s", implode("\n", $addressText), $m)) {
                $address = str_replace("\n", ", ", $m[1]);
            }
        }

        $h->hotel()
            ->name($hotelName)
        ;

        if (!empty($address)) {
            $h->hotel()
                ->address($address);
        } else {
            $h->hotel()
                ->noAddress();
        }

        // Phone
        $xpathFragment1 = $hotelName ? "//text()[{$this->eq([$hotelName, strtoupper($hotelName), ucwords(strtolower($hotelName))])}]" : '';

        if (empty($phone) && $xpathFragment1) {
            $phone = $this->http->FindSingleNode($xpathFragment1 . "/following::text()[{$this->eq($contactFieldNames)}][1]/following::text()[normalize-space(.)][1]", null, true, '/^\s*(' . $patterns['phone'] . ')$/');
        }

        if (empty($phone) && $xpathFragment1) {
            $phone = $this->http->FindSingleNode($xpathFragment1 . "/following::text()[{$this->contains('call us at')}][1]/following::text()[normalize-space(.)][1]", null, true, '/^\s*(' . $patterns['phone'] . ')$/');
        }

        if (empty($phone) && $xpathFragment1) {
            $phone = $this->http->FindSingleNode($xpathFragment1 . "/following::text()[{$this->eq('Main:')}][1]/following::text()[normalize-space(.)][1]", null, true, '/^\s*(' . $patterns['phone'] . ')$/');
        }

        if (empty($phone) && $xpathFragment1) {
            $phone = $this->http->FindSingleNode($xpathFragment1 . "/following::text()[{$this->starts($contactFieldNames)}][1]/following::text()[normalize-space(.)][1]", null, true, '/\s*(' . $patterns['phone'] . ')/');
        }

        if (empty($phone) && $xpathFragment1) {
            $phone = $this->http->FindSingleNode($xpathFragment1 . "/ancestor::td[1]/descendant::text()[{$this->starts($contactFieldNames)}]", null, true, "/{$this->opt($contactFieldNames)}\s*({$patterns['phone']})/");
        } // it-32583006.eml

        if (empty($phone) && $xpathFragment1) {
            $phone = $this->http->FindSingleNode($xpathFragment1 . "/ancestor::td[1]/descendant::text()[{$this->starts('Local:')}]", null, true, "/{$this->opt('Local:')}\s*({$patterns['phone']})/");
        } // it-32583006.eml

        if (empty($phone) && $address) {
            $phone = $this->http->FindSingleNode('//text()[normalize-space(.)="' . strtoupper($address) . '"]/following::text()[normalize-space(.)="TEL."][1]/following::text()[normalize-space(.)][1]', null, true, '/^\s*(' . $patterns['phone'] . ')$/');
        }

        if (empty($phone) && $address) {
            $phone = $this->http->FindSingleNode('//text()[normalize-space(.)="' . strtoupper($address) . '"]/following::text()[normalize-space(.)="LOCAL"][1]/following::text()[normalize-space(.)][1]', null, true, '/^\s*(' . $patterns['phone'] . ')$/');
        }

        if (empty($phone) && $address) {
            $phone = $this->http->FindSingleNode("//tr[ not(.//tr) and count(descendant::text()[string-length(normalize-space())>3])=2 and descendant::text()[normalize-space()][1][{$this->contains($address)}] ]/descendant::text()[string-length(normalize-space())>3][2]", null, true, "/^\s*({$patterns['phone']})$/");
        }

        if (empty($phone) && $address) {
            $phone = $this->http->FindSingleNode("//text()[{$this->contains([$address, strtoupper($address), ucwords(strtolower($address))])} and contains(.,' - ')]", null, true, "/{$this->opt($address)}\s*-\s*({$patterns['phone']})/i");
        } // it-35410679.eml

        if (empty($phone) && $address) {
            $phones = array_filter($this->http->FindNodes("//text()[{$this->contains($address)}]/following::text()[normalize-space()][1]", null, "/^\s*P?[:\s]*({$patterns['phone']})/"));

            if (count(array_unique($phones)) === 1) {
                $phone = array_shift($phones);
            }
        }

        if (empty($phone) && $address) {
            $phone = $this->http->FindSingleNode('//text()[normalize-space(.)="' . $address . '"]/following::text()[normalize-space(.)="View Map"][1]/following::text()[normalize-space(.)!=""][1]', null, true, '/^\s*(' . $patterns['phone'] . ')$/');
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('tollFreeNumber'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]", null, true, '/^(' . $patterns['phone'] . ')$/');
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode('//text()[' . $this->eq('CONTACT US') . ']/following::text()[position()<10][' . $this->eq('Reservations Number') . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]', null, true, '/^(' . $patterns['phone'] . ')$/');
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode('//text()[' . $this->eq('CONTACT US') . ']/following::text()[position()<10][' . $this->eq('Resort Main Number') . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]', null, true, '/^(' . $patterns['phone'] . ')$/');
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[{$this->contains(['Main Number', 'MAIN NUMBER'])}]/ancestor::td[1]/following-sibling::td[normalize-space()]", null, true, '/^' . $patterns['phone'] . '$/');
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[normalize-space()='Hotel Phone']/following::text()[normalize-space()][1]/ancestor::span[1]", null, true, '/^' . $patterns['phone'] . '$/');
        }

        if (empty($phone) && !empty($hotelName)) {
            $contactsTexts = $this->http->FindNodes("//text()[{$this->eq($hotelName)}]/ancestor::td[1]/descendant::text()[normalize-space()][position()<7]", null, '/(' . $patterns['phone'] . ')\s*\|*$/'); // it-23049698.eml
            $contactsValues = array_values(array_filter($contactsTexts));

            if (count($contactsValues) === 1) {
                $phone = $contactsValues[0];
            }
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//a[{$this->contains($address)}]/preceding-sibling::a[contains(@href,'tel:')]");
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//a[{$this->contains($address)}]/following-sibling::a[contains(@href,'tel:')]");
        }

        if (empty($phone) && !empty($address)) {
            $phone = $this->http->FindSingleNode("//text()[{$this->contains($address)}]/ancestor::tr[1]/following-sibling::tr[1]/descendant::text()[normalize-space()][2]", null, true, '/^' . $patterns['phone'] . '$/');
        }

        if (!empty($phone)) {
            $h->hotel()->phone(preg_replace('/(\d)\.(\d)/', '$1-$2', $phone));
        }

        // Fax
        if (!empty($address)) {
            $addressTexts = $this->http->FindNodes('//td[not(.//td) and ' . $this->contains($address) . ']/descendant::text()[normalize-space(.)]');
            $addressText = implode(' ', $addressTexts);

            if (preg_match('/\bFax\s*:?\s*(' . $patterns['phone'] . ')/i', $addressText, $matches)) {
                $fax = $matches[1];
            }
        }

        if (empty($h->getFax())) {
            $fax = $this->http->FindSingleNode("//text()[normalize-space()='Hotel Fax']/following::text()[normalize-space()][1]/ancestor::span[1]", null, true, '/^' . $patterns['phone'] . '$/');
        }

        if (!empty($fax)) {
            $h->hotel()->fax(preg_replace('/(\d)\.(\d)/', '$1-$2', $fax));
        }

        /*
         *  Booked
         */

        $patterns['time'] = '\d{1,2}(?:\:?\.?\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?'; // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon

        // CheckInDate
        foreach (['Check In Date', 'Arrival Date', 'ARRIVAL:', 'Check-In:', 'Check In', 'Check-In Date:', 'ARRIVAL DATE', 'ARRIVAL', 'Arrival', 'Arrival date'] as $phrase) {
            $checkInDate = $this->http->FindSingleNode("//text()[{$this->starts($phrase)} and not({$this->contains(['Check In Time:', 'Check In Instructions'])})]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathFragmentBold}])]", null, true, '/^.*\d.*$/');

            if ($checkInDate) {
                break;
            }
        }

        if (empty($checkInDate)) {
            $checkInDate = $this->http->FindSingleNode('//td[not(.//td) and ' . $this->eq(['Arrival Date', 'ARRIVAL DATE:', 'ARRIVAL DATE', 'Arrival date']) . ']/following-sibling::td[normalize-space(.)][1]', null, true, "#.*\b\d{4}\b.*#");
        }

        if (empty($checkInDate)) {
            $checkInDate = $this->http->FindSingleNode('//text()[' . $this->eq(['Arrival Date', 'ARRIVAL DATE:', 'Arrival Date:', 'ARRIVAL:', 'Check-In:', 'ARRIVAL DATE', 'Arrival date']) . ']/following::text()[normalize-space()][1]');
        }

        if (empty($checkInDate)) {
            $checkInDate = $this->http->FindSingleNode('//text()[' . $this->eq(['Arrival Date', 'ARRIVAL DATE:', 'Arrival Date:', 'ARRIVAL:', 'Check-In:', 'Arrival date']) . ']/following::text()[normalize-space()][1]');
        }

        if (empty($checkInDate)) {
            $checkInDate = $this->http->FindSingleNode("//text()[normalize-space()='Check-in/Check-out:']/following::text()[normalize-space()][1]", null, true, "/^(.+)\//");
        }

        if ($checkInDate) {
            $checkInDate = strtotime($this->normalizeDate($checkInDate));

            if (!empty($checkInDate)) {
                $h->booked()->checkIn($checkInDate);
            }
        }

        if (empty($h->getCheckInDate())) {
            $checkInDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Arrival']/following::text()[normalize-space()][1]")));

            if (!empty($checkInDate)) {
                $h->booked()->checkIn($checkInDate);
                $checkInTime = $this->http->FindSingleNode("//text()[normalize-space()='Check In']/following::text()[normalize-space()][1]");
                $checkOutDate = $this->http->FindSingleNode("//text()[normalize-space()='Departure']/following::text()[normalize-space()][1]");
                $checkOutTime = $this->http->FindSingleNode("//text()[normalize-space()='Check Out']/following::text()[normalize-space()][1]");
            }
        }

        if (empty($checkInTime)) {
            $checkInTime = $this->http->FindSingleNode("//text()[{$this->starts($this->t('checkin/outTime'))}][1]/following::text()[normalize-space(.)!=''][1]",
                null, true, '/^\s*(\d{1,2}:\d{2}[^\/]*?)\s*\/\s*/');
        }

        if (empty($checkInTime)) {
            $checkInTime = $this->http->FindSingleNode("//text()[{$this->starts($this->t('checkin/outTime'))}][1]/following::text()[normalize-space(.)!=''][1]", null, true, '/^\s*(\d{1,2}A?P?M\s*[^\/]*?)/i');
        }

        $patters['timeEnd'] = "(?:\s*-\s*noon|[ ]*Check|$)";

        if (empty($checkInTime)) {
            foreach ((array) $this->t('checkInTime-starts') as $p) {
                $phrases = [$p, strtoupper($p), ucwords(strtolower($p))];
                $checkInTime = $this->http->FindSingleNode("//text()[{$this->starts($phrases)}]", null, true, "/{$this->opt($phrases)}[: ]+({$patterns['time']})[):]*{$patters['timeEnd']}/i")
                    ?? $this->http->FindSingleNode("//text()[{$this->eq($phrases)}]/following::text()[normalize-space()][1]", null, true, "/^[:(\s]*({$patterns['time']})[):]*{$patters['timeEnd']}/i");

                if ($checkInTime) {
                    break;
                }
            }
        }

        if (empty($checkInTime)) {
            $checkInTime = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('checkInTime-starts'))}])[1]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathFragmentBold}])]", null, true, "/^\D*({$patterns['time']})(?:[ ]*Check|[ ]*[)(]|$|)/i");
        }

        if (empty($checkInTime)) {
            $checkInTime = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('checkInTime-starts'))}])[1]", null, true, "/^\D*({$patterns['time']})(?:[ ]*Check|[ ]*[)(]|$|)/i");
        }

        if (empty($checkInTime)) {
            $checkInTime = $this->http->FindSingleNode("//text()[{$this->contains($this->t('checkInTime-contains'))}]", null, true, "/{$this->opt($this->t('checkInTime-contains'))}\s*({$patterns['time']})(?:[ ]*[.;:)(]|$)/i");
        }

        if (empty($checkInTime)) {
            $checkInTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Our check-in time is')][1]", null, true, '/Our check-in time is[ ]*(\d{1,2}:\d{2} [ap]\.m\.)/');
        }

        if (empty($checkInTime)) {
            $checkInTime = $this->http->FindSingleNode("//text()[normalize-space()='Check In Time:']/following::text()[normalize-space()][1]", null, true, "/([\d\:]+\s*A?P?M)/i");
        }

        if (empty($checkInTime)) {
            $checkInTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('checkInTime-starts'))}]/following::text()[normalize-space()][1]", null, true, "/([\d\:]+\s*A?P?M)/i");
        }

        if (empty($checkInTime)) {
            $checkInTime = $this->http->FindSingleNode("//text()[normalize-space()='CHECK-IN:']/following::text()[normalize-space()][1]", null, true, "/^\s*([\d\:]+\s*A?P?M)\s*$/i");
        }

        //checkInTime-starts

        if (!empty($h->getCheckInDate()) && $checkInTime && ($checkInTime = strtotime($this->normalizeTime($checkInTime), $h->getCheckInDate()))) {
            $h->booked()->checkIn($checkInTime);
        }

        // CheckOutDate
        if (empty($checkOutDate)) {
            foreach (['Check Out Date', 'Departure Date', 'DEPARTURE:', 'Check-Out:', 'Check Out', 'Check-Out Date:', 'DEPARTURE DATE', 'DEPARTURE', 'Departure date'] as $phrase) {
                $checkOutDate = $this->http->FindSingleNode("//text()[{$this->starts($phrase)} and not({$this->contains(['Check Out Time:'])})]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathFragmentBold}])]",
                    null, true, '/^.*\d.*$/');

                if ($checkOutDate) {
                    break;
                }
            }
        }

        if (empty($checkOutDate)) {
            $checkOutDate = $this->http->FindSingleNode('//td[not(.//td) and ' . $this->eq(['Departure Date', 'DEPARTURE DATE:']) . ']/following-sibling::td[normalize-space(.)][1]', null, true, "#.*\b\d{4}\b.*#");
        }

        if (empty($checkOutDate)) {
            $checkOutDate = $this->http->FindSingleNode('//text()[' . $this->eq(['Departure Date', 'DEPARTURE DATE:', 'Departure Date:', 'DEPARTURE:', 'Check-Out:', 'DEPARTURE DATE']) . ']/following::text()[normalize-space()][1]');
        }

        if (empty($checkOutDate)) {
            $checkOutDate = $this->http->FindSingleNode("//text()[normalize-space()='Check-in/Check-out:']/following::text()[normalize-space()][1]", null, true, "/\/(.+)$/");
        }

        if ($checkOutDate) {
            $h->booked()->checkOut(strtotime($this->normalizeDate($checkOutDate)));
        }

        if (empty($checkOutTime)) {
            $checkOutTime = $this->http->FindSingleNode("//text()[{$this->starts($this->t('checkin/outTime'))}][1]/following::text()[normalize-space(.)!=''][1]",
                null, true, '/\/\s*(\d{1,2}:\d{2}[^\/]*?|noon)\s*$/i');
        }

        if (empty($checkOutTime)) {
            $checkOutTime = $this->http->FindSingleNode("//text()[{$this->starts($this->t('checkin/outTime'))}][1]/following::text()[normalize-space(.)!=''][1]", null, true, '/\/\s*(\d{1,2}A?P?M\s*)$/i');
        }

        if (empty($checkOutTime)) {
            foreach ((array) $this->t('checkOutTime-starts') as $p) {
                $phrases = [$p, strtoupper($p), ucwords(strtolower($p))];
                $checkOutTime = $this->http->FindSingleNode("//text()[{$this->starts($phrases)}]", null, true, "/{$this->opt($phrases)}[: ]+({$patterns['time']})[):]*{$patters['timeEnd']}/i")
                    ?? $this->http->FindSingleNode("//text()[{$this->eq($phrases)}]/following::text()[normalize-space()][1]", null, true, "/^[:(\s]*({$patterns['time']})[):]*{$patters['timeEnd']}/i");

                if ($checkOutTime) {
                    break;
                }
            }
        }

        if (empty($checkOutTime)) {
            $checkOutTime = $this->http->FindSingleNode("//text()[{$this->starts($this->t('checkOutTime-starts'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathFragmentBold}])]", null, true, "/^\D*({$patterns['time']})(?:[ ]*[)(]|$|\D+)/i");
        }

        if (empty($checkOutTime)) {
            $checkOutTime = $this->http->FindSingleNode("//text()[{$this->contains($this->t('checkOutTime-contains'))}]", null, true, "/{$this->opt($this->t('checkOutTime-contains'))}\s*({$patterns['time']})(?:[ ]*[.;:)(]|$)/i");
        }

        if (empty($checkOutTime)) {
            if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Check-out time is scheduled for noon.')]")->length > 0) {
                $checkOutTime = '12:00';
            }
        }

        if (empty($checkOutTime)) {
            $checkOutTime = str_replace(' noon', ':00', $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Our check-in time is')][1]", null, true, '/check-out time is[ ]*((?:\d{1,2}:\d{2} [ap]\.m\.|\d{1,2} noon))/'));
        }

        if (empty($checkOutTime)) {
            $checkOutTime = $this->http->FindSingleNode("//text()[normalize-space()='CHECK-OUT:']/following::text()[normalize-space()][1]", null, true, "/^\s*([\d\:]+\s*A?P?M)\s*$/i");
        }

        if (!empty($h->getCheckOutDate()) && $checkOutTime && ($checkOutTime = strtotime($this->normalizeTime($checkOutTime), $h->getCheckOutDate()))) {
            $h->booked()->checkOut($checkOutTime);
        }

        // Guests
        $guests = $childrens = null;

        $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('numberOfGuests'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^.*\d.*$/");

        if ($node === null) {
            $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('numberOfGuests'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathFragmentBold}])]", null, true, "/^.*\d.*$/");
        }

        if (!isset($node)) {
            $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('numberAdults'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^.*\d.*$/");
        }

        if ($node === null) {
            $node = $this->http->FindSingleNode("//text()[{$this->eq('Adults')}]/ancestor::td[1]/following-sibling::*[normalize-space()]", null, true, "/^.*\d.*$/");
        }

        if ($node === null) {
            $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('numberAdults'))} or {$this->starts('Adults:')}]/following::text()[normalize-space(.)][1]", null, true,
                "/^[ :]*(\d+)\b/");
        }

        if ($node === null) {
            $node = $this->http->FindSingleNode("//text()[{$this->contains('Adults:')}]");
        }

        if ($node === null) {
            $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('numberOfGuests'))}]/following::text()[normalize-space()][1]/ancestor::td[1][not({$this->contains($this->t('numberOfGuests'))})]");
        }

        if (preg_match("#\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}\D+(\d{1,3})\s+{$this->opt($this->t('Child'))}#", $node, $m)) {
            $guests = $m[1];
            $childrens = $m[2];
        } elseif (preg_match("#\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}#", $node, $m)) {
            $guests = $m[1];
        } elseif (preg_match("#{$this->opt($this->t('Adult'))}[:]\s(\d+)\s\/\s{$this->opt($this->t('Child'))}[:]?\s(\d+)#", $node, $m)) {
            $guests = $m[1];
            $childrens = $m[2];
        } elseif (preg_match("#^(\d{1,2})/(\d{1,2})$#", $node, $m)) {
            $guests = $m[1];
            $childrens = $m[2];
        } elseif (preg_match("#^(\d{1,2})$#", $node, $m)) {
            $guests = $m[1];
        }

        if ($guests === null) {
            $node = $this->http->FindSingleNode("//text()[{$this->eq('Number of Adults / Children')}]/ancestor::td[1]/following-sibling::*[normalize-space(.)]");

            if (preg_match("/^\s*(\d+)\s*\\/\s*(\d+)\s*$/", $node, $m)) {
                $guests = $m[1];
                $childrens = $m[2];
            }
        }

        $h->booked()->guests($guests, false, true);

        if (empty($h->getGuestCount())) {
            $adultText = $this->http->FindSingleNode("//text()[normalize-space()='# of Adults']/ancestor::td[1]");

            if (preg_match("/{$this->opt($this->t('numberAdults'))}\s*(\d+)/su", $adultText, $m)) {
                $h->booked()
                    ->guests($m[1]);
            }
        }

        // Kids
        if ($childrens === null) {
            $childrens = $this->http->FindSingleNode("//text()[{$this->starts($this->t('numberChildren'))} or {$this->starts('Children:')}]/following::text()[normalize-space(.)][1]", null, true, '/^[ :]*(\d{1,3})$/');
        }

        if ($childrens === null) {
            $childrens = $this->http->FindSingleNode("//text()[{$this->eq('Children')}]/following::text()[normalize-space(.)][1]", null, true, '/^\d{1,3}$/');
        }

        if ($childrens === null) {
            $childrens = $this->http->FindSingleNode("//text()[{$this->contains('Children:')}]/ancestor::tr[1]", null, true, '/Children\s*:\s*(\d{1,3})\b/i');
        }

        $h->booked()->kids($childrens, false, true);

        // Rooms
        $roomsCount = null;

        foreach ((array) $this->t('rooms') as $p) {
            $phrases = [$p, strtoupper($p), ucwords(strtolower($p))];
            $roomsCount = $this->http->FindSingleNode("//text()[{$this->eq($phrases)}]/following::text()[normalize-space()][1]", null, true, '/^\d{1,3}$/')
                ?? $this->http->FindSingleNode("//text()[{$this->starts($phrases)}]/following::text()[normalize-space()][1]", null, true, '/^\d{1,3}$/');

            if ($roomsCount !== null) {
                break;
            }
        }

        $h->booked()->rooms($roomsCount, false, true);

        /*
         * Room
         */
        $r = $h->addRoom();

        // RoomType
        // RoomTypeDescription
        $roomType = $roomTypeDescription = null;

        foreach ((array) $this->t('roomType') as $phrase) {
            $roomTypes = array_filter($this->http->FindNodes("//text()[{$this->starts($phrase)}]/following::text()[normalize-space()][1][not(" . $this->contains(['Room Type', 'RATE NAME']) . ")][string-length(normalize-space(.)) > 5]", null, "/^[:\s]*(.{2,}?)[:\s]*$/"));

            if (count(array_unique($roomTypes)) === 1) {
                $roomType = array_shift($roomTypes);

                break;
            }
        }

        if ($roomType == 'DEPOSIT AMOUNT:') {
            $roomType = 'No description Room Type';
        }

        if (!$roomType) {
            $roomType = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Daily Rate")]/preceding::text()[normalize-space(.)][1]', null, true, '/^([A-z ]*Room[A-z ]*)$/i');
        }

        if (!$roomType) {
            $roomType = $this->http->FindSingleNode("//text()[{$this->starts('Accommodation:')}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1][{$this->contains($this->t('roomVariants'))} and string-length(normalize-space(.))<100]");
        }

        if (!$roomType) {
            $roomType = $this->http->FindSingleNode("//strong[starts-with(normalize-space(), 'Child(ren):')]/following::strong[{$this->contains($this->t('roomVariants'))}][1]");
        }

        if (!$roomType) {
            $roomType = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Description:'))}]/ancestor::td[1]", null, true, "/{$this->opt('Room Description:')}\s+([\w+\s+]+)\s+[-]/");
            $roomTypeDescription = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Description:'))}]/ancestor::td[1]", null, true, "/{$this->opt('Room Description:')}\s+[\w+\s+]+\s+[-]\s+(.+)[.]\s+Total\s+Stay/");
        }

        if (!$roomType) {
            $roomType = $this->http->FindSingleNode("//td[count(.//text()[normalize-space()]) = 2 and .//text()[{$this->eq($this->t('roomType'))}] and  .//text()[{$this->eq($this->t('ratePlan'))}]]/following-sibling::td[count(.//text()[normalize-space()]) = 2]/descendant::text()[normalize-space()][1]");
        }

        if (preg_match('/^(\w[-\w ]+?)\s*:\s*(.{3,})$/u', $roomType, $matches)) {
            // Penthouse: Your private residence amidst London's royal addresses.
            $roomType = $matches[1];
            $roomTypeDescription = $matches[2];
        }
        $roomDescTextNodes = $this->http->XPath->query("//tr[{$this->starts($this->t('roomAndRateDescription'))}]/following-sibling::tr[normalize-space(.)][1]/descendant::text()");
        $ratePlanFlag = false;

        foreach ($roomDescTextNodes as $roomDescTextNode) {
            if ($this->http->FindSingleNode('./ancestor::*[' . $xpathFragmentBold . ' and contains(normalize-space(.),"Rate Plan")]', $roomDescTextNode)) {
                $ratePlanFlag = true;

                continue;
            }
            $xpathFragment2 = './ancestor::*[' . $xpathFragmentBold . ' and normalize-space(.)]';
            $roomTypeValue = $this->http->FindSingleNode($xpathFragment2, $roomDescTextNode);

            if ($ratePlanFlag && (preg_match('/\bTwin/i', $roomTypeValue) || preg_match('/Room$/', $roomTypeValue))) {
                if (empty($roomType)) {
                    $roomType = $roomTypeValue;
                }

                if (($roomTypeDesc = $this->http->FindSingleNode($xpathFragment2 . '/following::text()[normalize-space(.)][1]', $roomDescTextNode))) {
                    $roomTypeDescription = $roomTypeDesc;
                }

                break;
            }
        }

        if (mb_strlen($roomType) > 200 && preg_match('/^(.+?)[,.;!?]\s*(.+)/', $roomType, $m)) {
            $roomType = $m[1];

            if (!$roomTypeDescription) {
                $roomTypeDescription = $m[2];
            }
        }

        if ($roomType) {
            $roomType = preg_replace('/^You have reserved a\s*(.+?)$/', '$1', trim($roomType, ',.;!? '));
        }

        $roomTypeArray = $this->http->FindNodes("//text()[normalize-space()='Room Type']/ancestor::tr[1]/descendant::td[last()]/descendant::text()[not(contains(normalize-space(), 'Points') or contains(normalize-space(), 'Room Type'))][normalize-space()]");
        $roomRateArray = $this->http->FindNodes("//text()[normalize-space()='Average Nightly Rate']/ancestor::tr[1]/descendant::td[last()]/descendant::text()[normalize-space()]", null, "/^(\S\s*[\d\.\,]+)/u");

        if (in_array('Number of Adults', $roomTypeArray) !== false) {
            $roomTypeArray = [];
        }

        if ($rooms = count($roomTypeArray) && count($roomTypeArray) > 1) {
            foreach ($roomTypeArray as $i => $roomTypeValue) {
                if ($roomTypeValue !== $roomType) {
                    $room = $h->addRoom();
                    $room->setType($roomTypeValue);

                    if (isset($roomRateArray[$i])) {
                        $room->setRate($roomRateArray[$i] . '  / day');
                    }
                }
            }
        } else { //it-200150623.eml
            $rateText = $this->http->FindSingleNode("//text()[normalize-space()='Rate Per Night']/ancestor::tr[1]/descendant::td[normalize-space()][2]");

            if (preg_match_all("/(\d+\/\d+\/\d{4}\s*[A-z\d\.\s\-]+)(?:\s|$)/u", $rateText, $match)) {
                $freeNight = 0;

                foreach ($match[1] as $rateValue) {
                    if (stripos($rateValue, 'Complimentary') !== false) {
                        $h->booked()
                            ->freeNights($freeNight++);
                    } elseif (preg_match("/^(\d+\/\d+\/\d{4}\s*[A-Z]{3}\s*[\d\.\,]+)/u", $rateValue, $m)) {
                        $r->setRate($m[1]);
                    }
                }
            }
        }

        if (empty($roomTypeDescription)) {
            if ($this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Description:'))}]/ancestor::td[1]") == 'Room Description:') {
                $roomTypeDescription = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Description:'))}]/ancestor::td[1]/following-sibling::td[1]");
            }
        }

        if (empty($roomType)) {
            $roomType = $this->http->FindSingleNode("//text()[(starts-with(normalize-space(.),'Room & Rate Description'))]/following::strong[{$this->contains($this->t('roomVariants'))}][1]");
            $roomTypeDescription = $this->http->FindSingleNode("//text()[(starts-with(normalize-space(.),'Room & Rate Description'))]/following::strong[{$this->contains($this->t('roomVariants'))}][1]/following::text()[normalize-space()][1]");
        }

        $r
            ->setType($roomType ?? null, false, true)
            ->setDescription($roomTypeDescription ?? null, false, true);
        // example: it-64512920.eml
        if ($roomType == 'Package Rate') {
            $h->removeRoom($r);
        }

        $xpathDigits = 'contains(translate(.,"0123456789","˚˚˚˚˚˚˚˚˚˚"),"˚")';

        $patterns['payment'] = '(?<currency>[^,.\'\d]+?)\s*(?<amount>\d[,.\'\d\s]*)'; // $2,743.32

        // Rate
        $rate = '';

        // Step 1: find range rate
        $rateText = implode(' ', $this->http->FindNodes("//tr[ descendant::text()[normalize-space()][1][{$this->eq('Nightly Rate')}] ]/descendant::text()[normalize-space()]"));
        $rateRange = $this->parseRateRange($rateText);

        if ($rateRange !== null) {
            $rate = $rateRange;
        }

        if (empty($rate)) {
            $rateText = implode(' ', $this->http->FindNodes("//text()[{$this->eq('Nightly Rate')} or {$this->eq('Rate')} or {$this->eq('Nightly Rate:')} or {$this->eq('Nightly Room Rate:')}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]/descendant::text()[normalize-space(.)]"));
            $rateRange = $this->parseRateRange($rateText);

            if ($rateRange !== null) {
                $rate = $rateRange;
            }
        }

        if (empty($rate)) {
            // it-23049698.eml
            $rateText = '';
            $rateRows = $this->http->XPath->query('//text()[' . $this->eq('Daily Rate Amount:') . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]/descendant::tr[ not(.//tr) and ./td[3] ]');

            foreach ($rateRows as $rateRow) {
                $rowDate = $this->http->FindSingleNode('*[1]', $rateRow);
                $rowCurrency = $this->http->FindSingleNode('*[2]', $rateRow);
                $rowAmount = $this->http->FindSingleNode('*[3]', $rateRow);

                if ($rowDate !== null && $rowCurrency !== null && $rowAmount !== null) {
                    $rateText .= "\n" . $rowCurrency . $rowAmount . ' from ' . $rowDate;
                }
            }
            $rateRange = $this->parseRateRange($rateText);

            if ($rateRange !== null) {
                $rate = $rateRange;
            }
        }

        if (empty($rate)) {
            $rateText = '';
            $rateRows = $this->http->XPath->query('//text()[' . $this->eq('Daily Rates') . ']/ancestor::tr[1]/following-sibling::*[ normalize-space(.) and ./td[2] ]');

            if ($rateRows->length === 0) {
                $rateRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Nightly Rate per Room:'))}]/following::node()[normalize-space()][1]/descendant-or-self::table[normalize-space()]/descendant::tr[not(.//tr) and normalize-space() and *[2] ]");
            }

            foreach ($rateRows as $rateRow) {
                $rowDate = $this->http->FindSingleNode('*[1]', $rateRow);
                $rowPayment = $this->http->FindSingleNode('*[2]', $rateRow);

                if ($this->normalizeDate($rowDate) !== null && $rowPayment !== null) {
                    $rateText .= "\n" . $rowPayment . ' from ' . $rowDate;
                }
            }
            $rateRange = $this->parseRateRange($rateText);

            if ($rateRange !== null) {
                $rate = $rateRange;
            }
        }

        // Step 2: find singleline rate
        if (empty($rate)) {
            $xpathFragment3 = '//text()[starts-with(normalize-space(),"Nightly Rate per Room:")]/following::table[1]';

            if (($rateText = $this->http->FindSingleNode($xpathFragment3 . '/descendant::tr[contains(normalize-space(),"Stay Date") and contains(.,"Rate")][1]/following-sibling::tr[1]/td[2]', null, true, '/(' . $patterns['payment'] . ')$/'))) { // it-3066565.eml
                $rate = $rateText . ' / night';
            } elseif (($rateText = $this->http->FindSingleNode($xpathFragment3 . '/descendant::tr[1]/td[2]', null, true, '/(' . $patterns['payment'] . ')$/'))) {
                $rate = $rateText;
            } elseif (($rateText = $this->http->FindSingleNode("//text()[normalize-space()='Nightly Rate:']/ancestor::tr[1]/descendant::td[2]", null, true, '/(' . $patterns['payment'] . ')$/'))) {
                $rate = $rateText;
            } elseif (
                ($paymentRate = $this->http->FindSingleNode("(//td[not(.//td) and {$this->starts($this->t('rateSingleline'))}]/following-sibling::td[normalize-space()][1][{$xpathDigits}])[1]", null, true, "/^({$patterns['payment']})\b\s*(?:\(Average Daily Rate\)|$)/i")) // it-32583006.eml, it-35410679.eml
                || ($paymentRate = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('rateSingleline'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathFragmentBold}])][{$xpathDigits}])[1]", null, true, "/^\s*({$patterns['payment']})\s*$/")) // it-8024881.eml
            ) {
                $rate = $paymentRate . ' / day';
            } elseif (($rateText = $this->http->FindSingleNode('//text()[normalize-space()="Rates"]/following::text()[normalize-space()][1][ not(ancestor::*[' . $xpathFragmentBold . ']) ]'))) {
                $rate = $rateText;
            } elseif (($rateText = $this->http->FindSingleNode('//td[starts-with(normalize-space(),"Room Rate")][1]/following-sibling::td[normalize-space()][1]'))) {
                $rate = $rateText;
            } elseif (($rateText = $this->http->FindSingleNode('//td[starts-with(normalize-space(),"Daily Room Rate")][1]/following-sibling::td[normalize-space()][1]'))) {
                $rate = $rateText;
            } elseif (($paymentRate = $this->http->FindSingleNode('//text()[normalize-space()="Daily Rate:"]/following::text()[normalize-space()][1][ not(ancestor::*[' . $xpathFragmentBold . ']) ]', null, true, '/(' . $patterns['payment'] . ')$/'))) {
                $rate = $paymentRate . ' / day';
            } elseif (($paymentRate = $this->http->FindSingleNode('//text()[starts-with(normalize-space(),"All Inclusive")]', null, true, "/All Inclusive\s+(.+?)$/"))) {
                $rate = $paymentRate;
            } elseif (($paymentRate = implode(', ', $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Rate per Room per Night')]/following::table[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Per night starting'))]")))) {
                $rate = $paymentRate;
            } elseif (($paymentRate = $this->http->FindSingleNode("//text()[{$this->eq(['Daily Rate Amount:', 'Nightly rate:'])}]/following::text()[string-length()>4][1]"))) {
                $rate = $paymentRate;
            }
        }

        if (empty($rate)) {
            $reservationNotesTexts = $this->http->FindNodes('//text()[' . $this->eq('Reservation Notes:') . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]/descendant::text()[normalize-space(.)]');
            $reservationNotesText = implode("\n", $reservationNotesTexts);

            if (preg_match('/at (' . $patterns['payment'] . ') per night/', $reservationNotesText, $matches)) {
                $rate = $matches[1] . ' / night';
            }
        }

        if (stripos($rate, 'Includes') !== false) {
            $rate = $this->http->FindSingleNode("//text()[normalize-space()='Daily Rate']/ancestor::p[1]/following::span[1][contains(normalize-space(), '-') and contains(normalize-space(), '/')]");
        }

        if (!empty($rate) && strpos($rate, 'Excluding') == false) {
            $r->setRate($rate);
        }

        // RateType
        $ratePlan = implode('; ',
            $this->http->FindNodes("//div[{$this->eq($this->t('ratePlan'))}]/following-sibling::*[normalize-space(.)!=''][1]//ul/li[normalize-space()!='']"));

        if (!$ratePlan) {
            $ratePlan = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('ratePlan'))}] ]/*[normalize-space()][2]", null, false, "/^\b[-,.;!\d\s[:alpha:]]+$/u")
                ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('ratePlan'))}]/following::text()[normalize-space(.)][1][ not(ancestor::*[{$xpathFragmentBold}]) ]");
        }

        if ($ratePlan) {
            $r->setRateType($ratePlan);
        }

        /*
         * Price
         */

        // Currency
        // Total
        $paymentTotal = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Cost with '))}]/following::text()[normalize-space(.)!=''][1]");

        if ($paymentTotal === null) {
            $paymentTotal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Cost Per Room:'))}]/following::text()[normalize-space(.)!=''][1]");
        }

        if ($paymentTotal === null) {
            $paymentTotal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/following::text()[normalize-space(.)!=''][1]");
        }

        if ($paymentTotal === null) {
            $paymentTotal = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('roomTotal'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1][{$xpathDigits}])[last()]");
        }

        if ($paymentTotal === null) {
            $paymentTotal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Estimated Total Charges:'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]");
        }

        if ($paymentTotal === null) {
            $paymentTotal = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Stay'))}]/ancestor::div[1]", null, true, "/{$this->opt('Total Stay')}[:]\s+(.+)/u");
        }

        if ($paymentTotal === null) {
            $paymentTotal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('roomTotal'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathFragmentBold}])]");
        }

        if ($paymentTotal === null) {
            $paymentTotal = $this->http->FindSingleNode("//text()[normalize-space()='Total Cost With Tax:']/following::text()[normalize-space()][1]");
        }

        if ($paymentTotal === null) {
            $paymentTotal = $this->http->FindSingleNode("//text()[normalize-space()='Total Cost without Taxes']/following::text()[normalize-space()][1]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Total Cost without Taxes'))}\s*(\D[\d\,\.]+)[\s\-]+/su");
        }

        if ($paymentTotal === null) {
            $paymentTotal = $this->http->FindSingleNode("//text()[normalize-space()='Total Stay Amount:']/following::text()[normalize-space()][1]/ancestor::td[1]", null, true, "/^\s*(\D[\d\,\.]+)/su");
        }

        if ($paymentTotal === null) {
            $paymentTotal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('roomTotal'))}]/following::text()[normalize-space()][1]", null, true, "/^[^:]*\d[^:]*$/");
        }

        if (preg_match('/' . $patterns['payment'] . '$/', $paymentTotal, $m)
            || preg_match('/^' . $patterns['payment'] . '/', $paymentTotal, $m) // $2,743.32 includes tax
            || preg_match("/^(?<amount>\d[,.'\d\s]*)\s+(?<currency>[^,.'\d]+?)$/", $paymentTotal, $m)
        ) {
            $currency = $this->normalizeCurrency($m['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $h->price()->currency($currency)->total(PriceHelper::parse($m['amount'], $currencyCode));

            if ($m['currency'] === '$') {
                $rates = $this->http->FindSingleNode("//div[{$this->eq($this->t('ratePlan'))}]/following-sibling::*[normalize-space()!=''][1][contains(.,'night')]");
                $dollar = preg_quote('$', '/');

                if (preg_match("/{$dollar}\s*\d[\d\.,]*\s+([A-Z]{3})$/", $rates, $mat)) {
                    $h->price()->currency($currencyCode = $mat[1]);
                }
            }

            $taxes = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('taxes'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/')
                ?? $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('taxes'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/')
                ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('taxes'))}]/following::text()[normalize-space()][1]", null, true, '/^.*\d.*$/');

            if (preg_match('/^' . preg_quote($m['currency'], '/') . '\s*(?<amount>\d[,.\'\d]*)\b/', $taxes, $matches)) {
                $h->price()->tax(PriceHelper::parse($matches['amount'], $currencyCode));
            }

            // Cost
            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cost'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, '/^.*\d.*$/')
                ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cost'))}]/following::text()[normalize-space()][1]", null, true, '/^.*\d.*$/');

            if (preg_match('/^' . preg_quote($m['currency'], '/') . '\s*(?<amount>\d[,.\'\d]*)\b/', $cost, $matches)
                || preg_match('/^(?<amount>\d[,.\'\d]*)\s*' . preg_quote($m['currency'], '/') . '$/', $cost, $matches)
            ) {
                $h->price()->cost(PriceHelper::parse($matches['amount'], $currencyCode));
            }

            // Discount
            $discount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Discount'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, '/^.*\d.*$/');

            if (preg_match('/^\-?\s?' . preg_quote($m['currency'], '/') . '\s*(?<amount>\d[,.\'\d]*)\b/', $discount, $matches)) {
                $h->price()->discount(PriceHelper::parse($matches['amount'], $currencyCode));
            }
        }

        // CancellationPolicy
        // Deadline
        $cancelPolicy = $this->http->FindSingleNode("//text()[contains(., 'Cancellations made') and contains(., 'days prior to the date of arrival will incure')]");

        if (!$cancelPolicy) {
            $cancelPolicy = $this->http->FindSingleNode("//text()[normalize-space()='Policies:']/following::em[1]");
        }

        if (!$cancelPolicy) {
            $cancelPolicy = $this->http->FindSingleNode("//p[{$this->eq('Cancellations:')}]/following-sibling::p[normalize-space()][1][ descendant::text()[normalize-space()][not(ancestor::*[{$xpathFragmentBold}])] ]");
        }

        if (!$cancelPolicy) {
            $cancelPolicy = $this->http->FindSingleNode("//div[{$this->starts('Cancellation:')}]", null, true,
                "/Cancellation:\s*(\S.{20,})/");
        }

        if (!$cancelPolicy) {
            $cancelPolicy = $this->http->FindSingleNode("//text()[{$this->starts('CANCELLATION POLICY')}]/ancestor::tr[1]/following-sibling::tr[1]");
        }

        if (!$cancelPolicy) {
            $cancelPolicy = $this->http->FindSingleNode("//text()[{$this->contains($this->t('cancellationPolicy'))}]/following::text()[string-length(normalize-space())>3][1][not(ancestor::*[$xpathFragmentBold] or {$this->contains(['VIEW ALL POLICIES', 'START DREAMING', 'HOW TO REACH US'])})]", null, true, '/^\s*:?\s*(.+)/s');
        }

        if (!$cancelPolicy) {
            $cancelPolicy = $this->http->FindSingleNode("//td[not(.//td)][{$this->eq($this->t('Cancellation Policy'))}]/following-sibling::td[normalize-space()][1]", null, true, '/^\s*Cancel.{25,}/');
        }

        if (!$cancelPolicy) {
            $cancelPolicy = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation Policy'))}]/ancestor::tr[1]/following::tr[1]", null, true, '/^(Cancellations.+tax fee.)/');
        }

        if (!$cancelPolicy) {
            $cancelPolicy = $this->http->FindSingleNode("//text()[{$this->starts('Please')} and {$this->contains('hour cancellation policy')}]");
        }

        if (!$cancelPolicy) {
            $cancelPolicy = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'If you find it necessary to cancel this reservation')]", null, true, '/(If you find it necessary to cancel this reservation, we require notification at least \d{1,2} hours prior to your arrival to avoid a charge for one night\'s room rate plus tax)/');
        }

        if (!$cancelPolicy) {
            $cancelPolicy = $this->http->FindSingleNode("//text()[contains(., 'Please cancel your reservation by') and contains(., 'hours prior to arrival to avoid any penalties')]");
        }

        if (!$cancelPolicy) {
            $cancelPolicy = $this->http->FindSingleNode("//text()[contains(., 'May be cancelled up to') and contains(., 'prior to arrival with no penalty')]");
        }

        if (!$cancelPolicy) {
            $cancelPolicy = trim($this->http->FindSingleNode("//text()[contains(., 'Reservations not cancelled within') and contains(., 'of arrival will be charged one')]"), '. ');
        }

        if (!$cancelPolicy) {
            $cancelPolicy = $this->http->FindSingleNode("//text()[contains(., 'If you should need to cancel your reservation')]");
        }

        if (empty($cancelPolicy)) {
            $cancelPolicy = $this->http->FindSingleNode("//text()[normalize-space()='Cancellation Policy:']/following::text()[normalize-space()][1]/ancestor::*[not(self::span) and not(self::strong)][1][not(contains(normalize-space(), 'Cancellation Policy:'))]");
        }

        if (!$cancelPolicy) {
            $cancelPolicy = $this->http->FindSingleNode("//text()[{$this->eq(['Cancellation Policy:', 'Cancellation policy'])}]/following::text()[normalize-space()][1]/ancestor::*[not(self::span) and not(self::strong)][1][{$this->starts(['Cancellation Policy:', 'Cancellation policy:'])}]",
                null, true, "/Cancellation Policy:\s*(.+)/i");
        }

        if (!$cancelPolicy) {
            $cancelPolicy = $this->http->FindSingleNode("//text()[normalize-space()='Policies']/following::text()[normalize-space()][1]");
        }

        if (!$cancelPolicy) {
            $cancelPolicy = $this->http->FindSingleNode("//text()[normalize-space()='Cancellation Policy:']/following::text()[string-length()>5][1]");
        }

        if (!$cancelPolicy) {
            $cancelPolicy = $this->http->FindSingleNode("//text()[normalize-space()='Cancellation Policy']/following::text()[string-length()>5][1]");
        }

        if ($cancelPolicy == 'cancellation') {
            $cancelPolicy = $this->http->FindSingleNode("//text()[{$this->starts($this->t('cancellationPolicy'))}]/ancestor::tr[1]/descendant::td[2]");
        }

        if ($cancelPolicy) {
            $h->general()->cancellation($cancelPolicy);

            if (preg_match("/^Non refundable booking[\s.;!]*$/i", $cancelPolicy)
                || preg_match("/All (?i)reservations must be guaranteed by valid credit card upon booking\. Non-Refundable/", $cancelPolicy)
                || preg_match("/No (?i)revisions and\/or refunds or credits will be issued for early departure, cancell?ation or failed arrival/", $cancelPolicy)
                || preg_match("/For (?i)this reservation we cannot guarantee a refund(?:\s*\(full or partial\))? upon cancell?ation\./", $cancelPolicy)
            ) {
                $h->booked()->nonRefundable();
            } elseif (preg_match("/cancell? the reservation [\w ]+? by (?<hour>{$patterns['time']}), (?<prior>\d{1,3} \w+) prior to arrival to avoid a cancell?ation charge/i", $cancelPolicy, $m) // en
                || preg_match("/If the reservation is not cancell?ed by (?<hour>{$patterns['time']}) (?<prior>\d{1,3} \w+) prior to arrival, one night's stay/i", $cancelPolicy, $m) // en
                || preg_match("/Cancellations are allowed free of charge up to (?<prior>\d{1,3} \w+) before the arrival date at (?<hour>{$patterns['time']}) local time/i", $cancelPolicy, $m) // en
            ) {
                $h->parseDeadlineRelative($m['prior'], $m['hour']);
            } elseif (
                preg_match("#to cancel [\w ]+? this reservation, please do so before (\d+:\d+[ ]*[ap]m) GMT[+\-;\d]* one day prior to arrival#i", $cancelPolicy, $m) // en
                || preg_match("#Cancellations must be made before (\d+:\d+[ ]*[ap]m) GMT\, prior to day of arrival#i", $cancelPolicy, $m) // en
            ) {
                $h->parseDeadlineRelative("1 day", $m[1]);
            } elseif (
                preg_match("/must be made (?<prior>\d+ hours?) prior to guests' arrival to avoid a penalty/i", $cancelPolicy, $m) // en
                || preg_match('/You may cancel this reservation up to (?<prior>\d{1,2} days?) prior to arrival with no penalty/i', $cancelPolicy, $m) // en
                || preg_match('/May be cancelled up to (?<prior>\d{1,2} days?) prior to arrival with no penalty/i', $cancelPolicy, $m) // en
                || preg_match("/Any reservation cancellation or changes to date of arrival less than (?<prior>\d+ hours?) prior to arrival will forfeit \d+ night's room deposit/i", $cancelPolicy, $m) // en
                || preg_match('/Free cancellation (?<prior>\d+ ?hrs?) prior to arrival./i', $cancelPolicy, $m) // en
                || preg_match('/All modifications \/ cancellations must be made (?<prior>\d{1,2} days?) prior to the arrival date to avoid forfeiture of/i', $cancelPolicy, $m) // en
                || preg_match('/The property standard cancellation policy is (?<prior>\d{1,2} hours)/i', $cancelPolicy, $m) // en
                || preg_match('/we require notification at least (?<prior>\d{1,2} hours) prior to your arrival to avoid a charge for one night\'s room rate plus tax/', $cancelPolicy, $m)
                || preg_match('/Cancellation is required (?<prior>\d{1,2} hours?) prior to arrival to avoid a charge/i', $cancelPolicy, $m)
                || preg_match('/Cancellations made (?<prior>\d{1,2} days?) prior to the date of arrival will incure 1 nights charge/i', $cancelPolicy, $m)
                || preg_match('/it is refundable if we receive a cancellation at least (?<prior>\d+ days?) prior to the scheduled arrival/i', $cancelPolicy, $m)
                || preg_match('/Cancellation of your reservation must be made (?<prior>\d+ days?) prior to arrival to avoid/i', $cancelPolicy, $m)
            ) {
                $m['prior'] = preg_replace('/^(\d+)\s*hrs?$/i', '$1 hours', $m['prior']);
                $h->parseDeadlineRelative($m['prior'], '00:00');
            } elseif (
                preg_match("#By (\d+:\d+[ ]*[ap]m) [A-Z]{3} on day prior to your arrival#i", $cancelPolicy, $m) // en
                || preg_match("#before (\d+:\d+\s*[ap]m) the days? before your intended arrival in order to obtain a deposit refund#i", $cancelPolicy, $m) // en
                || preg_match("#Cancel by (\d+:?\d+\s*[ap]m) 24 hours prior to arrival, local hotel time, to avoid a one night cancellation fee#i", $cancelPolicy, $m) // en
                || preg_match("#Cancellations must be made by (\d+\s+a?p?.m.) local time 24 hours prior to arrival to avoid a 1 night plus tax fee.#i", $cancelPolicy, $m) // en
                || preg_match("#Reservation must be cancelled by (\d+\s*[ap]m), 24 hours prior to arrival to avoid penalty or forfeiture of deposit.#i", $cancelPolicy, $m) // en
                || preg_match("#(\d+\s*[ap]m) the day prior to arrival#i", $cancelPolicy, $m) // en
                || preg_match("#Cancellations and changes must be received prior to (\d+\s*[ap]m) Geneva time one day prior#i", $cancelPolicy, $m) // en
            ) {
                $h->parseDeadlineRelative("1 day", $m[1]);
            } elseif (
                preg_match("#To avoid a charge of one night’s room and tax to your credit card please notify our office by\s*(?<hour>{$patterns['time']})\s*MST\s*(?<prior>\d+\s*hours?)\s*prior to your arrival#i", $cancelPolicy, $m) // en
            ) {
                $h->parseDeadlineRelative($m['prior'] . ' -1 day', $m['hour']);
            } elseif (
                preg_match("#To avoid a charge of one night room and tax, cancellation is required by (?<time>{$patterns['time']}) [A-Z]{3,4} on (?<date>.+)#i", $cancelPolicy, $m) // en
                || preg_match("#Cancelling your reservation before (?<time>{$patterns['time']}) \(local hotel time\) on \w+,\s*(?<date>.+?) will result in no charge\.#i", $cancelPolicy, $m) // en
            ) {
                $h->booked()->deadline(strtotime(preg_replace('/\s*,\s*/', ' ', $m['date']) . ', ' . $m['time']));
            } elseif (
                preg_match("#Reservation must be cancelled by (?<date>[a-z]+ \d{1,2})(?:st|th|nd) to avoid deposit penalty\.#i", $cancelPolicy, $m) && !empty($h->getCheckInDate()) // en
                || preg_match("#Cancellation or changes after (?<date>.+), will result in a full forfeiture of payment\.#i", $cancelPolicy, $m) && !empty($h->getCheckInDate()) // en
            ) {
                $h->booked()->deadline(EmailDateHelper::parseDateRelative($m['date'], $h->getCheckInDate(), false));
            } elseif (
                preg_match("#^Cancellations must be received (?<prior>\d+) days prior to the day of arrival to avoid penalty charges#i", $cancelPolicy, $m) && !empty($h->getCheckInDate()) // en
                || preg_match("#^Reservation may be cancelled (?<prior>\d+) days prior arrival with no penalty \(deposit will be refunded\)#i", $cancelPolicy, $m) && !empty($h->getCheckInDate()) // en
                || preg_match('/Deposits are refundable if the reservation is canceled more than (?<prior>\d+) days? prior to the arrival date/iu', $cancelPolicy, $m)
                || preg_match("#Notice of cancellation should be received fourteen \((?<prior>\d+)\) days prior to arrival date#i", $cancelPolicy, $m)
                || preg_match("#For cancellations made more than (?<prior>\d+) days prior to scheduled arrival date, you will receive a full refund of your advanced deposit#i", $cancelPolicy, $m)
                || preg_match("#(?<prior>\d+) days\’ notice prior to the arrival date#i", $cancelPolicy, $m)
                || preg_match("#no later than \w+ \((?<prior>\d+)\) days prior to your arrival date#i", $cancelPolicy, $m)
            ) {
                $h->booked()->deadlineRelative($m['prior'] . ' days', '00:00');
            } elseif (
                preg_match("#^The reservation must be cancelled by (?<time>.+?), .+? days \((?<priorHours>\d+) hours\) before the scheduled arrival date otherwise a penalty#i", $cancelPolicy, $m) && !empty($h->getCheckInDate()) // en
                || preg_match("#^Cancel within (?<priorHours>\d+) hours by (?<time>.+?) local hotel time prior to arrival to avoid penalty#i", $cancelPolicy, $m) && !empty($h->getCheckInDate()) // en
                || preg_match("#Cancellation of your reservation must be made by (?<time>.+?)\, (?<priorHours>\d+) hours prior to arrival to avoid#ui", $cancelPolicy, $m) && !empty($h->getCheckInDate()) // en
                || preg_match("#Your reservation can be cancelled free of charge until (?<time>.+?) local time (?<priorHours>\d+) hours prior to your arrival date#ui", $cancelPolicy, $m) && !empty($h->getCheckInDate()) // en
            ) {
                $h->booked()->deadlineRelative($m['priorHours'] . ' hours', $m['time']);
            } elseif (
                preg_match("#^Guest room reservations can be canceled without penalty until (?<time>\d+\s*[ap]m),? (?<prior>\d+) days? prior to your scheduled arrival.#i", $cancelPolicy, $m) && !empty($h->getCheckInDate())
            || preg_match("#^Free of charge if cancelled before (?<time>\d+\s*[ap]m),? (?<prior>\d+) days? prior to arrival.#i", $cancelPolicy, $m) && !empty($h->getCheckInDate()) // en
            || preg_match("#^Cancel before (?<time>\d+\s*[ap]m) local time, (?<prior>\d+) days prior to arrival to avoid a charge of one night.#i", $cancelPolicy, $m) && !empty($h->getCheckInDate()) // en
            ) {
                $h->booked()->deadlineRelative($m['prior'] . ' days', $m['time']);
            } elseif (preg_match('/The deposit is fully refundable upon notice of cancellation at least (\d{1,2} hours) prior to the arrival date/', $cancelPolicy, $m)
            || preg_match('/Cancellations or modifications are applicable free of charge until (\d+\s*a?p?m?) day of arrival/', $cancelPolicy, $m)) {
                $h->booked()->deadlineRelative($m[1]);
            } elseif (preg_match('/Cancel (?<prevHour>\d+) hours prior to (?<time>\d+[AP]M) day of arrival local hotel time to avoid penalty charges/i', $cancelPolicy, $m)) {
                $h->booked()->deadlineRelative("{$m['prevHour']} hours", $m['time']);
            } elseif (preg_match("#Reservations must be cancelled (\d+) hours prior to the arrival date to avoid a penalty of one night#i", $cancelPolicy, $m)
            || preg_match("#^\s*(\d+) hours prior to arrival#i", $cancelPolicy, $m)) {
                $h->booked()->deadlineRelative($m[1] . ' hours');
            }
        }
        $findDeadline = false;
        $deadlineText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Cancellation must be received'))}]");

        if (preg_match("#Cancellation must be rec[ei]{2}ved before (?<hour1>\d+)\s*(?<hour2>[ap]m) (?<prior>\d+ days) prior to your arrival date to avoid [\w\-]+ penalty#i", $deadlineText, $m)
            || preg_match("#Cancellation must be rec[ei]{2}ved (?<prior>\d+ hours?) prior to (?<hour1>\d+)\s*(?<hour2>[ap]m) to avoid [-\w ]+ penalty#i", $deadlineText, $m)
        ) {
            $h->parseDeadlineRelative($m['prior'] . ' -1 day', $m['hour1'] . ':00' . $m['hour2']);
            $findDeadline = true;
        } elseif (preg_match("/This (?i)reservation may be cancell?ed on or before ([[:alpha:]]{3,}\s+\d{1,2}\s*,\s*\d{4}) - \d{1,3} Days Prior to Arrival/", $deadlineText, $m) // en
        ) {
            $h->booked()->deadline(strtotime($m[1]));
            $findDeadline = true;
        } elseif (preg_match("/Reservations must be cancelled (\d+)\-hours prior to arrival to avoid a penalty of one night's rate and tax./", $deadlineText, $m) // en
        ) {
            $h->setCancellation($deadlineText);
            $h->booked()->deadlineRelative($m[1] . ' hours');
            $findDeadline = true;
        }

        if (!$findDeadline) {
            $deadlineText = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Refundable, cancelable until ')]");

            if (preg_match("#Refundable, cancelable until (\d+)\s*([ap]m) local hotel time the day before arrival, after that#i", $deadlineText, $m)
           ) {
                $h->parseDeadlineRelative("1 day", $m[1] . ':00' . $m[2]);
                $findDeadline = true;
            }
        }

        if (empty($findDeadline) && !empty($checkOutDate)) {
            $deadlineText = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'A cancellation is free of charge if done')]");

            if (preg_match("#A cancellation is free of charge if done (\d+) days prior to arrival\.#i", $deadlineText, $m)
            ) {
                $h->booked()->deadline(strtotime(date('d.m.Y', strtotime($checkOutDate . ' -' . $m[1] . ' day'))));
                $findDeadline = true;
            }
        }
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     */
    private function normalizeDate(?string $string): ?string
    {
        // Sep. 25th, 2018
        // Saturday, March 28, 2020
        if (preg_match('/([^\d\W]{3,})[.\s]+(\d{1,2})[A-z]*[,\s]+(\d{4})$/u', $string, $matches)) {
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/(\d+)\/(\d+)\/(\d{2})$/', $string, $matches)) {
            $month = $matches[1];
            $day = $matches[2];
            $year = '20' . $matches[3];
        } elseif (preg_match('/(\d+)\/(\d+)\/(\d{4})$/', $string, $matches)) {
            if ($matches[1] > 12) {
                $day = $matches[1];
                $month = $matches[2];
            } else {
                $month = $matches[1];
                $day = $matches[2];
            }
            $year = $matches[3];
        } elseif (preg_match('/[^\d\W]{2,}\s*,\s*([^\d\W]{3,})\s+(\d+)\s*,\s*(\d{4})$/u', $string, $matches)) {
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/\b(\d{1,2})\s+([^\d\W]{3,})[,\s]+(\d{4})$/u', $string, $matches)) { // Saturday, 6 October, 2018
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^(\d+)\/(\d+)\/(\d{4})\s*(?:at|by)\s*(\d+a?p?m)$/u', $string, $matches)) {
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
            $time = $matches[4];
        } elseif (preg_match('/^(\w+)\s*(\d+)\,\s*(\d{4})\s*$/u', $string, $matches)) { // August 21, 2022
            $day = $matches[2];
            $month = $matches[1];
            $year = $matches[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                if (isset($time)) {
                    return $m[1] . '/' . $day . ($year ? '/' . $year : '') . ', ' . $time;
                } else {
                    return $m[1] . '/' . $day . ($year ? '/' . $year : '');
                }
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            if (isset($time)) {
                return $day . ' ' . $month . ($year ? ' ' . $year : '') . ', ' . $time;
            } else {
                return $day . ' ' . $month . ($year ? ' ' . $year : '');
            }
        }

        return null;
    }

    private function normalizeTime(string $string): string
    {
        if (preg_match('/^(?:12)?\s*noon$/i', $string)) {
            return '12:00';
        }

        if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', $string, $m) && (int) $m[2] > 12) {
            $string = $m[1];
        } // 21:51 PM    ->    21:51
        $string = preg_replace('/^(0{1,2}[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', '$1', $string); // 00:25 AM    ->    00:25
        $string = preg_replace('/(\d)[ ]*-[ ]*(\d)/', '$1:$2', $string); // 01-55 PM    ->    01:55 PM
        $string = str_replace(['午前', '午後'], ['AM', 'PM'], $string); // 10:36 午前    ->    10:36 AM

        return $string;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'SEK' => ['Kr'],
            'EUR' => ['€'],
            'BRL' => ['R$'],
            '$'   => ['$'],
            'USD' => ['USD $', 'US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function parseRateRange(?string $string): ?string
    {
        // $239.20 from August 15    |    $650.00 on May 29     |   $339.00 on November 29, 2019
        $ptrn1 = '/(?:^\s*|\b\s+)(?<currency>[^\d\s]\D{0,2}?)\s*(?<amount>\d[,.\d\s]*)\s+(?:from|on)\s+\b/i';
        // 11/10/2019 $119.70    |    Jan 23 $352.00    |    Jan 24 - Jan 25 $392.00
        $ptrn2 = '/(?:\d+\/\d{4}|(?:[-\s]+[[:alpha:]]{3,}\s+\d{1,2}){1,2})\s+(?<currency>[^\d\s]\D{0,2}?)\s*(?<amount>\d[,.\d]*)\b/iu';

        if (preg_match_all($ptrn1, $string, $rateMatches) || preg_match_all($ptrn2, $string, $rateMatches)) {
            if (count(array_unique($rateMatches['currency'])) === 1) {
                $currency = array_shift($rateMatches['currency']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $rateMatches['amount'] = array_map(function ($item) use ($currencyCode) {
                    return (float) PriceHelper::parse($item, $currencyCode);
                }, $rateMatches['amount']);
                $rateMin = min($rateMatches['amount']);
                $rateMax = max($rateMatches['amount']);

                if ($rateMin === $rateMax) {
                    $amount = array_shift($rateMatches['amount']);

                    return number_format($amount, 2, '.', '') . ' ' . $currency . ' / night';
                } else {
                    return number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.', '') . ' ' . $currency . ' / night';
                }
            }
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }
}
