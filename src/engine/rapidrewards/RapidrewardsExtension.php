<?php

namespace AwardWallet\Engine\rapidrewards;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\ConfNoOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithConfNoInterface;
use AwardWallet\ExtensionWorker\LoginWithConfNoResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\RetrieveByConfNoInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class RapidrewardsExtension extends AbstractParser implements LoginWithIdInterface, LoginWithConfNoInterface, RetrieveByConfNoInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.southwest.com/loyalty/myaccount/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $loginFieldOrLogOut = $tab->evaluate('//div[@id="pageContent"]//form//input[@id="username"]  | //span[contains(text(), "RR#")]
        | //ul/li/button[contains(text(),"Log out")]');

        return strtoupper($loginFieldOrLogOut->getInnerText()) == 'LOG OUT' || $loginFieldOrLogOut->getNodeName() == 'SPAN';
    }

    public function getLoginId(Tab $tab): string
    {
        $accountNumber = $tab->findText('//span[@class="accountNumber"]/span[contains(text(),"RR#")]', FindTextOptions::new()->nonEmptyString()->preg('/#\s*(\w+)$/'));
        $this->logger->debug("Account Number: $accountNumber");

        return $accountNumber ?? '';
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//ul/li/button[contains(text(),"Log out")] | //button[contains(text(), "Log out")]', EvaluateOptions::new()->visible(false))->click();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        // sleep(1);
        $login = $tab->evaluate('//div[@id="pageContent"]//form//input[@id="username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//div[@id="pageContent"]//form//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//div[@id="pageContent"]//form//button[@type="submit"]')->click();

        $errorOrLogOut = $tab->evaluate('//div[contains(@class,"errorMessage__")] | //ul/li/button[contains(text(),"Log out")] | //span[contains(text(), "RR#")]');

        if (strtoupper($errorOrLogOut->getInnerText()) == 'LOG OUT' || $errorOrLogOut->getNodeName() == 'SPAN') {
            $this->logger->info('logged in');

            return new LoginResult(true);
        } else {
            $this->logger->info('error logging in');
            $error = $errorOrLogOut->getInnerText();

            if (str_contains($error, "The username/account number and/or password are incorrect.")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (str_contains($error, "We are unable to process your request. Please try again. If you continue to have difficulties, please contact a")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error, null, null);
        }
    }

    public function getLoginWithConfNoStartingUrl(array $confNoFields, ConfNoOptions $options): string
    {
        /*
        return 'https://www.southwest.com/air/manage-reservation/';
        */
        // this link leads to the desktop version if you run it from mobile
        return 'https://www.southwest.com/air/manage-reservation/view.html?searchToken=nN01Zj6G83RBcEaHgJ88hslPTBmI1O3Jso1Mv-dDq2DL6Zknhpm_SNd1i5hi5Kv3s_OBIKioNisDmXPsjDjDRM_XotTR__XICayFX4mJG2z-YEoiiPA4AVqe9roXs2UhfasQkRM8L3ZdnUEn2Q%3D%3D';
    }

    public function loginWithConfNo(Tab $tab, array $fields, ConfNoOptions $options): LoginWithConfNoResult
    {
        $tab->evaluate('//input[@id="confirmationNumber"] | //input[@name="recordLocator"]')->setValue($fields['ConfNo']);
        $tab->evaluate('//input[@id="passengerFirstName"] | //input[@name="firstName"]')->setValue($fields['FirstName']);
        $tab->evaluate('//input[@id="passengerLastName"] | //input[@name="lastName"]')->setValue($fields['LastName']);
        $tab->evaluate('//button[contains(@id, "submit-button")] | //div[@class="segment"]/button')->click();
        $loginResult = $tab->evaluate('//li[contains(@class, "error-message") and not(contains(text(), "Please enter the information below to continue"))] | //div[contains(@class, "swa-g-error")] | //span[contains(@class, "confirmation-number") and contains(@class, "code")] | //div[contains(@class, "passenger-record-locator")]');

        if (
            $loginResult->getNodeName() == 'LI'
            || $loginResult->getNodeName() == 'DIV' && strstr($loginResult->getAttribute('class'), "swa-g-error")
        ) {
            return LoginWithConfNoResult::error($loginResult->getInnerText());
        }

        return LoginWithConfNoResult::success();
    }

    /*
    public function retrieveByConfNo(Tab $tab, Master $master, array $fields, ConfNoOptions $options): void // works only for desktop
    {
        $options = [
            'method'      => 'POST',
            'mode'        => "cors",
            'credentials' => 'include',
            'headers'     => [
                'Content-Type'         => 'application/json',
                'Accept'               => 'application/json',
                'x-api-key'            => 'l7xx944d175ea25f4b9c903a583ea82a1c4c',
                'x-app-id'             => 'air-manage-reservation',
                'x-channel-id'         => 'southwest',
                'x-user-experience-id' => '00a93f87-c960-4c54-8fea-593aed8761ab',
                'ee30zvqlwf-a'         => 'tr3=8SM0WNPzO_f80fM2soZiLybz_ySr9_JXJag_8VH88d5Mi1dc7Z9Ssp2Zuf_4NfpW1j4=bx=iAD51fT1jaZp8QrD__fNU137m_Qs2aRBJ=HJP9sxU47xcQwmy4q8bZYTX5n0lu4euHqhwVkA5dBAl_wmr1cEaXdojzc_AYk8xm5KfCwXYOyj-2dhVHsVT_89_-2aYSFg7FDrvRmjUXexDkTr8ifAuuOz2Cjj6aK4MK-Ws9dOoqQ9NyJOnzu8j6Bd4XE1-DfYMmJn77024qmo_Qx3PfhsMLfkQVDQrn3vpXHO48gZdsY5-278bFPMPdHtlObShbCWptlTohWuoYQRbZwMbNB7FfROVhDoRpPZYkVmV7qseFvJmWeqlgWfsD4upus5uzD0eDk1eBdqRbElaSZo8moh4=2JuFzBthmr8372rC5gxKXaomrT1oV0uAMO-qcvmcjjiNnBjFEKnS-1oo5HCKeVOfwr--CANsMfToOuHxCT-ZXg=yjmn7QNPHcY9WBw1kE9ibYflQNv_VkpFM-Y5AgZtiTO9mwbY0Ck4CVgcmU6hJvcodQOHZwO4WFQi2oLjv7PAPo4o2mEpr21dWtvissjoHWLXohtUqePaayfZanWfpSRwm=bkBTDuuwJDfcYrOxe4XzDf6HxR1PHaS_gghjY=bsMQS4R0vC-FtD17BG9koudrstrB7csBXiTwpdCDgoB=UgghTeqjCjrqUm_Wwz_zqVrkYHl4YoL1AbVnXU6j611oR5=y=WpUkjhksdBDkNSO-YQ7W0_qEVvgis2p0aDJNJgyMKw=QXoUQ1uja2v2XV6ffrv8tOd3VRFBYj_u2UkQ4lYUy9_YWvZES4ZU3X4PC4kA_NdQ28_ANlA4UaE=_SVGS8PGl9EA8sFTExOty4tUdiq4Hdr_NB6sX38zN960gC-5VACMZ0L2AKYVT6xnfwBG-8tNvDAlgz4xpqr5NqwVOm5p00U4qSxY3_oM00ptALT7x6LnzJ5mAhNkpx-G2zmxJ6SKzkY4pjtLU=n3_OufU8avDd6K1zVCV7xDqwTCLp_1J1ywHQ5oLnyVp1U=bOLY3b8jiciNNsl1sUFg56Y4ktLMCoLb34vxgQ-rfyfm0smla007zYt3HTiuhJmAwsdRzvJ8dtT5xxPJDGYPjtRR8mFAuockjfO8gqzXr3xkVTLQBk8fEFrqUBwwPUAebw13e2_x8ZDomae=bNfEirN2CHJk3EKvYZv8u2y2PxEVTNw6o=7TKCRS9QNTegEyuLY8K2ow6F30P_0QoHz5XlBzP_EJVZr9Gpd7BC2dEwSzsLYpRonJtjJl8h6TMzBSCNgde=hCyC3fkuwe-PcSplnbht9wdHh6cy_298yhd0m1uyo7ukSn6WgREclSxuoUwGEG7osePdR7QRdEYnvEkfmcTygpgjdleVcVot1Gcs3jDcg7YTh__vcjA2groS4yVp8uV03ACbv=sDcjasrrykmzviji6=k4Ths5uqzCHt_gKaeRmpFObfyzBb7CPCDWmiGH6N0pAoahhzcwGh4cqTQzNCvnUTZ7ktoQp37BKeWvieP=s8W9xAhbhL7DZu_177-qsUBoqX5RQGzXEFkkx7VpC8uGhl166a=dDlxRPGeYRKzwpsXV7XXL-aoRvOzz4HMn6k09We5iHen=KrQoWVQjgUlXYeMb9tvXADm-TMe3MU6eyfG8dYvZz1yvhVq5UmB5MrWEXaeCx_YFAXnnJMH_1BoUbpnHP3-yTKdwZNrpD-v3qrimxmUHShsx5PSL=vhhuu21JPs6RtlXVrZ-SjpdRFG_JxKHPBxY-J0sr60jgtwc-5NkzqDuxk04ZVPmJ0QVx0sA=KqvL4xSMOR_FuX-WBS4BifNd5vDJcEUS--fdnHAwhLQRjw4CzLVcfszCGgAomPjqnh9jBty4ppvpL7jALwhOmuSGbTed_BNuskYlVrba7MZQO2Mj3ePikKpbxlOUXwtSBzQbHgvdK7eZqYS0m3c_SXA4N08bXmTk5xM6EnJ2ss1x9kUlrt3fxggkYzWj-X-3Q25hJlRT6JeAuXocyqpBU07vlWCmhWn8ba4FCNokS7HKSK_0KmFC1rhB96QsTtkDd5vl2t_CSzSHR3KY9Uqk919EoJLdJjCaEs-4_4fGEbR=w0Jfhy-rMoNMG2fYwesYunZ_TscwcS3FVvU5pCt6RedJdkwovs-TpHKji2OG_p_vGdSRopDdwdAxjPoFKnBpdR4KNpe=GkNlcdqF-tAu-7b=ieE7L-S5t-nLD6A_HWuwgVBNsy6321_yq2F1kK7znxHOllS-gvbUX-cTt0VJvqfwZnEMjyGTmR5xLePLd6rbsh1vwjDS6vA30qoAyiRoK=uKlYKO9fpUzZP1c3qM7U3JrhmtolvKDDHxO2sraz4dUdxD68HqoMWXmgiiiPzuPANOpOMrPVcxvi-5m24UDcVgEl-9UWiqs4O6RMDkye-RTflOmh7rD_m6PR75mHYpFzStX5l42oyVjp1eoy7k93ysnxSjgH1vG_x3-zTOSO3W_mv5xl99bAMtbjSrHjlfCEnJsYDpphKpp_-Pe1mJuXpz5OjTnNAPz5rB_TDu0ksedSOEt8SnSkOvSAoTRyokZ_qN5KhQGUumlbZj2WnAczQ=zUhZASs_UA8Fr-4ojk8HWnUTpv9ZpXUEw-AbqPeJObqSCCu8xzTnQ87L308cJFYdQ2NVC12_tD_xeje9BeqFV1MBYla5eYuAX5OE144=lpZL1e1_R-6fgysRBdOQcW4tohJkVurP=-30nRhuMhsRcGbWZLA3ROC12ByHojHiU08fp6WhYj3q_PA33AVc9nD9Mr=lwAfG36eEWAqfWEl2qgOckXMeu8X0Nh9CA=D-ZhUfFUYDtUp_YUw1sKU58pXu5aDoogNn=R=wPGnqAPwrZde15xT=q5OyiticYX2iPRqeR3qVm0=KaFruNQBkhyzMZXZSNxCSmJJAew4eYYQw4=rvHZkKuU0lqnSK_l9fteaHlC-kVpOMqVNzrhGcdg3sxaOTJHQJwO=jcv7_2sd5oipy6E7HCKYnVD_hotaqR2eT_v47Uf-02UW24=OYJBHovnk0q_q2abqzokUk=8RUHnEemnt5YZ1uEordy4Oytbx9RA4aRd9j8Tstd07Z=3y6U_J9D1fOhvftPB2WKwV2T3ncUf938ZHToXdqfcXdAjGXYFDRFTkxBYqZXhd1p3fmejV7HWhH7d=HnRuNva=AU=aGj72bpzF1ZPv=O1RhEBc7B2BL7n_LmbuveDFeOqAbHcD5HGEmxV217eXVPXVwfDjuA7MnfQ7pVag-H75Rj50XhnGapc0sPjg_71FYT=-K4eVUECWgCm=VoifPoyqzqOgtAhFHPYMAw93LKET51Y9VDSrQja94KfqEmo9HdQm--YUBxOKV6pZXkPn=umNfxJLKJa1RMNwdqZb5-p55TMTg5xez4T=t7NCtLx5jv9FCF1FWE=_9XFn3FD4HGUSmMcs7Mrf1tkTEyGCGWyQh_fr8zischySW-kPg_QodEc0ZaJHOnrSKkrLsZ5heo9mQ4MNmo=7-aJj2h3NQosf9A3YOdF3H8JZWFRpxL6jocYlB=bUNW3XrtnwG_SA=jYua4n5yEKuXppnC59sf0NUlzMokNrWeatLEyPUDyc=58dQFw3BnaSCl_ORt5W8u=Qs0c3qDAj5R8H7A_HJNFd_LfSDAZGiCHCJ59qYcEYHkxC7UOw8TVi-4mTQ2eVefTcnDtzhBrWhSi9jelrzA9rsYUvTL6R0E8KPjz6eJWggr6n05kAOQ1CHeGW7wURvAdAkU8ZsFZND2Ov8N1w1CNuS15qz2zd1sUqO_4-C=7Thq_xLruyZ5Y9-wQeGKNJZfNYqXftkNqW8XpJ5Xf9QYNS0Kkv3r-Z67x3hlcH1VoA-oioatXfXvbsndFVrnMzXNNbdWkvoQE6bfoz8FezQJUcDs3Qm2b7l=PFCoYZRMSlniK6ZR=DAaw98svUutLuhTOt7e0rBDnAZpJ0NA3G1FhX6V3Z=79TcPaw6c7==kZjL2=_xDA0NLwwoOaSf-Q4lWob9KMjdQTt82dH09B34kpfdhAM7Pzi2QiN4Zwkc-BOMB0yKPcdm_zG07ymdTi_s=B7-s1phnQMzvpQXqVzQxhmz99JF-3KJDmere42OZHiurQfd5puzoa2AHjD2XVArLAYy4Bk9FxLT7o_larzKNLQJSFrH1tUAof-bag7gDhbS0gEmHngg1_26QpoRHyfuD4G22S=CXGqUSChhNTmn-VtT-QkxKKsk1UTYXJDkEsaRlcB-fS_POWhEhVUcMnR4aHac40feij0WS9LQvUfu0a3iSAmj0RO=3NY9qMgSh8PrlW=fjfzB1FiEP-1rlRjr5kPTTf-Ln6_A1UJwnTltUKkvCYvUvWZH2=t7ddqqYBk=hJefWiQopz9PyFlnEeaAYxqaxu8d3l0LJH6ua2UlZypbXnb_072sJ9UEPAdixWcK4OLr3a=4dnJXliHPU24=V=dDMf=6m68o=rNX7MBJz0WrLoNpOFOLdBONzxMxTbUJ9Rg8D6gf-Q9o2F710ZRp4euvglmmQepVXCFiWylojVNh99HN=-j0E3_7cBF88BBfqFYwrjeDDYTatefPWM10Cj0oEeWs5cQq27hE8Bga5ZTJCc1fLsY6m6CWHVGXfRgVd4JTKKct5uZvSifVQ=oM37ujKuJiePSB0_39ufHkmgz01exuJdbJUgkj4B4mk_2qV=9uxT1UaXhlC-EflBwhmayNwrtpZYGXk6boi_s381vTUwl2EMoZYkSyD8ZSy=wrr1f7_U27uEYeHqDeGPQR_PpDrGKdDxPt1W5-=Y78UrLhsL6cz0_VxOomxeGiExyz8OVr=CzPKEbGZhyDUdZ5p-3X4PspWraj5Ki2tsdEko0j=BsxJEWKKcLdPlfJKqbcBvuNKYbZ45bJV=NTjmsGqveivNYd-cdEi_0Pei0owdVTZEHk-dt6q13TMeYwZfhtBEODBTicbgmsETrLmHLVF_penPe_DQd3mUnMRPHX2Qy3jRE4mUviiLyihjGnd5zVuCiYvBiogQr45hrtj-GdLOZOzqCH2b9eiMYgek=qNYxiEZeymt4c_Lgduz7Z_ejCrAeSuXw0WBhlMC7GqPKJVPKEUWakKRKcVbYqsDGjGJMoN92Pn-10g-VkgKvHOkP6FAcjv2236947qfjUt_cvyPuAeUtCa=JJGh3_sMsP=8EqHbayhjnKouYlTKh3O1Dc-Dq6zYV1eJYCh6BuFhHBo3dEX_NVtA4NEfnvfy55jB4Jm6AR9AcdVKxrQySdFa8T8ljyH1_yma=T38kaN7kzajZgm0nZL=rJ63lAXKKZEkM0KzAOX5xv=tlid9JL8AlzMHS0y0E9OGwgtxjXBNABWhXB3DFZF4zUu=Bu8Rvc4LAn4B6ODFgTpuaBPnjqUua_ZqF1mes5hV49Pzew29lScAzy5Ylo9tSlLpaxHok35uEXEnpsqySif4o6p3xS3C5n=jxsoXjaDAUhncXEz0inqX3hi-5UmpZlFsp7FOUSXAqe2lcYMj-QDRCCwr0lcpwBZDv0JDEElhRBbj6aXCSEm3SgmqldeRg4Yuz3HxD2-J-M11TyUuW=Y00Jb-D_qQOsmv6FeHkOEk9MQGKkZiUDFQtmzXmZhKzgdkMkp0Fq=cvX6xCF_mVOYj6zZ4L4fx3M-1Dm=o4d0oVWbd5tVS-BAG1TEhz9-mdSFB3E0tL43G7mVykQSMpf5M=kvMaTfSAK3-XT0vuAt6V-kZ_mZvJ0PAEJWs1zKD2=rRD46hGW0VQ1qBO2DcNJTUaCDbsGuKWs227PpxT8r8RXLE9BpsockQVPa-BTLbuR=iYSGuO8N_OTTuY1xt361O2pWu25rxg3JCEi8uFeXjsbc31sz6cqm1Bb4uLWC3wr=89HYR4NqO2q8Sbqe3bRTP595MPc8vQ=OLMiyKOwnrM_NV7TJqa5wDBAJQZz5vt-RMD0Fn9SGHDQrH6RdSb7o2evS=Bb2kS_URQ2SGNx_3TzTn62vo7AhMBlenL7ZQ=f8TgFKL4wXWmirWCntoywABxcnsYRqarO=k14x=e8EFOfEksxtgLCxufFffE-NlqzBY83HXmzjS71kveHQ8bruNppOn5t9CBA3-njtLVvPyeU31=Z8ngSqxJtF4uyeJYTwepuNZ_53aRvimYwUomnsWdWFEcasU=n5PWXXdDrWws7xcKZ_6_1u3A4nqErCxkkY0Quo_9-_wpDL6u5JfFTcU4wFRrX69z9nRlyseR9bL-Fn8p84KCP47inS6aHa5ZriAnYhDUDzek4CDvqQTu3dyHPl70T6iqm=LB0eVbkrS2aYH9E5OttGj_GdsLrF_fNqV-fr55QNffOZYRQipZroVfpk=tZxEeER2JLyMrOGzQEFkdHxHo2ilVBCtd_L=BK5fiKYtb_KeKLiHPO3lJ=miQ4Ct9diOjC8wPe00l-iOxcOAM_jNloyquQPmKrmk_5XAgT2ZY=O4xoyoKTcN_OagqDYm=Ji3AC=VGDumPH01g697OzoRkKDtcBwchOWozUd-1A=ZiM6Y=95AnEQwlsYWqv-ssYkzfDF15Cti4m5=fETM_kdJamcHjhjEixDOKVKEZ28pXfnZ81rO_PZdGCQCK=aZ3HhpnOXgBa1pE9PnrTinAiusEG6u_1iH2yJG9NeV4vkYuNH7R=H06r3fvNo4LAw8QnDN92KYNp9rDB0XWBOtNS9N0Tecb=a8xDPasfgYfgkC1pmazPPYvZzhSyJ7Pma-CTpN3B7MlZgvG94q0tB1x=mG0tHRU6-GTdkUxA8qJ-_KYgffqtpy-=To0-wrT2xu6-6HxOcW4S3X-HVSSGJ3_Ypp8fnVe9aDChm=6Xta=zJgohWAwnSM5E4rdVsWNeFgN8sYyYrzHQoeBG0h86iKDoME345t8MK=WLvyzGU4c_vG84JCpdnGEdiY2BX6rGAHAa5S9J8hdDdPg2dWQ64QAMqZs2f17xQBoChkhab266AE4jfnYMDdDiKkf3QvL0l3zFC5wJlfsa3RSv-YuRQJ_7gq85HMxncuXbqa3uEovvzj96319Bfga07aB_nHuwmY5tWQEKZbeDkHggg7u-mFrJYqeYWjP1Lbqt228hhMKXEY94G4UT_O8uYB2WDeiVyyTFZtBtwYFJSmvBoyn5aE8UwYxhsUGTBcQe2pWrygc-0vq231jjNvNmcKVZwCambVOTT71HjlG7rDjUGiajN1tVjw6ZYk5qezGP3cpXKn6ZcjZXv6nnUpfSzMzKMm3VBaDwJj5GRZ-azqF2Qs-NbraGJF6pgWLAkBLD3lU2y91OUbtkxqzbYP7coro6Wca3CNZ=xaK_SGcYXpqPvplFmDMsgCsPchVYOytlLCwB4fLCTNJcEOYLPnZ0wCpz1=b0_-k0f77UNDPnT4Kqbazf7JATirpO767pYBBXmL7tetqmZ-kNj4Z0K1MzA2PvKu3RZhKBLObuxpiUtgKix3EftrPvjQ-WM_i-vFEPu--ZFVv5_jZ=UjPxlxLqQVVdrO7C4n2iM2mwF91324A07vwN3Z2gcGLXByMe9_F4aR56sJs0gZt7cVQ-HOq_PRt-9WqRLukkqkv_-x=_Cwi3jt9yOqLaUfJsQXcKOQuHDiAePOkBd77zZ3kOa-WKZPi9fxjghZjpAEtKtyA-5MX0NYqUQt5sBxA6miQFMWGsF6J5Z4Ngq7OL9dNBD9C-6JBVScaXDgLFYENgqKamatWyzG-8EdW=hnTF7Lrim0JmVCJmbKMLDM97S2_AVrRTfqsraUklPGkMeiRMQk72EKPT-FJ0Wh1iVrVP2csVQZod_8kqvxUm9pmUqWrgm6yE3CUrlMrcq8=DbHu-y3ZEtK85QY8L',
                'ee30zvqlwf-a0'        => '4S0a6vUOkfXD9xka56AP_wCMYnwHStf2tRCAb24-CNmMA9bvqmGtC8M6LeLTS2uY2ERU8HBj4DbDAj6MBeB97fq3uJpga=n2qt0d2NKD1-uSocDF06s3H38h7Cp6B7ROVlCO76yxxEH0uyUt1PfqXyJxc48xl0g4C5QqeBsYaOQbc0hKH-nb-dfiKTeZ7k3h98eE-RtCBX=FX1JkKnWMNdWbyGOyXSmQGl-yEy69OAD0o1_djjx4iaSkGzx-LzLtCZ8qj2LQkMMMYonhAEVtTHXWl_9cg1SyT37kQn6J2wShForK9vaUwQYC2ctB4FWKUfcONJ7Ua6Ebl-mz1BzipDVFQi2s-qG1Mapz5_Gx_KkvafaHvDUi0eOCky-yW5msQw5ZHahVBlJlQixe_RG3BbFv4CQOJQDBsJ0Nl-6maZXY_hypeHTDC034LEeBE_s1pFB55Oc6NlXCbCJtwa',
                'ee30zvqlwf-b'         => 'rlowom',
                'ee30zvqlwf-c'         => 'AGA4u5-SAQAA1FpigIsUaJ2OFf2OtzTuR83-DUA-6IMKU6hn_Y6ONWU3QvRR',
                'ee30zvqlwf-d'         => 'ADaAhIDBCKGBgQGAAYIQgISigaIAwBGAzPpCxg_33ocx3sD_CACOjjVlN0L0UQAAAAAn36CjAy_0JWFSoxquVzw2UY9YU6s',
                'ee30zvqlwf-f'         => 'A8yEvZ-SAQAAvkd-qInvLrxT3cBc4dmnE9wy6wDqkqWFSGdBix6-WT4uJMs0AbwRk9iucqPJwH9eCOfvosJeCA==',
                'ee30zvqlwf-z'         => 'q',
            ],
            'body' => json_encode([
                'confirmationNumber' => $fields['RecordLocator'],
                'passengerFirstName' => $fields['FirstName'],
                'passengerLastName'  => $fields['LastName'],
                'application'        => 'air-manage-reservation',
                'site'               => 'southwest',
            ]),
        ];
        $response = $tab->fetch('https://www.southwest.com/api/air-misc/v1/air-misc/page/air/manage-reservation/view', $options);
        $this->logger->debug("Redirect: $response->url");
        $this->logger->debug("body: $response->body");
        $this->logger->debug("Headers: " . var_export($response->headers, true));
        $this->logger->debug("Status: $response->status");

        $json = json_decode($response->body);
        $data = $json->data->searchResults->reservations;

        foreach ($data as $reservation) {
            $f = $master->createFlight();

            foreach ($reservation->air->bounds as $bound) {
                foreach ($bound->segments as $segment) {
                    $s = $f->addSegment();
                    $s->departure()->code($segment->originationAirportCode);
                    $s->arrival()->code($segment->destinationAirportCode);
                    $s->departure()->date2($segment->departureDateTime);
                    $s->arrival()->date2($segment->arrivalDateTime);

                    $s->airline()->name($segment->operatingCarrierCode);
                    $s->airline()->number($segment->flightNumber);
                    $s->extra()->duration($segment->duration);
                }
            }

            foreach ($reservation->air->ADULT->passengers as $passenger) {
                $f->general()->confirmation($passenger->confirmationNumber, 'Confirmation #');
                $f->issued()->ticket($passenger->ticketNumber, false);
                $f->general()->traveller("{$passenger->name->firstName} {$passenger->name->middleNames} {$passenger->name->lastName}", true);
            }
        }
    }
    */

    public function retrieveByConfNo(Tab $tab, Master $master, array $fields, ConfNoOptions $options): void
    {
        $f = $master->createFlight();
        $confNo = $tab->evaluate('//span[@class="confirmation-number--code"]')->getInnerText();
        $f->general()->confirmation($confNo, 'Confirmation #');

        $passengersCount = count($tab->evaluateAll('//tr[@class="passenger-details"]'));
        $this->logger->debug('Passengers Count: ' . $passengersCount);

        for ($i = 1; $i <= $passengersCount; $i++) {
            $passengerDataXpath = '(//tr[@class="passenger-details"])' . "[$i]";

            $passengerName = $tab->evaluate($passengerDataXpath . '//div[@class="reservation-name--person-name"]')->getInnerText();
            $f->general()->traveller($passengerName, true);

            $passengerTicketNumber = $tab->findText($passengerDataXpath . '//span[contains(text(), "Ticket #")]', FindTextOptions::new()->preg('/\d+/')->nonEmptyString());
            $f->issued()->ticket($passengerTicketNumber, false, $passengerName);
        }

        $segmentsCount = count($tab->evaluateAll('//div[@class="flight-segments--stop-detail"]'));
        $this->logger->debug('Segments Count: ' . $segmentsCount);

        for ($i = 1; $i <= $segmentsCount; $i++) {
            $segmentDataXpath = '(//div[@class="flight-segments--stop-detail"])' . "[$i]";

            $flightDate = $tab->findText($segmentDataXpath . '/../..//span[@class="flight-detail--heading-date"]', FindTextOptions::new()->preg('/\d+\/\d+\/\d+/')->nonEmptyString());

            $segmentDepartureDataXpath = $segmentDataXpath . '//div[contains(@class, "flight-segments--segment") and contains(@class, "flight-segments--departs")]';

            $departureTime = $tab->findText($segmentDepartureDataXpath . '//span[@class="time--value"]', FindTextOptions::new()->preg('/\d+:\d+\w+/i')->nonEmptyString());
            $departureAirportCode = $tab->evaluate($segmentDepartureDataXpath . '//span[@class="flight-segments--airport-code"]')->getInnerText();
            $departureAirportName = $tab->evaluate($segmentDepartureDataXpath . '//span[@class="flight-segments--station-name"]')->getInnerText();

            $flightNumber = $tab->evaluate($segmentDepartureDataXpath . '//span[@class="flight-segments--flight-number"]')->getInnerText();
            $aircraftName = $tab->evaluate($segmentDepartureDataXpath . '//span[@class="flight-segments--scheduled-aircraft-section--description"]')->getInnerText();

            $segmentArrivalDataXpath = $segmentDataXpath . '//div[contains(@class, "flight-segments--segment") and contains(@class, "flight-segments--arrives")]';

            $arrivalTime = $tab->findText($segmentArrivalDataXpath . '//span[@class="time--value"]', FindTextOptions::new()->preg('/\d+:\d+\w+/i')->nonEmptyString());
            $arrivalAirportCode = $tab->evaluate($segmentArrivalDataXpath . '//span[@class="flight-segments--airport-code"]')->getInnerText();
            $arrivalAirportName = $tab->evaluate($segmentArrivalDataXpath . '//span[@class="flight-segments--station-name"]')->getInnerText();

            $segmentDuration = $tab->evaluate($segmentArrivalDataXpath . '//span[@class="flight-segments--total-duration"]')->getInnerText();

            $segment = $f->addSegment();
            $segment->departure()->code($departureAirportCode);
            $segment->departure()->date2($flightDate . ' ' . $departureTime);
            $segment->departure()->name($departureAirportName);

            $segment->arrival()->code($arrivalAirportCode);
            $segment->arrival()->date2($flightDate . ' ' . $arrivalTime);
            $segment->arrival()->name($arrivalAirportName);

            $segment->extra()->aircraft($aircraftName);
            $segment->airline()->name($this->findPreg('/\D+/', $flightNumber));
            $segment->airline()->number($this->findPreg('/\d+/', $flightNumber));
            $segment->extra()->duration($segmentDuration);
        }
    }
}
