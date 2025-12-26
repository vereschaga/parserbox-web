var plugin = {

    // keepTabOpen: true,//todo
    hideOnStart: true,
    hosts: {'www.tada.com': true, 'secure.tada.com': true},
    blockImages: /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor),

    getStartingUrl: function (params) {
        return 'https://www.tada.com/my-account#/';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            // distil workaround
            var distilForm = $('form#distilCaptchaForm:visible');
            if (distilForm.length > 0) {
                clearInterval(start);
                if (provider.isMobile) {
                    provider.command('show', function () {
                        provider.reCaptchaMessage();
                        distilForm.bind('submit', function (event) {
                            provider.command('hide', function () {
                                provider.setNextStep('loadLoginForm', function(){
                                    browserAPI.log("captcha entered by user");
                                });
                            });
                        });
                    });
                }// if (provider.isMobile)
                else {
                    browserAPI.log("waiting...");
                    provider.setNextStep('loadLoginForm', function () {
                        provider.reCaptchaMessage();
                        var distilCounter = 0;
                        var distil = setInterval(function () {
                            browserAPI.log("waiting... " + distilCounter);
                            if (distilCounter > 80) {
                                clearInterval(distil);
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }
                            distilCounter++;
                        }, 500);
                    });
                }
            }// if (distilForm.length > 0)
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout();
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.logBody("lastPage");
                // Sorry, you have been blocked. You are unable to access tada.com
                var error = $('h1:contains("Sorry, you have been blocked"):visible');
                if (error.length == 0)
                    error = $('p:contains("The owner of this website (www.tada.com) has banned you temporarily from accessing this website."):visible');
                if (error.length == 0)
                    error = $('td.contentData:contains("Your requested URL has been blocked by the URL Filter database module of McAfee Web Gateway."):visible');
                if (error.length > 0) {
                    provider.setError([util.filter(error.text()), util.errorCodes.providerError], true);
                    return;
                }
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if (
            $('a:contains("Sign Out")').length > 0
            || $('li.nav-pts:visible').length
        ) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form#signinForm input[name = "email"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        return false;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            //$('a[href *= "logoutall"]').get(0).click();
            document.location.href = 'https://www.tada.com/sign-out';
        });
    },

    loadStart: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = "https://secure.tada.com";
        });
    },

    login: function (params) {
        browserAPI.log("login");
        setTimeout(function() {
            var form = $('form#signinForm');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "email"]').val(params.account.login);
                util.sendEvent( form.find('input[name = "email"]').get(0), 'input');
                form.find('input[name = "password"]').val(params.account.password);
                util.sendEvent( form.find('input[name = "password"]').get(0), 'input');
                provider.setNextStep('checkLoginErrors', function () {
                    var btn = form.find('#submitGroup > button');
                    setTimeout(function() {
                        var captcha = form.find('div#recaptcha-login iframe:visible');
                        browserAPI.log("waiting captcha -> " + captcha.length);
                        if (captcha.length > 0) {
                            browserAPI.log(">>> Captcha was found");
                            if (provider.isMobile) {
                                browserAPI.log(">>> Mobile");
                                provider.command('show', function(){
                                    // provider.reCaptchaMessage();
                                });
                                btn.bind('click', function(event){
                                    browserAPI.log("captcha entered by user");
                                    // provider.command('hide', function () {
                                    // });
                                    // event.preventDefault();
                                });
                            } else {
                                browserAPI.log(">>> Desktop");
                                provider.reCaptchaMessage();
                                browserAPI.log("waiting...");
                                provider.setNextStep('checkLoginErrors', function() {
                                    var counter = 0;
                                    var login = setInterval(function () {
                                        browserAPI.log("waiting... " + counter);
                                        if (counter > 120) {
                                            clearInterval(login);
                                            provider.setError(util.errorMessages.captchaErrorMessage, true);
                                        }
                                        counter++;
                                    }, 500);
                                });
                            }
                        }// if (captcha.length > 0)
                        else {
                            browserAPI.log(">>> Captcha not found");
                            btn.get(0).click();
                            setTimeout(function () {
                                plugin.checkLoginErrors(params);
                            }, 7000);
                        }
                    }, 3000);
                });
            }// if (form.length > 0)
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }, 2000)
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var error = $('div.loginErrorContainer:visible');
        if (error.length > 0)
            provider.setError(error.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (params.autologin) {
            provider.complete();
            return;
        }
        // plugin.waitBalance(params);
    }/*,

    waitBalance: function (params) {
        browserAPI.log("waitBalance");
        var counter = 0;
        var waitBalance = setInterval(function () {
            browserAPI.log("waiting balance... " + counter);
            var pendingPayment = $('strong:contains("Pending Payment"):visible');

            var futurePayments = $('strong:contains("Future Payments"):visible').parents('div:eq(0)').prev('div').find('div[class *= "font-small"]');
            browserAPI.log("pendingPayment: " + pendingPayment.length);
            browserAPI.log("futurePayments: " + futurePayments.length);
            if (provider.isMobile && pendingPayment.length == 0 && futurePayments.length == 0 && counter > 10) {
                clearInterval(waitBalance);
                provider.setNextStep('waitBalance', function () {
                    document.location.reload();
                });
            }// if (provider.isMobile && pendingPayment.length == 0 && futurePayments.length == 0 && counter > 10)
            if (pendingPayment.length > 0 || (!provider.isMobile && futurePayments.length > 0 && counter > 10)
                || (provider.isMobile && futurePayments.length > 0 && counter > 20)) {
            // if (pendingPayment.length > 0 || counter > 15) {
                clearInterval(waitBalance);
                plugin.parse(params);
            }// if (pendingPayment.length > 0 || counter > 10)
            counter++;
        }, 500);
    },

    parse: function (params) {
        provider.updateAccountMessage();
        var data = {};
        // refs #14471
        var futurePayments = $('strong:contains("Future Payments"):visible').parents('div:eq(0)').prev('div').find('div[class *= "font-small"]');
        var totalUnpaidCashBack = $('strong:contains("Total Unpaid Cash Back"):visible').parents('div[class *= "font-small"]:eq(0)').prev('div').find('div[class *= "font-small"]');
        var pendingPayment = $('strong:contains("Pending Payment"):visible').parents('div[class *= "font-small"]:eq(0)').prev('div').find('div[class *= "font-small"]');
        var payoutOn = util.findRegExp( $('span:contains("Payout On"):visible').text(), /(Payout\s*On\s*\d+\/\d+\/\d+)/i);

        var subAccounts = [];
        if (futurePayments.length && totalUnpaidCashBack.length && pendingPayment.length && payoutOn) {
            // Pending Payment
            subAccounts.push({
                "Code": 'tadaPendingPayment',
                "DisplayName": "Pending Payment (" + payoutOn + ")",
                "Balance": util.filter(pendingPayment.text())
            });
            // Future Payments
            subAccounts.push({
                "Code": 'tadaFuturePayments',
                "DisplayName": "Future Payments",
                "Balance": util.filter(futurePayments.text())
            });
        }// if (futurePayments && totalUnpaidCashBack && pendingPayment && payoutOn)
        else {
            // Future Payments
            browserAPI.log("FuturePayments: " + util.filter(futurePayments.text()) );
            data.FuturePayments = util.filter(futurePayments.text());
        }
        // Balance - Total Unpaid Cash Back
        var balance = $('h1[ng-class *= "gpUser.points"]:visible');
        if (totalUnpaidCashBack.length > 0) {
            balance = util.findRegExp( totalUnpaidCashBack.text(), /([\d\.\,\-]+)/i);
            browserAPI.log("Balance: " + balance );
            data.Balance = balance;
        }
        else
            browserAPI.log("Balance not found");

        data.SubAccounts = subAccounts;

        // Name
        var customerId = util.findRegExp( $('script:contains("customerId")').text(), /customerId:\s*"([^"]+)/);
        if (customerId) {
            $.ajax({
                url: "https://services.tada.com/api/Customer/getcustomeraddress?addressTypeId=1&customerId=" + customerId,
                async: false,
                success: function(profileInfo) {
                    browserAPI.log("parse profile info");
                    profileInfo = $(profileInfo);
                    // console.log("---------------- profileInfo ----------------");
                    // console.log(profileInfo[0]);
                    // console.log("---------------- profileInfo ----------------");
                    if (typeof (profileInfo[0]) != 'undefined'
                        && typeof (profileInfo[0].FirstName) != 'undefined' && typeof (profileInfo[0].LastName) != 'undefined') {
                        data.Name = util.beautifulName(profileInfo[0].FirstName + ' ' + profileInfo[0].LastName);
                        browserAPI.log("Name: " + data.Name);
                    }
                    else
                        browserAPI.log("Name not found");
                }// success: function (profileInfo)
            });
        }// if (customerId)

        // save properties
        params.account.properties = data;
        // console.log(params.account.properties);//todo
        provider.saveProperties(params.account.properties);
        provider.complete();
    }
    */
}