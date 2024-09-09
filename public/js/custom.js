document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.preview-link').forEach(function(link) {
        link.addEventListener('click', function(event) {
            event.preventDefault();
            var documentId = this.dataset.id;
            var iframe = document.getElementById('previewIframe');
            iframe.src = documentPreviewUrl.replace('DOCUMENT_ID_PLACEHOLDER', documentId);
            $('#previewModal').modal('show');
        });
    });

    $('#previewModal .close').on('click', function() {
        $('#previewModal').modal('hide');
    });

    $(window).on('click', function(event) {
        if ($(event.target).is('#previewModal')) {
            $('#previewModal').modal('hide');
        }
    });
});


document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        document.querySelectorAll('.flash-message').forEach(function(message) {
            message.style.transition = "opacity 1s ease";
            message.style.opacity = 0;
            setTimeout(function() {
                message.remove(); // Supprime complètement l'élément après la transition
            }, 1000); // Attendre la fin de l'animation (1 seconde)
        });
    }, 3000); // 5 secondes avant de démarrer la disparition
});

function supprimer() {
    alert('Élément supprimé');
}

function modifier() {
    alert('Modifier l\'élément');
}

function details() {
    alert('Voir les détails');
}