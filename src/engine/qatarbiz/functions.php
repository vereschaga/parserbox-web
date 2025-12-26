<?php

require_once __DIR__ . '/../qmiles/functions.php';

class TAccountCheckerQatarbiz extends TAccountCheckerQmiles
{
    protected $provider = 'qatarbiz';
    protected $formUrl = 'https://www.qatarairways.com/en/corporate-travel/loginpage.html?activityCode=SME';
    protected $j_currentPage = '/content/global/en/corporate-travel/loginpage';
    protected $rewardsPageUrl = 'https://www.qatarairways.com/en/corporate-travel/postLogin/dashboardsmeuser.html';

    public function Parse()
    {
        //$this->http->GetURL("https://www.qatarairways.com/en/corporate-travel/postLogin/dashboardsmeuser.html");

        if (empty($this->State['body'])) {
            return;
        }
        $response = $this->State['body'];
        $this->logger->notice("get new basicInfo, otherInfo");
        /**
         * should return
         * response '{"status":true}'
         * set new cookies: basicInfo, otherInfo.
         */
        $data = [
            "customerProfileId" => $response[0]->customerProfileId,
            "t"                 => $this->http->getCookieByName('QRTOKEN'),
            "programCode"       => $response[0]->programCode,
            "activity-code"     => "SME",
            "j_destination"     => "/content/global/en/destinations/repository",
            "j_alldestination"  => "/content/global/en/destinations",
        ];
        $headers = [
            'Accept'           => '*/*',
            'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer'          => 'https://www.qatarairways.com/en/corporate-travel/loginpage.html?activityCode=SME',
        ];
        $this->http->PostURL("https://www.qatarairways.com/qr/getAdditionalInfo", $data, $headers);
        $this->http->JsonLog();

        $this->logger->notice("decrypt new basicInfo, otherInfo");
        $this->getUserInfo();

        if (empty($this->State['body'])) {
            return;
        }
        $response = $this->State['body'];

        if (!empty($response[0])) {
            $last = $response[0];
        }

        if (!isset($last->qrewardsAmount, $last->lastName, $last->dealCode)) {
            $this->logger->error("something went wrong");

            return;
        }

        if (isset($last->qpointsAmount) && $last->qpointsAmount > 0) {
            $this->sendNotification('refs #17827 - QPoints // MI');
        }

        // QRewards
        $this->SetBalance($last->qrewardsAmount);
        // CompanyName
        $this->SetProperty('CompanyName', $last->lastName);
        // Membership Number:
        $this->SetProperty('Number', $last->dealCode);
        // Tier
        $this->SetProperty('Status', $last->tier);
        // Tier Validity
        $this->SetProperty('StatusValidity', date('d F Y', strtotime($response[1]->tierExpiry, false)));
        // Spent USD
        $this->SetProperty('SpentUSD', $last->flownRevenue ?? null);
        // Expiring balance - QRewards
        $this->SetProperty("ExpiringBalance", $last->qrewardsExpiryAmount ?? null);
        // refs #17827#note-8
        if (
            isset($last->qrewardsExpiryAmount, $last->qrewardsExpiryDate)
            && $last->qrewardsExpiryAmount > 0
            && ($exp = strtotime($last->qrewardsExpiryDate, false))
        ) {
            $this->SetExpirationDate($exp);
        }

        // Lounge Pass
        $this->http->PostURL('https://eisffp.qatarairways.com/ffp-services/profileService/getCustomerBenefits', json_encode([
            'customerProfileId' => $last->customerProfileId,
            'ffpNumber'         => $last->ffpNumber,
            'programCode'       => $last->programCode,
        ]), [
            'Accept'        => 'application/json, text/plain, */*',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->http->getCookieByName('QRTOKEN'),
        ]);
        $response = $this->http->JsonLog();

        foreach ($response->currentBenefits as $item) {
            if (isset($item->benefitCode, $item->validTo) && $item->benefitCode == 'LNGPASS') {
                $this->AddSubAccount([
                    "Code"           => "qmilesLoungePass",
                    "DisplayName"    => "Lounge pass(es)",
                    "Balance"        => $item->balanceCount,
                    'ExpirationDate' => strtotime('+1 hour', strtotime($item->validTo)),
                ]);
            }
        }
    }
}
