var plugin = {
    mobileUserAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML like Gecko) Chrome/68.0.3440.75 Safari/537.36',
    hosts: {
        'www.msccruises.com.au': true,
        'www.msccruises.be': true,
        'www.msccruzeiros.com.br': true,
        'www.msccruises.de': true,
        'www.msccrociere.it': true,
        'www.msccruceros.es': true,
        'www.msccruisesusa.com': true,
        'www.msccruises.co.uk': true,
        'login.microsoftonline.com': true,
        'mscb2cprod.b2clogin.com': true,
    },

    getStartingUrl: function (params) {
        // Old Site
        if (params.account.login2 === 'au')
            return 'https://www.msccruises.com.au/manage-booking/manage-your-booking';
        if (params.account.login2 === 'be')
            return 'https://www.msccruises.be/beheer-uw-boeking/beheer-uw-boeking';
        if (params.account.login2 === 'de')
            return 'https://www.msccruises.de/buchung-verwalten/buchung-verwalten';
        if (params.account.login2 === 'it')
            return 'https://www.msccrociere.it/la-mia-prenotazione/gestisci-prenotazione';
        if (params.account.login2 === 'es')
            return 'https://www.msccruceros.es/mi-reserva/mi-crucero';
        if (params.account.login2 === 'br')
            return 'https://www.msccruzeiros.com.br/gerenciar-reserva/gerenciar-sua-reserva';
        if (params.account.login2 === 'uk')
            return 'https://www.msccruises.co.uk/manage-booking/manage-your-booking';
        return 'https://www.msccruisesusa.com/manage-booking/manage-your-booking';
    },

    isNewSite: function (params) {
        return params.account.login2 === 'br' || params.account.login2 === 'de' ||
            params.account.login2 === 'it' || params.account.login2 === 'es' || params.account.login2 === 'uk' ||
            params.account.login2 === 'be' || params.account.login2 === 'au' ||
            params.account.login2 === 'us';
    },

    loadLoginForm: function (params, redirectUrl) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            if (redirectUrl) {
                document.location.href = redirectUrl;
            } else {
                document.location.href = plugin.getStartingUrl(params);
            }
        });
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);

            if ($('#error-message :contains("500 INTERNAL SERVER ERROR"):visible').length > 0) {
                plugin.loadLoginForm(params);
                return;
            }
            let redirectUrl = $('a[href*="/Account/SignIn?ReturnUrl="]:visible');
            if (redirectUrl.length > 0) {
                plugin.loadLoginForm(params, redirectUrl.attr('href'));
                return;
            }

            var isLoggedIn = plugin.isLoggedIn(params);
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else {
                    if (plugin.isNewSite(params)) {
                        // provider.setNextStep('preLoginTwo', function () {
                        //     var link = $('a#login_link');
                        //     if (link.length)
                        //         link.get(0).click();
                        // });
                        plugin.login(params);
                    } else {
                        provider.setNextStep('preLogin', function () {
                            var link = $('a#myBookingLink:contains("LOG")');
                            if (link.length)
                                link.get(0).click();
                        });
                    }
                }
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if (plugin.isNewSite(params)) {
            if ($('.login-registration__login:visible, .error-page__editorial:visible').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            if ($('a#signoutUrl:visible').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
        } else {
            var menu = $('#myBookingLink');
            if (menu.length > 0 && $('a#myBookingLink:contains("LOG")').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            if (menu.length > 0 && $('a#myBookingLink:not(:contains("LOG"))').length > 0) {
                browserAPI.log("LoggedIn");
                menu.get(0).click();
                return true;
            }
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var name = util.trim($('a#myBookingLink, div.columns.as-profile-text').text());
        browserAPI.log("name: " + name);
        return ((typeof (account.properties) !== 'undefined')
                && (typeof (account.properties.Name) !== 'undefined')
                && (account.properties.Name !== '')
                && name
                && (name.toLowerCase() === account.properties.Name.toLowerCase()));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            var logout;
            if (plugin.isNewSite(params)) {
                logout = $('a#signoutUrl:visible');
                if (logout.length > 0) {
                    logout.get(0).click();
                }
            } else {
                var counter = 0;
                logout = setInterval(function () {
                    browserAPI.log("logout waiting... " + counter);
                    var logoutLink = $('iframe#frmMYB').contents().find(
                        "a:contains('Logout'):visible, a:contains('Firma'):visible, a:contains('Sign Out'):visible, a:contains('Uitloggen'):visible, a:contains('Salir'):visible"
                    );
                    if (logoutLink.length > 0 || counter > 30) {
                        clearInterval(logout);
                        logoutLink.get(0).click();
                    }// if (logout.length > 0 || counter > 30)
                    counter++;
                }, 500);
            }
        });
    },

    preLoginTwo: function (params) {
        browserAPI.log("preLoginTwo");
        var signIn = $('a[href*="/Account/SignIn?"]:eq(0)');
        if (signIn.length > 0) {
            provider.setNextStep('preLoginTwo', function () {
                signIn.get(0).click()
            });
        }
        else
            plugin.login(params);
    },

    preLogin: function (params) {
        browserAPI.log("preLogin");
        var signIn = $('#Logon');
        if (signIn.length > 0) {
            provider.setNextStep('preLogin', function () {
                $('#Logon').submit();
            });
        }
        else
            plugin.login(params);
    },

    login: function (params) {
        browserAPI.log("login");
        // wait login form
        var counter = 0;
        var login = setInterval(function () {
            var form = $('div.login, .login-registration__login');
            browserAPI.log("waiting... " + counter);
            if (form.length > 0) {
                clearInterval(login);
                browserAPI.log("submitting saved credentials");
                form.find('input[id = "signInName"]').val(params.account.login);
                form.find('input[id = "password"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button#next').get(0).click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 5000);
                });
            }
            if (counter > 15) {
                clearInterval(login);
                provider.setError(util.errorMessages.loginFormNotFound);
            }
            counter++;
        }, 500);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.error:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '')
            provider.setError(util.filter(errors.text()));
        else
            plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof (params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('loadToItineraries', function () {
                if (params.account.login2 === 'br')
                    document.location.href = 'https://www.msccruzeiros.com.br/my%20area/all%20my%20cruises';
                else if (params.account.login2 === 'au')
                    document.location.href = 'https://www.msccruises.com.au/my-msc/plan-my-cruise#/MyCruise';
                else if (params.account.login2 === 'de')
                    document.location.href = 'https://www.msccruises.de/my%20area/all%20my%20cruises';
                else if (params.account.login2 === 'it')
                    document.location.href = 'https://www.msccrociere.it/my%20area/all%20my%20cruises';
                else if (params.account.login2 === 'it')
                    document.location.href = 'https://www.msccruceros.es/my%20area/account%20settings';
                else if (params.account.login2 === 'uk')
                    document.location.href = 'https://www.msccruises.co.uk/my%20area/all%20my%20cruises';
                else if (params.account.login2 === 'us')
                    document.location.href = 'https://www.msccruisesusa.com/my%20area/all%20my%20cruises';
                else
                    plugin.loadToItineraries(params);
            });
            return;
        }
        provider.complete();
    },

    loadToItineraries: function(params) {
        var counter = 0;
        var loginComplete = setInterval(function () {
            browserAPI.log("loginComplete waiting... " + counter);
            // if ($('select[ng-model="OM.bookSelected"] option[selected]').length > 0 || counter > 30) {
            if ($('.my-bookings-cabins-title:visible, section.tile-container--mymsc button.button:visible').length > 0 || counter > 30) {
                clearInterval(loginComplete);
                plugin.toItineraries(params);
            }// if ($('div:contains("Cabin Details"):visible').length > 0 || counter > 30)
            counter++;
        }, 500);
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        var confNo = params.account.properties.confirmationNumber;
        var link;
        if (plugin.isNewSite(params)) {
            link = $('.tile--mymsc-cruises__details:contains("' + confNo + '")').next('div').find('button.button');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function () {
                    link.get(0).click();
                    plugin.itLoginComplete(params);
                });
            }// if (link.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        } else {
            link = $('select[ng-model="OM.bookSelected"] option:contains("' + confNo + '")');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function () {
                    var inputCode = (
                        'var scope = angular.element(document.querySelector("select[ng-model*=bookSelected]")).scope();' +
                        "for (key in scope.OM.bookList) {" +
                        "   if (scope.OM.bookList[key].bookingNumber == \"" + confNo + "\") {" +
                        "       scope.OM.bookSelected = scope.OM.bookList[key];" +
                        "       scope.OM.loadBook();" +
                        "       break;" +
                        "   }" +
                        "}" +
                        "scope.OM.loadBook();"
                    );
                    provider.eval(inputCode);
                    plugin.itLoginComplete(params);
                });
            }// if (link.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
