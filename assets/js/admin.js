console.log('✅ admin.js chargé');

jQuery(document).ready(function ($) {
    // ✅ Génération AJAX pour un seul bouton
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

    // ✅ Génération séquentielle pour tous les boutons
    function processAllNodesSequentially() {
        const buttons = $('.csb-generate-node').toArray();

        function clickNext(index) {
            if (index >= buttons.length) return;

            const btn = $(buttons[index]);
            btn.trigger('click');

            setTimeout(() => clickNext(index + 1), 3500); // délai pour attendre la génération
        }

        clickNext(0);
    }

    // ✅ Bouton "Tout générer"
    if ($('#csb-generate-all').length === 0) {
        $('<button id="csb-generate-all" class="button button-primary" style="margin: 10px 0;">🚀 Tout générer en AJAX</button>')
            .insertBefore('.csb-generate-node:first')
            .on('click', processAllNodesSequentially);
    }
});
