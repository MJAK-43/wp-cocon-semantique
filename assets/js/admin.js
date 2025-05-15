//console.log('✅ admin.js chargé');

jQuery(document).ready(function ($) {
    // ✅ Génération AJAX pour un seul bouton
    $('.csb-generate-node').on('click', function () {
        const postId = $(this).data('post-id');
        const button = $(this);
        const status = $('.csb-node-status[data-post-id="' + postId + '"]');

        const startTime = Date.now();

        button.prop('disabled', true).text('⏳ Génération...');

        $.post(csbData.ajaxurl, {
            action: 'csb_process_node',
            nonce: csbData.nonce,
            post_id: postId
        }, function (response) {
            const duration = ((Date.now() - startTime) / 1000).toFixed(1); // ⏱️ End
            if (response.success) {
                status.html('✅ <a href="' + response.data.link + '" target="_blank">Voir l’article</a>');
                button.text(`✅ Fait (${duration}s)`);
            } else {
                status.text('❌ Erreur');
                button.text('⚠️ Erreur');
            }

            setTimeout(() => {
                button.prop('disabled', false).text('⚙️ Générer (AJAX)');
            }, 3000);
        });
    });

    // ✅ Génération simultanée de tous les boutons
    function processAllNodesSimultaneously() {
        $('.csb-generate-node').each(function () {
            $(this).trigger('click');
        });
    }

    // ✅ Bouton "Tout générer"
    $('#csb-generate-all').on('click', function () {
        $(this).prop('disabled', true).text('⏳ Génération en cours...');
        processAllNodesSimultaneously();
        setTimeout(() => {
            $(this).prop('disabled', false).text('🚀 Tout générer en AJAX');
        }, 4000);
    });
});
