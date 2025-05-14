jQuery(document).ready(function ($) {
    $('.csb-generate-node').on('click', function () {
        const postId = $(this).data('post-id');
        const button = $(this);
        const status = $('.csb-node-status[data-post-id="' + postId + '"]');

        button.prop('disabled', true).text('⏳ Génération...');

        $.post(csbData.ajaxurl, {
            action: 'csb_process_node',
            nonce: csbData.nonce,
            post_id: postId
        }, function (response) {
            if (response.success) {
                status.text('✅');
                button.text('✅ Fait');
                if (response.data.link) {
                    window.open(response.data.link, '_blank');
                }
            } else {
                status.text('❌');
                button.text('⚠️ Erreur');
            }

            setTimeout(() => {
                status.text('');
                button.prop('disabled', false).text('⚙️ Générer (AJAX)');
            }, 3000);
        });
    });
});
