//console.log('✅ admin.js chargé');
let csbStopRequested = false;

jQuery(document).ready(function ($) {
    // ✅ Génération AJAX pour un seul bouton
    $('.csb-generate-node').on('click', function () {
        if (csbStopRequested) {
            alert('🛑 Génération stoppée.');
            return;
        }

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
                const tokensUsed = response.data.tokens || 0;
                const currentTotal = parseInt($('#csb-token-count').text()) || 0;
                $('#csb-token-count').text(currentTotal + tokensUsed);

                status.html('✅ <a href="' + response.data.link + '" target="_blank">Voir l’article</a>');
                button.text(`✅ Fait (${duration}s)`);
            } else {
                status.text('❌ Erreur');
                button.text('⚠️ Erreur');
            }

            setTimeout(() => {
                button.prop('disabled', false).text('⚙️ Générer');
            }, 3000);
        });
    });

    // ✅ Génération simultanée de tous les boutons
    function processAllNodesSimultaneously() {
        $('.csb-generate-node').each(function () {
            if (!csbStopRequested) {
                $(this).trigger('click');
            }
        });
    }

    // 🛑 Si le bouton Stop est soumis, activer le flag
    $('form').on('submit', function (e) {
        if ($(this).find('[name="csb_stop_generation"]').length > 0) {
            csbStopRequested = true;
            alert('🛑 La génération a été arrêtée.');
        }
    });

    // ✅ Bouton "Tout générer"
    $('#csb-generate-all').on('click', function () {
        if (csbStopRequested) {
            alert('🛑 Génération stoppée.');
            return;
        }
        $(this).prop('disabled', true).text('⏳ Génération en cours...');
        processAllNodesSimultaneously();
        setTimeout(() => {
            $(this).prop('disabled', false).text('🚀 Tout générer en AJAX');
        }, 4000);
    });
});
