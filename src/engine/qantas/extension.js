var plugin = {

    hosts: {'www.qantas.com': true},

    getStartingUrl: function (params) {
        return 'https://www.qantas.com/gb/en/frequent-flyer/my-account.html';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        // provider bug workaround
        if (document.location.href.indexOf('https://www.qantas.com/fflyer/do/dyns/login') !== -1) {
            browserAPI.log("try to open new site");
            provider.setNextStep('loadLoginForm', function () {
                document.location.href = 'https://www.qantas.com/gb/en.html';
            });
            return;
        }// if (document.location.href.indexOf('https://www.qantas.com/fflyer/do/dyns/login') !== -1)

        setTimeout(function () {
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                var isLoggedIn = plugin.isLoggedIn();
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
                    provider.setError(util.errorMessages.unknownLoginState);
                    return;
                }// if (isLoggedIn === null && counter > 10)
                counter++;
            }, 500);
        }, 2000)
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[name = LSLLoginForm]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('button[name = logoutButton]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp($('div.ql-login-member-details-body strong').text(), /\((\d+)\)/ig);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log('logout');
        provider.setNextStep('loadLoginForm', function () {
            $('button[name = logoutButton]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log('login');
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = "http://www.qantas.com/travel/airlines/your-booking/global/en";
            });
            return;
        }
        var counter = 0;
        var login = setInterval(function () {
            var form = $('form[name = "LSLLoginForm"]:visible');
            browserAPI.log("waiting... " + counter);
            if (form.length > 0) {
                clearInterval(login);
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "memberId"]').val(params.account.login);
                form.find('input[name = "lastName"]').val(params.account.login2);
                form.find('input[name = "memberPin"]').val(params.account.password);

                provider.setNextStep('checkLoginErrors', function () {
                    // reactjs
                    provider.eval(
                        "function doEvent( obj, event ) {"
                        + "var event = new Event( event, {target: obj, bubbles: true} );"
                        + "return obj ? obj.dispatchEvent(event) : false;"
                        + "};"
                        + "var el = document.querySelector('[id=form-member-id-login-menu-frequent-flyer]'); el.value = \"" + params.account.login + "\"; doEvent(el, 'input' );"
                        + "var el = document.querySelector('[id=form-member-surname-login-menu-frequent-flyer]'); el.value = \"" + params.account.login2 + "\"; doEvent(el, 'input' );"
                        + "var el = document.querySelector('[id=form-member-pin-login-menu-frequent-flyer]'); el.value = \"" + params.account.password + "\"; doEvent(el, 'input' );"
                        // + "var el = document.querySelector('#main form[name = \"LSLLoginForm\"] button'); doEvent(el, 'click' );"// not working in safari
                    );
                    $('#main form[name = "LSLLoginForm"] button').click();

                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 7000)
                });
            }
            if (counter > 40) {
                clearInterval(login);
                provider.setError(util.errorMessages.loginFormNotFound);
            }
            counter++;
        }, 500);
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        var errors = $('div.ql-login-error-heading:visible');
        // old site, safari
        if (errors.length == 0)
            errors = $('div.error ul:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log('loginComplete');
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.qantas.com/gb/en/frequent-flyer/your-account/bookings.html';
            });
            return;
        }
        if (typeof(params.account.fromPartner) == 'string') {
            setTimeout(provider.close, 1000);
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log('toItineraries');
        var counter = 0;
        var confNo = params.account.properties.confirmationNumber;
        var toItineraries = setInterval(function() {
            browserAPI.log("waiting... " + counter);
            var link = $('input[value = "' + params.account.properties.confirmationNumber + '"] + input + button:first');
            if (link.length > 0) {
                browserAPI.log('Opening itinerary page...');
                provider.setNextStep('itLoginComplete', function () {
                    link.get(0).click();
                });
            }// if (link.length > 0)
            if (counter > 30) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
            }
            counter++;
        }, 2000);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log('getConfNoItinerary');
        var counter = 0;
        var getConfNoItinerary = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var form = $('form.widget-form:first:visible');
            if (form.length > 0) {
                clearInterval(getConfNoItinerary);
                var properties = params.account.properties.confFields;
                // reactjs
                provider.eval(
                    "var $form = jQuery('form.widget-form:first:visible');"
                    + "var $obj, els = {'Booking or voucher reference field': '" + properties.ConfNo + "', 'Last name field': '" + properties.LastName + "'};"
                    + "for (var i in els) {"
                    + "$obj = jQuery('input[aria-label=\"' + i + '\"]', $form).get(0);"
                    + "for (var k in $obj)"
                    + "if (0 == k.indexOf('__reactInternalInstance')) $obj[k]._currentElement.props.onChange({target: {value: els[i]}}); "
                    + "}"
                );
                provider.setNextStep('itLoginComplete', function () {
                    form.find('input[value = "Continue"]').click();
                });
                return;
            }// if (form.length > 0)
            if (counter > 10) {
                clearInterval(getConfNoItinerary);
                provider.setError(util.errorMessages.itineraryFormNotFound);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
