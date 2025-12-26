var plugin = {

    mobileUserAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML like Gecko) Chrome/68.0.3440.75 Safari/537.36',
    hosts : {
        'vueling.com'         : true,
        'www.vueling.com'     : true,
        'tickets.vueling.com' : true
    },

    getStartingUrl : function (params) {
        return 'https://tickets.vueling.com/HomePrivateArea.aspx';
    },

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl();
        });
    },

    // TODO: You need to reload the page several times to get into the form
    loadLoginForm: function (params) {
        browserAPI.log('loadLoginForm');
        var step = document.location.href === plugin.getStartingUrl() ? 'start' : 'loadLoginForm';
        provider.setNextStep(step, function () {
            document.location.href = plugin.getStartingUrl();
        });
    },

    start: function (params) {
        browserAPI.log('start');
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

    isLoggedIn : function (params) {
        browserAPI.log('isLoggedIn');
        if ($('#memberLoginAndRegister').length) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        if ($('#SignOutHeader').length > 0) {
            browserAPI.log('isLoggedIn: true');
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = $('p.vuelingCard__content__cardData__num').text();
        browserAPI.log("number: " + number);
        return typeof account.properties !== 'undefined'
            && typeof account.properties.Number !== 'undefined'
            && account.properties.Number !== ''
            && number == account.properties.Number;
    },

    logout : function (params) {
        browserAPI.log('logout');
        provider.setNextStep('loadLoginForm', function () {
            $('#SignOutHeader').get(0).click();
        });
    },

    login : function (params) {
        browserAPI.log('login');
        if (    typeof (params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId == 0   ) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = 'https://tickets.vueling.com/RetrieveBooking.aspx?event=change&culture=en-GB';
            });
            return;
        }

        var form = $('#SkySales');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            $('#ControlGroupLoginViewMyVueling_MemberLoginView2LoginViewMyVueling_TextBoxUserID').val(params.account.login);
            $('#ControlGroupLoginViewMyVueling_MemberLoginView2LoginViewMyVueling_PasswordFieldPassword').val(params.account.password);

            provider.setNextStep('checkLoginErrors', function () {
                provider.eval("jQuery('#ControlGroupLoginViewMyVueling_MemberLoginView2LoginViewMyVueling_LinkButtonLogIn').trigger('click');"
                    + "__doPostBack('ControlGroupLoginViewMyVueling$MemberLoginView2LoginViewMyVueling$LinkButtonLogIn','')");
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form#SkySales');
        if (form.length > 0) {
            form.find('input[name = "ControlGroupRetrieveBookingView$BookingRetrieveInputRetrieveBookingView$CONFIRMATIONNUMBER1"]').val(properties.ConfNo);
            form.find('input[name = "ControlGroupRetrieveBookingView$BookingRetrieveInputRetrieveBookingView$CONTACTEMAIL1"]').val(properties.Email);
            provider.setNextStep('itLoginComplete', function () {
                provider.eval("jQuery('#ControlGroupRetrieveBookingView$BookingRetrieveInputRetrieveBookingView$LinkButtonRetrieve').trigger('click');"
                    + "javascript:__doPostBack('ControlGroupRetrieveBookingView$BookingRetrieveInputRetrieveBookingView$LinkButtonRetrieve','')");
                // setTimeout(function() {
                //     form.find('div#ButtonContainer').get(0).click();
                // }, 2000);
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    checkLoginErrors : function (params) {
        browserAPI.log('checkLoginErrors');
        var error = $('.fs_12', '#validationErrorContainerReadAlongList');
        if (error.length && '' != util.trim(error.text()))
            provider.setError(error.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (    typeof (params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId > 0    ) {
            provider.setNextStep('itLoginComplete', function () {
                document.location.href = 'https://tickets.vueling.com/MemberBookingListAsPax.aspx';
            });
            return;
        }
        provider.complete();
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
