jQuery(function($){

    // ── Thank You Page Registration ────────────────────────────────────
    $('#pcp-register-form').on('submit', function(e){
        e.preventDefault();

        var btn = $(this).find('button[type=submit]');
        btn.prop('disabled', true).text('⏳ প্রসেস হচ্ছে...');

        var data = {
            action:       'pcp_register_from_thankyou',
            nonce:        $(this).find('[name=nonce]').val(),
            order_id:     $(this).find('[name=order_id]').val(),
            display_name: $(this).find('[name=display_name]').val(),
            email:        $(this).find('[name=email]').val(),
            password:     $(this).find('[name=password]').val(),
            phone:        $(this).find('[name=phone]').val(),
            ref_code:     $(this).find('[name=ref_code]').val(),
        };

        $.post(pcp_data.ajax_url, data, function(res){
            if(res.success){
                $('#pcp-register-form').hide();
                $('#pcp-register-success').fadeIn();
            } else {
                btn.prop('disabled', false).text('✅ অ্যাকাউন্ট তৈরি করুন ও পয়েন্ট নিন');
                alert('❌ ' + (res.data.message || 'একটি সমস্যা হয়েছে। আবার চেষ্টা করুন।'));
            }
        }).fail(function(){
            btn.prop('disabled', false).text('✅ অ্যাকাউন্ট তৈরি করুন ও পয়েন্ট নিন');
            alert('❌ সংযোগ সমস্যা। পুনরায় চেষ্টা করুন।');
        });
    });

    // ── Checkout — Redeem Points Toggle ───────────────────────────────
    var toggleBtn = $('#pcp-toggle-redeem');
    if (toggleBtn.length) {
        toggleBtn.attr('data-original-text', toggleBtn.text());
    }

    toggleBtn.on('click', function(){
        var btn    = $(this);
        var active = btn.data('active') === 1 ? 0 : 1;

        btn.prop('disabled', true).text('⏳...');

        $.post(pcp_data.ajax_url, {
            action: 'pcp_toggle_redeem',
            nonce:  pcp_data.nonce,
            active: active
        }, function(res){
            if(res.success){
                $(document.body).trigger('update_checkout');
                btn.data('active', active);
                if(active){
                    btn.addClass('pcp-active').text('✅ পয়েন্ট প্রয়োগ হয়েছে — বাতিল করুন');
                } else {
                    btn.removeClass('pcp-active').text(btn.attr('data-original-text') || '🎁 পয়েন্ট দিয়ে ছাড় নিন');
                }
            }
            btn.prop('disabled', false);
        });
    });

    // ── Referral link copy ────────────────────────────────────────────
    $(document).on('click', '.pcp-copy-ref', function(){
        var url = $(this).data('url');
        if(navigator.clipboard){
            navigator.clipboard.writeText(url);
        }
    });

    // ── Points History — AJAX Pagination ─────────────────────────────
    var $pagination = $('#pcp-history-pagination');

    if ($pagination.length) {
        var currentPage  = 1;
        var totalPages   = parseInt($pagination.data('total'), 10);
        var nonce        = $pagination.data('nonce');
        var $list        = $('#pcp-history-list');
        var $prevBtn     = $('#pcp-history-prev');
        var $nextBtn     = $('#pcp-history-next');
        var $pageInfo    = $('#pcp-history-page-info');

        function updatePaginationUI() {
            $prevBtn.prop('disabled', currentPage <= 1);
            $nextBtn.prop('disabled', currentPage >= totalPages);
            $pageInfo.text(currentPage + ' / ' + totalPages);
        }

        function loadPage(page) {
            $list.css('opacity', '0.5');
            $prevBtn.prop('disabled', true);
            $nextBtn.prop('disabled', true);

            $.post(pcp_data.ajax_url, {
                action: 'pcp_get_history',
                nonce:  nonce,
                page:   page,
            }, function(res){
                if(res.success){
                    currentPage  = res.data.current_page;
                    totalPages   = res.data.total_pages;
                    $list.html(res.data.html);
                    updatePaginationUI();
                    // Scroll to top of history section smoothly
                    $('html, body').animate({
                        scrollTop: $list.offset().top - 80
                    }, 300);
                }
                $list.css('opacity', '1');
            }).fail(function(){
                $list.css('opacity', '1');
                updatePaginationUI();
            });
        }

        $prevBtn.on('click', function(){
            if(currentPage > 1) loadPage(currentPage - 1);
        });

        $nextBtn.on('click', function(){
            if(currentPage < totalPages) loadPage(currentPage + 1);
        });
    }
});