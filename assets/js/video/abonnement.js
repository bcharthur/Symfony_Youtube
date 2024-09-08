document.addEventListener('DOMContentLoaded', function() {
    const abonnementForm = document.getElementById('abonnement-form');
    const abonnementButton = document.getElementById('abonnement-button');
    const subscribersCountElem = document.getElementById('subscribers-count');

    let isSubmitting = false; // Pour éviter les soumissions multiples

    if (abonnementForm) {
        abonnementForm.addEventListener('submit', function(event) {
            event.preventDefault();

            if (isSubmitting || abonnementButton.disabled) {
                return; // Empêche les soumissions multiples
            }

            abonnementButton.disabled = true;
            isSubmitting = true;

            // Envoi de la requête AJAX pour gérer l'abonnement/désabonnement
            fetch(abonnementForm.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(response => response.json())
                .then(data => {
                    abonnementButton.disabled = false;
                    isSubmitting = false;

                    // Mise à jour du texte du bouton en fonction de l'état de l'abonnement
                    if (data.abonne) {
                        abonnementButton.textContent = 'Se désabonner';
                    } else {
                        abonnementButton.textContent = 'S\'abonner';
                    }

                    // Mise à jour du compteur d'abonnés
                    subscribersCountElem.textContent = `(${data.subscribers_count}) abonnés`;
                })
                .catch(error => {
                    abonnementButton.disabled = false;
                    isSubmitting = false;
                    console.error('Erreur lors de la mise à jour de l\'abonnement :', error);
                    alert('Erreur lors du traitement de votre demande. Veuillez réessayer plus tard.');
                });
        });
    }
});
