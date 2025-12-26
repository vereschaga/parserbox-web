var plugin = {
    hosts: {
        'www.sixt.com': true,
        'www.sixt.de' : true
    },

    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
            plugin.start(params);
        });
    },

    getStartingUrl: function (params) {
        var domain = 'com';
        if (params.account.login2 == 'Germany')
            domain = 'de';
        browserAPI.log("Domain => " + domain);
        var url = 'https://www.sixt.' + domain + '/#/?page=loginregister';
        browserAPI.log("url => " + url);
        return url;
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
		clearInterval(window.start);
        window.start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(window.start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(window.start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('#customersettings_root button:last svg').find('path[d*="19.18 14.03 20 12 20z"]').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('input#email').closest('form[method="post"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
		browserAPI.log("isSameAccount");
        const cardNumber = util.findRegExp($('button:contains("Booking profile"):visible').find('div>div:eq(1)').text(), /Sixt Card\s*([^<]+)/i);
        browserAPI.log("CardNumber: " + cardNumber);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.CardNumber) != 'undefined')
            && (account.properties.CardNumber !== '')
            && (cardNumber === account.properties.CardNumber));
    },

    logout: function (params) {
        browserAPI.log("logout");
        let btn = $('#customersettings_root button:last svg').find('path[d*="19.18 14.03 20 12 20z"]').closest('button');
        if (btn.length) {
            btn.get(0).click();
            const logout = $('button:contains("Logout")');
            if (logout.length > 0) {
                provider.setNextStep('start', function () {
                    logout.get(0).click();
                    setTimeout(function () {
                        document.location.href = plugin.getStartingUrl(params);
                        document.location.reload();
                    }, 5000);
                });
            }
        }
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('input#email').closest('form[method="post"]');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting login");
        form.find('input#email').val(params.account.login);
        util.sendEvent(form.find('input#email').get(0), 'input');
        provider.setNextStep('loginStepPassword', function () {
            form.find('button[type="submit"]').get(0).click();
            setTimeout(function () {
                plugin.loginStepPassword(params);
            }, 1000);
        });
    },

    loginStepPassword: function (params) {
        browserAPI.log("loginStepPassword");
        util.waitFor({
            selector: 'span:contains("Sign in with password"):visible',
            success: function (elem) {
                elem.get(0).click();
                const form = $('input#password').closest('form[method="post"]');

                if (form.length === 0) {
                    provider.setError(util.errorMessages.passwordFormNotFound);
                    return;
                }

                browserAPI.log("submitting password");
                form.find('input#password').val(params.account.password);
                util.sendEvent(form.find('input#password').get(0), 'input');
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button[type="submit"]').get(0).click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 5000);
                });
            },
            fail: function () {
                provider.setError(util.errorMessages.passwordFormNotFound);
            },
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $(".FieldError__wrapper:visible");

        if (errors.length > 0 && util.trim(errors.text()) !== '') {
            provider.setError(util.trim(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params){
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                util.waitFor({
                    selector: '.TopLayout__manageBookingIconWrapper:visible',
                    success: function (elem) {
                        elem.get(0).click();
                        setTimeout(function () {
                            plugin.toItineraries(params);
                        }, 5000);
                    },
                    fail: function () {
                        provider.setError(util.errorMessages.itineraryNotFound);
                    },
                });
            });
            return;
        }
		provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        const confNo = params.account.properties.confirmationNumber;
        util.waitFor({
            selector: '.BookingOverviewCard__bookingOverviewDetailsWrapperLarge .VehicleDetails__vehicleReservationTile:contains("' + confNo + '")',
            success: function (elem) {
                provider.setNextStep('itLoginComplete', function () {
                    elem.get(0).click();
                    setTimeout(function () {
                        plugin.itLoginComplete(params);
                    }, 3000);
                });
            },
            fail: function () {
                provider.setError(util.errorMessages.itineraryNotFound);
            },
        });
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
