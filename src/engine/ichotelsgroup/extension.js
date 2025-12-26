var plugin = {

    hosts: {
        'www.ichotelsgroup.com': true, 'secure.ichotelsgroup.com': true,
        'secure.priorityclub.com': true, 'www.priorityclub.com': true,
        'www.holidayinn.com': true, 'www.ihg.com': true
    },

    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return "https://www.ihg.com/rewardsclub/us/en/account/home";
    },

    start: function (params) {
        browserAPI.log("start");
        if (typeof (params.account.fromPartner) == 'string') {
            provider.setNextStep('startLogin', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        } else
            plugin.startLogin(params);
    },

    startLogin: function (params) {
        browserAPI.log("startLogin");
        var counter = 0;
        setTimeout(function () {
            var start = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                var isLoggedIn = plugin.isLoggedIn();
                if (isLoggedIn !== null && counter > 2) {
                    clearInterval(start);
                    if (isLoggedIn) {
                        if (plugin.isSameAccount(params.account))
                            plugin.loginComplete(params);
                        else
                            plugin.logout(params);
                    } else
                        plugin.login(params);
                }// if (isLoggedIn !== null)
                if (isLoggedIn === null && counter > 10) {
                    clearInterval(start);
                    provider.setError(util.errorMessages.unknownLoginState);
                    return;
                }// if (isLoggedIn === null && counter > 10)
                counter++;
            }, 500);
        }, 2000);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        var logout = $('a.logOut:visible');
        if (logout.length == 0 &&
            ($('div.logIn > a.logIn-link:visible').length > 0 || $('form#gigya-login-form:visible').length > 0)
        ) {
            browserAPI.log("not logged in");
            return false;
        }
        if (logout.length > 0 || (provider.isMobile && $('span[data-slnm-ihg="memberNumberSID"]:visible').length)) {
            browserAPI.log("logged in");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = $('span[data-slnm-ihg="memberNumberSID"]').text().replace(/\s*/g, "");
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
                && (typeof (account.properties.Number) != 'undefined')
                && (account.properties.Number != '')
                && (number.length > 0)
                && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('startLogin', function () {
            var link = $('a.logOut:visible');
            if (provider.isMobile) {
                link = $('a.logOut');
            }
            if (link.length > 0)
                link.get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof (params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = 'https://www.ihg.com/hotels/us/en/reservation/ManageYourStay';
            });
            return;
        }

        util.waitFor({
            selector: 'form#gigya-login-form:visible',
            success: function (form) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "username"]').val(params.account.login);
                form.find('input[name = "password"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('input[value="Sign in"]').click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 7000);
                });
            },
            fail: function () {
                provider.setError(util.errorMessages.loginFormNotFound);
            },
            timeout: 10
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.gigya-error-msg:visible');
        if (errors.length > 0 && util.filter(errors.text()) != 0)
            provider.setError(util.filter(errors.text()));
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        if (typeof (params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.ihg.com/hotels/us/en/stay-mgmt/ManageYourStay';
            });
        } else {
            if (typeof (params.account.fromPartner) == 'string')
                setTimeout(provider.close, 1000);
            if (params.goto == 'offerIHGRewards')
                document.location.href = 'https://www.awardwallet.com/lib/redirect.php?ID=143';
            provider.complete();
        }
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        plugin.itLoginComplete(params);
        /*
         var link = $('form:has(input[name = "confirmationNumber"][value = "' + params.account.properties.confirmationNumber + '"]) a#viewLink');
         if (link.length > 0) {
         provider.setNextStep('itLoginComplete', function () {
         link[0].click();
         });
         }
         else
         provider.setError(util.errorMessages.itineraryNotFound);
         */
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form:has(input[name = "confirmationNumber"])');
        if (form.length > 0) {
            form.find('input[name="confirmationNumber"]').val(properties.ConfNo);
            util.sendEvent(form.find('input[name="confirmationNumber"]').get(0), 'input');
            form.find('input[name="familyName"]').val(properties.LastName);
            util.sendEvent(form.find('input[name="familyName"]').get(0), 'input');
            provider.setNextStep('itLoginComplete', function () {
                form.find('button[type = "submit"]').click();
            });
        } else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};
