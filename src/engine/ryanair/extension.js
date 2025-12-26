var plugin = {

    hosts : {
        'ryanair.com'     : true,
        'www.ryanair.com' : true
    },
    // mobileUserAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.110 Safari/537.36',
    mobileUserAgent: 'Mozilla/5.0 (X11; Linux x86_64; rv:68.0) Gecko/20100101 Firefox/68.0',

    getStartingUrl : function (params) {
        return 'https://www.ryanair.com/gb/en/';
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function() {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        provider.complete();
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn : function () {
        browserAPI.log('isLoggedIn');
        if ($('.ry-header__menu-item-title:contains("Log in"):visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('.ry-header__user-name:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount : function (account) {
        browserAPI.log('isSameAccount');
        return false;
    },

    logout : function (params) {
        browserAPI.log('logout');
        $('.ry-header__user-name:visible').click();
        util.waitFor({
            selector: '.ry-header__menu-dropdown-user__logout',
            success: function(link) {
                link.get(0).click();
                setTimeout(function () {
                    plugin.start(params);
                }, 1000);
            },
            fail: function() {
                browserAPI.log('failed to log out');
            }
        });
    },

    login : function (params) {
        browserAPI.log('login');
        if (typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId == 0
        ) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = 'https://www.ryanair.com/gb/en/check-in';
            });
            return;
        }

        $('.ry-header__menu-item-title:contains("Log in"):visible').click();
        util.waitFor({
            selector: '.auth-popup__content',
            success: function(form) {
                input1 = form.find('input[name = "email"]');
                input2 = form.find('input[name = "password"]');
                input1.val(params.account.login);
                input2.val(params.account.password);

                util.sendEvent(input1.get(0), 'change');
                util.sendEvent(input2.get(0), 'change');
                form.find('button[type = "submit"]').click();
                setTimeout(function() {
                    plugin.checkLoginErrors(params);
                }, 2000);
            },
            fail: function() {
                provider.setError(util.errorMessages.loginFormNotFound);
            }
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        var errors = $('span._error:visible:eq(0)');
        if (errors.length && '' != errors.text().trim()) {
            provider.setError(util.filter(errors.text()));
        } else {
            plugin.loginComplete(params);
        }
    },

    loginComplete : function (params) {
        browserAPI.log('loginComplete');
        if (
            'boolean' == typeof params.account.itineraryAutologin &&
            params.account.itineraryAutologin &&
            params.account.accountId > 0
        ) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://www.ryanair.com/gb/en/trip/manage';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        setTimeout(function () {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('.card-trip-desktop__pnr span:contains("' + confNo + '")').closest('.card-trip-desktop__description').next('.card-trip-desktop__manage-booking').find('button');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function () {
                    link.get(0).click();
                    setTimeout(function () {
                        plugin.itLoginComplete(params)
                    }, 2000);
                });
            }// if (link.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 2000);
    },

    itLoginComplete : function (params) {
        browserAPI.log('itLoginComplete');
        provider.complete();
    },

    getConfNoItinerary : function (params) {
        browserAPI.log('getConfNoItinerary');
        var properties = params.account.properties.confFields;
        $('.welcome-tabs-container lib-retrieve-booking-tab:not(.tab-active)').click();

        util.waitFor({
            selector: '.retrieve-booking-tabs__content form',
            success: function (form) {
                input1 = form.find('input[type="text"]');
                input2 = form.find('input[type="email"]');
                input1.val(properties.ConfNo);
                input2.val(properties.Email);
                util.sendEvent(input1.get(0), 'input');
                util.sendEvent(input2.get(0), 'input');
                form.find('button[type = "submit"]:contains("Retrieve your booking")').click();
                provider.setNextStep('itLoginComplete', function() {
                    plugin.checkLoginErrors(params);
                    setTimeout(function () {
                        plugin.itLoginComplete(params);
                    }, 2000)
                });
            },
            fail: function () {
                provider.setError(util.errorMessages.loginFormNotFound);
            }
        });
    }
};
