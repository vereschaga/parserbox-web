var plugin = {
    incognito: true,
    hideOnStart: false, // refs #14557
    clearCache: true,
    // keepTabOpen: true, // todo
    // alwaysSendLogs: true, // todo
    // mobileUserAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.110 Safari/537.36',
    loginURL: 'https://www.hyatt.com/en-US/member/sign-in/traditional?returnUrl=https%3A%2F%2Fwww.hyatt.com%2Fprofile%2Faccount-overview',
    loginURLde: 'https://www.hyatt.com/de-DE/member/sign-in/traditional?returnUrl=https%3A%2F%2Fwww.hyatt.com%2Fprofile%2Faccount-overview',

    hosts: {
        'goldpassport.hyatt.com': true,
        'www.hyatt.com': true,
        'world.hyatt.com': true,
        'hyatt.com': true,
        // clearCache
        '.hyatt.com': true,
        '.world.hyatt.com': true
    },

    getStartingUrl: function(params) {
        if (typeof applicationPlatform !== 'undefined' && applicationPlatform === 'android') {
            return plugin.loginURL;
        }
        return 'https://www.hyatt.com/profile/account-overview';
    },

    start: function(params) {
        browserAPI.log("start");
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));

        provider.setTimeout(function() {
            if (plugin.isFullyLoggedIn()) {
                if (plugin.isSameAccount(params.account))
                    plugin.loadAccount(params);
                else {
                    plugin.logout(params);
                }
            } else if (plugin.isPartlyLoggedIn()) {
                plugin.login(params);
            } else if (plugin.isLoggedOut()) {
                plugin.login(params);
            } else {
                browserAPI.log('default branch');
                provider.setNextStep('login', function() {
                    document.location.href = plugin.loginURL;
                });
            }
        }, 2000);
    },

    getFocusTab: function (account, params) {
        return true;
    },

    // for Firefox, refs #19191, #note-24
    getXMLHttp: function () {
        if (typeof content !== 'undefined' && content && content.XMLHttpRequest) {
            return new content.XMLHttpRequest();
        }
        return new XMLHttpRequest();
    },

    isFullyLoggedIn: function() {
        browserAPI.log("isFullyLoggedIn");
        if ($('span[class *= "MemberCard_tierLabel"]:visible').length) {
            browserAPI.log('isFullyLoggedIn = true');
            return true;
        }
    },

    isPartlyLoggedIn: function() {
        browserAPI.log("isPartlyLoggedIn");
        if ($('a.signout-link, a[data-js="signout-lnk"]').length) {
            browserAPI.log('isPartlyLoggedIn = true');
            return true;
        }
    },

    isLoggedOut: function() {
        browserAPI.log("isLoggedOut");
        if ($('span.js-member-name:contains("Sign In")').length) {
            browserAPI.log('isLoggedOut = true');
            return true;
        }
    },

    isLoggedIn: function (returnError) {
        browserAPI.log("isLoggedIn");
        if ($('dd.js-member-number').text()) {
            browserAPI.log("isLoggedIn = true");
            return true;
        }
        if (/(?:Sign In|Anmelden)/.test($('span.js-member-name').text())) {
            browserAPI.log("isLoggedIn = false");
            return false;
        }
        if (typeof(returnError) != 'undefined')
            return false;
        // maintenance
        var error = $('p:contains("The World of Hyatt account system is offline maintenance for maintenance."):eq(0)');
        if (error.length === 0)
            error = $('p:contains("The page you are trying to access is currently down for maintenance."):visible');
        if (error.length === 0)
            error = $('p:contains("The World of Hyatt account system is offline for maintenance. We will be back shortly."):visible, p:contains("Hyatt.com and other related sites are currently down for maintenance. Please come back soon."):visible');
        if (error.length > 0)
            provider.setError([error.text(), util.errorCodes.providerError]);
        else {
            provider.logBody("isLoggedInPage");
            provider.setError(util.errorMessages.unknownLoginState);
        }
    },

    isSameAccount: function(account) {
        browserAPI.log("isSameAccount");
        const number = util.trim($('h4[data-current-tier] > span:visible, [data-locator="member-number"]:visible:eq(0)').text());
        browserAPI.log('number = ' + number);
        var res = (
            (typeof(account.properties) !== 'undefined') &&
            (typeof(account.properties.Number) !== 'undefined') &&
            (account.properties.Number !== '') &&
            (number === account.properties.Number)
        );
        browserAPI.log('isSameAccount = ' + res);
        return res;
    },

    logout: function(params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function() {
            $('div[data-locator="account-panel"] button').click();
            provider.setTimeout(function () {
                $('form[class *= "_profile-signout-form"] button').click();
            }, 500);
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form[action *= "/en-US/reservation/lookup"]');
        if (form.length > 0) {
            form.find('input[name = "confirmationNumber"]').val(properties.ConfNo);
            form.find('input[name = "firstName"]').val(properties.FirstName);
            form.find('input[name = "lastName"]').val(properties.LastName);
            provider.setNextStep('itLoginComplete', function() {
                form.find('button[type = "submit"]').click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    login: function (params) {
        browserAPI.log("login");
        if (
            typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId === 0
        ) {
            provider.setNextStep('getConfNoItinerary', function() {
                document.location.href = 'https://www.hyatt.com/reservation/find';
            });
            return;
        }

        // fixed bad redirect
        if (document.location.href.indexOf('gp/en') === -1 && document.location.href.indexOf('en-US') === -1) {
            browserAPI.log("try to load login form one more time");
            provider.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        }// if (document.location.href.indexOf('gp/en') === -1)

        let counter = 0;
        let login = setInterval(function () {
            let form = null;
            form = $('form[name = "signin-form"]');
            browserAPI.log("waiting... " + counter);
            if (counter > 5 && form.length === 0) {
                form = $('div.hnav-signin form[action = "/bin/gp/login"]');
            }
            let retry = $.cookie("hyatt.com_retry_" + params.account.login);
            browserAPI.log(">>> retry number: " + retry);
            if (form.length > 0) {
                provider.logBody("loginPage");
                // half logged in
                let signout = form.find('a.signout-link:visible, a[data-js="signout-lnk"]:visible');
                if (signout.length > 0) {
                    clearInterval(login);
                    provider.setNextStep('login', function() {
                        browserAPI.log('I am not clicked');
                        signout.get(0).click();
                    });
                    return;
                }// if (signout.length > 0)
                clearInterval(login);
                browserAPI.log('location = ' + document.location.href);
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "userId"]').val(params.account.login);
                form.find('input[type = "password"]').val(params.account.password);

                if (!params.account.login2) {
                    provider.setError(["To update this World of Hyatt account you need to fill in the ‘Last Name’ field. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", util.errorCodes.providerError]);
                    return;
                }

                // debug
                if (provider.isMobile && (retry === null || typeof(retry) === 'undefined')) {
                    if (retry === null || typeof(retry) === 'undefined')
                        retry = 0;
                    browserAPI.log(">>> Retry: " + retry);
                    if (retry >= 2) {
                        browserAPI.log(">>> show site");
                        provider.command('show', function () {
                        });
                    }
                }

                form.find('input[name = "lastName"]').val(params.account.login2);
                form.find('input[name = "remember"]').prop('checked', true);
                provider.setNextStep('checkLoginErrors', function(){
                    provider.setTimeout(function(){
                        form.find('button.submit-btn').prop('disabled', false).get(0).click();
                        if (provider.isMobile && retry >= 2) {
                            browserAPI.log(">>> hide site");
                            provider.command('hide', function () {
                            });
                        }
                    }, 2000);
                });
            }// if (form.length > 0)
            if (counter > 15) {
                clearInterval(login);
                if (plugin.checkProviderErrors(params))
                    return;
                else {
                    // retries
                    browserAPI.log(">>> retry");
                    provider.logBody("loginPage-" + retry);
                    if ((retry === null || typeof(retry) === 'undefined') || retry < 3) {
                        if (retry === null || typeof(retry) === 'undefined')
                            retry = 0;
                        provider.logBody("lastPage-" + retry);
                        browserAPI.log(">>> Retry: " + retry);
                        retry++;
                        $.cookie("hyatt.com_retry_" + params.account.login, retry, { expires: 0.01, path:'/', domain: '.hyatt.com', secure: true });
                        provider.setNextStep('start', function () {
                            document.location.href = plugin.getStartingUrl(params);
                        });
                        return;
                    }// if (retry == null || retry < 3)

                    const message = $('h1:contains("Access Denied"):visible');
                    if (message.length) {
                        provider.setError([message.text(), util.errorCodes.providerError]);
                    }

                    provider.setError(util.errorMessages.loginFormNotFound);
                }
            }
            counter++;
        }, 500);
    },

    startRetry: function (params) {
        browserAPI.log('startRetry');
        browserAPI.log(">>> retry");
        var retry = $.cookie("hyatt.com_retry_" + params.account.login);
        browserAPI.log(">>> retry number: " + retry);
        // browserAPI.log('document.cookie = ' + document.cookie);
        if ((retry === null || typeof (retry) === 'undefined') || retry < 3) {
            if (retry === null || typeof (retry) === 'undefined') {
                retry = 0;
            }
            provider.logBody("lastPage-" + retry);
            browserAPI.log(">>> Retry: " + retry);
            retry++;
            $.cookie("hyatt.com_retry_" + params.account.login, retry, { expires: 0.01, path: '/', domain: '.hyatt.com', secure: true });
            provider.setNextStep('start', function () {
                // debug
                if (retry === 2) {
                    document.location.href = plugin.loginURLde;
                } else {
                    document.location.href = plugin.getStartingUrl(params);
                }
            });
            return true;
        }
        return false;
    },

    checkLoginErrors: function(params) {
        browserAPI.log("checkLoginErrors");
        // browserAPI.log('document.cookie = ' + document.cookie);
        provider.setTimeout(function () {
            let errors = $('span.error-message:visible');
            if (errors.length === 0)
                errors = $("b:contains('The password you entered does not correspond with your Hyatt Gold Passport account number'):visible");
            if ((errors.length === 0 && util.findRegExp(document.location.href, /(signin-error)/i))
                || $('h1:contains("Bad Request"):visible').length > 0
                || $('h1:contains("Access Denied"):visible').length > 0
                || $('p:contains("The requested method GET is not allowed for the URL"):visible').length > 0) {
                // retries
                if (plugin.startRetry(params)) {
                    return;
                }
                provider.setError(['Sign in error occured', util.errorCodes.providerError], true);
                return;
            }// if (errors.length === 0 && util.findRegExp(document.location.href, /(signin-error)/i))
            if (errors.length === 0 && !plugin.isLoggedIn(false) && util.findRegExp(document.location.href, /(login)/i)) {
                if ($('body').text().length === 0) {
                    if (plugin.startRetry(params)) {
                        return;
                    }
                }
                browserAPI.log('lastPage');
                provider.logBody("lastPage");
                if (plugin.checkProviderErrors(params)) {
                    return;
                }
                provider.complete();
                return;
            }
            // if (errors.length === 0 && !plugin.isLoggedIn() && $('h4[data-current-tier] > span:visible').length == 0) {
            //     provider.logBody("lastPage");
            //     provider.setError(['We could not login you to hyatt website for some reason. Please try again later. Code 10.', util.errorCodes.providerError], true);
            // }
            if (errors.length > 0) {
                if (errors.text().indexOf('signIn.error-SystemError') === -1) {

                    if (errors.text().indexOf('We have locked your account to keep it secure') !== -1) {
                        provider.setError([util.filter(errors.text()), util.errorCodes.lockout], true);
                        return;
                    }

                    provider.setError(util.filter(errors.text()), true);
                }
                else
                    provider.setError(['Sign in error occured', util.errorCodes.providerError], true);
            }
            else
                plugin.loadAccount(params);//todo
        }, 2000);
    },

    checkProviderErrors: function (params) {
        browserAPI.log(">>> check provider errors");
        var error = $('h2:contains("Sorry, we\'re experiencing technical difficulties and were unable to complete your request."):visible');
        // maintenance
        if (error.length === 0)
            error = $('p:contains("The World of Hyatt account system is offline maintenance for maintenance."):eq(0)');
        if (error.length === 0)
            error = $('p:contains("The page you are trying to access is currently down for maintenance."):visible');
        if (error.length === 0)
            error = $('p:contains("The World of Hyatt account system is offline for maintenance. We will be back shortly."):visible');
        if (error.length === 0)
            error = $('p:contains("Hyatt.com, world.hyatt.com, and other related sites are currently down for maintenance."):visible');
        if (error.length === 0)
            error = $('p:contains("An unexpected error has occurred while attempting to process your request. Please try again later."):visible');
        if (error.length > 0) {
            provider.setError([util.filter(error.text()), util.errorCodes.providerError], true);
            return true;
        }

        return false;
    },

    loadAccount: function(params) {
        browserAPI.log("loadAccount");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.hyatt.com/profile/my-stays';
            });
            return;
        }
        if (params.autologin) {
            provider.complete();
            return;
        }

        let url = 'https://www.hyatt.com/profile/account-overview';

        if (document.location.href !== url) {
            provider.setNextStep('waitBalance', function () {
                document.location.href = url;
            });
            return;
        }// if (document.location.href != url)

        plugin.waitBalance(params);
    },

    waitBalance: function (params) {
        browserAPI.log("waitBalance");
        var counter = 0;
        var waitBalance = setInterval(function () {
            browserAPI.log("waiting balance... " + counter);
            var balance = $('h1[ng-class *= "gpUser.points"]:visible, div[data-locator="points-balance"]:visible');
            if (balance.length > 0 || counter > 30) {
                clearInterval(waitBalance);
                plugin.parse(params);
            }// if (balance.length > 0 || counter > 30)
            counter++;
        }, 500);
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        let confNo =  params.account.properties.confirmationNumber;
        var link = $('div[data-locator="confirmationNumber"]:contains("' + confNo + '"):eq(0)').parent().parent().next('div').find('a');
        if (link.length > 0) {
            provider.setNextStep('itLoginComplete', function(){
                link.get(0).click();
            });
        }
        else
            provider.setError(util.errorMessages.itineraryNotFound);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    },

    updateAccountMessage:function() {
        browserAPI.log("updateAccountMessage");
        setTimeout(function() {
            provider.updateAccountMessage();
            if (!provider.isMobile)
                provider.eval('$(\'#awMessage\').attr(\'style\', $(\'#awMessage\').attr(\'style\') + \'height:20px !important\');');
        }, 1000);
    },

    parse: function (params) {
        browserAPI.log('parse');
        plugin.updateAccountMessage();
        if (provider.isMobile) {
            provider.command('hide', function () {
            });
        }// if (provider.isMobile)

        var myPastActivity = $('iframe#acct-tabs-iframe').contents().find('span.trigger:contains("My Past Activity")');
        if (myPastActivity.length === 0)
            myPastActivity = $('button.tabs--tab-btn:contains("Past Activity")');
        if (myPastActivity.length > 0)
            myPastActivity.click();
        else
            browserAPI.log('My Reservations not found');

        // error login
        var errors = util.findRegExp( $('span.gpError').text(), /(You must be a Hyatt Gold Passport member to view Account Details\.)/i);
        if (errors && errors.length == 0 && !plugin.isLoggedIn())
            provider.setError('We could not login you to hyatt website for some reason.');

        // login failed
        if (-1 < document.location.href.indexOf('https://world.hyatt.com/gp/en/account/sign_in.jsp') ) {
            provider.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        }

        let data = {};
        // Balance - Current Points
        let balance = $('h1[ng-class *= "gpUser.points"]:visible, div[data-locator="points-balance"]:visible');
        if (balance.length > 0) {
            balance = util.findRegExp( balance.eq(0).text(), /([\d\.\,\-]+)/i);
            browserAPI.log("Balance: " + balance );
            data.Balance = balance;
        }
        else
            browserAPI.log("Balance not found");
        // World Passport #
        let number =  $('h4[data-current-tier] > span:visible, [data-locator="member-number"]:visible:eq(0)');
        if (number.length > 0) {
            data.Number = number.text().trim();
            browserAPI.log("World Passport #: " + data.Number );
        }
        else
            browserAPI.log("World Passport # not found");
        // Name
        let name = $('div.hero-block > h1[class *= "title"], span.js-member-name, div.hbe-header_popover-person span.hbe-header_popover-label span, span[class *= "hbe-menuButton_menuLabel"] span[data-cs-mask]');
        if (name.length > 0) {
            data.Name = util.beautifulName(util.findRegExp( name.eq(0).html(), /([^\.]+)/));
            browserAPI.log("Name: " + data.Name );
        }
        else
            browserAPI.log("Name not found");
        // Tier
        let tier = $('span[class *= "MemberCard_tierLabel"]:visible');
        if (tier.length > 0) {
            data.Tier = util.findRegExp(tier.text(), /([^\|]+)/i);
            browserAPI.log("Tier: " + data.Tier );
        }

        $.ajax({
            url: 'https://www.hyatt.com/profile/api/member/profile',
            async: false,
            xhr: plugin.getXMLHttp,
            success: function (response) {
                response = $(response);
                // console.log(response);
                // Tier
                if (tier.length === 0 && typeof (response[0].profile.full.profile.tier) !== 'undefined') {
                    tier = null;

                    switch (response[0].profile.full.profile.tier) {
                        case 'P':
                            tier = "Platinum";
                            break;
                        case 'D':
                            tier = "Diamond";
                            break;
                        case 'l':
                            tier = "Lifetime Diamond";
                            break;
                        case 'C':
                            tier = "Courtesy";
                            break;
                        case 'G':
                            tier = "Gold";
                            break;
                        case 'M':
                            tier = "Member";
                            break;
                        case 'E':
                            tier = "Explorist";
                            break;
                        case 'V':
                            tier = "Discoverist";
                            break;
                        case 'B':
                            tier = "Globalist";
                            break;
                        case 'L':
                            tier = "Lifetime Globalist";
                            break;
                        default:
                            browserAPI.log("New tier was found: " + response[0].profile.full.profile.tier );
                            break;
                    }// switch ($tier)
                    data.Tier = tier;
                    browserAPI.log("Tier: " + data.Tier );
                }

                let profile = response[0].profile.full;
                // Member since
                let enrollDate = profile.profile.enrollDate | '';
                browserAPI.log("enrollDate: " + enrollDate);

                if (enrollDate) {
                    enrollDate = enrollDate + '';
                    let date = enrollDate.replace(/(\d{4})(\d{2})(\d{2})/, '$1/$2/$3');
                    let res = date.split("/");
                    let months = [
                        "Jan",
                        "Feb",
                        "Mar",
                        "Apr",
                        "May",
                        "Jun",
                        "Jul",
                        "Aug",
                        "Sep",
                        "Oct",
                        "Nov",
                        "Dec"
                    ];
                    data.MemberSince = months[res[1]-1] + ' ' + res[2] + ', ' + res[0];
                    browserAPI.log("Member Since " + data.MemberSince);
                } else {
                    browserAPI.log("Member Since not found");
                }
                // Lifetime Points
                if (typeof profile.lifePoints !== 'undefined') {
                    data.LifetimePoints = util.trim(profile.lifePoints);
                    browserAPI.log("Lifetime Points: " + data.LifetimePoints );
                }
                else
                    browserAPI.log("Lifetime Points not found");
                // Qualified Nights YTD
                if (typeof profile.ytdNights !== 'undefined') {
                    data.Nights = util.trim(profile.ytdNights);
                    browserAPI.log("Qualified Nights YTD: " + data.Nights );
                }
                else
                    browserAPI.log("Qualified Nights YTD not found");
                // Base Points YTD
                if (typeof profile.ytdBasePoints !== 'undefined') {
                    data.BasePointsYTD = util.trim(profile.ytdBasePoints);
                    browserAPI.log("Base Points YTD: " + data.BasePointsYTD );
                }
                else
                    browserAPI.log("Base Points YTD not found");
            }// success: function (response)
        });// $.ajax({

        // Tier Expiration
        let tierExpiration = $('p:contains("Valid through"), div[data-locator="status"]:contains("through"):eq(0)');
        if (tierExpiration.length > 0) {
            data.TierExpiration = util.findRegExp( tierExpiration.text(), /Through\s*([^<]+)/i);
            browserAPI.log("Tier Expiration: " + data.TierExpiration );
        }
        else
            browserAPI.log("Tier Expiration not found");

        params.data.properties = data;
        browserAPI.log("saving temp");
        provider.saveTemp(params.data);

        // Parsing subAccounts
        provider.setNextStep('parseSubAccounts', function () {
            document.location.href = 'https://www.hyatt.com/profile/awards';
        });
    },

    number_format: function (number, decimals, dec_point, thousands_sep) {
        // Strip all characters but numerical ones.
        number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
        var n          = !isFinite(+number) ? 0 : +number,
            prec       = !isFinite(+decimals) ? 0 : Math.abs(decimals),
            sep        = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
            dec        = (typeof dec_point === 'undefined') ? '.' : dec_point,
            s          = '',
            toFixedFix = function (n, prec) {
                var k = Math.pow(10, prec);
                return '' + Math.round(n * k) / k;
            };
        // Fix for IE parseFloat(0.55).toFixed(0) = 0;
        s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
        if (s[0].length > 3) {
            s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
        }
        if ((s[1] || '').length < prec) {
            s[1] = s[1] || '';
            s[1] += new Array(prec - s[1].length + 1).join('0');
        }
        return s.join(dec);
    },

    parseSubAccounts: function (params) {
        browserAPI.log("parseSubAccounts");
        let subAccounts = [];
        const awards = $('li[class *= "AwardsListItem_award"]');
        browserAPI.log('total ' + awards.length + ' awards were found');
        $.ajax({
            url: "https://www.hyatt.com/profile/api/member/profile",
            async: false,
            success: function (response) {
                response = $(response);
                if (typeof (response[0].profile.full.accountNumber) !== 'undefined') {
                    $.ajax({
                        type: "GET",
                        url: 'https://www.hyatt.com/profile/api/loyalty/awarddetail?locale=en-US',
                        async: false,
                        contentType: "application/json; charset=UTF-8",
                        dataType: "json",
                        success: function (data) {
                            browserAPI.log("Awards start");
                            data = $(data);
                             // browserAPI.log("---------------- data ----------------");
                             // browserAPI.log(JSON.stringify(data));
                             // browserAPI.log("---------------- data ----------------");
                            if (typeof (data[0].awardCategories) !== 'undefined') {
                                let subAccount = [];
                                for (let awardCategory in data[0].awardCategories) {
                                    browserAPI.log(">>> Award: " + awardCategory);
                                    if (data[0].awardCategories.hasOwnProperty(awardCategory)) {
                                        browserAPI.log("Award was found");
                                        let awards = data[0].awardCategories[awardCategory].awards;
                                        for (let award in awards) {
                                            let unixTime = new Date(awards[award].expirationDate) / 1000;
                                            if (isNaN(unixTime))
                                                continue;
                                            let subAcc = {
                                                "Code": 'goldpassport' + awards[award].code + awards[award].expirationDate + awards[award].title.replace(/\s*/ig, ''),
                                                "DisplayName": awards[award].title,
                                                "Balance": 1,
                                                "ExpirationDate": unixTime
                                            };
                                            let code = awards[award].expirationDate + awards[award].title.replace(/\s*/ig, '');
                                            if (typeof subAccount[code] !== 'undefined') {
                                                subAcc = subAccount[code];
                                                subAcc['Balance'] += 1;
                                            }
                                            subAccount[code] = subAcc;
                                            browserAPI.log(">>> subAcc: " + subAcc);
                                        }
                                    }
                                }

                                for (let subAcc in subAccount) {
                                    subAccounts.push(subAccount[subAcc]);
                                }

                                /*for (var award in data[0].awards) {
                                    browserAPI.log(">>> Award: " + award);
                                    if (data[0].awards.hasOwnProperty(award)) {
                                        browserAPI.log("Award was found");
                                        // console.log(data[0].awards[award]);
                                        // Available
                                        if (typeof (data[0].awards[award].totalAvailable) === 'undefined' || data[0].awards[award].totalAvailable === 0) {
                                            continue;
                                        }// if (typeof (data[0].awards[award].totalAvailable) !== 'undefined' && data[0].awards[award].totalAvailable === 0)

                                        let details = data[0].awards[award].details;

                                        for (var detail in details) {
                                            browserAPI.log("Details was found");
                                            if (!details.hasOwnProperty(detail)) {
                                                continue;
                                            }
                                            var nightAwards = details[detail].availableAwards;

                                            if (nightAwards === 0) {
                                                continue;
                                            }

                                            // Subaccount name
                                            var displayName = details[detail].awardDescription;
                                            var code = details[detail].awardCode;
                                            var subAccountName = plugin.setSubAccountName(code);
                                            if (code !== 'GOHLEGACY' && subAccountName)
                                                displayName = subAccountName;
                                            // Expiration
                                            var exp = details[detail].expirationDate;
                                            var unixtime = new Date(exp) / 1000;
                                            // // refs #15508
                                            // var newElem = true;
                                            // for (var i = 0; i < subAccounts.length; i++) {
                                            //     if (subAccounts[i]["DisplayName"] === displayName) {
                                            //         browserAPI.log("duplicate was found: " + displayName);
                                            //         newElem = false;
                                            //         if (!isNaN(unixtime) && typeof (subAccounts[i]["ExpirationDate"]) != 'undefined') {
                                            //             if (unixtime < subAccounts[i]["ExpirationDate"]) {
                                            //                 subAccounts[i]["ExpiringBalance"] = nightAwards;
                                            //                 subAccounts[i]["ExpirationDate"] = unixtime;
                                            //             }
                                            //             else
                                            //                 subAccounts[i]["ExpiringBalance"] = subAccounts[i]["Balance"];
                                            //         }
                                            //         subAccounts[i]["Balance"] = subAccounts[i]["Balance"] + nightAwards;
                                            //     }
                                            // }
                                            if (!isNaN(unixtime) && nightAwards > 0) {
                                                // if (newElem === true)
                                                subAccounts.push({
                                                    "Code"          : 'goldpassport' + code + exp + displayName.replace(/\s*!/ig, ''),
                                                    "DisplayName"   : displayName,
                                                    "Balance"       : nightAwards,
                                                    "ExpirationDate": unixtime
                                                });
                                            } else if (nightAwards > 0) {
                                                // if (newElem === true)
                                                subAccounts.push({
                                                    "Code"       : 'goldpassport' + code + displayName.replace(/\s*!/ig, ''),
                                                    "DisplayName": displayName,
                                                    "Balance"    : nightAwards
                                                });
                                            }
                                            browserAPI.log(JSON.stringify(subAccounts));
                                        }// for (var detail in details) {
                                    }// if (data[0].awards.hasOwnProperty(award))
                                    browserAPI.log("<<< Award: " + award);
                                }// for (var award in data[0].awards)*/
                            }// if (typeof (data[0].awards) !== 'undefined')
                            browserAPI.log("Awards end");
                        },// success: function (data)
                        // refs #15508#note-9
                        // todo: for Firefox, refs #19191, #note-24
                        error: function (data) {
                            browserAPI.log("fail");
                            data = $(data);
                            browserAPI.log("---------------- fail data ----------------");
                            browserAPI.log(JSON.stringify(data));
                            browserAPI.log("---------------- fail data ----------------");

                            var awCode = document.createElement( 'script' );
                            awCode.id = 'subAccounts';
                            document.getElementsByTagName('head')[0].appendChild(awCode);

                            /*provider.eval("" +
                                "var subAccounts = [];\n" +
                                "$.ajax({\n" +
                                "                        type: \"GET\",\n" +
                                "                        url: 'https://www.hyatt.com/mse/memberaward/v1/members/details/" + response[0].goldpassportId + "?locale=en-US',\n" +
                                "                        async: false,\n" +
                                "                        contentType: \"application/json; charset=UTF-8\",\n" +
                                "                        dataType: \"json\",\n" +
                                "                        success: function (data) {\n" +
                                "                            console.log(\"Awards\");\n" +
                                "                            data = $(data);\n" +
                                "                            console.log(\"---------------- data ----------------\");\n" +
                                "                            console.log(JSON.stringify(data));\n" +
                                "                            console.log(\"---------------- data ----------------\");\n" +
                                "                            if (typeof (data[0].awards) !== 'undefined') {\n" +
                                "                                // console.log(\"Total nodes found: \".data[0].awards.length);\n" +
                                "                                for (var award in data[0].awards) {\n" +
                                "                                    console.log(\">>> Award: \" + award);\n" +
                                "                                    if (data[0].awards.hasOwnProperty(award)) {\n" +
                                "                                        // console.log(data[0].awards[award]);\n" +
                                "                                        // Available\n" +
                                "                                        if (typeof (data[0].awards[award].totalAvailable) === 'undefined' || data[0].awards[award].totalAvailable === 0) {\n" +
                                "                                            continue;\n" +
                                "                                        }// if (typeof (data[0].awards[award].totalAvailable) !== 'undefined' && data[0].awards[award].totalAvailable === 0)\n" +
                                "                                        let details = data[0].awards[award].details;\n" +
                                "                                        for (var detail in details) {\n" +
                                "                                            let details = data[0].awards[award].details;\n" +
                                "                                            if (!details.hasOwnProperty(detail)) {\n" +
                                "                                                continue;\n" +
                                "                                            }\n" +
                                "                                            var nightAwards = details[detail].availableAwards;\n" +
                                "                                            if (nightAwards === 0) {\n" +
                                "                                               continue;\n" +
                                "                                            }\n" +
                                "                                            // Subaccount name\n" +
                                "                                            var displayName = data[0].awards[award].awardDescription;\n" +
                                "                                            var code = data[0].awards[award].awardCode;\n" +
                                "                                            var subAccountName = setSubAccountName(code);\n" +
                                "                                            if (code !== 'GOHLEGACY' && subAccountName)\n" +
                                "                                                displayName = subAccountName;\n" +
                                "                                            // Expiration\n" +
                                "                                            var exp = details[detail].expirationDate;\n" +
                                "                                            var unixtime = new Date(exp) / 1000;\n" +
                                // "                                            var newElem = true;\n" +
                                // "                                            for (var i = 0; i < subAccounts.length; i++) {\n" +
                                // "                                                if (subAccounts[i][\"DisplayName\"] === displayName) {\n" +
                                // "                                                    console.log(\"duplicate was found: \" + displayName);\n" +
                                // "                                                    newElem = false;\n" +
                                // "                                                    if (!isNaN(unixtime) && typeof (subAccounts[i][\"ExpirationDate\"]) != 'undefined') {\n" +
                                // "                                                        if (unixtime < subAccounts[i][\"ExpirationDate\"]) {\n" +
                                // "                                                            subAccounts[i][\"ExpiringBalance\"] = nightAwards;\n" +
                                // "                                                            subAccounts[i][\"ExpirationDate\"] = unixtime;\n" +
                                // "                                                       }\n" +
                                // "                                                        else\n" +
                                // "                                                            subAccounts[i][\"ExpiringBalance\"] = subAccounts[i][\"Balance\"];" +
                                // "                                                    }\n" +
                                // "                                                    subAccounts[i][\"Balance\"] = subAccounts[i][\"Balance\"] + nightAwards;\n" +
                                // "                                                }\n" +
                                // "                                            }" +
                                "                                            if (!isNaN(unixtime) && nightAwards > 0) {\n" +
                                // "                                               if (newElem === true)\n" +
                                "                                                subAccounts.push({\n" +
                                "                                                    \"Code\": 'goldpassport' + code + exp  + displayName.replace(/\\s*!/ig, ''),\n" +
                                "                                                    \"DisplayName\": displayName,\n" +
                                "                                                    \"Balance\": nightAwards,\n" +
                                "                                                    \"ExpirationDate\" : unixtime\n" +
                                "                                                });\n" +
                                "                                            } else if (nightAwards > 0) {\n" +
                                // "                                               if (newElem === true)\n" +
                                "                                                subAccounts.push({\n" +
                                "                                                    \"Code\": 'goldpassport' + code + displayName.replace(/\\s*!/ig, ''),\n" +
                                "                                                    \"DisplayName\": displayName,\n" +
                                "                                                    \"Balance\": nightAwards\n" +
                                "                                                });\n" +
                                "                                            }\n" +
                                "                                            console.log(JSON.stringify(subAccounts));\n" +
                                "                                        }// if (typeof (award.available) != 'undefined' && award.available > 0)\n" +
                                "                                    }// if (data.trip.products.hasOwnProperty(itinerary))\n" +
                                "                                    console.log(\"<<< Award: \" + award);\n" +
                                "                                }// for (var itinerary in data.trip.products)\n" +
                                "                                function setSubAccountName(code) {\n" +
                                "                                    console.log(\"setSubAccountName\");\n" +
                                "                                    var displayName = null;\n" +
                                "                                    switch (code) {\n" +
                                "                                        case 'TUPUS':\n" +
                                "                                        case 'ADJUPUS':\n" +
                                "                                            displayName = \"Suite Upgrade Award\";\n" +
                                "                                            break;\n" +
                                "                                        case 'DIAMD':\n" +
                                "                                            displayName = \"Diamond Suite Upgrade\";\n" +
                                "                                            break;\n" +
                                "                                        case 'PBUPUR':\n" +
                                "                                            displayName = \"Club Access Award\";\n" +
                                "                                            break;\n" +
                                "                                        case 'UPUS2':\n" +
                                "                                            displayName = \"Suite Upgrade Award\";\n" +
                                "                                            break;\n" +
                                "                                        case 'TUPUS2':\n" +
                                "                                        case 'TUPUSM':\n" +
                                "                                            displayName = \"Tier Suite Upgrade Award\";\n" +
                                "                                            break;\n" +
                                "                                        case 'MS75UH':\n" +
                                "                                            displayName = 'One Free Night - 75 Unique Hotels';\n" +
                                "                                            break;\n" +
                                "                                        case 'MSBL10B':\n" +
                                "                                            displayName = 'One Free Night in a Suite - 1 million base points';\n" +
                                "                                            break;\n" +
                                "                                        case 'CHASE_FN':\n" +
                                "                                            displayName = 'Free Night Award';\n" +
                                "                                            break;\n" +
                                "                                        case 'CAT17RM':\n" +
                                "                                        case 'CAT17RM365':\n" +
                                "                                            displayName = 'Promotional Free Night Award';\n" +
                                "                                            break;\n" +
                                "                                        case 'CAT14RM365':\n" +
                                "                                            displayName = \"Category 1-4 Free Night Award 365\";\n" +
                                "                                            break;\n" +
                                "                                        case 'CAT14RM':\n" +
                                "                                            displayName = \"Category 1-4 Promotion Award\";\n" +
                                "                                            break;\n" +
                                "                                        case 'CHRM2':\n" +
                                "                                            displayName = \"Category 1-4 Standard Award\";\n" +
                                "                                            break;\n" +
                                "                                        case 'CHASE_ANIV':\n" +
                                "                                            displayName = \"Anniversary Free Night Award\";\n" +
                                "                                            break;\n" +
                                "                                        default:\n" +
                                "                                            console.log(\"New award type was found:\" + code);\n" +
                                "                                    }\n" +
                                "\n" +
                                "                                    return displayName;\n" +
                                "                                };" +
                                "$('#subAccounts').text(JSON.stringify(subAccounts));" +
                                "                            }// if (typeof (data[0].awards) !== 'undefined')\n" +
                                "                        },// success: function (data)\n" +
                                "                        error: function (data) {\n" +
                                "                            console.log(\"fail\");\n" +
                                "                            data = $(data);\n" +
                                "                            console.log(\"---------------- fail data ----------------\");\n" +
                                "                            console.log(JSON.stringify(data));\n" +
                                "                            console.log(\"---------------- fail data ----------------\");\n" +
                                "                        }// error: function (data)\n" +
                                "                    });// $.ajax({" +
                                "");

                            const safeParseJson = (str) => {
                                let outputStr;

                                try {
                                    outputStr = JSON.parse(str);
                                } catch (e) {
                                    outputStr = JSON.parse(JSON.stringify(str));
                                }

                                return outputStr;
                            };

                            subAccounts = safeParseJson($('#subAccounts').text());*/
                            // browserAPI.log("---------------- fail data ----------------");
                            // browserAPI.log(JSON.stringify(subAccounts));
                            // browserAPI.log("---------------- fail data ----------------");
                        }// error: function (data)
                    });// $.ajax({
                }// if (typeof (response[0].token) != 'undefined' && typeof (response[0].goldpassportId) != 'undefined')
            }// success: function (response)
        });// $.ajax({

        browserAPI.log("ajax end");

        params.data.properties.SubAccounts = subAccounts;
        params.data.properties.CombineSubAccounts = 'false';
        // params.account.properties = params.data.properties;
        // console.log(params.account.properties);//todo
        // provider.saveProperties(params.account.properties);

        params.data.properties.HistoryRows = [];
        params.data.endHistory = false;
        browserAPI.log("saving temp");
        provider.saveTemp(params.data);

        // Parsing history
        provider.setNextStep('parseHistoryPreLoad', function () {
            document.location.href = 'https://www.hyatt.com/profile/en-US/account-activity';
        });
    },

    setSubAccountName: function (code) {
        browserAPI.log("setSubAccountName");
        let displayName = null;
        switch (code) {
            case 'TUPUS':
            case 'ADJUPUS':
                displayName = "Suite Upgrade Award";
                break;
            case 'DIAMD':
                displayName = "Diamond Suite Upgrade";
                break;
            // broken subacc
            case 'DISVGIFTAW':
                break;
            case 'PBUPUR':
            case 'GOHLEGACY':
            case 'GOHCY14M':
                displayName = "Club Access Award";
                break;
            case 'UPUS2':
                displayName = "Suite Upgrade Award";
                break;
            case 'TUPUS2':
            case 'TUPUSM':
                displayName = "Tier Suite Upgrade Award";
                break;
            case 'MS75UH':
                displayName = 'One Free Night - 75 Unique Hotels';
                break;
            case 'MSBL10B':
                displayName = 'One Free Night in a Suite - 1 million base points';
                break;
            case 'CHASE_FN':
                displayName = 'Free Night Award';
                break;
            case 'CAT17RM':
            case 'CAT17RM365':
                displayName = 'Promotional Free Night Award';
                break;
            case 'CAT14RM365':
                displayName = "Category 1-4 Free Night Award 365";
                break;
            case 'CAT14RM':
                displayName = "Category 1-4 Promotion Award";
                break;
            case 'CHRM1':
                displayName = "Standard Free Night Award";
                break;
            case 'CHRM2':
                displayName = "Category 1-4 Standard Award";
                break;
            case 'CHASE_ANIV':
                displayName = "Anniversary Free Night Award";
                break;
            default:
                browserAPI.log("New award type was found:" + code);
        }

        return displayName;
    },

    parseHistoryPreLoad: function (params) {
        setTimeout( function () {
            plugin.parseHistory(params);
        }, 3000);
    },

    parseHistory: function (params) {
        browserAPI.log("parseHistory");
        plugin.updateAccountMessage();
        var history = [];
        var startDate = params.account.historyStartDate;

        browserAPI.log("historyStartDate: " + startDate);

        // refs #14557
        if (!provider.isMobile) {
            browserAPI.log("setIdleTimer: 180");
            provider.setIdleTimer(180);
        }

        // Expiration date  // refs #6360, 12414
        var time = Math.round(new Date().getTime()/1000);
        var lastActivity;
        var lastActivityUnixTime;
        var exp;

        let transactions = $('div[data-js="past-activity-card"]');
        browserAPI.log('total ' + transactions.length + ' transactions were found');
        transactions.each(function () {
            let activityDate = util.filter($('div[class *= "PastActivityCard_dates"]:visible', $(this)).text());
            browserAPI.log('activityDate: ' + activityDate);
            activityDate = activityDate.replace(/\s*- \w{3} \d+,/, '');
            browserAPI.log("Activity Date: " + activityDate);
            if ((typeof(activityDate) != 'undefined') && (activityDate !== '')) {
                let date = new Date(activityDate + ' UTC');
                let unixtime = date / 1000;
                if (date && !isNaN(unixtime) && !lastActivity) {
                    lastActivity = activityDate;
                    lastActivityUnixTime = unixtime;
                    browserAPI.log("Last Activity: " + lastActivity + " / " + lastActivityUnixTime);
                }// if (date && !isNaN(unixtime) && !lastActivity)
                if ( unixtime <= time ) {
                    date.setFullYear(date.getFullYear() + 2);
                    unixtime = date / 1000;
                    if (date && !isNaN(unixtime)) {
                        browserAPI.log("ExpirationDate = lastActivity + 2 years");
                        browserAPI.log("Expiration Date: " + date + " Unixtime: " + unixtime );
                        exp = unixtime;
                    }// if (date && !isNaN(unixtime))
                }// if ( unixtime <= time )
                if (exp && lastActivityUnixTime) {
                    if (exp <= lastActivityUnixTime) {
                        // Last Activity
                        params.data.properties.LastActivity = activityDate;
                        browserAPI.log("Set Last Activity: " + activityDate);
                        params.data.properties.AccountExpirationDate = exp;
                    }// if (exp <= lastActivityUnixTime)
                    else {
                        browserAPI.log("Set Last Activity: " + lastActivity);
                        date = new Date(lastActivity + ' UTC');
                        date.setFullYear(date.getFullYear() + 2);
                        unixtime = date / 1000;
                        if (date && !isNaN(unixtime) ) {
                            browserAPI.log("ExpirationDate = lastActivity + 2 years");
                            browserAPI.log("Expiration Date: " + date + " Unixtime: " + unixtime );
                            params.data.properties.AccountExpirationDate = unixtime;
                        }// if ( date != 'NaN' && !isNaN(unixtime) && unixtime <= time)
                        // Last Activity
                        params.data.properties.LastActivity = lastActivity;
                    }
                    return false;
                }// if (isset($exp, $lastActivityUnixTime))
            } else
                browserAPI.log("Last Activity not found");
        });

        let filterStartDate = plugin.getHistoryDate(-5);
        $.ajax({
            url: 'https://www.hyatt.com/profile/api/stay/pastactivity?pageSize=100&pageIndex=0&transactionType=AT&locale=en-US&startDate=' + filterStartDate + '&endDate=',
            async: false,
            headers: {
                'Accept': 'application/json',
            },
            success: function (data) {
                plugin.parseHistoryData(params, data);
            },
            error: function (data) {
                browserAPI.log('Failed to parse history');
                browserAPI.log('---------------- fail data ----------------');
                browserAPI.log(JSON.stringify(data));
                browserAPI.log('---------------- fail data ----------------');

                let awCode = document.createElement('script');
                awCode.id = 'accountHistory';
                document.getElementsByTagName('head')[0].appendChild(awCode);

                provider.eval("" +
                    "$.ajax({" +
                        "url: 'https://www.hyatt.com/profile/api/stay/pastactivity?pageSize=100&pageIndex=0&transactionType=AT&locale=en-US&startDate='" + filterStartDate + "'&endDate='," +
                        "async: false," +
                        "headers: {" +
                            "'Accept': 'application/json'," +
                        "}," +
                        "success: function (data) {" +
                            "$('#accountHistory').text(JSON.stringify(data));" +
                        "}," +
                        "error: function (data) {" +
                            "console.log('Failed to parse history again');" +
                            "console.log('---------------- fail data ----------------');" +
                            "console.log(JSON.stringify(data));" +
                            "console.log('---------------- fail data ----------------');" +
                        "}" +
                    "});"
                );
                data = $.parseJSON($('#accountHistory').text());
                plugin.parseHistoryData(params, data);
            }// error: function (data)
        });// $.ajax({

        if (typeof (params.account.parseItineraries) == 'boolean' &&
            params.account.parseItineraries
        ) {
            provider.setNextStep('waitItineraries', function () {
                document.location.href = 'https://www.hyatt.com/profile/my-stays';
            });
            setTimeout(function() {
                plugin.waitItineraries(params);
            }, 2000);
        } else {
            browserAPI.log(">>> complete");
            provider.complete();
        }
    },

    historyCodeToLabel: function(code) {
        browserAPI.log('historyCodeToLabel');
        var labels = {
            'CHRM2': 'Category 1-4 - Standard Award',
            'XFRPTS': 'Points Transfer',
            'PCRF': 'Held for Future - Partner Credit',
            'CHRM1': 'Standard Free Night Award',
            '5K02NC': 'Chase Credit Card Night Credits',
            'CHASE_ANIV': 'Anniversary Free Night Award ',
            'TUPUSM': 'Tier Suite Upgrade Award',
            'UPUS2': 'Suite Upgrade Award',
            'NE05NC': 'Chase Credit Card Night Credits',
            'AA05NC': 'Chase Credit Card Night Credits',
            '20FRN': 'Category 1-7 Standard Award',
            'CHASE_FN': 'Free Night Award',
            'PBUPUR': 'Club Access Award',
            'GPMBONUS': 'Meeting or Event Bonus',
            'GR': 'Guest Relations Bonus',
            'CAT14RM365': 'Category 1-4 Promotion Award',
            'SIGNVAR': 'Planner Signing Bonus',
            'TUPUS2': 'Tier Suite Upgrade Award',
            'hhhpfn': 'Free Night Award',
            'CAT17RM': 'Promotional Free Night Award',
            'CAT17RM365': 'Promotional Free Night Award',
            'WHYSTL': 'Promotional Free Night Award',
            'QARVAR': 'Quality Assurance Bonus',
            'CAT14RM': 'Category 1-4 Promotion Award',
        };
        return labels[code];
    },

    parseHistoryData: function (params, data) {
        browserAPI.log('parseHistoryData');
        if (
            typeof (data) === 'undefined'
            || typeof (data.pastActivity) === 'undefined'
        ) {
            browserAPI.log('>>> History activities not found');
            return;
        }
        if (typeof params.data.properties.HistoryRows !== 'undefined' &&
            params.data.properties.HistoryRows.length > 0
        ) {
            browserAPI.log('History already parsed');
            return;
        }
        let startDate = params.account.historyStartDate;

        browserAPI.log('Found ' + data.pastActivity.length + ' history items');
        for (let i = 0; i < data.pastActivity.length; i++) {
            let activity = data.pastActivity[i];
            browserAPI.log(">>> activity: " + JSON.stringify(activity));//todo
            let row = {};
            // TransactionDate
            let transactionDateStr = activity.transaction.date;

            if (!transactionDateStr) {
                browserAPI.log('>>> Skipping activity with no date');
                continue;
            }

            let transactionDate = new Date(transactionDateStr).getTime() / 1000;

            if (startDate > 0 && transactionDate < startDate) {
                browserAPI.log("break at date " + transactionDateStr + " " + transactionDate);
                params.data.endHistory = true;
                break;
            }

            row['Transaction Date'] = transactionDate;
            // Check-out Date
            if (
                typeof activity.stay !== 'undefined'
                && typeof activity.stay.endDate !== 'undefined'
            ) {
                let checkOutDate = activity.stay.endDate;
                browserAPI.log("checkOutDate " + checkOutDate);
                if (checkOutDate) {
                    row['Check-out Date'] = new Date(checkOutDate).getTime() / 1000;
                }
            }
            // Type and Description
            let transactionType = activity.transaction.category || null;
            let transactionSubType = activity.transaction.subCategory || null;
            let checkInDatePresent = true;
            let type = null;
            let description = '';
            let facilityName = '';
            switch (transactionType) {
                case 'A':
                    type = 'Points Redeemed';
                    if (transactionSubType === 'FreeNight' &&
                        activity.transaction.totalAmount >= 0
                    ) {
                        type = 'Free Night Award';
                        checkInDatePresent = false;
                    }

                    description = typeof activity.hotelDetail !== 'undefined' ? activity.hotelDetail.name : activity.misc.description;
                    break;
                case 'B':
                    type = 'Bonus';
                    let actionCode = activity.misc.bonusCode || null;
                    description = plugin.historyCodeToLabel(actionCode) || 'Reward Bonus';
                    break;
                case 'F':
                    type = 'Points earned';
                    description = typeof activity.hotelDetail !== 'undefined' ? activity.hotelDetail.name : activity.misc.description;
                    break;
                case 'G':
                    type = 'Gift';
                    description = 'Gift';
                    break;
                case 'P':
                    type = 'Point Purchase';
                    description = 'Purchase';
                    break;
                case 'N':
                    type = 'Other';
                    if (transactionSubType === 'NonStay') {
                        type = 'Points earned';
                    }
                    description = activity.hotelDetail.name || null;
                    facilityName = activity.misc.facilityName;
                    if (facilityName) {
                        description +=  " / " + facilityName;
                    }
                    break;
                case 'O':
                    type = 'Adjustment';
                    description = typeof activity.hotelDetail !== 'undefined' ? activity.hotelDetail.name : null;
                    facilityName = activity.misc.facilityName;
                    if (facilityName) {
                        description += " / " + facilityName;
                    }
                    if (!description || description === '') {
                        description = activity.misc.adjustmentDescription;
                    }
                    break;
                case 'T':
                    type = 'Gift';
                    description = activity.misc.description;

                    break;
                case 'V':
                    type = 'Stay';
                    if (transactionSubType === 'Stay') {
                        type = 'Stay - Points earned';
                    }
                    description = activity.misc.description;
                    break;
                default:
                    browserAPI.log('[error]: unknown transaction type was found: ' + transactionType);
                    break;
            }
            row.Type = type;
            row.Description = description;
            // Check-in Date
            if (checkInDatePresent) {
                if (
                    typeof activity.stay !== 'undefined'
                    && typeof activity.stay.startDate !== 'undefined'
                ) {
                    let checkIn = activity.stay.startDate;
                    browserAPI.log("Check-in Date " + checkIn);
                    if (checkIn) {
                        row['Check-in Date'] = new Date(checkIn).getTime() / 1000;
                    }
                }
            }
            // Bonus and Points
            let totalPoints = activity.transaction.totalAmount | '';
            if (type === 'Bonus') {
                row.Bonus = totalPoints;
            } else {
                row.Points = totalPoints;
            }
            params.data.properties.HistoryRows.push(row);
        }

        params.account.properties = params.data.properties;
        provider.saveProperties(params.account.properties);
    },

    getHistoryDate: function (offset) {
        browserAPI.log("getHistoryDate");
        var date = new Date();
        // year
        if (typeof offset != 'undefined')
            date.setFullYear(date.getFullYear() + offset);
        var result = date.getFullYear() + "-";
        // month
        if (/^\d$/.test(date.getMonth() + 1)) {
            result += '0' + (date.getMonth() + 1) + "-";
        } else {
            result += (date.getMonth() + 1) + "-";
        }
        // date
        if (/^\d$/.test(date.getDate())) {
            result += '0' + date.getDate();
        } else {
            result += date.getDate();
        }

        browserAPI.log(">>> historyDate: " + result);
        return result;
    },

    waitItineraries: function(params) {
        browserAPI.log('waitItineraries');
        setTimeout(function () {
            plugin.waitAllItineraries(params);
        }, 5000);
    },

    waitAllItineraries: function (params) {
        let viewMore = $('button:contains("Load More"):visible');
        if (viewMore.length > 0) {
            viewMore.click();
            let counter = 0;
            let itinerariesInterval = setInterval(function () {
                browserAPI.log("waiting itineraries... " + counter);
                let loader = $('div[data-js="loadin-content"]:visible');
                let moreIts = $('button:contains("Load More"):visible');
                if ((counter > 5 && loader.length === 0)
                    //|| moreIts.is(":disabled")
                    || moreIts.length > 0
                    || counter > 50
                ) {
                    clearInterval(itinerariesInterval);
                    setTimeout(function () {
                        moreIts = $('button:contains("Load More"):visible');
                        if (moreIts.length > 0 && !moreIts.is(":disabled")) {
                            plugin.waitAllItineraries(params);
                        } else {
                            let links = $('div[class *= "ReservationCard_container"]');
                            console.log('links: ' + links.length);
                            plugin.parseItineraryLinks(params);
                        }
                    }, 1000);
                }
                counter++;
            }, 500);
        } else {
            browserAPI.log('View More Reservations not found');
            let links = $('div[class *= "ReservationCard_container"]');
            console.log('links: ' + links.length);
            plugin.parseItineraryLinks(params);
        }
    },

    parseItineraryLinks: function (params) {
        browserAPI.log("parseItineraryLinks");
        // plugin.updateAccountMessage();

        // no itineraries
        if ($('div[data-module="upcomingStays"]:contains("You have no upcoming reservations"):visible').length > 0) {
            params.account.properties.Reservations = [{ NoItineraries: true }];
            provider.saveProperties(params.account.properties);
            browserAPI.log("complete");
            provider.complete();
            return;
        }

        let links = $('a[class *= "ReservationCard_stayDetailsButton"]');
        let maxCountOfItineraries = links.length;
        browserAPI.log("Total " + maxCountOfItineraries + " reservations found");
        if (provider.isMobile && maxCountOfItineraries > 20) {
            maxCountOfItineraries = 20;
            browserAPI.log('Parse only ' + maxCountOfItineraries + ' reservations in mobile');
        }
        if (typeof params.data.links === 'undefined') {
            params.data.links = [];
        }
        for (let i = 0; i < maxCountOfItineraries; i++) {
            let link = links.eq(i).attr('href');
            browserAPI.log('Link ' + link);
            params.data.links.push(link);
        }
        provider.saveTemp(params.data);
        browserAPI.log(`params.data.links = ${params.data.links}`);
        provider.setNextStep('parseItineraries', function () {
            window.open('https://www.hyatt.com/en-US/home/', '_self');
        });
    },

    parseItineraries: function (params) {
        browserAPI.log('parseItineraries');
        plugin.updateAccountMessage();
        params.data.Reservations = [];
        browserAPI.log(`params.data.links = ${params.data.links}`);
        setTimeout(function () {
            for (let i = 0; i < params.data.links.length; i++) {
                let link = params.data.links[i];
                let itins = plugin.parseItinerary(link);
                if (itins && itins.length > 0) {
                    params.data.Reservations = params.data.Reservations.concat(itins);
                }
            }
            params.account.properties.Reservations = params.data.Reservations;
            browserAPI.log(JSON.stringify(params.account.properties));
            provider.saveProperties(params.account.properties);
            browserAPI.log('complete');
            provider.complete();
        }, 1000);
    },

    parseItinerary: function (link) {
        browserAPI.log("parseItinerary");
        let res = null;
        $.ajax({
            async: false,
            xhr: plugin.getXMLHttp,
            type: 'GET',
            url: link,
            success: function (data) {
                // browserAPI.log('---------------- success data ----------------');
                // browserAPI.log(data);
                // browserAPI.log('---------------- success data ----------------');
                data = $(data);
                res = plugin.parseMultiItinerary2018(data);
            },
            error: function (data) {
                browserAPI.log('Failed to parse itinerary');
                browserAPI.log('---------------- fail data ----------------');
                browserAPI.log(JSON.stringify(data));
                browserAPI.log('---------------- fail data ----------------');
            }
        });
        return res;
    },

    parseOneRoomItinerary: function(res, roomNode) {
        browserAPI.log('parseOneRoomItinerary');
        res = $.extend(true, {}, res);
        // CheckInDate
        var checkInDate = roomNode.find('dt').filter(function() {
            return $(this).text() === 'Check-in' || $(this).text() === 'Checkin';
        }).next('dd:eq(0)');
        if (checkInDate.length) {
            checkInDate = util.filter(checkInDate.text());
            checkInDate = checkInDate.replace(/\./ig, '');
            checkInDate = util.filter(checkInDate.replace(/pm\s*$/i, ' PM'));
            browserAPI.log("CheckInDate: " + checkInDate);
            // fixed time without minutes: m d, Y H PM
            if (/\d{4}\s*\d+\s*PM/.test(checkInDate)) {
                checkInDate = util.filter(checkInDate.replace(' PM', ':00 PM'));
                browserAPI.log("CheckInDate: " + checkInDate);
            }
            res.CheckInDate = new Date(checkInDate + ' UTC') / 1000;
            browserAPI.log("CheckInDate: " + res.CheckInDate);
        } else {
            browserAPI.log("wrong CheckInDate");
        }
        // CheckOutDate
        var checkOutDate = roomNode.find('dt').filter(function() {
            return $(this).text() === 'Check-out' || $(this).text() === 'Checkout';
        }).next('dd:eq(0)');
        if (checkOutDate.length) {
            checkOutDate = util.filter(checkOutDate.text());
            checkOutDate = checkOutDate.replace(/\./ig, '');
            checkOutDate = util.filter(checkOutDate.replace(/pm\s*$/i, ' PM'));
            browserAPI.log("CheckOutDate: " + checkOutDate);
            // fixed time without minutes: m d, Y H PM
            if (/\d{4}\s*\d+\s*PM/.test(checkOutDate)) {
                checkOutDate = util.filter(checkOutDate.replace(' PM', ':00 PM'));
                browserAPI.log("CheckOutDate: " + checkOutDate);
            }
            res.CheckOutDate = new Date(checkOutDate + ' UTC') / 1000;
            browserAPI.log("CheckOutDate: " + res.CheckOutDate);
        } else {
            browserAPI.log("wrong CheckOutDate");
        }

        var room = roomNode.find('dt:contains("Room") + dd:eq(0)').text();
        // Rooms
        res.Rooms = util.findRegExp( room, /^\s*\((\d+)\)/i);
        browserAPI.log("Rooms: " + res.Rooms);
        // RoomType
        res.RoomType = util.findRegExp( room, /^\s*\(\d+\)\s*([^<]+)/i);
        browserAPI.log("RoomType: " + res.RoomType);
        // RoomTypeDescription
        res.RoomTypeDescription = util.unionArray( roomNode.find('div.special-requests-content:visible > div:not(.b-d-none)') , ', ');
        browserAPI.log("RoomTypeDescription: " + res.RoomTypeDescription);
        // Guests
        res.Guests = util.findRegExp( roomNode.find('dt:contains("Guests") + dd:eq(0)').text(), /(\d+)\s*Guests?/i);
        if (!res.Guests && util.findRegExp(roomNode.find('dt:contains("Guests") + dd:eq(0)').text(), /^\s*Guest\s*$/i))
            res.Guests = 1;
        browserAPI.log("Guests: " + res.Guests);
        // Rate
        res.Rate = util.filter(roomNode.find('dt:contains("Rate") + dd:eq(0)').text());
        browserAPI.log('Rate: ' + res.Rate);
        // GuestNames
        res.GuestNames = util.unionArray( roomNode.find('dt:contains("Name") + dd:eq(0)'), ', ', true);
        if (res.GuestNames) {
            res.GuestNames = util.beautifulName(util.filter(res.GuestNames.replace(/\s*\[\[[^\]]*\]\]\s*/ig, "")));
        }
        browserAPI.log("GuestNames: " + res.GuestNames);
        // AccountNumbers
        res.AccountNumbers = util.unionArray( roomNode.find('dt:contains("World of Hyatt Membership #") + dd:eq(0)'), ', ', true);
        browserAPI.log("AccountNumbers: " + res.AccountNumbers);
        // Total
        res.Total = roomNode.find('span[data-js = "cash-total-price"]').attr('data-price') || null;
        if (res.Total) {
            res.Total = util.findRegExp(res.Total, /^(.+?\.\d{2})/) || res.Total;
        }
        // Currency
        res.Currency = roomNode.find('span[data-js = "cash-total-price"]').attr('data-currency') || null;
        // Cost
        res.Cost = roomNode.find('span[data-js = "subtotal-price"]').attr('data-price') || null;
        if (res.Cost) {
            res.Cost = util.findRegExp(res.Cost, /^(.+?\.\d{2})/) || res.Cost;
        }
        browserAPI.log("Cost: " + res.Cost);
        // Taxes
        res.Taxes = roomNode.find('span[data-js = "taxes-fees-price"]').attr('data-price') || null;
        if (res.Taxes) {
            res.Taxes = util.findRegExp(res.Taxes, /^(.+?\.\d{2})/) || res.Taxes;
        }
        browserAPI.log("Taxes: " + res.Taxes);
        // SpentAwards
        res.SpentAwards = roomNode.find('div.total-points-per-room').find('> div + div').text().trim() || null;

        return res;
    },

    parseMultiItinerary2018: function (data) {
        browserAPI.log("parseMultiItinerary2018");
        var res = [];
        var hotelNodes = data.find('div.p-hotel-stay, div.m-modify-reservation');
        browserAPI.log('Found ' + hotelNodes.length + ' hotel nodes');

        for (var $i = 0; $i < hotelNodes.length; $i++) {
            var hotelItins = plugin.parseOneHotelItineraries(data, $(hotelNodes[$i]));
            if (hotelItins.length > 0) {
                res = res.concat(hotelItins);
            }
        }
        if (res.length > 1) {
            for (var $i = 0; $i < res.length; $i++) {
                var conf = res[$i].ConfirmationNumber;
                if (conf) {
                    res[$i].ConfirmationNumber = conf + '-' + ($i + 1);
                }
            }
        }
        return res;
    },

    parseOneHotelItineraries: function(data, hotelNode) {
        browserAPI.log("parseOneHotelItineraries");
        var res = [];

        var base = {};
        // ConfirmationNumber
        base.ConfirmationNumber = util.findRegExp( data.find('div.m-reservation-header').text(), /Confirmation:\s*#\s*(\d+)/i);
        browserAPI.log("ConfirmationNumber: " + base.ConfirmationNumber);
        // Cancelled
        if (data.find('div.p-cancelled-reservation div:contains("This reservation has been canceled")').length > 0) {
            base.Cancelled = true;
            browserAPI.log("Cancelled: " + base.Cancelled);
        }
        // CancellationPolicy
        base.CancellationPolicy = hotelNode.find('div.cancellation-policy').find('div:contains("Cancellation Policy")').next('div').text();
        // hotel info
        var hotelInfoNodes = hotelNode.find('div.hotel-info-container, div.m-hotel-card');
        var hotelInfoNode = hotelInfoNodes.length === 1 ? $(hotelInfoNodes[0]) : null;
        if (hotelInfoNode) {
            // HotelName
            base.HotelName = util.filter(hotelInfoNode.find('div.b-text_display-1:first').text());
            if (base.HotelName.length === 0) {
                base.HotelName = util.filter(hotelInfoNode.find('span.hotel-name:first').text());
            }
            if (base.HotelName.length === 0) {
                base.HotelName = util.filter(hotelInfoNode.find('span[data-locator]:first').text());
            }
            if (base.HotelName.length === 0) {
                base.HotelName = util.filter(hotelInfoNode.find('span[data-locator]:first').text());
            }
            if (base.HotelName.length === 0) {
                base.HotelName = util.filter(hotelInfoNode.find('div.b-text_style-uppercase:first').text());
            }
            browserAPI.log("HotelName: " + base.HotelName);
            // Address
            browserAPI.log("Address: " + hotelInfoNode.find('.b-row div.b-text_display-1').next('.b-text_copy-3:visible').text());
            base.Address = util.filter(util.unionArray(hotelInfoNode.find('.b-row div.b-text_display-1').next('.b-text_copy-3:visible').find('div'), ', ', false, true));
            if (base.Address.length === 0) {
                base.Address = util.filter(util.unionArray(hotelInfoNode.find('button[data-js = "cancel-button"]').parent('div').prev('div').prev('div'), ', ', false, true));
            }
            if (base.Address.length === 0) {
                base.Address = util.filter(util.unionArray(hotelInfoNode.find('span[data-locator = "hotel-name"]:nth(1)').parent('div').next('div'), ', ', false, true));
                base.Address = util.filter(util.findRegExp(base.Address, /^(.+?)(?:\s*Tel:|$)/i));
            }
            if (base.Address.length === 0) {
                base.Address = util.filter(hotelInfoNode.find('a[data-js = "print-button"]').closest('ul').prev('div').prev('div').text());
            }
            browserAPI.log("Address: " + base.Address);
            // Phone
            base.Phone = util.findRegExp( hotelInfoNode.find('div.b-text_display-1').nextAll().filter('div:contains("Tel:")').text() , /Tel:(.+)/i);
            if (base.Phone === null) {
                base.Phone = util.findRegExp( hotelInfoNode.find('div:contains("Tel:")').text() , /Tel:(.+)/i);
            }

            if (base.Phone) {
                base.Phone = base.Phone.replace('–', '-').replace(new RegExp(/\u2028|\u2029/), '')
                    .replace(new RegExp(/^[+＋]/), '+');
            }

            browserAPI.log("Phone: " + base.Phone);
            // Fax
            base.Fax = util.findRegExp( hotelInfoNode.find('div.m-hotel-card div.b-text_display-1').nextAll().filter('div:contains("Fax:")').text() , /Fax:(.+)/i);
            browserAPI.log("Fax: " + base.Fax);
        } else {
            browserAPI.log('hotelInfoNode not found');
        }

        var roomNodes = hotelNode.find('div.m-reservation-details');
        for (var i = 0; i < roomNodes.length; i++) {
            var itin = plugin.parseOneRoomItinerary(base, $(roomNodes[i]));
            if (itin) {
                res.push(itin);
            }
        }
        return res;
    }

};
