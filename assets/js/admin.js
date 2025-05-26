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

        // // ✅ Serialize tout le formulaire actif (avec structure[...])
        // const formData = $(button).closest('form').serializeArray();

        // // Ajout des champs AJAX obligatoires
        // formData.push({ name: 'action', value: 'csb_process_node' });
        // formData.push({ name: 'nonce', value: csbData.nonce });
        // formData.push({ name: 'post_id', value: postId });


        const formData = $(button).closest('form').serializeArray();

        // 🔧 Ajouter manuellement les champs globaux
        formData.push({ name: 'csb_product', value: $('#csb_product').val() });
        formData.push({ name: 'csb_demographic', value: $('#csb_demographic').val() });

        // ✅ Ajouter les autres champs AJAX
        formData.push({ name: 'action', value: 'csb_process_node' });
        formData.push({ name: 'nonce', value: csbData.nonce });
        formData.push({ name: 'post_id', value: postId });


        $.post(csbData.ajaxurl, formData, function (response) {
            const duration = ((Date.now() - startTime) / 1000).toFixed(1);
            if (response.success) {
                const tokensUsed = response.data.tokens || 0;
                const currentTotal = parseInt($('#csb-token-count').text()) || 0;
                $('#csb-token-count').text(currentTotal + tokensUsed);

                status.html('✅ <a href="' + response.data.link + '" target="_blank">Voir l’article</a>');
                button.text(`✅ Fait (${duration}s)`);
            } else {
                status.text('❌ Erreur');
                button.text('⚠️ Erreur');
                console.warn('Erreur AJAX CSB :', response);
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

    let lastClickedButton = null;

    $('form button[type="submit"]').on('click', function () {
        lastClickedButton = this;
    });

    $('form').on('submit', function (e) {
        if (lastClickedButton && lastClickedButton.name === 'csb_stop_generation') {
            csbStopRequested = true;
            alert('🛑 La génération a été arrêtée.');
        }

        // Réinitialiser après soumission
        lastClickedButton = null;
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
