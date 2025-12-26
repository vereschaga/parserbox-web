var plugin = {
	autologin: {
		url: "https://m.malaysiaairlines.com/itravel/ffLogin.xhtml?lang=en",

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
            if($('#ffLogin').length > 0){
                return false;
            }

            if($('#ffLogin\\:button3').length > 0){
                return false;
            }

            if($('#ffLogin\\:button1').length > 0){
                return true;
            }

            throw "can't determine login state";
        },

        login: function () {
            var form = $('#ffLogin');
            var button = $('#ffLogin\\:button3');
            if(form.length == 1 && button.length == 1){
                form.find('#ffLogin\\:ffn').val(params.login.replace('MH', ''));
                form.find('#ffLogin\\:ffPassword').val(params.pass);
                api.setNextStep('checkLoginErrors', function () {
                    button.click();
                });
            } else {
                api.error("can't find login form");
            }
        },

        isSameAccount: function () {
            return (typeof(params.login) !== 'undefined')
                && ($('span:contains("' + params.login.replace('MH', '') + '")').length > 0)
        },

        checkLoginErrors: function () {
            var error = $('.form_error_small');
            if(error.length > 0){
                api.error(error.text().trim());
            } else {
                this.finish();
            }
        },

        logout: function (){
            api.setNextStep('start', function () {
                $('#ffLogin\\:button1').click();
            });
        },

        finish: function () {
            api.complete();
        }
	}
};