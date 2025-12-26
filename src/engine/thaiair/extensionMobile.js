var plugin = {
	autologin: {
        url: "https://m.thaiairways.com/AMB_ROP/MemberSection?Type=iphone",
        start: function () {
			if(this.isLoggedIn())
                if(this.isSameAccount())
                    api.complete();
                else
                    this.logout();
            else
                this.login();
        },

        isLoggedIn: function () {
            if($('form[action *= "MobileLogOn"]').length > 0){
                return false;
            }

            if($('input#Login').length > 0){
                return false;
            }

            if($('a[href *= "MobileLogOff"]').length > 0){
                return true;
            }

            throw "can't determine login state";
        },

        login: function () {
            var form = $('form[action *= "MobileLogOn"]');
            var button = $('input#Login');
            if(form.length == 1 && button.length == 1){
                form.find('input#MemberID').val(params.login);
                form.find('input#Pin').val(params.pass);
                api.setNextStep('checkLoginErrors', function () {
                    button.click();
                });
            } else {
                api.error("can't find login form");
            }
        },

        isSameAccount: function () {
            return (typeof(params.properties) !== 'undefined')
                && (typeof(params.properties.Number) !== 'undefined')
                && ($('td.detail:contains("' + params.properties.Number + '")').length > 0 ||
                $('td.detail:contains("' + params.login + '")').length > 0)
        },

        checkLoginErrors: function () {
            var error = $('.remark');
            if(error.length > 0){
                api.error(error.text().trim());
            } else {
                api.complete();
            }
        },

        logout: function (){
            api.setNextStep('start', function () {
                document.location.href = $('a[href *= "MobileLogOff"]').attr('href');
            });
        },

        finish: function () {
            api.complete();
        }
	}
};