(function (_, $) {
    var uloginNetwork = $('#ulogin_accounts').find('.ulogin_network');
    uloginNetwork.click(function () {
        var network = $(this).attr('data-ulogin-network');
        var identity = $(this).attr('data-ulogin-identity');
        uloginDeleteAccount(network, identity);

        function uloginDeleteAccount(network,identity) {
            jQuery.ajax({
                url: fn_url('ulogin.login'),
                type: 'POST',
                dataType: 'json',
                cache: false,
                data: {
                    identity: identity,
                    network: network
                },
                error: function (data) {
                    alert('Error');
                },
                success: function (data) {
                    if (data.answerType == 'error') {
                        alert(data.msg);
                    }
                    if (data.answerType == 'ok') {
                        var accounts = $('#ulogin_accounts');
                        nw = accounts.find('[data-ulogin-network=' + network + ']');
                        if (nw.length > 0) nw.hide();
                        alert(data.msg);
                    }
                }
            });
        }
    })
})(Tygh, Tygh.$);
