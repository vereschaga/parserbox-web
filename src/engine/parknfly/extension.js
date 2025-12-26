var plugin = {

    hosts: {
        'parknfly.ca'        : true,
        'www.parknfly.ca'    : true,
        'parknfly.com.au'    : true,
        'www.parknfly.com.au': true,
        'pnf.com'            : true,
        'www.pnf.com'        : true,
        'booking.pnf.com'    : true,
    },

    getStartingUrl: function (params) {
        switch (params.account.login2) {
            case 'Canada':
                return 'https://www.parknfly.ca/members/profile/';
            case 'Australia':
                return 'https://www.parknfly.com.au/loginmanage.aspx';
            case 'USA':
            default:
                return provider.isMobile ? 'https://booking.pnf.com/PNFBooking/account-sign-in' : 'https://booking.pnf.com/PNFBooking/registration/my_account';
        }
    },

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);
            if (isLoggedIn !== null) {
                clearInterval(start);
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
                clearInterval(start);
                provider.logBody("loginPage");
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function (params) {
        browserAPI.log('isLoggedIn');
        switch (params.account.login2) {
            case 'Canada':
                if ($('#nav_sign_in_btn:visible').length > 0) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                if ($('div[data-target="#member-nav"]:visible').length > 0) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                break;
            case 'Australia':
                return $('#ctl00$placeHoldMast$btnLogout, a[href*="/user/myaccount"]').length ? true : false;
            case 'USA':
            default:
                if ($('form[action *= "login"]:visible').length > 0) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                if ($('a[href *= "/logout"]:visible').length > 0) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        let name = null;
        switch (account.login2) {
            case 'USA':
                name = util.findRegExp($("h3:contains('Welcome')").text(), /Welcome(?:\,|!|)\s*([^<]+)/i);
                break;
            case 'Canada':
                name = $('div.welcome-msg').text();
                break;
            case 'Australia':
                break;
        }
        browserAPI.log("name: " + name);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && name
            && (
                    (account.login2 === 'Canada' && -1 < account.properties.Name.toLowerCase().indexOf(name.toLowerCase()))
                    || name.toLowerCase() === account.properties.Name.toLowerCase()
                )
        );
    },

    logout: function (params) {
        browserAPI.log('logout');
        switch (params.account.login2) {
            case 'Canada':
                $('div[data-target="#member-nav"]').click();
                setTimeout(function () {
                    $('a[href="#userSignOut"]').get(0).click();
                    setTimeout(function () {
                        plugin.start(params);
                    }, 2000);
                }, 500);
                break;
            case 'Australia':
                provider.setNextStep('loadLoginForm', function () {
                    provider.eval("__doPostBack('ctl00$LinkButton1','');");
                });
                break;
            case 'USA':
            default:
                provider.setNextStep('loadLoginForm', function () {
                    $('a[href *= "/logout"]').get(0).click();
                });
                break;
        }
    },

    login : function (params) {
        browserAPI.log('login');
        switch (params.account.login2) {
            case 'Canada':
                $('#nav_sign_in_btn:visible').get(0).click();
                setTimeout(function () {
                    let form = $('#header_login_form:visible');
                    if (form.length === 0) {
                        provider.setError(util.errorMessages.loginFormNotFound);
                        return;
                    }
                    form.find('#header_login_username').val(params.account.login);
                    form.find('#header_login_password').val(params.account.password);
                    provider.setNextStep('checkLoginErrors', function () {
                        setTimeout(function () {
                            $('#header_login_submit').click();
                            setTimeout(function () {
                                plugin.checkLoginErrors(params);
                            }, 7000);
                        }, 1000);
                    });
                }, 1000);
                break;
            case 'Australia':
                if (!$('#masterForm').length)
                    break;
                browserAPI.log("submitting saved credentials");
                $('#placeHoldMast_loginemail').val(params.account.login);
                $('#placeHoldMast_loginpassword').val(params.account.password);
                return provider.setNextStep('checkLoginErrors', function () {
                    provider.eval('WebForm_DoPostBackWithOptions(new WebForm_PostBackOptions("ctl00$placeHoldMast$login", "", true, "manageLogin", "", false, true));');
                });
                break;
            case 'USA':
            default:
                browserAPI.log("login");
                if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
                    provider.setNextStep('getConfNoItinerary', function(){
                        document.location.href = 'https://www.pnf.com/manage-reservations';
                    });
                    return;
                }

                const form = $('form[action *= "login"]:visible');

                if (form.length === 0) {
                    provider.setError(util.errorMessages.loginFormNotFound);
                    return;
                }

                browserAPI.log("submitting saved credentials");
                $('#email').val(params.account.login);
                $('#password').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('input[value="Login"]').click();
                    setTimeout(function () {
                        plugin.checkLoginErrors();
                    }, 10000);
                });
                break;
        }
    },

    checkLoginErrors : function (params) {
        browserAPI.log('checkLoginErrors');
        let error;
        switch (params.account.login2) {
            case 'Canada':
                error = $('#header_login_error:visible');
                break;
            case 'Australia':
                error = $('#placeHoldMast_masterLoginError p');
                break;
            case 'USA':
            default:
                error = $('div.alert-danger:visible li');
                break;
        }

        if (error.length && util.filter(error.text()) !== "") {
            provider.setError(util.filter(error.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://booking.pnf.com/PNFBooking/registration/my_account';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        const confNo = params.account.properties.confirmationNumber;
        const link = $('form:has(input[name="bookingref"][value = "' + confNo + '"])');

        if (link.length === 0) {
            provider.setError(util.errorMessages.itineraryNotFound);
            return;
        }// if (link.length > 0)

        provider.setNextStep('itLoginComplete', function () {
            link.submit();
        });
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        const properties = params.account.properties.confFields;
        const form = $('form[action="/PNFBooking/Management/Display"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.itineraryFormNotFound);
            return;
        }

        form.find('input#bookingref').val(properties.ConfNo);
        form.find('input#email').val(properties.Email);
        form.find('input#postcode').val(properties.ZipCode);
        provider.setNextStep('itLoginComplete', function() {
            form.find('button[type="submit"]').click();
        });
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
