jQuery(function($){
    // Adjust points modal
    $(document).on('click', '.pcp-adjust-btn', function(){
        var btn = $(this);
        $('#pcp-modal-login').text(btn.data('login'));
        $('#pcp-modal-balance').text(btn.data('balance'));
        $('#pcp-modal-user-id').val(btn.data('user'));
        $('#pcp-modal-amount').val('');
        $('#pcp-modal-reason').val('');
        $('#pcp-modal-msg').hide();
        $('#pcp-adjust-modal').css('display','flex');
    });

    $('#pcp-modal-cancel').on('click', function(){
        $('#pcp-adjust-modal').hide();
    });

    $('#pcp-modal-save').on('click', function(){
        var userId = $('#pcp-modal-user-id').val();
        var amount = $('#pcp-modal-amount').val();
        var reason = $('#pcp-modal-reason').val() || 'Admin manual adjustment';

        if(!amount || amount == 0){
            alert('Please enter a non-zero amount');
            return;
        }

        $.post(pcp_admin.ajax_url, {
            action: 'pcp_admin_adjust_points',
            nonce:  pcp_admin.nonce,
            user_id: userId,
            amount:  amount,
            reason:  reason
        }, function(res){
            if(res.success){
                $('#pcp-modal-msg').text('✅ ' + res.data.message).show();
                $('#pcp-modal-balance').text(res.data.balance);
                setTimeout(function(){ location.reload(); }, 1500);
            } else {
                $('#pcp-modal-msg').css('color','red').text('❌ ' + (res.data.message || 'Error')).show();
            }
        });
    });

    // Close modal on backdrop click
    $('#pcp-adjust-modal').on('click', function(e){
        if($(e.target).is('#pcp-adjust-modal')) $(this).hide();
    });
});
