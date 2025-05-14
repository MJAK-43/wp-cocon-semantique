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
            status.html(
                '✅ <a href="' + response.data.link + '" target="_blank">Voir l’article</a>'
            );
            button.text('✅ Fait');
        } else {
            status.text('❌ Erreur');
            button.text('⚠️ Erreur');
        }

        setTimeout(() => {
            button.prop('disabled', false).text('⚙️ Générer (AJAX)');
        }, 3000);
    });
});
